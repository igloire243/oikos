<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Derrière le serveur de cache et le terminateur HTTPS d'un hébergeur mutualisé, PHP reçoit la requête en
        // `http://` : Laravel écrivait alors des redirections en `http://`, et la connexion (une requête XHR, avec
        // son écran qui « tourne ») était bloquée par le navigateur comme contenu mixte — sans la moindre erreur
        // visible. Quand l'adresse de l'application est en https, toutes les adresses produites le sont aussi.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
