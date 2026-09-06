<?php

use App\Http\Controllers\Api\ActivationController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\SynchronisationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| L'API QUE LES INSTALLATIONS APPELLENT
|--------------------------------------------------------------------------
| Deux routes, et deux seulement. C'est la troisième surface exposée à Internet, après le site
| public et l'écran de connexion — et la seule que des serveurs appellent sans qu'un humain
| regarde. Elle mérite donc d'être minuscule et de le rester.
|
| PAS DE SESSION, PAS DE COOKIE, PAS DE CSRF. Le groupe 'api' ne démarre pas de session : ces
| appels viennent d'un serveur, pas d'un navigateur. Une route de synchronisation qui accepterait
| un cookie serait vulnérable à des requêtes déclenchées depuis un navigateur tiers.
|
| LE VERSIONNEMENT EST DANS L'URL (/api/v1/…) et il n'est pas décoratif : le jour où la réponse de
| licence changera de forme, des installations tourneront encore avec l'ancienne lecture. Sans
| numéro de version, il faudrait choisir entre casser leurs mises à jour et ne jamais évoluer.
|
| LES PLAFONDS SONT SERRÉS. Une installation active s'authentifie une fois puis synchronise une
| fois par nuit ; personne de légitime n'a besoin de plus.
*/

Route::prefix('v1')->group(function () {

    // L'échange d'une clé courte contre une clé de synchronisation et l'état d'abonnement.
    // Dix essais par minute et par adresse : une clé de douze caractères pris dans trente et un
    // est déjà hors de portée d'une recherche exhaustive, mais rien n'oblige à laisser essayer.
    Route::post('/activation', [ActivationController::class, 'activer'])
        ->middleware('throttle:10,1')
        ->name('api.activation');

    // L'appel de nuit : l'arbre des entités monte, l'état d'abonnement descend.
    // Soixante par heure : largement de quoi absorber une reprise après panne, et pas de quoi
    // faire de cette route un moyen de charger le serveur.
    Route::post('/synchronisation', [SynchronisationController::class, 'synchroniser'])
        ->middleware('throttle:60,60')
        ->name('api.synchronisation');

    // « Payer maintenant » déclenché depuis le produit (page « Mon abonnement » du client).
    // Le produit ne parle jamais à FlexPay : il délègue à la console, qui pousse l'invite et
    // rend la main. Le versement se confirme ensuite par /webhooks/flexpay. Douze par minute :
    // un client peut s'y reprendre à quelques essais (mauvais numéro, opérateur), pas en boucle.
    Route::post('/paiement/demarrer', [PaiementController::class, 'demarrer'])
        ->middleware('throttle:12,1')
        ->name('api.paiement.demarrer');
});
