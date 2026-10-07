import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { route, ZiggyVue } from '../../vendor/tightenco/ziggy';

// `route()` est aussi appelée telle quelle dans les scripts des pages (pas seulement dans les gabarits). Elle venait du
// script inline de `@routes` ; les routes étant désormais servies par `/ziggy.js`, la fonction n'était plus définie et
// TOUT appel depuis un script (connexion comprise) échouait avec « route is not defined ».
window.route = route;
import { garderLesDonneesAJour } from './fraicheur';

const appName = import.meta.env.VITE_APP_NAME || 'Oikos Console';

// PAS DE ZOOM AU PINCEMENT. Safari (iOS) ignore `user-scalable=no` depuis iOS 10 : ses gestes
// passent par `gesturestart`, et un pincement à deux doigts par `touchmove` avec `scale`.
// Voir app.css : les tailles suivent déjà l'appareil.
const bloquerLeZoom = (evenement) => evenement.preventDefault();
document.addEventListener('gesturestart', bloquerLeZoom, { passive: false });
document.addEventListener(
    'touchmove',
    (evenement) => {
        if (evenement.scale !== undefined && evenement.scale !== 1) {
            evenement.preventDefault();
        }
    },
    { passive: false }
);

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        // Au retour arrière, Inertia réaffiche les données gardées dans l'historique : un client ou
        // une facture modifiés sur l'écran suivant restaient périmés (voir fraicheur.js).
        garderLesDonneesAJour();

        const application = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);

        // L'écran de démarrage de l'application installée (app.blade.php) s'efface une fois Vue
        // monté ; sans cette ligne, un délai de six secondes le retire quand même.
        document.getElementById('demarrage')?.classList.add('fini');

        return application;
    },
    progress: {
        color: '#4B5563',
    },
});
