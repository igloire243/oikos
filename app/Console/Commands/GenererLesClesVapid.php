<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;
use Throwable;

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
        self::chercherLaConfigurationOpenssl();

        try {
            $cles = VAPID::createVapidKeys();
        } catch (Throwable $erreur) {
            $this->error('OpenSSL refuse de créer la clé : '.$erreur->getMessage());
            $this->line('Sous Windows, c\'est presque toujours un `openssl.cnf` introuvable. Pointez-le puis relancez :');
            $this->line('  PowerShell : $env:OPENSSL_CONF = "$env:USERPROFILE\\.config\\herd\\openssl.cnf"');
            $this->line('  puis       : php artisan push:cles-vapid');

            return self::FAILURE;
        }

        $this->warn('Copiez ces deux lignes dans le .env — puis relancez le serveur.');
        $this->newLine();
        $this->line("VAPID_CLE_PUBLIQUE={$cles['publicKey']}");
        $this->line("VAPID_CLE_PRIVEE={$cles['privateKey']}");
        $this->newLine();
        $this->comment('La clé privée ne doit jamais quitter le serveur ni être commise dans le dépôt.');

        return self::SUCCESS;
    }

    /**
     * SOUS WINDOWS, `openssl_pkey_new` ne sait pas créer une clé EC sans fichier de configuration, et
     * celui que PHP cherche (`C:\\Program Files\\Common Files\\SSL\\openssl.cnf`) n'existe presque
     * jamais : Herd et XAMPP fournissent le leur, ailleurs. On en cherche un avant d'abandonner —
     * ça ne concerne que la GÉNÉRATION des clés, jamais l'envoi, qui signe avec celles qu'on a déjà.
     */
    private static function chercherLaConfigurationOpenssl(): void
    {
        $actuelle = getenv('OPENSSL_CONF');

        if (is_string($actuelle) && $actuelle !== '' && is_file($actuelle)) {
            return;
        }

        $maison = (string) (getenv('USERPROFILE') ?: getenv('HOME') ?: '');
        $dossierPhp = dirname(PHP_BINARY);

        foreach ([
            $maison.'/.config/herd/openssl.cnf',
            $dossierPhp.'/extras/ssl/openssl.cnf',
            $dossierPhp.'/openssl.cnf',
            dirname($dossierPhp).'/apache/conf/openssl.cnf',
            '/etc/ssl/openssl.cnf',
        ] as $candidat) {
            if (is_file($candidat)) {
                putenv('OPENSSL_CONF='.$candidat);

                return;
            }
        }
    }
}
