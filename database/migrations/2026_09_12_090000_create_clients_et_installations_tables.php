<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QUI SONT VOS CLIENTS, ET OÙ TOURNE LEUR LOGICIEL.
 *
 * Un CLIENT est une communauté à qui vous vendez. Une INSTALLATION est une copie du logiciel qui
 * tourne quelque part avec sa propre base. Les deux sont distincts : un client peut avoir une
 * installation de démonstration en plus de la sienne, ou changer d'hébergement sans cesser d'être
 * client.
 *
 * LES ENTITÉS SONT UNE COPIE, PAS UNE SOURCE
 * -------------------------------------------
 * `entites` recopie l'arbre Vision / Antennes / Extensions que chaque installation envoie chaque
 * nuit. La vérité reste chez le client : ici, c'est ce qui permet de VENDRE un abonnement à
 * « l'antenne de Lubumbashi » — sans cette copie, vous ignorez qu'elle existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id('client_id');
            $table->string('nom', 200);
            $table->string('pays', 120)->nullable();
            $table->string('ville', 120)->nullable();

            $table->string('contact_nom', 190)->nullable();
            $table->string('contact_email', 190)->nullable();
            $table->string('contact_telephone', 40)->nullable();

            // PROSPECT : en discussion, aucune installation encore.
            // ACTIF : au moins un abonnement en cours. SUSPENDU : impayé. RESILIE : parti.
            $table->string('statut', 20)->default('PROSPECT');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->index('statut');
        });

        Schema::create('installations', function (Blueprint $table) {
            $table->id('installation_id');
            $table->foreignId('client_id')->constrained('clients', 'client_id')->cascadeOnDelete();

            $table->string('nom', 190);                       // « Production », « Démonstration »
            $table->string('url', 255)->nullable();

            // LA CLÉ N'EST PAS STOCKÉE EN CLAIR. Elle sert de mot de passe à la synchronisation :
            // une base dérobée donnerait sinon accès à toutes les installations d'un coup. On garde
            // son empreinte pour la retrouver, et ses huit premiers caractères pour l'afficher —
            // assez pour reconnaître laquelle est laquelle, pas assez pour s'en servir.
            $table->char('cle_hash', 64)->unique();
            $table->string('cle_apercu', 12);

            $table->string('version', 20)->nullable();        // remontée à chaque synchronisation
            $table->timestamp('vue_le')->nullable();          // dernier contact
            $table->json('compteurs')->nullable();            // membres, comptes, extensions

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['client_id', 'active']);
            // La requête de surveillance : « qui ne s'est pas manifesté depuis trois jours ? »
            $table->index('vue_le');
        });

        Schema::create('entites', function (Blueprint $table) {
            $table->id('entite_id');
            $table->foreignId('installation_id')->constrained('installations', 'installation_id')->cascadeOnDelete();

            $table->string('type', 12);                       // VISION | ANTENNE | EXTENSION
            $table->unsignedBigInteger('ref');                // son identifiant DANS l'installation
            $table->string('nom', 200);
            $table->string('sous_type', 12)->nullable();      // SECTEUR | CELLULE
            $table->unsignedBigInteger('parent_ref')->nullable();

            $table->timestamp('vue_le')->nullable();
            $table->timestamps();

            // Une entité est identifiée par son installation ET son identifiant local : deux
            // clients peuvent tous deux avoir une extension n° 12.
            $table->unique(['installation_id', 'type', 'ref'], 'uq_entite_installation_ref');
            $table->index(['installation_id', 'parent_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entites');
        Schema::dropIfExists('installations');
        Schema::dropIfExists('clients');
    }
};
