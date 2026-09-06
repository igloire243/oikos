<?php

namespace App\Providers;

use App\Support\Paiement\FlexPayPasserelle;
use App\Support\Paiement\PasserelleInactive;
use App\Support\Paiement\PasserellePaiement;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // LA PASSERELLE D'ENCAISSEMENT — un seul point de choix.
        //
        // Tant que config('flexpay.actif') est faux, tout le code qui dépend de PasserellePaiement
        // reçoit PasserelleInactive : estActive() renvoie false, le bouton « Payer maintenant »
        // disparaît, l'encaissement reste manuel. Brancher un autre agrégateur un jour = une classe
        // de plus et une branche ici.
        $this->app->singleton(PasserellePaiement::class, function () {
            if (! config('flexpay.actif')) {
                return new PasserelleInactive();
            }

            return new FlexPayPasserelle(
                baseUrl: config('flexpay.base_url'),
                marchand: config('flexpay.marchand'),
                jeton: config('flexpay.jeton'),
                secretWebhook: config('flexpay.secret_webhook'),
                devise: config('flexpay.devise'),
                callbackUrl: config('flexpay.callback_url'),
                timeout: (int) config('flexpay.timeout', 15),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
