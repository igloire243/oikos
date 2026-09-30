<script setup>
import { computed, ref } from 'vue';
import Cadre from './Cadre.vue';
import { graduations, nombre, plafond } from './palette.js';

/**
 * DES BARRES VERTICALES — une seule série dans le temps.
 *
 * « Les présences des douze derniers dimanches », « les membres inscrits par mois ». Une seule
 * série, donc la couleur de l'ESPACE : le graphique appartient à l'écran qui le montre.
 *
 * LE ZÉRO EST TOUJOURS À ZÉRO. Tronquer l'axe pour « mieux voir la variation » multiplie
 * visuellement un écart de 3 % par dix — c'est la première façon de mentir avec un graphique,
 * et elle se fait sans intention.
 */
const props = defineProps({
    /** [{ libelle, valeur }] */
    series: { type: Array, default: () => [] },
    titre: { type: String, default: null },
    sousTitre: { type: String, default: null },
    hauteur: { type: Number, default: 220 },
    /** Ce qu'une valeur COMPTE, pour l'infobulle : « présents », « membres »… */
    unite: { type: String, default: null },
    videTexte: { type: String, default: 'Pas encore de données à montrer.' },
});

const LARGEUR = 600;
const MARGE = { haut: 12, bas: 28, gauche: 38, droite: 8 };

const survole = ref(null);

const max = computed(() => plafond(props.series.map((p) => p.valeur)));
const lignes = computed(() => graduations(max.value));

const hauteurDessin = computed(() => props.hauteur - MARGE.haut - MARGE.bas);
const largeurDessin = computed(() => LARGEUR - MARGE.gauche - MARGE.droite);

const pas = computed(() => largeurDessin.value / Math.max(1, props.series.length));
const largeurBarre = computed(() => Math.min(46, pas.value * 0.62));

const y = (valeur) => MARGE.haut + hauteurDessin.value * (1 - valeur / max.value);
const x = (rang) => MARGE.gauche + pas.value * rang + (pas.value - largeurBarre.value) / 2;

/**
 * Un libellé sur deux quand ils sont nombreux : douze dates alignées sur un téléphone se
 * chevauchent, et deux étiquettes superposées valent moins qu'une seule lisible.
 */
const pasEtiquette = computed(() => Math.ceil(props.series.length / 6));
</script>

<template>
    <Cadre
        :titre="titre"
        :sous-titre="sousTitre"
        :hauteur="hauteur"
        :vide="series.length === 0"
        :vide-texte="videTexte"
    >
        <template #actions>
            <slot name="actions" />
        </template>

        <div class="relative">
            <svg
                :viewBox="`0 0 ${LARGEUR} ${hauteur}`"
                class="w-full"
                :style="{ height: hauteur + 'px' }"
                preserveAspectRatio="none"
                role="img"
                :aria-label="titre ?? 'Graphique en barres'"
            >
                <!-- Les graduations, derrière les barres. -->
                <g>
                    <line
                        v-for="ligne in lignes"
                        :key="ligne"
                        :x1="MARGE.gauche"
                        :x2="LARGEUR - MARGE.droite"
                        :y1="y(ligne)"
                        :y2="y(ligne)"
                        stroke="#e2e8f0"
                        stroke-width="1"
                        vector-effect="non-scaling-stroke"
                    />
                </g>

                <g
                    v-for="(point, rang) in series"
                    :key="point.libelle + rang"
                    @mouseenter="survole = rang"
                    @mouseleave="survole = null"
                >
                    <!-- Une cible de survol pleine hauteur : viser une barre de 3 px de haut
                         serait impossible, et c'est justement celle qu'on veut interroger. -->
                    <rect
                        :x="MARGE.gauche + pas * rang"
                        :y="MARGE.haut"
                        :width="pas"
                        :height="hauteurDessin"
                        fill="transparent"
                    />
                    <rect
                        :x="x(rang)"
                        :y="y(point.valeur)"
                        :width="largeurBarre"
                        :height="Math.max(1, MARGE.haut + hauteurDessin - y(point.valeur))"
                        rx="3"
                        :fill="survole === rang ? 'var(--marque-700)' : 'var(--marque-500)'"
                        class="transition-[fill]"
                    />
                </g>
            </svg>

            <!-- Les étiquettes en HTML : un `text` SVG se déforme avec `preserveAspectRatio` -->
            <div class="mt-1 flex" :style="{ paddingLeft: (MARGE.gauche / LARGEUR) * 100 + '%' }">
                <div
                    v-for="(point, rang) in series"
                    :key="point.libelle + rang"
                    class="min-w-0 text-center text-[10px] leading-tight text-slate-400"
                    :style="{ width: 100 / series.length + '%' }"
                >
                    <span v-if="rang % pasEtiquette === 0" class="block truncate">
                        {{ point.libelle }}
                    </span>
                </div>
            </div>

            <!-- L'échelle, en HTML pour la même raison. -->
            <div
                class="pointer-events-none absolute left-0 top-0 flex flex-col justify-between text-[10px] text-slate-400"
                :style="{ height: hauteur - MARGE.bas + 'px' }"
            >
                <span v-for="ligne in [...lignes].reverse()" :key="ligne">
                    {{ nombre(Math.round(ligne)) }}
                </span>
            </div>

            <div
                v-if="survole !== null"
                class="pointer-events-none absolute -top-1 z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-lg bg-slate-900 px-2 py-1 text-xs text-white shadow-lg"
                :style="{
                    left: ((MARGE.gauche + pas * (survole + 0.5)) / LARGEUR) * 100 + '%',
                    top: y(series[survole].valeur) + 'px',
                }"
            >
                <span class="font-semibold">{{ nombre(series[survole].valeur) }}</span>
                <span v-if="unite" class="text-slate-300"> {{ unite }}</span>
                <span class="text-slate-400"> — {{ series[survole].libelle }}</span>
            </div>
        </div>
    </Cadre>
</template>
