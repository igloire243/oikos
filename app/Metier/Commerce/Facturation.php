<?php

namespace App\Metier\Commerce;

use App\Metier\Console\Reglages;
use App\Metier\Journal\Journal;
use App\Metier\Licence\Rappel;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\PeriodeAbonnement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ENCAISSER — seul écrivain de `factures` et de `paiements`.
 *
 * ============================================================================================
 * LA FACTURE DÉCIDE, PAS LE PAIEMENT
 * ============================================================================================
 * La facture naît avec la vente, au prix figé, que rien n'ait encore été payé. Les versements s'y
 * imputent — partiels autant qu'il faut — et c'est la FACTURE qui dit ce qui reste dû. L'ancienne
 * console faisait l'inverse : un « statut de paiement » saisi à la main sur l'abonnement, sans
 * trace de ce qui avait été reçu ni de quand.
 *
 * ET L'ACCÈS NE SUIT PAS L'ARGENT TOUT SEUL. Une facture en retard se SIGNALE, elle ne coupe rien :
 * on ne prive pas une église de ses données le jour où une facture traîne (le produit garde la
 * lecture ouverte, P1). Couper est une décision — résilier un abonnement — et elle se trace.
 *
 * ============================================================================================
 * L'ORDRE DES ÉCRITURES : l'argent, puis l'accès, puis le rappel — le rappel HORS transaction.
 * ============================================================================================
 * Le rappel est un appel réseau vers le serveur du client, jusqu'à six secondes. Dans la
 * transaction, il garderait un verrou sur la facture pendant qu'on attend un serveur injoignable ;
 * et un échec réseau annulerait un encaissement pourtant réel. Il ne lève jamais (`Rappel`).
 */
class Facturation
{
    /** Émise dans la transaction de la vente : une vente sans facture n'existerait pas. */
    public static function emettre(PeriodeAbonnement $periode, ?User $par = null): Facture
    {
        $aujourdhui = Carbon::today();
        $annee = $aujourdhui->year;

        // Le numéro se calcule sous verrou ; l'index unique est le filet si deux ventes se croisent.
        $dernier = Facture::query()->lockForUpdate()->where('numero', 'like', "FAC-{$annee}-%")->max('numero');
        $suite = $dernier === null ? 1 : ((int) substr((string) $dernier, -5)) + 1;

        return Facture::query()->create([
            'numero' => sprintf('FAC-%d-%05d', $annee, $suite),
            'jeton_paiement' => Str::random(32),
            'periode_abonnement_id' => $periode->id,
            'montant_centimes' => $periode->montant_centimes,
            'devise' => $periode->devise,
            'emise_le' => $aujourdhui,
            'echeance_le' => $aujourdhui->copy()->addDays(Reglages::valeur('echeance_jours')),
            'emise_par_id' => $par?->id,
        ]);
    }

