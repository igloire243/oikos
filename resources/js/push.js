import http from '@/http';

/**
 * LES NOTIFICATIONS PUSH — activées ou non, jamais devinées.
 *
 * Un abonnement est un objet du NAVIGATEUR (`PushManager`), pas de l'application : ce fichier ne
 * fait que le créer côté client puis le confier au serveur (`POST /console/push/abonnements`), qui
 * l'associe au compte connecté. `App\Metier\Notifications\PushNotifications` s'en sert ensuite
 * pour pousser un message à cet appareil précis.
 */

function clePubliqueVapid() {
    return document.querySelector('meta[name="vapid-cle-publique"]')?.content ?? null;
}

/** Le format que `PushManager.subscribe()` exige : un `Uint8Array`, pas la chaîne base64url. */
function versUint8Array(base64Url) {
    const complement = '='.repeat((4 - (base64Url.length % 4)) % 4);
    const base64 = (base64Url + complement).replace(/-/g, '+').replace(/_/g, '/');
    const brut = window.atob(base64);

    return Uint8Array.from([...brut].map((caractere) => caractere.charCodeAt(0)));
}

/** Le navigateur sait-il faire, ET le serveur a-t-il posé ses clés VAPID ? */
export function pushSupporte() {
    return (
        typeof window !== 'undefined' &&
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        clePubliqueVapid() !== null
    );
}

export async function pushActif() {
    if (!pushSupporte()) {
        return false;
    }

    const inscription = await navigator.serviceWorker.ready;
    const abonnement = await inscription.pushManager.getSubscription();

    return abonnement !== null;
}

export async function activerPush() {
    const inscription = await navigator.serviceWorker.ready;

    const abonnement = await inscription.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: versUint8Array(clePubliqueVapid()),
    });

    const donnees = abonnement.toJSON();

    await http.post(route('console.push.abonner'), {
        endpoint: donnees.endpoint,
        cle_p256dh: donnees.keys.p256dh,
        cle_auth: donnees.keys.auth,
    });
}

export async function desactiverPush() {
    const inscription = await navigator.serviceWorker.ready;
    const abonnement = await inscription.pushManager.getSubscription();

    if (!abonnement) {
        return;
    }

    // On désabonne le NAVIGATEUR d'abord : si le serveur ne répond plus, l'appareil arrête quand
    // même de recevoir — c'est ce que la personne vient de demander.
    const endpoint = abonnement.endpoint;
    await abonnement.unsubscribe();
    await http.delete(route('console.push.desabonner'), { data: { endpoint } });
}
