<script setup>
/**
 * DES ONGLETS — changer la FORME d'un même bloc de données, pas le filtrer.
 *
 * À ne pas confondre avec `FiltreBoutons` : celui-ci réduit un ensemble de lignes (« tout »,
 * « en attente »…), celui-ci change la manière de LIRE le même ensemble complet — un tableau ou
 * un graphique du même tableau. D'où un style volontairement distinct (soulignement, pas pilule),
 * pour que l'œil ne confonde pas les deux gestes.
 */
import { nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({
    /** [{ valeur, libelle, icone, compteur }] — `compteur` : une pastille, ce qui attend dedans. */
    options: { type: Array, required: true },
    modelValue: { type: String, required: true },
});

defineEmits(['update:modelValue']);

/*
 * L'ONGLET ACTIF RESTE VISIBLE. Sur un téléphone la rangée défile, et un onglet ouvert par un lien
 * (`?onglet=contributions`) pouvait se trouver hors de l'écran : la page montrait son contenu
 * sans qu'on voie de quel onglet il s'agissait.
 */
const rangee = ref(null);

const montrerLActif = () =>
    nextTick(() => {
        const actif = rangee.value?.querySelector('[aria-selected="true"]');
        if (!actif || !rangee.value) return;
        const { offsetLeft, offsetWidth } = actif;
        const { scrollLeft, clientWidth } = rangee.value;
        if (offsetLeft < scrollLeft || offsetLeft + offsetWidth > scrollLeft + clientWidth) {
            rangee.value.scrollLeft = offsetLeft - 16;
        }
    });

onMounted(montrerLActif);
watch(() => props.modelValue, montrerLActif);
</script>

<template>
    <div
        ref="rangee"
        class="defilement-discret flex items-center gap-1 overflow-x-auto border-b border-slate-200"
        role="tablist"
    >
        <button
            v-for="option in options"
            :key="option.valeur"
            type="button"
            role="tab"
            :aria-selected="modelValue === option.valeur"
            class="relative flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap px-3 py-2 text-sm font-semibold transition"
            :class="
                modelValue === option.valeur
                    ? 'text-[color:var(--marque-700)]'
                    : 'text-slate-500 hover:text-slate-700'
            "
            @click="$emit('update:modelValue', option.valeur)"
        >
            <component :is="option.icone" v-if="option.icone" class="h-4 w-4" />
            {{ option.libelle }}
            <span
                v-if="option.compteur"
                class="rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white"
            >
                {{ option.compteur }}
            </span>
            <span
                v-if="modelValue === option.valeur"
                class="absolute inset-x-0 -bottom-px h-0.5 rounded-full"
                :style="{ backgroundColor: 'var(--marque-600)' }"
            />
        </button>
    </div>
</template>
