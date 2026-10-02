<?php

use App\Metier\Clients\Installations;
use App\Metier\Commerce\Ventes;
use App\Metier\Console\Reglages;
use App\Metier\Licence\EtatLicence;
use App\Models\Abonnement;
use App\Models\Entite;
use App\Models\EntreeJournal;
use App\Models\Installation;
use App\Models\Offre;
use App\Models\PeriodeAbonnement;
use App\Models\User;
use Database\Seeders\OffreSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * VENDRE — la cascade, le prorata, et ce que la licence en dit à l'installation.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-01 10:00:00');
    $this->seed(OffreSeeder::class);

    $this->installation = Installation::factory()->activee()->create(['activee_le' => '2026-09-01']);
    $entite = fn (string $type, int $ref, string $nom, ?int $parent = null) => Entite::query()->create([
        'installation_id' => $this->installation->id, 'type' => $type, 'ref' => $ref, 'nom' => $nom, 'parent_ref' => $parent,
    ]);
    $this->vision = $entite(Entite::VISION, 1, 'Génération Joël');
    $this->antenne = $entite(Entite::ANTENNE, 2, 'Antenne Lualaba', 1);
    $this->eglise = $entite(Entite::EXTENSION, 3, 'Béthel Kolwezi', 2);
});

afterEach(fn () => Carbon::setTestNow());

function uneOffre(string $code): Offre
{
    return Offre::query()->where('code', $code)->sole();
}

it("refuse un accès tant que la Vision n'a pas de licence — la cascade", function () {
    $apercu = Ventes::apercu($this->eglise, uneOffre('ACCES_EGLISE_STARTER'), 'USD');

    expect($apercu['empechement'])->toContain('Pas de licence en cours')
        ->and(fn () => Ventes::vendre($this->eglise, uneOffre('ACCES_EGLISE_STARTER'), 'USD', null, null))
        ->toThrow(ValidationException::class);
});

it('vend une licence à la Vision seulement, au prix de la taille du réseau', function () {
    expect(Ventes::apercu($this->antenne, uneOffre('LICENCE_VISION_STARTER'), 'USD')['empechement'])->toContain('se vend à la Vision')
        ->and(Ventes::apercu($this->vision, uneOffre('ACCES_EGLISE_STARTER'), 'USD')['empechement'])->toContain('à une église');

    $periode = Ventes::vendre($this->vision, uneOffre('LICENCE_VISION_STARTER'), 'CDF', null, null);

    // Deux entités sous la Vision : la première tranche de la grille (jusqu'à 10).
    expect($periode->montant_centimes)->toBe(34500000)
        ->and($periode->devise)->toBe('CDF')
        ->and($periode->debut->toDateString())->toBe('2026-10-01')
        ->and($periode->fin->toDateString())->toBe('2027-09-30')
        ->and(EntreeJournal::query()->where('action', 'ABONNEMENT_VENDU')->count())->toBe(1);
});

it("vend un accès plein tarif sous une licence, et refuse un palier au-dessus d'elle", function () {
    Ventes::vendre($this->vision, uneOffre('LICENCE_VISION_STANDARD'), 'USD', null, null);

    $acces = Ventes::vendre($this->eglise, uneOffre('ACCES_EGLISE_STANDARD'), 'USD', null, null);

    expect($acces->montant_centimes)->toBe(1000)
        ->and($acces->fin->toDateString())->toBe('2026-10-31')
        ->and($acces->au_prorata)->toBeFalse()
        ->and(Ventes::apercu($this->antenne, uneOffre('ACCES_ANTENNE_PREMIUM'), 'USD')['empechement'])
        ->toContain('jusqu\'au palier Standard');
});

