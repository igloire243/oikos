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

    if (url.pathname.startsWith('/build/')) {
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
