<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // L'hébergeur place un relais devant PHP : on lui fait confiance pour le schéma (https) et l'adresse du client.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // La notification d'un fournisseur de paiement vient de SON serveur, sans notre jeton CSRF :
        // c'est l'interrogation directe du fournisseur (confirmer) qui fait foi, pas ce corps de requête.
        $middleware->validateCsrfTokens(except: ['payer/notification/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // LES PAGES D'ERREUR PORTENT L'IDENTITÉ DE LA CONSOLE — comme celles du produit. Sans ça, un
        // lien mort ou un écran refusé tombait sur la page nue du framework, en anglais, sans logo ni
        // bouton pour revenir. Le statut HTTP ne change pas ; en local, une 500 garde la trace
        // détaillée, qui sert à corriger. Les requêtes JSON (l'API des installations) gardent du JSON.
        $exceptions->respond(function (Response $reponse, Throwable $erreur, Request $requete) {
            $statut = $reponse->getStatusCode();

            if ($requete->expectsJson() || $requete->is('api/*')) {
                return $reponse;
            }

            // 419 : la session a expiré pendant qu'un formulaire restait ouvert. Une page d'erreur
            // ferait perdre ce qui était tapé ; on revient sur l'écran, avec un mot.
            if ($statut === 419) {
                return back()->with('erreur', 'La page était restée ouverte trop longtemps. Réessayez.');
            }

            $affichees = [403, 404, 429, 503];
            if (! config('app.debug')) {
                $affichees[] = 500;
            }

            if (! in_array($statut, $affichees, true)) {
                return $reponse;
            }

            return Inertia::render('Erreur', ['statut' => $statut])
                ->toResponse($requete)
                ->setStatusCode($statut);
        });
    })->create();
