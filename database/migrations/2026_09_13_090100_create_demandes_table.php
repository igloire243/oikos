<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LES DEMANDES VENUES DU SITE PUBLIC — la seule écriture qu'un inconnu puisse provoquer.
 *
 * POURQUOI ENREGISTRER PLUTÔT QU'ENVOYER UN E-MAIL
 * -------------------------------------------------
 * Un formulaire qui se contente d'envoyer un mail perd tout ce qu'il reçoit : si le SMTP tombe, si
 * le message part en indésirable, si vous le supprimez par mégarde, la demande n'a jamais existé.
 * En base, elle reste, elle se retrouve, et vous voyez d'un coup d'œil ce qui n'a pas encore été
 * traité. L'e-mail devient une simple notification par-dessus — agréable, mais pas la source.
 *
 * CE QUE CETTE TABLE NE DOIT JAMAIS DEVENIR
 * ------------------------------------------
 * Un dépotoir de robots. `ip` et `cree_le` sont là pour ça : ils permettent de repérer et de
 * plafonner. `statut` a une valeur SPAM plutôt qu'une suppression — on marque, on ne détruit pas,
 * parce qu'un vrai client mal classé doit pouvoir être retrouvé.
 *
 * ET CE QU'ELLE NE CONTIENT PAS : aucun mot de passe, aucun montant, aucune clé. Cette table est
 * la seule que du texte venu de l'extérieur remplit ; elle est donc tenue à l'écart de tout ce qui
 * a de la valeur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes', function (Blueprint $table) {
            $table->id('demande_id');

            $table->string('nom', 190);
            $table->string('organisation', 190)->nullable();
            $table->string('email', 190);
            $table->string('telephone', 40)->nullable();
            $table->string('ville', 120)->nullable();

            // Le niveau qui l'intéresse : vision, antenne ou église seule. C'est ce qui décide de
            // l'offre dont on lui parlera, donc autant le demander tout de suite.
            $table->string('niveau', 30)->nullable();

            $table->text('message');

            $table->string('statut', 20)->default('NOUVELLE');   // NOUVELLE · LUE · TRAITEE · SPAM
            $table->text('note_interne')->nullable();

            // Trace technique — pour plafonner les envois répétés et reconnaître un robot.
            $table->string('ip', 45)->nullable();
            $table->string('agent', 255)->nullable();

            $table->timestamp('cree_le')->useCurrent();
            $table->timestamp('traite_le')->nullable();

            // La boîte de réception se lit « les non traitées, les plus récentes d'abord » :
            // c'est exactement cet index.
            $table->index(['statut', 'cree_le'], 'idx_demande_statut_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes');
    }
};
