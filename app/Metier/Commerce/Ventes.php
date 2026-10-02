<?php

namespace App\Metier\Commerce;

use App\Metier\Journal\Journal;
use App\Metier\Licence\Rappel;
use App\Models\Abonnement;
use App\Models\Entite;
use App\Models\Installation;
use App\Models\Offre;
use App\Models\PeriodeAbonnement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * VENDRE — seul écrivain de `abonnements` et de `periodes_abonnement`.
 *
 * Un seul geste pour la première vente, le renouvellement et la reprise après résiliation :
 * « vendre une période à cette entité ». Ce qui change d'un cas à l'autre — la date de début
 * proposée — se déduit de ce qui a déjà été vendu, au lieu de vivre dans trois écrans.
 *
 * ============================================================================================
 * LA CASCADE : un accès ne peut exister que sous une licence en cours, et ne peut jamais lui
 * survivre.
 * ============================================================================================
 * Écrite ici (`empechement()`), et pas dans le formulaire : un appel direct passerait à côté d'un
 * bouton masqué. Et la SURVIE est tenue deux fois — à la vente, par le prorata ci-dessous, puis à
 * chaque réponse de licence (`EtatLicence`), qui coupe un accès le jour où sa licence tombe, même
 * résiliée en cours de route.
 *
 * ============================================================================================
 * LE PRORATA EST CONTRAINT — un défaut de l'ancienne console, corrigé ici
 * ============================================================================================
 * La page publique de l'ancienne promettait qu'un accès vendu en cours d'année « s'arrête avec la
 * licence », et son propre manuel avouait que ce n'était « pas encore contraint dans le code ». Un
 * accès d'un mois vendu dix jours avant l'échéance de la licence courait donc vingt jours au-delà,
 * payés plein tarif, sous une licence qui n'existait plus. Ici la période est COUPÉE à la fin de la
 * licence, et son prix réduit au jour près.
 */
class Ventes
{
    /** @var list<string> */
    public const DEVISES = ['USD', 'CDF'];

    /**
     * Ce que la vente produirait, sans rien écrire — l'écran le montre avant qu'on confirme.
     *
     * @return array{empechement: string|null, debut: string, fin: string|null, montant_centimes: int|null, plein_centimes: int|null, au_prorata: bool, taille: int, licence_fin: string|null}
     */
    public static function apercu(Entite $entite, Offre $offre, string $devise, ?Carbon $debut = null): array
    {
        $debut = ($debut ?? self::debutPropose($entite))->copy()->startOfDay();
        $taille = self::taille($entite->installation);
        $vide = ['debut' => $debut->toDateString(), 'fin' => null, 'montant_centimes' => null, 'plein_centimes' => null, 'au_prorata' => false, 'taille' => $taille, 'licence_fin' => null];

        if ($raison = self::empechement($entite, $offre, $devise, $debut)) {
            return ['empechement' => $raison] + $vide;
        }

        $finPleine = $debut->copy()->addMonthsNoOverflow($offre->periode_mois)->subDay();
        $fin = $finPleine->copy();
        $licenceFin = null;

        if (! $offre->estUneLicence()) {
            /** @var Carbon $licenceFin la licence couvre `debut`, `empechement()` vient de le vérifier */
            $licenceFin = self::finDeLaLicence($entite->installation, $debut);
            if ($fin->gt($licenceFin)) {
                $fin = $licenceFin->copy();
            }
        }

        $plein = $offre->prixPour($devise, $taille);
        $auProrata = $fin->lt($finPleine);
        $joursPleins = (int) $debut->diffInDays($finPleine) + 1;
        $jours = (int) $debut->diffInDays($fin) + 1;

        return [
            'empechement' => null,
            'debut' => $debut->toDateString(),
            'fin' => $fin->toDateString(),
            // Arrondi au centime, une seule fois, sur le montant final : multiplier puis arrondir
            // chaque jour perdrait un centime par jour, et personne ne le retrouverait.
            'montant_centimes' => $auProrata ? (int) round($plein * $jours / $joursPleins) : $plein,
            'plein_centimes' => $plein,
            'au_prorata' => $auProrata,
            'taille' => $taille,
            'licence_fin' => $licenceFin?->toDateString(),
        ];
    }

