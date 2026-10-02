<script setup>
import { ref } from 'vue';
import { ChevronRight, Church, Folder, FolderOpen } from 'lucide-vue-next';
import LigneEntite from '@/Composants/Console/LigneEntite.vue';

/**
 * UN NŒUD DE L'ARBORESCENCE — un dossier qui se plie, ou une église au bout de la branche.
 *
 * L'affichage « en dossiers » de l'ancienne console, gardé à la demande de l'utilisateur : la Vision
 * est la racine, ses antennes sont des sous-dossiers, leurs églises ce qu'ils contiennent. Un
 * dossier montre combien il contient une fois plié — on sait ce qu'on cache avant de l'ouvrir.
 *
 * Récursif : le même composant sert à chaque étage, et c'est lui qui décide si une ligne est un
 * dossier (elle a des enfants) ou une feuille (elle n'en a pas).
 */
const props = defineProps({
    noeud: { type: Object, required: true },
    // Les deux premiers étages s'ouvrent d'office : on vient pour voir les églises, pas des dossiers clos.
    profondeur: { type: Number, default: 0 },
});

defineEmits(['vendre', 'historique']);

const ouvert = ref(props.profondeur < 2);
</script>

<template>
    <li>
        <div class="flex min-w-0 items-start gap-1.5">
            <button
                v-if="noeud.enfants.length"
                type="button"
                class="mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100"
                :aria-expanded="ouvert"
                :title="ouvert ? 'Replier' : 'Déplier'"
                @click="ouvert = !ouvert"
            >
                <ChevronRight class="h-4 w-4 transition" :class="ouvert ? 'rotate-90' : ''" />
            </button>
            <span v-else class="mt-1 h-7 w-7 shrink-0" />

            <component
                :is="noeud.enfants.length ? (ouvert ? FolderOpen : Folder) : Church"
                class="mt-[0.4rem] h-5 w-5 shrink-0"
                :class="noeud.enfants.length ? 'text-amber-500' : 'text-slate-400'"
            />

            <div class="min-w-0 flex-1 pt-0.5">
                <LigneEntite
                    :entite="noeud"
                    :enfant="!noeud.enfants.length"
                    @vendre="$emit('vendre', $event)"
                    @historique="$emit('historique', $event)"
                />
                <p v-if="noeud.enfants.length && !ouvert" class="text-xs text-slate-400">
                    {{ noeud.enfants.length }} élément{{ noeud.enfants.length > 1 ? 's' : '' }}
                </p>
            </div>
        </div>

        <ul
            v-if="noeud.enfants.length && ouvert"
            class="ml-3 mt-1 space-y-1 border-l border-slate-200 pl-3 sm:ml-4 sm:pl-4"
        >
            <NoeudEntite
                v-for="enfant in noeud.enfants"
                :key="enfant.reference ?? enfant.nom"
                :noeud="enfant"
                :profondeur="profondeur + 1"
                @vendre="$emit('vendre', $event)"
                @historique="$emit('historique', $event)"
            />
        </ul>
    </li>
</template>
