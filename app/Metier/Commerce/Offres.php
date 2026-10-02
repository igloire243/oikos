<?php

namespace App\Metier\Commerce;

use App\Metier\Catalogue\Modules;
use App\Metier\Journal\Journal;
use App\Models\Entite;
use App\Models\Offre;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * LE CATALOGUE COMMERCIAL — seul écrivain de `offres`.
 *
 * Trois règles qui ne se voient pas dans un formulaire, et qu'il tient pour tous :
 *
 *   · LA NATURE COMMANDE LE NIVEAU. Une licence se vend à la Vision, un accès à une antenne ou à
 *     une église — dans les deux sens, parce qu'une licence d'antenne ou un accès de Vision
 *     n'auraient aucune place dans la cascade.
 *   · UNE OFFRE NE VEND QUE DES MODULES VENDABLES DE SES ESPACES. Cocher `antenne.rapports` sur un
 *     accès d'église ouvrirait un écran que l'église n'a pas ; cocher un module non vendable (les
 *     comptes, les délégations) ferait payer ce qui vient de toute façon avec l'espace.
 *   · CHANGER UN PRIX NE CHANGE PAS LE PASSÉ : chaque période vendue garde son montant. La nouvelle
 *     grille vaut pour les ventes à venir, renouvellements compris.
 */
class Offres
{
    /**
     * @param  array<string, mixed>  $donnees  validées par le contrôleur
     */
    public static function enregistrer(?Offre $offre, array $donnees, ?User $par): Offre
    {
        $donnees = self::controler($donnees);
        $nouvelle = $offre === null;

        $offre ??= new Offre;
        $offre->fill($donnees)->save();

        Journal::tracer($nouvelle ? 'OFFRE_CREEE' : 'OFFRE_MODIFIEE', $offre, 'Offre « '.$offre->nom.' » '.($nouvelle ? 'créée' : 'modifiée'), [
            'code' => $offre->code,
            'prix' => Montant::formater($offre->prix_usd_centimes, 'USD').' / '.Montant::formater($offre->prix_cdf_centimes, 'CDF'),
        ], $par);

        return $offre;
    }

    /**
     * On RETIRE une offre, on ne la supprime pas : des périodes vendues la désignent. Retirée,
     * elle ne se vend plus — ni en première vente ni en renouvellement — mais ce qui court continue
     * de courir jusqu'à son terme.
     */
    public static function retirer(Offre $offre, ?User $par): void
    {
        if ($offre->estRetiree()) {
            return;
        }

        $offre->forceFill(['retiree_le' => Carbon::now()])->save();
        Journal::tracer('OFFRE_RETIREE', $offre, 'Offre « '.$offre->nom.' » retirée de la vente', [], $par);
    }

    public static function retablir(Offre $offre, ?User $par): void
    {
        if (! $offre->estRetiree()) {
            return;
        }

        $offre->forceFill(['retiree_le' => null])->save();
        Journal::tracer('OFFRE_RETABLIE', $offre, 'Offre « '.$offre->nom.' » remise en vente', [], $par);
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private static function controler(array $donnees): array
    {
        $licence = $donnees['nature'] === Offre::LICENCE;

        if ($licence && $donnees['niveau'] !== Entite::VISION) {
            throw ValidationException::withMessages(['niveau' => 'Une licence se vend à la Vision : c\'est elle qui met le système en service.']);
        }
        if (! $licence && $donnees['niveau'] === Entite::VISION) {
            throw ValidationException::withMessages(['niveau' => 'La Vision n\'achète pas d\'accès : sa licence ouvre déjà son espace.']);
        }

        if ($donnees['modules'] !== null) {
            $espaces = Offre::ESPACES[$donnees['niveau']];
            $toutes = Modules::toutes();

            foreach ($donnees['modules'] as $cle) {
                $module = $toutes[$cle] ?? null;
                if ($module === null || ! in_array($module['espace'], $espaces, true) || ! $module['vendable']) {
                    throw ValidationException::withMessages(['modules' => "« {$cle} » ne se vend pas dans cette offre : il n'appartient pas à ses espaces, ou il n'est pas vendable."]);
                }
            }

            $donnees['modules'] = array_values(array_unique($donnees['modules']));
        }

        if (! $licence) {
            // Un accès n'a ni grille de taille ni plafond : il EST ce que le plafond limite.
            $donnees['paliers_taille'] = null;
            $donnees['plafond_acces'] = null;

            return $donnees;
        }

        if (empty($donnees['plafond_acces'])) {
            throw ValidationException::withMessages(['plafond_acces' => 'Une licence dit jusqu\'à quel palier on peut vendre des accès en dessous.']);
        }

        $donnees['paliers_taille'] = self::grille($donnees['paliers_taille'] ?? null);

        return $donnees;
    }

    /**
     * La grille de taille, rangée et bouclée. Sans tranche « au-delà » (max nul), un réseau plus
     * grand que la dernière tranche retomberait sur le prix de base — le moins cher —, sans que
     * personne s'en aperçoive.
     *
     * @param  list<array{max: int|null, prix_usd_centimes: int, prix_cdf_centimes: int}>|null  $grille
     * @return list<array{max: int|null, prix_usd_centimes: int, prix_cdf_centimes: int}>|null
     */
    private static function grille(?array $grille): ?array
    {
        if (empty($grille)) {
            return null;
        }

        usort($grille, fn ($a, $b) => ($a['max'] ?? PHP_INT_MAX) <=> ($b['max'] ?? PHP_INT_MAX));

        $ouvertes = array_filter($grille, fn ($t) => $t['max'] === null);
        if (count($ouvertes) !== 1 || end($grille)['max'] !== null) {
            throw ValidationException::withMessages(['paliers_taille' => 'La grille se termine par UNE tranche « au-delà », sans maximum.']);
        }

        $maxs = array_filter(array_column($grille, 'max'), fn ($m) => $m !== null);
        if (count($maxs) !== count(array_unique($maxs))) {
            throw ValidationException::withMessages(['paliers_taille' => 'Deux tranches ont le même maximum.']);
        }

        return $grille;
    }
}
