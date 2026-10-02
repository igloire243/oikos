<?php

use App\Metier\Commerce\Facturation;
use App\Metier\Commerce\PaiementsEnLigne;
use App\Metier\Commerce\Passerelles\PasserelleSimulee;
use App\Metier\Commerce\Ventes;
use App\Models\DemandePaiement;
use App\Models\Entite;
use App\Models\Installation;
use App\Models\Offre;
use App\Models\Paiement;
use App\Models\User;
use Database\Seeders\OffreSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * PAYER EN LIGNE — le fournisseur confirme, le navigateur ne prouve rien.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-01 10:00:00');
    Cache::flush();
    $this->seed(OffreSeeder::class);
    Http::fake();

    $installation = Installation::factory()->activee()->create(['activee_le' => '2026-09-01']);
    $vision = Entite::query()->create(['installation_id' => $installation->id, 'type' => Entite::VISION, 'ref' => 1, 'nom' => 'Génération Joël']);
    $this->facture = Ventes::vendre($vision, Offre::query()->where('code', 'LICENCE_VISION_STARTER')->sole(), 'USD', null, User::factory()->create())->facture;
});

function allumer(): void
{
    config(['oikos.paiement_en_ligne' => true, 'oikos.passerelle_paiement' => 'simulee']);
}

it('répond 404 partout tant que le paiement en ligne est éteint', function () {
    expect($this->facture->jeton_paiement)->not->toBeNull();

    $this->get(route('paiement.afficher', $this->facture->jeton_paiement))->assertNotFound();
    $this->post(route('paiement.demarrer', $this->facture->jeton_paiement))->assertNotFound();
    expect(PaiementsEnLigne::lienPour($this->facture))->toBeNull();
});

it('ne laisse pas deviner une facture par son numéro', function () {
    allumer();

    $this->get('/payer/'.$this->facture->numero)->assertNotFound();
    $this->get(route('paiement.afficher', $this->facture->jeton_paiement))->assertOk();
});

it('encaisse une fois le fournisseur confirmé, même si la confirmation est rejouée', function () {
    allumer();
    PaiementsEnLigne::initier($this->facture);
    $demande = DemandePaiement::query()->sole();

    PasserelleSimulee::decider($demande->reference, true);

    $this->get(route('paiement.retour', $demande->reference))->assertOk();
    $this->get(route('paiement.retour', $demande->reference))->assertOk();
    $this->post(route('paiement.notification', 'simulee'), ['reference' => $demande->reference])->assertOk();

    expect(Paiement::query()->count())->toBe(1)
        ->and(Paiement::query()->sole()->moyen)->toBe('EN_LIGNE')
        ->and($this->facture->fresh()->restantCentimes())->toBe(0)
        ->and($demande->fresh()->statut)->toBe(DemandePaiement::CONFIRMEE);
});

it("n'encaisse rien tant que le fournisseur n'a pas répondu", function () {
    allumer();
    PaiementsEnLigne::initier($this->facture);
    $demande = DemandePaiement::query()->sole();

    $this->get(route('paiement.retour', $demande->reference))->assertOk();

    expect(Paiement::query()->count())->toBe(0)->and($demande->fresh()->statut)->toBe(DemandePaiement::EN_ATTENTE);
});

it('marque un échec sans encaisser', function () {
    allumer();
    PaiementsEnLigne::initier($this->facture);
    $demande = DemandePaiement::query()->sole();
    PasserelleSimulee::decider($demande->reference, false);

    $this->get(route('paiement.retour', $demande->reference))->assertOk();

    expect(Paiement::query()->count())->toBe(0)->and($demande->fresh()->statut)->toBe(DemandePaiement::ECHOUEE);
});

it('refuse un montant confirmé différent de celui demandé', function () {
    allumer();
    PaiementsEnLigne::initier($this->facture);
    $demande = DemandePaiement::query()->sole();
    PasserelleSimulee::decider($demande->reference, true, $demande->montant_centimes - 100);

    $this->get(route('paiement.retour', $demande->reference))->assertOk();

    expect(Paiement::query()->count())->toBe(0)->and($demande->fresh()->statut)->toBe(DemandePaiement::ECHOUEE);
});

it('signale « à rembourser » quand le client a payé une facture déjà soldée', function () {
    allumer();
    PaiementsEnLigne::initier($this->facture);
    $demande = DemandePaiement::query()->sole();
    PasserelleSimulee::decider($demande->reference, true);

    Facturation::encaisser($this->facture, $this->facture->restantCentimes(), 'ESPECES', null, Carbon::today(), null, null);

    $this->get(route('paiement.retour', $demande->reference))->assertOk();

    expect(Paiement::query()->count())->toBe(1)
        ->and($demande->fresh()->statut)->toBe(DemandePaiement::ECHOUEE)
        ->and($demande->fresh()->motif)->toContain('rembourser');
    $this->assertDatabaseHas('journal', ['action' => 'PAIEMENT_EN_LIGNE_A_REMBOURSER']);
});

