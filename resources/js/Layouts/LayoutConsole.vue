<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Bell, BellOff, CircleUserRound, Download, Globe, LogOut, Menu as MenuIcone, X } from 'lucide-vue-next';
import { icone } from '@/Composants/icones.js';
import { useInstallation } from '@/Composables/installation';
import { activerPush, desactiverPush, pushActif, pushRaison, pushSupporte } from '@/push.js';

/**
 * LA MISE EN PAGE DE LA CONSOLE — la même grammaire que les espaces du produit, en plus court.
 *
 * Barre latérale au bureau, tiroir et barre du bas au téléphone (le pouce, pas l'index), messages
 * d'un seul affichage en haut du contenu. Le menu vient du serveur (`App\Metier\Console\Menu`) :
 * la barre latérale et la barre du bas lisent la MÊME liste, et une entrée sans écran reste
 * visible, marquée « à venir », sans lien.
 */
defineProps({
    titre: { type: String, required: true },
});

const page = usePage();
const menu = computed(() => page.props.menu ?? []);
const flash = computed(() => page.props.flash ?? {});
const compte = computed(() => page.props.auth?.user);

const tiroirOuvert = ref(false);
router.on('navigate', () => (tiroirOuvert.value = false));

// Un message ne survit pas à la page suivante ; on laisse quand même le fermer à la main.
const messagesMasques = ref(false);
watch(flash, () => (messagesMasques.value = false));

const entrees = computed(() => menu.value.flatMap((groupe) => groupe.entrees));
const estActive = (entree) =>
    entree.route && route().current(entree.route.replace(/\.index$/, '.*'));

// La barre du bas porte les gestes de tous les jours ; le reste vit dans le tiroir.
const BARRE_DU_BAS = ['accueil', 'clients', 'factures'];
const barreDuBas = computed(() =>
    BARRE_DU_BAS.map((cle) => entrees.value.find((entree) => entree.cle === cle)).filter(Boolean)
);

const seDeconnecter = () => router.post(route('logout'));

const { invite: peutInstaller, installer } = useInstallation();

// Un réglage de l'APPAREIL : la cloche n'existe que si le navigateur ET le serveur savent faire
// (clé VAPID posée), et reflète l'abonnement réel du navigateur — jamais un état deviné.
const pushDisponible = pushSupporte();
const pushExplication = ref(null);
const pushActifIci = ref(false);
const pushEnCours = ref(false);

if (pushDisponible) {
    pushActif().then((actif) => (pushActifIci.value = actif));
}

const basculerPush = async () => {
    if (pushEnCours.value) {
        return;
    }

    // Indisponible : on DIT pourquoi, au lieu de cacher la cloche et de laisser chercher.
    if (!pushDisponible) {
        pushExplication.value = pushExplication.value ? null : pushRaison();

        return;
    }

    pushEnCours.value = true;

    try {
        if (pushActifIci.value) {
            await desactiverPush();
            pushActifIci.value = false;
        } else {
            await activerPush();
            pushActifIci.value = true;
        }
    } catch {
        // Permission refusée : l'état reste celui du navigateur.
        pushActifIci.value = await pushActif();
    } finally {
        pushEnCours.value = false;
    }
};
</script>

