<script setup>
import { computed } from 'vue';
import Cadre from './Cadre.vue';
import Avatar from '@/Composants/Avatar.vue';
import { nombre, plafond } from './palette.js';

/**
 * DES BARRES HORIZONTALES — comparer des ENTITÉS entre elles.
 *
 * « Quelle extension a le plus de membres », « laquelle a la meilleure assiduité ». Horizontales
 * et pas verticales pour une raison bête et décisive : les noms d'entités sont longs
 * (« Antenne de la Gombe — Limete Industriel »), et à la verticale ils se chevauchent ou
 * s'inclinent à 45°, ce qui les rend pénibles à lire.
 *
 * ON NE TRIE PAS ICI. L'ordre vient du serveur, parce que c'est lui qui sait si le classement
 * porte sur l'effectif, l'assiduité ou autre chose — et parce qu'un tri côté navigateur ne
 * porterait que sur la page affichée.
 */
const props = defineProps({
    /** [{ libelle, valeur, precision?, personne?, photo? }] — `personne` : un visage devant le nom. */
    series: { type: Array, default: () => [] },
    titre: { type: String, default: null },
    sousTitre: { type: String, default: null },
    unite: { type: String, default: null },
    /** Affiche la valeur en pourcentage plutôt qu'en nombre — pour les taux. */
    enPourcentage: { type: Boolean, default: false },
    videTexte: { type: String, default: 'Pas encore de données à montrer.' },
});

const max = computed(() =>
    props.enPourcentage ? 100 : plafond(props.series.map((p) => p.valeur))
);

const largeur = (valeur) => Math.max(1, ((Number(valeur) || 0) / max.value) * 100);
</script>

<template>
    <Cadre
        :titre="titre"
        :sous-titre="sousTitre"
        :vide="series.length === 0"
        :vide-texte="videTexte"
    >
        <template #actions>
            <slot name="actions" />
        </template>

        <ul class="space-y-3">
            <li v-for="point in series" :key="point.libelle">
                <div
                    class="mb-1 flex justify-between gap-3 text-sm"
                    :class="point.personne ? 'items-center' : 'items-baseline'"
                >
                    <span class="flex min-w-0 items-center gap-2">
                        <Avatar v-if="point.personne" :nom="point.libelle" :photo="point.photo" />
                        <span class="min-w-0 truncate text-slate-700">{{ point.libelle }}</span>
                    </span>
                    <span class="shrink-0 font-semibold text-slate-800">
                        {{ enPourcentage ? point.valeur + ' %' : nombre(point.valeur) }}
                        <span v-if="unite && !enPourcentage" class="font-normal text-slate-400">
                            {{ unite }}
                        </span>
                    </span>
                </div>

                <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                    <div
                        class="h-full rounded-full transition-[width]"
                        :style="{
                            width: largeur(point.valeur) + '%',
                            backgroundColor: 'var(--marque-500)',
                        }"
                    />
                </div>

                <p v-if="point.precision" class="mt-0.5 text-xs text-slate-400">
                    {{ point.precision }}
                </p>
            </li>
        </ul>
    </Cadre>
</template>
