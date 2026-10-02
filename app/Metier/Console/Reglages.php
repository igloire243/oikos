<?php

namespace App\Metier\Console;

use App\Metier\Journal\Journal;
use App\Models\Reglage;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * LES RÉGLAGES DE LA CONSOLE — et la règle qui décide de ce qu'on y met.
 *
 * Un réglage n'entre ici que si une règle déjà livrée le LIT (même discipline que les paramètres du
 * produit). Les quatre durées ci-dessous commandent chacune quelque chose de précis, et l'écran dit
 * quoi : aucune n'a d'effet visible sur sa propre page.
 *
 * Absent en base, un réglage retombe sur `config/oikos.php` : la console ne se retrouve jamais avec
 * une durée vide qui fermerait ou ouvrirait un parc entier sans que personne l'ait décidé.
 */
final class Reglages
{
    /**
     * @var array<string, array{libelle: string, effet: string, min: int, max: int}>
     */
    public const DEFINITIONS = [
        'essai_jours' => [
            'libelle' => "Durée de l'essai",
            'effet' => "Compté depuis la première activation d'une installation. Passé ce délai sans licence vendue, elle est servie « expirée ». Ne repart jamais.",
            'min' => 0,
            'max' => 365,
        ],
        'grace_jours' => [
            'libelle' => 'Période de grâce après une échéance',
            'effet' => "Après la fin d'une licence ou d'un accès, le produit laisse travailler et prévient pendant ce délai, puis ferme.",
            'min' => 0,
            'max' => 90,
        ],
        'silence_jours' => [
            'libelle' => "Silence toléré d'une installation",
            'effet' => "Si une installation ne se synchronise plus depuis ce délai, elle se déclare périmée d'elle-même. Assez long pour absorber une coupure de fibre, pas pour punir une panne.",
            'min' => 7,
            'max' => 365,
        ],
        'cle_validite_jours' => [
            'libelle' => "Validité d'une clé d'activation",
            'effet' => "Une clé émise et jamais utilisée expire après ce délai. Elle ne sert qu'une fois, de toute façon.",
            'min' => 1,
            'max' => 365,
        ],
    ];

    public static function valeur(string $cle): int
    {
        $enBase = self::enBase();

        return (int) ($enBase[$cle] ?? config('oikos.'.$cle, 0));
    }

    /** @return array<string, int> la valeur EN VIGUEUR de chaque réglage, base ou repli */
    public static function toutes(): array
    {
        return collect(array_keys(self::DEFINITIONS))->mapWithKeys(fn (string $cle) => [$cle => self::valeur($cle)])->all();
    }

    /**
     * @param  array<string, int|string>  $valeurs
     *
     * @throws ValidationException
     */
    public static function enregistrer(array $valeurs, ?User $par): void
    {
        $avant = self::toutes();
        $erreurs = [];

        foreach (self::DEFINITIONS as $cle => $definition) {
            if (! array_key_exists($cle, $valeurs)) {
                continue;
            }

            $valeur = $valeurs[$cle];

            if (! is_numeric($valeur) || (int) $valeur != $valeur || $valeur < $definition['min'] || $valeur > $definition['max']) {
                $erreurs[$cle] = "Entre {$definition['min']} et {$definition['max']} jours.";
            }
        }

        if ($erreurs !== []) {
            throw ValidationException::withMessages($erreurs);
        }

        foreach (self::DEFINITIONS as $cle => $definition) {
            if (array_key_exists($cle, $valeurs)) {
                Reglage::query()->updateOrCreate(['cle' => $cle], ['valeur' => (int) $valeurs[$cle], 'modifie_le' => Carbon::now()]);
            }
        }

        app()->forgetInstance('oikos.reglages');

        $changes = collect(self::toutes())->filter(fn (int $v, string $cle) => $v !== $avant[$cle]);

        // Rien n'est tracé si rien n'a bougé : un clic sur « Enregistrer » ne se conteste pas.
        if ($changes->isNotEmpty()) {
            Journal::tracer('REGLAGES_MODIFIES', null, 'Réglages modifiés : '.$changes
                ->map(fn (int $v, string $cle) => self::DEFINITIONS[$cle]['libelle']." {$avant[$cle]} → {$v} j")
                ->implode(' · '), ['avant' => $avant, 'apres' => self::toutes()], $par);
        }
    }

    /** @return array<string, int> */
    private static function enBase(): array
    {
        // Une lecture par requête : `Abonnement::etat()` est appelée par dizaines sur une liste.
        if (! app()->bound('oikos.reglages')) {
            app()->instance('oikos.reglages', Reglage::query()->pluck('valeur', 'cle')->map(fn ($v) => (int) $v)->all());
        }

        return app('oikos.reglages');
    }
}
