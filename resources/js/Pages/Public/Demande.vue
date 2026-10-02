<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AuthDivise from '@/Layouts/AuthDivise.vue';
import Bouton from '@/Composants/Bouton.vue';
import ChampTexte from '@/Composants/ChampTexte.vue';
import ChampZoneTexte from '@/Composants/ChampZoneTexte.vue';

/**
 * LE FORMULAIRE DU SITE COMMERCIAL — la même identité que la connexion, sans compte ni menu.
 * Le champ `site_web` est un piège pour les robots : une personne ne le voit pas, ne le remplit pas.
 */
const page = usePage();
const succes = computed(() => page.props.flash?.succes);

const form = useForm({
    nom: '',
    organisation: '',
    email: '',
    telephone: '',
    pays: '',
    message: '',
    site_web: '',
});

const envoyer = () => form.post(route('demande.envoyer'), { preserveScroll: true, onSuccess: () => form.reset() });
</script>

<template>
    <AuthDivise
        badge="Oikos"
        titre="Gérer votre église, de la cellule à la Vision."
        description="Dites-nous ce que vous cherchez : nous vous répondons avec une offre adaptée à la taille de votre réseau."
    >
        <h2 class="text-xl font-black tracking-tight text-gray-900">Demander une offre</h2>
        <p class="mt-1 text-sm text-gray-500">Nous revenons vers vous rapidement.</p>

        <div
            v-if="succes"
            class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
        >
            {{ succes }}
        </div>

        <form class="mt-5 space-y-4" @submit.prevent="envoyer">
            <ChampTexte v-model="form.nom" label="Votre nom" :erreur="form.errors.nom" obligatoire />
            <ChampTexte v-model="form.organisation" label="Église ou organisation" :erreur="form.errors.organisation" />
            <ChampTexte v-model="form.email" type="email" label="Adresse électronique" :erreur="form.errors.email" obligatoire />
            <div class="grid gap-4 sm:grid-cols-2">
                <ChampTexte v-model="form.telephone" label="Téléphone" :erreur="form.errors.telephone" />
                <ChampTexte v-model="form.pays" label="Pays" :erreur="form.errors.pays" />
            </div>
            <ChampZoneTexte
                v-model="form.message"
                label="Votre demande"
                :lignes="4"
                placeholder="Nous sommes 7 églises, nous cherchons à suivre les présences…"
                :erreur="form.errors.message"
                obligatoire
            />

            <!-- Le piège à robots : hors de l'écran, hors du clavier, hors de la lecture vocale. -->
            <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                <label>
                    Ne pas remplir
                    <input v-model="form.site_web" type="text" tabindex="-1" autocomplete="off" />
                </label>
            </div>

            <Bouton type="submit" :desactive="form.processing">Envoyer la demande</Bouton>
        </form>
    </AuthDivise>
</template>
