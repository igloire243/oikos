<?php

namespace App\Metier\Commerce;

/**
 * LES MONTANTS — des entiers en centimes, et aucun taux de change.
 *
 * La même règle que le produit (son invariant n° 19), et la même classe à peu de chose près : une
 * offre a un prix en dollars ET un prix en francs, fixés l'un et l'autre, jamais l'un déduit de
 * l'autre au taux du jour. Une église qui paie en francs paie le prix en francs qu'on lui a annoncé.
 * `round()` avant le cast : `(int) (12.30 * 100)` vaut 1229.
 */
class Montant
{
    /** @var array<string, array{symbole: string, decimales: int}> */
    public const DEVISES = [
        'USD' => ['symbole' => '$', 'decimales' => 2],
        'CDF' => ['symbole' => 'FC', 'decimales' => 2],
    ];

    /** « 12,50 », « 12.50 » ou « 1 250 » tels qu'on les tape. */
    public static function enCentimes(string|float|int $saisie, string $devise = 'USD'): int
    {
        $normalise = is_string($saisie)
            ? (float) str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($saisie))
            : (float) $saisie;

        return (int) round($normalise * (10 ** self::decimales($devise)));
    }

    public static function enUnites(int $centimes, string $devise = 'USD'): float
    {
        return $centimes / (10 ** self::decimales($devise));
    }

    public static function formater(int $centimes, string $devise = 'USD'): string
    {
        $valeur = number_format(self::enUnites($centimes, $devise), self::decimales($devise), ',', ' ');

        return $valeur."\u{00A0}".(self::DEVISES[$devise]['symbole'] ?? $devise);
    }

    public static function decimales(string $devise): int
    {
        return self::DEVISES[$devise]['decimales'] ?? 2;
    }
}
