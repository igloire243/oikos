<?php

namespace App\Metier\Licence;

use App\Metier\Catalogue\Modules;
use App\Metier\Commerce\Ventes;
use App\Models\Abonnement;
use App\Models\Entite;
use App\Models\Installation;
use App\Models\Offre;
use Illuminate\Support\Carbon;

/**
 * CE QUE LA CONSOLE RÉPOND À UNE INSTALLATION QUI DEMANDE « de quoi ai-je le droit ? ».
 *
 * Le SEUL endroit où cette réponse se construit : l'activation et la synchronisation renvoient
 * exactement le même objet. Deux constructions finiraient par dire deux choses, et le client
 * verrait ses modules changer selon qu'il vient d'activer ou qu'il a attendu la nuit.
 *
 * ============================================================================================
 * L'ESSAI A UNE FIN FIXE — un défaut de l'ancienne console, corrigé ici
 * ============================================================================================
 * L'ancienne calculait la fin de l'essai comme « maintenant + 30 jours » à CHAQUE réponse. Une
 * installation qui n'achetait rien voyait donc son essai repoussé d'un jour à chaque nuit de
 * synchronisation : un essai éternel, sans que personne l'ait décidé. Ici la fin part de la
 * PREMIÈRE activation (`activee_le`), et elle ne bouge plus.
 *
 * Quatre statuts : ESSAI (tout ouvert, avant toute licence), ACTIF, GRACE (la licence est échue
 * depuis moins de `grace_jours` : le produit laisse travailler, et prévient), EXPIRÉ.
 */
class EtatLicence
{
    public const ESSAI = 'ESSAI';

    public const ACTIF = 'ACTIF';

    public const GRACE = 'GRACE';

    public const EXPIRE = 'EXPIRE';

    /** @return array<string, mixed> */
    public static function pour(Installation $installation, ?Carbon $maintenant = null): array
    {
        $maintenant ??= Carbon::now();
        $aujourdhui = $maintenant->copy()->startOfDay();
        $grace = (int) config('oikos.grace_jours', 14);

        $abonnements = $installation->abonnements()
            ->whereNull('resilie_le')
            ->with(['entite', 'periodes.offre'])
            ->get();

        $licence = $abonnements->first(fn (Abonnement $a) => $a->entite->type === Entite::VISION);
        $licenceVendue = $installation->abonnements()
            ->whereHas('entite', fn ($q) => $q->where('type', Entite::VISION))
            ->whereHas('periodes')
            ->exists();

        [$statut, $fin, $offre] = self::statut($installation, $licence, $licenceVendue, $aujourdhui, $grace);
        $ouverte = in_array($statut, [self::ACTIF, self::GRACE], true);

        // LA CASCADE, TENUE UNE SECONDE FOIS : un accès ne survit pas à sa licence. Sa fin servie
        // est la plus proche des deux — même quand la licence a été résiliée APRÈS la vente de
        // l'accès, ce que le prorata de la vente ne pouvait pas savoir.
        $finLicence = $ouverte ? Carbon::parse($fin) : null;
        $entites = [];

        foreach ($ouverte ? $abonnements : [] as $abonnement) {
            $ligne = self::ligne($abonnement, $aujourdhui, $grace, $abonnement->entite->type === Entite::VISION ? null : $finLicence);
            if ($ligne !== null) {
                $entites[$abonnement->entite->reference()] = $ligne;
            }
        }

        $etat = [
            'emis_le' => $maintenant->toIso8601String(),
            'console' => (string) config('app.url'),
            'communaute' => $installation->client?->nom,
            'installation' => $installation->libelle(),

            // L'EMPREINTE DE LA MACHINE DESTINATAIRE, dans le message signé : le produit refuse
            // une licence qui ne porte pas la sienne. Copier le fichier d'un client bien abonné sur
            // un autre serveur est l'attaque la plus simple qui soit — elle est fermée ici.
            'empreinte' => $installation->empreinte,

            'statut' => $statut,
            'offre' => $offre?->code,
            'fin' => $fin,
            'grace_jours' => $grace,
            'silence_jours' => (int) config('oikos.silence_jours', 45),

            // L'empreinte du catalogue au nom duquel on répond : le produit compare avec la sienne
            // et peut dire qu'une mise à jour manque, d'un côté ou de l'autre.
            'catalogue' => Modules::empreinte(),

            // null = tout ouvert : c'est l'essai, on laisse voir le produit entier. Sinon, les
            // modules de l'espace de la Vision — ceux de la licence.
            'modules' => $statut === self::ESSAI ? null : ($ouverte && $licence ? ($entites[$licence->entite->reference()]['modules'] ?? []) : []),

            // Le détail PAR ENTITÉ (« EXTENSION:44 » → offre, fin, modules) : c'est lui que la
            // barrière `module:` du produit lit. L'ancienne console servait l'union des offres à
            // toute l'installation, et une antenne bien abonnée ouvrait ses écrans à une église
            // qui n'avait pas payé autant.
            'entites' => (object) $entites,
        ];

        // LA SIGNATURE EN DERNIER, sur tout ce qui précède : un champ ajouté après coup ne serait
        // pas couvert, et la signature rassurerait sans rien protéger.
        $etat['signature'] = Signature::signer($etat);

        return $etat;
    }

