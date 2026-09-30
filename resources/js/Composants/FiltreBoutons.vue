<script setup>
import { icone } from '@/Composants/icones.js';

/**
 * LES FILTRES, EN BOUTONS.
 *
 * Le bouton ACTIF garde son libellé, les autres se réduisent à leur icône dès que la place
 * manque. C'est ce qui permet d'aligner six filtres sur un téléphone sans que l'utilisateur
 * perde de vue celui qui est en cours — l'information la plus importante de la barre.
 *
 * Le compteur, quand il est fourni, évite d'avoir à cliquer pour découvrir qu'un filtre ne
 * ramène rien.
 */
defineProps({
    // [{ valeur, libelle, icone, compte }]
    options: { type: Array, required: true },
    modelValue: { type: [String, null], default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <!--
        Sur téléphone, les filtres DÉFILENT sur une ligne au lieu de s'empiler en trois rangées
        qui repoussent la liste hors de l'écran ; chacun garde son libellé — une icône seule ne se
        devine pas.
    -->
    <div
        class="defilement-discret -mx-1 flex items-center gap-1.5 overflow-x-auto px-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0"
    >
        <button
            v-for="option in options"
            :key="option.valeur ?? 'tous'"
            type="button"
            class="inline-flex min-h-10 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl px-3 py-2 text-sm font-medium transition"
            :class="
                modelValue === option.valeur
                    ? 'text-[color:var(--marque-700)]'
                    : 'text-slate-600 hover:bg-slate-100'
            "
            :style="modelValue === option.valeur ? { backgroundColor: 'var(--marque-50)' } : null"
            :title="option.libelle"
            @click="$emit('update:modelValue', option.valeur)"
        >
            <component
                :is="icone(option.icone)"
                v-if="option.icone"
                class="h-4 w-4 shrink-0"
                :stroke-width="modelValue === option.valeur ? 2.2 : 1.8"
            />
            <span>
                {{ option.libelle }}
            </span>
            <span
                v-if="option.compte !== undefined && option.compte !== null"
                class="rounded-full bg-white/70 px-1.5 text-xs font-semibold"
                :class="modelValue === option.valeur ? '' : 'text-slate-500'"
            >
                {{ option.compte }}
            </span>
        </button>
    </div>
</template>
