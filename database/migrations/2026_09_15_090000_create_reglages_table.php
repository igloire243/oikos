<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LES RÉGLAGES MODIFIABLES SANS TOUCHER AU CODE.
 *
 * POURQUOI SORTIR CES VALEURS DU .env
 * ------------------------------------
 * Le taux du franc, un numéro M-Pesa, le délai de réouverture promis : ce sont des données
 * COMMERCIALES qui bougent, pas des paramètres techniques. Tant qu'elles vivaient dans le .env,
 * les changer demandait d'ouvrir un fichier sur le serveur, de ne pas se tromper de guillemet, et
 * de vider le cache de configuration — trois occasions de casser le site pour corriger un chiffre.
 * Pire : un taux qui traîne fait perdre de l'argent en silence, et rien n'incite à le mettre à jour
 * quand la manœuvre est pénible.
 *
 * CE QUI RESTE DANS LE .env, ET POURQUOI
 * ---------------------------------------
 * Les identifiants de la base, la clé de l'application, les accès SMTP. Ce sont des SECRETS
 * d'infrastructure : ils n'ont rien à faire dans une table qu'un écran d'administration peut
 * afficher, et ils ne changent qu'au déploiement.
 *
 * LE REPLI EST VOLONTAIRE. `Reglage::valeur()` retombe sur config() quand la clé n'existe pas
 * encore en base : une installation neuve, ou une migration jouée avant le seeder, doit continuer
 * d'afficher des tarifs — un site qui montre « 0 FC » parce qu'une ligne manque est pire qu'un
 * site qui montre l'ancienne valeur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglages', function (Blueprint $table) {
            $table->id('reglage_id');

            // La clé est l'identifiant stable : c'est elle que le code interroge. Elle ne se
            // renomme pas — on ajoute, on ne rebaptise pas.
            $table->string('cle', 80)->unique();

            // Tout est stocké en texte et converti à la lecture selon `type`. Une colonne par
            // type serait plus stricte et rendrait l'ajout d'un réglage impossible sans migration.
            $table->text('valeur')->nullable();
            $table->string('type', 12)->default('texte');   // texte | entier | booleen

            $table->string('groupe', 40)->default('general');
            $table->string('libelle', 190);
            $table->string('aide', 255)->nullable();
            $table->unsignedSmallInteger('ordre')->default(50);

            $table->timestamps();

            $table->index(['groupe', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglages');
    }
};
