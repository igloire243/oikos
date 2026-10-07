<?php

use Illuminate\Contracts\Console\Kernel;

/**
 * LE POINT D'ENTRÉE DU PLANIFICATEUR POUR UN HÉBERGEMENT SANS LIGNE DE COMMANDE.
 *
 * Le panneau « Tâches CRON » de certains mutualisés (LWS) refuse `cd … && php artisan …` : il attend UN
 * fichier à exécuter. Ce fichier fait exactement ce que fait `php artisan schedule:run`, sans shell — il
 * démarre l'application et déclenche ce qui est dû à cette minute. Il vit à la racine de l'application
 * (hors du dossier public) : on ne l'appelle jamais par une adresse web.
 */
if (PHP_SAPI !== 'cli') {
    // Appelé par le web, il ne ferait que donner un moyen de déclencher les tâches à volonté.
    http_response_code(404);
    exit;
}

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';

$app->make(Kernel::class)->call('schedule:run');
