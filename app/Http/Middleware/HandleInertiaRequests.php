<?php

namespace App\Http\Middleware;

use App\Metier\Console\Menu;
use App\Metier\Console\Reglages;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * LES PROPS PARTAGÉES — et leurs noms sont RÉSERVÉS.
     *
     * Une prop de page qui porte le même nom qu'une prop partagée l'écrase (piège vécu côté
     * produit : un écran servi sans menu, sans la moindre erreur). `auth`, `menu` et `flash` ne se
     * renvoient donc jamais depuis un contrôleur.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            // L'identité de l'éditeur : le pied du site commercial et la page de paiement la montrent.
            'editeur' => fn () => Reglages::editeur(),

            'menu' => fn () => $request->user() ? Menu::groupes() : [],

            // Les messages d'un seul affichage. `erreur` sert quand un geste est refusé pour une
            // raison métier — une vente qu'une règle interdit, par exemple — et que la phrase
            // doit s'afficher telle quelle.
            'flash' => fn () => [
                'succes' => $request->session()->get('succes'),
                'avertissement' => $request->session()->get('avertissement'),
                'erreur' => $request->session()->get('erreur'),
            ],
        ];
    }
}
