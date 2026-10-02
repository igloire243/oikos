<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, Layers, LayoutDashboard } from 'lucide-vue-next';
import LayoutConsole from '@/Layouts/LayoutConsole.vue';
import EnTetePage from '@/Composants/EnTetePage.vue';
import CarteStat from '@/Composants/CarteStat.vue';
import { icone } from '@/Composants/icones.js';

/**
 * L'ACCUEIL DE LA CONSOLE — d'abord ce qui attend quelqu'un (la même liste que la notification du
 * matin), ensuite où l'on en est. Une liste vide dit « tout est à jour » en clair.
 */
defineProps({
    catalogue: { type: Object, required: true },
    chiffres: { type: Object, required: true },
    a_traiter: { type: Array, required: true },
});
</script>

<template>
    <LayoutConsole titre="Tableau de bord">
        <EnTetePage
            titre="Tableau de bord"
            sous-titre="Ce qui se vend du produit, et où en est chaque client"
            :icone="LayoutDashboard"
        />

        <div class="mx-auto mt-6 max-w-5xl space-y-6 px-4 sm:px-0">
            <section class="rounded-2xl bg-white p-5 shadow-card ring-1 ring-slate-100">
                <h2 class="font-semibold text-slate-800">À traiter</h2>
                <p v-if="!a_traiter.length" class="mt-2 flex items-center gap-2 text-sm text-emerald-700">
                    <CheckCircle2 class="h-5 w-5" /> Tout est à jour : rien n'attend de réponse.
                </p>
                <ul v-else class="mt-2 divide-y divide-slate-100">
                    <li v-for="ligne in a_traiter" :key="ligne.cle">
                        <Link
                            :href="ligne.route"
                            class="flex min-h-11 items-center gap-3 py-2 text-sm text-slate-700 hover:text-slate-900"
                        >
                            <span class="min-w-8 rounded-lg bg-amber-50 px-2 py-1 text-center font-bold text-amber-700">
                                {{ ligne.compte }}
                            </span>
                            <span class="min-w-0 flex-1">{{ ligne.libelle }}</span>
                            <ArrowRight class="h-4 w-4 shrink-0 text-slate-300" />
                        </Link>
                    </li>
                </ul>
            </section>

            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <CarteStat libelle="Clients" :valeur="chiffres.clients" :icone="icone('building-2')" />
                <CarteStat
                    libelle="Installations actives"
                    :valeur="chiffres.installations"
                    :icone="icone('server')"
                />
            </div>

            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <CarteStat
                    libelle="Modules au catalogue"
                    :valeur="catalogue.modules"
                    :icone="icone('layers')"
                />
                <CarteStat
                    libelle="Vendables"
                    :valeur="catalogue.vendables"
                    precision="les autres restent toujours ouverts"
                    :icone="icone('tag')"
                />
                <CarteStat
                    libelle="Espaces"
                    :valeur="catalogue.espaces"
                    :icone="icone('network')"
                />
                <CarteStat
                    libelle="Empreinte"
                    :valeur="catalogue.empreinte"
                    precision="celle que chaque installation doit annoncer"
                    :icone="icone('hash')"
                />
            </div>

            <Link
                :href="route('console.catalogue.index')"
                class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-card transition hover:shadow-card-hover"
            >
                <Layers class="h-6 w-6 shrink-0 text-[color:var(--marque-600)]" />
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-slate-800">Le catalogue des modules</p>
                    <p class="text-sm text-slate-500">
                        Ce que le produit sait ouvrir, espace par espace — donc ce que les offres
                        peuvent vendre.
                    </p>
                </div>
                <ArrowRight class="h-5 w-5 shrink-0 text-slate-300" />
            </Link>
        </div>
    </LayoutConsole>
</template>
