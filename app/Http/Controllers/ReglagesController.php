<?php

namespace App\Http\Controllers;

use App\Metier\Commerce\PaiementsEnLigne;
use App\Metier\Commerce\Passerelles\Flutterwave;
use App\Metier\Console\Reglages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LES RÉGLAGES — les durées de la licence, le délai de paiement, le paiement en ligne, l'identité de
 * l'éditeur. Chacun est LU par un code déjà livré (voir `Reglages`). Le contrôleur ne valide rien : les
 * bornes vivent avec les définitions, à un seul endroit.
 *
 * Les SECRETS (clés du prestataire) ne reviennent jamais à l'écran : on dit s'ils sont posés, et d'où ils
 * viennent (l'écran ou le `.env` du serveur), jamais ce qu'ils valent.
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

            'facturation' => [
                'echeance_jours' => Reglages::valeur('echeance_jours'),
                'min' => Reglages::AUTRES['echeance_jours']['min'],
                'max' => Reglages::AUTRES['echeance_jours']['max'],
                'lu_par' => Reglages::AUTRES['echeance_jours']['lu_par'],
            ],

            'paiement' => [
                'actif' => PaiementsEnLigne::actif(),
                'passerelle' => PaiementsEnLigne::nomDeLaPasserelle(),
                'choix' => [
                    ['valeur' => 'flutterwave', 'libelle' => 'Flutterwave (cartes, mobile money)'],
                    ['valeur' => 'simulee', 'libelle' => 'Simulé — pour essayer le parcours (interdit en production)'],
                ],
                'cle' => ['posee' => Reglages::secretPose('flutterwave_cle_secrete'), 'origine' => Reglages::origine('flutterwave_cle_secrete')],
                'hash' => ['posee' => Reglages::secretPose('flutterwave_hash'), 'origine' => Reglages::origine('flutterwave_hash')],
                'url_notification' => url('/payer/notification/flutterwave'),
                'https' => str_starts_with(url('/'), 'https://'),
                'production' => app()->isProduction(),
            ],

            'editeur' => Reglages::editeur(),
        ]);
    }

    public function update(Request $requete): RedirectResponse
    {
        Reglages::enregistrer($requete->only(array_keys(Reglages::DEFINITIONS)), $requete->user());

        return back()->with('succes', 'Réglages enregistrés.');
    }

    /** Facturation, paiement, éditeur : seules les clés envoyées bougent. */
    public function autres(Request $requete): RedirectResponse
    {
        Reglages::modifier($requete->only([
            ...array_keys(Reglages::AUTRES),
            'flutterwave_cle_secrete_effacer', 'flutterwave_hash_effacer',
        ]), $requete->user());

        return back()->with('succes', 'Réglages enregistrés.');
    }

    /** Essaie la clé du prestataire sans rien payer. */
    public function testerLePaiement(): RedirectResponse
    {
        $resultat = PaiementsEnLigne::nomDeLaPasserelle() === 'flutterwave'
            ? Flutterwave::tester()
            : ['ok' => false, 'message' => 'Le prestataire simulé n\'a pas de clé à essayer.'];

        return back()->with($resultat['ok'] ? 'succes' : 'erreur', $resultat['message']);
    }
}
