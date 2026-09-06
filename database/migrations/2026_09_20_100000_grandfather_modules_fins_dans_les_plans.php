<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DÉCOUPAGE FIN DES MODULES (Lot 16) — report sur les formules déjà éditées en base.
 *
 * Deux clés nouvelles côté produit :
 *   · `secteur.transferts` — était une sous-fonction de `secteur.membres`, séparée pour se vendre à
 *     part. Report : toute formule qui donnait `secteur.membres` donne aussi `secteur.transferts`.
 *   · l'espace ANTENNE, jusqu'ici ouvert en bloc (aucune formule ne listait ses clés), gagne un jeu
 *     complet de modules. Report GÉNÉREUX : toute formule d'accès à une entité (celles qui portent
 *     `secteur.membres`) reçoit l'ensemble des modules d'antenne vendables — on ne retire aucun
 *     accès dont un client bénéficiait de fait. Les nouvelles formules du PlanSeeder, elles, sont
 *     déjà découpées par palier.
 *
 * Les formules « tous modules » (`fonctionnalites` = NULL) ne sont pas touchées : elles ouvrent
 * déjà tout, clés fines comprises.
 */
return new class extends Migration
{
    private const ANTENNE_VENDABLES = [
        'antenne.extensions', 'antenne.bergers', 'antenne.membres', 'antenne.transferts',
        'antenne.pastoral', 'antenne.visites', 'antenne.services', 'antenne.departements',
        'antenne.finances', 'antenne.rapports', 'antenne.reunions', 'antenne.dossiers',
        'antenne.communications',
    ];

    public function up(): void
    {
        foreach (DB::table('plans')->get(['plan_id', 'fonctionnalites']) as $plan) {
            if ($plan->fonctionnalites === null) {
                continue;   // « tous modules » : rien à faire
            }

            $cles = json_decode($plan->fonctionnalites, true);
            if (! is_array($cles)) {
                continue;
            }

            $avant = $cles;

            if (in_array('secteur.membres', $cles, true)) {
                $cles[] = 'secteur.transferts';
                $cles = array_merge($cles, self::ANTENNE_VENDABLES);
            }

            $cles = array_values(array_unique($cles));

            if ($cles !== $avant) {
                DB::table('plans')->where('plan_id', $plan->plan_id)
                    ->update(['fonctionnalites' => json_encode($cles)]);
            }
        }
    }

    public function down(): void
    {
        $retirer = array_merge(['secteur.transferts'], self::ANTENNE_VENDABLES);

        foreach (DB::table('plans')->get(['plan_id', 'fonctionnalites']) as $plan) {
            if ($plan->fonctionnalites === null) {
                continue;
            }
            $cles = json_decode($plan->fonctionnalites, true);
            if (! is_array($cles)) {
                continue;
            }
            $filtre = array_values(array_diff($cles, $retirer));
            if ($filtre !== $cles) {
                DB::table('plans')->where('plan_id', $plan->plan_id)
                    ->update(['fonctionnalites' => json_encode($filtre)]);
            }
        }
    }
};