it('interdit la passerelle simulée en production', function () {
    allumer();
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => PaiementsEnLigne::passerelle('simulee'))->toThrow(RuntimeException::class);
});

it('montre le lien de paiement sur l\'écran des factures quand le paiement est allumé', function () {
    allumer();

    expect(PaiementsEnLigne::lienPour($this->facture))->toContain('/payer/'.$this->facture->jeton_paiement);
});

/** FLUTTERWAVE — le contrat avec leur API, rejoué sans réseau. */
function brancherFlutterwave(): void
{
    // Le `Http::fake()` de beforeEach répond 200 à tout : il passerait avant les réponses de chaque test.
    Http::swap(new Factory);
    config([
        'oikos.paiement_en_ligne' => true,
        'oikos.passerelle_paiement' => 'flutterwave',
        'oikos.flutterwave.cle_secrete' => 'FLWSECK_TEST-xxx',
        'oikos.flutterwave.hash_notification' => 'secret-de-notification',
    ]);
}

it('envoie le client sur la page Flutterwave, montant en unités', function () {
    brancherFlutterwave();
    Http::fake(['*/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/v3/hosted/pay/abc']])]);

    $this->post(route('paiement.demarrer', $this->facture->jeton_paiement))
        ->assertRedirect('https://checkout.flutterwave.com/v3/hosted/pay/abc');

    $demande = DemandePaiement::query()->sole();
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/payments')
        && $r['tx_ref'] === $demande->reference
        && $r['currency'] === 'USD'
        && (float) $r['amount'] === (float) ($demande->montant_centimes / 100)
        && $r->hasHeader('Authorization', 'Bearer FLWSECK_TEST-xxx'));
});

it('dit au client que le prestataire est indisponible, sans erreur 500', function () {
    brancherFlutterwave();
    Http::fake(['*/payments' => Http::response(['status' => 'error', 'message' => 'Invalid key'], 401)]);

    $this->from(route('paiement.afficher', $this->facture->jeton_paiement))
        ->post(route('paiement.demarrer', $this->facture->jeton_paiement))
        ->assertSessionHasErrors('facture');
});

it('encaisse sur la foi de verify_by_reference, pas de l\'adresse de retour', function () {
    brancherFlutterwave();
    Http::fake(['*/payments' => Http::response(['data' => ['link' => 'https://x.test/p']])]);
    PaiementsEnLigne::initier($this->facture);
    $demande = DemandePaiement::query()->sole();

    Http::swap(new Factory);   // un stub déjà posé passerait avant le nouveau
    // L'adresse de retour affirme « successful » ; le fournisseur dit « pending » : rien n'entre.
    Http::fake(['*/transactions/verify_by_reference*' => Http::response(['data' => ['status' => 'pending', 'amount' => $demande->montant_centimes / 100, 'currency' => 'USD', 'id' => 77]])]);
    $this->get(route('paiement.retour', $demande->reference).'?status=successful')->assertOk();
    expect(Paiement::query()->count())->toBe(0);

    Http::swap(new Factory);
    Http::fake(['*/transactions/verify_by_reference*' => Http::response(['data' => ['status' => 'successful', 'amount' => $demande->montant_centimes / 100, 'currency' => 'USD', 'id' => 77]])]);
    $this->get(route('paiement.retour', $demande->reference))->assertOk();

    expect(Paiement::query()->sole()->reference)->toBe('FLW-77')
        ->and($demande->fresh()->statut)->toBe(DemandePaiement::CONFIRMEE);
});

it('refuse une notification sans le bon verif-hash', function () {
    brancherFlutterwave();

    $this->postJson(route('paiement.notification', 'flutterwave'), ['data' => ['tx_ref' => 'PAY-X']])->assertUnauthorized();
    $this->postJson(route('paiement.notification', 'flutterwave'), ['data' => ['tx_ref' => 'PAY-X']], ['verif-hash' => 'faux'])->assertUnauthorized();

    config(['oikos.flutterwave.hash_notification' => '']);
    $this->postJson(route('paiement.notification', 'flutterwave'), ['data' => ['tx_ref' => 'PAY-X']], ['verif-hash' => ''])->assertUnauthorized();
});

it('traite une notification authentique en interrogeant le fournisseur', function () {
    brancherFlutterwave();
    Http::fake(['*/payments' => Http::response(['data' => ['link' => 'https://x.test/p']])]);
    PaiementsEnLigne::initier($this->facture);
    $demande = DemandePaiement::query()->sole();
    Http::fake(['*/transactions/verify_by_reference*' => Http::response(['data' => ['status' => 'successful', 'amount' => $demande->montant_centimes / 100, 'currency' => 'USD', 'id' => 9]])]);

    $this->postJson(route('paiement.notification', 'flutterwave'), ['event' => 'charge.completed', 'data' => ['tx_ref' => $demande->reference]], ['verif-hash' => 'secret-de-notification'])->assertOk();

    expect(Paiement::query()->count())->toBe(1);
});
