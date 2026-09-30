<?php

use App\Metier\Catalogue\Modules;

/**
 * LE CATALOGUE — une copie de celui du produit, qui doit le rester.
 *
 * Ces chiffres sont ceux que le produit verrouille de son côté (tests/Feature/Metier/ModulesTest).
 * S'ils bougent ici sans bouger là-bas, c'est que quelqu'un a édité la copie au lieu de la
 * réexporter : exactement l'écart qui ferait vendre un module que rien n'ouvre.
 */
it('porte les 64 modules du produit, dont 51 vendables, sur 4 espaces', function () {
    expect(Modules::espaces())->toHaveCount(4)
        ->and(Modules::toutes())->toHaveCount(64)
        ->and(Modules::vendables())->toHaveCount(51);
});

it('a une empreinte qui correspond à ses clés — un fichier retouché à la main se trahit', function () {
    expect(Modules::calculerEmpreinte())->toBe(Modules::empreinte());
});

it('parle le vocabulaire du produit v2, pas celui de l\'ancien', function () {
    expect(array_keys(Modules::espaces()))->toBe(['vision', 'antenne', 'extension', 'departement'])
        ->and(Modules::existe('extension.tresorerie'))->toBeTrue()
        ->and(Modules::existe('secteur.tresorerie'))->toBeFalse()
        ->and(Modules::existe('superadmin.reports'))->toBeFalse();
});

it('porte les quatre clés ajoutées pendant la Grande Convention', function () {
    foreach (['vision.commissions', 'extension.inventaire', 'antenne.inventaire', 'antenne.delegations'] as $cle) {
        expect(Modules::existe($cle))->toBeTrue("{$cle} manque au catalogue");
    }

    expect(Modules::estVendable('vision.commissions'))->toBeFalse()
        ->and(Modules::estVendable('extension.inventaire'))->toBeTrue()
        ->and(Modules::estVendable('antenne.delegations'))->toBeFalse();
});
