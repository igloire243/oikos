<script setup>
import { ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Building2, Plus, Search } from 'lucide-vue-next';
import LayoutConsole from '@/Layouts/LayoutConsole.vue';
import EnTetePage from '@/Composants/EnTetePage.vue';
import Bouton from '@/Composants/Bouton.vue';
import Badge from '@/Composants/Badge.vue';
import Tableau from '@/Composants/Tableau.vue';
import Pagination from '@/Composants/Pagination.vue';
import Modale from '@/Composants/Modale.vue';
import ChampTexte from '@/Composants/ChampTexte.vue';

/**
 * LES CLIENTS. Un nom provisoire suffit pour en créer un : l'installation remplira le reste de sa
 * fiche à l'activation (sans jamais écraser ce qu'on aura saisi).
 */
const props = defineProps({
    clients: Object,
    recherche: String,
});

const terme = ref(props.recherche ?? '');
let minuterie = null;
watch(terme, (valeur) => {
    clearTimeout(minuterie);
    minuterie = setTimeout(
        () =>
            router.get(route('console.clients.index'), valeur ? { recherche: valeur } : {}, {
                preserveState: true,
                replace: true,
            }),
        300
    );
});

const colonnes = [
    { cle: 'nom', libelle: 'Client', titre: true },
    { cle: 'lieu', libelle: 'Lieu' },
    { cle: 'contact', libelle: 'Responsable' },
    { cle: 'installations', libelle: 'Installations', aligne: 'centre' },
];

const ouverte = ref(false);
const form = useForm({ nom: '', ville: '', pays: '' });
const creer = () =>
    form.post(route('console.clients.store'), { onSuccess: () => (ouverte.value = false) });
</script>

<template>
    <LayoutConsole titre="Clients et installations">
        <EnTetePage
            titre="Clients et installations"
            sous-titre="Chaque église cliente, ses serveurs et ce qu'ils remontent"
            :icone="Building2"
        >
            <template #actions>
                <Bouton variante="blanc" :icone="Plus" @click="ouverte = true"
                    >Nouveau client</Bouton
                >
            </template>
        </EnTetePage>

        <div class="mx-auto mt-6 max-w-5xl space-y-4">
            <div class="relative">
                <Search
                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                />
                <input
                    v-model="terme"
                    type="search"
                    placeholder="Chercher un client, une ville, un responsable…"
                    class="w-full rounded-xl border-slate-300 py-2.5 pl-9 text-sm shadow-sm"
                />
            </div>

            <Tableau
                :colonnes="colonnes"
                :lignes="clients.data"
                :filtre-actif="!!recherche"
                vide-titre="Aucun client"
                vide-texte="Créez le premier : un nom provisoire suffit, l'installation remplira le reste."
            >
                <template #cellule-nom="{ ligne }">
                    <Link
                        :href="route('console.clients.show', ligne.id)"
                        class="inline-flex items-center gap-2 font-semibold text-[color:var(--marque-700)] hover:underline"
                    >
                        {{ ligne.nom }}
                        <AlertTriangle
                            v-if="ligne.alerte"
                            class="h-4 w-4 text-amber-500"
                            title="Une installation ne se synchronise plus"
                        />
                    </Link>
                </template>
                <template #cellule-lieu="{ ligne }">{{ ligne.lieu ?? '—' }}</template>
                <template #cellule-contact="{ ligne }">{{ ligne.contact ?? '—' }}</template>
                <template #cellule-installations="{ ligne }">
                    <Badge :ton="ligne.installations ? 'marque' : 'ardoise'">{{
                        ligne.installations
                    }}</Badge>
                </template>
            </Tableau>

            <Pagination :paginateur="clients" libelle="clients" libelle-singulier="client" />
        </div>

        <Modale :ouverte="ouverte" titre="Nouveau client" @fermer="ouverte = false">
            <div class="space-y-4">
                <ChampTexte
                    v-model="form.nom"
                    label="Nom"
                    placeholder="Église Béthel — provisoire, il se corrigera"
                    :erreur="form.errors.nom"
                    obligatoire
                />
                <div class="grid gap-4 sm:grid-cols-2">
                    <ChampTexte
                        v-model="form.ville"
                        label="Ville"
                        placeholder="Kolwezi"
                        :erreur="form.errors.ville"
                    />
                    <ChampTexte
                        v-model="form.pays"
                        label="Pays"
                        placeholder="RD Congo"
                        :erreur="form.errors.pays"
                    />
                </div>
            </div>
            <template #actions>
                <Bouton variante="contour" @click="ouverte = false">Annuler</Bouton>
                <Bouton :desactive="form.processing" @click="creer">Créer</Bouton>
            </template>
        </Modale>
    </LayoutConsole>
</template>