    /**
     * Enregistre un versement. Refusé : un montant nul, un moyen inconnu, une date dans le futur,
     * une référence déjà connue, ou un TROP-PERÇU — il ferait dire « soldée » à une facture qui l'est
     * déjà, et personne ne retrouverait d'où vient la différence.
     */
    public static function encaisser(
        Facture $facture,
        int $montantCentimes,
        string $moyen,
        ?string $reference,
        Carbon $recuLe,
        ?string $notes,
        ?User $par,
    ): Paiement {
        $reference = $reference !== null && trim($reference) !== '' ? trim($reference) : null;

        if ($montantCentimes <= 0) {
            throw ValidationException::withMessages(['montant' => 'Le montant doit être supérieur à zéro.']);
        }
        if (! array_key_exists($moyen, Paiement::MOYENS)) {
            throw ValidationException::withMessages(['moyen' => 'Moyen de paiement inconnu.']);
        }
        if ($recuLe->copy()->startOfDay()->gt(Carbon::today())) {
            throw ValidationException::withMessages(['recu_le' => 'Un versement ne peut pas être reçu dans le futur.']);
        }

        $paiement = DB::transaction(function () use ($facture, $montantCentimes, $moyen, $reference, $recuLe, $notes, $par) {
            // Sous verrou : deux encaissements simultanés ne doivent pas dépasser, à eux deux, ce qui reste dû.
            $facture = Facture::query()->lockForUpdate()->findOrFail($facture->id);

            if ($reference !== null && ($existant = Paiement::query()->where('reference', $reference)->with('facture')->first())) {
                throw ValidationException::withMessages(['reference' => $existant->estRecu()
                    ? "Cette référence est déjà enregistrée (facture {$existant->facture->numero}) : le même versement ne s'encaisse pas deux fois."
                    : "Cette référence a été marquée « non reçue » sur la facture {$existant->facture->numero} : rétablissez ce versement plutôt que d'en saisir un second."]);
            }

            $restant = $facture->restantCentimes();
            if ($restant === 0) {
                throw ValidationException::withMessages(['montant' => "La facture {$facture->numero} est déjà soldée."]);
            }
            if ($montantCentimes > $restant) {
                throw ValidationException::withMessages(['montant' => 'Il ne reste que '.Montant::formater($restant, $facture->devise)
                    ." à payer sur {$facture->numero} : un trop-perçu ne s'enregistre pas."]);
            }

            $paiement = $facture->paiements()->create([
                'montant_centimes' => $montantCentimes,
                'moyen' => $moyen,
                'reference' => $reference,
                'recu_le' => $recuLe->toDateString(),
                'notes' => $notes,
                'saisi_par_id' => $par?->id,
            ]);

            Journal::tracer('PAIEMENT_ENREGISTRE', $paiement,
                Montant::formater($montantCentimes, $facture->devise)." reçus sur {$facture->numero}", [
                    'facture' => $facture->numero,
                    'moyen' => $moyen,
                    'reference' => $reference,
                    'reste_du' => Montant::formater($restant - $montantCentimes, $facture->devise),
                ], $par);

            return $paiement;
        });

        self::prevenir($paiement->facture);

        return $paiement;
    }

    /** Un versement annoncé mais jamais arrivé : on le marque, on ne l'efface pas. */
    public static function marquerNonRecu(Paiement $paiement, string $motif, ?User $par): void
    {
        if (! $paiement->estRecu()) {
            throw ValidationException::withMessages(['motif' => 'Ce versement est déjà marqué « non reçu ».']);
        }

        $paiement->forceFill(['non_recu_le' => Carbon::now(), 'motif_non_recu' => $motif])->save();

        Journal::tracer('PAIEMENT_NON_RECU', $paiement,
            Montant::formater($paiement->montant_centimes, $paiement->facture->devise)." marqués non reçus sur {$paiement->facture->numero}", [
                'facture' => $paiement->facture->numero,
                'reference' => $paiement->reference,
                'motif' => $motif,
            ], $par);

        self::prevenir($paiement->facture);
    }

    /** Le versement est finalement arrivé : il compte de nouveau, sans dépasser ce qui est dû. */
    public static function retablir(Paiement $paiement, ?User $par): void
    {
        if ($paiement->estRecu()) {
            throw ValidationException::withMessages(['motif' => 'Ce versement compte déjà.']);
        }

        DB::transaction(function () use ($paiement, $par) {
            $facture = Facture::query()->lockForUpdate()->findOrFail($paiement->facture_id);

            if ($paiement->montant_centimes > $facture->restantCentimes()) {
                throw ValidationException::withMessages(['motif' => "Rétablir ce versement dépasserait ce qui est dû sur {$facture->numero}."]);
            }

            $paiement->forceFill(['non_recu_le' => null, 'motif_non_recu' => null])->save();

            Journal::tracer('PAIEMENT_RETABLI', $paiement,
                Montant::formater($paiement->montant_centimes, $facture->devise)." rétablis sur {$facture->numero}", [
                    'facture' => $facture->numero,
                    'reference' => $paiement->reference,
                ], $par);
        });

        self::prevenir($paiement->facture);
    }

    /** Dernier temps : l'installation se resynchronise. Ne lève jamais, et jamais dans une transaction. */
    public static function prevenir(Facture $facture): void
    {
        $installation = $facture->periode->abonnement->installation;

        Rappel::prevenir($installation);
    }
}
