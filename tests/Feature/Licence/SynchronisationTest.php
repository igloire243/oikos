<?php

use App\Metier\Licence\Cles;
use App\Metier\Licence\EtatLicence;
use App\Metier\Licence\Rappel;
use App\Metier\Licence\Signature;
use App\Models\Entite;
use App\Models\Installation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as RequeteHttp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * LA SYNCHRONISATION, LA LICENCE SIGNÉE ET LE RAPPEL.
 */
function synchroniser(string $cle, array $donnees = []): TestResponse
{
    return test()->withToken($cle)->postJson('/api/v1/synchronisation', $donnees);
}

it('reconnaît une installation à sa clé de synchronisation, et à rien d\'autre', function () {
    Installation::factory()->activee('bonne-cle')->create();

    synchroniser('mauvaise-cle')->assertUnauthorized();
    test()->postJson('/api/v1/synchronisation')->assertUnauthorized();
    synchroniser('bonne-cle')->assertOk()->assertJsonStructure(['licence' => ['statut', 'signature']]);
});

it("reçoit l'arbre des entités, met à jour sans jamais supprimer une entité absente", function () {
    $installation = Installation::factory()->activee('cle')->create();

    synchroniser('cle', [
        'entites' => [
            ['type' => 'VISION', 'ref' => 1, 'nom' => 'Génération Joël'],
            ['type' => 'ANTENNE', 'ref' => 2, 'nom' => 'Antenne Katanga', 'parent_ref' => 1],
            ['type' => 'EXTENSION', 'ref' => 3, 'nom' => 'Béthel', 'sous_type' => 'SECTEUR', 'parent_ref' => 2, 'effectif' => 140],
        ],
        'compteurs' => ['membres' => 140],
    ])->assertOk();

    expect($installation->entites()->count())->toBe(3)
        ->and($installation->refresh()->compteurs)->toBe(['membres' => 140]);

    // Un envoi partiel : Béthel manque, et elle ne doit pas disparaître pour autant.
    synchroniser('cle', [
        'entites' => [['type' => 'ANTENNE', 'ref' => 2, 'nom' => 'Antenne du Lualaba', 'parent_ref' => 1]],
    ])->assertOk();

    expect($installation->entites()->count())->toBe(3)
        ->and(Entite::query()->where('ref', 2)->value('nom'))->toBe('Antenne du Lualaba')
        ->and(Entite::query()->where('ref', 3)->value('effectif'))->toBe(140);
});

it('refuse une installation désactivée, et une clé devenue ancienne après réactivation', function () {
    $installation = Installation::factory()->activee('ancienne-cle')->create();

    $code = Cles::emettre($installation, null)['code'];
    $nouvelle = test()->postJson('/api/v1/activation', ['cle' => $code, 'empreinte' => $installation->empreinte])
        ->assertOk()->json('jeton');

    synchroniser('ancienne-cle')->assertUnauthorized();
    synchroniser($nouvelle)->assertOk();

    $installation->refresh()->forceFill(['desactivee_le' => Carbon::now()])->save();
    synchroniser($nouvelle)->assertUnauthorized();
});

it("borne l'arbre reçu : un type inconnu est refusé", function () {
    Installation::factory()->activee('cle')->create();

    synchroniser('cle', ['entites' => [['type' => 'DEPARTEMENT', 'ref' => 1, 'nom' => 'Chorale']]])
        ->assertStatus(422)->assertJsonValidationErrors('entites.0.type');
});

it('signe la licence : vérifiable par la clé publique, et toute retouche la casse', function () {
    $cles = Signature::fabriquerLesCles();
    config(['oikos.licence_cle_privee' => $cles['privee']]);
    $publique = base64_decode($cles['publique']);

    $licence = EtatLicence::pour(Installation::factory()->activee()->create());
    $signature = base64_decode($licence['signature']);

    expect(openssl_verify(Signature::message($licence), $signature, $publique, OPENSSL_ALGO_SHA256))->toBe(1);

    // L'ordre des clés ne compte pas — le message est trié.
    $melange = array_reverse($licence, true);
    expect(openssl_verify(Signature::message($melange), $signature, $publique, OPENSSL_ALGO_SHA256))->toBe(1);

    // Copier la licence sur une autre machine change l'empreinte : la signature ne tient plus.
    $copiee = [...$licence, 'empreinte' => 'une-autre-machine'];
    expect(openssl_verify(Signature::message($copiee), $signature, $publique, OPENSSL_ALGO_SHA256))->toBe(0);
});

it('ne signe pas sans clé, plutôt que de signer faux', function () {
    config(['oikos.licence_cle_privee' => null]);

    expect(EtatLicence::pour(Installation::factory()->activee()->create())['signature'])->toBeNull();
});

it("rappelle l'installation avec son jeton, sans données, et n'échoue jamais", function () {
    Http::fake([
        'eglise.example.test/*' => Http::response(['ok' => true]),
        'injoignable.example.test/*' => fn () => throw new ConnectionException('injoignable'),
    ]);
    $installation = Installation::factory()->activee()->create(['rappel_jeton' => 'jeton-rappel-123456']);

    expect(Rappel::prevenir($installation))->toBeTrue()
        ->and($installation->refresh()->rappel_le)->not->toBeNull();

    Http::assertSent(fn (RequeteHttp $requete) => $requete->url() === 'https://eglise.example.test/oikos/rafraichir'
        && $requete->hasHeader('Authorization', 'Bearer jeton-rappel-123456')
        && $requete->data() === []);

    $injoignable = Installation::factory()->activee()->create(['url' => 'https://injoignable.example.test', 'rappel_jeton' => 'jeton-rappel-123456']);
    expect(Rappel::prevenir($injoignable))->toBeFalse();

    // Sans jeton de rappel, on n'essaie même pas.
    expect(Rappel::prevenir(Installation::factory()->create()))->toBeFalse();
});

it('signe pareil une liste d\'entités vide, qu\'elle soit un objet ou un tableau', function () {
    // Le produit reçoit un tableau décodé là où la console construit un objet : sans aller-retour
    // JSON avant de signer, `{}` et `[]` donnaient deux messages et une signature « fausse ».
    $base = ['statut' => 'ESSAI', 'modules' => null];

    expect(Signature::message([...$base, 'entites' => (object) []]))->toBe(Signature::message([...$base, 'entites' => []]));
});
