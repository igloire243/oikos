<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, Pencil, Plus, Tags, Trash2 } from 'lucide-vue-next';
import LayoutConsole from '@/Layouts/LayoutConsole.vue';
import EnTetePage from '@/Composants/EnTetePage.vue';
import Bouton from '@/Composants/Bouton.vue';
import Badge from '@/Composants/Badge.vue';
import Modale from '@/Composants/Modale.vue';
import ChampTexte from '@/Composants/ChampTexte.vue';
import ChampZoneTexte from '@/Composants/ChampZoneTexte.vue';
import ChampSelect from '@/Composants/ChampSelect.vue';

/**
 * LES OFFRES — ce qu'on vend, rangé comme on le vend : la licence de la Vision d'abord, puis les
 * accès des antennes et des églises.
 *
 * Le formulaire ne propose que les modules VENDABLES des espaces de l'offre — et « tous les
 * modules », qui n'est pas la liste d'aujourd'hui cochée en entier : c'est aussi ceux qu'une mise
 * à jour du produit ajoutera.
 */
const props = defineProps({
    offres: Array,
    modules_par_niveau: Object,
    paliers: Array,
});

const SECTIONS = [
    {
        titre: 'Licences de la Vision',
        sous: 'Annuelles. Elles mettent le système en service.',
        filtre: (o) => o.nature === 'LICENCE',
    },
    {
        titre: 'Accès des églises',
        sous: "Mensuels. Ils ouvrent l'espace de l'église et de ses départements.",
        filtre: (o) => o.nature === 'ACCES' && o.niveau === 'EXTENSION',
    },
    {
        titre: 'Accès des antennes',
        sous: "Mensuels. Ils ouvrent l'espace de supervision.",
        filtre: (o) => o.nature === 'ACCES' && o.niveau === 'ANTENNE',
    },
];

const NIVEAUX = [
    { valeur: 'VISION', libelle: 'Licence — la Vision' },
    { valeur: 'EXTENSION', libelle: 'Accès — une église' },
    { valeur: 'ANTENNE', libelle: 'Accès — une antenne' },
];

/* --- Le formulaire -------------------------------------------------------------------------- */

const ouverte = ref(false);
const enEdition = ref(null);
const vide = () => ({
    code: '',
    nature: 'ACCES',
    niveau: 'EXTENSION',
    palier: 'STARTER',
    nom: '',
    argumentaire: '',
    periode_mois: 1,
    prix_usd: '',
    prix_cdf: '',
    plafond_acces: null,
    paliers_taille: [],
    tous_les_modules: false,
    modules: [],
    publique: true,
    ordre: 50,
});
const form = useForm(vide());

const ouvrir = (offre = null) => {
    enEdition.value = offre;
    form.clearErrors();
    if (!offre) {
        Object.assign(form, vide());
    } else {
        Object.assign(form, {
            code: offre.code,
            nature: offre.nature,
            niveau: offre.niveau,
            palier: offre.palier,
            nom: offre.nom,
            argumentaire: offre.argumentaire ?? '',
            periode_mois: offre.periode_mois,
            prix_usd: offre.prix_usd_saisie,
            prix_cdf: offre.prix_cdf_saisie,
            plafond_acces: offre.plafond_acces,
            paliers_taille: offre.paliers_taille.map((t) => ({
                max: t.max,
                prix_usd: t.prix_usd,
                prix_cdf: t.prix_cdf,
            })),
            tous_les_modules: offre.modules === null,
            modules: offre.modules ?? [],
            publique: offre.publique,
            ordre: offre.ordre,
        });
    }
    ouverte.value = true;
};

// Le niveau commande la nature : une licence se vend à la Vision, un accès en dessous. On ne
// laisse pas l'opérateur choisir les deux séparément pour se faire refuser ensuite.
watch(
    () => form.niveau,
    (niveau, ancien) => {
        form.nature = niveau === 'VISION' ? 'LICENCE' : 'ACCES';
        if (ancien && niveau !== ancien) form.modules = [];
        if (!enEdition.value) form.periode_mois = niveau === 'VISION' ? 12 : 1;
    }
);

