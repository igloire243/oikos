<?php

namespace App\Http\Controllers;

use App\Metier\Catalogue\Modules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LE CATALOGUE, EN LECTURE — ce que le produit sait ouvrir, donc ce qu'on peut vendre.
 *
 * Aucun formulaire ici, et c'est voulu : le catalogue appartient au produit (voir
 * `App\Metier\Catalogue\Modules`). Le modifier depuis la console ferait exactement ce que ce lot
 * existe pour empêcher — un module vendu que rien n'ouvre.
 */
class CatalogueController extends Controller
{
    public function index(): Response
    {
        $espaces = [];

        foreach (Modules::espaces() as $cle => $espace) {
            $modules = [];

            foreach ($espace['modules'] as $module => $definition) {
                $modules[] = [
                    'cle' => "{$cle}.{$module}",
                    'libelle' => $definition['libelle'],
                    'icone' => $definition['icone'],
                    'vendable' => (bool) $definition['vendable'],
                    'groupe' => isset($definition['groupe']) ? ($espace['groupes'][$definition['groupe']] ?? null) : null,
                ];
            }

            $espaces[] = [
                'cle' => $cle,
                'libelle' => $espace['libelle'],
                'modules' => $modules,
            ];
        }

        return Inertia::render('Console/Catalogue', [
            'espaces' => $espaces,
            'empreinte' => Modules::empreinte(),
            'vendables' => count(Modules::vendables()),
            'inclus' => collect(Modules::inclus())->map(fn (array $groupe, string $cle) => [
                'cle' => $cle,
                'libelle' => $groupe['libelle'],
                'ouvert_par' => $groupe['ouvert_par'] ? Modules::libelle($groupe['ouvert_par']).' ('.$groupe['ouvert_par'].')' : null,
                'modules' => collect($groupe['modules'])->map(fn (array $m, string $c) => ['cle' => $c, ...$m])->values(),
            ])->values(),
        ]);
    }
}
