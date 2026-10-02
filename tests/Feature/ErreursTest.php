<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * LES PAGES D'ERREUR DE LA CONSOLE — l'identité de la console, le statut HTTP intact.
 */
it('rend la page d\'erreur de la console sur une adresse inconnue, en gardant le statut 404', function () {
    $this->actingAs(User::factory()->create())
        ->get('/console/une-page-qui-n-existe-pas')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Erreur')->where('statut', 404));
});

it('rend aussi la page d\'erreur à qui n\'est pas connecté, sans menu ni compte', function () {
    $this->get('/adresse-inconnue')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Erreur'));
});

it('garde du JSON pour l\'API des installations', function () {
    $this->getJson('/api/inconnue')->assertNotFound()->assertHeader('content-type', 'application/json');
});