const groupes = computed(() => props.modules_par_niveau?.[form.niveau] ?? []);
const basculerModule = (cle) => {
    form.modules = form.modules.includes(cle)
        ? form.modules.filter((m) => m !== cle)
        : [...form.modules, cle];
};
const toutCocherDans = (groupe) => {
    const cles = groupe.modules.map((m) => m.cle);
    const tous = cles.every((c) => form.modules.includes(c));
    form.modules = tous
        ? form.modules.filter((m) => !cles.includes(m))
        : [...new Set([...form.modules, ...cles])];
};

const ajouterTranche = () => form.paliers_taille.push({ max: null, prix_usd: '', prix_cdf: '' });
const retirerTranche = (index) => form.paliers_taille.splice(index, 1);

const enregistrer = () => {
    const options = { preserveScroll: true, onSuccess: () => (ouverte.value = false) };
    enEdition.value
        ? form.put(route('console.offres.update', enEdition.value.id), options)
        : form.post(route('console.offres.store'), options);
};

const retirer = (offre) =>
    router.patch(route('console.offres.retirer', offre.id), {}, { preserveScroll: true });
const retablir = (offre) =>
    router.patch(route('console.offres.retablir', offre.id), {}, { preserveScroll: true });

const erreursTranches = computed(() =>
    Object.entries(form.errors)
        .filter(([cle]) => cle.startsWith('paliers_taille'))
        .map(([, message]) => message)
);
</script>

