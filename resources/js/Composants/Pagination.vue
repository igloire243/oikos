<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

/**
 * LA PAGINATION.
 *
 * Elle affiche TOUJOURS le décompte, même sur une seule page : « 124 membres » répond à une
 * question que l'utilisateur se pose vraiment, alors que des flèches grisées ne répondent à rien.
 */
const props = defineProps({
    // Le paginateur de Laravel, tel quel.
    paginateur: { type: Object, required: true },
    /** Le libellé au PLURIEL — le singulier en est déduit. */
    libelle: { type: String, default: 'résultats' },
    /** À fournir seulement quand le singulier ne s'obtient pas en retirant le « s ». */
    libelleSingulier: { type: String, default: null },
    /** Quand le tableau affiche déjà le décompte en haut, à côté de ses filtres. */
    sansDecompte: { type: Boolean, default: false },
});

const liens = computed(() => props.paginateur.links ?? []);

// Sur téléphone, deux gros boutons « précédent / suivant » : une rangée de numéros de 30 px ne se
// vise pas au doigt, et on y tourne les pages une à une de toute façon.
const precedent = computed(() => props.paginateur.prev_page_url ?? null);
const suivant = computed(() => props.paginateur.next_page_url ?? null);

/** Laravel renvoie les fleches en entites HTML ; on les remplace par des chevrons lisibles. */
const etiquette = (lien) => lien.label.replace('&laquo;', '‹').replace('&raquo;', '›');
const plusieursPages = computed(() => (props.paginateur.last_page ?? 1) > 1);

// « 1 requêtes » se remarque tout de suite et fait négligé. Le pluriel français se retire en
// enlevant le « s » final dans l'immense majorité des cas ; les exceptions passent le singulier.
const libelleAccorde = computed(() =>
    props.paginateur.total === 1
        ? (props.libelleSingulier ?? props.libelle.replace(/s$/, ''))
        : props.libelle
);
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p v-if="!sansDecompte || plusieursPages" class="text-xs text-slate-500">
            <template v-if="!sansDecompte">
                <span class="font-semibold text-slate-700">{{ paginateur.total }}</span>
                {{ libelleAccorde }}
                <span v-if="plusieursPages"> · </span>
            </template>
            <span v-if="plusieursPages">
                page {{ paginateur.current_page }} sur {{ paginateur.last_page }}
            </span>
        </p>

        <div v-if="plusieursPages" class="flex w-full items-center gap-2 sm:hidden">
            <Link
                v-if="precedent"
                :href="precedent"
                preserve-scroll
                preserve-state
                class="flex min-h-11 flex-1 items-center justify-center rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-700"
                >‹ Précédent</Link
            >
            <Link
                v-if="suivant"
                :href="suivant"
                preserve-scroll
                preserve-state
                class="flex min-h-11 flex-1 items-center justify-center rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-700"
                >Suivant ›</Link
            >
        </div>

        <div v-if="plusieursPages" class="hidden flex-wrap items-center gap-1 sm:flex">
            <template v-for="(lien, index) in liens" :key="index">
                <span v-if="!lien.url" class="px-2.5 py-1.5 text-sm text-slate-300">{{
                    etiquette(lien)
                }}</span>
                <Link
                    v-else
                    :href="lien.url"
                    preserve-scroll
                    preserve-state
                    class="rounded-lg px-2.5 py-1.5 text-sm transition"
                    :class="
                        lien.active
                            ? 'font-semibold text-[color:var(--marque-700)]'
                            : 'text-slate-600 hover:bg-slate-100'
                    "
                    :style="lien.active ? { backgroundColor: 'var(--marque-50)' } : null"
                    >{{ etiquette(lien) }}</Link
                >
            </template>
        </div>
    </div>
</template>
