<?php

namespace App\Console\Commands;

use App\Metier\Licence\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Fabrique la paire de clés qui signe les licences. À lancer UNE FOIS, avant la première livraison.
 *
 * La commande n'écrit rien nulle part, et c'est délibéré : elle affiche les deux clés et vous dit
 * où les mettre. Écrire automatiquement dans le .env d'un côté et dans un fichier de configuration
 * de l'autre supposerait connaître le chemin du produit depuis la console — deux dépôts, deux
 * serveurs, souvent deux machines. Un copier-coller conscient vaut mieux qu'une écriture magique
 * qui se trompe de fichier.
 */
class CreerClesSignature extends Command
{
    protected $signature = 'oikos:cles-signature {--forcer : Passer outre l\'avertissement si une clé existe déjà}';

    protected $description = 'Fabrique la paire de clés qui signe les licences';

    public function handle(): int
    {
        if (Signature::estConfiguree() && ! $this->option('forcer')) {
            $this->error('Une clé privée est déjà configurée.');
            $this->newLine();
            $this->line('  En fabriquer une nouvelle INVALIDERA toutes les licences déjà posées chez');
            $this->line('  vos clients : leurs installations refuseront leur fichier au prochain');
            $this->line("  démarrage, jusqu'à ce que la nouvelle clé publique soit livrée dans une");
            $this->line('  mise à jour du produit.');
            $this->newLine();
            $this->line("  Si c'est bien ce que vous voulez : --forcer");

            return self::FAILURE;
        }

        try {
            $cles = Signature::fabriquerLesCles();
        } catch (Throwable $e) {
            return $this->expliquerLEchec($e->getMessage());
        }

        $this->newLine();
        $this->info('=== 1. DANS LE .env DE CETTE CONSOLE ===');
        $this->newLine();
        $this->line('LICENCE_CLE_PRIVEE='.$cles['privee']);
        $this->newLine();
        $this->warn('  Ce secret ne doit jamais quitter ce serveur, ni entrer dans Git.');
        $this->newLine();

        $this->info('=== 2. DANS config/cle_publique.php DU PRODUIT ===');
        $this->newLine();
        $this->line("'valeur' => '".$cles['publique']."',");
        $this->newLine();
        $this->line('  Celle-ci se lit sans danger : elle vérifie, elle ne signe pas.');
        $this->line('  Elle va DANS LE CODE, pas dans le .env — un client qui peut la remplacer');
        $this->line('  peut se signer ses propres licences.');
        $this->newLine();

        $this->info("=== 3. L'ORDRE DE BASCULE ===");
        $this->newLine();
        $this->line('  a. Posez la clé privée ici, puis  php artisan optimize:clear');
        $this->line("  b. Vérifiez qu'une installation se synchronise normalement.");
        $this->line('     Elle reçoit désormais des licences signées, sans encore les vérifier.');
        $this->line('  c. Alors seulement, livrez la clé publique dans le produit.');
        $this->newLine();
        $this->warn("  Dans l'ordre inverse, les installations refuseraient des licences non signées");
        $this->warn('  et se fermeraient toutes en même temps.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * L'échec le plus probable sous Windows, et sa sortie de secours.
     *
     * PHP pour Windows est livré sans `openssl.cnf` configuré. `openssl_pkey_new()` échoue alors
     * avec un message de bas niveau parfaitement opaque — « error:...:configuration file routines »
     * — qui n'apprend rien à personne. Comme cette commande ne se lance qu'une fois dans la vie du
     * projet, autant que cette fois-là ne coûte pas une soirée.
     */
    private function expliquerLEchec(string $detail): int
    {
        $this->error("openssl n'a pas pu fabriquer la paire de clés.");
        $this->newLine();
        $this->line('  Détail : '.$detail);
        $this->newLine();

        $this->info('SOUS WINDOWS, C\'EST PRESQUE TOUJOURS LE FICHIER openssl.cnf QUI MANQUE.');
        $this->newLine();
        $this->line('  Deux façons d\'en sortir, au choix.');
        $this->newLine();

        $this->line('  A. Indiquer le fichier de configuration à PHP, puis relancer :');
        $this->newLine();
        $this->line('       $env:OPENSSL_CONF="C:\\php\\extras\\ssl\\openssl.cnf"');
        $this->line('       php artisan oikos:cles-signature');
        $this->newLine();
        $this->line('     (adaptez le chemin : cherchez openssl.cnf dans votre dossier PHP)');
        $this->newLine();

        $this->line('  B. Fabriquer la paire avec l\'outil openssl en ligne de commande,');
        $this->line('     livré avec Git pour Windows :');
        $this->newLine();
        $this->line('       openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out privee.pem');
        $this->line('       openssl rsa -in privee.pem -pubout -out publique.pem');
        $this->newLine();
        $this->line('     puis convertissez chaque fichier en une ligne :');
        $this->newLine();
        $this->line('       php -r "echo base64_encode(file_get_contents(\'privee.pem\'));"');
        $this->line('       php -r "echo base64_encode(file_get_contents(\'publique.pem\'));"');
        $this->newLine();
        $this->warn('     Supprimez privee.pem du disque une fois la valeur reportée dans le .env.');
        $this->newLine();

        return self::FAILURE;
    }
}
