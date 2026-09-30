<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, Eye, EyeOff, Lock } from 'lucide-vue-next';
import AuthDivise from '@/Layouts/AuthDivise.vue';
import Bouton from '@/Composants/Bouton.vue';
import ChampTexte from '@/Composants/ChampTexte.vue';

/**
 * LA CONNEXION DES OPÉRATEURS DE LA CONSOLE. Pas de lien « créer un compte » : il n'y a pas
 * d'inscription (voir config/fortify.php), un compte se crée par `php artisan oikos:operateur`.
 */
defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({ email: '', password: '', remember: false });
const motDePasseVisible = ref(false);

const submit = () => {
    form.transform((data) => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Se connecter" />

    <AuthDivise
        titre="Vendre, facturer, encaisser — et savoir où en est chaque église."
        description="Les clients, leurs installations, les abonnements par entité et les licences signées : tout ce qui se vend du produit se pilote d'ici."
    >
        <h2 class="text-xl font-black tracking-tight text-gray-900">Espace de connexion 🔐</h2>
        <p class="mt-1 text-sm text-gray-500">Réservé aux opérateurs de la console.</p>

        <div
            v-if="status"
            class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
        >
            {{ status }}
        </div>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <ChampTexte
                id="email"
                v-model="form.email"
                label="Adresse e-mail"
                type="email"
                icone="at-sign"
                obligatoire
                autocomplete="username"
                :erreur="form.errors.email"
            />

            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label
                        for="password"
                        class="block text-xs font-semibold uppercase tracking-wide text-slate-500"
                    >
                        Mot de passe
                    </label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-xs font-semibold"
                        :style="{ color: 'var(--marque-700)' }"
                    >
                        Oublié ?
                    </Link>
                </div>
                <div class="relative">
                    <Lock
                        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        id="password"
                        v-model="form.password"
                        :type="motDePasseVisible ? 'text' : 'password'"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-xl border-slate-300 py-2 pl-9 pr-10 text-sm shadow-sm transition focus:border-slate-400 focus:ring-2"
                        :class="form.errors.password ? 'border-rose-300 focus:border-rose-400' : ''"
                        :style="{ '--tw-ring-color': 'var(--marque-200)' }"
                    />
                    <button
                        type="button"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                        :aria-label="
                            motDePasseVisible
                                ? 'Masquer le mot de passe'
                                : 'Afficher le mot de passe'
                        "
                        @click="motDePasseVisible = !motDePasseVisible"
                    >
                        <EyeOff v-if="motDePasseVisible" class="h-4 w-4" />
                        <Eye v-else class="h-4 w-4" />
                    </button>
                </div>
                <p v-if="form.errors.password" class="mt-1.5 text-xs text-rose-600">
                    {{ form.errors.password }}
                </p>
            </div>

            <label class="flex cursor-pointer items-center gap-2">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-[color:var(--marque-600)] focus:ring-[color:var(--marque-500)]"
                />
                <span class="select-none text-sm text-gray-600">Se souvenir de moi</span>
            </label>

            <Bouton
                type="submit"
                class="w-full justify-center gap-1.5"
                :desactive="form.processing"
            >
                Se connecter <ArrowRight class="h-4 w-4" />
            </Bouton>
        </form>
    </AuthDivise>
</template>
