<script setup>
import { computed } from 'vue';
import { ShoppingCart } from 'lucide-vue-next';
import Badge from '@/Composants/Badge.vue';

/**
 * UNE ENTITÉ DE L'ARBRE, ET CE QU'ELLE A ACHETÉ.
 *
 * Une seule ligne pour la Vision, les antennes et les églises : vendre se fait au même endroit,
 * au même geste, quel que soit l'étage — seule l'offre proposée change, et c'est le serveur qui la
 * filtre.
 */
const props = defineProps({
    entite: { type: Object, required: true },
    enfant: { type: Boolean, default: false },
});

defineEmits(['vendre', 'historique']);

const TONS = {
    EN_COURS: 'succes',
    A_VENIR: 'info',
    EN_GRACE: 'alerte',
    ECHU: 'danger',
    RESILIE: 'ardoise',
};

const abonnement = computed(() => props.entite.abonnement);
</script>

<template>
    <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-sm">
        <Badge v-if="!enfant" :ton="entite.type === 'VISION' ? 'marque' : 'info'">{{
            entite.libelle_type
        }}</Badge>
        <span
            class="min-w-0 truncate"
            :class="enfant ? 'text-slate-700' : 'font-medium text-slate-800'"
            >{{ entite.nom }}</span
        >
        <span v-if="enfant" class="shrink-0 text-xs text-slate-400"
            >{{ entite.libelle_type
            }}<template v-if="entite.effectif !== null">
                · {{ entite.effectif }} membres</template
            ></span
        >

        <span class="ml-auto flex shrink-0 items-center gap-2">
            <button
                v-if="abonnement"
                type="button"
                class="flex items-center gap-1.5"
                title="L'historique des périodes vendues"
                @click="$emit('historique', entite)"
            >
                <Badge :ton="TONS[abonnement.etat]">{{ abonnement.libelle_etat }}</Badge>
                <span class="hidden text-xs text-slate-500 sm:inline">
                    {{ abonnement.offre
                    }}<template v-if="abonnement.fin"> · {{ abonnement.fin }}</template>
                </span>
            </button>
            <button
                v-if="entite.id"
                type="button"
                class="flex min-h-9 items-center gap-1 rounded-lg px-2 text-xs font-semibold text-[color:var(--marque-700)] hover:bg-[color:var(--marque-50)]"
                @click="$emit('vendre', entite)"
            >
                <ShoppingCart class="h-3.5 w-3.5" /> Vendre
            </button>
        </span>
    </div>
</template>