it('coupe un accès à la fin de la licence, et réduit son prix au jour près — le prorata', function () {
    Carbon::setTestNow('2025-10-11 09:00:00');
    Ventes::vendre($this->vision, uneOffre('LICENCE_VISION_STARTER'), 'USD', null, null);

    // Un an plus tard, à dix jours de l'échéance (le 10 octobre 2026 inclus).
    Carbon::setTestNow('2026-10-01 09:00:00');
    $acces = Ventes::vendre($this->eglise, uneOffre('ACCES_EGLISE_STARTER'), 'USD', null, null);

    // Dix jours sur trente et un : 500 × 10 / 31 = 161,29… → 161 centimes.
    expect($acces->fin->toDateString())->toBe('2026-10-10')
        ->and($acces->au_prorata)->toBeTrue()
        ->and($acces->montant_centimes)->toBe(161);
});

it('renouvelle bout à bout, sans trou ni chevauchement, et refuse une période qui chevauche', function () {
    Ventes::vendre($this->vision, uneOffre('LICENCE_VISION_STANDARD'), 'USD', null, null);
    Ventes::vendre($this->eglise, uneOffre('ACCES_EGLISE_STARTER'), 'USD', null, null);

    expect(Ventes::debutPropose($this->eglise)->toDateString())->toBe('2026-11-01')
        ->and(Ventes::apercu($this->eglise, uneOffre('ACCES_EGLISE_STARTER'), 'USD', Carbon::parse('2026-10-15'))['empechement'])
        ->toContain('déjà couverte jusqu\'au');

    $suivante = Ventes::vendre($this->eglise, uneOffre('ACCES_EGLISE_STANDARD'), 'USD', null, null);

    expect($suivante->debut->toDateString())->toBe('2026-11-01')
        ->and(EntreeJournal::query()->where('action', 'ABONNEMENT_RENOUVELE')->count())->toBe(1);
});

it("refuse une offre retirée, et une période antidatée de plus d'un mois", function () {
    Ventes::vendre($this->vision, uneOffre('LICENCE_VISION_STARTER'), 'USD', null, null);
    uneOffre('ACCES_EGLISE_STARTER')->update(['retiree_le' => now()]);

    expect(Ventes::apercu($this->eglise, uneOffre('ACCES_EGLISE_STARTER'), 'USD')['empechement'])->toContain('retirée')
        ->and(Ventes::apercu($this->eglise, uneOffre('ACCES_EGLISE_STANDARD'), 'USD', Carbon::parse('2026-08-01'))['empechement'])
        ->toContain('plus d\'un mois dans le passé');
});

it("l'essai sert tout ouvert ; une licence sert le détail par entité", function () {
    $essai = EtatLicence::pour($this->installation->refresh());
    expect($essai['statut'])->toBe(EtatLicence::ESSAI)
        ->and($essai['modules'])->toBeNull()
        ->and($essai['fin'])->toBe('2026-10-01');

    Ventes::vendre($this->vision, uneOffre('LICENCE_VISION_STARTER'), 'USD', null, null);
    Ventes::vendre($this->eglise, uneOffre('ACCES_EGLISE_STARTER'), 'USD', null, null);

    $etat = EtatLicence::pour($this->installation->refresh());
    $entites = (array) $etat['entites'];

    expect($etat['statut'])->toBe(EtatLicence::ACTIF)
        ->and($etat['offre'])->toBe('LICENCE_VISION_STARTER')
        ->and($etat['fin'])->toBe('2027-09-30')
        ->and($entites)->toHaveKeys(['VISION:1', 'EXTENSION:3'])
        ->and($entites)->not->toHaveKey('ANTENNE:2')
        // Starter énumère : la trésorerie oui, les médias non ; et les départements de l'église
        // viennent avec elle.
        ->and($entites['EXTENSION:3']['modules'])->toContain('extension.tresorerie', 'departement.plannings')
        ->and($entites['EXTENSION:3']['modules'])->not->toContain('extension.medias')
        // Un module non vendable vient avec l'espace, sans être coché.
        ->and($entites['EXTENSION:3']['modules'])->toContain('extension.parametres')
        ->and($etat['modules'])->toContain('vision.entites')
        ->and($etat['modules'])->not->toContain('antenne.extensions');
});

