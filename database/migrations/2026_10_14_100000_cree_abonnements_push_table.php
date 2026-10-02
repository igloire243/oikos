<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // UN COMPTE PEUT AVOIR PLUSIEURS ABONNEMENTS — un par appareil (le téléphone à l'accueil,
        // l'ordinateur du bureau). `endpoint` est l'identité de l'appareil pour le navigateur qui
        // l'a ouvert : deux abonnements du même compte n'ont jamais le même endpoint, et le même
        // appareil qui se réabonne (permission redemandée, cache vidé) remplace le sien plutôt que
        // d'en empiler un second qui ne recevrait plus jamais rien.
        Schema::create('abonnements_push', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('cle_p256dh');
            $table->string('cle_auth');
            $table->timestamps();

            // MySQL n'indexe pas un TEXT sans longueur de préfixe : un abonnement se distingue
            // donc par le hachage de son endpoint, jamais par l'endpoint lui-même. C'est ce qui
            // permet de remplacer l'abonnement d'un appareil qui se réabonne au lieu d'en empiler
            // un second, mort, qui ne recevrait plus jamais rien.
            $table->string('endpoint_hache', 64);
            $table->unique('endpoint_hache');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonnements_push');
    }
};
