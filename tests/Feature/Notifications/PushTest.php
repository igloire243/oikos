<?php

use App\Metier\Commerce\Ventes;
use App\Metier\Notifications\Alertes;
use App\Metier\Notifications\PushNotifications;
use App\Models\AbonnementPush;
use App\Models\Entite;
use App\Models\Installation;
use App\Models\Offre;
use App\Models\User;
use Database\Seeders\OffreSeeder;
use Illuminate\Support\Carbon;

/**
 * LES NOTIFICATIONS PUSH DE LA CONSOLE — l'abonnement de l'appareil, et ce qui fait sonner.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-01 10:00:00');
    PushNotifications::simuler();
    $this->operateur = User::factory()->create();
});

afterEach(function () {
    Carbon::setTestNow();
    PushNotifications::arreterDeSimuler();
});

function uneLicenceVendue(): void
{
    test()->seed(OffreSeeder::class);
    $installation = Installation::factory()->activee()->create(['activee_le' => '2026-09-01']);
    $vision = Entite::query()->create([
        'installation_id' => $installation->id, 'type' => Entite::VISION, 'ref' => 1, 'nom' => 'Génération Joël',
    ]);

    Ventes::vendre($vision, Offre::query()->where('code', 'LICENCE_VISION_STARTER')->sole(), 'USD', null, null);
}

it("abonne un appareil, et le remplace au lieu d'en empiler un second", function () {
    $corps = ['endpoint' => 'https://push.example/abc', 'cle_p256dh' => 'p', 'cle_auth' => 'a'];

    $this->actingAs($this->operateur)->postJson(route('console.push.abonner'), $corps)->assertOk();
    $this->actingAs($this->operateur)->postJson(route('console.push.abonner'), $corps)->assertOk();

    expect(AbonnementPush::query()->count())->toBe(1);

    $this->actingAs($this->operateur)
        ->deleteJson(route('console.push.desabonner'), ['endpoint' => $corps['endpoint']])
        ->assertOk();

    expect(AbonnementPush::query()->count())->toBe(0);
});

it('ne prévient de rien quand tout est à jour', function () {
    uneLicenceVendue();

    expect(Alertes::envoyer())->toBe(['retards' => 0, 'echeances' => 0])
        ->and(PushNotifications::envoisSimules())->toBeEmpty();
});

it('sonne une fois pour toutes les factures dépassées, pas une fois par facture', function () {
    uneLicenceVendue();

    // La facture court 15 jours : vingt jours plus tard, elle est échue.
    $resultat = Alertes::envoyer(Carbon::today()->addDays(20));

    expect($resultat['retards'])->toBe(1)
        ->and(collect(PushNotifications::envoisSimules())->where('titre', 'Factures en retard')->where('destinataire', $this->operateur->id))
        ->toHaveCount(1);
});

it("prévient quand un abonnement s'achève sous quinze jours, et pas avant", function () {
    uneLicenceVendue();

    expect(Alertes::envoyer(Carbon::parse('2027-08-01'))['echeances'])->toBe(0)
        // La période se termine le 30 septembre 2027.
        ->and(Alertes::envoyer(Carbon::parse('2027-09-20'))['echeances'])->toBe(1);
});
