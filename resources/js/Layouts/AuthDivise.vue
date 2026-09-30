<script setup>
/**
 * LE GABARIT DES ÉCRANS DE CONNEXION — le même « split-screen » que le produit, pour que les deux
 * applications se reconnaissent. Panneau sombre à gauche (dégradé maison, aucune image externe qui
 * pourrait disparaître), formulaire à droite, et sur téléphone le formulaire seul.
 */
defineProps({
    badge: { type: String, default: "Console de l'éditeur" },
    titre: { type: String, required: true },
    description: { type: String, required: true },
});
</script>

<template>
    <div class="grid min-h-screen grid-cols-1 lg:grid-cols-12">
        <div
            class="relative hidden flex-col justify-between overflow-hidden bg-gray-950 p-10 lg:col-span-7 lg:flex xl:p-14"
        >
            <div
                class="pointer-events-none absolute inset-0 opacity-40"
                :style="{
                    backgroundImage:
                        'radial-gradient(circle at 15% 20%, var(--marque-600), transparent 45%), radial-gradient(circle at 85% 75%, var(--marque-800), transparent 50%), radial-gradient(circle at 50% 100%, var(--marque-900), transparent 60%)',
                }"
            />
            <div
                class="pointer-events-none absolute inset-0 bg-gradient-to-tr from-gray-950 via-gray-950/90 to-gray-900/70"
            />

            <div class="relative z-10 flex items-center gap-2.5">
                <span
                    class="flex h-11 w-11 items-center justify-center rounded-full text-lg font-black text-white"
                    :style="{ backgroundColor: 'var(--marque-600)' }"
                    >O</span
                >
                <span class="font-black tracking-tight text-white">Oikos</span>
            </div>

            <div class="relative z-10 max-w-lg">
                <span
                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wider"
                    :style="{ backgroundColor: 'var(--marque-500)', color: '#fff' }"
                >
                    {{ badge }}
                </span>
                <h1
                    class="mt-5 text-3xl font-black leading-tight tracking-tight text-white xl:text-4xl"
                >
                    {{ titre }}
                </h1>
                <p class="mt-4 text-sm leading-relaxed text-gray-300">
                    {{ description }}
                </p>
            </div>

            <div class="relative z-10 border-t border-white/10 pt-5 text-xs text-gray-400">
                © {{ new Date().getFullYear() }} Oikos
            </div>
        </div>

        <div class="col-span-1 flex items-center justify-center bg-white px-4 py-10 lg:col-span-5">
            <div class="w-full max-w-md">
                <slot />
            </div>
        </div>
    </div>
</template>