it("résilier la licence coupe aussi les accès, et ne rend jamais l'essai", function () {
    Ventes::vendre($this->vision, uneOffre('LICENCE_VISION_STARTER'), 'USD', null, null);
    Ventes::vendre($this->eglise, uneOffre('ACCES_EGLISE_STARTER'), 'USD', null, null);

    Ventes::resilier($this->vision->abonnement()->sole(), 'Le client a arrêté.', null);

    $etat = EtatLicence::pour($this->installation->refresh());

    expect($etat['statut'])->toBe(EtatLicence::EXPIRE)
        ->and((array) $etat['entites'])->toBe([])
        ->and($etat['modules'])->toBe([])
        ->and(EntreeJournal::query()->where('action', 'ABONNEMENT_RESILIE')->value('details')['acces_coupes'])->toBe(1);
});

it('laisse travailler pendant la grâce, puis expire', function () {
    Ventes::vendre($this->vision, uneOffre('LICENCE_VISION_STARTER'), 'USD', null, null);

    Carbon::setTestNow('2027-10-05 08:00:00');
    expect(EtatLicence::pour($this->installation->refresh())['statut'])->toBe(EtatLicence::GRACE);

    Carbon::setTestNow('2027-10-20 08:00:00');
    expect(EtatLicence::pour($this->installation->refresh())['statut'])->toBe(EtatLicence::EXPIRE)
        ->and($this->vision->abonnement()->sole()->load('periodes')->etat())->toBe(Abonnement::ECHU);
});

it('reprend un abonnement résilié sans perdre son historique', function () {
    Ventes::vendre($this->vision, uneOffre('LICENCE_VISION_STARTER'), 'USD', null, null);
    $abonnement = $this->vision->abonnement()->sole();
    Ventes::resilier($abonnement, 'Pause.', null);

    // Une résiliation libère la place : on revend à partir d'aujourd'hui, et les périodes
    // d'avant restent dans l'historique.
    Ventes::vendre($this->vision, uneOffre('LICENCE_VISION_STANDARD'), 'USD', null, null);

    expect($abonnement->refresh()->estResilie())->toBeFalse()
        ->and($abonnement->periodes()->count())->toBe(2)
        ->and(EntreeJournal::query()->where('action', 'ABONNEMENT_REPRIS')->count())->toBe(1);
});

