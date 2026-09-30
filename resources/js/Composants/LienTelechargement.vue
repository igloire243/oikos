<script setup>
import { Download } from 'lucide-vue-next';

/**
 * UN LIEN QUI TÉLÉCHARGE UN FICHIER — une balise `<a>` nue, habillée comme un `Bouton` discret.
 *
 * `Bouton` avec un `href` rend un `<Link>` Inertia, qui traite la réponse comme une navigation :
 * un PDF n'en est pas une, et le clic ne fait rien, sans la moindre erreur. Le piège est tombé
 * quatre fois dans ce produit ; ce composant existe pour qu'il ne tombe pas une cinquième.
 */
defineProps({
    href: { type: String, required: true },
});
</script>

<template>
    <a
        :href="href"
        class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl px-2 py-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 sm:min-h-0 sm:gap-2 sm:px-4 sm:text-sm"
    >
        <Download class="h-4 w-4" :stroke-width="2" />
        <slot>PDF</slot>
    </a>
</template>
