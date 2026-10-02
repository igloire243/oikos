<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * GÉNÈRE LA PAIRE DE CLÉS VAPID — une seule fois, à installer dans le `.env`.
 *
 * La clé PUBLIQUE part au navigateur au moment de l'abonnement (`PushManager.subscribe()`) ; la
 * clé PRIVÉE ne quitte jamais le serveur, c'est elle qui signe chaque envoi. Les régénérer
 * invaliderait tous les abonnements déjà posés — ce n'est donc pas un réglage qu'on retouche.
 */
class GenererLesClesVapid extends Command
{
    protected $signature = 'push:cles-vapid';

    protected $description = 'Génère une paire de clés VAPID pour les notifications push (à copier dans le .env)';

    public function handle(): int
    {
        $cles = VAPID::createVapidKeys();

        $this->warn('Copiez ces deux lignes dans le .env — puis relancez le serveur.');
        $this->newLine();
        $this->line("VAPID_CLE_PUBLIQUE={$cles['publicKey']}");
        $this->line("VAPID_CLE_PRIVEE={$cles['privateKey']}");
        $this->newLine();
        $this->comment('La clé privée ne doit jamais quitter le serveur ni être commise dans le dépôt.');

        return self::SUCCESS;
    }
}
