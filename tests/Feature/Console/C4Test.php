<?php

use App\Metier\Console\Reglages;
use App\Metier\Licence\EtatLicence;
use App\Models\Client;
use App\Models\DemandeContact;
use App\Models\EntreeJournal;
use App\Models\Installation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * LES DEMANDES, LES RÉGLAGES, LE JOURNAL ET L'ACCUEIL — le lot C4 de la console.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-15 10:00:00');
    $this->operateur = User::factory()->create();
});

afterEach(fn () => Carbon::setTestNow());

/* ===================================================== LE FORMULAIRE PUBLIC */

it('enregistre une demande du site commercial, sans compte', function () {
    $this->post(route('demande.envoyer'), [
        'nom' => 'Pasteur Joël', 'email' => 'joel@eglise.test', 'message' => 'Nous sommes sept églises à Kolwezi.',
    ])->assertSessionHas('succes');

    expect(DemandeContact::query()->count())->toBe(1)
        ->and(DemandeContact::query()->first()->estTraitee())->toBeFalse();
});

it('borne le formulaire : champs requis, message trop court', function () {
    $this->post(route('demande.envoyer'), ['nom' => '', 'email' => 'pas-un-courriel', 'message' => 'court'])
        ->assertSessionHasErrors(['nom', 'email', 'message']);

    expect(DemandeContact::query()->count())->toBe(0);
});

it('répond par un faux succès au robot qui remplit le champ piège, sans rien enregistrer', function () {
    $this->post(route('demande.envoyer'), [
        'nom' => 'Robot', 'email' => 'bot@spam.test', 'message' => 'Achetez nos produits maintenant.', 'site_web' => 'http://spam.test',
    ])->assertSessionHas('succes');

    expect(DemandeContact::query()->count())->toBe(0);
});

it('freine les envois en rafale', function () {
    $donnees = ['nom' => 'A', 'email' => 'a@b.test', 'message' => 'Un message assez long.'];

    foreach (range(1, 5) as $i) {
        $this->post(route('demande.envoyer'), $donnees)->assertSessionHasNoErrors();
    }

    $this->post(route('demande.envoyer'), $donnees)->assertStatus(429);
});

/* ===================================================== LES DEMANDES, CÔTÉ OPÉRATEUR */

it('liste les plus anciennes demandes non traitées d\'abord, et les traite sans les supprimer', function () {
    $vieille = DemandeContact::query()->create(['nom' => 'Ancienne', 'email' => 'a@a.test', 'message' => 'Demande ancienne depuis longtemps.', 'created_at' => '2026-10-01']);
    DemandeContact::query()->create(['nom' => 'Récente', 'email' => 'b@b.test', 'message' => 'Demande toute récente ici.', 'created_at' => '2026-10-14']);

    $this->actingAs($this->operateur)->get(route('console.demandes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('demandes.data.0.nom', 'Ancienne')
            ->where('comptes.a_traiter', 2));

    $this->actingAs($this->operateur)
        ->patch(route('console.demandes.traiter', $vieille), ['note' => 'Rappelée'])
        ->assertSessionHas('succes');

    expect($vieille->fresh()->estTraitee())->toBeTrue()
        ->and(DemandeContact::query()->count())->toBe(2)
        ->and(EntreeJournal::query()->where('action', 'DEMANDE_TRAITEE')->count())->toBe(1);

    // On ne traite pas deux fois la même demande.
    $this->actingAs($this->operateur)->patch(route('console.demandes.traiter', $vieille))->assertSessionHas('erreur');
});

it('refuse la liste des demandes à qui n\'est pas connecté', function () {
    $this->get(route('console.demandes.index'))->assertRedirect();
});

/* ===================================================== LES RÉGLAGES */

it('retombe sur la configuration quand aucun réglage n\'est enregistré', function () {
    expect(Reglages::valeur('grace_jours'))->toBe((int) config('oikos.grace_jours'));
});

it('enregistre un réglage, le fait lire par la licence et l\'écrit au journal', function () {
    $this->actingAs($this->operateur)
        ->put(route('console.reglages.update'), ['grace_jours' => '21'])
        ->assertSessionHasNoErrors();

    expect(Reglages::valeur('grace_jours'))->toBe(21)
        ->and(EntreeJournal::query()->where('action', 'REGLAGES_MODIFIES')->count())->toBe(1);
});

it('refuse une valeur hors bornes ou non entière, sans rien écrire', function () {
    $this->actingAs($this->operateur)
        ->put(route('console.reglages.update'), ['grace_jours' => '9999', 'silence_jours' => '3', 'essai_jours' => 'beaucoup'])
        ->assertSessionHasErrors(['grace_jours', 'silence_jours', 'essai_jours']);

    expect(Reglages::valeur('grace_jours'))->toBe((int) config('oikos.grace_jours'))
        ->and(EntreeJournal::query()->count())->toBe(0);
});

it('n\'écrit rien au journal quand rien n\'a bougé', function () {
    $this->actingAs($this->operateur)->put(route('console.reglages.update'), ['grace_jours' => (string) config('oikos.grace_jours')]);

    expect(EntreeJournal::query()->count())->toBe(0);
});

it('applique la durée d\'essai réglée à la licence servie', function () {
    $installation = Installation::factory()->activee()->create(['activee_le' => '2026-10-01']);

    $fin = fn () => EtatLicence::pour($installation->fresh())['fin'];

    expect($fin())->toBe('2026-10-31');

    Reglages::enregistrer(['essai_jours' => 60], $this->operateur);

    expect($fin())->toBe('2026-11-30');
});

/* ===================================================== LE JOURNAL ET L'ACCUEIL */

it('montre le journal en lecture seule, filtré par type de décision', function () {
    EntreeJournal::query()->create(['action' => 'CLE_EMISE', 'libelle' => 'Clé émise', 'user_id' => $this->operateur->id]);
    EntreeJournal::query()->create(['action' => 'VENTE', 'libelle' => 'Licence vendue']);

    $this->actingAs($this->operateur)->get(route('console.journal.index', ['action' => 'VENTE']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entrees.data', 1)
            ->where('entrees.data.0.libelle', 'Licence vendue')
            ->where('entrees.data.0.par', 'Système')
            ->has('actions', 2));

    // Aucune route d'écriture sur le journal : ni purge, ni correction.
    expect(collect(app('router')->getRoutes()->getRoutesByName())->keys()->filter(fn ($n) => str_starts_with($n, 'console.journal.') && $n !== 'console.journal.index'))
        ->toBeEmpty();
});

it('dit « tout est à jour » quand rien n\'attend, et compte ce qui attend sinon', function () {
    $this->actingAs($this->operateur)->get(route('console.accueil'))
        ->assertInertia(fn (Assert $page) => $page->where('a_traiter', [])->where('chiffres.a_traiter', 0));

    DemandeContact::query()->create(['nom' => 'X', 'email' => 'x@x.test', 'message' => 'Un message assez long.']);
    Client::query()->create(['nom' => 'Église test']);

    $this->actingAs($this->operateur)->get(route('console.accueil'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('a_traiter.0.cle', 'demandes')
            ->where('chiffres.clients', 1));
});
