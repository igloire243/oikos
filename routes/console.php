<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// PROMOTION « MOIS DES FÊTES » — décembre offert sur tous les accès.
// Planifiée au 1ᵉʳ décembre à 02:00, idempotente (voir App\Console\Commands\OffrirDecembre).
// Suppose que `php artisan schedule:run` tourne en cron sur le serveur ; sinon, à lancer à la main.
Schedule::command('abonnement:offrir-decembre')->yearlyOn(12, 1, '02:00');
