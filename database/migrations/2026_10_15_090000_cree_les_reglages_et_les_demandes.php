<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Les quatre durées qui commandent la licence servie aux installations. Une ligne par
        // réglage et non un JSON : « depuis quand la grâce vaut-elle 21 jours ? » se lit au journal.
        // Absente, une clé retombe sur `config/oikos.php` — jamais un réglage vide qui fermerait
        // un parc entier.
        Schema::create('reglages', function (Blueprint $table) {
            $table->string('cle', 60)->primary();
            $table->unsignedInteger('valeur');
            $table->timestamp('modifie_le')->nullable();
        });

        // Ce que laisse un visiteur du site commercial. Tant que personne ne la traite, la demande
        // reste en tête de liste : une demande oubliée est un client perdu en silence.
        Schema::create('demandes_contact', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);
            $table->string('organisation', 190)->nullable();
            $table->string('email', 190);
            $table->string('telephone', 40)->nullable();
            $table->string('pays', 100)->nullable();
            $table->text('message');
            $table->timestamp('traitee_le')->nullable();
            $table->foreignId('traitee_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['traitee_le', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_contact');
        Schema::dropIfExists('reglages');
    }
};
