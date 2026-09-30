<script setup>
import { onBeforeUnmount, onMounted, watch } from 'vue';
import { X } from 'lucide-vue-next';

/**
 * UNE MODALE.
 *
 * Fermée par la croix, par le voile et par la touche Échap — les trois, parce qu'une modale qui
 * ne se ferme que d'une seule façon finit toujours par piéger quelqu'un.
 *
 * Le défilement de la page est bloqué tant qu'elle est ouverte : sans ça, faire défiler dans la
 * modale fait glisser la page derrière, et on perd sa place en la refermant.
 */
const props = defineProps({
    ouverte: { type: Boolean, default: false },
    titre: { type: String, default: null },
    sousTitre: { type: String, default: null },
    largeur: { type: String, default: 'lg' },
});

const emit = defineEmits(['fermer']);

const largeurs = {
    sm: 'max-w-md',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
};

const surEchap = (evenement) => {
    if (evenement.key === 'Escape' && props.ouverte) {
        emit('fermer');
    }
};

watch(
    () => props.ouverte,
    (ouverte) => {
        document.body.style.overflow = ouverte ? 'hidden' : '';
    }
);

onMounted(() => document.addEventListener('keydown', surEchap));
onBeforeUnmount(() => {
    document.removeEventListener('keydown', surEchap);
    document.body.style.overflow = '';
});
</script>

<template>
    <teleport to="body">
        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition ease-in duration-150"
            leave-to-class="opacity-0"
        >
            <div v-if="ouverte" class="fixed inset-0 z-[60] overflow-y-auto overscroll-contain">
                <div
                    class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm"
                    @click="$emit('fermer')"
                />

                <!--
                    SUR TÉLÉPHONE, UNE FEUILLE QUI MONTE DU BAS — collée au bord, toute la largeur,
                    ses boutons en bas où le pouce les trouve, et le formulaire qui défile ENTRE
                    l'en-tête et les boutons plutôt que d'emporter les boutons hors de l'écran.
                    `dvh` et non `vh` : sur mobile, `vh` compte la barre d'adresse qui se replie, et
                    la feuille débordait d'autant.
                -->
                <div class="flex min-h-full items-end justify-center sm:items-center sm:p-4">
                    <div
                        class="apparition relative flex max-h-[92dvh] w-full flex-col rounded-t-3xl bg-white shadow-2xl sm:max-h-none sm:rounded-2xl"
                        :class="largeurs[largeur] ?? largeurs.lg"
                    >
                        <span
                            class="mx-auto mt-2.5 h-1.5 w-10 shrink-0 rounded-full bg-slate-200 sm:hidden"
                            aria-hidden="true"
                        />
                        <div
                            v-if="titre"
                            class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-4 py-3 sm:px-6 sm:py-4"
                        >
                            <div class="min-w-0">
                                <h2 class="font-bold tracking-tight text-slate-900">{{ titre }}</h2>
                                <p v-if="sousTitre" class="mt-0.5 text-sm text-slate-500">
                                    {{ sousTitre }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="-mr-1.5 rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                                @click="$emit('fermer')"
                            >
                                <span class="sr-only">Fermer</span>
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div
                            class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:overflow-visible sm:px-6 sm:py-5"
                        >
                            <slot />
                        </div>

                        <div
                            v-if="$slots.actions"
                            class="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-slate-50/60 px-4 pb-[calc(0.75rem+env(safe-area-inset-bottom))] pt-3 sm:px-6 sm:py-4 [&>*]:flex-1 sm:[&>*]:flex-none"
                        >
                            <slot name="actions" />
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>
