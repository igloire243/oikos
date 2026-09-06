<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FACTURES, PAIEMENTS ET JOURNAL.
 *
 * L'INDEX UNIQUE SUR (fournisseur, reference) N'EST PAS UNE PRÉCAUTION THÉORIQUE
 * -------------------------------------------------------------------------------
 * TOUS les opérateurs mobile money rejouent leur webhook quand ils ne reçoivent pas un « 200 »
 * assez vite — et un hébergement mutualisé est lent. Sans cet index, un paiement de 30 $ en crédite
 * 60, puis 90. C'est la panne la plus courante des intégrations de paiement, et la plus
 * embarrassante à expliquer à un client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factures', function (Blueprint $table) {
            $table->id('facture_id');
            $table->foreignId('client_id')->constrained('clients', 'client_id')->cascadeOnDelete();
            $table->foreignId('abonnement_id')->nullable()->constrained('abonnements', 'abonnement_id')->nullOnDelete();

            $table->string('numero', 30)->unique();           // FACT-2026-0042
            $table->unsignedInteger('montant');               // en centimes ou en CDF entiers
            $table->char('devise', 3);                        // USD | CDF

            // EMISE | PAYEE | ANNULEE | IRRECOUVRABLE
            $table->string('statut', 15)->default('EMISE');
            $table->date('du_le');
            $table->timestamp('payee_le')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();
            $table->index(['statut', 'du_le']);
        });

        Schema::create('paiements', function (Blueprint $table) {
            $table->id('paiement_id');
            $table->foreignId('facture_id')->constrained('factures', 'facture_id')->cascadeOnDelete();

            // MPESA | ORANGE_MONEY | AIRTEL_MONEY | VIREMENT | ESPECES | DEPOT_MARCHAND
            $table->string('fournisseur', 20);
            $table->string('reference', 120)->nullable();     // la référence de la transaction

            $table->unsignedInteger('montant');
            $table->char('devise', 3);

            // EN_ATTENTE | CONFIRME | ECHOUE | REMBOURSE
            $table->string('statut', 12)->default('EN_ATTENTE');
            $table->json('charge_brute')->nullable();         // la réponse telle quelle de l'opérateur

            // Qui a validé, quand la saisie est manuelle — espèces, virement constaté en banque.
            $table->unsignedBigInteger('confirme_par_user_id')->nullable();
            $table->timestamp('confirme_le')->nullable();

            $table->timestamps();

            // Voir l'en-tête : c'est ce qui empêche un webhook rejoué de créditer deux fois.
            $table->unique(['fournisseur', 'reference'], 'uq_paiement_fournisseur_reference');
            $table->index('statut');
        });

        Schema::create('journal', function (Blueprint $table) {
            $table->id('journal_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 60);                     // ABONNEMENT_SUSPENDU, PAIEMENT_CONFIRME…
            $table->string('cible_type', 40)->nullable();
            $table->unsignedBigInteger('cible_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['cible_type', 'cible_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal');
        Schema::dropIfExists('paiements');
        Schema::dropIfExists('factures');
    }
};