<template>
    <!-- SUR TÉLÉPHONE, LA PAGE NE DÉFILE PAS : c'est la zone du milieu, entre l'en-tête et la barre du
         bas. Le défilement de la FENÊTRE est un indicateur que le système peint par-dessus les
         barres fixes — sur toute la hauteur de l'écran, et presque pleine quand la page ne dépasse
         que de quelques pixels (`min-h-screen` + marges). Aucun CSS ne le masque ; seul un
         conteneur qui défile lui-même (`scroll-region`, que le CSS peut taire) en est exempt. -->
    <div
        class="min-h-screen bg-slate-50 max-lg:flex max-lg:h-[100dvh] max-lg:min-h-0 max-lg:flex-col max-lg:overflow-hidden"
    >
        <Head :title="titre" />

        <transition
            enter-active-class="transition-opacity ease-out duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity ease-in duration-150"
            leave-to-class="opacity-0"
        >
            <div
                v-if="tiroirOuvert"
                class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm lg:hidden"
                @click="tiroirOuvert = false"
            />
        </transition>

        <!-- ===================== Barre latérale ===================== -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-[85vw] max-w-xs flex-col border-r border-slate-200 bg-white transition-transform duration-300 ease-out lg:w-64 lg:translate-x-0"
            :class="tiroirOuvert ? 'translate-x-0 shadow-2xl' : '-translate-x-full'"
        >
            <div class="marque-degrade relative overflow-hidden">
                <div class="relative flex items-center gap-3 px-4 py-5">
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white text-lg font-black text-[color:var(--marque-700)] ring-2 ring-white/40"
                        >O</span
                    >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold leading-tight text-white">Oikos</p>
                        <p class="truncate text-xs font-medium text-white/80">
                            Console de l'éditeur
                        </p>
                    </div>
                    <button
                        type="button"
                        class="ml-auto rounded-lg p-1.5 text-white/80 transition hover:bg-white/20 hover:text-white lg:hidden"
                        @click="tiroirOuvert = false"
                    >
                        <span class="sr-only">Fermer le menu</span>
                        <X class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <nav class="defilement-discret flex-1 space-y-6 overflow-y-auto px-3 py-5">
                <div v-for="(groupe, index) in menu" :key="index">
                    <p
                        v-if="groupe.titre"
                        class="px-3 pb-2 text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-slate-400"
                    >
                        {{ groupe.titre }}
                    </p>
                    <ul class="space-y-1">
                        <li v-for="entree in groupe.entrees" :key="entree.cle">
                            <Link
                                v-if="entree.route"
                                :href="route(entree.route)"
                                class="relative flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium transition lg:py-2.5"
                                :class="
                                    estActive(entree)
                                        ? 'bg-[color:var(--marque-50)] text-[color:var(--marque-700)]'
                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                                "
                            >
                                <span
                                    v-if="estActive(entree)"
                                    class="absolute inset-y-1.5 left-0 w-1 rounded-r-full bg-[color:var(--marque-500)]"
                                />
                                <component
                                    :is="icone(entree.icone)"
                                    class="h-5 w-5 shrink-0"
                                    :class="
                                        estActive(entree)
                                            ? 'text-[color:var(--marque-600)]'
                                            : 'text-slate-400'
                                    "
                                    :stroke-width="2"
                                />
                                <span class="truncate">{{ entree.libelle }}</span>
                            </Link>
                            <span
                                v-else
                                class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-slate-300 lg:py-2.5"
                                title="Cet écran arrive avec un prochain lot."
                            >
                                <component
                                    :is="icone(entree.icone)"
                                    class="h-5 w-5 shrink-0"
                                    :stroke-width="2"
                                />
                                <span class="min-w-0 truncate">{{ entree.libelle }}</span>
                                <span
                                    class="ml-auto shrink-0 whitespace-nowrap rounded-full bg-slate-100 px-2 py-0.5 text-[0.6rem] font-semibold uppercase tracking-wide text-slate-400"
                                    >à venir</span
                                >
                            </span>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>

        <!-- ===================== Contenu ===================== -->
        <div class="max-lg:flex max-lg:min-h-0 max-lg:flex-1 max-lg:flex-col lg:pl-64">
            <header
                class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6"
            >
                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
                    @click="tiroirOuvert = true"
                >
                    <span class="sr-only">Ouvrir le menu</span>
                    <MenuIcone class="h-5 w-5" />
                </button>

                <h1 class="min-w-0 truncate text-sm font-semibold text-slate-800 sm:text-base">
                    {{ titre }}
                </h1>

                <div class="ml-auto flex items-center gap-1">
                    <div class="relative">
                        <button
                            type="button"
                            class="rounded-xl p-2 hover:bg-slate-100"
                            :class="[
                                pushDisponible && pushActifIci ? 'text-[color:var(--marque-700)]' : pushDisponible ? 'text-slate-400' : 'text-slate-300',
                                pushEnCours ? 'opacity-50' : '',
                            ]"
                            :disabled="pushEnCours"
                            :title="!pushDisponible ? 'Notifications indisponibles — voir pourquoi' : pushActifIci ? 'Notifications activées — cliquer pour couper' : 'Activer les notifications'"
                            @click="basculerPush"
                        >
                            <span class="sr-only">Notifications</span>
                            <component :is="pushActifIci ? Bell : BellOff" class="h-5 w-5" />
                        </button>
                        <div
                            v-if="pushExplication"
                            class="absolute right-0 top-full z-50 mt-2 w-72 max-w-[85vw] rounded-xl border border-slate-200 bg-white p-3 text-xs leading-relaxed text-slate-700 shadow-lg"
                            role="status"
                        >
                            {{ pushExplication }}
                            <button type="button" class="mt-2 block font-semibold text-slate-900 underline" @click="pushExplication = null">
                                Fermer
                            </button>
                        </div>
                    </div>
                    <!-- Balise <a> nue et adresse RELATIVE, pas <Link> ni route() : le site public est une autre mise en
                         page, et une navigation Inertia gardait l'état de celle de la console (classes de <html>, variables
                         de couleur) — d'où une présentation cassée. Un chargement complet repart d'une page propre, et
                         une adresse relative reste sur l'origine courante : une adresse absolue bâtie sur APP_URL
                         (127.0.0.1) sortait de l'application installée depuis l'IP du réseau, et iOS affichait alors les
                         boutons de Safari. -->
                    <!-- Le site public : un opérateur y va pour voir ce que voit un visiteur — les tarifs, le
                         formulaire de demande. -->
                    <a
                        href="/"
                        class="flex items-center gap-1.5 rounded-xl px-2.5 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100"
                        title="Voir le site public"
                    >
                        <Globe class="h-5 w-5" />
                        <span class="hidden sm:inline">Site public</span>
                    </a>
                    <button
                        v-if="peutInstaller"
                        type="button"
                        class="flex items-center gap-1.5 rounded-xl px-2.5 py-1.5 text-sm font-medium text-[color:var(--marque-700)] hover:bg-[color:var(--marque-50)]"
                        title="Installer l'application"
                        @click="installer"
                    >
                        <Download class="h-5 w-5" />
                        <span class="hidden sm:inline">Installer</span>
                    </button>
                    <Link
                        :href="route('profile.show')"
                        class="flex items-center gap-2 rounded-xl px-2 py-1.5 text-sm text-slate-600 hover:bg-slate-100"
                        :title="compte?.email"
                    >
                        <CircleUserRound class="h-5 w-5 text-slate-400" />
                        <span class="hidden max-w-[10rem] truncate sm:inline">{{
                            compte?.name
                        }}</span>
                    </Link>
                    <!-- La déconnexion est visible, pas enterrée dans un menu : la console
                         détient la clé qui signe les licences, on ne la laisse pas ouverte. -->
                    <button
                        type="button"
                        class="rounded-xl p-2 text-slate-500 hover:bg-slate-100"
                        title="Se déconnecter"
                        @click="seDeconnecter"
                    >
                        <span class="sr-only">Se déconnecter</span>
                        <LogOut class="h-5 w-5" />
                    </button>
                </div>
            </header>

            <main
                scroll-region
                class="defilement-discret px-4 pb-6 pt-4 max-lg:min-h-0 max-lg:flex-1 max-lg:overflow-y-auto max-lg:overscroll-contain sm:px-6 lg:px-8 lg:pb-12 lg:pt-6"
            >
                <div v-if="!messagesMasques" class="space-y-2">
                    <div
                        v-for="(message, ton) in {
                            succes: flash.succes,
                            avertissement: flash.avertissement,
                            erreur: flash.erreur,
                        }"
                        v-show="message"
                        :key="ton"
                        class="apparition mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm"
                        :class="{
                            'border-emerald-200 bg-emerald-50 text-emerald-800': ton === 'succes',
                            'border-amber-200 bg-amber-50 text-amber-800': ton === 'avertissement',
                            'border-rose-200 bg-rose-50 text-rose-800': ton === 'erreur',
                        }"
                    >
                        <p class="min-w-0 flex-1">{{ message }}</p>
                        <button
                            type="button"
                            class="shrink-0 opacity-60 hover:opacity-100"
                            @click="messagesMasques = true"
                        >
                            <span class="sr-only">Fermer</span>
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <slot />
            </main>
        </div>

        <!-- ===================== Barre du bas (téléphone) ===================== -->
        <nav
            class="z-40 shrink-0 border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden"
        >
            <ul class="grid grid-cols-4">
                <li v-for="entree in barreDuBas" :key="entree.cle">
                    <Link
                        v-if="entree.route"
                        :href="route(entree.route)"
                        class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-[0.7rem] font-medium"
                        :class="
                            estActive(entree) ? 'text-[color:var(--marque-700)]' : 'text-slate-500'
                        "
                    >
                        <component :is="icone(entree.icone)" class="h-5 w-5" />
                        <span class="truncate">{{
                            entree.cle === 'accueil' ? 'Accueil' : entree.libelle.split(' ')[0]
                        }}</span>
                    </Link>
                    <span
                        v-else
                        class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-[0.7rem] font-medium text-slate-300"
                    >
                        <component :is="icone(entree.icone)" class="h-5 w-5" />
                        <span class="truncate">{{ entree.libelle.split(' ')[0] }}</span>
                    </span>
                </li>
                <li>
                    <button
                        type="button"
                        class="flex min-h-14 w-full flex-col items-center justify-center gap-0.5 text-[0.7rem] font-medium text-slate-500"
                        @click="tiroirOuvert = true"
                    >
                        <MenuIcone class="h-5 w-5" />
                        <span>Menu</span>
                    </button>
                </li>
            </ul>
        </nav>
    </div>
</template>
