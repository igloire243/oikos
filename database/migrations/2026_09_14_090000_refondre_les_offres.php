<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REFONTE DES OFFRES — de « un prix par niveau hiérarchique » à « une licence + des accès ».
 *
 * CE QUI N'ALLAIT PAS
 * --------------------
 * L'ancienne grille demandait au prospect de comprendre la hiérarchie du produit AVANT de
 * comprendre son prix : il fallait savoir ce qu'était une « antenne », deviner à quel niveau on se
 * situait, puis additionner mentalement une cascade d'abonnements pour obtenir son coût réel. Un
 * pasteur qui veut savoir combien ça coûte n'a pas à apprendre notre modèle de données.
 *
 * LE NOUVEAU MODÈLE, EN UNE PHRASE
 * ---------------------------------
 * La vision paie UNE licence qui allume le système pour toute la structure ; ensuite chaque entité
 * paie SON accès. Deux natures de vente, et non trois niveaux à empiler.
 *
 * LES QUATRE COLONNES AJOUTÉES, ET CE QU'ELLES RÉSOLVENT
 * -------------------------------------------------------
 * `nature`  — LICENCE, ACCES ou COMBINEE. C'est la colonne qui remplace la lecture par niveau.
 *             COMBINEE existe pour l'église seule : elle achète licence et accès en un seul prix,
 *             parce qu'une assemblée de quatre-vingts membres à qui l'on présente une addition de
 *             deux lignes referme la page.
 *
 * `palier`  — STARTER, STANDARD ou PREMIUM. Le même vocabulaire pour les trois natures : c'est ce
 *             qui permet de dire « Standard » et d'être compris sans préciser de quoi on parle.
 *
 * `paliers_taille` — LA GRILLE DE PRIX DE LA LICENCE, RANGÉE DANS LE PLAN plutôt qu'éclatée en
 *             lignes. Vous vouliez un prix de licence variable selon la taille du réseau ET des
 *             paliers Starter/Standard/Premium : croiser les deux en lignes distinctes donnerait
 *             neuf offres de licence à afficher, c'est-à-dire exactement la grille illisible qu'on
 *             est en train de corriger. Ici, trois offres seulement, chacune portant sa petite
 *             grille de tailles. Le client lit « Standard, à partir de 40 $ », puis, s'il veut le
 *             détail, trois lignes de paliers dans la même carte.
 *
 * `plafond_acces` — CE QUI DONNE UN SENS AUX NIVEAUX DE LICENCE. Sans lui, « licence Premium »
 *             ne voudrait rien dire de vérifiable. Avec lui, la licence fixe le palier maximum que
 *             les entités du réseau peuvent souscrire en dessous : une licence Starter ne laisse
 *             pas une église prendre un accès Premium. C'est une règle que le code peut faire
 *             respecter, pas un argument de brochure.
 *
 * `niveau` accepte désormais la valeur TOUS : un accès ne dépend plus de la position dans la
 * hiérarchie, une antenne et une cellule achètent le même Standard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // LICENCE | ACCES | COMBINEE — la valeur par défaut couvre les lignes existantes, qui
            // étaient toutes des accès par entité avant cette refonte.
            $table->string('nature', 12)->default('ACCES')->after('code');

            $table->string('palier', 12)->nullable()->after('nature');   // STARTER | STANDARD | PREMIUM

            // [{"max": 5, "prix_usd_cents": 2500, "prix_cdf": 70000}, {"max": null, …}]
            // `max` null = le dernier palier, celui qui n'a pas de plafond.
            $table->json('paliers_taille')->nullable()->after('prix_cdf');

            $table->string('plafond_acces', 12)->nullable()->after('paliers_taille');

            // L'ordre d'affichage. Sans lui, il faudrait trier par prix — et une offre
            // volontairement peu chère se retrouverait présentée en premier alors qu'elle n'est
            // pas celle qu'on veut mettre en avant.
            $table->unsignedSmallInteger('ordre')->default(50)->after('is_public');

            $table->index(['nature', 'is_public'], 'idx_plan_nature_public');
        });

        // LES ANCIENNES OFFRES SONT RETIRÉES DE LA VENTE, PAS SUPPRIMÉES.
        //
        // Des abonnements pointent peut-être déjà sur elles, et `abonnements.plan_id` a une clé
        // étrangère : les effacer ferait échouer la migration, ou — pire, si la contrainte venait
        // à manquer — laisserait des abonnements dont on ne saurait plus ce qui avait été vendu ni
        // à quel prix. On les rend simplement invisibles : le site public ne montre que
        // `is_public = true`, l'historique reste lisible, et un client déjà servi n'est pas
        // renommé sous ses pieds.
        DB::table('plans')
            ->whereIn('code', ['VISION_STANDARD', 'ANTENNE_STANDARD', 'EGLISE_SECTEUR', 'EGLISE_CELLULE'])
            ->update(['is_public' => false, 'ordre' => 80]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropIndex('idx_plan_nature_public');
            $table->dropColumn(['nature', 'palier', 'paliers_taille', 'plafond_acces', 'ordre']);
        });
    }
};
