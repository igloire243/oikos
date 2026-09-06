<?php

namespace App\Providers;

use App\Support\Paiement\CinetPayPasserelle;
use App\Support\Paiement\ConfigPasserelle;
use App\Support\Paiement\FlexPayPasserelle;
use App\Support\Paiement\PasserelleInactive;
use App\Support\Paiement\PasserellePaiement;
use App\Support\Paiement\PawaPayPasserelle;
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
        // L'agrégateur et ses identifiants viennent de la console (écran /console/paiement), via
        // App\Support\Paiement\ConfigPasserelle, avec repli sur l'ancienne config .env de FlexPay.
        // Tant que rien n'est actif, tout le code reçoit PasserelleInactive : estActive() = false,
        // le bouton « Payer maintenant » disparaît, l'encaissement reste manuel.
        //
        // Le singleton est PARESSEUX : il n'interroge la base que lorsqu'un encaissement en ligne
        // est réellement demandé — jamais pendant une migration.
        $this->app->singleton(PasserellePaiement::class, function () {
            try {
                if (! ConfigPasserelle::actif()) {
                    return new PasserelleInactive();
                }

                $s = ConfigPasserelle::secrets();
                $callback = config('flexpay.callback_url');
                $timeout = (int) config('flexpay.timeout', 15);

                return match (ConfigPasserelle::agregateur()) {
                    ConfigPasserelle::PAWAPAY => new PawaPayPasserelle(
                        baseUrl: (string) ($s['base_url'] ?? ''),
                        jeton: (string) ($s['jeton'] ?? ''),
                        secretWebhook: (string) ($s['secret_webhook'] ?? ''),
                        devise: strtoupper((string) ($s['devise'] ?? 'USD')),
                        callbackUrl: $callback,
                        timeout: $timeout,
                    ),
                    ConfigPasserelle::CINETPAY => new CinetPayPasserelle(
                        baseUrl: (string) ($s['base_url'] ?? ''),
                        siteId: (string) ($s['site_id'] ?? ''),
                        apiKey: (string) ($s['api_key'] ?? ''),
                        secretKey: (string) ($s['secret_key'] ?? ''),
                        devise: strtoupper((string) ($s['devise'] ?? 'USD')),
                        callbackUrl: $callback,
                        returnUrl: null,
                        timeout: $timeout,
                    ),
                    ConfigPasserelle::FLEXPAY => new FlexPayPasserelle(
                        baseUrl: (string) ($s['base_url'] ?? config('flexpay.base_url')),
                        marchand: (string) ($s['marchand'] ?? config('flexpay.marchand')),
                        jeton: (string) ($s['jeton'] ?? config('flexpay.jeton')),
                        secretWebhook: (string) ($s['secret_webhook'] ?? config('flexpay.secret_webhook')),
                        devise: strtoupper((string) ($s['devise'] ?? config('flexpay.devise'))),
                        callbackUrl: $callback,
                        timeout: $timeout,
                    ),
                    default => new PasserelleInactive(),
                };
            } catch (\Throwable $e) {
                // Config illisible, base injoignable… : on ne casse jamais l'application pour ça,
                // l'encaissement retombe simplement en manuel.
                return new PasserelleInactive();
            }
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
