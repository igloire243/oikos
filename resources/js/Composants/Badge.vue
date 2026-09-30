<script setup>
import { computed } from 'vue';

/**
 * UNE PASTILLE D'ÉTAT.
 *
 * Les tons sémantiques ne suivent JAMAIS la couleur de l'espace : un succès est vert dans les
 * cinq espaces. Seul le ton `marque` se teinte, et il sert à ce qui est neutre mais propre à
 * l'espace courant — un type d'entité, par exemple.
 */
const props = defineProps({
    ton: { type: String, default: 'ardoise' },
});

const classes = computed(
    () =>
        ({
            marque: 'bg-[color:var(--marque-50)] text-[color:var(--marque-700)] ring-[color:var(--marque-200)]',
            ardoise: 'bg-slate-50 text-slate-600 ring-slate-200',
            succes: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            alerte: 'bg-amber-50 text-amber-700 ring-amber-200',
            danger: 'bg-rose-50 text-rose-700 ring-rose-200',
            info: 'bg-blue-50 text-blue-700 ring-blue-200',
        })[props.ton] ?? 'bg-slate-50 text-slate-600 ring-slate-200'
);
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
        :class="classes"
    >
        <slot />
    </span>
</template>
