<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FAIRE ÉVOLUER UNE OFFRE EN COURS, ET REMETTRE UNE INSTALLATION À L'ESSAI.
 *
 * Une période vendue ne se modifiait jamais : une formule plus haute vendue en renouvellement
 * attendait donc l'échéance de la précédente. Appliquer « maintenant » la fait commencer
 * aujourd'hui et raccourcit celle qu'elle remplace — les deux seuls gestes qui réécrivent une
 * date. `debut_vendu` et `fin_vendue` gardent ce qui avait été VENDU : la facture et l'historique
 * continuent de répondre à « qu'avait-il acheté, pour quelles dates ? ».
 *
 * `essai_relance_le` : un essai a une fin fixe comptée depuis la première activation (EtatLicence),
 * et une licence vendue puis échue ne le rouvre pas. La remise à l'essai est donc une DÉCISION
 * datée, pas un recul de `activee_le` qui effacerait l'ancienneté de l'installation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodes_abonnement', function (Blueprint $table) {
            $table->date('debut_vendu')->nullable()->after('fin');
            $table->date('fin_vendue')->nullable()->after('debut_vendu');
        });

        Schema::table('installations', function (Blueprint $table) {
            $table->timestamp('essai_relance_le')->nullable()->after('activee_le');
        });
    }

    public function down(): void
    {
        Schema::table('periodes_abonnement', fn (Blueprint $table) => $table->dropColumn(['debut_vendu', 'fin_vendue']));
        Schema::table('installations', fn (Blueprint $table) => $table->dropColumn('essai_relance_le'));
    }
};
