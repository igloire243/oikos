<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE PRIX D'UNE LICENCE DEVIENT UNE FORMULE : un socle, plus un montant par entité.
 *
 * POURQUOI ON ABANDONNE LES TRANCHES
 * ------------------------------------
 * `paliers_taille` fixait un prix forfaitaire par tranche de taille. Conséquence arithmétique, que
 * personne n'avait posée : le prix par entité variait de 320 $ (un réseau d'une entité) à 5,30 $
 * (deux cents entités), et FRANCHIR UNE BORNE COÛTAIT PLUS CHER QUE LA TRANCHE ELLE-MÊME — passer
 * de 10 à 11 entités ajoutait 180 $ pour une seule église, de 90 à 91 en ajoutait 340. Un réseau de
 * 11 entités payait 45 $ par entité quand un réseau de 50 en payait 10, pour la même offre.
 *
 * À la question « une entité vaut combien ? », la grille par tranches n'avait aucune réponse. La
 * formule en a une, en une phrase : « 250 $ par an, plus 4 $ par entité ».
 *
 * LE SOCLE EST LE PRIX DÉJÀ EN PLACE. On n'ajoute pas de colonne pour lui : `prix_usd_cents` /
 * `prix_cdf` jouent ce rôle, et gardent leur sens pour toutes les autres offres (un accès n'a pas
 * de socle, il a un prix). Seules les deux colonnes du montant PAR ENTITÉ sont nouvelles.
 *
 * `paliers_taille` N'EST PAS SUPPRIMÉE : une offre déjà vendue doit continuer à se facturer comme
 * elle a été signée. `Plan::prixPourTaille()` regarde d'abord la formule, et retombe sur les
 * tranches si l'offre n'en a pas — les deux cohabitent sans qu'on ait à migrer les abonnements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Nullable, et c'est ce qui distingue les deux modes : une offre sans montant par
            // entité se facture aux tranches (ou à prix fixe). Pas de colonne « mode » à tenir
            // cohérente avec les montants — un champ qui peut mentir sur le contenu des autres.
            $table->unsignedInteger('prix_par_entite_usd_cents')->nullable()->after('prix_cdf');
            $table->unsignedInteger('prix_par_entite_cdf')->nullable()->after('prix_par_entite_usd_cents');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['prix_par_entite_usd_cents', 'prix_par_entite_cdf']);
        });
    }
};
