<?php

use App\Metier\Catalogue\Modules;
use App\Metier\Commerce\Offres;
use App\Metier\Commerce\Ventes;
use App\Models\Entite;
use App\Models\Installation;
use App\Models\Offre;
use App\Models\User;
use Database\Seeders\OffreSeeder;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * LES OFFRES — ce qu'une offre a le droit de vendre, et ce que changer son prix ne change pas.
 */
function uneOffreDeBase(array $plus = []): array
{
    return [
        'code' => 'ACCES_TEST', 'nature' => Offre::ACCES, 'niveau' => Entite::EXTENSION, 'palier' => Offre::STARTER,
        'nom' => 'Accès de test', 'argumentaire' => null, 'periode_mois' => 1,
        'prix_usd_centimes' => 500, 'prix_cdf_centimes' => 1150000, 'plafond_acces' => null,
        'paliers_taille' => null, 'modules' => ['extension.membres'], 'publique' => true, 'ordre' => 50,
        ...$plus,
    ];
}

it('ne pose dans le catalogue de départ que des modules vendables de leurs espaces', function () {
    $this->seed(OffreSeeder::class);

    expect(Offre::query()->count())->toBe(9);

    foreach (Offre::all() as $offre) {
        foreach ($offre->modules ?? [] as $cle) {
            expect(Modules::estVendable($cle))->toBeTrue("{$offre->code} : {$cle}")
                ->and(in_array(Modules::toutes()[$cle]['espace'], Offre::ESPACES[$offre->niveau], true))->toBeTrue("{$offre->code} : {$cle}");
        }
    }
});

it('refuse une licence ailleurs qu\'à la Vision, et un accès à la Vision', function () {
    expect(fn () => Offres::enregistrer(null, uneOffreDeBase(['nature' => Offre::LICENCE, 'plafond_acces' => Offre::STARTER]), null))
        ->toThrow(ValidationException::class)
        ->and(fn () => Offres::enregistrer(null, uneOffreDeBase(['niveau' => Entite::VISION]), null))
        ->toThrow(ValidationException::class);
});

it("refuse un module d'un autre espace, et un module non vendable", function () {
    expect(fn () => Offres::enregistrer(null, uneOffreDeBase(['modules' => ['antenne.rapports']]), null))
        ->toThrow(ValidationException::class)
        ->and(fn () => Offres::enregistrer(null, uneOffreDeBase(['modules' => ['extension.parametres']]), null))
        ->toThrow(ValidationException::class);

    // Le département vient avec l'église.
    expect(Offres::enregistrer(null, uneOffreDeBase(['modules' => ['departement.plannings']]), null)->modules)
        ->toBe(['departement.plannings']);
});

it('exige une tranche « au-delà » à la grille d\'une licence', function () {
    $licence = fn (array $grille) => uneOffreDeBase([
        'code' => 'LIC_TEST', 'nature' => Offre::LICENCE, 'niveau' => Entite::VISION, 'plafond_acces' => Offre::STARTER,
        'modules' => null, 'paliers_taille' => $grille,
    ]);

    expect(fn () => Offres::enregistrer(null, $licence([['max' => 10, 'prix_usd_centimes' => 1, 'prix_cdf_centimes' => 1]]), null))
        ->toThrow(ValidationException::class);

    $offre = Offres::enregistrer(null, $licence([
        ['max' => null, 'prix_usd_centimes' => 30, 'prix_cdf_centimes' => 3],
        ['max' => 10, 'prix_usd_centimes' => 10, 'prix_cdf_centimes' => 1],
    ]), null);

    // Rangée : la tranche ouverte toujours en dernier.
    expect($offre->paliers_taille[1]['max'])->toBeNull()
        ->and($offre->prixPour('USD', 4))->toBe(10)
        ->and($offre->prixPour('USD', 40))->toBe(30);
});

it('change un prix sans changer ce qui a déjà été vendu, et retire sans supprimer', function () {
    $this->seed(OffreSeeder::class);
    $installation = Installation::factory()->activee()->create();
    $vision = Entite::query()->create(['installation_id' => $installation->id, 'type' => Entite::VISION, 'ref' => 1, 'nom' => 'Vision']);
    $offre = Offre::query()->where('code', 'LICENCE_VISION_STARTER')->sole();

    $periode = Ventes::vendre($vision, $offre, 'USD', null, null);
    $offre->update(['paliers_taille' => null, 'prix_usd_centimes' => 99900]);
    Offres::retirer($offre, null);

    expect($periode->refresh()->montant_centimes)->toBe(15000)
        ->and(Offre::query()->whereKey($offre->id)->exists())->toBeTrue()
        ->and($offre->refresh()->estRetiree())->toBeTrue();
});

it("crée une offre depuis l'écran, prix tapés à la française", function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('console.offres.store'), [
        'code' => 'ACCES_EGLISE_SOLIDAIRE', 'nature' => 'ACCES', 'niveau' => 'EXTENSION', 'palier' => 'STARTER',
        'nom' => 'Accès Église — Solidaire', 'periode_mois' => 1, 'prix_usd' => '2,50', 'prix_cdf' => '5 750',
        'tous_les_modules' => false, 'modules' => ['extension.membres', 'extension.cultes'], 'publique' => false,
    ])->assertSessionHasNoErrors();

    $offre = Offre::query()->where('code', 'ACCES_EGLISE_SOLIDAIRE')->sole();
    expect($offre->prix_usd_centimes)->toBe(250)
        ->and($offre->prix_cdf_centimes)->toBe(575000)
        ->and($offre->publique)->toBeFalse();

    $this->put(route('console.offres.update', $offre), [
        'code' => 'ACCES_EGLISE_SOLIDAIRE', 'nature' => 'ACCES', 'niveau' => 'EXTENSION', 'palier' => 'STARTER',
        'nom' => 'Accès Église — Solidaire', 'periode_mois' => 1, 'prix_usd' => '3', 'prix_cdf' => '6900',
        'tous_les_modules' => true, 'publique' => false,
    ])->assertSessionHasNoErrors();

    // « Tous les modules » est null, pas la liste d'aujourd'hui : les modules futurs suivront.
    expect($offre->refresh()->modules)->toBeNull();

    $this->get(route('console.offres.index'))
        ->assertInertia(fn (Assert $page) => $page->component('Console/Offres/Index')->has('offres', 1)
            ->has('modules_par_niveau.EXTENSION', 2));
});
