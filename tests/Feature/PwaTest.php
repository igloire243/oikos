<?php

/**
 * La PWA de la console : tout ce qu'exige le navigateur pour proposer « Installer ».
 *
 * Un manifeste qui pointe sur une icône absente, ou sans icône 512 px, et le navigateur ne propose
 * rien — sans la moindre erreur visible. Ce test est le seul endroit où on s'en aperçoit.
 */
it('sert un manifeste valide dont chaque icône existe', function () {
    $manifeste = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifeste['name'])->not->toBeEmpty()
        ->and($manifeste['display'])->toBe('standalone')
        ->and($manifeste['start_url'])->toBe('/')
        // Blanc, comme le produit : une couleur de thème verte peignait une bande verte sous
        // l'heure et la batterie, par-dessus l'écran.
        ->and($manifeste['theme_color'])->toBe('#ffffff');

    $tailles = [];
    foreach ($manifeste['icons'] as $icone) {
        expect(file_exists(public_path(ltrim($icone['src'], '/'))))->toBeTrue("Icône absente : {$icone['src']}");
        $tailles[] = $icone['sizes'].'/'.$icone['purpose'];
    }

    // Une icône 512 px « any » et une « maskable » : Android recadre la seconde sans rogner le dessin.
    expect($tailles)->toContain('512x512/any')->toContain('512x512/maskable');
});

it('fournit le service worker, la page hors ligne et les favicons', function () {
    foreach (['sw.js', 'hors-ligne.html', 'favicon.ico', 'icons/apple-touch-icon.png', 'icons/favicon-32.png'] as $fichier) {
        expect(file_exists(public_path($fichier)))->toBeTrue("Fichier absent : {$fichier}");
    }

    // Les pages ne doivent JAMAIS être mises en cache : une licence échue lue depuis le cache
    // mentirait, et resterait lisible sur un téléphone prêté.
    expect(file_get_contents(public_path('sw.js')))->not->toContain('CACHE_PAGES');
});

it('déclare le manifeste et le service worker dans la page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('manifest.webmanifest', false)
        ->assertSee('apple-touch-icon', false)
        ->assertSee("register('/sw.js')", false);
});

it("a une image de démarrage pour chaque taille d'iPhone et d'iPad déclarée", function () {
    $page = file_get_contents(resource_path('views/app.blade.php'));

    preg_match_all("#icons/demarrage/(\d+x\d+)\.png#", $page, $trouvees);

    // Une image déclarée mais absente donnerait un écran blanc sur cet appareil, sans erreur.
    expect($trouvees[1])->not->toBeEmpty();

    foreach ($trouvees[1] as $taille) {
        expect(public_path("icons/demarrage/{$taille}.png"))->toBeFile();
    }
});

it('garde le site public DANS le périmètre de l\'application installée', function () {
    $manifeste = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

    // Le bouton « Site public » de la console mène à `/`. Avec un périmètre limité à `/console`, iOS
    // sort de l'application et ouvre la page dans une vue Safari — barre d'adresse et boutons compris,
    // la mise en page d'une page web ordinaire au lieu de celle d'une application (signalé à l'usage).
    expect($manifeste['scope'])->toBe('/')
        ->and($manifeste['start_url'])->toStartWith($manifeste['scope'])
        // L'application s'ouvre sur le site public (demande de l'éditeur : on la montre à un client depuis le
        // téléphone) ; la connexion est un lien de la vitrine. `id` reste `/console` : le changer ferait passer
        // l'application déjà installée pour une AUTRE, qu'il faudrait réinstaller.
        ->and($manifeste['start_url'])->toBe('/')
        ->and($manifeste['id'])->toBe('/console');
});
