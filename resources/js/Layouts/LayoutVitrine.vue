<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { LogIn, Menu as MenuIcone, X } from 'lucide-vue-next';

/**
 * LA MISE EN PAGE DU SITE COMMERCIAL — le même langage que la console (couleur de marque, rayons,
 * ombres), sans menu d'espace ni compte.
 *
 * Sous `md`, la page est une colonne de la hauteur de l'écran dont le milieu défile : l'indicateur
 * de défilement de la FENÊTRE, que le système peint par-dessus les éléments fixes, ne court plus sur
 * toute la hauteur (voir « Les pièges » du CLAUDE.md). Le pied de page vit DANS la zone qui défile,
 * sinon il resterait hors de portée du doigt.
 */
defineProps({
    titre: { type: String, required: true },
});

const page = usePage();

// Un opérateur déjà connecté ne revoit pas le formulaire : `/console/login` le renvoie à son tableau
// de bord, et un lien « Connexion » qui ne montre rien ressemble à un lien cassé. Il lit donc
// « Ma console », et mène là où il va vraiment.
const editeur = computed(() => page.props.editeur ?? {});
const connecte = computed(() => page.props.auth?.user != null);
const lienConsole = computed(() => (connecte.value ? route('console.accueil') : route('login')));
const libelleConsole = computed(() => (connecte.value ? 'Ma console' : 'Connexion'));
const menuOuvert = ref(false);
router.on('navigate', () => (menuOuvert.value = false));

const LIENS = [
    { libelle: 'Accueil', route: 'vitrine.accueil' },
    { libelle: 'Tarifs', route: 'vitrine.tarifs' },
];

const actif = (nom) => route().current(nom);
</script>

<template>
    <Head :title="titre" />

    <div class="flex h-[100dvh] flex-col bg-white md:block md:h-auto md:min-h-screen">
        <header class="relative z-30 shrink-0 border-b border-slate-200 bg-white/95 backdrop-blur md:sticky md:top-0">
            <div class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4 sm:px-6">
                <Link :href="route('vitrine.accueil')" class="flex shrink-0 items-center gap-2.5">
                    <img src="/icons/icon-192.png" alt="" class="h-9 w-9 shrink-0 rounded-xl" />
                    <span class="text-lg font-black tracking-tight text-slate-900">Oikos</span>
                </Link>

                <nav class="ml-6 hidden items-center gap-1 md:flex">
                    <Link
                        v-for="lien in LIENS"
                        :key="lien.route"
                        :href="route(lien.route)"
                        class="rounded-xl px-3 py-2 text-sm font-medium transition"
                        :class="actif(lien.route) ? 'bg-[color:var(--marque-50)] text-[color:var(--marque-700)]' : 'text-slate-600 hover:bg-slate-100'"
                    >
                        {{ lien.libelle }}
                    </Link>
                </nav>

                <div class="ml-auto flex items-center gap-2">
                    <Link
                        :href="lienConsole"
                        class="max-sm:!hidden inline-flex min-h-11 items-center gap-1.5 rounded-xl px-3 text-sm font-medium text-slate-600 hover:bg-slate-100"
                    >
                        <LogIn class="h-4 w-4" /> {{ libelleConsole }}
                    </Link>
                    <Link
                        :href="route('demande.formulaire')"
                        class="inline-flex min-h-11 shrink-0 items-center whitespace-nowrap rounded-xl bg-[color:var(--marque-600)] px-4 text-sm font-semibold text-white shadow-sm hover:brightness-110"
                    >
                        <span class="sm:hidden">Nous écrire</span>
                        <span class="max-sm:hidden">Demander une offre</span>
                    </Link>
                    <button
                        type="button"
                        class="rounded-xl p-2 text-slate-600 hover:bg-slate-100 md:hidden"
                        :aria-expanded="menuOuvert"
                        @click="menuOuvert = !menuOuvert"
                    >
                        <span class="sr-only">Menu</span>
                        <component :is="menuOuvert ? X : MenuIcone" class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <nav v-if="menuOuvert" class="border-t border-slate-100 bg-white px-4 py-2 md:hidden">
                <Link
                    v-for="lien in LIENS"
                    :key="lien.route"
                    :href="route(lien.route)"
                    class="flex min-h-11 items-center rounded-xl px-3 text-sm font-medium text-slate-700 hover:bg-slate-100"
                >
                    {{ lien.libelle }}
                </Link>
                <Link
                    :href="lienConsole"
                    class="flex min-h-11 items-center gap-2 rounded-xl px-3 text-sm font-medium text-slate-700 hover:bg-slate-100"
                >
                    <LogIn class="h-4 w-4" /> {{ connecte ? 'Ma console' : 'Connexion des opérateurs' }}
                </Link>
            </nav>
        </header>

        <div class="scroll-region min-h-0 flex-1 overflow-y-auto overflow-x-hidden md:overflow-visible" scroll-region>
            <main>
                <slot />
            </main>

            <footer class="border-t border-slate-200 bg-slate-50">
                <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <p class="font-semibold text-slate-700">{{ editeur.nom || 'Oikos' }} — la gestion de votre église.</p>
                        <!-- Rien n'est affiché pour un champ que l'éditeur n'a pas rempli (Réglages → Éditeur). -->
                        <p v-if="editeur.email || editeur.telephone || editeur.adresse" class="mt-1 text-xs">
                            <a v-if="editeur.email" :href="`mailto:${editeur.email}`" class="hover:text-slate-800">{{ editeur.email }}</a>
                            <span v-if="editeur.email && (editeur.telephone || editeur.adresse)"> · </span>
                            <span v-if="editeur.telephone">{{ editeur.telephone }}</span>
                            <span v-if="editeur.telephone && editeur.adresse"> · </span>
                            <span v-if="editeur.adresse">{{ editeur.adresse }}</span>
                        </p>
                    </div>
                    <p class="flex flex-wrap gap-x-4 gap-y-1">
                        <Link :href="route('vitrine.tarifs')" class="hover:text-slate-800">Tarifs</Link>
                        <Link :href="route('demande.formulaire')" class="hover:text-slate-800">Demander une offre</Link>
                        <Link :href="lienConsole" class="hover:text-slate-800">{{ connecte ? 'Ma console' : 'Connexion des opérateurs' }}</Link>
                    </p>
                </div>
            </footer>
        </div>
    </div>
</template>
