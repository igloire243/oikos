<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE SECRET DU CANAL ENTRANT — celui qui permet à la console de dire à une installation
 * « remets-toi à jour tout de suite », sans attendre la synchronisation de la nuit.
 *
 * POURQUOI IL EST STOCKÉ EN CLAIR, CONTRAIREMENT À TOUS LES AUTRES
 * -----------------------------------------------------------------
 * Parce qu'il faut le PRÉSENTER. Une empreinte suffit pour vérifier un secret qu'on reçoit ; elle
 * ne sert à rien pour un secret qu'on doit envoyer. C'est le seul de cette base dans ce cas, et
 * c'est assumé — mais il fallait mesurer ce qu'il ouvre.
 *
 * CE QU'IL OUVRE, EXACTEMENT : le droit de faire relire à une installation son propre état, chez
 * sa propre console. Il ne transporte aucune donnée, n'en modifie aucune, et ne permet pas de
 * dicter un abonnement — la route côté produit refuse tout paramètre. Une base dérobée livrerait
 * donc le pouvoir de faire travailler des serveurs pour rien, ce que leur plafond de requêtes
 * borne déjà.
 *
 * Si un jour cette route devait accepter davantage, il faudrait revenir ici : le raisonnement
 * ci-dessus ne tiendrait plus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->string('rappel_jeton', 128)->nullable()->after('cle_apercu');

            // Quand l'appel a-t-il abouti pour la dernière fois. Sans cette date, une adresse
            // devenue injoignable — le client a changé d'hébergeur — ne se signalerait jamais :
            // les paiements sembleraient rouvrir l'accès alors qu'ils ne rouvrent rien.
            $table->timestamp('rappel_le')->nullable()->after('rappel_jeton');
        });
    }

    public function down(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->dropColumn(['rappel_jeton', 'rappel_le']);
        });
    }
};
