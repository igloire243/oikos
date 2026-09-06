<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotion « mois des fêtes » : chaque décembre, tout ACCÈS actif gagne un mois de période en
 * plus, gratuitement (voir la commande abonnement:offrir-decembre). Cette colonne retient l'année
 * où le cadeau a été appliqué, pour ne jamais le donner deux fois le même décembre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abonnements', function (Blueprint $table) {
            $table->unsignedSmallInteger('promo_decembre_annee')->nullable()->after('grace_fin');
        });
    }

    public function down(): void
    {
        Schema::table('abonnements', function (Blueprint $table) {
            $table->dropColumn('promo_decembre_annee');
        });
    }
};
