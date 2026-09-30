<?php

namespace App\Metier\Console;

use Illuminate\Support\Facades\Route;

/**
 * LE MENU DE LA CONSOLE — une seule liste, lue par la barre latérale et la barre du bas.
 *
 * Une entrée dont la route n'existe pas encore reste affichée, marquée « à venir », sans lien :
 * le menu montre la carte complète de la console dès le premier lot, et ajouter l'écran suffit à
 * l'allumer. Même convention que le produit.
 */
class Menu
{
    /** @var list<array{titre: string|null, entrees: list<array{cle: string, libelle: string, icone: string, route: string}>}> */
    private const GROUPES = [
        [
            'titre' => null,
            'entrees' => [
                ['cle' => 'accueil', 'libelle' => 'Tableau de bord', 'icone' => 'layout-dashboard', 'route' => 'console.accueil'],
            ],
        ],
        [
            'titre' => 'Commerce',
            'entrees' => [
                ['cle' => 'clients', 'libelle' => 'Clients et installations', 'icone' => 'building-2', 'route' => 'console.clients.index'],
                ['cle' => 'offres', 'libelle' => 'Offres', 'icone' => 'tags', 'route' => 'console.offres.index'],
                ['cle' => 'factures', 'libelle' => 'Factures et encaissements', 'icone' => 'receipt', 'route' => 'console.factures.index'],
                ['cle' => 'demandes', 'libelle' => 'Demandes de contact', 'icone' => 'inbox', 'route' => 'console.demandes.index'],
            ],
        ],
        [
            'titre' => 'Produit',
            'entrees' => [
                ['cle' => 'catalogue', 'libelle' => 'Catalogue des modules', 'icone' => 'layers', 'route' => 'console.catalogue.index'],
            ],
        ],
        [
            'titre' => 'Réglages',
            'entrees' => [
                ['cle' => 'reglages', 'libelle' => 'Réglages', 'icone' => 'settings', 'route' => 'console.reglages.index'],
                ['cle' => 'journal', 'libelle' => 'Journal', 'icone' => 'scroll-text', 'route' => 'console.journal.index'],
            ],
        ],
    ];

    /** @return list<array{titre: string|null, entrees: list<array{cle: string, libelle: string, icone: string, route: string|null}>}> */
    public static function groupes(): array
    {
        return array_map(fn (array $groupe) => [
            'titre' => $groupe['titre'],
            'entrees' => array_map(fn (array $entree) => [
                ...$entree,
                'route' => Route::has($entree['route']) ? $entree['route'] : null,
            ], $groupe['entrees']),
        ], self::GROUPES);
    }
}
