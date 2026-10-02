<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LOT C2 — CE QU'ON VEND, ET À QUI.
 *
 * Trois tables, chacune avec un seul sens :
 *
 *   · `offres` — le catalogue commercial. Une offre se RETIRE, elle ne se supprime pas : des
 *     périodes déjà vendues la référencent, et « qu'avait-il acheté en mars ? » doit garder une
 *     réponse.
 *   · `abonnements` — UN par entité vendue (la Vision, une antenne, une église), jamais un par
 *     client : douze églises, douze abonnements, douze échéances.
 *   · `periodes_abonnement` — ce qui a été vendu, période par période, avec le PRIX FIGÉ le jour
 *     de la vente. L'ancienne console ne gardait qu'une `periode_fin` sur l'abonnement : chaque
 *     renouvellement écrasait la précédente, et changer le prix d'une offre changeait ce qu'on
 *     croyait avoir facturé.
 *
 * Pas de colonne `statut` : l'état d'un abonnement se LIT sur ses périodes et sur `resilie_le`.
 * L'ancienne en avait une, et rien ne la faisait vieillir — un abonnement « ACTIF » échu depuis
 * trois mois l'était encore.
 *
 * Pas de quotas non plus (membres, comptes) : l'ancienne les vendait et aucun écran du produit ne
 * les appliquait. Promettre une limite qu'on n'applique pas est pire que ne rien promettre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offres', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('nature', 12);                     // LICENCE | ACCES
            $table->string('niveau', 12);                     // VISION | ANTENNE | EXTENSION
            $table->string('palier', 12);                     // STARTER | STANDARD | PREMIUM
            $table->string('nom', 120);
            $table->text('argumentaire')->nullable();
            $table->unsignedSmallInteger('periode_mois');

            // Deux prix, jamais un taux de change : une église qui paie en francs paie le prix en
            // francs fixé ici, pas une conversion du jour (invariant n° 19 du produit).
            $table->unsignedBigInteger('prix_usd_centimes');
            $table->unsignedBigInteger('prix_cdf_centimes');

            // Licence seulement : le prix suit la taille du réseau, sans multiplier les offres.
            $table->json('paliers_taille')->nullable();
            // Licence seulement : jusqu'à quel palier on peut vendre des accès en dessous.
            $table->string('plafond_acces', 12)->nullable();

            // null = TOUS les modules vendables de son espace, ceux d'aujourd'hui et ceux à venir.
            $table->json('modules')->nullable();

            $table->boolean('publique')->default(true);
            $table->unsignedSmallInteger('ordre')->default(50);
            $table->timestamp('retiree_le')->nullable();
            $table->timestamps();

            $table->index(['nature', 'niveau']);
        });

        Schema::create('abonnements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_id')->constrained('installations');
            $table->foreignId('entite_id')->constrained('entites');
            $table->timestamp('resilie_le')->nullable();
            $table->string('motif_resiliation', 255)->nullable();
            $table->timestamps();

            // Une entité, un abonnement : revendre après une résiliation le REPREND, pour que son
            // historique de périodes reste d'un seul tenant.
            $table->unique('entite_id', 'uq_abonnement_entite');
        });

        Schema::create('periodes_abonnement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abonnement_id')->constrained('abonnements');
            $table->foreignId('offre_id')->constrained('offres');
            $table->date('debut');
            $table->date('fin');                              // dernier jour couvert, inclus
            $table->unsignedBigInteger('montant_centimes');
            $table->string('devise', 3);                      // USD | CDF
            // Vrai quand la période a été raccourcie pour finir avec la licence (le prorata).
            $table->boolean('au_prorata')->default(false);
            $table->foreignId('vendue_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['abonnement_id', 'fin'], 'idx_periode_abonnement_fin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodes_abonnement');
        Schema::dropIfExists('abonnements');
        Schema::dropIfExists('offres');
    }
};
