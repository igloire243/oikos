<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LA FIN D'ESSAI DEVIENT UNE DATE, PAS UN CALCUL.
 *
 * Elle était jusqu'ici recalculée à CHAQUE émission de licence — `now()->addDays(essai_jours)` dans
 * EtatLicence::echeance(). Deux conséquences, l'une gênante et l'autre grave :
 *
 *   1. Changer le réglage `essai_jours` en plein essai ne raccourcissait rien : passer de 30 à 5
 *      donnait « aujourd'hui + 5 », donc RALLONGEAIT l'essai d'une installation au 28e jour.
 *   2. Surtout, l'essai N'EXPIRAIT JAMAIS. La synchronisation nocturne repoussait l'échéance à
 *      « maintenant + N » chaque nuit : une installation qui se synchronise reste en essai
 *      perpétuel et utilise tout le produit sans jamais voir la porte se fermer.
 *
 * La date est donc posée UNE FOIS, à l'activation, et relue ensuite. Changer le réglage n'affecte
 * plus que les activations futures — ce qu'on attend d'un réglage commercial.
 *
 * La colonne `abonnements.essai_fin` existait déjà mais n'a jamais été ni écrite ni lue : elle
 * concerne un abonnement, or l'essai a lieu justement quand il n'y en a aucun. L'essai appartient à
 * l'INSTALLATION, c'est elle qui s'active.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->timestamp('essai_fin')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->dropColumn('essai_fin');
        });
    }
};
