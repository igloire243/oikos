/**
 * LE SERVICE WORKER DE LA CONSOLE — deux politiques, et surtout ce qu'il ne garde PAS.
 *
 * LES PAGES ne sont JAMAIS mises en cache. La console montre des clients, des clés de licence et
 * des montants : une copie servie depuis le cache mentirait sur un abonnement échu, et resterait
 * lisible sur un téléphone prêté après la déconnexion. Réseau d'abord, toujours ; si le réseau
 * manque, `hors-ligne.html` le dit — plutôt qu'une ancienne page qui aurait l'air à jour.
 *
 * SEULS LES FICHIERS /build/ sont gardés, cache d'abord : chacun porte un hash dans son nom
 * (Vite), donc un fichier en cache est le bon ou a été remplacé par un nouveau nom au build
 * suivant — jamais périmé en silence. Plafond de 500 entrées (le plus ancien part d'abord).
 *
 * Les écritures (POST, PUT, DELETE) et les requêtes d'API passent sans que le worker les touche :
 * une vente ne se rejoue pas toute seule.
 *
 * Sans ce worker et le manifeste, le navigateur ne propose pas d'installer l'application
 * (`beforeinstallprompt` exige les deux, en HTTPS ou sur localhost).
 */
const CACHE_BUILD = 'console-build-v1';
const CACHE_SOCLE = 'console-socle-v1';
const PLAFOND_ENTREES = 500;
const PAGE_HORS_LIGNE = '/hors-ligne.html';
const SOCLE = [PAGE_HORS_LIGNE, '/icons/icon-192.png', '/icons/favicon-32.png'];

self.addEventListener('install', (evenement) => {
    evenement.waitUntil(
        caches
            .open(CACHE_SOCLE)
            .then((cache) => cache.addAll(SOCLE))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (evenement) => {
    evenement.waitUntil(
        caches
            .keys()
            .then((noms) =>
                Promise.all(
                    noms
                        .filter((nom) => nom !== CACHE_BUILD && nom !== CACHE_SOCLE)
                        .map((nom) => caches.delete(nom))
                )
            )
            .then(() => self.clients.claim())
    );
});

async function plafonner(cache) {
    const cles = await cache.keys();
    await Promise.all(cles.slice(0, Math.max(0, cles.length - PLAFOND_ENTREES)).map((cle) => cache.delete(cle)));
}

self.addEventListener('fetch', (evenement) => {
    const requete = evenement.request;
    const url = new URL(requete.url);

    if (requete.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Les routes (`/ziggy.js?v=…`) : l'adresse porte la version, donc une copie gardée est forcément la bonne.
    if (url.pathname.startsWith('/build/') || url.pathname === '/ziggy.js') {
        evenement.respondWith(
            caches.open(CACHE_BUILD).then(async (cache) => {
                const gardee = await cache.match(requete);
                if (gardee) return gardee;

                const reponse = await fetch(requete);
                if (reponse.ok) {
                    await cache.put(requete, reponse.clone());
                    await plafonner(cache);
                }

                return reponse;
            })
        );

        return;
    }

    // Une NAVIGATION (page entière) : réseau seul, et la page hors-ligne si rien ne répond.
    if (requete.mode === 'navigate') {
        evenement.respondWith(
            fetch(requete).catch(() => caches.match(PAGE_HORS_LIGNE).then((page) => page ?? Response.error()))
        );
    }
});

/**
 * LES NOTIFICATIONS PUSH — un fait à traiter (facture en retard, abonnement qui s'achève), jamais
 * un secret : le texte s'affiche sur un écran verrouillé. La charge est `{titre, corps, url}`,
 * posée par `App\Metier\Notifications\PushNotifications`. Un clic rouvre la console déjà
 * ouverte plutôt que d'en empiler une seconde.
 */
self.addEventListener('push', (evenement) => {
    if (!evenement.data) {
        return;
    }

    const { titre, corps, url } = evenement.data.json();

    evenement.waitUntil(
        self.registration.showNotification(titre, {
            body: corps,
            icon: '/icons/icon-192.png',
            badge: '/icons/favicon-48.png',
            data: { url: url ?? '/console' },
        })
    );
});

self.addEventListener('notificationclick', (evenement) => {
    evenement.notification.close();

    const cible = evenement.notification.data?.url ?? '/console';

    evenement.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((fenetres) => {
            const ouverte = fenetres.find((f) => new URL(f.url).origin === self.location.origin);

            if (ouverte) {
                ouverte.navigate(cible);

                return ouverte.focus();
            }

            return self.clients.openWindow(cible);
        })
    );
});
