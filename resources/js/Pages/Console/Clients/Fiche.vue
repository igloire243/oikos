<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    BellRing,
    Building2,
    Copy,
    KeyRound,
    Pencil,
    Plus,
    Power,
    Server,
} from 'lucide-vue-next';
import LayoutConsole from '@/Layouts/LayoutConsole.vue';
import EnTetePage from '@/Composants/EnTetePage.vue';
import Bouton from '@/Composants/Bouton.vue';
import Badge from '@/Composants/Badge.vue';
import Modale from '@/Composants/Modale.vue';
import ChampTexte from '@/Composants/ChampTexte.vue';
import ChampZoneTexte from '@/Composants/ChampZoneTexte.vue';

/**
 * LA FICHE D'UN CLIENT — ses installations, ce qu'elles remontent, et leurs clés d'activation.
 *
 * La fiche se remplit toute seule à l'activation, mais seulement dans ses cases VIDES : ce que
 * l'opérateur corrige ici gagne pour toujours (App\Metier\Clients\IdentiteRecue).
 */
const props = defineProps({
    client: Object,
    installations: Array,
    cle_emise: Object,
});

const TONS = { ACTIVE: 'succes', MUETTE: 'alerte', DESACTIVEE: 'ardoise', JAMAIS_ACTIVEE: 'info' };
const TONS_CLE = {
    UTILISABLE: 'marque',
    UTILISEE: 'succes',
    REVOQUEE: 'ardoise',
    EXPIREE: 'ardoise',
};

/* --- La fiche ----------------------------------------------------------------------------- */

const ficheOuverte = ref(false);
const fiche = useForm({ ...props.client });
const enregistrerFiche = () =>
    fiche.put(route('console.clients.update', props.client.id), {
        preserveScroll: true,
        onSuccess: () => (ficheOuverte.value = false),
    });

/* --- Les installations -------------------------------------------------------------------- */

const installationOuverte = ref(false);
const enEdition = ref(null);
const formInstallation = useForm({ nom: '', url: '' });
const ouvrirInstallation = (installation = null) => {
    enEdition.value = installation;
    formInstallation.clearErrors();
    formInstallation.nom = installation?.nom ?? '';
    formInstallation.url = installation?.url ?? '';
    installationOuverte.value = true;
};
const enregistrerInstallation = () => {
    const options = { preserveScroll: true, onSuccess: () => (installationOuverte.value = false) };
    enEdition.value
        ? formInstallation.put(route('console.installations.update', enEdition.value.id), options)
        : formInstallation.post(
              route('console.clients.installations.store', props.client.id),
              options
          );
};

const basculer = (installation) =>
    router.patch(
        route('console.installations.activation', installation.id),
        { active: installation.etat === 'DESACTIVEE' },
        { preserveScroll: true }
    );

const rappeler = (installation) =>
    router.post(
        route('console.installations.rappel', installation.id),
        {},
        { preserveScroll: true }
    );

/* --- Les clés ----------------------------------------------------------------------------- */

const cleOuverte = ref(null);
const formCle = useForm({ note: '' });
const emettre = () =>
    formCle.post(route('console.installations.cles.store', cleOuverte.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            cleOuverte.value = null;
            formCle.reset();
        },
    });
const revoquer = (cle) =>
    router.patch(route('console.cles.revoquer', cle.id), {}, { preserveScroll: true });

const copiee = ref(false);
const copier = async (code) => {
    try {
        await navigator.clipboard.writeText(code);
        copiee.value = true;
        setTimeout(() => (copiee.value = false), 2000);
    } catch {
        // Presse-papiers refusé (page en http://, navigateur ancien) : la clé reste affichée,
        // sélectionnable à la main.
    }
};

const lieu = computed(() => [props.client.ville, props.client.pays].filter(Boolean).join(', '));
</script>

