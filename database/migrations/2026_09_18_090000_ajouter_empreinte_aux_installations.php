<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'EMPREINTE DE L'INSTALLATION, SUR L'INSTALLATION ELLE-MÊME.
 *
 * POURQUOI ELLE NE SUFFISAIT PAS LÀ OÙ ELLE ÉTAIT
 * -------------------------------------------------
 * Jusqu'ici l'empreinte n'était gardée que sur la CLÉ D'ACTIVATION qui l'avait transmise — comme
 * une trace d'événement : « c'est cette machine-là qui a consommé cette clé le 12 mars ». Très bien
 * pour enquêter, inutile pour décider : après trois réactivations, il fallait retrouver la dernière
 * clé consommée pour savoir à quelle machine on parle.
 *
 * Or la signature de licence a besoin de cette réponse à chaque échange. Une licence signée doit
 * porter l'empreinte de la machine à laquelle elle est destinée : sans cela, le fichier
 * `licence.json` d'un client bien abonné, copié sur le serveur d'un autre, y serait accepté — une
 * signature valide, mais valide pour quelqu'un d'autre. C'est l'attaque la plus simple qui soit,
 * et elle ne demande aucune compétence : copier un fichier.
 *
 * ON GARDE AUSSI CELLE DE LA CLÉ. Les deux ne disent pas la même chose : celle-ci dit « qui est
 * cette installation aujourd'hui », celle de la clé dit « qui l'a activée ce jour-là ». La seconde
 * reste la trace, la première devient l'identité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            // Nullable, et il faut qu'elle le reste : les installations déjà en service n'en ont
            // pas encore transmis. Elles la donneront à leur prochaine synchronisation, sans qu'on
            // ait à les réactiver — et jusque-là, leur licence part simplement sans liaison.
            $table->string('empreinte', 128)->nullable()->after('url');

            // Indexée : la retrouver par empreinte servira le jour où une installation appellera
            // sans savoir présenter sa clé — après une restauration, par exemple.
            $table->index('empreinte', 'idx_installation_empreinte');
        });
    }

    public function down(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->dropIndex('idx_installation_empreinte');
            $table->dropColumn('empreinte');
        });
    }
};
