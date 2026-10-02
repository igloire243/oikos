<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * PAYER EN LIGNE — deux ajouts, et aucun ne touche à `paiements`.
 *
 *   · `factures.jeton_paiement` — l'adresse publique d'une facture. Ce n'est PAS son numéro : « FAC-2026-00012 »
 *     se devine, et ouvrirait la facture d'un autre client. Le jeton est tiré au hasard, et sert à une seule chose.
 *   · `demandes_paiement` — une TENTATIVE. Elle est écrite avant de partir chez le fournisseur et ne devient un
 *     paiement que lorsque le fournisseur CONFIRME. Une tentative abandonnée reste là, et ne coûte rien à personne.
 *
 * `reference` (la nôtre, envoyée au fournisseur) est unique, et `reference_externe` (la sienne) aussi : c'est ce
 * qui fait qu'une confirmation rejouée — le fournisseur renvoie sa notification, le client recharge la page de
 * retour — n'encaisse qu'une fois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->string('jeton_paiement', 40)->nullable()->unique()->after('numero');
        });

        DB::table('factures')->whereNull('jeton_paiement')->orderBy('id')->each(function ($f) {
            DB::table('factures')->where('id', $f->id)->update(['jeton_paiement' => Str::random(32)]);
        });

        Schema::create('demandes_paiement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_id')->constrained('factures')->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->unsignedBigInteger('montant_centimes');
            $table->string('devise', 3);
            $table->string('passerelle', 30);
            $table->string('statut', 12)->default('EN_ATTENTE');   // EN_ATTENTE | CONFIRMEE | ECHOUEE
            $table->string('reference_externe', 120)->nullable()->unique();
            $table->foreignId('paiement_id')->nullable()->constrained('paiements')->nullOnDelete();
            $table->string('motif', 255)->nullable();
            $table->timestamp('confirmee_le')->nullable();
            $table->timestamps();

            $table->index(['facture_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_paiement');
        Schema::table('factures', fn (Blueprint $table) => $table->dropColumn('jeton_paiement'));
    }
};
