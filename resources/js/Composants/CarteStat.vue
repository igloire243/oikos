<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { TrendingDown, TrendingUp } from 'lucide-vue-next';
import Sparkline from '@/Composants/Graphiques/Sparkline.vue';

/**
 * UN INDICATEUR CHIFFRÉ.
 *
 * `tendance` est un pourcentage signé. Passer `null` MASQUE la comparaison au lieu d'afficher
 * « 0 % » : une église qui vient d'être créée n'a pas d'historique, et « 0 % » se lirait comme
 * une stagnation alors qu'il n'y a rien à comparer.
 *
 * `ton` ne suit la couleur de l'espace que par défaut. Les tons sémantiques — succès, alerte,
 * danger — restent FIXES d'un espace à l'autre : si le vert « succès » devenait ambre dans
 * l'espace du berger, le sens se perdrait.
 *
 * `serie` dessine une micro-courbe à côté du chiffre. Elle ne répond qu'à « ça monte ou ça
 * descend ? » : pas d'axe, pas de valeur lisible. Le chiffre du jour et la forme du mois, c'est
 * tout ce qu'une tuile doit porter — pour lire des valeurs, on va sur l'écran Rapports.
 */
const props = defineProps({
    libelle: { type: String, required: true },
    valeur: { type: [String, Number], required: true },
    precision: { type: String, default: null },
    icone: { type: [Object, Function], default: null },
    tendance: { type: Number, default: null },
    ton: { type: String, default: 'marque' },
    /** Des nombres bruts, du plus ancien au plus récent. Moins de deux points : rien à tracer. */
    serie: { type: Array, default: () => [] },
    /** Absent par défaut : la plupart des tuiles du produit ne sont QUE de la lecture. */
    href: { type: String, default: null },
});

const positive = computed(() => (props.tendance ?? 0) >= 0);

const classesDuTon = computed(
    () =>
        ({
            marque: 'text-[color:var(--marque-600)] bg-[color:var(--marque-50)]',
            ardoise: 'text-slate-600 bg-slate-100',
            succes: 'text-emerald-600 bg-emerald-50',
            alerte: 'text-amber-600 bg-amber-50',
            danger: 'text-rose-600 bg-rose-50',
            info: 'text-blue-600 bg-blue-50',
        })[props.ton] ?? 'text-slate-600 bg-slate-100'
);

/** Les nombres se lisent à la française : 1 234, pas 1,234. */
const valeurAffichee = computed(() =>
    typeof props.valeur === 'number' ? props.valeur.toLocaleString('fr-FR') : props.valeur
);
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href ?? undefined"
        class="group relative block overflow-hidden rounded-2xl border border-slate-200 bg-white p-3.5 shadow-card transition hover:shadow-card-hover sm:p-5"
        :class="href ? 'cursor-pointer hover:border-[color:var(--marque-300)]' : ''"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p
                    class="line-clamp-2 text-[11px] font-medium uppercase leading-tight tracking-wide text-slate-500 sm:truncate sm:text-xs"
                >
                    {{ libelle }}
                </p>
                <p
                    class="mt-1.5 text-xl font-bold tracking-tight text-slate-900 sm:mt-2 sm:text-2xl"
                >
                    {{ valeurAffichee }}
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <!-- Sur téléphone, deux tuiles par ligne : la courbe n'y a plus la place d'être lisible. -->
                <Sparkline
                    v-if="serie.length > 1"
                    class="hidden sm:block"
                    :valeurs="serie"
                    :description="`Tendance de : ${libelle}`"
                />

                <div
                    v-if="icone"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition group-hover:scale-105 sm:h-11 sm:w-11 sm:rounded-xl"
                    :class="classesDuTon"
                >
                    <component :is="icone" class="h-4 w-4 sm:h-5 sm:w-5" :stroke-width="1.9" />
                </div>
            </div>
        </div>

        <div
            v-if="tendance !== null || precision"
            class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] sm:mt-3 sm:text-xs"
        >
            <span
                v-if="tendance !== null"
                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold"
                :class="positive ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
            >
                <component :is="positive ? TrendingUp : TrendingDown" class="h-3 w-3" />
                {{ positive ? '+' : '' }}{{ tendance }} %
            </span>
            <span v-if="precision" class="line-clamp-2 text-slate-500 sm:truncate">{{
                precision
            }}</span>
        </div>
    </component>
</template>
