<?php

/**
 * `route()` EST APPELÉE TELLE QUELLE DANS LES SCRIPTS DES PAGES, pas seulement dans les gabarits.
 *
 * Elle venait du script inline de `@routes`. Le jour où les routes sont devenues un fichier (`/ziggy.js`), la
 * fonction n'était plus définie : la connexion, puis tout appel de script, échouait avec « route is not defined »,
 * sans qu'aucun test PHP ne le voie. Ce test lit le point d'entrée : il échoue si l'on retire l'affectation.
 */
it('définit route() globalement dans le point d\'entrée JavaScript', function () {
    $source = file_get_contents(resource_path('js/app.js'));

    expect($source)->toContain('window.route = route');
});
