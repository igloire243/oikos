import axios from 'axios';

/**
 * Le client HTTP du projet, configure une seule fois.
 *
 * POURQUOI CE FICHIER EXISTE
 * --------------------------
 * Le squelette Laravel 13 ne livre plus `resources/js/bootstrap.js`, qui posait autrefois
 * `window.axios`. Les composants publies par Jetstream, eux, appellent encore `axios` comme s'il
 * etait global : l'ecran de double authentification levait donc « axios is not defined » et
 * n'affichait jamais son QR code — une panne silencieuse, puisque rien ne casse tant qu'on
 * n'ouvre pas cette section du profil.
 *
 * On expose une instance nommee plutot que de restaurer un global : ce qui est importe se lit
 * dans le fichier qui s'en sert, et l'analyse statique peut le verifier.
 */
const http = axios.create({
    headers: {
        // Laravel lit cet en-tete pour repondre en JSON plutot que par une redirection HTML.
        'X-Requested-With': 'XMLHttpRequest',
    },

    // Renvoie le cookie XSRF-TOKEN dans l'en-tete X-XSRF-TOKEN. Sans lui, toute requete
    // d'ecriture — la regeneration des codes de secours, par exemple — repartirait en 419.
    withCredentials: true,
    withXSRFToken: true,
});

export default http;