    /** Vend une période à cette entité — première vente, renouvellement ou reprise. */
    public static function vendre(Entite $entite, Offre $offre, string $devise, ?Carbon $debut, ?User $par): PeriodeAbonnement
    {
        $apercu = self::apercu($entite, $offre, $devise, $debut);

        if ($apercu['empechement'] !== null) {
            throw ValidationException::withMessages(['offre_id' => $apercu['empechement']]);
        }

        $periode = DB::transaction(function () use ($entite, $offre, $devise, $apercu, $par) {
            $abonnement = Abonnement::query()->lockForUpdate()->firstOrCreate(
                ['entite_id' => $entite->id],
                ['installation_id' => $entite->installation_id],
            );

            $reprise = $abonnement->estResilie();
            $premiere = ! $abonnement->periodes()->exists();

            if ($reprise) {
                $abonnement->forceFill(['resilie_le' => null, 'motif_resiliation' => null])->save();
            }

            $periode = $abonnement->periodes()->create([
                'offre_id' => $offre->id,
                'debut' => $apercu['debut'],
                'fin' => $apercu['fin'],
                'montant_centimes' => $apercu['montant_centimes'],
                'devise' => $devise,
                'au_prorata' => $apercu['au_prorata'],
                'vendue_par_id' => $par?->id,
            ]);

            $geste = match (true) {
                $reprise => 'ABONNEMENT_REPRIS',
                $premiere => 'ABONNEMENT_VENDU',
                default => 'ABONNEMENT_RENOUVELE',
            };

            Journal::tracer($geste, $abonnement, $offre->nom.' pour « '.$entite->nom.' », du '
                .Carbon::parse($apercu['debut'])->translatedFormat('j F Y').' au '
                .Carbon::parse($apercu['fin'])->translatedFormat('j F Y'), [
                    'entite' => $entite->reference(),
                    'offre' => $offre->code,
                    'montant' => Montant::formater((int) $apercu['montant_centimes'], $devise),
                    'au_prorata' => $apercu['au_prorata'],
                ], $par);

            // La facture naît avec la vente, dans la même transaction : une vente sans facture
            // laisserait une période servie que personne ne réclame (voir Facturation).
            Facturation::emettre($periode, $par);

            return $periode;
        });

        // L'argent d'abord, l'accès ensuite, le rappel en dernier et HORS transaction : un serveur
        // client injoignable ne doit ni retarder ni annuler une vente.
        Rappel::prevenir($entite->installation);

        return $periode;
    }

    /**
     * Résilie, sans rien effacer : les périodes vendues restent l'historique de ce qui a été
     * facturé. Résilier une LICENCE coupe aussi les accès de l'installation — non pas en les
     * réécrivant, mais parce que `EtatLicence` ne les sert plus sans elle.
     */
    public static function resilier(Abonnement $abonnement, string $motif, ?User $par): void
    {
        if ($abonnement->estResilie()) {
            throw ValidationException::withMessages(['motif' => 'Cet abonnement est déjà résilié.']);
        }

        $abonnement->forceFill(['resilie_le' => Carbon::now(), 'motif_resiliation' => $motif])->save();

        Journal::tracer('ABONNEMENT_RESILIE', $abonnement, 'Abonnement de « '.$abonnement->entite->nom.' » résilié', [
            'entite' => $abonnement->entite->reference(),
            'motif' => $motif,
            'acces_coupes' => $abonnement->entite->type === Entite::VISION ? self::accesSousLaLicence($abonnement->installation) : 0,
        ], $par);

        Rappel::prevenir($abonnement->installation);
    }

    /**
     * La date de début qu'on propose : le lendemain de la dernière période si elle court encore
     * (le renouvellement se colle à l'échéance, sans trou ni chevauchement), aujourd'hui sinon.
     */
    public static function debutPropose(Entite $entite): Carbon
    {
        $aujourdhui = Carbon::today();
        $finVendue = PeriodeAbonnement::query()
            ->whereHas('abonnement', fn ($q) => $q->where('entite_id', $entite->id)->whereNull('resilie_le'))
            ->max('fin');

        if ($finVendue === null) {
            return $aujourdhui;
        }

        $lendemain = Carbon::parse($finVendue)->addDay();

        return $lendemain->gt($aujourdhui) ? $lendemain : $aujourdhui;
    }

