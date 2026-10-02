<?php

use App\Metier\Commerce\Facturation;
use App\Metier\Commerce\PaiementsEnLigne;
use App\Metier\Commerce\Ventes;
use App\Metier\Console\Reglages;
use App\Models\Entite;
use App\Models\EntreeJournal;
use App\Models\Installation;
use App\Models\Offre;
use App\Models\Reglage;
use App\Models\User;
use Database\Seeders\OffreSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * LES RÉGLAGES DE LA CONSOLE — facturation, paiement en ligne (clés chiffrées), identité de l'éditeur.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-15 10:00:00');
    $this->operateur = User::factory()->create();
});

afterEach(fn () => Carbon::setTestNow());

function enregistrerAutres(object $test, array $donnees)
{
    return $test->actingAs($test->operateur)->put(route('console.reglages.autres'), $donnees);
}

it('lit le .env tant que rien n\'est enregistré à l\'écran', function () {
    config(['oikos.paiement_en_ligne' => true, 'oikos.flutterwave.cle_secrete' => 'FLW-ENV']);

    expect(Reglages::booleen('paiement_en_ligne'))->toBeTrue()
        ->and(Reglages::secret('flutterwave_cle_secrete'))->toBe('FLW-ENV')
        ->and(Reglages::origine('flutterwave_cle_secrete'))->toBe('env');
});

it('enregistre les clés CHIFFRÉES et ne les renvoie jamais à l\'écran', function () {
    enregistrerAutres($this, ['flutterwave_cle_secrete' => 'FLWSECK_TEST-secret', 'flutterwave_hash' => 'mot-secret'])
        ->assertSessionHasNoErrors();

    // En base : du texte chiffré, jamais la clé.
    expect(Reglage::query()->find('flutterwave_cle_secrete')->valeur)->not->toContain('FLWSECK')
        ->and(Reglages::secret('flutterwave_cle_secrete'))->toBe('FLWSECK_TEST-secret')
        ->and(Reglages::origine('flutterwave_cle_secrete'))->toBe('ecran');

    $reponse = $this->actingAs($this->operateur)->get(route('console.reglages.index'));
    $reponse->assertInertia(fn (Assert $page) => $page
        ->where('paiement.cle.posee', true)->where('paiement.cle.origine', 'ecran'));

    expect($reponse->getContent())->not->toContain('FLWSECK_TEST-secret')->not->toContain('mot-secret');
});

it('ne trace jamais un secret au journal, seulement qu\'il a changé', function () {
    enregistrerAutres($this, ['flutterwave_cle_secrete' => 'FLWSECK_TEST-secret']);

    $trace = EntreeJournal::query()->where('action', 'REGLAGES_MODIFIES')->latest('id')->firstOrFail();

    expect(json_encode($trace->details).$trace->libelle)->not->toContain('FLWSECK')
        ->and($trace->libelle)->toContain('remplacé');
});

it('laisse la clé en place quand on enregistre un champ vide, et l\'efface sur demande', function () {
    enregistrerAutres($this, ['flutterwave_cle_secrete' => 'FLWSECK_TEST-secret']);
    enregistrerAutres($this, ['flutterwave_cle_secrete' => '', 'passerelle' => 'flutterwave']);

    expect(Reglages::secret('flutterwave_cle_secrete'))->toBe('FLWSECK_TEST-secret');

    enregistrerAutres($this, ['flutterwave_cle_secrete_effacer' => true]);

    expect(Reglages::secretPose('flutterwave_cle_secrete'))->toBeFalse();
});

it('allume le paiement en ligne et choisit le prestataire depuis l\'écran', function () {
    config(['oikos.paiement_en_ligne' => false]);

    enregistrerAutres($this, ['paiement_en_ligne' => true, 'passerelle' => 'flutterwave'])->assertSessionHasNoErrors();

    expect(PaiementsEnLigne::actif())->toBeTrue()
        ->and(PaiementsEnLigne::nomDeLaPasserelle())->toBe('flutterwave');

    enregistrerAutres($this, ['paiement_en_ligne' => false]);

    expect(PaiementsEnLigne::actif())->toBeFalse();
});

it('refuse un prestataire inconnu et un délai hors bornes', function () {
    enregistrerAutres($this, ['passerelle' => 'paypal'])->assertSessionHasErrors('passerelle');
    enregistrerAutres($this, ['echeance_jours' => '0'])->assertSessionHasErrors('echeance_jours');
    enregistrerAutres($this, ['echeance_jours' => '500'])->assertSessionHasErrors('echeance_jours');
});

it('applique le délai de paiement aux factures émises ensuite', function () {
    $this->seed(OffreSeeder::class);
    Http::fake();
    $installation = Installation::factory()->activee()->create(['activee_le' => '2026-09-01']);
    $vision = Entite::query()->create(['installation_id' => $installation->id, 'type' => Entite::VISION, 'ref' => 1, 'nom' => 'Génération Joël']);

    enregistrerAutres($this, ['echeance_jours' => '30'])->assertSessionHasNoErrors();

    $facture = Ventes::vendre($vision, Offre::query()->where('code', 'LICENCE_VISION_STARTER')->sole(), 'USD', null, $this->operateur)->facture;

    expect($facture->echeance_le->toDateString())->toBe('2026-11-14');
});

it('enregistre l\'identité de l\'éditeur et l\'offre au pied du site', function () {
    enregistrerAutres($this, [
        'editeur_nom' => 'Oikos', 'editeur_email' => 'contact@oikos.test', 'editeur_telephone' => '+243 810 000 000',
    ])->assertSessionHasNoErrors();

    $this->get(route('vitrine.accueil'))->assertInertia(fn (Assert $page) => $page
        ->where('editeur.email', 'contact@oikos.test')->where('editeur.nom', 'Oikos'));

    enregistrerAutres($this, ['editeur_email' => 'pas-un-email'])->assertSessionHasErrors('editeur_email');
});

it('essaie la clé auprès de Flutterwave sans rien payer', function () {
    enregistrerAutres($this, ['passerelle' => 'flutterwave', 'flutterwave_cle_secrete' => 'FLWSECK_TEST-secret']);

    Http::fake(['*/balances' => Http::response(['status' => 'success', 'data' => []])]);
    $this->actingAs($this->operateur)->post(route('console.reglages.paiement.test'))->assertSessionHas('succes');

    Http::swap(new Factory);
    Http::fake(['*/balances' => Http::response(['message' => 'Invalid authorization key'], 401)]);
    $this->actingAs($this->operateur)->post(route('console.reglages.paiement.test'))->assertSessionHas('erreur');
});

it('dit qu\'il manque la clé plutôt que de planter', function () {
    enregistrerAutres($this, ['passerelle' => 'flutterwave']);
    config(['oikos.flutterwave.cle_secrete' => null]);

    $this->actingAs($this->operateur)->post(route('console.reglages.paiement.test'))
        ->assertSessionHas('erreur', fn ($m) => str_contains($m, 'clé secrète'));
});

it('refuse l\'accès aux réglages à qui n\'est pas connecté', function () {
    auth()->logout();
    $this->put(route('console.reglages.autres'), ['paiement_en_ligne' => true])->assertRedirect();

    expect(Reglage::query()->count())->toBe(0);
});
