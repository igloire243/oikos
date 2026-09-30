<?php

use App\Metier\Catalogue\Modules;
use App\Metier\Licence\Cles;
use App\Models\CleActivation;
use App\Models\Client;
use App\Models\EntreeJournal;
use App\Models\Installation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;

/**
 * L'ACTIVATION — une clé courte, un seul usage, et rien ne se crée depuis Internet.
 */
function uneCleEmise(?Installation $installation = null): array
{
    $installation ??= Installation::factory()->create();

    return [$installation, Cles::emettre($installation, null)['code']];
}

function activer(string $code, string $empreinte = 'machine-de-test-0001', array $plus = []): TestResponse
{
    return test()->postJson('/api/v1/activation', [
        'cle' => $code,
        'empreinte' => $empreinte,
        'url' => 'https://eglise.example.test',
        'version' => '2.0.0',
        'rappel' => 'jeton-de-rappel-0123456789',
        'catalogue' => Modules::empreinte(),
        ...$plus,
    ]);
}

it("émet une clé dictable, la garde hachée, et l'accepte tapée n'importe comment", function () {
    [$installation, $code] = uneCleEmise();

    expect($code)->toMatch('/^OIKOS-[A-HJKMNP-Z2-9]{4}-[A-HJKMNP-Z2-9]{4}-[A-HJKMNP-Z2-9]{4}$/');

    $cle = CleActivation::query()->sole();
    expect($cle->code_hash)->not->toContain(substr($code, 6))
        ->and(Cles::parCode(strtolower(str_replace('-', ' ', $code)))?->id)->toBe($cle->id);

    expect(EntreeJournal::query()->where('action', 'CLE_EMISE')->count())->toBe(1);
});

it('active une installation : jeton de synchronisation, licence, clé consommée', function () {
    [$installation, $code] = uneCleEmise();

    $reponse = activer($code)->assertOk()->assertJsonStructure(['jeton', 'licence' => ['statut', 'fin', 'empreinte', 'signature']]);

    $installation->refresh();
    expect($installation->empreinte)->toBe('machine-de-test-0001')
        ->and($installation->cle_synchro_hash)->toBe(hash('sha256', $reponse->json('jeton')))
        ->and($installation->rappel_jeton)->toBe('jeton-de-rappel-0123456789')
        ->and($installation->etat())->toBe(Installation::ACTIVE)
        ->and($reponse->json('licence.empreinte'))->toBe('machine-de-test-0001')
        ->and(CleActivation::query()->sole()->etat())->toBe(CleActivation::UTILISEE);

    // Le jeton de rappel est chiffré en base : une copie de la base ne suffit pas à rappeler le parc.
    expect(DB::table('installations')->value('rappel_jeton'))->not->toBe('jeton-de-rappel-0123456789');

    // Une clé ne sert qu'une fois.
    activer($code)->assertStatus(422)->assertJson(['message' => 'Cette clé a déjà servi. Demandez-en une nouvelle.']);
});

it('refuse un code inconnu sans dire pourquoi, une clé révoquée ou expirée en le disant', function () {
    activer('OIKOS-AAAA-BBBB-CCCC')->assertStatus(422)->assertJson(['message' => "Clé d'activation invalide."]);

    [, $revoquee] = uneCleEmise();
    Cles::revoquer(CleActivation::query()->latest('id')->first(), null);
    activer($revoquee)->assertStatus(422)->assertJsonFragment(['message' => 'Cette clé a été révoquée. Demandez-en une nouvelle.']);

    [, $expiree] = uneCleEmise();
    Carbon::setTestNow(Carbon::now()->addDays(31));
    activer($expiree)->assertStatus(422)->assertJsonFragment(['message' => 'Cette clé a expiré. Demandez-en une nouvelle.']);
    Carbon::setTestNow();

    expect(Installation::query()->whereNotNull('empreinte')->count())->toBe(0);
});

it("refuse d'activer une installation désactivée, et d'émettre une clé pour elle", function () {
    [$installation, $code] = uneCleEmise();
    $installation->forceFill(['desactivee_le' => Carbon::now()])->save();

    activer($code)->assertStatus(422);

    expect(fn () => Cles::emettre($installation, null))->toThrow(ValidationException::class);
});

it('une machine, une installation : une empreinte déjà rattachée ailleurs est refusée', function () {
    Installation::factory()->create(['empreinte' => 'machine-deja-vue-0001']);
    [$installation, $code] = uneCleEmise();

    activer($code, 'machine-deja-vue-0001')->assertStatus(422);

    expect($installation->refresh()->empreinte)->toBeNull()
        ->and(CleActivation::query()->latest('id')->first()->etat())->toBe(CleActivation::UTILISABLE);
});

it("la carte de visite ne remplit que le vide, jamais ce que l'opérateur a saisi", function () {
    $client = Client::factory()->create(['nom' => 'Béthel', 'ville' => null, 'contact_email' => 'tresorier@bethel.test']);
    [, $code] = uneCleEmise(Installation::factory()->for($client)->create());

    activer($code, plus: ['identite' => [
        'communaute' => 'Bethel (réglages)',
        'ville' => 'Kolwezi',
        'email' => 'pasteur@bethel.test',
        'telephone' => '+243 812 345 678',
    ]])->assertOk();

    $client->refresh();
    expect($client->nom)->toBe('Béthel')
        ->and($client->contact_email)->toBe('tresorier@bethel.test')
        ->and($client->ville)->toBe('Kolwezi')
        ->and($client->contact_telephone)->toBe('+243 812 345 678');
});

it("l'essai part de la PREMIÈRE activation : réactiver ne l'offre pas une seconde fois", function () {
    [$installation, $code] = uneCleEmise();

    Carbon::setTestNow('2026-09-01 10:00:00');
    $premiere = activer($code)->json('licence.fin');

    Carbon::setTestNow('2026-09-20 10:00:00');
    [, $autre] = uneCleEmise($installation->refresh());
    $seconde = activer($autre)->assertOk()->json('licence.fin');
    Carbon::setTestNow();

    expect($seconde)->toBe($premiere)
        ->and(Carbon::parse($premiere)->toDateString())->toBe('2026-10-01');
});

it('valide ce qui arrive d\'Internet : empreinte et catalogue ont une forme imposée', function () {
    [, $code] = uneCleEmise();

    activer($code, 'court')->assertStatus(422)->assertJsonValidationErrors('empreinte');
    activer($code, plus: ['catalogue' => 'pas-une-empreinte'])->assertStatus(422)->assertJsonValidationErrors('catalogue');
});
