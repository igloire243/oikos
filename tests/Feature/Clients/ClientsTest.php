<?php

use App\Models\Client;
use App\Models\EntreeJournal;
use App\Models\Installation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * LES CLIENTS ET LEURS INSTALLATIONS — côté opérateur.
 */
it("n'ouvre rien à un visiteur", function () {
    $client = Client::factory()->create();

    $this->get(route('console.clients.index'))->assertRedirect('/console/login');
    $this->post(route('console.clients.store'), ['nom' => 'X'])->assertRedirect('/console/login');
    $this->post(route('console.clients.installations.store', $client))->assertRedirect('/console/login');
});

it('crée un client et une installation, tracés au journal', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('console.clients.store'), ['nom' => 'Génération Joël', 'ville' => 'Kolwezi'])->assertRedirect();
    $client = Client::query()->sole();

    $this->post(route('console.clients.installations.store', $client), ['nom' => 'Serveur', 'url' => 'https://gj.example.test'])
        ->assertRedirect();

    expect($client->installations()->count())->toBe(1)
        ->and(EntreeJournal::query()->pluck('action')->all())->toBe(['CLIENT_CREE', 'INSTALLATION_AJOUTEE']);

    $this->get(route('console.clients.index', ['recherche' => 'Joël']))
        ->assertInertia(fn (Assert $page) => $page->component('Console/Clients/Index')->has('clients.data', 1));
});

it('montre la clé émise UNE fois, puis plus jamais', function () {
    $this->actingAs(User::factory()->create());
    $installation = Installation::factory()->create();

    $this->post(route('console.installations.cles.store', $installation))->assertRedirect();

    $code = session('cle_emise')['code'];
    expect($code)->toStartWith('OIKOS-');

    $this->get(route('console.clients.show', $installation->client_id))
        ->assertInertia(fn (Assert $page) => $page->component('Console/Clients/Fiche')
            ->where('cle_emise.code', $code)
            ->has('installations.0.cles', 1));

    $this->get(route('console.clients.show', $installation->client_id))
        ->assertInertia(fn (Assert $page) => $page->where('cle_emise', null));

    // Nulle part ailleurs dans les props : la fiche ne montre qu'un aperçu.
    $page = $this->get(route('console.clients.show', $installation->client_id))->viewData('page');
    expect(json_encode($page['props']))->not->toContain(substr($code, 6));
});

it('désactive et réactive une installation sans jamais la supprimer, et révoque une clé', function () {
    $this->actingAs(User::factory()->create());
    $installation = Installation::factory()->create();

    $this->post(route('console.installations.cles.store', $installation));
    $cle = $installation->clesActivation()->sole();

    $this->patch(route('console.cles.revoquer', $cle))->assertRedirect();
    expect($cle->refresh()->revoquee_le)->not->toBeNull();

    $this->patch(route('console.installations.activation', $installation), ['active' => false])->assertRedirect();
    expect($installation->refresh()->etat())->toBe(Installation::DESACTIVEE);

    $this->post(route('console.installations.cles.store', $installation))->assertSessionHasErrors('installation');

    $this->patch(route('console.installations.activation', $installation), ['active' => true]);
    expect($installation->refresh()->estDesactivee())->toBeFalse()
        ->and(Installation::query()->count())->toBe(1);
});

it("lit l'état d'une installation sur ses dates : muette au bout de trois jours sans nouvelles", function () {
    $installation = Installation::factory()->activee()->create();
    expect($installation->etat())->toBe(Installation::ACTIVE);

    $installation->forceFill(['vue_le' => now()->subDays(4)])->save();
    expect($installation->etat())->toBe(Installation::MUETTE)
        ->and(Installation::factory()->create()->etat())->toBe(Installation::JAMAIS_ACTIVEE);
});
