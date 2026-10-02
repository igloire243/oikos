<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Settings } from 'lucide-vue-next';
import LayoutConsole from '@/Layouts/LayoutConsole.vue';
import EnTetePage from '@/Composants/EnTetePage.vue';
import Badge from '@/Composants/Badge.vue';
import Bouton from '@/Composants/Bouton.vue';
import ChampSelect from '@/Composants/ChampSelect.vue';
import ChampTexte from '@/Composants/ChampTexte.vue';
import Onglets from '@/Composants/Onglets.vue';

/**
 * LES RÉGLAGES — quatre familles, chacune lue par un code déjà livré : les durées de la licence, la
 * facturation, le paiement en ligne, l'identité de l'éditeur. L'écran dit OÙ chaque réglage agit, parce
 * qu'aucun n'a d'effet visible sur sa propre page : c'est la seule façon de ne pas laisser un bouton qui
 * semble ne rien commander.
 *
 * Les clés du prestataire ne reviennent JAMAIS ici : on dit si elles sont posées et d'où elles viennent
 * (cet écran ou le `.env` du serveur), jamais ce qu'elles valent.
 */
const props = defineProps({
    reglages: { type: Array, default: () => [] },
    facturation: { type: Object, required: true },
    paiement: { type: Object, required: true },
    editeur: { type: Object, required: true },
});

const onglet = ref('licence');
const onglets = [
    { valeur: 'licence', libelle: 'Licence' },
    { valeur: 'facturation', libelle: 'Facturation' },
    { valeur: 'paiement', libelle: 'Paiement en ligne' },
    { valeur: 'editeur', libelle: 'Éditeur' },
];

/* --- Licence : les quatre durées ---------------------------------------------- */
const formDurees = useForm(Object.fromEntries(props.reglages.map((r) => [r.cle, String(r.valeur)])));
const enregistrerLesDurees = () => formDurees.put(route('console.reglages.update'), { preserveScroll: true });

/* --- Facturation ------------------------------------------------------------- */
const formFacturation = useForm({ echeance_jours: String(props.facturation.echeance_jours) });
const enregistrerLaFacturation = () => formFacturation.put(route('console.reglages.autres'), { preserveScroll: true });

/* --- Paiement en ligne ------------------------------------------------------- */
const formPaiement = useForm({
    paiement_en_ligne: props.paiement.actif,
    passerelle: props.paiement.passerelle,
    flutterwave_cle_secrete: '',
    flutterwave_hash: '',
    flutterwave_cle_secrete_effacer: false,
    flutterwave_hash_effacer: false,
});
const enregistrerLePaiement = () =>
    formPaiement.put(route('console.reglages.autres'), {
        preserveScroll: true,
        onSuccess: () => formPaiement.reset('flutterwave_cle_secrete', 'flutterwave_hash', 'flutterwave_cle_secrete_effacer', 'flutterwave_hash_effacer'),
    });
const testerLaCle = useForm({});
const tester = () => testerLaCle.post(route('console.reglages.paiement.test'), { preserveScroll: true });

const copier = async (texte) => {
    try {
        await navigator.clipboard.writeText(texte);
    } catch {
        window.prompt('Adresse à copier :', texte);
    }
};

const etat = (secret) => (secret.posee ? (secret.origine === 'ecran' ? 'posée (cet écran)' : 'posée (.env du serveur)') : 'absente');

/* --- Éditeur ----------------------------------------------------------------- */
const formEditeur = useForm({
    editeur_nom: props.editeur.nom ?? '',
    editeur_email: props.editeur.email ?? '',
    editeur_telephone: props.editeur.telephone ?? '',
    editeur_adresse: props.editeur.adresse ?? '',
});
const enregistrerLEditeur = () => formEditeur.put(route('console.reglages.autres'), { preserveScroll: true });
</script>

