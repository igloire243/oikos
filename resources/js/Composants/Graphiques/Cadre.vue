<script setup>
import { ChartNoAxesColumn } from 'lucide-vue-next';

/**
 * LE CADRE COMMUN À TOUS LES GRAPHIQUES : titre, légende, et surtout l'ÉTAT VIDE.
 *
 * L'état vide est la raison d'être de ce composant. Un graphique sans données affiche sinon un
 * cadre avec des axes et rien dedans — ce qui se lit comme « tout est à zéro » alors que la
 * réponse est « personne n'a encore saisi ». C'est exactement la confusion que le moteur
 * d'activité évite déjà côté métier : une entité sans culte saisi ne fait décrocher personne.
 */
defineProps({
    titre: { type: String, default: null },
    sousTitre: { type: String, default: null },
    /** Vrai quand il n'y a rien À MONTRER — distinct de « tout vaut zéro ». */
    vide: { type: Boolean, default: false },
    videTexte: { type: String, default: 'Pas encore de données à montrer.' },
    /** La hauteur du dessin, en pixels. La largeur suit toujours le conteneur. */
    hauteur: { type: Number, default: 220 },
});
</script>

<template>
    <div class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-card sm:p-5">
        <div
            v-if="titre || $slots.actions"
            class="mb-4 flex flex-wrap items-start justify-between gap-x-3 gap-y-2"
        >
            <div class="min-w-0">
                <h3 v-if="titre" class="text-sm font-semibold text-slate-800 sm:truncate">
                    {{ titre }}
                </h3>
                <p v-if="sousTitre" class="mt-0.5 text-xs leading-relaxed text-slate-500">
                    {{ sousTitre }}
                </p>
            </div>
            <slot name="actions" />
        </div>

        <div
            v-if="vide"
            class="flex flex-col items-center justify-center text-center"
            :style="{ height: hauteur + 'px' }"
        >
            <div
                class="mb-3 flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
            >
                <ChartNoAxesColumn class="h-5 w-5" />
            </div>
            <p class="max-w-xs text-xs leading-relaxed text-slate-500">{{ videTexte }}</p>
        </div>

        <template v-else>
            <slot />
            <slot name="legende" />
        </template>
    </div>
</template>
