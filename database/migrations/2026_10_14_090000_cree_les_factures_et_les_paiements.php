<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LOT C3 — ENCAISSER : ce qui est DÛ, et ce qui est REÇU.
 *
 *   · `factures` — UNE par période vendue, au montant et à la devise figés le jour de la vente. Une
 *     facture ne dit jamais autre chose que la période qu'elle facture : pas de colonne « statut »,
 *     son état se LIT sur les paiements (l'ancienne console en tenait une, qui ne vieillissait pas).
 *   · `paiements` — les versements, PARTIELS autant que nécessaire. Un versement annoncé mais jamais
 *     arrivé se marque « non reçu » (`non_recu_le`) : il ne s'efface pas, parce que « qui a dit avoir
 *     payé en mars ? » doit garder une réponse.
 *
 * `reference` est UNIQUE en base, pas seulement en validation : une même référence d'opérateur
 * saisie deux fois — par deux opérateurs, ou par un double clic — encaisserait deux fois le même
 * argent. MySQL accepte plusieurs NULL : un paiement en espèces n'a pas de référence.
 *
 * Les périodes déjà vendues avant ce lot reçoivent leur facture, laissée EN ATTENTE : on ne devine
 * pas qu'elles ont été payées, l'opérateur enregistre ce qu'il a réellement reçu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('periode_abonnement_id')->unique()->constrained('periodes_abonnement')->restrictOnDelete();
            $table->unsignedBigInteger('montant_centimes');
            $table->string('devise', 3);
            $table->date('emise_le');
            $table->date('echeance_le');
            $table->foreignId('emise_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('echeance_le');
        });

        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_id')->constrained('factures')->restrictOnDelete();
            $table->unsignedBigInteger('montant_centimes');
            $table->string('moyen', 20);                       // ESPECES | MOBILE_MONEY | VIREMENT | CHEQUE | AUTRE
            $table->string('reference', 80)->nullable()->unique();
            $table->date('recu_le');
            $table->text('notes')->nullable();
            $table->timestamp('non_recu_le')->nullable();
            $table->string('motif_non_recu', 255)->nullable();
            $table->foreignId('saisi_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['facture_id', 'non_recu_le']);
        });

        $this->factureLesPeriodesExistantes();
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
        Schema::dropIfExists('factures');
    }

    private function factureLesPeriodesExistantes(): void
    {
        $suites = [];

        DB::table('periodes_abonnement')->orderBy('id')->get()->each(function ($periode) use (&$suites) {
            $emise = Carbon::parse($periode->created_at ?? $periode->debut);
            $annee = $emise->year;
            $suites[$annee] = ($suites[$annee] ?? 0) + 1;

            DB::table('factures')->insert([
                'numero' => sprintf('FAC-%d-%05d', $annee, $suites[$annee]),
                'periode_abonnement_id' => $periode->id,
                'montant_centimes' => $periode->montant_centimes,
                'devise' => $periode->devise,
                'emise_le' => $emise->toDateString(),
                'echeance_le' => $emise->copy()->addDays(15)->toDateString(),
                'emise_par_id' => $periode->vendue_par_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
};