<template>
    <LayoutConsole titre="Réglages">
        <EnTetePage
            titre="Réglages"
            sous-titre="La licence, la facturation, le paiement en ligne et l'identité de l'éditeur"
            :icone="Settings"
        />

        <!-- `min-w-0` + `w-full` : un enfant de flex ou de grille ne rétrécit pas sous son contenu, et une liste déroulante aux libellés longs élargissait toute la page sur téléphone. -->
        <div class="mx-auto w-full min-w-0 max-w-2xl space-y-4 px-4 py-5 sm:px-0">
            <Onglets v-model="onglet" :options="onglets" />

            <!-- ============================== LICENCE -->
            <template v-if="onglet === 'licence'">
                <div
                    v-for="r in reglages"
                    :key="r.cle"
                    class="rounded-2xl bg-white p-5 shadow-card ring-1 ring-slate-100"
                >
                    <ChampTexte
                        v-model="formDurees[r.cle]"
                        type="number"
                        :label="`${r.libelle} (jours)`"
                        :indication="r.effet"
                        :erreur="formDurees.errors[r.cle]"
                    />
                    <p class="mt-2 text-xs text-slate-400">
                        Entre {{ r.min }} et {{ r.max }} jours · valeur de départ {{ r.defaut }}
                    </p>
                </div>

                <p class="text-xs text-slate-500">
                    Un changement vaut pour les installations à leur prochaine synchronisation ou au prochain
                    rappel, et il est écrit au journal. Le renommage d'une offre ou un prix se règle sur
                    l'écran « Offres ».
                </p>
                <Bouton :desactive="formDurees.processing" @click="enregistrerLesDurees">Enregistrer</Bouton>
            </template>

            <!-- ============================== FACTURATION -->
            <template v-else-if="onglet === 'facturation'">
                <div class="rounded-2xl bg-white p-5 shadow-card ring-1 ring-slate-100">
                    <ChampTexte
                        v-model="formFacturation.echeance_jours"
                        type="number"
                        label="Délai de paiement d'une facture (jours)"
                        :indication="facturation.lu_par"
                        :erreur="formFacturation.errors.echeance_jours"
                    />
                    <p class="mt-2 text-xs text-slate-400">Entre {{ facturation.min }} et {{ facturation.max }} jours.</p>
                </div>
                <p class="text-xs text-slate-500">
                    Ne vaut que pour les factures émises <strong>après</strong> le changement : une facture déjà
                    émise garde son échéance. Une facture en retard se signale, elle ne coupe aucun accès.
                </p>
                <Bouton :desactive="formFacturation.processing" @click="enregistrerLaFacturation">Enregistrer</Bouton>
            </template>

            <!-- ============================== PAIEMENT EN LIGNE -->
            <template v-else-if="onglet === 'paiement'">
                <div class="rounded-2xl bg-white p-5 shadow-card ring-1 ring-slate-100">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-slate-800">Paiement en ligne</h2>
                        <Badge :ton="paiement.actif ? 'succes' : 'ardoise'">{{ paiement.actif ? 'allumé' : 'éteint' }}</Badge>
                    </div>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">
                        Allumé, chaque facture a une adresse que le client ouvre pour payer chez le prestataire ;
                        la facture se solde quand celui-ci confirme. Éteint, les pages <code class="break-all">/payer</code> répondent
                        « introuvable » et aucun lien n'est proposé.
                    </p>

                    <label class="mt-3 flex items-start gap-2 text-sm text-slate-700">
                        <input v-model="formPaiement.paiement_en_ligne" type="checkbox" class="mt-1 h-4 w-4" />
                        <span><strong>Allumer le paiement en ligne</strong></span>
                    </label>

                    <div class="mt-3 min-w-0">
                        <ChampSelect
                            v-model="formPaiement.passerelle"
                            label="Prestataire"
                            :options="paiement.choix"
                            :erreur="formPaiement.errors.passerelle"
                        />
                        <p v-if="formPaiement.passerelle === 'simulee' && paiement.production" class="mt-1 text-xs text-rose-600">
                            Le prestataire simulé est refusé en production : choisissez Flutterwave.
                        </p>
                    </div>
                </div>

                <div v-if="formPaiement.passerelle === 'flutterwave'" class="space-y-4 rounded-2xl bg-white p-5 shadow-card ring-1 ring-slate-100">
                    <h2 class="text-sm font-semibold text-slate-800">Clés Flutterwave</h2>
                    <p class="text-xs leading-relaxed text-slate-500">
                        À copier depuis votre tableau de bord Flutterwave (Paramètres → API). Commencez par les clés
                        <strong>de test</strong>. Elles sont enregistrées chiffrées et ne s'affichent plus jamais ;
                        laisser un champ vide <strong>ne change rien</strong>.
                    </p>

                    <div>
                        <ChampTexte
                            v-model="formPaiement.flutterwave_cle_secrete"
                            type="password"
                            label="Clé secrète"
                            placeholder="FLWSECK_TEST-…"
                            :erreur="formPaiement.errors.flutterwave_cle_secrete"
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            État : <Badge :ton="paiement.cle.posee ? 'succes' : 'alerte'">{{ etat(paiement.cle) }}</Badge>
                        </p>
                        <label v-if="paiement.cle.origine === 'ecran'" class="mt-1 flex items-center gap-2 text-xs text-slate-500">
                            <input v-model="formPaiement.flutterwave_cle_secrete_effacer" type="checkbox" class="h-4 w-4" />
                            Effacer la clé enregistrée
                        </label>
                    </div>

                    <div>
                        <ChampTexte
                            v-model="formPaiement.flutterwave_hash"
                            type="password"
                            label="Secret de notification"
                            indication="Un mot secret que vous inventez ; le même doit être saisi chez Flutterwave."
                            :erreur="formPaiement.errors.flutterwave_hash"
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            État : <Badge :ton="paiement.hash.posee ? 'succes' : 'alerte'">{{ etat(paiement.hash) }}</Badge>
                        </p>
                        <label v-if="paiement.hash.origine === 'ecran'" class="mt-1 flex items-center gap-2 text-xs text-slate-500">
                            <input v-model="formPaiement.flutterwave_hash_effacer" type="checkbox" class="h-4 w-4" />
                            Effacer le secret enregistré
                        </label>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs font-medium text-slate-700">Adresse de notification à donner à Flutterwave</p>
                        <p class="mt-1 break-all font-mono text-xs text-slate-600">{{ paiement.url_notification }}</p>
                        <button type="button" class="mt-1 text-xs font-medium text-slate-700 underline" @click="copier(paiement.url_notification)">
                            Copier l'adresse
                        </button>
                        <p v-if="!paiement.https" class="mt-2 text-xs text-amber-700">
                            Cette console n'est pas servie en https : Flutterwave n'appellera pas cette adresse. Il
                            faut un domaine en https (ou un tunnel) pour recevoir les confirmations.
                        </p>
                    </div>
                </div>

                <p class="text-xs text-slate-500">
                    Les clés déjà posées dans le <code>.env</code> du serveur continuent de servir tant que rien
                    n'est enregistré ici. Cet écran n'a pas été essayé contre le service réel de Flutterwave.
                </p>

                <div class="flex flex-wrap gap-2">
                    <Bouton :desactive="formPaiement.processing" @click="enregistrerLePaiement">Enregistrer</Bouton>
                    <Bouton
                        v-if="paiement.passerelle === 'flutterwave'"
                        variante="contour"
                        :desactive="testerLaCle.processing"
                        @click="tester"
                    >
                        Essayer la clé enregistrée
                    </Bouton>
                </div>
            </template>

            <!-- ============================== ÉDITEUR -->
            <template v-else>
                <div class="space-y-4 rounded-2xl bg-white p-5 shadow-card ring-1 ring-slate-100">
                    <p class="text-xs leading-relaxed text-slate-500">
                        Ce qu'un client voit pour vous joindre : le pied du site commercial et la page de paiement.
                        Un champ vide n'affiche rien.
                    </p>
                    <ChampTexte v-model="formEditeur.editeur_nom" label="Nom de l'éditeur" :erreur="formEditeur.errors.editeur_nom" />
                    <ChampTexte v-model="formEditeur.editeur_email" type="email" label="E-mail de contact" :erreur="formEditeur.errors.editeur_email" />
                    <ChampTexte v-model="formEditeur.editeur_telephone" label="Téléphone de contact" placeholder="+243 …" :erreur="formEditeur.errors.editeur_telephone" />
                    <ChampTexte v-model="formEditeur.editeur_adresse" label="Adresse" :erreur="formEditeur.errors.editeur_adresse" />
                </div>
                <Bouton :desactive="formEditeur.processing" @click="enregistrerLEditeur">Enregistrer</Bouton>
            </template>
        </div>
    </LayoutConsole>
</template>
