<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Layers, LayoutDashboard } from 'lucide-vue-next';
import LayoutConsole from '@/Layouts/LayoutConsole.vue';
import EnTetePage from '@/Composants/EnTetePage.vue';
import CarteStat from '@/Composants/CarteStat.vue';
import { icone } from '@/Composants/icones.js';

/**
 * L'ACCUEIL DE LA CONSOLE. Au socle, il ne montre que ce qui existe : le catalogue qu'on vend.
 * Clients, échéances et encaissements arrivent avec leurs lots.
 */
defineProps({
    catalogue: { type: Object, required: true },
});
</script>

<template>
    <LayoutConsole titre="Tableau de bord">
        <EnTetePage
            titre="Tableau de bord"
            sous-titre="Ce qui se vend du produit, et où en est chaque client"
            :icone="LayoutDashboard"
        />

        <div class="mx-auto mt-6 max-w-5xl space-y-6">
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
