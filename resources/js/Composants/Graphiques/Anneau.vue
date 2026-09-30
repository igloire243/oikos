<script setup>
import { computed, ref } from 'vue';
import Cadre from './Cadre.vue';
import { couleurCategorie, nombre, pourcentage } from './palette.js';

/**
 * UN ANNEAU — la COMPOSITION d'un ensemble.
 *
 * « Hommes / femmes / garçons / filles », « les profils spirituels du registre ». Des catégories,
 * donc la palette FIXE : « Hommes » doit avoir la même couleur dans les cinq espaces, sinon
 * quelqu'un qui passe de l'un à l'autre lit le graphique de travers sans s'en rendre compte.
 *
 * UN ANNEAU ET PAS UN CAMEMBERT : le trou du milieu porte le total, qui est la première chose
 * qu'on cherche — « 124 membres, dont… ». Et l'œil compare mieux des arcs que des pointes.
 *
 * LA LÉGENDE PORTE LES CHIFFRES. Un anneau seul ne permet pas de distinguer 18 % de 22 % ; il
 * montre les ordres de grandeur, la légende donne la valeur exacte. Les deux ensemble, jamais
 * l'un sans l'autre.
 */
const props = defineProps({
    /** [{ libelle, valeur }] */
    series: { type: Array, default: () => [] },
    titre: { type: String, default: null },
    sousTitre: { type: String, default: null },
    hauteur: { type: Number, default: 220 },
    /** Ce que le trou du milieu annonce : « membres », « présents »… */
    unite: { type: String, default: null },
    videTexte: { type: String, default: 'Pas encore de données à montrer.' },
});

const RAYON = 70;
const EPAISSEUR = 26;
const CENTRE = 90;

const survole = ref(null);

const total = computed(() => props.series.reduce((somme, p) => somme + (Number(p.valeur) || 0), 0));

/** Les parts non nulles : une catégorie à zéro n'a pas d'arc, donc pas de couleur à réserver. */
const parts = computed(() => {
    let angle = -90;

    return props.series.map((point, rang) => {
        const valeur = Number(point.valeur) || 0;
        const portion = total.value ? valeur / total.value : 0;
        const debut = angle;
        angle += portion * 360;

        return {
            ...point,
            rang,
            valeur,
            portion,
            debut,
            fin: angle,
            couleur: couleurCategorie(rang),
            pourcentage: pourcentage(valeur, total.value),
        };
    });
});

const coordonnee = (angle, rayon) => {
    const radians = ((angle - 0) * Math.PI) / 180;

    return [CENTRE + rayon * Math.cos(radians), CENTRE + rayon * Math.sin(radians)];
};

/**
 * Le chemin d'un arc.
 *
 * Une part à 100 % est le cas particulier : un arc dont le début et la fin coïncident ne dessine
 * rien du tout. On trace alors deux demi-arcs — sans quoi une église dont tous les membres sont
 * dans la même catégorie afficherait un anneau vide.
 */
const chemin = (part) => {
    const interieur = RAYON - EPAISSEUR;

    if (part.portion >= 0.999) {
        const [ax, ay] = coordonnee(-90, RAYON);
        const [bx, by] = coordonnee(90, RAYON);
        const [cx, cy] = coordonnee(90, interieur);
        const [dx, dy] = coordonnee(-90, interieur);

        return [
            `M ${ax} ${ay}`,
            `A ${RAYON} ${RAYON} 0 0 1 ${bx} ${by}`,
            `A ${RAYON} ${RAYON} 0 0 1 ${ax} ${ay}`,
            `L ${dx} ${dy}`,
            `A ${interieur} ${interieur} 0 0 0 ${cx} ${cy}`,
            `A ${interieur} ${interieur} 0 0 0 ${dx} ${dy}`,
            'Z',
        ].join(' ');
    }

    const grand = part.fin - part.debut > 180 ? 1 : 0;
    const [ax, ay] = coordonnee(part.debut, RAYON);
    const [bx, by] = coordonnee(part.fin, RAYON);
    const [cx, cy] = coordonnee(part.fin, interieur);
    const [dx, dy] = coordonnee(part.debut, interieur);

    return [
        `M ${ax} ${ay}`,
        `A ${RAYON} ${RAYON} 0 ${grand} 1 ${bx} ${by}`,
        `L ${cx} ${cy}`,
        `A ${interieur} ${interieur} 0 ${grand} 0 ${dx} ${dy}`,
        'Z',
    ].join(' ');
};
</script>

<template>
    <Cadre
        :titre="titre"
        :sous-titre="sousTitre"
        :hauteur="hauteur"
        :vide="series.length === 0 || total === 0"
        :vide-texte="videTexte"
    >
        <template #actions>
            <slot name="actions" />
        </template>

        <div class="flex flex-wrap items-center gap-6">
            <svg
                viewBox="0 0 180 180"
                class="h-44 w-44 shrink-0"
                role="img"
                :aria-label="titre ?? 'Graphique de composition'"
            >
                <path
                    v-for="part in parts"
                    :key="part.libelle"
                    :d="chemin(part)"
                    :fill="part.couleur"
                    :opacity="survole === null || survole === part.rang ? 1 : 0.35"
                    class="cursor-default transition-opacity"
                    @mouseenter="survole = part.rang"
                    @mouseleave="survole = null"
                />

                <text
                    x="90"
                    y="86"
                    text-anchor="middle"
                    class="fill-slate-900 text-[26px] font-bold"
                >
                    {{ nombre(survole === null ? total : parts[survole].valeur) }}
                </text>
                <text x="90" y="104" text-anchor="middle" class="fill-slate-400 text-[11px]">
                    {{
                        survole === null ? (unite ?? 'au total') : parts[survole].pourcentage + ' %'
                    }}
                </text>
            </svg>

            <!-- La légende porte les chiffres : l'anneau seul ne distingue pas 18 % de 22 %.
                 Une largeur MINIMALE, et non `min-w-0` : sinon, sur téléphone, elle se serrait à
                 côté de l'anneau jusqu'à ne plus montrer que « C.. » ; ainsi elle passe dessous. -->
            <ul class="min-w-[11rem] flex-1 space-y-1.5">
                <li
                    v-for="part in parts"
                    :key="part.libelle"
                    class="flex items-center gap-2 text-sm"
                    @mouseenter="survole = part.rang"
                    @mouseleave="survole = null"
                >
                    <span
                        class="h-2.5 w-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: part.couleur }"
                    />
                    <span class="min-w-0 flex-1 truncate text-slate-600">{{ part.libelle }}</span>
                    <span class="shrink-0 font-semibold text-slate-800">
                        {{ nombre(part.valeur) }}
                    </span>
                    <span class="w-10 shrink-0 text-right text-xs text-slate-400">
                        {{ part.pourcentage }} %
                    </span>
                </li>
            </ul>
        </div>
    </Cadre>
</template>
