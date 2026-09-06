<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CE QUE VOUS VENDEZ, ET À QUI.
 *
 * L'ABONNEMENT PORTE SUR UNE ENTITÉ, PAS SUR UN CLIENT
 * -----------------------------------------------------
 * Une communauté paie pour sa Vision, chaque antenne pour la sienne, chaque église pour la sienne.
 * L'accès d'une entité exige que la SIENNE soit active ET que celles de tous ses ancêtres le
 * soient : une église ne peut pas fonctionner si son antenne est fermée — les programmes, les
 * approbations et les rapports traversent la hiérarchie.
 *
 * PAYEUR ET BÉNÉFICIAIRE SONT DEUX CHOSES DIFFÉRENTES
 * -----------------------------------------------------
 * C'est ce qui permet à une Vision de payer pour ses douze extensions, ou de choisir lesquelles.
 * Cela produit DOUZE abonnements avec le même payeur, et non un seul : chacun garde son échéance,
 * et l'un peut être renouvelé sans les autres. Un abonnement unique « Vision + tout » serait plus
 * simple à facturer et impossible à défaire le jour où une extension quitte la communauté.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id('plan_id');
            $table->string('code', 40)->unique();             // VISION_STANDARD, EGLISE_CELLULE…
            $table->string('niveau', 12);                     // VISION | ANTENNE | EXTENSION
            $table->string('nom', 120);
            $table->text('argumentaire')->nullable();

            // EN CENTIMES, TOUJOURS. Un montant en décimal flottant finit par produire des totaux
            // faux de quelques centimes, et une facture fausse d'un centime est une facture fausse.
            $table->unsignedInteger('prix_usd_cents')->default(0);
            $table->unsignedInteger('prix_cdf')->default(0);
            $table->unsignedSmallInteger('periode_mois')->default(1);

            // Quotas et fonctionnalités vivent en base, jamais dans le code : changer une offre ne
            // doit pas demander un déploiement chez tous les clients.
            $table->json('quotas')->nullable();               // {"membres":500,"comptes":10}
            $table->json('fonctionnalites')->nullable();      // ["messagerie","tresorerie",…]
            $table->json('modes_paiement')->nullable();       // null = tous les modes publics

            $table->boolean('is_public')->default(true);      // une offre négociée reste invisible
            $table->timestamps();

            $table->index(['niveau', 'is_public']);
        });

        Schema::create('abonnements', function (Blueprint $table) {
            $table->id('abonnement_id');
            $table->foreignId('installation_id')->constrained('installations', 'installation_id')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans', 'plan_id');

            // L'entité couverte, désignée par son type et son identifiant DANS l'installation —
            // et non par une clé étrangère vers `entites`. Une resynchronisation peut recréer les
            // lignes d'entités ; l'abonnement, lui, ne doit jamais perdre son bénéficiaire.
            $table->string('beneficiaire_type', 12);
            $table->unsignedBigInteger('beneficiaire_ref');

            $table->string('payeur_type', 12)->nullable();
            $table->unsignedBigInteger('payeur_ref')->nullable();

            // ESSAI | ACTIF | IMPAYE | SUSPENDU | RESILIE | BLOQUE
            $table->string('statut', 12)->default('ESSAI');

            $table->timestamp('essai_fin')->nullable();
            $table->timestamp('periode_debut')->nullable();
            $table->timestamp('periode_fin')->nullable();     // LA date qui fait autorité
            $table->timestamp('grace_fin')->nullable();
            $table->timestamp('resilie_le')->nullable();
            $table->string('motif_resiliation', 255)->nullable();

            $table->timestamps();

            // Une entité n'a qu'un abonnement à la fois : sans cette contrainte, deux ventes
            // successives créeraient deux lignes concurrentes, et rien ne dirait laquelle
            // détermine l'accès.
            $table->unique(['installation_id', 'beneficiaire_type', 'beneficiaire_ref'], 'uq_abonnement_beneficiaire');

            // La requête du cron quotidien : « lesquels arrivent à échéance ? »
            $table->index(['statut', 'periode_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonnements');
        Schema::dropIfExists('plans');
    }
};
