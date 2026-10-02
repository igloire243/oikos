<?php

namespace App\Metier\Vitrine;

use App\Metier\Commerce\Montant;
use App\Models\Entite;
use App\Models\Offre;

/**
 * CE QUE LA VITRINE PROMET — lu dans les offres, jamais écrit à la main.
 *
 * Un tarif tapé dans une page publique serait une seconde copie, et le jour où l'opérateur change un
 * prix sur l'écran « Offres », le site continuerait d'annoncer l'ancien. La vitrine ne montre donc que
 * les offres PUBLIQUES et en vente (une offre « négociée » ne s'affiche jamais), dans les deux devises,
 * et la liste de modules vient de `Offre::modulesParEspace()`, la même que celle de l'opérateur.
 */
final class Tarifs
{
    /**
     * @return array{licences: list<array<string, mixed>>, eglises: list<array<string, mixed>>, antennes: list<array<string, mixed>>}
     */
    public static function catalogue(): array
    {
        $offres = Offre::query()->enVente()->where('publique', true)->orderBy('ordre')->orderBy('id')->get();

        return [
            'licences' => $offres->where('nature', Offre::LICENCE)->map(fn (Offre $o) => self::presenter($o))->values()->all(),
            'eglises' => $offres->where('nature', Offre::ACCES)->where('niveau', Entite::EXTENSION)->map(fn (Offre $o) => self::presenter($o))->values()->all(),
            'antennes' => $offres->where('nature', Offre::ACCES)->where('niveau', Entite::ANTENNE)->map(fn (Offre $o) => self::presenter($o))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private static function presenter(Offre $o): array
    {
        $prix = fn (int $usd, int $cdf) => ['USD' => Montant::formater($usd, 'USD'), 'CDF' => Montant::formater($cdf, 'CDF')];

        return [
            'code' => $o->code,
            'nom' => $o->nom,
            'palier' => Offre::PALIERS[$o->palier],
            'argumentaire' => $o->argumentaire,
            'duree' => $o->periode_mois === 12 ? 'par an' : ($o->periode_mois === 1 ? 'par mois' : "pour {$o->periode_mois} mois"),
            // Une grille de taille (la licence) remplace le prix fixe : « à partir de » le premier palier.
            'prix' => $prix($o->prix_usd_centimes, $o->prix_cdf_centimes),
            'tranches' => array_map(fn (array $t) => [
                'libelle' => $t['max'] === null ? 'Au-delà' : "Jusqu'à {$t['max']} entités",
                'prix' => $prix($t['prix_usd_centimes'], $t['prix_cdf_centimes']),
            ], $o->paliers_taille ?? []),
            'tout_le_produit' => $o->modules === null,
            'modules' => $o->modulesParEspace(),
            'plafond' => $o->plafond_acces === null ? null : Offre::PALIERS[$o->plafond_acces],
        ];
    }
}
