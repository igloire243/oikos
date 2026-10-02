<?php

use App\Metier\Commerce\Facturation;
use App\Metier\Commerce\Ventes;
use App\Models\Entite;
use App\Models\Facture;
use App\Models\Installation;
use App\Models\Offre;
use App\Models\Paiement;
use App\Models\User;
use Database\Seeders\OffreSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * FACTURER ET ENCAISSER — une facture par période vendue, soldée par des versements reçus.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-01 10:00:00');
    $this->seed(OffreSeeder::class);
    $this->operateur = User::factory()->create();

    $this->installation = Installation::factory()->activee()->create([
        'activee_le' => '2026-09-01',
        'url' => 'https://eglise.example.test',
        'rappel_jeton' => 'jeton-de-rappel-0123456789',
    ]);
    $this->vision = Entite::query()->create([
        'installation_id' => $this->installation->id, 'type' => Entite::VISION, 'ref' => 1, 'nom' => 'Génération Joël',
    ]);

    Http::fake();
    $this->periode = Ventes::vendre($this->vision, Offre::query()->where('code', 'LICENCE_VISION_STARTER')->sole(), 'USD', null, $this->operateur);
    $this->facture = $this->periode->facture;
});

afterEach(fn () => Carbon::setTestNow());

function encaisserLe(Facture $facture, int $centimes, ?string $reference = null, string $jour = '2026-10-01'): Paiement
{
    return Facturation::encaisser($facture, $centimes, 'MOBILE_MONEY', $reference, Carbon::parse($jour), null, test()->operateur);
}

it('émet une facture à la vente, au montant gelé de la période, échue sous quinze jours', function () {
    expect($this->facture)->not->toBeNull()
        ->and($this->facture->numero)->toBe('FAC-2026-00001')
        ->and($this->facture->montant_centimes)->toBe($this->periode->montant_centimes)
        ->and($this->facture->echeance_le->toDateString())->toBe('2026-10-16')
        ->and($this->facture->etat())->toBe(Facture::EN_ATTENTE);
});

it('passe de en attente à partielle puis soldée, et dérive son état de ses versements', function () {
    $moitie = intdiv($this->facture->montant_centimes, 2);

    encaisserLe($this->facture, $moitie, 'MM-1');
    expect($this->facture->fresh()->etat())->toBe(Facture::PARTIELLE);

    encaisserLe($this->facture->fresh(), $this->facture->montant_centimes - $moitie, 'MM-2');
    expect($this->facture->fresh()->etat())->toBe(Facture::SOLDEE)
        ->and($this->facture->fresh()->restantCentimes())->toBe(0);
});

it('refuse un trop-perçu, une facture déjà soldée, un montant nul et un versement futur', function () {
    expect(fn () => encaisserLe($this->facture, $this->facture->montant_centimes + 1))
        ->toThrow(ValidationException::class, 'trop-perçu');

    expect(fn () => encaisserLe($this->facture, 0))->toThrow(ValidationException::class);
    expect(fn () => encaisserLe($this->facture, 100, null, '2026-10-05'))->toThrow(ValidationException::class);

    encaisserLe($this->facture, $this->facture->montant_centimes);
    expect(fn () => encaisserLe($this->facture->fresh(), 100))->toThrow(ValidationException::class, 'déjà soldée');
});

it('refuse la même référence deux fois, même sur une autre facture', function () {
    encaisserLe($this->facture, 1000, 'MM-UNIQUE');

    expect(fn () => encaisserLe($this->facture->fresh(), 1000, 'MM-UNIQUE'))
        ->toThrow(ValidationException::class, 'déjà enregistrée');
});

it('marque un versement non reçu sans l\'effacer, et le rétablit', function () {
    $paiement = encaisserLe($this->facture, $this->facture->montant_centimes, 'MM-X');
    expect($this->facture->fresh()->estSoldee())->toBeTrue();

    Facturation::marquerNonRecu($paiement, 'Virement jamais arrivé', $this->operateur);

    expect($this->facture->fresh()->etat())->toBe(Facture::EN_ATTENTE)
        ->and(Paiement::query()->count())->toBe(1)
        // Une référence « non reçue » se rétablit, elle ne se ressaisit pas.
        ->and(fn () => encaisserLe($this->facture->fresh(), 100, 'MM-X'))->toThrow(ValidationException::class, 'rétablissez');

    Facturation::retablir($paiement->fresh(), $this->operateur);
    expect($this->facture->fresh()->estSoldee())->toBeTrue();
});

