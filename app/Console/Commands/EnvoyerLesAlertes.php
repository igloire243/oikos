<?php

namespace App\Console\Commands;

use App\Metier\Notifications\Alertes;
use Illuminate\Console\Command;

class EnvoyerLesAlertes extends Command
{
    protected $signature = 'alertes:envoyer';

    protected $description = 'Prévient les opérateurs des factures en retard et des abonnements qui s\'achèvent';

    public function handle(): int
    {
        $envoyees = Alertes::envoyer();

        $this->info("Factures en retard : {$envoyees['retards']} · abonnements qui s'achèvent : {$envoyees['echeances']}.");

        return self::SUCCESS;
    }
}