<template>
    <LayoutConsole titre="Offres">
        <EnTetePage
            titre="Offres"
            sous-titre="Ce qu'on vend, et pour combien — en dollars et en francs, jamais l'un converti de l'autre"
            :icone="Tags"
        >
            <template #actions>
                <Bouton variante="blanc" :icone="Plus" @click="ouvrir()">Nouvelle offre</Bouton>
            </template>
        </EnTetePage>

        <div class="mx-auto max-w-5xl space-y-8">
            <section v-for="section in SECTIONS" :key="section.titre">
                <h2 class="text-base font-bold text-slate-900">{{ section.titre }}</h2>
                <p class="text-sm text-slate-500">{{ section.sous }}</p>

                <p
                    v-if="!offres.filter(section.filtre).length"
                    class="mt-3 rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400"
                >
                    Aucune offre ici.
                </p>
                <div v-else class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="offre in offres.filter(section.filtre)"
                        :key="offre.id"
                        class="flex min-w-0 flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-card"
                        :class="offre.retiree ? 'opacity-60' : ''"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-900">{{ offre.nom }}</p>
                                <p class="font-mono text-[11px] text-slate-400">{{ offre.code }}</p>
                            </div>
                            <Badge :ton="offre.retiree ? 'ardoise' : 'marque'">{{
                                offre.retiree ? 'Retirée' : offre.libelle_palier
                            }}</Badge>
                        </div>

                        <p class="mt-3 text-xl font-bold text-slate-900">
                            {{ offre.prix_usd }}
                            <span class="text-sm font-medium text-slate-500"
                                >/
                                {{
                                    offre.periode_mois === 12
                                        ? 'an'
                                        : offre.periode_mois === 1
                                          ? 'mois'
                                          : offre.periode_mois + ' mois'
                                }}</span
                            >
                        </p>
                        <p class="text-sm text-slate-500">{{ offre.prix_cdf }}</p>

                        <ul
                            v-if="offre.paliers_taille.length"
                            class="mt-2 space-y-0.5 text-xs text-slate-500"
                        >
                            <li v-for="(t, i) in offre.paliers_taille" :key="i">{{ t.libelle }}</li>
                        </ul>

                        <p
                            v-if="offre.argumentaire"
                            class="mt-3 line-clamp-3 text-sm text-slate-600"
                        >
                            {{ offre.argumentaire }}
                        </p>

                        <p class="mt-3 flex flex-wrap gap-1.5 text-xs">
                            <Badge ton="info">{{
                                offre.nombre_modules === null
                                    ? 'Tous les modules, futurs compris'
                                    : offre.nombre_modules + ' modules'
                            }}</Badge>
                            <Badge v-if="offre.libelle_plafond" ton="ardoise"
                                >Accès jusqu'au {{ offre.libelle_plafond }}</Badge
                            >
                            <Badge v-if="!offre.publique" ton="alerte">Négociée</Badge>
                        </p>

                        <div class="mt-auto grid grid-cols-2 gap-2 pt-4">
                            <Bouton
                                variante="contour"
                                compact
                                :icone="Pencil"
                                @click="ouvrir(offre)"
                                >Modifier</Bouton
                            >
                            <Bouton
                                v-if="!offre.retiree"
                                variante="discret"
                                compact
                                :icone="Archive"
                                @click="retirer(offre)"
                                >Retirer</Bouton
                            >
                            <Bouton
                                v-else
                                variante="discret"
                                compact
                                :icone="ArchiveRestore"
                                @click="retablir(offre)"
                                >Remettre</Bouton
                            >
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <Modale
            :ouverte="ouverte"
            :titre="enEdition ? 'Modifier l\'offre' : 'Nouvelle offre'"
            largeur="xl"
            @fermer="ouverte = false"
        >
            <div class="space-y-4">
                <p
                    v-if="enEdition?.vendue"
                    class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800"
                >
                    Cette offre a déjà été vendue. Un nouveau prix vaudra pour les ventes et les
                    renouvellements à venir ; les périodes déjà vendues gardent le leur.
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <ChampSelect
                        v-model="form.niveau"
                        label="À qui elle se vend"
                        :options="NIVEAUX"
                        :erreur="form.errors.niveau"
                        obligatoire
                    />
                    <ChampSelect
                        v-model="form.palier"
                        label="Palier"
                        :options="paliers"
                        :erreur="form.errors.palier"
                        obligatoire
                    />
                    <ChampTexte
                        v-model="form.nom"
                        label="Nom"
                        placeholder="Accès Église — Standard"
                        :erreur="form.errors.nom"
                        obligatoire
                    />
                    <ChampTexte
                        v-model="form.code"
                        label="Code"
                        placeholder="ACCES_EGLISE_STANDARD"
                        indication="Il apparaît dans les licences : MAJUSCULES, chiffres et _."
                        :erreur="form.errors.code"
                        obligatoire
                    />
                    <ChampTexte
                        v-model="form.prix_usd"
                        label="Prix en dollars"
                        placeholder="10,00"
                        :erreur="form.errors.prix_usd"
                        obligatoire
                    />
                    <ChampTexte
                        v-model="form.prix_cdf"
                        label="Prix en francs"
                        placeholder="23 000"
                        :erreur="form.errors.prix_cdf"
                        obligatoire
                    />
                    <ChampTexte
                        v-model="form.periode_mois"
                        label="Durée d'une période (mois)"
                        type="number"
                        :erreur="form.errors.periode_mois"
                        obligatoire
                    />
                    <ChampSelect
                        v-if="form.nature === 'LICENCE'"
                        v-model="form.plafond_acces"
                        label="Accès autorisés jusqu'au palier"
                        :options="paliers"
                        indication="Pas d'accès Premium sous une licence Starter."
                        :erreur="form.errors.plafond_acces"
                        obligatoire
                    />
                </div>

                <ChampZoneTexte
                    v-model="form.argumentaire"
                    label="Argumentaire"
                    placeholder="Ce que l'offre permet, dit comme on le dirait au pasteur."
                    :erreur="form.errors.argumentaire"
                />

                <!-- La grille de taille : une licence coûte plus cher à un réseau plus grand. -->
                <div
                    v-if="form.nature === 'LICENCE'"
                    class="rounded-xl border border-slate-200 p-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-slate-700">
                            Prix selon la taille du réseau
                        </p>
                        <Bouton variante="doux" compact :icone="Plus" @click="ajouterTranche"
                            >Une tranche</Bouton
                        >
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        La taille, c'est le nombre d'antennes et d'églises que l'installation a
                        remontées. Laissez le maximum vide sur la dernière tranche : « au-delà ».
                        Sans tranche, le prix ci-dessus vaut pour tous.
                    </p>
                    <div
                        v-for="(tranche, index) in form.paliers_taille"
                        :key="index"
                        class="mt-2 grid grid-cols-[1fr_1fr_1fr_auto] items-end gap-2"
                    >
                        <ChampTexte
                            v-model="tranche.max"
                            label="Jusqu'à"
                            type="number"
                            placeholder="au-delà"
                        />
                        <ChampTexte v-model="tranche.prix_usd" label="USD" placeholder="260" />
                        <ChampTexte v-model="tranche.prix_cdf" label="CDF" placeholder="598 000" />
                        <button
                            type="button"
                            class="mb-1 rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                            title="Retirer cette tranche"
                            @click="retirerTranche(index)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                    <p
                        v-for="(message, i) in erreursTranches"
                        :key="i"
                        class="mt-2 text-xs text-rose-600"
                    >
                        {{ message }}
                    </p>
                </div>

                <!-- Les modules -->
                <div class="rounded-xl border border-slate-200 p-3">
                    <label class="flex items-start gap-3">
                        <input
                            v-model="form.tous_les_modules"
                            type="checkbox"
                            class="mt-1 h-4 w-4 rounded border-slate-300"
                        />
                        <span class="text-sm">
                            <span class="font-semibold text-slate-800">Tous les modules</span>
                            <span class="block text-xs text-slate-500"
                                >Y compris ceux qu'une mise à jour du produit ajoutera. Une liste
                                cochée, elle, reste figée.</span
                            >
                        </span>
                    </label>
                    <p v-if="form.errors.modules" class="mt-2 text-xs text-rose-600">
                        {{ form.errors.modules }}
                    </p>

                    <div v-if="!form.tous_les_modules" class="mt-3 space-y-4">
                        <div v-for="groupe in groupes" :key="groupe.espace">
                            <div class="flex items-center justify-between gap-2">
                                <p
                                    class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                >
                                    {{ groupe.libelle }}
                                </p>
                                <button
                                    type="button"
                                    class="text-xs font-semibold text-[color:var(--marque-700)] hover:underline"
                                    @click="toutCocherDans(groupe)"
                                >
                                    Tout / rien
                                </button>
                            </div>
                            <div class="mt-1 grid gap-1 sm:grid-cols-2">
                                <label
                                    v-for="module in groupe.modules"
                                    :key="module.cle"
                                    class="flex min-h-9 items-center gap-2 rounded-lg px-2 text-sm hover:bg-slate-50"
                                >
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300"
                                        :checked="form.modules.includes(module.cle)"
                                        @change="basculerModule(module.cle)"
                                    />
                                    <span class="min-w-0 truncate">{{ module.libelle }}</span>
                                </label>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500">
                            Les écrans non vendables (comptes, paramètres, messagerie interne selon
                            l'espace) viennent avec l'espace : ils ne se cochent pas.
                        </p>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input
                        v-model="form.publique"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300"
                    />
                    Offre publique — décochée, c'est une offre négociée, qu'on ne montre qu'à son
                    client
                </label>
            </div>
            <template #actions>
                <Bouton variante="contour" @click="ouverte = false">Annuler</Bouton>
                <Bouton :desactive="form.processing" @click="enregistrer">Enregistrer</Bouton>
            </template>
        </Modale>
    </LayoutConsole>
</template>
