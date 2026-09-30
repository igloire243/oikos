<script setup>
/**
 * L'ENVELOPPE D'UN CHAMP — libellé, icône, indication, message d'erreur.
 *
 * PLACEHOLDER ET INDICATION NE DISENT PAS LA MÊME CHOSE, et c'est pour ça qu'il y a les deux :
 *
 *   · le PLACEHOLDER donne un EXEMPLE de ce qu'on attend (« +243 812 345 678 ») ;
 *   · l'INDICATION donne la RÈGLE ou la conséquence (« sans elle, la fiche sera signalée »).
 *
 * L'indication s'affiche SOUS le champ et n'y entre jamais : un placeholder disparaît dès qu'on
 * tape, c'est-à-dire exactement au moment où l'on aurait besoin de relire la règle.
 *
 * L'icône, elle, sert à reconnaître un champ sans lire son libellé — sur un formulaire de dix
 * champs, c'est ce qui permet de retrouver le téléphone d'un coup d'œil.
 */
defineProps({
    label: { type: String, required: true },
    pour: { type: String, default: null },
    erreur: { type: String, default: null },
    indication: { type: String, default: null },
    obligatoire: { type: Boolean, default: false },
});
</script>

<template>
    <div>
        <label
            :for="pour"
            class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-600"
        >
            {{ label }}
            <span v-if="obligatoire" class="text-rose-500">*</span>
        </label>

        <slot />

        <p v-if="erreur" class="mt-1.5 text-xs font-medium text-rose-600">{{ erreur }}</p>
        <p v-else-if="indication" class="mt-1.5 text-xs text-slate-500">{{ indication }}</p>
    </div>
</template>
