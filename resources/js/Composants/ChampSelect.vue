<script setup>
import { computed } from 'vue';
import Champ from '@/Composants/Champ.vue';
import { icone as resoudreIcone } from '@/Composants/icones.js';

const props = defineProps({
    label: { type: String, required: true },
    modelValue: { type: [String, Number, null], default: null },
    // [{ valeur, libelle, groupe? }] — `groupe` range les options sous un intitulé (<optgroup>),
    // dans l'ordre où les groupes apparaissent.
    options: { type: Array, required: true },
    /**
     * Ce que dit la première ligne quand rien n'est choisi.
     *
     * « — » ne veut rien dire : on écrit ce que l'absence de choix SIGNIFIE (« Aucun — culte
     * exceptionnel »), parce que c'est souvent une option à part entière et non un oubli.
     */
    videLibelle: { type: String, default: 'Choisir…' },
    erreur: { type: String, default: null },
    indication: { type: String, default: null },
    obligatoire: { type: Boolean, default: false },
    icone: { type: String, default: null },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

/**
 * ON RENVOIE LA VALEUR D'ORIGINE, PAS LA CHAINE DU DOM.
 *
 * `$event.target.value` est toujours une chaine, meme quand l'option porte un entier. Une page
 * qui compare `option.valeur === modelValue` — pour afficher une aide, une alerte, un detail —
 * echouait alors en silence sur `28 !== '28'`, sans la moindre erreur : le formulaire partait
 * juste, puisque la validation PHP recoit la chaine et la convertit.
 */
const choisir = (evenement) => {
    const brut = evenement.target.value;

    emit(
        'update:modelValue',
        brut === '' ? null : (props.options.find((o) => String(o.valeur) === brut)?.valeur ?? brut)
    );
};

const groupes = computed(() => {
    const liste = [];
    for (const option of props.options) {
        let groupe = liste.find((g) => g.nom === (option.groupe ?? null));
        if (!groupe) {
            groupe = { nom: option.groupe ?? null, options: [] };
            liste.push(groupe);
        }
        groupe.options.push(option);
    }
    return liste;
});

const composantIcone = computed(() => (props.icone ? resoudreIcone(props.icone) : null));
</script>

<template>
    <Champ
        :label="label"
        :pour="id"
        :erreur="erreur"
        :indication="indication"
        :obligatoire="obligatoire"
    >
        <div class="relative">
            <component
                :is="composantIcone"
                v-if="composantIcone"
                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                :stroke-width="1.8"
            />
            <select
                :id="id"
                :value="modelValue ?? ''"
                class="w-full rounded-xl border-slate-300 text-sm shadow-sm transition focus:ring-2"
                :class="[
                    erreur ? 'border-rose-300 focus:border-rose-400' : 'focus:border-slate-400',
                    composantIcone ? 'pl-9' : '',
                    modelValue === null || modelValue === '' ? 'text-slate-400' : '',
                ]"
                :style="{ '--tw-ring-color': 'var(--marque-200)' }"
                @change="choisir($event)"
            >
                <!-- Valeur '' et non null : Vue pose `select.value = ''` quand le modèle est nul,
                     et une option sans valeur n'y répond pas — le champ s'affichait VIDE, sans
                     même « Tout le réseau » (vu sur iPhone, centre d'export). `choisir()` rend
                     déjà null pour ''. -->
                <option value="" class="text-slate-400">{{ videLibelle }}</option>
                <template v-for="groupe in groupes" :key="groupe.nom ?? '—'">
                    <optgroup v-if="groupe.nom" :label="groupe.nom">
                        <option
                            v-for="option in groupe.options"
                            :key="option.valeur"
                            :value="option.valeur"
                            class="text-slate-800"
                        >
                            {{ option.libelle }}
                        </option>
                    </optgroup>
                    <template v-else>
                        <option
                            v-for="option in groupe.options"
                            :key="option.valeur"
                            :value="option.valeur"
                            class="text-slate-800"
                        >
                            {{ option.libelle }}
                        </option>
                    </template>
                </template>
            </select>
        </div>
    </Champ>
</template>
