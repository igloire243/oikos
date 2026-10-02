<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LES RÉGLAGES NE SONT PLUS QUE DES DURÉES.
 *
 * `reglages.valeur` était un entier : assez pour quatre durées, pas pour dire quelle passerelle de paiement
 * est branchée, ni pour garder les clés du fournisseur. Elle devient un texte ; les durées s'y lisent toujours
 * comme des entiers (`Reglages::valeur()`), et les secrets s'y écrivent CHIFFRÉS (`Reglages::secret()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reglages', function (Blueprint $table) {
            $table->text('valeur')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reglages', function (Blueprint $table) {
            $table->unsignedInteger('valeur')->default(0)->change();
        });
    }
};
