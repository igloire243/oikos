<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LES CLÉS D'ACTIVATION — courtes, à usage unique, dictables au téléphone.
 *
 * À QUOI ELLES SERVENT, ET À QUOI ELLES NE SERVENT PAS
 * -----------------------------------------------------
 * Une clé d'activation N'EST PAS la clé de synchronisation. Elle sert UNE fois, pour présenter une
 * installation à la console : « voici qui je suis, voici mon empreinte ». En échange, la console
 * lui remet sa vraie clé de synchronisation — longue, secrète, renouvelée à cet instant — et l'état
 * de son abonnement.
 *
 * Séparer les deux permet d'avoir une clé courte sans affaiblir quoi que ce soit : elle ne vaut
 * qu'une fois, quelques minutes, et ce qu'elle ouvre est immédiatement remplacé.
 *
 * POURQUOI ELLE EST STOCKÉE HACHÉE
 * ----------------------------------
 * Comme la clé d'installation : une base dérobée ne doit pas livrer de quoi activer des copies.
 * Conséquence assumée — une clé perdue ne se retrouve pas, elle se réémet.
 *
 * L'ALPHABET ÉVITE LES CARACTÈRES AMBIGUS. Pas de O ni de 0, pas de I ni de 1 ni de L : cette clé
 * sera dictée au téléphone à quelqu'un qui la tape sur un clavier de téléphone, et « OIKOS-I1LO »
 * est une erreur de saisie qui attend de se produire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cles_activation', function (Blueprint $table) {
            $table->id('cle_activation_id');

            $table->foreignId('installation_id')
                ->constrained('installations', 'installation_id')->cascadeOnDelete();

            $table->char('code_hash', 64)->unique();
            $table->string('code_apercu', 24);          // « OIKOS-7K4P-… », pour la retrouver

            $table->string('statut', 12)->default('EMISE');   // EMISE | UTILISEE | REVOQUEE

            // Une clé sans date de fin traîne indéfiniment dans une conversation WhatsApp. Trente
            // jours par défaut : assez pour installer sans se presser, trop peu pour resservir
            // un an plus tard à quelqu'un d'autre.
            $table->timestamp('expire_le')->nullable();

            $table->timestamp('utilisee_le')->nullable();

            // L'empreinte de l'installation qui l'a consommée. C'est la trace qui permet de dire
            // « cette clé a servi là », et de reconnaître une deuxième tentative depuis ailleurs.
            $table->string('empreinte', 128)->nullable();
            $table->string('ip', 45)->nullable();

            $table->string('note', 190)->nullable();

            $table->timestamps();

            $table->index(['installation_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cles_activation');
    }
};
