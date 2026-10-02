<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Chaque matin à 8 h 11, à l'heure du serveur : de quoi voir en arrivant ce qui attend, sans
// tomber pile sur l'heure ronde où tout le monde planifie.
Schedule::command('alertes:envoyer')->dailyAt('08:11');
