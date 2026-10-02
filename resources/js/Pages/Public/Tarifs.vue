<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronDown, Check } from 'lucide-vue-next';
import LayoutVitrine from '@/Layouts/LayoutVitrine.vue';

/**
 * LES TARIFS — lus dans les offres publiques, jamais écrits ici (voir `App\Metier\Vitrine\Tarifs`).
 *
 * Trois étages, parce que c'est ainsi que ça se vend : la LICENCE de la Vision (annuelle, au prix de
 * la taille du réseau) met le système en service ; chaque antenne et chaque église ouvre ensuite SON
 * accès (mensuel), du palier qu'elle choisit — sans dépasser celui que la licence permet.
 *
 * La devise est un choix du visiteur, jamais une conversion : chaque offre porte deux prix posés par
 * l'opérateur.
 */
defineProps({
    tarifs: { type: Object, required: true },
});

const devise = ref('USD');

const ouvertes = ref(new Set());
const basculer = (code) => {
    const suite = new Set(ouvertes.value);
    suite.has(code) ? suite.delete(code) : suite.add(code);
    ouvertes.value = suite;
};

const SECTIONS = [
    {
        cle: 'licences',
        titre: 'La licence de la Vision',
        sous: "Annuelle. Elle met le système en service pour tout votre réseau, au prix de sa taille.",
    },
    {
        cle: 'eglises',
        titre: 'L\'accès d\'une église',
        sous: "Mensuel. Il ouvre l'espace de l'église et de ses départements.",
    },
    {
        cle: 'antennes',
        titre: "L'accès d'une antenne",
        sous: "Mensuel. Il ouvre l'espace de supervision des églises qu'elle suit.",
    },
];
</script>

<template>
    <LayoutVitrine titre="Tarifs">
        <section class="bg-gradient-to-br from-[color:var(--marque-700)] to-[color:var(--marque-500)] text-white">
            <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
                <h1 class="text-3xl font-black tracking-tight sm:text-4xl">Des tarifs à la taille de votre réseau</h1>
                <p class="mt-3 max-w-2xl text-white/90">
                    Une licence pour la Vision, puis un accès par antenne et par église : chaque entité ne
                    paie que ce qu'elle ouvre.
                </p>

                <div class="mt-6 inline-flex rounded-xl bg-white/15 p-1 ring-1 ring-white/30">
                    <button
                        v-for="d in ['USD', 'CDF']"
                        :key="d"
                        type="button"
                        class="min-h-10 rounded-lg px-4 text-sm font-bold transition"
                        :class="devise === d ? 'bg-white text-[color:var(--marque-700)]' : 'text-white hover:bg-white/10'"
                        @click="devise = d"
                    >
                        {{ d === 'USD' ? 'Dollars ($)' : 'Francs (FC)' }}
                    </button>
                </div>
            </div>
        </section>

        <div class="mx-auto max-w-6xl space-y-14 px-4 py-12 sm:px-6">
            <section v-for="section in SECTIONS" :key="section.cle">
                <h2 class="text-2xl font-black tracking-tight text-slate-900">{{ section.titre }}</h2>
                <p class="mt-1 text-slate-500">{{ section.sous }}</p>

                <p v-if="!tarifs[section.cle].length" class="mt-6 rounded-2xl border border-dashed border-slate-300 p-6 text-sm text-slate-500">
                    Les offres de cette catégorie sont communiquées sur demande.
                </p>

                <div v-else class="mt-6 grid gap-4 lg:grid-cols-3">
                    <article
                        v-for="o in tarifs[section.cle]"
                        :key="o.code"
                        class="flex min-w-0 flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-card"
                    >
                        <p class="text-xs font-bold uppercase tracking-widest text-[color:var(--marque-600)]">{{ o.palier }}</p>
                        <h3 class="mt-1 font-bold text-slate-900">{{ o.nom }}</h3>
                        <p v-if="o.argumentaire" class="mt-1.5 text-sm text-slate-600">{{ o.argumentaire }}</p>

                        <p class="mt-4">
                            <span v-if="o.tranches.length" class="block text-xs text-slate-500">À partir de</span>
                            <span class="text-3xl font-black tracking-tight text-slate-900">{{ o.prix[devise] }}</span>
                            <span class="ml-1 text-sm text-slate-500">{{ o.duree }}</span>
                        </p>

                        <ul v-if="o.tranches.length" class="mt-3 space-y-1 border-t border-slate-100 pt-3 text-sm text-slate-600">
                            <li v-for="t in o.tranches" :key="t.libelle" class="flex justify-between gap-3">
                                <span>{{ t.libelle }}</span>
                                <span class="shrink-0 font-semibold text-slate-800">{{ t.prix[devise] }}</span>
                            </li>
                        </ul>

                        <p v-if="o.plafond" class="mt-3 text-xs text-slate-500">
                            Permet de vendre des accès jusqu'au palier {{ o.plafond }}.
                        </p>

                        <button
                            type="button"
                            class="mt-4 inline-flex min-h-11 items-center justify-between gap-2 rounded-xl border border-slate-200 px-3 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            :aria-expanded="ouvertes.has(o.code)"
                            @click="basculer(o.code)"
                        >
                            {{ o.tout_le_produit ? 'Tous les écrans, futurs compris' : 'Les écrans inclus' }}
                            <ChevronDown class="h-4 w-4 transition" :class="ouvertes.has(o.code) ? 'rotate-180' : ''" />
                        </button>

                        <div v-if="ouvertes.has(o.code)" class="mt-3 space-y-3">
                            <div v-for="groupe in o.modules" :key="groupe.espace">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ groupe.espace }}</p>
                                <ul class="mt-1 space-y-0.5 text-sm text-slate-700">
                                    <li v-for="m in groupe.modules" :key="m" class="flex items-start gap-1.5">
                                        <Check class="mt-0.5 h-4 w-4 shrink-0 text-[color:var(--marque-600)]" />
                                        <span class="min-w-0">{{ m }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <Link
                            :href="route('demande.formulaire')"
                            class="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-[color:var(--marque-600)] px-4 text-sm font-semibold text-white hover:brightness-110 lg:mt-auto"
                        >
                            Demander cette offre
                        </Link>
                    </article>
                </div>
            </section>

            <section class="rounded-2xl bg-slate-50 p-6 text-sm leading-relaxed text-slate-600">
                <h2 class="font-bold text-slate-900">Comment ça s'assemble</h2>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    <li>L'accès d'une antenne ou d'une église suppose une licence de la Vision en cours.</li>
                    <li>Un accès ne dépasse pas le palier que la licence permet, ni la date de fin de la licence.</li>
                    <li>Les écrans de réglages et de sécurité sont toujours ouverts, quelle que soit l'offre.</li>
                    <li>Les prix sont affichés dans les deux devises : aucune n'est convertie de l'autre.</li>
                </ul>
            </section>
        </div>
    </LayoutVitrine>
</template>
