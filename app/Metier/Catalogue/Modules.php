<?php

namespace App\Metier\Catalogue;

use RuntimeException;

/**
 * LE CATALOGUE DES MODULES — ce qu'on vend, lu dans la copie que le PRODUIT a exportée.
 *
 * ============================================================================================
 * UNE SEULE SOURCE, ET CE N'EST PAS LA CONSOLE
 * ============================================================================================
 * L'ancienne console tenait sa propre liste (`config/modules.php`), recopiée à la main de celle du
 * produit. Les deux devaient rester identiques « au caractère près », et rien ne le vérifiait :
 * une clé présente d'un seul côté donnait un module vendu que rien n'ouvre, ou ouvert que rien ne
 * facture — et le défaut ne se voyait qu'à l'usage, chez le client.
 *
 * Ici le produit fait foi : `resources/catalogue/modules.json` est l'export de
 * `App\Metier\Acces\Modules` côté produit, copié tel quel. On ne l'édite pas à la main — on le
 * régénère depuis le produit (`php artisan modules:exporter`) et on le recopie ici. Et chaque
 * installation annonce l'EMPREINTE de son propre catalogue à la synchronisation : un écart entre
 * les deux se lit sur la fiche de l'installation, au lieu de se découvrir au téléphone.
 *
 * La clé porte toujours son espace : `extension.rapports`, `antenne.rapports` et
 * `departement.rapports` sont trois modules différents, qui ne se vendent pas ensemble.
 */
class Modules
{
    public const FICHIER = 'catalogue/modules.json';

    /** @var array{empreinte: string, espaces: array<string, array{libelle: string, groupes?: array<string, string>, modules: array<string, array{libelle: string, icone: string, vendable: bool, groupe?: string}>}>, inclus?: array<string, array{libelle: string, ouvert_par: string|null, modules: array<string, array{libelle: string, icone: string}>}>}|null */
    private static ?array $document = null;

    /** @return array<string, array{libelle: string, groupes?: array<string, string>, modules: array<string, array{libelle: string, icone: string, vendable: bool, groupe?: string}>}> */
    public static function espaces(): array
    {
        return self::document()['espaces'];
    }

    /**
     * Tous les modules, à plat, indexés par clé complète.
     *
     * @return array<string, array{libelle: string, icone: string, vendable: bool, espace: string}>
     */
    public static function toutes(): array
    {
        $toutes = [];

        foreach (self::espaces() as $espace => $definition) {
            foreach ($definition['modules'] as $cle => $module) {
                $toutes["{$espace}.{$cle}"] = [
                    'libelle' => $module['libelle'],
                    'icone' => $module['icone'],
                    'vendable' => (bool) $module['vendable'],
                    'espace' => $espace,
                ];
            }
        }

        return $toutes;
    }

    /** @return list<string> */
    public static function vendables(): array
    {
        return array_keys(array_filter(self::toutes(), fn (array $module) => $module['vendable']));
    }

    public static function existe(string $cle): bool
    {
        return array_key_exists($cle, self::toutes());
    }

    public static function estVendable(string $cle): bool
    {
        return self::toutes()[$cle]['vendable'] ?? false;
    }

    public static function libelle(string $cle): string
    {
        return self::toutes()[$cle]['libelle'] ?? $cle;
    }

    public static function libelleEspace(string $espace): string
    {
        return self::espaces()[$espace]['libelle'] ?? $espace;
    }

    /** L'empreinte annoncée par le fichier — celle que le produit a calculée en l'exportant. */
    public static function empreinte(): string
    {
        return self::document()['empreinte'];
    }

    /**
     * L'empreinte RECALCULÉE sur les clés du fichier.
     *
     * Elle doit égaler `empreinte()` : un fichier retouché à la main — une clé ajoutée ici sans
     * passer par le produit — se trahit par cet écart, et un test le vérifie.
     *
     * @param  list<string>|null  $cles
     */
    public static function calculerEmpreinte(?array $cles = null): string
    {
        $cles ??= array_keys(self::toutes());
        sort($cles);

        return hash('sha256', implode("\n", $cles));
    }

    /**
     * Ce que le produit contient SANS le vendre à part : les écrans de « Mon Église » (ouverts par
     * la seule clé `extension.espace_membre`) et ce qui vit dans tous les espaces — la Bible, la
     * recherche, les notifications. Listés pour qu'on sache tout ce qui existe ; hors empreinte,
     * puisqu'aucune licence ne les ouvre ni ne les ferme un par un.
     *
     * @return array<string, array{libelle: string, ouvert_par: string|null, modules: array<string, array{libelle: string, icone: string}>}>
     */
    public static function inclus(): array
    {
        return self::document()['inclus'] ?? [];
    }

    /** Pour les tests : relire le fichier au prochain appel. */
    public static function oublier(): void
    {
        self::$document = null;
    }

    /** @return array{empreinte: string, espaces: array<string, array{libelle: string, groupes?: array<string, string>, modules: array<string, array{libelle: string, icone: string, vendable: bool, groupe?: string}>}>, inclus?: array<string, array{libelle: string, ouvert_par: string|null, modules: array<string, array{libelle: string, icone: string}>}>} */
    private static function document(): array
    {
        if (self::$document !== null) {
            return self::$document;
        }

        $chemin = resource_path(self::FICHIER);
        $contenu = is_file($chemin) ? json_decode((string) file_get_contents($chemin), true) : null;

        // Une console sans catalogue ne peut rien vendre de sensé : mieux vaut un arrêt net qu'une
        // grille d'offres vide qui aurait l'air d'une console sans rien à vendre.
        if (! is_array($contenu) || ! isset($contenu['empreinte'], $contenu['espaces'])) {
            throw new RuntimeException("Le catalogue des modules est illisible ({$chemin}). Réexportez-le depuis le produit.");
        }

        return self::$document = $contenu;
    }
}
