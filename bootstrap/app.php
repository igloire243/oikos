<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',

        // L'API que les installations appellent. Déclarée séparément parce qu'elle ne doit PAS
        // hériter du groupe 'web' : pas de session, pas de cookie, pas de jeton CSRF. Ces appels
        // viennent d'un serveur, et une route de synchronisation qui accepterait un cookie
        // pourrait être déclenchée depuis le navigateur d'un tiers.
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // OÙ ENVOYER UN VISITEUR NON CONNECTÉ.
        //
        // Le middleware `auth` de Laravel cherche par défaut une route nommée « login » ; ici elle
        // s'appelle « connexion », d'où l'erreur « Route [login] not defined » — qui désigne le
        // symptôme et non la cause, et fait chercher une route manquante alors qu'il ne manque
        // qu'une indication.
        $middleware->redirectGuestsTo(fn () => route('connexion'));

        // ET OÙ ENVOYER UN VISITEUR DÉJÀ CONNECTÉ qui rouvre la page de connexion. Par défaut,
        // Laravel le renvoie à « / » — qui est désormais le site public. Il verrait la brochure
        // au lieu de son tableau de bord, et croirait s'être déconnecté.
        $middleware->redirectUsersTo(fn () => route('tableau-bord'));

        // Le webhook de l'agrégateur mobile money (FlexPay) est appelé par un serveur qui n'a pas
        // de session et ne peut donc pas présenter de jeton CSRF. Sa sécurité tient à la signature
        // partagée + la re-vérification serveur à serveur (voir PaiementEnLigneController::webhook).
        $middleware->validateCsrfTokens(except: [
            'webhooks/flexpay',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
