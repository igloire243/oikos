<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LES CLIENTS, LEURS INSTALLATIONS, ET CE QUE CHAQUE INSTALLATION DIT D'ELLE-MÊME.
 *
 * Un CLIENT est une organisation qui paie ; une INSTALLATION est un serveur où tourne une copie du
 * produit — un client peut en avoir plusieurs (production, démonstration). Les ENTITÉS sont l'arbre
 * que l'installation remonte (Vision, antennes, églises) : c'est à elles qu'on vendra (Lot C2).
 *
 * Aucun statut n'est stocké. Un client est « prospect » tant qu'il n'a rien acheté, une installation
 * « jamais activée » tant qu'elle n'a pas d'empreinte, « muette » quand son dernier contact est trop
 * ancien, « désactivée » quand `desactivee_le` est posée. Un statut écrit à la main vieillit faux
 * dès que personne ne repasse dessus — c'est la maladie que le produit a soignée à dix endroits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 200);
            $table->string('pays', 120)->nullable();
            $table->string('ville', 120)->nullable();
            $table->string('contact_nom', 190)->nullable();
            $table->string('contact_email', 190)->nullable();
            $table->string('contact_telephone', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('nom');
        });

        Schema::create('installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

            // Vide à la création, exprès : la carte de visite de l'installation le remplit à
            // l'activation avec le nom exact de la communauté.
            $table->string('nom', 190)->nullable();
            $table->string('url', 255)->nullable();

            // L'IDENTITÉ STABLE de la machine, déclarée à l'activation. La licence signée la porte,
            // et le produit refuse une licence qui n'est pas la sienne.
            $table->string('empreinte', 128)->nullable()->unique();

            // LA CLÉ DE SYNCHRONISATION, en SHA-256 seulement : la base ne peut pas la rendre. On
            // en fabrique une neuve à chaque activation, ce qui invalide la précédente.
            $table->char('cle_synchro_hash', 64)->nullable()->unique();
            $table->string('cle_synchro_apercu', 12)->nullable();

            // LE JETON DE RAPPEL, confié par l'installation pour qu'on puisse la réveiller. Il ne
            // permet que de lui demander de se resynchroniser ; il est chiffré au repos.
            $table->text('rappel_jeton')->nullable();
            $table->timestamp('rappel_le')->nullable();

            $table->string('version', 20)->nullable();
            // L'empreinte du catalogue de modules que l'installation annonce : un écart avec celle
            // de la console se lit sur sa fiche au lieu de se découvrir chez le client.
            $table->char('catalogue_empreinte', 64)->nullable();
            $table->json('compteurs')->nullable();

            $table->timestamp('activee_le')->nullable();
            $table->timestamp('vue_le')->nullable();
            $table->timestamp('desactivee_le')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'desactivee_le']);
            $table->index('vue_le');
        });

        Schema::create('entites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_id')->constrained('installations')->cascadeOnDelete();

            $table->string('type', 12);                       // VISION | ANTENNE | EXTENSION
            $table->unsignedBigInteger('ref');                // son identifiant DANS l'installation
            $table->string('nom', 200);
            $table->string('sous_type', 12)->nullable();      // SECTEUR | CELLULE — jamais facturé
            $table->unsignedBigInteger('parent_ref')->nullable();
            $table->unsignedInteger('effectif')->nullable();

            $table->timestamp('vue_le')->nullable();
            $table->timestamps();

            $table->unique(['installation_id', 'type', 'ref'], 'uq_entite_installation_ref');
            $table->index(['installation_id', 'parent_ref']);
        });

        Schema::create('cles_activation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_id')->constrained('installations')->cascadeOnDelete();

            // HACHÉE, comme un mot de passe : la clé s'affiche une seule fois, à l'émission.
            $table->char('code_hash', 64)->unique();
            $table->string('code_apercu', 24);

            $table->timestamp('expire_le');
            $table->timestamp('utilisee_le')->nullable();
            $table->timestamp('revoquee_le')->nullable();
            $table->string('empreinte', 128)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('note', 190)->nullable();
            $table->foreignId('emise_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['installation_id', 'utilisee_le']);
        });

        Schema::create('journal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('sujet_type', 60)->nullable();
            $table->unsignedBigInteger('sujet_id')->nullable();
            // LE LIBELLÉ, pas seulement l'identifiant : « installation 7 » ne dit rien le jour où
            // l'installation 7 n'existe plus.
            $table->string('libelle', 255);
            $table->json('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sujet_type', 'sujet_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal');
        Schema::dropIfExists('cles_activation');
        Schema::dropIfExists('entites');
        Schema::dropIfExists('installations');
        Schema::dropIfExists('clients');
    }
};
