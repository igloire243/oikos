<script setup>
import { computed } from 'vue';

/**
 * UNE MICRO-COURBE, sans axe ni étiquette — la tendance d'un chiffre, à côté de ce chiffre.
 *
 * Elle ne répond qu'à une question : « ça monte ou ça descend ? ». Pas de graduations, pas
 * d'infobulle, pas de zéro garanti — et c'est assumé : une sparkline qui prétendrait se lire
 * précisément prendrait la place d'un vrai graphique sans en faire le travail. Pour lire des
 * valeurs, on va sur l'écran Rapports.
 *
 * Elle s'étire donc sur le MIN et le MAX de la série, ce qu'un graphique complet ne ferait
 * jamais : ici on ne compare pas des hauteurs, on suit une forme.
 */
const props = defineProps({
    /** Des nombres bruts, du plus ancien au plus récent. */
    valeurs: { type: Array, default: () => [] },
    largeur: { type: Number, default: 96 },
    hauteur: { type: Number, default: 28 },
    /** Le libellé lu par un lecteur d'écran : la forme ne lui dit rien. */
    description: { type: String, default: 'Tendance récente' },
});

const nombres = computed(() => props.valeurs.map((v) => Number(v) || 0));

const min = computed(() => Math.min(...nombres.value));
const max = computed(() => Math.max(...nombres.value));

const points = computed(() => {
    const n = nombres.value.length;

    if (n < 2) {
        return '';
    }

    const amplitude = max.value - min.value || 1;

    return nombres.value
        .map((valeur, rang) => {
            const x = (props.largeur / (n - 1)) * rang;
            const y = props.hauteur - ((valeur - min.value) / amplitude) * (props.hauteur - 4) - 2;

            return `${x},${y}`;
        })
        .join(' ');
});

const monte = computed(() => nombres.value[nombres.value.length - 1] >= (nombres.value[0] ?? 0));
</script>

<template>
    <svg
        v-if="points"
        :viewBox="`0 0 ${largeur} ${hauteur}`"
        :width="largeur"
        :height="hauteur"
        class="overflow-visible"
        role="img"
        :aria-label="description"
    >
        <polyline
            :points="points"
            fill="none"
            :stroke="monte ? '#059669' : '#e11d48'"
            stroke-width="1.75"
            stroke-linejoin="round"
            stroke-linecap="round"
            vector-effect="non-scaling-stroke"
        />
    </svg>
</template>
