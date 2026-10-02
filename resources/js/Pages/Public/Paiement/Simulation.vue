<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthDivise from '@/Layouts/AuthDivise.vue';
import Bouton from '@/Composants/Bouton.vue';

/** LA FAUSSE PAGE DU FOURNISSEUR — pour voir le parcours ; refusée en production. */
const props = defineProps({
    reference: { type: String, required: true },
    montant: { type: String, required: true },
    numero: { type: String, required: true },
});

const form = useForm({ paye: true });
const decider = (paye) => {
    form.paye = paye;
    form.post(route('paiement.simuler', props.reference));
};
</script>

<template>
    <AuthDivise badge="Simulation" titre="Prestataire simulé." description="Cette page remplace le vrai prestataire pour les essais.">
        <h2 class="text-xl font-black tracking-tight text-gray-900">Payer {{ montant }}</h2>
        <p class="mt-1 text-sm text-gray-500">Facture {{ numero }} · {{ reference }}</p>
        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <Bouton :desactive="form.processing" @click="decider(true)">Simuler un paiement réussi</Bouton>
            <Bouton variante="contour" :desactive="form.processing" @click="decider(false)">Simuler un échec</Bouton>
        </div>
    </AuthDivise>
</template>
