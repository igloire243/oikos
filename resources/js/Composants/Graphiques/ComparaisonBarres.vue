<script setup>
import { computed } from 'vue';
import Cadre from './Cadre.vue';
import { couleurCategorie, nombre, pourcentage } from './palette.js';

/**
 * DEUX RÉPARTITIONS CÔTE À CÔTE, sur les mêmes catégories.
 *
 * ============================================================================================
 * LA QUESTION À LAQUELLE CE GRAPHIQUE RÉPOND, ET QU'AUCUN AUTRE NE POSE
 * ============================================================================================
 * « La SALLE ressemble-t-elle au REGISTRE ? » Une assemblée peut compter deux fois plus d'enfants
 * le dimanche qu'elle n'en a inscrits — ce sont les enfants du quartier, que personne n'a jamais
 * mis sur une fiche. Deux anneaux côte à côte ne le montreraient pas : l'œil compare mal deux
 * cercles, et les pourcentages masquent justement l'écart de volume.
 *
 * On compare donc des PARTS, pas des effectifs : la salle d'un dimanche et le registre entier
 * n'ont aucune raison d'avoir le même total, et superposer leurs valeurs brutes ne dirait rien.
 */
const props = defineProps({
    /** [{ libelle, valeur }] — même ordre de catégories dans les deux séries. */
    gauche: { type: Array, default: () => [] },
    droite: { type: Array, default: () => [] },
    libelleGauche: { type: String, default: 'Registre' },
    libelleDroite: { type: String, default: 'Salle' },
    titre: { type: String, default: null },
    sousTitre: { type: String, default: null },
    videTexte: { type: String, default: 'Pas encore de données à montrer.' },
});

const totalGauche = computed(() => props.gauche.reduce((s, p) => s + (p.valeur || 0), 0));
const totalDroite = computed(() => props.droite.reduce((s, p) => s + (p.valeur || 0), 0));

const lignes = computed(() =>
    props.gauche.map((point, rang) => {
        const valeurDroite = props.droite[rang]?.valeur ?? 0;

        return {
            libelle: point.libelle,
            couleur: couleurCategorie(rang),
            gauche: point.valeur ?? 0,
            droite: valeurDroite,
            partGauche: pourcentage(point.valeur ?? 0, totalGauche.value),
            partDroite: pourcentage(valeurDroite, totalDroite.value),
        };
    })
);
</script>

<template>
    <Cadre
        :titre="titre"
        :sous-titre="sousTitre"
        :vide="totalGauche === 0 && totalDroite === 0"
        :vide-texte="videTexte"
    >
        <template #actions>
            <slot name="actions" />
        </template>

        <div class="mb-3 flex items-center justify-between text-xs font-semibold text-slate-500">
            <span>{{ libelleGauche }} — {{ nombre(totalGauche) }}</span>
            <span>{{ libelleDroite }} — {{ nombre(totalDroite) }}</span>
        </div>

        <ul class="space-y-3">
            <li v-for="ligne in lignes" :key="ligne.libelle">
                <div class="mb-1 flex items-baseline justify-between gap-2 text-xs">
                    <span class="font-semibold text-slate-700">{{ ligne.partGauche }} %</span>
                    <span class="min-w-0 truncate text-slate-500">{{ ligne.libelle }}</span>
                    <span class="font-semibold text-slate-700">{{ ligne.partDroite }} %</span>
                </div>

                <div class="flex items-center gap-1">
                    <!-- La barre de gauche part de la DROITE : les deux se lisent depuis le
                         centre, ce qui met l'écart sous les yeux au lieu de le faire calculer. -->
                    <div
                        class="flex h-2 flex-1 justify-end overflow-hidden rounded-full bg-slate-100"
                    >
                        <div
                            class="h-full rounded-full"
                            :style="{
                                width: ligne.partGauche + '%',
                                backgroundColor: ligne.couleur,
                            }"
                        />
                    </div>
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                        <div
                            class="h-full rounded-full opacity-60"
                            :style="{
                                width: ligne.partDroite + '%',
                                backgroundColor: ligne.couleur,
                            }"
                        />
                    </div>
                </div>
            </li>
        </ul>
    </Cadre>
</template>
