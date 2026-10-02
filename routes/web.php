<?php

use App\Http\Controllers\AbonnementsController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\DemandePubliqueController;
use App\Http\Controllers\DemandesController;
use App\Http\Controllers\FacturesController;
use App\Http\Controllers\InstallationsController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\OffresController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\ReglagesController;
use App\Http\Controllers\TableauDeBordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| La console de l'éditeur
|--------------------------------------------------------------------------
|
| TOUT CE QUI VOUS APPARTIENT VIT SOUS /console — connexion (Fortify, `prefix` => console), profil
| (Jetstream, remonté ici) et écrans. La racine reviendra au site commercial ; une route
| d'administration égarée à la racine se verrait en relisant ce fichier.
|
*/

Route::redirect('/', '/console');

// La seule porte publique de la console avec l'API machine : un formulaire borné et freiné.
Route::get('/demande', [DemandePubliqueController::class, 'formulaire'])->name('demande.formulaire');
Route::post('/demande', [DemandePubliqueController::class, 'envoyer'])->middleware('throttle:5,1')->name('demande.envoyer');

Route::prefix('console')->group(function () {
    // Le profil de Jetstream (mot de passe, double authentification, sessions), sous /console.
    require base_path('vendor/laravel/jetstream/routes/inertia.php');

    Route::middleware(['auth:sanctum', config('jetstream.auth_session')])
        ->name('console.')
        ->group(function () {
            Route::get('/', TableauDeBordController::class)->name('accueil');
            // Un réglage de l'APPAREIL, pas un écran : tout opérateur connecté peut s'abonner sur
            // le téléphone qu'il tient en main.
            Route::post('/push/abonnements', [PushController::class, 'abonner'])->name('push.abonner');
            Route::delete('/push/abonnements', [PushController::class, 'desabonner'])->name('push.desabonner');

            Route::get('/catalogue', [CatalogueController::class, 'index'])->name('catalogue.index');

            Route::prefix('clients')->name('clients.')->group(function () {
                Route::get('/', [ClientsController::class, 'index'])->name('index');
                Route::post('/', [ClientsController::class, 'store'])->name('store');
                Route::get('/{client}', [ClientsController::class, 'show'])->name('show');
                Route::put('/{client}', [ClientsController::class, 'update'])->name('update');
                Route::post('/{client}/installations', [InstallationsController::class, 'store'])->name('installations.store');
            });

            Route::prefix('installations')->name('installations.')->group(function () {
                Route::put('/{installation}', [InstallationsController::class, 'update'])->name('update');
                Route::patch('/{installation}/activation', [InstallationsController::class, 'activation'])->name('activation');
                Route::post('/{installation}/cles', [InstallationsController::class, 'emettreCle'])->name('cles.store');
                Route::post('/{installation}/rappel', [InstallationsController::class, 'rappeler'])->name('rappel');
            });
            Route::patch('/cles/{cle}/revocation', [InstallationsController::class, 'revoquerCle'])->name('cles.revoquer');

            Route::prefix('offres')->name('offres.')->group(function () {
                Route::get('/', [OffresController::class, 'index'])->name('index');
                Route::post('/', [OffresController::class, 'store'])->name('store');
                Route::put('/{offre}', [OffresController::class, 'update'])->name('update');
                Route::patch('/{offre}/retrait', [OffresController::class, 'retirer'])->name('retirer');
                Route::patch('/{offre}/retablissement', [OffresController::class, 'retablir'])->name('retablir');
            });

            Route::prefix('factures')->name('factures.')->group(function () {
                Route::get('/', [FacturesController::class, 'index'])->name('index');
                Route::post('/{facture}/paiements', [FacturesController::class, 'encaisser'])->name('encaisser');
            });
            Route::patch('/paiements/{paiement}/non-recu', [FacturesController::class, 'nonRecu'])->name('paiements.non_recu');
            Route::patch('/paiements/{paiement}/retablissement', [FacturesController::class, 'retablir'])->name('paiements.retablir');

            Route::get('/demandes', [DemandesController::class, 'index'])->name('demandes.index');
            Route::patch('/demandes/{demande}/traitement', [DemandesController::class, 'traiter'])->name('demandes.traiter');
            Route::get('/reglages', [ReglagesController::class, 'index'])->name('reglages.index');
            Route::put('/reglages', [ReglagesController::class, 'update'])->name('reglages.update');
            Route::get('/journal', [JournalController::class, 'index'])->name('journal.index');

            // Vendre se fait sur une ENTITÉ, jamais sur un client en bloc.
            Route::get('/entites/{entite}/vente', [AbonnementsController::class, 'apercu'])->name('ventes.apercu');
            Route::post('/entites/{entite}/vente', [AbonnementsController::class, 'store'])->name('ventes.store');
            Route::patch('/abonnements/{abonnement}/resiliation', [AbonnementsController::class, 'resilier'])->name('abonnements.resilier');
        });
});
