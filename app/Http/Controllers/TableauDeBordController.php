<?php

namespace App\Http\Controllers;

use App\Metier\Catalogue\Modules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * L'ACCUEIL DE LA CONSOLE.
 *
 * Au socle, il ne dit que ce qui existe déjà : le catalogue qu'on vend. Les clients, les
 * échéances et les encaissements s'y ajouteront avec leurs lots — un tableau de bord qui annonce
 * des chiffres qu'aucun écran ne produit encore mentirait.
 */
class TableauDeBordController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Console/Accueil', [
            'catalogue' => [
                'espaces' => count(Modules::espaces()),
                'modules' => count(Modules::toutes()),
                'vendables' => count(Modules::vendables()),
                'empreinte' => substr(Modules::empreinte(), 0, 8),
            ],
        ]);
    }
}
