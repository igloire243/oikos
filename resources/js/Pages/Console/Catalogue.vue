<script setup>
import { Layers } from 'lucide-vue-next';
import LayoutConsole from '@/Layouts/LayoutConsole.vue';
import EnTetePage from '@/Composants/EnTetePage.vue';
import Badge from '@/Composants/Badge.vue';
import { icone } from '@/Composants/icones.js';

/**
 * LE CATALOGUE, EN LECTURE. Il appartient au produit : on le régénère là-bas et on recopie le
 * fichier ici, jamais on ne l'édite depuis la console (voir App\Metier\Catalogue\Modules).
 */
defineProps({
    espaces: { type: Array, required: true },
    empreinte: { type: String, required: true },
    vendables: { type: Number, required: true },
    inclus: { type: Array, default: () => [] },
});
</script>

<template>
    <LayoutConsole titre="Catalogue des modules">
        <EnTetePage
            titre="Catalogue des modules"
            :sous-titre="`${vendables} modules vendables — le catalogue du produit, recopié tel quel`"
            :icone="Layers"
        />

        <div class="mx-auto mt-6 max-w-5xl space-y-6">
            <p
                class="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-600 shadow-card"
            >
                Ce catalogue vient du produit (<code class="text-xs">modules:exporter</code>) et ne
                se modifie pas ici : une clé présente d'un seul côté donnerait un module vendu que
                rien n'ouvre. Chaque installation annonce l'empreinte du sien ; celle-ci est
                <code class="break-all text-xs font-semibold">{{ empreinte }}</code
                >.
            </p>

            <section
                v-for="espace in espaces"
                :key="espace.cle"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card"
            >
                <header
                    class="flex items-baseline justify-between gap-2 border-b border-slate-100 px-5 py-3"
                >
                    <h2 class="font-semibold text-slate-800">{{ espace.libelle }}</h2>
                    <span class="shrink-0 text-xs text-slate-400"
                        >{{ espace.modules.length }} modules</span
                    >
                </header>
                <ul class="divide-y divide-slate-100">
                    <li
                        v-for="module in espace.modules"
                        :key="module.cle"
                        class="flex min-w-0 items-center gap-3 px-5 py-3"
                    >
                        <component
                            :is="icone(module.icone)"
                            class="h-4 w-4 shrink-0 text-slate-400"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm text-slate-800">{{ module.libelle }}</p>
                            <p class="truncate font-mono text-xs text-slate-400">
                                {{ module.cle }}
                                <template v-if="module.groupe"> · {{ module.groupe }}</template>
                            </p>
                        </div>
                        <Badge :ton="module.vendable ? 'marque' : 'ardoise'">
                            {{ module.vendable ? 'vendable' : 'toujours ouvert' }}
                        </Badge>
                    </li>
                </ul>
            </section>

            <!-- Ce qui existe sans se vendre à part : on le voit, on ne le coche pas. -->
            <section
                v-for="groupe in inclus"
                :key="groupe.cle"
                class="overflow-hidden rounded-2xl border border-dashed border-slate-300 bg-white"
            >
                <header class="border-b border-slate-100 px-5 py-3">
                    <h2 class="font-semibold text-slate-800">{{ groupe.libelle }}</h2>
                    <p class="text-xs text-slate-500">
                        <template v-if="groupe.ouvert_par"
                            >Inclus : tout s'ouvre d'un bloc avec {{ groupe.ouvert_par }}.</template
                        >
                        <template v-else>Inclus dans chaque espace, sans clé à vendre.</template>
                    </p>
                </header>
                <ul class="divide-y divide-slate-100">
                    <li
                        v-for="module in groupe.modules"
                        :key="module.cle"
                        class="flex min-w-0 items-center gap-3 px-5 py-3"
                    >
                        <component
                            :is="icone(module.icone)"
                            class="h-4 w-4 shrink-0 text-slate-400"
                        />
                        <p class="min-w-0 flex-1 truncate text-sm text-slate-800">
                            {{ module.libelle }}
                        </p>
                        <Badge ton="ardoise">inclus</Badge>
                    </li>
                </ul>
            </section>
        </div>
    </LayoutConsole>
</template>
