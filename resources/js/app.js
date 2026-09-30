import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

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
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
