import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * INSTALLER L'APPLICATION — le bouton n'existe que si le navigateur propose vraiment l'installation.
 *
 * `beforeinstallprompt` n'est émis que si le manifeste est valide, qu'un service worker est
 * enregistré, que la page est en HTTPS (ou localhost) et que l'application n'est pas déjà
 * installée. Afficher un bouton « Installer » sans cet événement ferait un bouton inerte — la
 * même promesse non tenue que la caméra masquée sans un mot.
 *
 * iOS n'émet jamais l'événement : Safari ne s'installe que par « Partager → Sur l'écran
 * d'accueil ». On le dit, au lieu de laisser croire que l'installation est impossible.
 */
export function useInstallation() {
    const invite = ref(null);
    const installee = ref(false);
    const surIos = ref(false);

    const retenir = (evenement) => {
        evenement.preventDefault();
        invite.value = evenement;
    };
    const constater = () => {
        installee.value = true;
        invite.value = null;
    };

    onMounted(() => {
        installee.value =
            window.matchMedia('(display-mode: standalone)').matches ||
            window.navigator.standalone === true;
        surIos.value = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
        window.addEventListener('beforeinstallprompt', retenir);
        window.addEventListener('appinstalled', constater);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('beforeinstallprompt', retenir);
        window.removeEventListener('appinstalled', constater);
    });

    const installer = async () => {
        if (!invite.value) return;
        await invite.value.prompt();
        invite.value = null;
    };

    return { invite, installee, surIos, installer };
}
