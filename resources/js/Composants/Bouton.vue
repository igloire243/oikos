<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

/**
 * LE BOUTON DU PRODUIT.
 *
 * Rend un `<Link>` Inertia quand `href` est fourni, un `<button>` sinon. La distinction compte :
 * un lien doit rester ouvrable dans un nouvel onglet et lisible par un lecteur d'écran comme une
 * navigation, pas comme une action.
 */
const props = defineProps({
    variante: { type: String, default: 'principal' },
    href: { type: String, default: null },
    type: { type: String, default: 'button' },
    methode: { type: String, default: 'get' },
    desactive: { type: Boolean, default: false },
    icone: { type: [Object, Function], default: null },
    /**
     * Pour une RANGÉE d'actions sur téléphone : moins de marge et un texte plus petit, pour que
     * trois boutons tiennent sur une ligne au lieu de s'empiler (signalé par l'utilisateur).
     * Rien ne change au-dessus de `sm`.
     */
    compact: { type: Boolean, default: false },
});

const variantes = {
    // Le fond suit la couleur de l'espace : c'est l'action principale de l'écran.
    principal: 'text-white shadow-sm hover:brightness-110 marque-fond',
    doux: 'bg-[color:var(--marque-50)] text-[color:var(--marque-700)] hover:bg-[color:var(--marque-100)]',
    contour: 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
    discret: 'text-slate-600 hover:bg-slate-100',
    danger: 'bg-rose-600 text-white hover:bg-rose-700',
    // Un refus reste bien visible sans crier autant que l'action principale.
    'danger-contour': 'border border-rose-300 bg-white text-rose-700 hover:bg-rose-50',
    blanc: 'bg-white/20 text-white ring-1 ring-white/30 backdrop-blur hover:bg-white/30',
};

const classes = computed(() => [
    // 44 px de haut sur téléphone : la cible minimale d'un doigt. Au-dessus de `sm`, la souris
    // vise juste et la hauteur naturelle suffit.
    'inline-flex min-h-11 items-center justify-center rounded-xl py-2.5 font-semibold transition sm:min-h-0',
    props.compact ? 'gap-1.5 px-2 text-xs sm:gap-2 sm:px-4 sm:text-sm' : 'gap-2 px-4 text-sm',
    'active:scale-[0.98]',
    'focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 marque-anneau',
    variantes[props.variante] ?? variantes.principal,
    props.desactive ? 'pointer-events-none opacity-50' : '',
]);
</script>

<template>
    <!-- `shrink-0` : sans lui, un libellé long écrasait l'icône jusqu'à un simple point. -->
    <Link v-if="href" :href="href" :method="methode" :class="classes">
        <component :is="icone" v-if="icone" class="h-4 w-4 shrink-0" :stroke-width="2" />
        <slot />
    </Link>

    <button v-else :type="type" :disabled="desactive" :class="classes">
        <component :is="icone" v-if="icone" class="h-4 w-4 shrink-0" :stroke-width="2" />
        <slot />
    </button>
</template>
