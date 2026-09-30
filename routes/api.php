<?php

use App\Http\Controllers\Api\LicenceController;
use Illuminate\Support\Facades\Route;

/*
| Les routes MACHINE : ce sont les installations du produit qui les appellent, jamais un navigateur.
| Deux routes, pas davantage — chaque route ouverte sur Internet est une porte à garder.
*/
Route::prefix('v1')->group(function () {
    // Une clé courte se devine moins bien quand on ne peut en essayer que dix par minute.
    Route::post('/activation', [LicenceController::class, 'activer'])->middleware('throttle:10,1');
    Route::post('/synchronisation', [LicenceController::class, 'synchroniser'])->middleware('throttle:60,1');
});
