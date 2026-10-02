<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Banknote, ChevronDown, Receipt, RotateCcw, Search, Undo2 } from 'lucide-vue-next';
import LayoutConsole from '@/Layouts/LayoutConsole.vue';
import EnTetePage from '@/Composants/EnTetePage.vue';
import Bouton from '@/Composants/Bouton.vue';
import Badge from '@/Composants/Badge.vue';
import Modale from '@/Composants/Modale.vue';
import ChampTexte from '@/Composants/ChampTexte.vue';
import ChampSelect from '@/Composants/ChampSelect.vue';
import FiltreBoutons from '@/Composants/FiltreBoutons.vue';

/**
 * FACTURES ET ENCAISSEMENTS — ce qui est dû, ce qui est arrivé, ce qui est en retard.
 *
 * Une carte par facture, et non un tableau : sur téléphone, un tableau de huit colonnes se lit mal,
 * et ce qu'on y cherche — « qui me doit quoi, depuis quand » — tient en trois lignes. Les versements
 * se dépliant sous la carte, « non reçu » et « rétablir » restent à portée du pouce.
 */
const props = defineProps({
    factures: Array,
    tronquee: Boolean,
    comptes: Object,
    a_recevoir: Array,
    filtres: Object,
    moyens: Array,
});

const recherche = ref(props.filtres.recherche ?? '');
const etat = ref(props.filtres.etat ?? 'tous');

const OPTIONS = [
    { valeur: 'tous', libelle: 'Toutes' },
    { valeur: 'impayees', libelle: 'En attente' },
    { valeur: 'partielles', libelle: 'Partielles' },
    { valeur: 'retard', libelle: 'En retard' },
    { valeur: 'soldees', libelle: 'Soldées' },
];

const options = () => OPTIONS.map((o) => ({ ...o, compte: props.comptes[o.valeur] }));

