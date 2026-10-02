<?php

namespace App\Http\Controllers;

use App\Metier\Vitrine\Tarifs;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LE SITE COMMERCIAL — ce que voit quelqu'un qui ne nous connaît pas encore.
 *
 * Deux pages : une présentation, et les tarifs lus dans les offres (voir `Tarifs`). Une seule porte
 * d'action, le formulaire de demande : la console ne vend pas en ligne tant qu'aucun paiement n'est
 * branché, et ne promet donc pas un bouton « acheter » qui ne mènerait nulle part.
 */
class VitrineController extends Controller
{
    public function accueil(): Response
    {
        return Inertia::render('Public/Accueil');
    }

    public function tarifs(): Response
    {
        return Inertia::render('Public/Tarifs', ['tarifs' => Tarifs::catalogue()]);
    }
}
