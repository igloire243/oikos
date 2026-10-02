<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Check, Inbox, Mail, Phone } from 'lucide-vue-next';
import LayoutConsole from '@/Layouts/LayoutConsole.vue';
import EnTetePage from '@/Composants/EnTetePage.vue';
import Bouton from '@/Composants/Bouton.vue';
import Badge from '@/Composants/Badge.vue';
import Modale from '@/Composants/Modale.vue';
import ChampTexte from '@/Composants/ChampTexte.vue';
import FiltreBoutons from '@/Composants/FiltreBoutons.vue';
import Pagination from '@/Composants/Pagination.vue';

/**
 * LES DEMANDES DU SITE COMMERCIAL — les plus anciennes d'abord : c'est celle qu'on a laissée attendre
 * le plus longtemps qu'on doit voir en premier. Le courriel et le téléphone sont des liens nus,
 * pour répondre d'un geste depuis le téléphone.
 */
const props = defineProps({
    demandes: Object,
    etat: String,
    comptes: Object,
});

const options = () => [
    { valeur: 'a_traiter', libelle: 'À traiter', compte: props.comptes.a_traiter },
    { valeur: 'traitees', libelle: 'Traitées', compte: props.comptes.traitees },
];

const choisir = (valeur) => router.get(route('console.demandes.index'), { etat: valeur }, { preserveState: true, replace: true });

const cible = ref(null);
const form = useForm({ note: '' });

const ouvrir = (demande) => {
    cible.value = demande;
    form.reset();
    form.clearErrors();
};

const traiter = () =>
    form.patch(route('console.demandes.traiter', cible.value.id), {
        preserveScroll: true,
        onSuccess: () => (cible.value = null),
    });
</script>

<template>
    <LayoutConsole titre="Demandes de contact">
        <EnTetePage
            titre="Demandes de contact"
            sous-titre="Ce que les visiteurs du site commercial ont demandé"
            :icone="Inbox"
        />

        <div class="mx-auto max-w-4xl space-y-4 py-5">
            <FiltreBoutons :options="options()" :model-value="etat" @update:model-value="choisir" />

            <div
                v-if="!demandes.data.length"
                class="rounded-2xl bg-white p-10 text-center shadow-card ring-1 ring-slate-100"
            >
                <Inbox class="mx-auto h-8 w-8 text-slate-300" />
                <p class="mt-3 font-semibold text-slate-700">
                    {{ etat === 'a_traiter' ? 'Tout est traité' : 'Aucune demande traitée' }}
                </p>
                <p class="mt-1 text-sm text-slate-500">
                    Le formulaire public est à l'adresse <code>/demande</code>.
                </p>
            </div>

            <article
                v-for="d in demandes.data"
                :key="d.id"
                class="min-w-0 rounded-2xl bg-white p-4 shadow-card ring-1 ring-slate-100"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-800">{{ d.nom }}</p>
                        <p v-if="d.organisation || d.pays" class="truncate text-sm text-slate-500">
                            {{ [d.organisation, d.pays].filter(Boolean).join(' · ') }}
                        </p>
                    </div>
                    <Badge :ton="d.traitee ? 'succes' : 'alerte'">{{ d.traitee ? 'Traitée' : 'À traiter' }}</Badge>
                </div>

                <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ d.message }}</p>
                <p class="mt-2 text-xs text-slate-400">Reçue {{ d.recue_le }}</p>

                <div class="mt-3 flex flex-wrap gap-2">
                    <a
                        :href="`mailto:${d.email}`"
                        class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-slate-300 px-3 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        <Mail class="h-4 w-4" /> {{ d.email }}
                    </a>
                    <a
                        v-if="d.telephone"
                        :href="`tel:${d.telephone}`"
                        class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-slate-300 px-3 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        <Phone class="h-4 w-4" /> {{ d.telephone }}
                    </a>
                    <Bouton v-if="!d.traitee" :icone="Check" compact @click="ouvrir(d)">Marquer traitée</Bouton>
                </div>

                <p v-if="d.traitee" class="mt-3 text-xs text-slate-500">
                    Traitée le {{ d.traitee_le }}<template v-if="d.traitee_par"> par {{ d.traitee_par }}</template
                    ><template v-if="d.note"> — {{ d.note }}</template>
                </p>
            </article>

            <Pagination :paginateur="demandes" libelle="demandes" />
        </div>

        <Modale
            :ouverte="cible !== null"
            :titre="cible ? `Traiter la demande de ${cible.nom}` : ''"
            sous-titre="Elle reste lisible dans « Traitées » : on ne la supprime pas."
            @fermer="cible = null"
        >
            <ChampTexte
                v-model="form.note"
                label="Note"
                placeholder="Rappelé le 3, démonstration prévue jeudi…"
                indication="Facultative : ce qui a été fait, pour ceux qui reprendront le dossier."
                :erreur="form.errors.note"
            />
            <template #actions>
                <Bouton variante="contour" @click="cible = null">Annuler</Bouton>
                <Bouton :desactive="form.processing" @click="traiter">Marquer traitée</Bouton>
            </template>
        </Modale>
    </LayoutConsole>
</template>