const filtrer = () =>
    router.get(
        route('console.factures.index'),
        {
            etat: etat.value === 'tous' ? undefined : etat.value,
            recherche: recherche.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );

const choisirEtat = (valeur) => {
    etat.value = valeur;
    filtrer();
};

const ton = (f) => (f.etat === 'SOLDEE' ? 'succes' : f.en_retard ? 'danger' : f.etat === 'PARTIELLE' ? 'info' : 'alerte');

/* --- Les versements d'une facture, dépliés à la demande ------------------------------------- */

const depliees = ref(new Set());
const basculer = (id) => {
    const suite = new Set(depliees.value);
    suite.has(id) ? suite.delete(id) : suite.add(id);
    depliees.value = suite;
};

/* --- Encaisser ------------------------------------------------------------------------------ */

const cible = ref(null);
const aujourdhui = () => new Date().toISOString().slice(0, 10);
const form = useForm({ montant: '', moyen: 'ESPECES', reference: '', recu_le: aujourdhui(), notes: '' });

const encaisser = (facture) => {
    cible.value = facture;
    form.reset();
    form.clearErrors();
    form.montant = String(facture.restant_saisie).replace('.', ',');
    form.recu_le = aujourdhui();
};

const enregistrer = () =>
    form.post(route('console.factures.encaisser', cible.value.id), {
        preserveScroll: true,
        onSuccess: () => (cible.value = null),
    });

/* --- « Non reçu » et « rétablir » ----------------------------------------------------------- */

const paiementVise = ref(null);
const formNonRecu = useForm({ motif: '' });

const marquerNonRecu = (paiement) => {
    paiementVise.value = paiement;
    formNonRecu.reset();
    formNonRecu.clearErrors();
};

const confirmerNonRecu = () =>
    formNonRecu.patch(route('console.paiements.non_recu', paiementVise.value.id), {
        preserveScroll: true,
        onSuccess: () => (paiementVise.value = null),
    });

const retablir = (paiement) =>
    router.patch(route('console.paiements.retablir', paiement.id), {}, { preserveScroll: true });
</script>

<template>
    <LayoutConsole titre="Factures et encaissements">
        <EnTetePage
            titre="Factures et encaissements"
            sous-titre="Une facture par période vendue, soldée par un ou plusieurs versements"
            :icone="Receipt"
        />

        <div class="mx-auto max-w-5xl space-y-4 px-4 py-5 sm:px-6">
            <!-- Ce qui reste à recevoir : une ligne par devise, jamais un total qui les mélange. -->
            <div v-if="a_recevoir.length" class="rounded-2xl bg-white p-4 shadow-card ring-1 ring-slate-100">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reste à recevoir</p>
                <p class="mt-1 flex flex-wrap gap-x-6 gap-y-1 text-xl font-bold text-slate-800">
                    <span v-for="ligne in a_recevoir" :key="ligne">{{ ligne }}</span>
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative min-w-0 flex-1">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="recherche"
                        type="search"
                        placeholder="Numéro, client ou église…"
                        class="w-full rounded-xl border-slate-300 pl-9 text-base shadow-sm sm:text-sm"
                        @keyup.enter="filtrer"
                        @search="filtrer"
                    />
                </div>
                <FiltreBoutons :options="options()" :model-value="etat" @update:model-value="choisirEtat" />
            </div>

            <div
                v-if="!factures.length"
                class="rounded-2xl bg-white p-10 text-center shadow-card ring-1 ring-slate-100"
            >
                <Receipt class="mx-auto h-8 w-8 text-slate-300" />
                <p class="mt-3 font-semibold text-slate-700">
                    {{ filtres.etat || filtres.recherche ? 'Aucune facture ne correspond' : 'Aucune facture' }}
                </p>
                <p class="mt-1 text-sm text-slate-500">
                    {{
                        filtres.etat || filtres.recherche
                            ? 'Élargissez le filtre ou la recherche.'
                            : 'Une facture naît de chaque vente d\'une licence ou d\'un accès.'
                    }}
                </p>
            </div>

            <article
                v-for="f in factures"
                :key="f.id"
                class="min-w-0 rounded-2xl bg-white p-4 shadow-card ring-1 ring-slate-100"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-mono text-xs font-semibold text-slate-500">{{ f.numero }}</p>
                        <Link
                            :href="route('console.clients.show', f.client_id)"
                            class="block truncate font-semibold text-slate-800 hover:underline"
                        >
                            {{ f.client }}
                        </Link>
                        <p class="truncate text-sm text-slate-500">{{ f.entite }} · {{ f.offre }}</p>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <Badge :ton="ton(f)">{{ f.etat_libelle }}</Badge>
                        <Badge v-if="f.en_retard" ton="danger">En retard</Badge>
                    </div>
                </div>

                <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
                    <div class="min-w-0">
                        <dt class="text-xs text-slate-400">Montant</dt>
                        <dd class="truncate font-semibold text-slate-800">{{ f.montant }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs text-slate-400">Reçu</dt>
                        <dd class="truncate font-semibold text-emerald-700">{{ f.recu }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs text-slate-400">Reste</dt>
                        <dd class="truncate font-semibold" :class="f.en_retard ? 'text-rose-700' : 'text-slate-800'">
                            {{ f.restant }}
                        </dd>
                    </div>
                </dl>

                <p class="mt-2 text-xs text-slate-500">
                    Période {{ f.periode }} · émise {{ f.emise_le }} ·
                    <span :class="f.en_retard ? 'font-semibold text-rose-600' : ''">échéance {{ f.echeance_le }}</span>
                </p>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <Bouton v-if="f.etat !== 'SOLDEE'" :icone="Banknote" compact @click="encaisser(f)">
                        Encaisser
                    </Bouton>
                    <button
                        v-if="f.paiements.length"
                        type="button"
                        class="inline-flex min-h-11 items-center gap-1 rounded-xl px-3 text-sm font-medium text-slate-600 hover:bg-slate-100"
                        @click="basculer(f.id)"
                    >
                        {{ f.paiements.length }} versement{{ f.paiements.length > 1 ? 's' : '' }}
                        <ChevronDown class="h-4 w-4 transition" :class="depliees.has(f.id) ? 'rotate-180' : ''" />
                    </button>
                </div>

                <ul v-if="depliees.has(f.id)" class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200">
                    <li v-for="p in f.paiements" :key="p.id" class="flex flex-wrap items-center justify-between gap-2 p-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold" :class="p.recu ? 'text-slate-800' : 'text-slate-400 line-through'">
                                {{ p.montant }}
                                <span class="font-normal text-slate-500">· {{ p.moyen }}</span>
                            </p>
                            <p class="truncate text-xs text-slate-500">
                                {{ p.recu_le }}<template v-if="p.reference"> · réf. {{ p.reference }}</template
                                ><template v-if="p.par"> · saisi par {{ p.par }}</template>
                            </p>
                            <p v-if="!p.recu" class="text-xs text-rose-600">Non reçu : {{ p.motif_non_recu }}</p>
                        </div>
                        <button
                            v-if="p.recu"
                            type="button"
                            class="inline-flex min-h-11 items-center gap-1 rounded-xl px-3 text-xs font-semibold text-rose-600 hover:bg-rose-50"
                            @click="marquerNonRecu(p)"
                        >
                            <Undo2 class="h-4 w-4" /> Non reçu
                        </button>
                        <button
                            v-else
                            type="button"
                            class="inline-flex min-h-11 items-center gap-1 rounded-xl px-3 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                            @click="retablir(p)"
                        >
                            <RotateCcw class="h-4 w-4" /> Rétablir
                        </button>
                    </li>
                </ul>
            </article>

            <p v-if="tronquee" class="text-center text-xs text-slate-500">
                Les 100 plus récentes sont affichées : affinez la recherche pour retrouver une ancienne facture.
            </p>
        </div>

        <Modale
            :ouverte="cible !== null"
            :titre="cible ? `Encaisser — ${cible.numero}` : ''"
            :sous-titre="cible ? `${cible.client} · reste ${cible.restant}` : null"
            @fermer="cible = null"
        >
            <div class="space-y-4">
                <ChampTexte
                    v-model="form.montant"
                    label="Montant reçu"
                    :indication="cible ? `Au plus ${cible.restant} : un trop-perçu ne s'enregistre pas.` : null"
                    :erreur="form.errors.montant"
                    obligatoire
                />
                <ChampSelect v-model="form.moyen" label="Moyen de paiement" :options="moyens" :erreur="form.errors.moyen" />
                <ChampTexte
                    v-model="form.reference"
                    label="Référence"
                    placeholder="N° de transaction, de virement…"
                    indication="Facultative, mais unique : la même référence ne s'encaisse pas deux fois."
                    :erreur="form.errors.reference"
                />
                <ChampTexte v-model="form.recu_le" type="date" label="Reçu le" :erreur="form.errors.recu_le" obligatoire />
                <ChampTexte v-model="form.notes" label="Note" :erreur="form.errors.notes" />
            </div>
            <template #actions>
                <Bouton variante="contour" @click="cible = null">Annuler</Bouton>
                <Bouton :desactive="form.processing" @click="enregistrer">Enregistrer le versement</Bouton>
            </template>
        </Modale>

        <Modale
            :ouverte="paiementVise !== null"
            titre="Marquer ce versement « non reçu »"
            sous-titre="Il ne compte plus, mais reste visible : on ne l'efface pas."
            @fermer="paiementVise = null"
        >
            <ChampTexte
                v-model="formNonRecu.motif"
                label="Pourquoi ?"
                placeholder="Chèque sans provision, virement jamais arrivé…"
                :erreur="formNonRecu.errors.motif"
                obligatoire
            />
            <template #actions>
                <Bouton variante="contour" @click="paiementVise = null">Annuler</Bouton>
                <Bouton variante="danger" :desactive="formNonRecu.processing" @click="confirmerNonRecu">
                    Marquer non reçu
                </Bouton>
            </template>
        </Modale>
    </LayoutConsole>
</template>
