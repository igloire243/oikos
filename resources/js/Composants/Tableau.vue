<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, ChevronRight, Inbox, SearchX } from 'lucide-vue-next';
import { useEstMobile } from '@/Composables/estMobile.js';

/**
 * LE TABLEAU DU PRODUIT.
 *
 * DEUX ÉTATS VIDES, ET C'EST LE POINT IMPORTANT.
 *
 * « Rien enregistré » et « aucun résultat » ne demandent pas la même chose à l'utilisateur : le
 * premier invite à créer, le second à élargir le filtre. Les confondre — ce que l'ancien produit
 * faisait presque partout — laisse quelqu'un devant un tableau vide en se demandant s'il a mal
 * fait quelque chose, alors qu'il a simplement filtré trop fin.
 *
 * Le composant ne sait pas lequel afficher tout seul : c'est l'écran qui dit, par `filtreActif`,
 * si l'utilisateur a demandé quelque chose de particulier.
 */
const props = defineProps({
    // [{ cle, libelle, triable, aligne: 'droite'|'centre', classe, titre }]
    // `titre: true` : la colonne qui sert de titre à la carte sur téléphone (la première sinon).
    colonnes: { type: Array, required: true },
    lignes: { type: Array, required: true },

    /** La colonne triée et son sens, tels que le serveur les a appliqués. */
    tri: { type: Object, default: () => ({ colonne: null, sens: 'asc' }) },

    /** Vrai dès qu'une recherche ou un filtre est en cours — décide de l'état vide affiché. */
    filtreActif: { type: Boolean, default: false },

    videTitre: { type: String, default: 'Rien à afficher' },
    videTexte: { type: String, default: null },
    aucunResultatTitre: { type: String, default: 'Aucun résultat' },

    /*
     * LE DÉCOMPTE, À CÔTÉ DES FILTRES. Demandé par l'utilisateur : « 124 membres » répond à la
     * question qu'on se pose en filtrant — combien en reste-t-il ? — et il était tout en bas, sous
     * la dernière carte. Le paginateur de Laravel tel quel, et le libellé au pluriel.
     */
    decompte: { type: Object, default: null },

    /**
     * LA LIGNE ENTIÈRE MÈNE QUELQUE PART — `(ligne) => url`. Demandé par l'utilisateur sur le
     * mode supervision : viser le seul nom d'une église, au doigt, rate une fois sur deux. Un
     * clic sur un vrai lien ou un bouton de la ligne garde son propre effet.
     */
    lien: { type: Function, default: null },
    libelleDecompte: { type: String, default: 'résultats' },
    libelleDecompteSingulier: { type: String, default: null },
    aucunResultatTexte: {
        type: String,
        default:
            'Aucune ligne ne correspond à ce que vous cherchez. Élargissez la recherche ou changez de filtre.',
    },
});

const emit = defineEmits(['trier']);

/*
 * SUR TÉLÉPHONE, UNE CARTE PAR LIGNE. Un tableau de cinq colonnes sur 390 px se lit en le faisant
 * glisser de côté, colonne après colonne, en perdant de vue le nom de la ligne qu'on lisait : on ne
 * compare plus rien. La carte garde la première colonne en titre, et chaque autre valeur sous son
 * libellé. Les mêmes slots servent les deux formes : un écran n'a rien à écrire de plus.
 *
 * Les colonnes sans libellé (une colonne d'actions, souvent) passent en pied de carte, alignées à
 * droite, là où le pouce les trouve.
 */
const estMobile = useEstMobile();
// Le TITRE d'une carte, c'est ce qu'on cherche des yeux : le nom d'une personne, pas son
// matricule. Un registre dont le matricule vient en premier sur grand écran met donc
// `titre: true` sur le nom — sinon le nom, relégué dans la grille à deux colonnes, n'avait que la
// moitié de la largeur et débordait sur sa voisine.
const colonneTitre = computed(() => props.colonnes.find((c) => c.titre) ?? props.colonnes[0]);
const autres = computed(() => props.colonnes.filter((c) => c !== colonneTitre.value));
// `masqueMobile: true` : une colonne que la carte dit déjà autrement (un badge dans le titre).
const colonnesDetail = computed(() => autres.value.filter((c) => c.libelle && !c.masqueMobile));
const colonnesActions = computed(() => autres.value.filter((c) => !c.libelle));
const colonnesTriables = computed(() => props.colonnes.filter((c) => c.triable));

