<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DÉCOUPAGE FIN DE L'ESPACE VISION (Lot 16.5) — report sur les formules déjà éditées en base.
 *
 * `superadmin.entites` ouvrait en bloc le réseau ET ses deux écrans autonomes. Ceux-ci ont
 * maintenant leur clé : `superadmin.transferts` (transferts de membres) et `superadmin.profils`
 * (profils spirituels), pour se déléguer un par un.
 *
 * Report : toute formule qui donnait `superadmin.entites` donne aussi ces deux clés. Les formules
 * « tous modules » (`fonctionnalites` = NULL) ne sont pas touchées — elles ouvrent déjà tout.
 */
return new class extends Migration
{
    private const AJOUTS = ['superadmin.transferts', 'superadmin.profils'];

    public function up(): void
    {
        foreach (DB::table('plans')->get(['plan_id', 'fonctionnalites']) as $plan) {
            if ($plan->fonctionnalites === null) {
                continue;
            }

            $cles = json_decode($plan->fonctionnalites, true);
            if (! is_array($cles) || ! in_array('superadmin.entites', $cles, true)) {
                continue;
            }

            $fusion = array_values(array_unique(array_merge($cles, self::AJOUTS)));

            if ($fusion !== $cles) {
                DB::table('plans')->where('plan_id', $plan->plan_id)
                    ->update(['fonctionnalites' => json_encode($fusion)]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('plans')->get(['plan_id', 'fonctionnalites']) as $plan) {
            if ($plan->fonctionnalites === null) {
                continue;
            }
            $cles = json_decode($plan->fonctionnalites, true);
            if (! is_array($cles)) {
                continue;
            }
            $filtre = array_values(array_diff($cles, self::AJOUTS));
            if ($filtre !== $cles) {
                DB::table('plans')->where('plan_id', $plan->plan_id)
                    ->update(['fonctionnalites' => json_encode($filtre)]);
            }
        }
    }
};