    /**
     * Le statut de l'installation, la date qui fait foi, et l'offre de licence.
     *
     * L'ESSAI A UNE FIN FIXE, comptée depuis la PREMIÈRE activation — l'ancienne console répondait
     * « maintenant + 30 jours » à chaque appel, et une installation qui n'achetait rien voyait son
     * essai repoussé chaque nuit. Et il ne revient pas : une licence vendue puis échue donne EXPIRÉ,
     * jamais un second essai.
     *
     * @return array{0: string, 1: string, 2: Offre|null}
     */
    private static function statut(Installation $installation, ?Abonnement $licence, bool $licenceVendue, Carbon $aujourdhui, int $grace): array
    {
        if ($licence !== null && $licence->periodes->isNotEmpty()) {
            $periode = $licence->periodeAu($aujourdhui);
            $chaine = Ventes::finDeLaLicence($installation, $aujourdhui);

            if ($periode !== null && $chaine !== null) {
                return [self::ACTIF, $chaine->toDateString(), $periode->offre];
            }

            $derniere = $licence->dernierePeriode();
            if ($derniere !== null && $derniere->fin->lt($aujourdhui) && $aujourdhui->lte($derniere->fin->copy()->addDays($grace))) {
                return [self::GRACE, $derniere->fin->toDateString(), $derniere->offre];
            }
        }

        $finEssai = ($installation->activee_le ?? $aujourdhui)->copy()->addDays((int) config('oikos.essai_jours', 30))->startOfDay();

        if (! $licenceVendue && $aujourdhui->lte($finEssai)) {
            return [self::ESSAI, $finEssai->toDateString(), null];
        }

        return [self::EXPIRE, ($licence?->dernierePeriode()->fin ?? $finEssai)->toDateString(), null];
    }

    /**
     * Ce que la licence dit d'une entité : son offre, ses modules, et jusqu'à quand.
     *
     * @return array{offre: string, palier: string, fin: string, etat: string, modules: list<string>}|null
     */
    private static function ligne(Abonnement $abonnement, Carbon $aujourdhui, int $grace, ?Carbon $plafond): ?array
    {
        $periode = $abonnement->periodeAu($aujourdhui);
        $etat = $abonnement->etat($aujourdhui);

        if ($periode === null) {
            $derniere = $abonnement->dernierePeriode();
            if ($etat !== Abonnement::EN_GRACE || $derniere === null) {
                return null;
            }
            $periode = $derniere;
        }

        // La fin de la chaîne vendue : un accès déjà renouvelé couvre jusqu'au bout du renouvellement.
        $fin = $periode->fin->copy();
        foreach ($abonnement->periodes->sortBy('debut') as $suivante) {
            if ($suivante->debut->equalTo($fin->copy()->addDay())) {
                $fin = $suivante->fin->copy();
            }
        }

        if ($plafond !== null && $fin->gt($plafond)) {
            $fin = $plafond->copy();
        }

        return [
            'offre' => $periode->offre->code,
            'palier' => $periode->offre->palier,
            'fin' => $fin->toDateString(),
            'etat' => $etat === Abonnement::EN_GRACE ? self::GRACE : self::ACTIF,
            'modules' => $periode->offre->clesOuvertes(),
        ];
    }
}
