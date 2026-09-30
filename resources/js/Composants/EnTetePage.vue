<script setup>
/**
 * L'EN-TÊTE D'UNE PAGE.
 *
 * Ce n'est pas un bandeau plat : un dégradé de la couleur de l'espace, deux formes organiques en
 * surimpression, et une vague qui raccorde au fond de page. C'est le motif qui donne son identité
 * à l'interface — un aplat uni à la place, et tout l'écran retombe dans le générique.
 *
 * La vague est remplie de la couleur du fond de page : elle ne « dessine » pas une vague, elle
 * DÉCOUPE le bandeau. Les écrans admin reposent sur `#f8fafc` (slate-50, le défaut) ; le site
 * public est sur blanc et doit passer `fond-vague="#ffffff"` — sinon un liseré gris apparaît sous
 * l'en-tête, visible mais sans la moindre erreur, puisqu'une couleur qui ne correspond pas au
 * fond ne casse rien, elle se voit juste.
 *
 * PAS D'OMBRE, ET PAS DE COINS ARRONDIS EN BAS. Une ombre est toujours RECTANGULAIRE — y compris
 * ses coins — alors que le bord visible du bandeau est une vague. Le rectangle réel descend plus
 * bas que la courbe, et deux choses le trahissaient : l'ombre (retirée) ET les coins arrondis du
 * bas, dont la courbe reste visible sous la vague même sans ombre — `rounded-2xl` est donc devenu
 * `rounded-t-2xl` : le HAUT seul est arrondi, le bas est un bord droit que la vague masque déjà,
 * exactement de la couleur du fond.
 */
defineProps({
    titre: { type: String, required: true },
    sousTitre: { type: String, default: null },
    icone: { type: [Object, Function], default: null },
    /** Un chip au-dessus du titre — utilisé par le site public, jamais par les écrans admin. */
    badge: { type: String, default: null },
    fondVague: { type: String, default: '#f8fafc' },
});
</script>

<template>
    <div class="marque-degrade relative mb-5 overflow-hidden rounded-t-2xl sm:mb-8">
        <svg
            class="pointer-events-none absolute -right-16 -top-24 h-72 w-72 opacity-[0.18]"
            viewBox="0 0 200 200"
            aria-hidden="true"
        >
            <path
                fill="#fff"
                d="M45.3,-63.7C57.6,-55.4,65.3,-40.3,70.4,-24.7C75.5,-9,78,7.3,73.2,21.2C68.4,35.1,56.3,46.6,43,55.6C29.7,64.6,15,71.1,-0.9,72.3C-16.7,73.6,-33.5,69.6,-46.6,60.3C-59.7,51,-69.2,36.4,-73.3,20.4C-77.5,4.4,-76.3,-13,-69.4,-27.4C-62.5,-41.8,-49.9,-53.2,-36.3,-61.2C-22.6,-69.2,-8,-73.8,5.9,-71.9C19.7,-70,33,-72,45.3,-63.7Z"
                transform="translate(100 100)"
            />
        </svg>
        <svg
            class="pointer-events-none absolute -bottom-20 -left-10 h-56 w-56 opacity-[0.12]"
            viewBox="0 0 200 200"
            aria-hidden="true"
        >
            <path
                fill="#fff"
                d="M38.8,-55.6C50.5,-47.9,60.1,-36.6,66.5,-23.1C72.9,-9.6,76.1,6,72.1,19.6C68.1,33.2,56.9,44.7,44.1,53.6C31.3,62.5,16.9,68.8,1.3,67.1C-14.4,65.4,-31.1,55.7,-44.3,44.1C-57.5,32.5,-67.2,19,-69.7,4.1C-72.2,-10.8,-67.5,-27.1,-57.6,-38.6C-47.6,-50.1,-32.4,-56.8,-18.1,-62.5C-3.8,-68.2,9.6,-72.9,22.6,-70C35.6,-67.1,27.1,-63.3,38.8,-55.6Z"
                transform="translate(100 100)"
            />
        </svg>

        <div
            class="relative flex flex-col gap-3 px-4 py-5 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-8 sm:py-7"
        >
            <div class="flex items-center gap-3 sm:items-start sm:gap-4">
                <div
                    v-if="icone"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/20 ring-1 ring-white/30 backdrop-blur sm:h-12 sm:w-12"
                >
                    <component
                        :is="icone"
                        class="h-5 w-5 text-white sm:h-6 sm:w-6"
                        :stroke-width="1.9"
                    />
                </div>

                <div class="min-w-0">
                    <span
                        v-if="badge"
                        class="mb-2 inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white ring-1 ring-white/25"
                    >
                        {{ badge }}
                    </span>
                    <h1
                        class="text-lg font-bold leading-tight tracking-tight text-white sm:text-2xl"
                    >
                        {{ titre }}
                    </h1>
                    <p
                        v-if="sousTitre"
                        class="mt-0.5 line-clamp-2 max-w-2xl text-xs text-white/85 sm:mt-1 sm:text-sm"
                    >
                        {{ sousTitre }}
                    </p>
                </div>
            </div>

            <!-- Sur téléphone, les actions s'étirent pour se viser au doigt — mais sur UNE ligne
                 de texte chacune, plus petites : trois boutons partageant la largeur coupaient
                 « Inscrire un membre » sur trois lignes et faisaient des blocs énormes (signalé
                 par l'utilisateur). Quand ils ne tiennent pas sur une rangée, ils passent à la
                 suivante, entiers. -->
            <div
                v-if="$slots.actions"
                class="flex shrink-0 flex-wrap items-center gap-2 [&>*]:grow [&>*]:whitespace-nowrap max-sm:[&>*]:min-h-10 max-sm:[&>*]:px-3 max-sm:[&>*]:py-2 max-sm:[&>*]:text-[13px] sm:[&>*]:grow-0"
            >
                <slot name="actions" />
            </div>
        </div>

        <svg
            class="relative -mb-px block w-full"
            viewBox="0 0 1440 60"
            preserveAspectRatio="none"
            aria-hidden="true"
        >
            <!-- `vague-admin` : en mode sombre, la vague prend le fond de page sombre — sinon un
                 liseré clair réapparaît sous chaque en-tête, le piège déjà noté pour la vitrine. -->
            <path
                :class="fondVague === '#f8fafc' ? 'vague-admin' : ''"
                :fill="fondVague"
                d="M0,32L60,37.3C120,43,240,53,360,50.7C480,48,600,32,720,26.7C840,21,960,27,1080,32C1200,37,1320,43,1380,45.3L1440,48L1440,60L0,60Z"
            />
        </svg>
    </div>
</template>
