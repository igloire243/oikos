<?php

namespace App\Http\Controllers;

use App\Metier\Console\Reglages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LES RÉGLAGES — quatre durées, chacune lue par une règle déjà livrée (voir `Reglages`).
 * Le contrôleur ne valide rien : les bornes vivent avec les définitions, à un seul endroit.
 */
class ReglagesController extends Controller
{
    public function index(): Response
    {
        $valeurs = Reglages::toutes();

        return Inertia::render('Console/Reglages/Index', [
            'reglages' => collect(Reglages::DEFINITIONS)->map(fn (array $d, string $cle) => [
                'cle' => $cle,
                'libelle' => $d['libelle'],
                'effet' => $d['effet'],
                'min' => $d['min'],
                'max' => $d['max'],
                'valeur' => $valeurs[$cle],
                'defaut' => (int) config('oikos.'.$cle),
            ])->values(),
        ]);
    }

    public function update(Request $requete): RedirectResponse
    {
        Reglages::enregistrer($requete->only(array_keys(Reglages::DEFINITIONS)), $requete->user());

        return back()->with('succes', 'Réglages enregistrés.');
    }
}
