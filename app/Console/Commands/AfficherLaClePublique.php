<?php

namespace App\Console\Commands;

use App\Metier\Licence\Signature;
use Illuminate\Console\Command;

/**
 * RETROUVE LA CLÉ PUBLIQUE d'une clé privée déjà posée dans le .env.
 *
 * `oikos:cles-signature` n'affiche la clé publique qu'à la fabrication de la paire, et refuse d'en
 * refaire une tant qu'il en existe une (en fabriquer une nouvelle invaliderait toutes les licences
 * déjà posées chez les clients). Si on a perdu la sortie de cette commande, la clé publique ne se
 * perd pas pour autant : elle se DÉDUIT de la privée. Cette commande ne génère rien et ne modifie rien.
 */
class AfficherLaClePublique extends Command
{
    protected $signature = 'oikos:cle-publique';

    protected $description = 'Affiche la clé publique qui correspond à la clé privée du .env (rien n\'est généré)';

    public function handle(): int
    {
        $publique = Signature::clePubliqueDeduite();

        if ($publique === null) {
            $this->error('Aucune clé privée lisible dans le .env (LICENCE_CLE_PRIVEE). Lancez : php artisan oikos:cles-signature');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("À coller dans config/oikos.php DU PRODUIT, entre les guillemets de 'cle_publique' :");
        $this->newLine();
        $this->line("'cle_publique' => '".$publique."',");
        $this->newLine();
        $this->comment('Elle se lit sans danger : elle vérifie, elle ne signe pas. Elle va dans le code du produit, jamais dans son .env.');

        return self::SUCCESS;
    }
}
