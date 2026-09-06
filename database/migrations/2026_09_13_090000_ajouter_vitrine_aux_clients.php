<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DE QUOI UN CLIENT PEUT ÊTRE CITÉ PUBLIQUEMENT — et à quelle condition.
 *
 * POURQUOI UNE COLONNE PLUTÔT QUE « on affiche les clients ACTIFS »
 * -----------------------------------------------------------------
 * Publier la table `clients` telle quelle serait une faute sur trois plans à la fois :
 *
 *   · COMMERCIAL — votre liste de clients est ce qu'un concurrent aimerait le plus connaître.
 *     Affichée en entier, elle lui donne exactement qui démarcher, et où.
 *   · JURIDIQUE ET HUMAIN — une église découvrirait son nom sur votre site sans l'avoir accepté.
 *     Certaines communautés ne souhaitent pas qu'on sache quels outils elles emploient.
 *   · FACTUEL — la table contient des PROSPECTS, des clients SUSPENDUS et des RÉSILIÉS. Les
 *     montrer comme références, c'est afficher comme satisfaits des gens qui ne le sont pas.
 *
 * D'où le principe inverse : rien n'est public par défaut. `vitrine` vaut false, et il faut un
 * geste délibéré de votre part — après accord du client — pour le passer à true.
 *
 * `vitrine_accord_le` garde la DATE de cet accord. Ce n'est pas de la décoration : le jour où
 * quelqu'un demande « qui vous a autorisé à me citer ? », une case cochée sans date ne répond rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('vitrine')->default(false)->after('notes');
            $table->timestamp('vitrine_accord_le')->nullable()->after('vitrine');

            // Une phrase du client, affichée telle quelle. Facultative : une référence sans
            // témoignage vaut mieux qu'un témoignage inventé.
            $table->text('temoignage')->nullable()->after('vitrine_accord_le');
            $table->string('temoignage_auteur', 190)->nullable()->after('temoignage');

            $table->string('site_url', 255)->nullable()->after('temoignage_auteur');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'vitrine', 'vitrine_accord_le', 'temoignage', 'temoignage_auteur', 'site_url',
            ]);
        });
    }
};
