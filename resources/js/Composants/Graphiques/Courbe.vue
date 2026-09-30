<script setup>
import { computed, ref } from 'vue';
import Cadre from './Cadre.vue';
import { graduations, nombre, plafond } from './palette.js';

/**
 * UNE COURBE — la même donnée que les barres, quand ce qui compte est la TENDANCE.
 *
 * Barres ou courbe ? Les barres comparent des valeurs isolées (« combien dimanche dernier ? ») ;
 * la courbe montre un mouvement (« est-ce que ça monte depuis trois mois ? »). Sur douze points
 * ou plus, la courbe gagne : douze barres serrées deviennent un peigne.
 *
 * L'AIRE SOUS LA COURBE est décorative et volontairement très pâle : elle aide l'œil à suivre le
 * mouvement sans suggérer un cumul, qui serait faux — la surface ne veut rien dire ici.
 */
const props = defineProps({
    /** [{ libelle, valeur }] */
    series: { type: Array, default: () => [] },
    titre: { type: String, default: null },
    sousTitre: { type: String, default: null },
    hauteur: { type: Number, default: 220 },
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

const y = (valeur) => MARGE.haut + hauteurDessin.value * (1 - valeur / max.value);

// Un point unique se place au MILIEU plutôt qu'au bord : collé à gauche, il se lirait comme le
// début d'une courbe dont la suite manque.
const x = (rang) =>
    props.series.length === 1
        ? MARGE.gauche + largeurDessin.value / 2
        : MARGE.gauche + (largeurDessin.value / (props.series.length - 1)) * rang;

const points = computed(() => props.series.map((p, rang) => `${x(rang)},${y(p.valeur)}`).join(' '));

const aire = computed(() => {
    if (props.series.length === 0) {
        return '';
    }

    const bas = MARGE.haut + hauteurDessin.value;

    return `${x(0)},${bas} ${points.value} ${x(props.series.length - 1)},${bas}`;
});

const pasEtiquette = computed(() => Math.ceil(props.series.length / 6));

/** Le point le plus proche du curseur — on interroge la courbe, pas un pixel précis. */
const viser = (evenement) => {
    const cadre = evenement.currentTarget.getBoundingClientRect();
    const ratio = (evenement.clientX - cadre.left) / cadre.width;
    const position = (ratio * LARGEUR - MARGE.gauche) / largeurDessin.value;

    survole.value = Math.min(
        props.series.length - 1,
        Math.max(0, Math.round(position * Math.max(1, props.series.length - 1)))
    );
};
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
                :aria-label="titre ?? 'Graphique en courbe'"
                @mousemove="viser"
                @mouseleave="survole = null"
            >
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

                <polygon :points="aire" fill="var(--marque-500)" opacity="0.10" />

                <polyline
                    :points="points"
                    fill="none"
                    stroke="var(--marque-600)"
                    stroke-width="2.5"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                    vector-effect="non-scaling-stroke"
                />

                <circle
                    v-for="(point, rang) in series"
                    :key="point.libelle + rang"
                    :cx="x(rang)"
                    :cy="y(point.valeur)"
                    :r="survole === rang ? 5 : 3"
                    fill="white"
                    stroke="var(--marque-600)"
                    stroke-width="2"
                    vector-effect="non-scaling-stroke"
                />
            </svg>

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
                class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-lg bg-slate-900 px-2 py-1 text-xs text-white shadow-lg"
                :style="{
                    left: (x(survole) / LARGEUR) * 100 + '%',
                    top: y(series[survole].valeur) - 8 + 'px',
                }"
            >
                <span class="font-semibold">{{ nombre(series[survole].valeur) }}</span>
                <span v-if="unite" class="text-slate-300"> {{ unite }}</span>
                <span class="text-slate-400"> — {{ series[survole].libelle }}</span>
            </div>
        </div>
    </Cadre>
</template>
