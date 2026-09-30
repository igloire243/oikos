<script setup>
import { computed } from 'vue';
import { Users } from 'lucide-vue-next';

/**
 * UN VISAGE — la photo de la fiche, ou les initiales à défaut.
 *
 * Écrit une fois pour `LienMembre`, la messagerie et l'annuaire : trois pastilles recopiées
 * finissent par avoir trois tailles et trois façons de calculer des initiales. Un groupe de
 * messagerie n'a pas de visage, il a une icône.
 */
const props = defineProps({
    nom: { type: String, default: '' },
    photo: { type: String, default: null },
    groupe: { type: Boolean, default: false },
    /** `xs` 1.75rem · `sm` 2.25rem · `md` 2.75rem */
    taille: { type: String, default: 'xs' },
});

const classesTaille = computed(
    () =>
        ({
            xs: 'h-7 w-7 text-[10px]',
            sm: 'h-9 w-9 text-xs',
            md: 'h-11 w-11 text-sm',
        })[props.taille] ?? 'h-7 w-7 text-[10px]'
);

const initiales = computed(() =>
    props.nom
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((mot) => mot[0].toUpperCase())
        .join('')
);
</script>

<template>
    <img
        v-if="photo && !groupe"
        :src="photo"
        alt=""
        class="shrink-0 rounded-full object-cover ring-1 ring-slate-200"
        :class="classesTaille"
    />
    <span
        v-else
        class="flex shrink-0 items-center justify-center rounded-full font-semibold ring-1 ring-slate-200"
        :class="[
            classesTaille,
            groupe
                ? 'bg-[color:var(--marque-50)] text-[color:var(--marque-600)]'
                : 'bg-slate-100 text-slate-500',
        ]"
        aria-hidden="true"
    >
        <Users v-if="groupe" class="h-1/2 w-1/2" />
        <template v-else>{{ initiales }}</template>
    </span>
</template>
