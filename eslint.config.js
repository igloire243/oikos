import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import pluginVue from 'eslint-plugin-vue';
import globals from 'globals';

export default [
    {
        ignores: ['public/**', 'vendor/**', 'node_modules/**', 'storage/**'],
    },

    js.configs.recommended,
    ...pluginVue.configs['flat/recommended'],

    // Doit rester EN DERNIER : desactive toutes les regles de mise en forme d'ESLint qui
    // entreraient en conflit avec Prettier. Sans ca, les deux outils se corrigent mutuellement
    // en boucle et `npm run lint` ne converge jamais.
    prettier,

    {
        files: ['**/*.{js,vue}'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                // Fournis par Ziggy, qui expose les routes nommees de Laravel au JavaScript.
                route: 'readonly',
                Ziggy: 'readonly',
            },
        },
        rules: {
            // Desactivee volontairement : les composants publies par Jetstream portent des noms
            // d'un seul mot (Modal, Banner, Dropdown, Checkbox...). Les renommer pour satisfaire
            // une regle de style couperait la correspondance avec l'amont a chaque mise a jour
            // de Jetstream, pour un gain nul.
            'vue/multi-word-component-names': 'off',
        },
    },

    // Une page et un composant n'ont pas le meme contrat, donc pas la meme exigence.
    //
    // Un COMPOSANT est appele par plusieurs ecrans, chacun lui passant ce qu'il veut. Il doit
    // savoir se debrouiller seul : c'est le piege le plus couteux de l'ancien projet, ou une
    // modale partagee recevait `roles` tantot comme liste de roles attribuables, tantot comme
    // repartition statistique — d'ou un ->first() sur un tableau. La regle reste donc active ici.
    //
    // Une PAGE, elle, ne recoit ses proprietes que d'un endroit : le controleur Inertia qui la
    // rend. Lui inventer une valeur par defaut masquerait une propriete oubliee cote serveur au
    // lieu de la faire echouer bruyamment.
    {
        files: ['resources/js/Pages/**/*.vue'],
        rules: {
            'vue/require-default-prop': 'off',

            // Meme raison, poussee plus loin : les proprietes d'une page arrivent en JSON depuis
            // le controleur, et Vue NE NORMALISE PAS les cles d'un objet JavaScript — seulement
            // les attributs d'un gabarit. Une prop declaree `comptesDisponibles` face a une cle
            // `comptes_disponibles` reste donc a sa valeur par defaut, en silence.
            //
            // Ca s'est produit quatre fois avant d'etre vu : le select « Affecter un berger »
            // etait vide depuis sa livraison. Le nom de la prop d'une page EST la cle du
            // controleur, snake_case compris.
            'vue/prop-name-casing': 'off',
        },
    },
];
