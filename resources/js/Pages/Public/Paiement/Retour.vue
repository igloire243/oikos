<script setup>
import AuthDivise from '@/Layouts/AuthDivise.vue';
import Bouton from '@/Composants/Bouton.vue';

/**
 * LE RETOUR — il affiche ce que le serveur a vérifié auprès du fournisseur, jamais ce que l'adresse
 * prétend. « En attente » est une réponse honnête : le fournisseur n'a pas encore confirmé.
 */
defineProps({
    statut: { type: String, required: true },
    numero: { type: String, required: true },
    montant: { type: String, required: true },
    motif: { type: String, default: null },
    jeton: { type: String, required: true },
});
</script>

<template>
    <AuthDivise badge="Oikos" titre="Régler votre abonnement." description="Merci de votre confiance.">
        <template v-if="statut === 'CONFIRMEE'">
            <h2 class="text-xl font-black tracking-tight text-gray-900">Paiement reçu</h2>
            <p class="mt-2 text-sm text-gray-600">
                {{ montant }} reçus sur la facture {{ numero }}. Votre accès est mis à jour.
            </p>
        </template>
        <template v-else-if="statut === 'ECHOUEE'">
            <h2 class="text-xl font-black tracking-tight text-gray-900">Paiement non abouti</h2>
            <p class="mt-2 text-sm text-gray-600">{{ motif }}</p>
            <Bouton class="mt-5" :href="route('paiement.afficher', jeton)">Réessayer</Bouton>
        </template>
        <template v-else>
            <h2 class="text-xl font-black tracking-tight text-gray-900">En attente de confirmation</h2>
            <p class="mt-2 text-sm text-gray-600">
                Le prestataire n'a pas encore confirmé le paiement de {{ montant }} ({{ numero }}). Rechargez
                cette page dans un instant.
            </p>
        </template>
    </AuthDivise>
</template>