const estVide = computed(() => props.lignes.length === 0);

const ouvrirLaLigne = (evenement, ligne) => {
    if (!props.lien || evenement.target.closest('a, button, input, select, label, textarea')) {
        return;
    }
    router.visit(props.lien(ligne));
};

const texteDecompte = computed(() => {
    if (props.decompte === null) {
        return null;
    }
    const total = props.decompte.total ?? 0;
    const libelle =
        total === 1
            ? (props.libelleDecompteSingulier ?? props.libelleDecompte.replace(/s$/, ''))
            : props.libelleDecompte;
    return `${total} ${libelle}`;
});

const iconeTri = (colonne) => {
    if (props.tri?.colonne !== colonne.cle) {
        return ArrowUpDown;
    }

    return props.tri.sens === 'desc' ? ArrowDown : ArrowUp;
};

const basculerTri = (colonne) => {
    if (!colonne.triable) {
        return;
    }

    const memeColonne = props.tri?.colonne === colonne.cle;

    emit('trier', {
        colonne: colonne.cle,
        sens: memeColonne && props.tri.sens === 'asc' ? 'desc' : 'asc',
    });
};
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card">
        <div
            v-if="$slots.outils || texteDecompte"
            class="flex flex-col gap-2 border-b border-slate-200 bg-slate-50/60 px-4 py-3 sm:flex-row sm:items-center"
        >
            <div class="min-w-0 flex-1"><slot name="outils" /></div>
            <p
                v-if="texteDecompte"
                class="shrink-0 text-xs font-semibold text-slate-500 sm:text-right"
                aria-live="polite"
            >
                {{ texteDecompte }}
            </p>
        </div>

        <!-- Téléphone : une carte par ligne -->
        <div v-if="!estVide && estMobile">
            <div
                v-if="colonnesTriables.length"
                class="defilement-discret flex gap-1.5 overflow-x-auto border-b border-slate-100 px-3 py-2"
            >
                <span class="shrink-0 self-center text-xs text-slate-400">Trier :</span>
                <button
                    v-for="colonne in colonnesTriables"
                    :key="colonne.cle"
                    type="button"
                    class="inline-flex min-h-9 shrink-0 items-center gap-1 rounded-full border px-3 text-xs font-medium"
                    :class="
                        tri?.colonne === colonne.cle
                            ? 'border-transparent bg-[color:var(--marque-50)] text-[color:var(--marque-700)]'
                            : 'border-slate-200 text-slate-600'
                    "
                    @click="basculerTri(colonne)"
                >
                    {{ colonne.libelle }}
                    <component :is="iconeTri(colonne)" class="h-3 w-3" />
                </button>
            </div>

            <ul class="divide-y divide-slate-100">
                <li
                    v-for="(ligne, index) in lignes"
                    :key="ligne.id ?? index"
                    class="relative px-4 py-3"
                    :class="lien ? 'cursor-pointer pr-9 transition active:bg-slate-50' : ''"
                    @click="ouvrirLaLigne($event, ligne)"
                >
                    <ChevronRight
                        v-if="lien"
                        class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-300"
                    />
                    <div class="min-w-0 text-sm">
                        <slot :name="'cellule-' + colonneTitre.cle" :ligne="ligne" :index="index">
                            <span class="font-medium text-slate-800">{{
                                ligne[colonneTitre.cle]
                            }}</span>
                        </slot>
                    </div>

                    <dl v-if="colonnesDetail.length" class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2">
                        <!-- `large: true` : une valeur longue (un nom avec sa photo) prend les deux
                             colonnes, au lieu de déborder sur sa voisine. -->
                        <div
                            v-for="colonne in colonnesDetail"
                            :key="colonne.cle"
                            class="min-w-0"
                            :class="colonne.large ? 'col-span-2' : ''"
                        >
                            <dt class="text-[11px] uppercase tracking-wide text-slate-400">
                                {{ colonne.libelle }}
                            </dt>
                            <dd class="min-w-0 break-words text-sm text-slate-700">
                                <slot
                                    :name="'cellule-' + colonne.cle"
                                    :ligne="ligne"
                                    :index="index"
                                >
                                    {{ ligne[colonne.cle] }}
                                </slot>
                            </dd>
                        </div>
                    </dl>

                    <div
                        v-if="colonnesActions.length"
                        class="mt-2 flex flex-wrap items-center justify-end gap-1"
                    >
                        <template v-for="colonne in colonnesActions" :key="colonne.cle">
                            <slot :name="'cellule-' + colonne.cle" :ligne="ligne" :index="index">
                                {{ ligne[colonne.cle] }}
                            </slot>
                        </template>
                    </div>
                </li>
            </ul>
        </div>

        <div v-else-if="!estVide" class="defilement-discret overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50/80">
                    <tr>
                        <th
                            v-for="colonne in colonnes"
                            :key="colonne.cle"
                            scope="col"
                            class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500"
                            :class="[
                                colonne.aligne === 'droite' ? 'text-right' : '',
                                colonne.aligne === 'centre' ? 'text-center' : '',
                                !colonne.aligne ? 'text-left' : '',
                            ]"
                        >
                            <button
                                v-if="colonne.triable"
                                type="button"
                                class="inline-flex items-center gap-1.5 transition hover:text-slate-800"
                                @click="basculerTri(colonne)"
                            >
                                {{ colonne.libelle }}
                                <component
                                    :is="iconeTri(colonne)"
                                    class="h-3.5 w-3.5"
                                    :class="
                                        tri?.colonne === colonne.cle
                                            ? 'text-slate-600'
                                            : 'text-slate-300'
                                    "
                                />
                            </button>
                            <span v-else>{{ colonne.libelle }}</span>
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    <tr
                        v-for="(ligne, index) in lignes"
                        :key="ligne.id ?? index"
                        class="transition hover:bg-slate-50"
                        :class="lien ? 'cursor-pointer' : ''"
                        @click="ouvrirLaLigne($event, ligne)"
                    >
                        <td
                            v-for="colonne in colonnes"
                            :key="colonne.cle"
                            class="px-4 py-3 align-middle"
                            :class="[
                                colonne.aligne === 'droite' ? 'text-right' : '',
                                colonne.aligne === 'centre' ? 'text-center' : '',
                                colonne.classe,
                            ]"
                        >
                            <!--
                                `index` sert aux listes ORDONNEES, ou la premiere et la derniere
                                ligne n'offrent pas les memes actions : desactiver la fleche
                                « monter » en tete vaut mieux qu'un clic sans effet.
                            -->
                            <slot :name="'cellule-' + colonne.cle" :ligne="ligne" :index="index">
                                {{ ligne[colonne.cle] }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Les deux états vides. Ils ne disent pas la même chose parce qu'ils n'appellent pas
             la même action. -->
        <div v-else class="px-6 py-14 text-center">
            <div
                class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
            >
                <component
                    :is="filtreActif ? SearchX : Inbox"
                    class="h-6 w-6"
                    :stroke-width="1.8"
                />
            </div>

            <p class="font-semibold text-slate-800">
                {{ filtreActif ? aucunResultatTitre : videTitre }}
            </p>
            <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">
                {{ filtreActif ? aucunResultatTexte : videTexte }}
            </p>

            <div v-if="!filtreActif && $slots.actionVide" class="mt-5">
                <slot name="actionVide" />
            </div>
        </div>

        <div v-if="$slots.pied" class="border-t border-slate-200 bg-slate-50/60 px-4 py-3">
            <slot name="pied" />
        </div>
    </div>
</template>