it('vend depuis la fiche : aperçu, vente et résiliation par les routes', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson(route('console.ventes.apercu', [$this->eglise, 'offre_id' => uneOffre('ACCES_EGLISE_STARTER')->id, 'devise' => 'USD']))
        ->assertOk()->assertJsonPath('empechement', fn ($m) => str_contains($m, 'Pas de licence'));

    $this->post(route('console.ventes.store', $this->vision), ['offre_id' => uneOffre('LICENCE_VISION_STARTER')->id, 'devise' => 'USD'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $this->getJson(route('console.ventes.apercu', [$this->eglise, 'offre_id' => uneOffre('ACCES_EGLISE_STARTER')->id, 'devise' => 'CDF']))
        ->assertOk()->assertJsonPath('empechement', null)->assertJsonPath('montant_centimes', 1150000);

    $this->get(route('console.clients.show', $this->installation->client_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('installations.0.arbre.0.abonnement.etat', Abonnement::EN_COURS)
            ->has('offres_en_vente.EXTENSION', 3));

    $abonnement = $this->vision->abonnement()->sole();
    $this->patch(route('console.abonnements.resilier', $abonnement), ['motif' => ''])->assertSessionHasErrors('motif');
    $this->patch(route('console.abonnements.resilier', $abonnement), ['motif' => 'Fin du contrat'])->assertSessionHasNoErrors();

    expect($abonnement->refresh()->estResilie())->toBeTrue();
});

it('range les entités en dossiers : la Vision contient ses antennes, qui contiennent leurs églises', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('console.clients.show', $this->installation->client_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('installations.0.arbre', fn ($arbre) => count($arbre) === 1
                && $arbre[0]['type'] === 'VISION'
                && $arbre[0]['enfants'][0]['nom'] === 'Antenne Lualaba'
                && $arbre[0]['enfants'][0]['enfants'][0]['nom'] === 'Béthel Kolwezi'));
});

it("applique dès aujourd'hui la formule plus haute vendue en renouvellement — sans refaire les prix", function () {
    Carbon::setTestNow('2026-09-20 10:00:00');
    Ventes::vendre($this->vision, uneOffre('LICENCE_VISION_STANDARD'), 'USD', null, null);
    Carbon::setTestNow('2026-10-01 10:00:00');
    $courante = Ventes::vendre($this->eglise, uneOffre('ACCES_EGLISE_STARTER'), 'USD', Carbon::parse('2026-09-25'), null);
    $suivante = Ventes::vendre($this->eglise, uneOffre('ACCES_EGLISE_STANDARD'), 'USD', null, null);

    $avancee = Ventes::appliquerMaintenant($suivante, null);

    $courante->refresh();
    expect($avancee->debut->toDateString())->toBe('2026-10-01')
        ->and($avancee->fin->toDateString())->toBe($suivante->fin->toDateString())
        ->and($avancee->debut_vendu->toDateString())->toBe('2026-10-25')
        ->and($courante->fin->toDateString())->toBe('2026-09-30')
        ->and($courante->fin_vendue->toDateString())->toBe('2026-10-24')
        ->and($avancee->montant_centimes)->toBe($suivante->montant_centimes)
        ->and(EntreeJournal::query()->where('action', 'ABONNEMENT_AVANCE')->count())->toBe(1);

    $ligne = (array) EtatLicence::pour($this->installation->refresh())['entites'];
    expect($ligne['EXTENSION:3']['offre'])->toBe('ACCES_EGLISE_STANDARD');
});

it("refuse d'avancer une période déjà commencée, ou quand la période en cours commence aujourd'hui", function () {
    Ventes::vendre($this->vision, uneOffre('LICENCE_VISION_STANDARD'), 'USD', null, null);
    $courante = Ventes::vendre($this->eglise, uneOffre('ACCES_EGLISE_STARTER'), 'USD', null, null);
    $suivante = Ventes::vendre($this->eglise, uneOffre('ACCES_EGLISE_STANDARD'), 'USD', null, null);

    expect(fn () => Ventes::appliquerMaintenant($courante, null))->toThrow(ValidationException::class, 'déjà commencé')
        ->and(fn () => Ventes::appliquerMaintenant($suivante, null))->toThrow(ValidationException::class, 'commence aujourd');
});

it("remet une installation à l'essai : tout ouvert, abonnements résiliés, historique gardé", function () {
    Ventes::vendre($this->vision, uneOffre('LICENCE_VISION_STARTER'), 'USD', null, null);
    Ventes::vendre($this->eglise, uneOffre('ACCES_EGLISE_STARTER'), 'USD', null, null);
    expect(EtatLicence::pour($this->installation->refresh())['statut'])->toBe(EtatLicence::ACTIF);

    Installations::remettreALEssai($this->installation, 'Démonstration', null);

    $etat = EtatLicence::pour($this->installation->refresh());
    expect($etat['statut'])->toBe(EtatLicence::ESSAI)
        ->and($etat['modules'])->toBeNull()
        ->and($etat['fin'])->toBe(now()->addDays(Reglages::valeur('essai_jours'))->toDateString())
        ->and(Abonnement::query()->whereNull('resilie_le')->count())->toBe(0)
        ->and(PeriodeAbonnement::query()->count())->toBe(2);

    // Une licence vendue ensuite reprend la main sur l'essai.
    Ventes::vendre($this->vision, uneOffre('LICENCE_VISION_STARTER'), 'USD', null, null);
    expect(EtatLicence::pour($this->installation->refresh())['statut'])->toBe(EtatLicence::ACTIF);
});