    /** Pourquoi cette vente est impossible — ou null. Chaque phrase s'affiche telle quelle. */
    public static function empechement(Entite $entite, Offre $offre, string $devise, Carbon $debut): ?string
    {
        $installation = $entite->installation;

        if (! in_array($devise, self::DEVISES, true)) {
            return 'Devise inconnue.';
        }
        if ($offre->estRetiree()) {
            return "L'offre « {$offre->nom} » est retirée : elle ne se vend plus.";
        }
        if ($installation->estDesactivee()) {
            return 'Cette installation est désactivée : réactivez-la avant de vendre.';
        }
        if ($offre->niveau !== $entite->type) {
            return $offre->estUneLicence()
                ? "Une licence se vend à la Vision, pas à « {$entite->nom} ». Vendez-lui plutôt un accès."
                : "« {$offre->nom} » se vend à une ".mb_strtolower(Entite::TYPES[$offre->niveau]).', pas à '
                    .mb_strtolower(Entite::TYPES[$entite->type])." comme « {$entite->nom} ».";
        }
        if ($debut->lt(Carbon::today()->subDays(31))) {
            // Antidater d'un mois suffit à régulariser un oubli ; au-delà, on facturerait un passé
            // que personne n'a utilisé sous cette offre.
            return 'Une période ne commence pas plus d\'un mois dans le passé.';
        }

        $finVendue = PeriodeAbonnement::query()
            ->whereHas('abonnement', fn ($q) => $q->where('entite_id', $entite->id)->whereNull('resilie_le'))
            ->max('fin');

        if ($finVendue !== null && $debut->lte(Carbon::parse($finVendue))) {
            return '« '.$entite->nom.' » est déjà couverte jusqu\'au '.Carbon::parse($finVendue)->translatedFormat('j F Y')
                .' : la nouvelle période commence au plus tôt le lendemain.';
        }

        if ($offre->estUneLicence()) {
            return null;
        }

        $licence = self::periodeDeLicence($installation, $debut);

        if ($licence === null) {
            return 'Pas de licence en cours au '.$debut->translatedFormat('j F Y').' sur cette installation. Vendez '
                ."d'abord la licence à la Vision : sans elle, l'accès de « {$entite->nom} » n'ouvrirait rien.";
        }
        if (! Offre::autorise($licence->offre->plafond_acces, $offre->palier)) {
            return 'La licence « '.$licence->offre->nom.' » n\'autorise les accès que jusqu\'au palier '
                .(Offre::PALIERS[$licence->offre->plafond_acces] ?? '—').'. Faites évoluer la licence pour vendre un '
                .Offre::PALIERS[$offre->palier].'.';
        }

        return null;
    }

    /**
     * La taille du réseau, telle que l'installation l'a remontée : ses antennes et ses églises.
     * Ni le nombre de membres ni la bascule secteur/cellule — on ne facture jamais sur un effectif
     * qui change avec un dimanche de baptêmes (invariant n° 3 du produit).
     */
    public static function taille(Installation $installation): int
    {
        return $installation->entites()->where('type', '!=', Entite::VISION)->count();
    }

    /** La période de licence qui couvre ce jour — l'abonnement de la Vision, non résilié. */
    public static function periodeDeLicence(Installation $installation, Carbon $jour): ?PeriodeAbonnement
    {
        return PeriodeAbonnement::query()
            ->with('offre')
            ->whereHas('abonnement', fn ($q) => $q->where('installation_id', $installation->id)->whereNull('resilie_le')
                ->whereHas('entite', fn ($q) => $q->where('type', Entite::VISION)))
            ->whereDate('debut', '<=', $jour)
            ->whereDate('fin', '>=', $jour)
            ->first();
    }

    /**
     * Le dernier jour couvert par la licence, de proche en proche à partir de ce jour : une licence
     * déjà renouvelée couvre jusqu'à la fin de son renouvellement, pas seulement de sa période en
     * cours. Null si rien ne couvre ce jour.
     */
    public static function finDeLaLicence(Installation $installation, Carbon $jour): ?Carbon
    {
        $fin = null;
        $curseur = $jour->copy();

        while ($periode = self::periodeDeLicence($installation, $curseur)) {
            $fin = $periode->fin->copy();
            $curseur = $fin->copy()->addDay();
        }

        return $fin;
    }

    private static function accesSousLaLicence(Installation $installation): int
    {
        return Abonnement::query()
            ->where('installation_id', $installation->id)
            ->whereNull('resilie_le')
            ->whereHas('entite', fn ($q) => $q->where('type', '!=', Entite::VISION))
            ->count();
    }
}
