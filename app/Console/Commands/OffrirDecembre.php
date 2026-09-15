<?php

namespace App\Console\Commands;

use App\Support\PromoDecembre;
use Illuminate\Console\Command;

/**
 * PROMOTION « MOIS DES FÊTES » — décembre offert sur tous les ACCÈS.
 *
 * La règle elle-même vit dans App\Support\PromoDecembre : cette commande n'est qu'une des DEUX
 * portes, l'autre étant le bouton de l'écran Réglages. La logique était ici à l'origine ; l'y
 * laisser aurait obligé le contrôleur à la recopier, et deux copies d'une règle qui déplace des
 * dates d'échéance finissent par créditer un client deux fois, ou pas du tout.
 *
 * Planifiée au 1ᵉʳ décembre (voir routes/console.php) — ce qui suppose un `schedule:run` en cron sur
 * le serveur. Le bouton existe précisément parce que cette condition n'est pas toujours remplie.
 *
 *     php artisan abonnement:offrir-decembre [--annee=2026]
 */
class OffrirDecembre extends Command
{
    protected $signature = 'abonnement:offrir-decembre {--annee= : Année de la promo (par défaut, l\'année en cours)}';

    protected $description = 'Repousse d\'un mois gratuit tous les accès actifs — promotion de décembre.';

    public function handle(): int
    {
        $resultat = PromoDecembre::offrir($this->option('annee') ? (int) $this->option('annee') : null);

        if ($resultat['offerts'] === 0) {
            // On distingue « rien à créditer » de « déjà fait » : le second n'est pas un échec, et
            // l'afficher comme tel ferait relancer la commande pour rien.
            $this->info($resultat['deja'] > 0
                ? "Décembre {$resultat['annee']} était déjà offert sur {$resultat['deja']} accès — rien à faire."
                : "Aucun accès à créditer pour {$resultat['annee']}.");

            return self::SUCCESS;
        }

        $this->info("Décembre {$resultat['annee']} offert sur {$resultat['offerts']} accès — période repoussée d'un mois.");

        if ($resultat['deja'] > 0) {
            $this->line("  ({$resultat['deja']} accès l'avaient déjà.)");
        }

        return self::SUCCESS;
    }
}
