<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Clock,
    Home,
    Lock,
    RefreshCw,
    SearchX,
    ServerCrash,
    Wrench,
} from 'lucide-vue-next';

/**
 * LA PAGE D'ERREUR — une seule, pour tous les statuts que `bootstrap/app.php` lui confie.
 *
 * AUTONOME, sans `EspaceAdmin` : une 404 sur une adresse inconnue arrive avant tout middleware,
 * donc sans compte, sans menu ni couleur d'espace. Une mise en page qui les attendrait casserait
 * précisément là où on a besoin d'elle. Le logo est celui de la console.
 *
 * Chaque message dit QUOI FAIRE, pas seulement ce qui s'est passé : « accès refusé » seul laisse
 * la personne devant un mur, « demandez-le à votre responsable » lui donne la porte.
 */
const props = defineProps({
    statut: { type: Number, required: true },
});

const logo = '/icons/icon-192.png';

const MESSAGES = {
    403: {
        icone: Lock,
        titre: 'Cet écran ne vous est pas ouvert',
        texte: "Votre compte n'a pas accès à cette page. Si vous pensez devoir y entrer, demandez-le à l'administrateur de la console.",
    },
    404: {
        icone: SearchX,
        titre: 'Page introuvable',
        texte: "Le lien est peut-être ancien, ou la page a été déplacée. Revenez en arrière ou repartez de l'accueil.",
    },
    429: {
        icone: Clock,
        titre: 'Trop de tentatives',
        texte: 'Par sécurité, patientez une minute avant de réessayer.',
    },
    500: {
        icone: ServerCrash,
        titre: 'Un incident est survenu',
        texte: "Ce n'est pas de votre fait. Réessayez dans un instant ; si cela persiste, consultez le journal du serveur.",
    },
    503: {
        icone: Wrench,
        titre: 'Maintenance en cours',
        texte: "La console est mise à jour. Elle revient dans quelques minutes.",
    },
};

const message = computed(() => MESSAGES[props.statut] ?? MESSAGES[500]);
const peutReessayer = computed(() => [429, 500, 503].includes(props.statut));

const recharger = () => window.location.reload();

const retour = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = '/console';
    }
};
</script>

<template>
    <Head :title="message.titre" />

    <div
        class="relative flex min-h-[100dvh] items-center justify-center overflow-hidden bg-slate-50 px-4 pb-[calc(1.5rem+env(safe-area-inset-bottom))] pt-[calc(1.5rem+env(safe-area-inset-top))]"
    >
        <!-- Les deux formes organiques de l'en-tête des écrans, en très pâle : le même langage
             visuel, sans la couleur d'un espace qu'on ne connaît pas forcément ici. -->
        <svg
            class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 text-[color:var(--marque-200)]/60"
            viewBox="0 0 200 200"
            aria-hidden="true"
        >
            <path
                fill="currentColor"
                d="M45.3,-63.7C57.6,-55.4,65.3,-40.3,70.4,-24.7C75.5,-9,78,7.3,73.2,21.2C68.4,35.1,56.3,46.6,43,55.6C29.7,64.6,15,71.1,-0.9,72.3C-16.7,73.6,-33.5,69.6,-46.6,60.3C-59.7,51,-69.2,36.4,-73.3,20.4C-77.5,4.4,-76.3,-13,-69.4,-27.4C-62.5,-41.8,-49.9,-53.2,-36.3,-61.2C-22.6,-69.2,-8,-73.8,5.9,-71.9C19.7,-70,33,-72,45.3,-63.7Z"
                transform="translate(100 100)"
            />
        </svg>
        <svg
            class="pointer-events-none absolute -bottom-24 -left-16 h-72 w-72 text-slate-200/70"
            viewBox="0 0 200 200"
            aria-hidden="true"
        >
            <path
                fill="currentColor"
                d="M38.8,-55.6C50.5,-47.9,60.1,-36.6,66.5,-23.1C72.9,-9.6,76.1,6,72.1,19.6C68.1,33.2,56.9,44.7,44.1,53.6C31.3,62.5,16.9,68.8,1.3,67.1C-14.4,65.4,-31.1,55.7,-44.3,44.1C-57.5,32.5,-67.2,19,-69.7,4.1C-72.2,-10.8,-67.5,-27.1,-57.6,-38.6C-47.6,-50.1,-32.4,-56.8,-18.1,-62.5C-3.8,-68.2,9.6,-72.9,22.6,-70C35.6,-67.1,27.1,-63.3,38.8,-55.6Z"
                transform="translate(100 100)"
            />
        </svg>

        <main class="relative w-full max-w-md text-center">
            <img :src="logo" alt="" class="mx-auto h-16 w-16 object-contain sm:h-20 sm:w-20" />

            <p
                class="mt-6 bg-gradient-to-br from-[color:var(--marque-400)] to-[color:var(--marque-700)] bg-clip-text text-7xl font-extrabold leading-none tracking-tight text-transparent sm:text-8xl"
            >
                {{ statut }}
            </p>

            <div
                class="mx-auto mt-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-white shadow-card ring-1 ring-slate-200"
            >
                <component :is="message.icone" class="h-6 w-6 text-slate-500" :stroke-width="1.8" />
            </div>

            <h1 class="mt-4 text-xl font-bold text-slate-800 sm:text-2xl">{{ message.titre }}</h1>
            <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-slate-500 sm:text-base">
                {{ message.texte }}
            </p>

            <!-- Des liens NUS (`<a>`) et un rechargement complet, pas une visite Inertia : après
                 une erreur, repartir d'une page entièrement neuve est le plus sûr. -->
            <div class="mt-8 grid grid-cols-1 gap-2 sm:grid-cols-2">
                <button
                    v-if="peutReessayer"
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[color:var(--marque-600)] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[color:var(--marque-700)]"
                    @click="recharger"
                >
                    <RefreshCw class="h-4 w-4 shrink-0" />
                    Réessayer
                </button>
                <button
                    v-else
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[color:var(--marque-600)] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[color:var(--marque-700)]"
                    @click="retour"
                >
                    <ArrowLeft class="h-4 w-4 shrink-0" />
                    Revenir en arrière
                </button>
                <a
                    href="/console"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    <Home class="h-4 w-4 shrink-0" />
                    Accueil
                </a>
            </div>
        </main>
    </div>
</template>
