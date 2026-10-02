<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Ce que toute console neuve doit contenir : le catalogue d'offres, et rien d'autre.
 *
 * Pas de compte d'opérateur ici — il se crée par `php artisan oikos:operateur`, avec un vrai mot de
 * passe. Le squelette Laravel posait `test@example.com` : sur la console qui signe les licences de
 * tout le parc, un compte au mot de passe connu de tous serait la porte d'entrée.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(OffreSeeder::class);
    }
}