<template>
    <LayoutConsole :titre="client.nom">
        <EnTetePage
            :titre="client.nom"
            :sous-titre="lieu || 'Lieu non renseigné'"
            :icone="Building2"
        >
            <template #actions>
                <Bouton variante="blanc" :icone="Pencil" @click="ficheOuverte = true"
                    >La fiche</Bouton
                >
                <Bouton variante="blanc" :icone="Plus" @click="ouvrirInstallation()"
                    >Une installation</Bouton
                >
            </template>
        </EnTetePage>

        <div class="mx-auto mt-6 max-w-5xl space-y-6">
            <Link
                :href="route('console.clients.index')"
                class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800"
            >
                <ArrowLeft class="h-4 w-4" /> Tous les clients
            </Link>

            <!-- LA CLÉ ÉMISE, montrée UNE fois : la base n'en garde que l'empreinte. -->
            <div
                v-if="cle_emise"
                class="apparition rounded-2xl border-2 border-[color:var(--marque-300)] bg-[color:var(--marque-50)] p-5"
            >
                <p class="text-sm font-semibold text-[color:var(--marque-800)]">
                    Clé d'activation émise — elle ne s'affichera plus.
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <code
                        class="select-all rounded-xl bg-white px-4 py-2 font-mono text-lg font-bold tracking-wider text-slate-900 shadow-sm"
                        >{{ cle_emise.code }}</code
                    >
                    <Bouton variante="contour" :icone="Copy" @click="copier(cle_emise.code)">
                        {{ copiee ? 'Copiée' : 'Copier' }}
                    </Bouton>
                </div>
                <p class="mt-2 text-xs text-slate-600">
                    À coller dans le produit, écran « Activation », ou à l'étape 4 de
                    l'installateur. Elle sert une seule fois et expire dans trente jours.
                </p>
            </div>

            <!-- Le responsable, en une ligne : la fiche complète s'ouvre à part. -->
            <div
                class="flex flex-wrap items-center gap-x-6 gap-y-1 rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm shadow-card"
            >
                <span class="text-slate-500">Responsable</span>
                <span class="font-medium text-slate-800">{{ client.contact_nom ?? '—' }}</span>
                <span class="text-slate-600">{{ client.contact_email ?? '' }}</span>
                <span class="text-slate-600">{{ client.contact_telephone ?? '' }}</span>
            </div>

            <p
                v-if="!installations.length"
                class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500"
            >
                Aucune installation. Ajoutez celle du client — le nom peut rester vide, il se
                remplira à l'activation — puis émettez-lui une clé.
            </p>

            <section
                v-for="installation in installations"
                :key="installation.id"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card"
            >
                <header class="flex flex-wrap items-start justify-between gap-3 px-5 py-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <Server class="mt-0.5 h-5 w-5 shrink-0 text-slate-400" />
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-800">
                                {{ installation.libelle }}
                            </p>
                            <p class="truncate text-xs text-slate-500">
                                {{
                                    installation.url ?? 'Adresse inconnue — pas de rappel possible'
                                }}
                            </p>
                        </div>
                    </div>
                    <Badge :ton="TONS[installation.etat]">{{ installation.libelle_etat }}</Badge>
                </header>

                <div
                    v-if="installation.catalogue_different"
                    class="mx-5 mb-3 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
                >
                    <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                    Son catalogue de modules n'est pas celui de la console : l'un des deux n'est pas
                    à jour, et un module vendu risquerait de ne rien ouvrir.
                </div>

                <dl
                    class="grid grid-cols-2 gap-x-4 gap-y-2 border-t border-slate-100 px-5 py-3 text-sm sm:grid-cols-4"
                >
                    <div class="min-w-0">
                        <dt class="text-xs text-slate-400">Activée</dt>
                        <dd class="truncate text-slate-700">
                            {{ installation.activee_le ?? '—' }}
                        </dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs text-slate-400">Dernier contact</dt>
                        <dd class="truncate text-slate-700">
                            {{ installation.vue_le ?? 'jamais' }}
                        </dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs text-slate-400">Version</dt>
                        <dd class="truncate text-slate-700">{{ installation.version ?? '—' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs text-slate-400">Empreinte</dt>
                        <dd class="truncate font-mono text-xs text-slate-700">
                            {{ installation.empreinte ?? '—' }}
                        </dd>
                    </div>
                    <div v-for="(valeur, cle) in installation.compteurs" :key="cle" class="min-w-0">
                        <dt class="text-xs capitalize text-slate-400">{{ cle }}</dt>
                        <dd class="text-slate-700">{{ valeur }}</dd>
                    </div>
                </dl>

                <div
                    class="grid grid-cols-2 gap-2 border-t border-slate-100 px-5 py-3 sm:flex sm:flex-wrap"
                >
                    <Bouton
                        compact
                        :icone="KeyRound"
                        :desactive="installation.etat === 'DESACTIVEE'"
                        @click="cleOuverte = installation"
                        >Émettre une clé</Bouton
                    >
                    <Bouton
                        variante="discret"
                        compact
                        :icone="BellRing"
                        :desactive="!installation.rappel_possible"
                        :title="
                            installation.rappel_possible
                                ? 'Lui demander de se resynchroniser maintenant'
                                : 'Elle doit d\'abord s\'être activée avec son adresse'
                        "
                        @click="rappeler(installation)"
                        >Rappeler</Bouton
                    >
                    <Bouton
                        variante="discret"
                        compact
                        :icone="Pencil"
                        @click="ouvrirInstallation(installation)"
                        >Modifier</Bouton
                    >
                    <Bouton
                        variante="discret"
                        compact
                        :icone="Power"
                        @click="basculer(installation)"
                    >
                        {{ installation.etat === 'DESACTIVEE' ? 'Réactiver' : 'Désactiver' }}
                    </Bouton>
                </div>

                <!-- L'arbre qu'elle a remonté : c'est à ces entités qu'on vendra. -->
                <div class="border-t border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-slate-700">Ses entités</h3>
                    <p v-if="!installation.arbre.length" class="mt-1 text-sm text-slate-400">
                        Rien de remonté encore. L'arbre arrive à la première synchronisation.
                    </p>
                    <ul v-else class="mt-2 space-y-2">
                        <li v-for="noeud in installation.arbre" :key="noeud.reference ?? 'autres'">
                            <p class="flex min-w-0 items-center gap-2 text-sm">
                                <Badge :ton="noeud.type === 'VISION' ? 'marque' : 'info'">{{
                                    noeud.libelle_type
                                }}</Badge>
                                <span class="truncate font-medium text-slate-800">{{
                                    noeud.nom
                                }}</span>
                            </p>
                            <ul
                                v-if="noeud.enfants.length"
                                class="ml-4 mt-1 space-y-1 border-l border-slate-200 pl-3"
                            >
                                <li
                                    v-for="enfant in noeud.enfants"
                                    :key="enfant.reference"
                                    class="flex min-w-0 items-center gap-2 text-sm"
                                >
                                    <span class="truncate text-slate-700">{{ enfant.nom }}</span>
                                    <span class="shrink-0 text-xs text-slate-400"
                                        >{{ enfant.libelle_type
                                        }}<template v-if="enfant.effectif !== null">
                                            · {{ enfant.effectif }} membres</template
                                        ></span
                                    >
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>

                <div v-if="installation.cles.length" class="border-t border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-slate-700">Ses clés d'activation</h3>
                    <ul class="mt-2 divide-y divide-slate-100">
                        <li
                            v-for="cle in installation.cles"
                            :key="cle.id"
                            class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2 text-sm"
                        >
                            <code class="font-mono text-slate-700">{{ cle.apercu }}</code>
                            <Badge :ton="TONS_CLE[cle.etat]">{{ cle.libelle_etat }}</Badge>
                            <span class="text-xs text-slate-400">
                                <template v-if="cle.utilisee_le">le {{ cle.utilisee_le }}</template>
                                <template v-else>expire le {{ cle.expire_le }}</template>
                                <template v-if="cle.emise_par"> · par {{ cle.emise_par }}</template>
                                <template v-if="cle.note"> · {{ cle.note }}</template>
                            </span>
                            <button
                                v-if="cle.etat === 'UTILISABLE'"
                                type="button"
                                class="ml-auto text-xs font-semibold text-rose-600 hover:underline"
                                @click="revoquer(cle)"
                            >
                                Révoquer
                            </button>
                        </li>
                    </ul>
                </div>
            </section>
        </div>

        <Modale :ouverte="ficheOuverte" titre="La fiche du client" @fermer="ficheOuverte = false">
            <div class="space-y-4">
                <ChampTexte
                    v-model="fiche.nom"
                    label="Nom"
                    :erreur="fiche.errors.nom"
                    obligatoire
                />
                <div class="grid gap-4 sm:grid-cols-2">
                    <ChampTexte v-model="fiche.ville" label="Ville" :erreur="fiche.errors.ville" />
                    <ChampTexte v-model="fiche.pays" label="Pays" :erreur="fiche.errors.pays" />
                    <ChampTexte
                        v-model="fiche.contact_nom"
                        label="Responsable"
                        :erreur="fiche.errors.contact_nom"
                    />
                    <ChampTexte
                        v-model="fiche.contact_telephone"
                        label="Téléphone"
                        :erreur="fiche.errors.contact_telephone"
                    />
                </div>
                <ChampTexte
                    v-model="fiche.contact_email"
                    label="Adresse électronique"
                    type="email"
                    :erreur="fiche.errors.contact_email"
                />
                <ChampZoneTexte v-model="fiche.notes" label="Notes" :erreur="fiche.errors.notes" />
                <p class="text-xs text-slate-500">
                    Ce que vous saisissez ici n'est jamais écrasé par une synchronisation : elle ne
                    remplit que les cases vides.
                </p>
            </div>
            <template #actions>
                <Bouton variante="contour" @click="ficheOuverte = false">Annuler</Bouton>
                <Bouton :desactive="fiche.processing" @click="enregistrerFiche">Enregistrer</Bouton>
            </template>
        </Modale>

        <Modale
            :ouverte="installationOuverte"
            :titre="enEdition ? 'L\'installation' : 'Nouvelle installation'"
            @fermer="installationOuverte = false"
        >
            <div class="space-y-4">
                <ChampTexte
                    v-model="formInstallation.nom"
                    label="Nom"
                    placeholder="Laissez vide : le nom de la communauté le remplira"
                    :erreur="formInstallation.errors.nom"
                />
                <ChampTexte
                    v-model="formInstallation.url"
                    label="Adresse du serveur"
                    placeholder="https://eglise-bethel.exemple.cd"
                    indication="C'est elle qui permet de rappeler l'installation dès qu'une facture est payée."
                    :erreur="formInstallation.errors.url"
                />
            </div>
            <template #actions>
                <Bouton variante="contour" @click="installationOuverte = false">Annuler</Bouton>
                <Bouton :desactive="formInstallation.processing" @click="enregistrerInstallation"
                    >Enregistrer</Bouton
                >
            </template>
        </Modale>

        <Modale
            :ouverte="!!cleOuverte"
            titre="Émettre une clé d'activation"
            :sous-titre="cleOuverte?.libelle"
            @fermer="cleOuverte = null"
        >
            <p class="text-sm text-slate-600">
                La clé s'affichera une seule fois. Elle sert une fois, expire dans trente jours, et
                se dicte au téléphone : ni O, ni I, ni L, ni 0, ni 1.
            </p>
            <div class="mt-4">
                <ChampTexte
                    v-model="formCle.note"
                    label="Note (facultative)"
                    placeholder="Envoyée par WhatsApp au pasteur, le 3 octobre"
                    :erreur="formCle.errors.note"
                />
            </div>
            <template #actions>
                <Bouton variante="contour" @click="cleOuverte = null">Annuler</Bouton>
                <Bouton :desactive="formCle.processing" :icone="KeyRound" @click="emettre"
                    >Émettre</Bouton
                >
            </template>
        </Modale>
    </LayoutConsole>
</template>