it('refuse de rétablir un versement qui dépasserait ce qui est dû', function () {
    $perdu = encaisserLe($this->facture, $this->facture->montant_centimes, 'MM-A');
    Facturation::marquerNonRecu($perdu, 'Rejeté', $this->operateur);

    // Entre-temps, la facture a été payée autrement : rétablir l'ancien ferait un trop-perçu.
    encaisserLe($this->facture->fresh(), $this->facture->montant_centimes, 'MM-B');

    expect(fn () => Facturation::retablir($perdu->fresh(), $this->operateur))->toThrow(ValidationException::class);
});

it('rappelle l\'installation après chaque mouvement, sans jamais faire échouer l\'encaissement', function () {
    Http::fake(['*' => Http::response([], 500)]);

    $paiement = encaisserLe($this->facture, 1000, 'MM-R');

    expect($paiement->exists)->toBeTrue();
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/oikos/rafraichir') && $r->hasHeader('Authorization', 'Bearer jeton-de-rappel-0123456789'));
});

it('numérote les factures à la suite, et repart de 1 chaque année', function () {
    // Une facture par période (index unique) : on en vend deux de plus.
    $autre = fn (string $debut) => $this->periode->abonnement->periodes()->create([
        'offre_id' => $this->periode->offre_id, 'debut' => $debut, 'fin' => Carbon::parse($debut)->addYear()->subDay(),
        'montant_centimes' => 1000, 'devise' => 'USD', 'au_prorata' => false,
    ]);

    expect(Facturation::emettre($autre('2028-01-01'), $this->operateur)->numero)->toBe('FAC-2026-00002');

    Carbon::setTestNow('2027-01-05 10:00:00');
    expect(Facturation::emettre($autre('2029-01-01'), $this->operateur)->numero)->toBe('FAC-2027-00001');
});

it('refuse une seconde facture pour la même période', function () {
    expect(fn () => Facturation::emettre($this->periode, $this->operateur))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('liste les factures, filtre celles en retard et additionne le dû par devise', function () {
    Carbon::setTestNow('2026-10-20 10:00:00');

    $this->actingAs($this->operateur)
        ->get(route('console.factures.index', ['etat' => 'retard']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Console/Factures/Index')
            ->has('factures', 1)
            ->where('factures.0.en_retard', true)
            ->where('comptes.retard', 1)
            ->has('a_recevoir', 1));

    encaisserLe($this->facture, $this->facture->montant_centimes, null, '2026-10-20');

    $this->actingAs($this->operateur)
        ->get(route('console.factures.index', ['etat' => 'retard']))
        ->assertInertia(fn (Assert $page) => $page->has('factures', 0)->where('comptes.soldees', 1));
});

it('encaisse depuis l\'écran en convertissant le montant tapé, virgule comprise', function () {
    $this->actingAs($this->operateur)
        ->post(route('console.factures.encaisser', $this->facture), [
            'montant' => '10,50', 'moyen' => 'ESPECES', 'recu_le' => '2026-10-01', 'reference' => 'ECR-1',
        ])
        ->assertRedirect()
        ->assertSessionHas('succes');

    expect($this->facture->fresh()->recuCentimes())->toBe(1050);
});

it('refuse d\'un message clair un trop-perçu saisi à l\'écran', function () {
    $this->actingAs($this->operateur)
        ->post(route('console.factures.encaisser', $this->facture), [
            'montant' => '99999999', 'moyen' => 'ESPECES', 'recu_le' => '2026-10-01',
        ])
        ->assertSessionHasErrors('montant');

    expect(Paiement::query()->count())->toBe(0);
});

it('montre le numéro et l\'état de la facture dans l\'historique du client', function () {
    $this->actingAs($this->operateur)
        ->get(route('console.clients.show', $this->installation->client_id))
        ->assertOk();

    expect($this->periode->fresh()->facture->numero)->toBe('FAC-2026-00001');
});
