import { router } from '@inertiajs/vue3';

/**
 * LES DONNÉES À JOUR QUAND ON REVIENT SUR UNE PAGE.
 *
 * Signalé par l'utilisateur : sur téléphone, le retour arrière réaffichait la page telle qu'on
 * l'avait quittée. C'est le comportement d'Inertia — au `popstate`, il restaure les props gardées
 * dans l'historique et ne redemande rien au serveur. On approuvait une demande d'adhésion sur
 * l'écran suivant, on revenait, et la demande était toujours là : de quoi croire que le geste
 * n'avait pas pris, et le refaire.
 *
 * Trois façons de revenir sur une page, une seule réponse — un `router.reload()`, qui garde le
 * défilement et l'état local (un formulaire à moitié rempli n'est pas vidé) et ne change que les
 * props :
 *  - le retour ou l'avance de l'historique ;
 *  - une page restaurée par le cache du navigateur (bfcache), qui ne déclenche pas `popstate` ;
 *  - l'application qui revient au premier plan après un moment — une PWA rouverte le lendemain
 *    montrerait sinon les chiffres de la veille.
 *
 * Sans réseau, on ne recharge rien : la page affichée (ou sa copie hors ligne) reste plus utile
 * qu'une erreur.
 */

// En deçà, revenir d'un coup d'œil à une autre application ne vaut pas une requête.
const ABSENCE_AVANT_RAFRAICHIR_MS = 60 * 1000;

let retourDHistorique = false;
let cacheeDepuis = null;

const rafraichir = () => {
    if (!navigator.onLine) return;
    router.reload({ preserveScroll: true, preserveState: true });
};

export function garderLesDonneesAJour() {
    // Le `popstate` arrive avant que la page restaurée soit posée : on attend l'événement
    // `navigate` qu'Inertia émet une fois qu'elle l'est, sinon le rechargement partirait pour
    // l'ADRESSE précédente.
    window.addEventListener('popstate', () => {
        retourDHistorique = true;
    });

    router.on('navigate', () => {
        if (!retourDHistorique) return;
        retourDHistorique = false;
        rafraichir();
    });

    window.addEventListener('pageshow', (evenement) => {
        if (evenement.persisted) rafraichir();
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            cacheeDepuis = Date.now();

            return;
        }

        if (cacheeDepuis !== null && Date.now() - cacheeDepuis > ABSENCE_AVANT_RAFRAICHIR_MS) {
            rafraichir();
        }
        cacheeDepuis = null;
    });
}
