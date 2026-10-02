import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/**
 * PAS DE `safelist` DE COULEURS DE MARQUE, ET C'EST VOULU.
 *
 * Le projet de référence en avait besoin parce qu'il composait des noms de classes à l'exécution
 * (`bg-brand-orange-500`, `bg-brand-blue-600`…) : Tailwind ne les voit pas par analyse statique, il
 * fallait donc les lui déclarer. Ici, la couleur de l'espace passe entièrement par des variables
 * CSS (`var(--marque-600)`), posées par la mise en page. Aucune classe n'est composée,
 * donc il n'y a rien à mettre en safelist — et rien à oublier d'y mettre le jour où un sixième
 * espace apparaît.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        // Les .js aussi : une classe écrite en toutes lettres dans un module (une table de tons,
        // par exemple) n'existerait dans aucune feuille si Tailwind ne lisait pas ce fichier.
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            // Les composants Jetstream (profil, authentification) sont écrits en `indigo` : ils
            // suivent la couleur de la console au lieu de rester violets au milieu d'un écran vert.
            colors: {
                indigo: Object.fromEntries(
                    [50, 100, 200, 300, 400, 500, 600, 700, 800, 900].map((rang) => [
                        rang,
                        `var(--marque-${rang})`,
                    ])
                ),
            },

            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },

            // Deux ombres, pas douze. `card` au repos, `card-hover` au survol : une carte qui se
            // soulève légèrement suffit à dire qu'elle est cliquable.
            boxShadow: {
                card: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.06)',
                'card-hover': '0 10px 30px -12px rgb(15 23 42 / 0.18)',
            },
        },
    },

    plugins: [forms, typography],
};
