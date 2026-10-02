<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthDivise from '@/Layouts/AuthDivise.vue';
import Bouton from '@/Composants/Bouton.vue';

/**
 * LA PAGE DE PAIEMENT — publique : celui qui paie n'a pas forcément de compte. Elle ne montre que ce
 * qu'il faut pour payer, et le bouton envoie chez le fournisseur pour TOUT ce qui reste dû.
 */
const props = defineProps({
    facture: { type: Object, required: true },
    jeton: { type: String, required: true },
});

const form = useForm({});
const payer = () => form.post(route('paiement.demarrer', props.jeton));
</script>

<template>
    <AuthDivise
        badge="Oikos"
        titre="Régler votre abonnement."
        description="Le paiement se fait chez notre prestataire ; votre accès est mis à jour dès qu'il le confirme."
    >
        <h2 class="text-xl font-black tracking-tight text-gray-900">Facture {{ facture.numero }}</h2>
        <p class="mt-1 text-sm text-gray-500">{{ facture.offre }} · {{ facture.entite }}</p>

        <dl class="mt-5 space-y-2 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm">
            <div class="flex justify-between gap-3">
                <dt class="text-gray-500">Montant de la facture</dt>
                <dd class="font-semibold text-gray-900">{{ facture.montant }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-gray-500">Reste à payer</dt>
                <dd class="font-black text-gray-900">{{ facture.restant }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-gray-500">Échéance</dt>
                <dd class="text-gray-900">{{ facture.echeance }}</dd>
            </div>
        </dl>

        <p
            v-if="facture.solde"
            class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
        >
            Cette facture est soldée. Merci !
        </p>
        <Bouton v-else class="mt-5 w-full" :desactive="form.processing" @click="payer">
            Payer {{ facture.restant }}
        </Bouton>
    </AuthDivise>
</template>
