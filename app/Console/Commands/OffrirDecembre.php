<?php

namespace App\Console\Commands;

use App\Models\Abonnement;
use App\Models\EntreeJournal;
use App\Models\Plan;
use Illuminate\Console\Command;

/**
 * PROMOTION « MOIS DES FÊTES » — décembre offert sur tous les ACCÈS.
 *
 * Chaque année, tout abonnement d'ACCÈS (église, cellule, antenne — la Licence annuelle n'est
 * pas concernée) qui laisse encore écrire gagne UN MOIS de période en plus, gratuitement : sa
 * `periode_fin` et sa `grace_fin` sont repoussées d'un mois, sans facture. Le client n'est donc
 * pas suspendu en décembre même s'il ne « renouvelle » pas.
 *
 * IDEMPOTENTE : la colonne `abonnements.promo_decembre_annee` retient l'année déjà offerte. Rejouer
 * la commande le même décembre ne touche à rien. À planifier au 1ᵉʳ décembre (voir routes/console.php)
 * ou à lancer à la main.
 *
 *     php artisan abonnement:offrir-decembre [--annee=2026]
 */
class OffrirDecembre extends Command
{
    protected $signature = 'abonnement:offrir-decembre {--annee= : Année de la promo (par défaut, l\'année en cours)}';

    protected $description = 'Repousse d\'un mois gratuit tous les accès actifs — promotion de décembre.';

    public function handle(): int
    {
        $annee = (int) ($this->option('annee') ?: now()->year);

        $abonnements = Abonnement::with('plan')
            ->whereIn('statut', Abonnement::OUVRENT_ECRITURE)
            ->where(function ($q) use ($annee) {
                $q->whereNull('promo_decembre_annee')->orWhere('promo_decembre_annee', '<', $annee);
            })
            ->get()
            ->filter(fn (Abonnement $a) => $a->plan?->nature === Plan::ACCES);

        if ($abonnements->isEmpty()) {
            $this->info("Aucun accès à créditer pour {$annee}.");

            return self::SUCCESS;
        }

        $offerts = 0;

        foreach ($abonnements as $abonnement) {
            $finAvant = $abonnement->periode_fin?->copy();

            $abonnement->update([
                'periode_fin' => $abonnement->periode_fin
                    ? $abonnement->periode_fin->copy()->addMonthNoOverflow()
                    : now()->addMonthNoOverflow(),
                'grace_fin' => $abonnement->grace_fin
                    ? $abonnement->grace_fin->copy()->addMonthNoOverflow()
                    : null,
                'promo_decembre_annee' => $annee,
            ]);

            EntreeJournal::noter('PROMO_DECEMBRE_OFFERTE', $abonnement->installation, [
                'annee' => $annee,
                'offre' => $abonnement->plan?->nom,
                'beneficiaire' => $abonnement->beneficiaire_type.' #'.$abonnement->beneficiaire_ref,
                'periode_fin_avant' => $finAvant?->toDateString(),
                'periode_fin_apres' => $abonnement->periode_fin?->toDateString(),
            ]);

            $offerts++;
        }

        $this->info("Décembre {$annee} offert sur {$offerts} accès — période repoussée d'un mois.");

        return self::SUCCESS;
    }
}
