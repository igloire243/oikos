<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * CRÉER UN COMPTE D'OPÉRATEUR — le seul chemin, puisque la console n'a pas d'inscription.
 *
 * La console détient la clé qui signe les licences de tout le parc : un formulaire d'inscription
 * ouvert sur Internet serait une porte vers elle. Un compte se crée donc depuis le serveur, par
 * quelqu'un qui y a déjà accès.
 */
class CreerOperateur extends Command
{
    protected $signature = 'oikos:operateur {email?} {--nom=}';

    protected $description = "Crée (ou réinitialise) un compte d'opérateur de la console";

    public function handle(): int
    {
        $email = (string) ($this->argument('email') ?? $this->ask('Adresse électronique'));
        $nom = (string) ($this->option('nom') ?? $this->ask('Nom affiché', 'Opérateur'));
        $motDePasse = (string) $this->secret('Mot de passe (12 caractères au moins)');

        $validation = Validator::make(
            ['email' => $email, 'nom' => $nom, 'mot_de_passe' => $motDePasse],
            ['email' => ['required', 'email'], 'nom' => ['required', 'string', 'max:120'], 'mot_de_passe' => ['required', 'string', 'min:12']],
        );

        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $existant = User::query()->where('email', $email)->exists();

        User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $nom, 'password' => Hash::make($motDePasse)],
        );

        $this->info($existant ? "Mot de passe de {$email} réinitialisé." : "Opérateur {$email} créé. Connexion : /console/login");

        return self::SUCCESS;
    }
}
