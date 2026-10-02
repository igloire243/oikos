<?php

use App\Metier\Vitrine\Tarifs;
use App\Models\Offre;
use Database\Seeders\OffreSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * LE SITE COMMERCIAL — public, indexable, et fidèle aux offres.
 */
beforeEach(fn () => $this->seed(OffreSeeder::class));

it('sert l\'accueil et les tarifs sans connexion', function () {
    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Public/Accueil'));
    $this->get('/tarifs')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Public/Tarifs'));
});

it('s\'ouvre aux moteurs de recherche, quand la console reste fermée', function () {
    $this->get('/')->assertDontSee('noindex', false)->assertSee('name="description"', false);
    $this->get('/console/login')->assertSee('noindex', false);
});

it('annonce les offres telles qu\'elles se vendent, dans les deux devises', function () {
    $this->get('/tarifs')->assertInertia(fn (Assert $page) => $page
        ->has('tarifs.licences', 3)
        ->has('tarifs.eglises', 3)
        ->has('tarifs.antennes', 3)
        ->where('tarifs.licences.0.prix.USD', fn ($v) => str_contains($v, '$'))
        ->where('tarifs.licences.0.prix.CDF', fn ($v) => str_contains($v, 'FC'))
        ->has('tarifs.licences.0.tranches'));
});

it('ne montre jamais une offre négociée ni une offre retirée', function () {
    Offre::query()->where('code', 'ACCES_EGLISE_STARTER')->update(['publique' => false]);
    Offre::query()->where('code', 'ACCES_EGLISE_STANDARD')->update(['retiree_le' => now()]);

    $codes = collect(Tarifs::catalogue()['eglises'])->pluck('code');

    expect($codes)->not->toContain('ACCES_EGLISE_STARTER')
        ->and($codes)->not->toContain('ACCES_EGLISE_STANDARD')
        ->and($codes)->toContain('ACCES_EGLISE_PREMIUM');
});

it('suit un prix changé sur l\'écran des offres, sans rien à recopier', function () {
    $avant = Tarifs::catalogue()['eglises'][0]['prix']['USD'];

    Offre::query()->where('code', 'ACCES_EGLISE_STARTER')->update(['prix_usd_centimes' => 999900]);

    expect(Tarifs::catalogue()['eglises'][0]['prix']['USD'])->not->toBe($avant);
});

it('liste les mêmes modules que l\'opérateur, et jamais un écran qui ne se vend pas', function () {
    $offre = Offre::query()->where('code', 'ACCES_EGLISE_STARTER')->sole();
    $libelles = collect($offre->modulesParEspace())->flatMap(fn ($g) => $g['modules']);

    expect($libelles)->not->toBeEmpty()
        ->and($libelles->count())->toBe(count($offre->modules ?? []));
});
