<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Crée un compte pour accéder à la console.
 *
 * IL N'Y A PAS D'INSCRIPTION EN LIGNE, ET IL N'Y EN AURA PAS. Cette application ne s'ouvre qu'à
 * vous et à qui vous en donnez la clé : une page d'inscription publique sur l'outil qui gère les
 * abonnements de tous vos clients serait la porte la plus large qu'on puisse laisser ouverte.
 * Les comptes se créent donc en ligne de commande, sur le serveur.
 *
 *     php artisan oikos:admin
 */
class CreerAdministrateur extends Command
{
    protected $signature = 'oikos:admin';

    protected $description = 'Crée un compte pour accéder à la console';

    public function handle(): int
    {
        $nom = $this->ask('Nom');
        $email = $this->ask('Adresse e-mail');

        if (User::where('email', $email)->exists()) {
            $this->error('Un compte existe déjà avec cette adresse.');

            return self::FAILURE;
        }

        do {
            $mdp = $this->secret('Mot de passe (8 caractères minimum)');
            $confirmation = $this->secret('Confirmez');

            if (strlen($mdp) < 8) {
                $this->error('  Trop court : 8 caractères minimum.');
                $mdp = '';
            } elseif ($mdp !== $confirmation) {
                $this->error('  Les deux saisies diffèrent.');
                $mdp = '';
            }
        } while ($mdp === '');

        User::create([
            'name' => $nom,
            'email' => $email,
            'password' => Hash::make($mdp),
        ]);

        $this->info("Compte créé. Connectez-vous avec {$email}.");

        return self::SUCCESS;
    }
}
