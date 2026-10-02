<script setup>
import { useForm } from '@inertiajs/vue3';
import { Settings } from 'lucide-vue-next';
import LayoutConsole from '@/Layouts/LayoutConsole.vue';
import EnTetePage from '@/Composants/EnTetePage.vue';
import Bouton from '@/Composants/Bouton.vue';
import ChampTexte from '@/Composants/ChampTexte.vue';

/**
 * LES RÉGLAGES — quatre durées, chacune lue par une règle de licence déjà livrée. L'écran dit OÙ
 * chaque réglage agit, parce qu'aucun n'a d'effet visible sur sa propre page : c'est la seule façon
 * de ne pas laisser un bouton qui semble ne rien commander.
 */
const props = defineProps({
    reglages: Array,
});

const form = useForm(Object.fromEntries(props.reglages.map((r) => [r.cle, String(r.valeur)])));

const enregistrer = () => form.put(route('console.reglages.update'), { preserveScroll: true });
</script>

<template>
    <LayoutConsole titre="Réglages">
        <EnTetePage
            titre="Réglages"
            sous-titre="Les durées qui commandent la licence servie aux installations"
            :icone="Settings"
        />

        <div class="mx-auto max-w-2xl space-y-4 px-4 py-5 sm:px-6">
            <div
                v-for="r in reglages"
                :key="r.cle"
                class="rounded-2xl bg-white p-5 shadow-card ring-1 ring-slate-100"
            >
                <ChampTexte
                    v-model="form[r.cle]"
                    type="number"
                    :label="`${r.libelle} (jours)`"
                    :indication="r.effet"
                    :erreur="form.errors[r.cle]"
                />
                <p class="mt-2 text-xs text-slate-400">
                    Entre {{ r.min }} et {{ r.max }} jours · valeur de départ {{ r.defaut }}
                </p>
            </div>

            <p class="text-xs text-slate-500">
                Un changement vaut pour les installations à leur prochaine synchronisation ou au prochain
                rappel, et il est écrit au journal. Ce qui n'est pas ici — le renommage d'une offre, un prix —
                se règle sur l'écran « Offres ».
            </p>

            <Bouton :desactive="form.processing" @click="enregistrer">Enregistrer</Bouton>
        </div>
    </LayoutConsole>
</template>
