<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ENCAISSEMENT EN LIGNE (FlexPay) — colonnes ajoutées à `paiements`.
 *
 * Un versement mobile money passe par un état de plus que la saisie manuelle : la demande est
 * ENVOYÉE au téléphone du client (on a un `order_number` FlexPay, mais pas encore de `reference`
 * d'opérateur), puis CONFIRMÉE ou ÉCHOUÉE via le webhook.
 *
 * `reference` reste nullable : tant que le client n'a pas validé, il n'y a pas de référence de
 * transaction. L'index unique (fournisseur, reference) existant tolère plusieurs NULL — on peut
 * donc avoir deux demandes en attente sur une même facture — mais dès qu'une référence réelle est
 * posée, elle ne peut plus l'être deux fois : c'est ce qui rend le webhook rejouable sans risque.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            // L'identifiant de commande rendu par FlexPay au démarrage. Sert de clé pour retrouver
            // le paiement local à la réception du webhook.
            $table->string('order_number', 80)->nullable()->unique('uq_paiement_order_number')->after('reference');

            // Le numéro sur lequel la demande a été poussée, au format international.
            $table->string('telephone', 20)->nullable()->after('order_number');

            // Quand la demande « Payer maintenant » a été émise. Passé le délai d'expiration
            // (config flexpay), l'opérateur peut en relancer une.
            $table->timestamp('initie_le')->nullable()->after('telephone');
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropUnique('uq_paiement_order_number');
            $table->dropColumn(['order_number', 'telephone', 'initie_le']);
        });
    }
};
