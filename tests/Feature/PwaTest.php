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
        ->and($manifeste['start_url'])->toBe('/console');

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
