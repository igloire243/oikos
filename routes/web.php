<?php

use App\Http\Controllers\AbonnementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CleActivationController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DemandeController;
use App\Http\Controllers\FactureController;
use App\Http\Controllers\InstallationController;
use App\Http\Controllers\MotDePasseController;
use App\Http\Controllers\PaiementEnLigneController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ReglageController;
use App\Http\Controllers\TableauBordController;
use App\Http\Controllers\Vitrine\ContactController;
use App\Http\Controllers\Vitrine\SiteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| OIKOS — DEUX APPLICATIONS DANS UN SEUL CODE
|--------------------------------------------------------------------------
| Ce fichier disait autrefois : « tout est derrière auth, sans exception ». Ce n'est plus vrai, et
| il faut le dire franchement : le site public a été ajouté, et cette application est passée
| d'« adresse que personne ne connaît » à « site indexé et scanné par des robots ».
|
| LA FRONTIÈRE EST DANS L'URL, PAS SEULEMENT DANS LE CODE
| --------------------------------------------------------
| Tout ce qui vous appartient vit sous /console — connexion comprise. Tout ce qui est ouvert vit à
| la racine. Un préfixe visible vaut mieux qu'une règle qu'on se rappelle : en relisant ce fichier,
| une route d'administration égarée à la racine se voit immédiatement.
|
| CE QUE LA PARTIE PUBLIQUE PEUT FAIRE
| -------------------------------------
| Lire `plans` (publics seulement) et `clients` (ceux qui ont accepté d'être cités). Écrire dans
| `demandes`, et nulle part ailleurs. Jamais `installations`, jamais `cle_hash`, jamais `factures`.
| Si une route publique doit un jour toucher autre chose, c'est le signe qu'elle n'est pas publique.
*/

/*
|--------------------------------------------------------------------------
| LE SITE PUBLIC
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| WEBHOOK DE L'AGRÉGATEUR MOBILE MONEY (FlexPay)
|--------------------------------------------------------------------------
| Hors de tout groupe : c'est un serveur qui appelle, pas un humain — ni session, ni 'auth'. Sa
| sécurité tient à deux choses (voir PaiementEnLigneController::webhook) : la signature partagée
| ET la re-vérification serveur à serveur avant tout encaissement. Exemptée de CSRF dans
| bootstrap/app.php pour la même raison qu'elle n'a pas de session à présenter un jeton.
|
| Plafond large : FlexPay rejoue son appel tant qu'il n'a pas reçu un 200, et le contrôleur
| répond 200 dans tous les cas sauf signature falsifiée.
*/
Route::post('/webhooks/flexpay', [PaiementEnLigneController::class, 'webhook'])
    ->middleware('throttle:120,1')
    ->name('webhooks.flexpay');

Route::name('vitrine.')->group(function () {
    Route::get('/', [SiteController::class, 'accueil'])->name('accueil');
    Route::get('/tarifs', [SiteController::class, 'tarifs'])->name('tarifs');
    Route::get('/fonctionnalites', [SiteController::class, 'fonctionnalites'])->name('fonctionnalites');
    Route::get('/comment-payer', [SiteController::class, 'paiement'])->name('paiement');
    Route::get('/references', [SiteController::class, 'references'])->name('references');

    Route::get('/contact', [ContactController::class, 'formulaire'])->name('contact');

    // La seule écriture ouverte du site. Cinq envois par minute et par adresse IP : sans ce
    // plafond, le formulaire devient un moyen de remplir la base en une nuit.
    Route::post('/contact', [ContactController::class, 'envoyer'])
        ->middleware('throttle:5,1')->name('contact.envoyer');
});

/*
|--------------------------------------------------------------------------
| LA CONSOLE — votre espace
|--------------------------------------------------------------------------
*/
Route::prefix('console')->group(function () {

    Route::get('/connexion', [AuthController::class, 'formulaire'])->name('connexion');
    Route::post('/connexion', [AuthController::class, 'connexion'])->middleware('throttle:20,1');

    // Mot de passe oublié — avec la connexion, les seules routes de la console hors de 'auth'.
    Route::middleware('guest')->group(function () {
        Route::get('/mot-de-passe/oublie', [MotDePasseController::class, 'demander'])
            ->name('mot-de-passe.oubli');
        Route::post('/mot-de-passe/oublie', [MotDePasseController::class, 'envoyer'])
            ->middleware('throttle:5,1')->name('mot-de-passe.lien');

        // Le jeton est DANS le chemin : c'est cette adresse-là qui part par e-mail.
        Route::get('/mot-de-passe/reinitialiser/{token}', [MotDePasseController::class, 'formulaire'])
            ->name('mot-de-passe.reinitialiser');
        Route::post('/mot-de-passe/reinitialiser', [MotDePasseController::class, 'enregistrer'])
            ->middleware('throttle:5,1')->name('mot-de-passe.enregistrer');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/deconnexion', [AuthController::class, 'deconnexion'])->name('deconnexion');

        Route::get('/', [TableauBordController::class, 'index'])->name('tableau-bord');

        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/nouveau', [ClientController::class, 'creer'])->name('clients.creer');
        Route::post('/clients', [ClientController::class, 'enregistrer'])->name('clients.enregistrer');
        Route::get('/clients/{client}', [ClientController::class, 'fiche'])->name('clients.fiche');
        Route::put('/clients/{client}', [ClientController::class, 'modifier'])->name('clients.modifier');
        Route::put('/clients/{client}/vitrine', [ClientController::class, 'vitrine'])->name('clients.vitrine');

        Route::post('/clients/{client}/installations', [InstallationController::class, 'enregistrer'])
            ->name('installations.enregistrer');
        Route::post('/installations/{installation}/cle', [InstallationController::class, 'renouvelerCle'])
            ->name('installations.cle');
        Route::post('/installations/{installation}/bascule', [InstallationController::class, 'basculer'])
            ->name('installations.bascule');

        // LES ABONNEMENTS. La vente porte sur une ENTITÉ de l'installation — l'arbre remonté par
        // la synchronisation —, jamais sur le client en bloc : c'est ce qui permet de vendre à
        // douze églises douze abonnements distincts, avec douze échéances.
        Route::get('/installations/{installation}/entites/{entite}/abonnement', [AbonnementController::class, 'creer'])
            ->name('abonnements.creer');
        Route::post('/installations/{installation}/entites/{entite}/abonnement', [AbonnementController::class, 'enregistrer'])
            ->name('abonnements.enregistrer');
        Route::post('/abonnements/{abonnement}/renouveler', [AbonnementController::class, 'renouveler'])
            ->name('abonnements.renouveler');
        Route::post('/abonnements/{abonnement}/statut', [AbonnementController::class, 'changerStatut'])
            ->name('abonnements.statut');

        // Les clés d'activation — courtes, à usage unique, dictables au téléphone.
        Route::post('/installations/{installation}/cles', [CleActivationController::class, 'emettre'])
            ->name('cles.emettre');
        Route::post('/cles/{cle}/revoquer', [CleActivationController::class, 'revoquer'])
            ->name('cles.revoquer');

        // LES OFFRES — création, modification, retrait de la vente, suppression.
        // '/offres/nouvelle' est déclarée AVANT '/offres/{plan}' : dans l'ordre inverse, le mot
        // « nouvelle » serait pris pour un identifiant d'offre et la page ne s'ouvrirait jamais.
        Route::get('/offres', [PlanController::class, 'index'])->name('plans.index');
        Route::get('/offres/nouvelle', [PlanController::class, 'creer'])->name('plans.creer');
        Route::post('/offres', [PlanController::class, 'enregistrer'])->name('plans.enregistrer');
        Route::get('/offres/{plan}/modifier', [PlanController::class, 'editer'])->name('plans.editer');
        Route::put('/offres/{plan}', [PlanController::class, 'modifier'])->name('plans.modifier');
        Route::post('/offres/{plan}/visibilite', [PlanController::class, 'basculer'])->name('plans.visibilite');
        Route::delete('/offres/{plan}', [PlanController::class, 'supprimer'])->name('plans.supprimer');

        Route::get('/reglages', [ReglageController::class, 'index'])->name('reglages.index');
        Route::put('/reglages', [ReglageController::class, 'enregistrer'])->name('reglages.enregistrer');

        // LES FACTURES ET LEUR ENCAISSEMENT. Un paiement ne solde rien par lui-même : on
        // enregistre des versements, et c'est la FACTURE qui décide quand elle est couverte —
        // ce qui règle sans effort les versements partiels, fréquents en mobile money.
        Route::get('/factures', [FactureController::class, 'index'])->name('factures.index');
        Route::post('/factures/{facture}/paiements', [FactureController::class, 'enregistrerPaiement'])
            ->name('paiements.enregistrer');
        Route::post('/paiements/{paiement}/confirmer', [FactureController::class, 'confirmer'])
            ->name('paiements.confirmer');
        Route::post('/paiements/{paiement}/rejeter', [FactureController::class, 'rejeter'])
            ->name('paiements.rejeter');

        // « Payer maintenant » — pousse une invite mobile money sur le téléphone du client via
        // l'agrégateur (FlexPay). N'encaisse rien : le résultat revient par /webhooks/flexpay.
        // Sans agrégateur configuré, le contrôleur répond « non configuré » et la vue masque le
        // bouton (PasserellePaiement::estActive()).
        Route::post('/factures/{facture}/payer-en-ligne', [PaiementEnLigneController::class, 'demarrer'])
            ->name('paiements.en-ligne');

        Route::get('/demandes', [DemandeController::class, 'index'])->name('demandes.index');
        Route::post('/demandes/{demande}', [DemandeController::class, 'marquer'])->name('demandes.marquer');
    });
});
