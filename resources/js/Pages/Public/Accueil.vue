<script setup>
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BarChart3,
    CalendarDays,
    Church,
    MessagesSquare,
    PartyPopper,
    Users,
    Wallet,
    WifiOff,
    Smartphone,
    BookOpen,
} from 'lucide-vue-next';
import LayoutVitrine from '@/Layouts/LayoutVitrine.vue';

/**
 * L'ACCUEIL DU SITE COMMERCIAL — ce que fait le produit, dit avec ce qu'il fait VRAIMENT.
 *
 * Aucun chiffre de clientèle, aucun témoignage : rien de ce qui n'existe pas n'est promis. Les prix
 * vivent sur la page Tarifs, lus dans les offres ; ici, on dit à quoi ça sert.
 */
const DOMAINES = [
    {
        icone: Users,
        titre: 'Membres et présences',
        texte: "Le registre de chaque église, le pointage nominal ou par carte à QR, le comptage à la porte. Chaque dimanche compte une seule fois.",
    },
    {
        icone: Wallet,
        titre: 'Finances',
        texte: "Offrandes, dépenses et campagnes de fonds, caisse par devise sans jamais mélanger dollars et francs, reversement calculé et partagé entre la Vision et l'antenne.",
    },
    {
        icone: Church,
        titre: 'Équipes et départements',
        texte: "Chorale, protocole, jeunesse : le responsable propose, le berger approuve. Plannings, appel nominal et assiduité de chaque équipe.",
    },
    {
        icone: BarChart3,
        titre: 'Rapports et supervision',
        texte: "Rapport mensuel de chaque église, visites d'extensions, procès-verbaux de réunion, tableau des églises qui décrochent. L'antenne voit ce qui demande un coup de fil.",
    },
    {
        icone: MessagesSquare,
        titre: 'Communication',
        texte: "Annonces de l'église, de l'antenne et de la Vision, messagerie interne bornée à votre périmètre, notifications sur le téléphone.",
    },
    {
        icone: PartyPopper,
        titre: 'Grands rassemblements',
        texte: "Programme jour par jour, commissions, inscriptions, budget réparti par antenne, transport, logement, appel à la portière et bilan.",
    },
];

const ATOUTS = [
    { icone: Smartphone, titre: 'Pensé pour le téléphone', texte: "Chaque écran est conçu d'abord pour l'écran qu'on tient à la main, et l'application s'installe comme n'importe quelle autre." },
    { icone: WifiOff, titre: 'Le pointage survit à une coupure', texte: "Dans une salle mal couverte, la feuille d'appel et la carte du fidèle restent utilisables, et tout repart au retour du réseau." },
    { icone: BookOpen, titre: 'La Bible intégrée', texte: 'Sept traductions en quatre langues, lisibles sans réseau, avec recherche, plans de lecture et lecture à voix haute.' },
];

const ETAPES = [
    { n: 1, titre: 'Vous demandez une offre', texte: "Dites-nous la taille de votre réseau : antennes, églises, besoins." },
    { n: 2, titre: 'Nous installons et activons', texte: "Votre installation reçoit une clé d'activation, puis se synchronise toute seule." },
    { n: 3, titre: 'Chaque entité ouvre ce qu\'elle choisit', texte: "Une antenne ou une église n'ouvre que les écrans de son offre : elle ne paie que ce qu'elle utilise." },
];
</script>

<template>
    <LayoutVitrine titre="La gestion de votre église">
        <!-- Le héros -->
        <section class="relative overflow-hidden bg-gradient-to-br from-[color:var(--marque-700)] to-[color:var(--marque-500)] text-white">
            <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-white/10" />
            <div class="pointer-events-none absolute -bottom-32 -left-16 h-72 w-72 rounded-full bg-black/10" />
            <div class="relative mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20 lg:py-24">
                <p class="text-sm font-semibold uppercase tracking-widest text-white/80">Oikos</p>
                <h1 class="mt-3 max-w-3xl text-3xl font-black leading-tight tracking-tight sm:text-5xl">
                    La gestion de votre église, de la cellule à la Vision.
                </h1>
                <p class="mt-5 max-w-2xl text-base text-white/90 sm:text-lg">
                    Membres, cultes et présences, finances, équipes, rapports : un seul outil qui suit la
                    hiérarchie réelle de votre église — la Vision, ses antennes, leurs secteurs et leurs cellules.
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <Link
                        :href="route('demande.formulaire')"
                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-white px-6 text-sm font-bold text-[color:var(--marque-700)] shadow-lg hover:bg-slate-50"
                    >
                        Demander une offre <ArrowRight class="h-4 w-4" />
                    </Link>
                    <Link
                        :href="route('vitrine.tarifs')"
                        class="inline-flex min-h-12 items-center justify-center rounded-xl border border-white/40 px-6 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        Voir les tarifs
                    </Link>
                </div>
            </div>
        </section>

        <!-- Ce que vous gérez -->
        <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
            <h2 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Tout ce que votre église fait déjà</h2>
            <p class="mt-2 max-w-2xl text-slate-500">
                Chaque domaine est un module : vous ouvrez ceux dont vous avez besoin, entité par entité.
            </p>
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="domaine in DOMAINES"
                    :key="domaine.titre"
                    class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-card transition hover:shadow-card-hover"
                >
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[color:var(--marque-50)] text-[color:var(--marque-700)]">
                        <component :is="domaine.icone" class="h-5 w-5" />
                    </span>
                    <h3 class="mt-4 font-bold text-slate-900">{{ domaine.titre }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ domaine.texte }}</p>
                </article>
            </div>
        </section>

        <!-- Pensé pour le terrain -->
        <section class="bg-slate-50">
            <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
                <h2 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Pensé pour le terrain</h2>
                <div class="mt-8 grid gap-6 md:grid-cols-3">
                    <div v-for="atout in ATOUTS" :key="atout.titre" class="min-w-0">
                        <component :is="atout.icone" class="h-6 w-6 text-[color:var(--marque-600)]" />
                        <h3 class="mt-3 font-bold text-slate-900">{{ atout.titre }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ atout.texte }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Comment ça marche -->
        <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
            <h2 class="flex items-center gap-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                <CalendarDays class="hidden h-7 w-7 text-[color:var(--marque-600)] sm:block" /> Comment ça marche
            </h2>
            <ol class="mt-8 grid gap-4 md:grid-cols-3">
                <li v-for="etape in ETAPES" :key="etape.n" class="min-w-0 rounded-2xl border border-slate-200 p-5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[color:var(--marque-600)] font-black text-white">
                        {{ etape.n }}
                    </span>
                    <h3 class="mt-3 font-bold text-slate-900">{{ etape.titre }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ etape.texte }}</p>
                </li>
            </ol>

            <div class="mt-10 rounded-2xl bg-slate-900 p-6 text-center sm:p-10">
                <h2 class="text-xl font-black text-white sm:text-2xl">Parlons de votre réseau</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm text-slate-300">
                    Une offre adaptée à la taille de votre Vision : dites-nous combien d'antennes et d'églises
                    vous suivez, nous vous répondons.
                </p>
                <Link
                    :href="route('demande.formulaire')"
                    class="mt-6 inline-flex min-h-12 items-center gap-2 rounded-xl bg-[color:var(--marque-500)] px-6 text-sm font-bold text-white hover:brightness-110"
                >
                    Demander une offre <ArrowRight class="h-4 w-4" />
                </Link>
            </div>
        </section>
    </LayoutVitrine>
</template>
