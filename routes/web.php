<?php

use App\Http\Controllers\CatalogueController;
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

Route::prefix('console')->group(function () {
    // Le profil de Jetstream (mot de passe, double authentification, sessions), sous /console.
    require base_path('vendor/laravel/jetstream/routes/inertia.php');

    Route::middleware(['auth:sanctum', config('jetstream.auth_session')])
        ->name('console.')
        ->group(function () {
            Route::get('/', TableauDeBordController::class)->name('accueil');
            Route::get('/catalogue', [CatalogueController::class, 'index'])->name('catalogue.index');
        });
});
