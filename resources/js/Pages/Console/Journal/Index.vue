<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ScrollText } from 'lucide-vue-next';
import LayoutConsole from '@/Layouts/LayoutConsole.vue';
import EnTetePage from '@/Composants/EnTetePage.vue';
import Badge from '@/Composants/Badge.vue';
import Pagination from '@/Composants/Pagination.vue';
import ChampSelect from '@/Composants/ChampSelect.vue';

/**
 * LE JOURNAL — en lecture seule : ni purge, ni correction, ni export. Une carte par décision, la plus
 * récente d'abord, avec QUI et QUAND : c'est ce qu'on ouvre six mois plus tard pour répondre à
 * « qui a fait ça ? ».
 */
const props = defineProps({
    entrees: Object,
    actions: Array,
    filtre: String,
});

const action = ref(props.filtre ?? '');

const filtrer = (valeur) => {
    action.value = valeur;
    router.get(
        route('console.journal.index'),
        { action: valeur || undefined },
        { preserveState: true, replace: true }
    );
};
</script>

<template>
    <LayoutConsole titre="Journal">
        <EnTetePage
            titre="Journal"
            sous-titre="Les décisions prises dans la console — en lecture seule"
            :icone="ScrollText"
        />

        <div class="mx-auto max-w-4xl space-y-4 py-5">
            <ChampSelect
                :model-value="action"
                label="Type de décision"
                :options="actions.map((a) => ({ valeur: a, libelle: a }))"
                vide-libelle="Toutes les décisions"
                @update:model-value="filtrer"
            />

            <div
                v-if="!entrees.data.length"
                class="rounded-2xl bg-white p-10 text-center shadow-card ring-1 ring-slate-100"
            >
                <ScrollText class="mx-auto h-8 w-8 text-slate-300" />
                <p class="mt-3 font-semibold text-slate-700">
                    {{ filtre ? 'Aucune décision de ce type' : 'Rien d\'écrit pour l\'instant' }}
                </p>
                <p class="mt-1 text-sm text-slate-500">
                    Une clé émise, une vente, un encaissement ou un réglage modifié laissent une ligne ici.
                </p>
            </div>

            <ul v-else class="space-y-2">
                <li
                    v-for="e in entrees.data"
                    :key="e.id"
                    class="min-w-0 rounded-2xl bg-white p-4 shadow-card ring-1 ring-slate-100"
                >
                    <p class="text-sm font-medium text-slate-800">{{ e.libelle }}</p>
                    <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                        <Badge ton="ardoise">{{ e.action }}</Badge>
                        <span>{{ e.le }}</span>
                        <span>· {{ e.par }}</span>
                    </p>
                </li>
            </ul>

            <Pagination :paginateur="entrees" libelle="décisions" />
        </div>
    </LayoutConsole>
</template>
