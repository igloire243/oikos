<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * LES CLÉS DES MODULES ONT CHANGÉ DE NOM — report sur les offres déjà éditées en base.
 *
 * Le produit (version 2) nomme ses modules `vision.*`, `extension.*` et `departement.*` là où l'ancien
 * disait `superadmin.*`, `secteur.*` et `department.*`. Ces clés voyagent dans la licence : une offre qui
 * garderait les anciennes ne rouvrirait AUCUN module chez le client (« un module vendu que rien n'ouvre »).
 *
 * Les offres « tous modules » (`fonctionnalites` = NULL) ne sont pas touchées, elles ouvrent déjà tout. Une
 * clé inconnue reste telle quelle plutôt que d'être effacée en silence ; `commission.tableau` (l'espace
 * commission n'existe plus) est retirée.
 *
 * Ce qui était ouvert hier le reste : la messagerie de chaque espace (invendable hier, vendable aujourd'hui)
 * et l'espace personnel des fidèles s'ajoutent à toute offre qui ouvre déjà la famille concernée. Les modules
 * NOUVEAUX ne sont PAS ajoutés ici : c'est une décision commerciale, portée par `PlanSeeder`.
 */
return new class extends Migration
{
    private const RENOMMES = [
        'users' => 'comptes', 'programs' => 'programmes', 'reports' => 'rapports', 'media' => 'medias', 'settings' => 'parametres',
    ];

    /** Famille (préfixe) → ce qu'une offre qui l'ouvre garde ouvert. */
    private const GARDES_OUVERTS = [
        'vision.' => ['vision.messagerie'],
        'extension.' => ['extension.espace_membre', 'departement.messagerie'],
        'antenne.' => ['antenne.messagerie'],
    ];

    public function up(): void
    {
        foreach (DB::table('plans')->get(['plan_id', 'fonctionnalites']) as $plan) {
            if ($plan->fonctionnalites === null) {
                continue;
            }

            $cles = json_decode($plan->fonctionnalites, true);

            if (! is_array($cles)) {
                continue;
            }

            $traduites = [];

            foreach ($cles as $cle) {
                $nouvelle = $this->traduire((string) $cle);

                if ($nouvelle !== null) {
                    $traduites[] = $nouvelle;
                }
            }

            foreach (self::GARDES_OUVERTS as $famille => $ajouts) {
                if (collect($traduites)->contains(fn ($c) => str_starts_with($c, $famille))) {
                    $traduites = array_merge($traduites, $ajouts);
                }
            }

            $traduites = array_values(array_unique($traduites));

            if ($traduites !== $cles) {
                DB::table('plans')->where('plan_id', $plan->plan_id)->update(['fonctionnalites' => json_encode($traduites)]);
            }
        }
    }

    public function down(): void
    {
        // Retour aux anciens noms ; les ajouts ne sont pas retirés (ils ouvraient déjà ce qui l'était).
        $inverses = array_flip(self::RENOMMES);

        foreach (DB::table('plans')->get(['plan_id', 'fonctionnalites']) as $plan) {
            $cles = $plan->fonctionnalites === null ? null : json_decode($plan->fonctionnalites, true);

            if (! is_array($cles)) {
                continue;
            }

            $anciennes = array_map(function ($cle) use ($inverses) {
                [$espace, $suffixe] = array_pad(explode('.', (string) $cle, 2), 2, '');

                return match ($espace) {
                    'vision' => 'superadmin.'.($inverses[$suffixe] ?? $suffixe),
                    'extension' => 'secteur.'.$suffixe,
                    'departement' => 'department.'.$suffixe,
                    default => $cle === 'antenne.prieres' ? 'antenne.pastoral' : $cle,
                };
            }, $cles);

            DB::table('plans')->where('plan_id', $plan->plan_id)->update(['fonctionnalites' => json_encode(array_values(array_unique($anciennes)))]);
        }
    }

    private function traduire(string $cle): ?string
    {
        [$espace, $suffixe] = array_pad(explode('.', $cle, 2), 2, '');

        return match (true) {
            $cle === 'commission.tableau' => null,
            $cle === 'antenne.pastoral' => 'antenne.prieres',
            $espace === 'superadmin' => 'vision.'.(self::RENOMMES[$suffixe] ?? $suffixe),
            $espace === 'secteur' => 'extension.'.$suffixe,
            $espace === 'department' => 'departement.'.$suffixe,
            default => $cle,
        };
    }
};
