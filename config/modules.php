<?php

/*
|--------------------------------------------------------------------------
| LE CATALOGUE DES MODULES — LE MIROIR EXACT DE CELUI DU PRODUIT
|--------------------------------------------------------------------------
| Ce fichier ne contient plus la liste : il la LIT dans `resources/catalogue/modules.json`, la copie exacte de
| `App\Metier\Acces\Modules` du produit (Génération Joël, `docs/console/modules.json`). Les clés voyagent dans la
| licence : une clé qui diffère d'un caractère entre les deux côtés donne un module vendu et jamais ouvert, et le
| défaut ne se voit qu'à l'usage, chez le client. Le fichier JSON se RECOPIE du produit, il ne se retouche jamais à
| la main ; `tests/Unit/CatalogueTest.php` vérifie son empreinte et que chaque module vendu a son texte.
|
| QUATRE ESPACES SE VENDENT : `vision`, `antenne`, `extension` (le secteur ou la cellule) et `departement`. Les
| écrans de « Mon Église », la Bible, la recherche et les notifications sont dans la section `inclus` du fichier :
| ils ne se vendent jamais à part, et ne figurent donc pas ici.
|
| LA CLÉ PORTE SON ESPACE, ET CE N'EST PAS DÉCORATIF : `rapports` désigne quatre pages différentes selon l'espace.
| Sans le préfixe, vendre « rapports » ouvrirait les quatre — ou aucune, selon la façon dont le code trancherait.
|
| CE QUI EST INVENDABLE, ET POURQUOI : les paramètres, la gestion des comptes, l'abonnement, la sécurité.
| `vendable => false` : les fermer empêcherait le client de réparer sa propre installation — y compris pour venir
| payer. Ils apparaissent dans le catalogue pour que l'inventaire soit complet et vérifiable.
|
| (Les anciennes clés `superadmin.*`, `secteur.*` et `department.*` sont devenues `vision.*`, `extension.*` et
| `departement.*` ; la migration `traduit_les_cles_des_modules_dans_les_plans` a reporté ce changement sur les
| offres déjà éditées en base.)
*/

$catalogue = json_decode((string) file_get_contents(base_path('resources/catalogue/modules.json')), true);
$textes = require base_path('resources/catalogue/textes.php');

$espaces = [];

foreach (['vision', 'antenne', 'extension', 'departement'] as $espace) {
    $definition = $catalogue['espaces'][$espace];
    $modules = [];

    foreach ($definition['modules'] as $suffixe => $module) {
        $cle = $espace.'.'.$suffixe;

        $modules[$cle] = [
            'nom' => $module['libelle'],
            'icone' => $module['icone'],
            'vendable' => $module['vendable'],
            'texte' => $textes[$cle] ?? $module['libelle'],
        ];
    }

    $espaces[$espace] = ['libelle' => $definition['libelle'], 'modules' => $modules];
}

return $espaces;
