<script setup>
import { computed } from 'vue';

/**
 * UN RATIO SUR UN OBJECTIF — « 62 membres sur les 100 qui feraient un secteur ».
 *
 * Un demi-anneau plutôt qu'une barre, parce que ce n'est pas une proportion d'un tout mais une
 * PROGRESSION vers un seuil : la forme dit « on en est là du chemin ».
 *
 * AU-DELÀ DE L'OBJECTIF, la jauge sature à 100 % et le dit dans le chiffre. La laisser déborder
 * casserait le dessin, et la tronquer en silence ferait croire qu'on est pile à l'objectif.
 */
const props = defineProps({
    valeur: { type: Number, required: true },
    objectif: { type: Number, required: true },
    libelle: { type: String, default: null },
    /** Ce qu'on compte : « membres », « fiches complètes »… */
    unite: { type: String, default: null },
});

const RAYON = 62;
const CENTRE = 80;

const ratio = computed(() =>
    props.objectif > 0 ? Math.min(1, Math.max(0, props.valeur / props.objectif)) : 0
);

const pourcentage = computed(() =>
    props.objectif > 0 ? Math.round((props.valeur / props.objectif) * 100) : 0
);

const coordonnee = (portion) => {
    const angle = Math.PI * (1 + portion);

    return [CENTRE + RAYON * Math.cos(angle), CENTRE + RAYON * Math.sin(angle)];
};

const chemin = (portion) => {
    const [ax, ay] = coordonnee(0);
    const [bx, by] = coordonnee(portion);

    return `M ${ax} ${ay} A ${RAYON} ${RAYON} 0 ${portion > 0.5 ? 1 : 0} 1 ${bx} ${by}`;
};
</script>

<template>
    <div class="flex flex-col items-center">
        <svg viewBox="0 0 160 96" class="w-40" role="img" :aria-label="libelle ?? 'Progression'">
            <path
                :d="chemin(1)"
                fill="none"
                stroke="#e2e8f0"
                stroke-width="12"
                stroke-linecap="round"
            />
            <path
                v-if="ratio > 0"
                :d="chemin(ratio)"
                fill="none"
                stroke="var(--marque-500)"
                stroke-width="12"
                stroke-linecap="round"
            />

            <text x="80" y="76" text-anchor="middle" class="fill-slate-900 text-[24px] font-bold">
                {{ pourcentage }} %
            </text>
        </svg>

        <p v-if="libelle" class="-mt-1 text-center text-xs leading-relaxed text-slate-500">
            {{ libelle }}
        </p>
        <p class="text-center text-xs text-slate-400">
            {{ valeur }} / {{ objectif }}
            <template v-if="unite">{{ unite }}</template>
        </p>
    </div>
</template>
