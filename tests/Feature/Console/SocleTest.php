<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * LE SOCLE DE LA CONSOLE — une porte, et une seule façon d'y avoir une clé.
 */
it('garde la console sous /console, connexion comprise, et laisse la vitrine publique à la racine', function () {
    $this->get('/')->assertOk();
    $this->get('/console')->assertRedirect('/console/login');
    $this->get('/console/catalogue')->assertRedirect('/console/login');

    expect(route('login', absolute: false))->toBe('/console/login')
        ->and(route('profile.show', absolute: false))->toBe('/console/user/profile');
});

it("n'a aucune inscription publique : un compte se crée en ligne de commande", function () {
    expect(Route::has('register'))->toBeFalse();

    $this->post('/console/register', ['name' => 'X', 'email' => 'x@exemple.test', 'password' => 'motdepasse1234'])
        ->assertNotFound();
});

it('crée un opérateur par la commande, et refuse un mot de passe trop court', function () {
    $this->artisan('oikos:operateur', ['email' => 'op@oikos.test', '--nom' => 'Opératrice'])
        ->expectsQuestion('Mot de passe (12 caractères au moins)', 'court')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);

    $this->artisan('oikos:operateur', ['email' => 'op@oikos.test', '--nom' => 'Opératrice'])
        ->expectsQuestion('Mot de passe (12 caractères au moins)', 'un-mot-de-passe-solide')
        ->assertSuccessful();

    expect(User::query()->where('email', 'op@oikos.test')->value('name'))->toBe('Opératrice');
});

it('ouvre l\'accueil et le catalogue à un opérateur, avec le menu partagé', function () {
    $operateur = User::factory()->create();

    $this->actingAs($operateur)->get('/console')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Console/Accueil')
            ->where('catalogue.modules', 77)
            ->where('menu.0.entrees.0.route', 'console.accueil'));

    // Une entrée sans écran reste au menu, sans lien : « à venir ».
    $this->actingAs($operateur)->get('/console/catalogue')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Console/Catalogue')
            ->has('espaces', 4)
            ->where('menu.1.entrees.0.route', 'console.clients.index')
            ->where('menu.1.entrees.1.route', 'console.offres.index')
            ->where('menu.1.entrees.2.route', 'console.factures.index')
            ->where('menu.1.entrees.3.route', 'console.demandes.index'));
});
