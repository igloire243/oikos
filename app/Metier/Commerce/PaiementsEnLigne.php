<?php

namespace App\Metier\Commerce;

use App\Metier\Commerce\Passerelles\Flutterwave;
use App\Metier\Commerce\Passerelles\Passerelle;
use App\Metier\Commerce\Passerelles\PasserelleSimulee;
use App\Metier\Journal\Journal;
use App\Models\DemandePaiement;
use App\Models\Facture;
use App\Models\Paiement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * PAYER EN LIGNE — ÉTEINT PAR DÉFAUT (`PAIEMENT_EN_LIGNE=true` pour l'allumer).
 *
 * ============================================================================================
 * LE FOURNISSEUR CONFIRME, LE NAVIGATEUR NE PROUVE RIEN
 * ============================================================================================
 * Le client revient sur notre site après avoir payé — ou en tapant l'adresse à la main. Ce retour
 * ne sert qu'à AFFICHER ; l'argent n'entre que par `confirmer()`, qui INTERROGE le fournisseur et
 * vérifie le montant et la devise avant d'écrire quoi que ce soit.
 *
 * `confirmer()` est idempotent : la notification du fournisseur, le retour du client et un
 * rechargement de page arrivent dans n'importe quel ordre, parfois ensemble. Le même versement
 * s'encaisse une fois.
 *
 * Et c'est `Facturation::encaisser()` qui écrit : une seule porte vers `paiements`, avec ses
 * refus (trop-perçu, facture soldée, référence connue). Quand elle refuse alors que le client A
 * PAYÉ — il a payé deux fois, ou en espèces entre-temps —, la demande est marquée échouée AVEC
 * ce motif et tracée au journal : c'est de l'argent à rendre, et personne ne doit l'ignorer.
 */
class PaiementsEnLigne
{
    /** Les noms que l'URL de notification accepte. */
    public const PASSERELLES = ['simulee', 'flutterwave'];

    public static function actif(): bool
    {
        return (bool) config('oikos.paiement_en_ligne', false);
    }

    public static function passerelle(?string $nom = null): Passerelle
    {
        $nom ??= (string) config('oikos.passerelle_paiement', 'simulee');

        return match ($nom) {
            'simulee' => app()->isProduction()
                ? throw new \RuntimeException('La passerelle simulée est interdite en production.')
                : new PasserelleSimulee,
            'flutterwave' => new Flutterwave,
            default => throw new \RuntimeException("Passerelle de paiement inconnue : {$nom}."),
        };
    }

    /** L'adresse publique que le client reçoit — null quand le paiement en ligne est éteint. */
    public static function lienPour(Facture $facture): ?string
    {
        if (! self::actif() || $facture->jeton_paiement === null || $facture->restantCentimes() === 0) {
            return null;
        }

        return route('paiement.afficher', $facture->jeton_paiement);
    }

    /** Une tentative pour TOUT ce qui reste dû — pas de montant libre : un partiel se saisit à la main. */
    public static function initier(Facture $facture): string
    {
        if (! self::actif()) {
            throw ValidationException::withMessages(['facture' => 'Le paiement en ligne est indisponible.']);
        }

        $restant = $facture->restantCentimes();
        if ($restant === 0) {
            throw ValidationException::withMessages(['facture' => "La facture {$facture->numero} est déjà soldée."]);
        }

        $passerelle = self::passerelle();

        $demande = DemandePaiement::query()->create([
            'facture_id' => $facture->id,
            'reference' => 'PAY-'.Str::upper(Str::random(14)),
            'montant_centimes' => $restant,
            'devise' => $facture->devise,
            'passerelle' => (string) config('oikos.passerelle_paiement', 'simulee'),
        ]);

        return $passerelle->initier($demande);
    }

    /**
     * Interroge le fournisseur et, s'il confirme, encaisse. Rend la demande à jour.
     */
    public static function confirmer(DemandePaiement $demande): DemandePaiement
    {
        if ($demande->statut !== DemandePaiement::EN_ATTENTE) {
            return $demande;
        }

        $reponse = self::passerelle($demande->passerelle)->verifier($demande);

        if ($reponse === null || $reponse['statut'] === 'EN_ATTENTE') {
            return $demande;
        }

        if ($reponse['statut'] !== 'PAYE') {
            return self::clore($demande, DemandePaiement::ECHOUEE, 'Le paiement a été refusé ou abandonné.');
        }

        if ($reponse['montant_centimes'] !== $demande->montant_centimes || $reponse['devise'] !== $demande->devise) {
            Journal::tracer('PAIEMENT_EN_LIGNE_ANOMALIE', $demande,
                "Montant ou devise inattendus sur {$demande->reference} : à vérifier chez le fournisseur", [
                    'attendu' => Montant::formater($demande->montant_centimes, $demande->devise),
                    'recu' => Montant::formater($reponse['montant_centimes'], $reponse['devise']),
                ]);

            return self::clore($demande, DemandePaiement::ECHOUEE, 'Le montant confirmé ne correspond pas : à vérifier chez le fournisseur.');
        }

        // Hors transaction : `encaisser()` rappelle le serveur du client, et ce rappel ne doit jamais
        // garder un verrou. Le filet contre le double encaissement est la référence UNIQUE du fournisseur.
        $demande = $demande->fresh() ?? $demande;
        if ($demande->statut !== DemandePaiement::EN_ATTENTE) {
            return $demande;
        }

        try {
            $paiement = Facturation::encaisser(
                $demande->facture,
                $demande->montant_centimes,
                'EN_LIGNE',
                $reponse['reference_externe'] ?? $demande->reference,
                Carbon::today(),
                "Paiement en ligne {$demande->reference}",
                null,
            );
        } catch (ValidationException $e) {
            $demande = $demande->fresh() ?? $demande;
            if ($demande->statut === DemandePaiement::CONFIRMEE) {
                return $demande;   // l'autre chemin (notification ou retour) vient de passer
            }

            // Le client a payé, et la facture ne peut plus recevoir : c'est à rembourser, pas à taire.
            Journal::tracer('PAIEMENT_EN_LIGNE_A_REMBOURSER', $demande,
                Montant::formater($demande->montant_centimes, $demande->devise)." payés en ligne sur {$demande->facture->numero} mais non imputables : à rembourser", [
                    'reference' => $demande->reference,
                    'raison' => collect($e->errors())->flatten()->first(),
                ]);

            return self::clore($demande, DemandePaiement::ECHOUEE, 'Payé mais non imputable (facture déjà soldée) : à rembourser.');
        }

        $demande->forceFill([
            'statut' => DemandePaiement::CONFIRMEE,
            'paiement_id' => $paiement->id,
            'reference_externe' => $reponse['reference_externe'],
            'confirmee_le' => Carbon::now(),
        ])->save();

        return $demande;
    }

    private static function clore(DemandePaiement $demande, string $statut, string $motif): DemandePaiement
    {
        $demande->forceFill(['statut' => $statut, 'motif' => $motif])->save();

        return $demande;
    }
}
