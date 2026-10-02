<?php

namespace App\Metier\Console;

use App\Metier\Journal\Journal;
use App\Models\Reglage;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Throwable;

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

    /**
     * LES AUTRES RÉGLAGES — facturation, paiement en ligne, identité de l'éditeur. Chacun est LU par un code
     * déjà livré (la colonne `lu_par` le dit) : un réglage que rien ne lit serait un bouton qui ne commande rien.
     *
     * `type` : `entier`, `booleen`, `choix`, `texte` ou `secret` (chiffré, jamais renvoyé à l'écran).
     * `repli` : la clé de `config/oikos.php` qui sert tant que rien n'est enregistré — les clés d'un
     * déploiement existant, posées dans le `.env`, continuent de marcher sans qu'on les ressaisisse.
     *
     * @var array<string, array{groupe: string, libelle: string, type: string, lu_par: string, repli?: string, min?: int, max?: int, choix?: list<string>}>
     */
    public const AUTRES = [
        'echeance_jours' => ['groupe' => 'facturation', 'libelle' => 'Délai de paiement d\'une facture', 'type' => 'entier', 'min' => 1, 'max' => 180,
            'repli' => 'echeance_jours', 'lu_par' => "L'échéance de chaque facture émise ; au-delà, elle est signalée « en retard »."],
        'paiement_en_ligne' => ['groupe' => 'paiement', 'libelle' => 'Paiement en ligne', 'type' => 'booleen',
            'repli' => 'paiement_en_ligne', 'lu_par' => 'Les pages /payer/* et le lien de paiement de l\'écran Factures.'],
        'passerelle' => ['groupe' => 'paiement', 'libelle' => 'Prestataire de paiement', 'type' => 'choix', 'choix' => ['simulee', 'flutterwave'],
            'repli' => 'passerelle_paiement', 'lu_par' => 'Le prestataire vers lequel le client est envoyé pour payer.'],
        'flutterwave_cle_secrete' => ['groupe' => 'paiement', 'libelle' => 'Flutterwave — clé secrète', 'type' => 'secret',
            'repli' => 'flutterwave.cle_secrete', 'lu_par' => 'Chaque appel à Flutterwave : créer un paiement, le vérifier.'],
        'flutterwave_hash' => ['groupe' => 'paiement', 'libelle' => 'Flutterwave — secret de notification', 'type' => 'secret',
            'repli' => 'flutterwave.hash_notification', 'lu_par' => 'L\'authentification des notifications de Flutterwave (en-tête verif-hash).'],
        'editeur_nom' => ['groupe' => 'editeur', 'libelle' => 'Nom de l\'éditeur', 'type' => 'texte',
            'lu_par' => 'Le pied du site commercial et la page de paiement.'],
        'editeur_email' => ['groupe' => 'editeur', 'libelle' => 'E-mail de contact', 'type' => 'texte',
            'lu_par' => 'Le pied du site commercial et la page de paiement : à qui écrire en cas de souci.'],
        'editeur_telephone' => ['groupe' => 'editeur', 'libelle' => 'Téléphone de contact', 'type' => 'texte',
            'lu_par' => 'Le pied du site commercial et la page de paiement.'],
        'editeur_adresse' => ['groupe' => 'editeur', 'libelle' => 'Adresse', 'type' => 'texte',
            'lu_par' => 'Le pied du site commercial.'],
    ];

    public static function valeur(string $cle): int
    {
        $enBase = self::enBase();

        return (int) ($enBase[$cle] ?? config('oikos.'.$cle, 0));
    }

    /** Le texte enregistré, ou le repli de `config/oikos.php`, ou `$defaut`. Jamais pour un secret. */
    public static function texte(string $cle, ?string $defaut = null): ?string
    {
        $brut = self::enBase()[$cle] ?? null;

        if ($brut !== null && $brut !== '') {
            return (string) $brut;
        }

        $repli = self::AUTRES[$cle]['repli'] ?? null;
        $valeur = $repli === null ? null : config('oikos.'.$repli);

        return is_string($valeur) && $valeur !== '' ? $valeur : $defaut;
    }

    public static function booleen(string $cle): bool
    {
        $brut = self::enBase()[$cle] ?? null;

        if ($brut !== null && $brut !== '') {
            return (string) $brut === '1';
        }

        return (bool) config('oikos.'.(self::AUTRES[$cle]['repli'] ?? $cle), false);
    }

    /** Un secret en clair, pour le code qui s'en sert. Enregistré (chiffré) d'abord, `.env` ensuite. */
    public static function secret(string $cle): ?string
    {
        $brut = (string) (self::enBase()[$cle] ?? '');

        if ($brut !== '') {
            try {
                return Crypt::decryptString($brut);
            } catch (Throwable) {
                // Une clé d'application changée rend le secret illisible : on le dit en n'en servant AUCUN,
                // plutôt que de renvoyer du texte chiffré à un fournisseur qui le refuserait sans explication.
                return null;
            }
        }

        $repli = self::AUTRES[$cle]['repli'] ?? null;
        $valeur = $repli === null ? null : config('oikos.'.$repli);

        return is_string($valeur) && $valeur !== '' ? $valeur : null;
    }

    /** Un secret est-il posé (en base ou dans le .env) ? L'écran le dit sans jamais le montrer. */
    public static function secretPose(string $cle): bool
    {
        return self::secret($cle) !== null;
    }

    /**
     * L'identité de l'éditeur, telle que les pages publiques l'affichent : nom, e-mail, téléphone, adresse.
     * Vides par défaut — on n'affiche pas une ligne dont personne n'a rien dit.
     *
     * @return array{nom: ?string, email: ?string, telephone: ?string, adresse: ?string}
     */
    public static function editeur(): array
    {
        return [
            'nom' => self::texte('editeur_nom'),
            'email' => self::texte('editeur_email'),
            'telephone' => self::texte('editeur_telephone'),
            'adresse' => self::texte('editeur_adresse'),
        ];
    }

    /** D'où vient la valeur en vigueur : l'écran, ou le fichier `.env` du serveur. */
    public static function origine(string $cle): string
    {
        $brut = self::enBase()[$cle] ?? null;

        return $brut !== null && $brut !== '' ? 'ecran' : (self::AUTRES[$cle]['repli'] ?? null ? 'env' : 'defaut');
    }

    /**
     * Enregistre un ou plusieurs des AUTRES réglages. Seules les clés présentes bougent ; un secret vide ne
     * l'efface pas (la clé `<secret>_effacer` le fait), pour qu'un clic sur « Enregistrer » ne détruise pas une
     * clé qu'on ne voit pas. Le journal dit QUEL réglage a changé, jamais sa valeur quand c'est un secret.
     *
     * @param  array<string, mixed>  $valeurs
     *
     * @throws ValidationException
     */
    public static function modifier(array $valeurs, ?User $par): void
    {
        $erreurs = [];
        $ecritures = [];

        foreach (self::AUTRES as $cle => $d) {
            $efface = ! empty($valeurs[$cle.'_effacer']);

            if (! array_key_exists($cle, $valeurs) && ! $efface) {
                continue;
            }

            $brut = $valeurs[$cle] ?? null;

            if ($d['type'] === 'secret') {
                if ($efface) {
                    $ecritures[$cle] = null;
                } elseif (is_string($brut) && trim($brut) !== '') {
                    $ecritures[$cle] = Crypt::encryptString(trim($brut));
                }

                continue;
            }

            $valide = match ($d['type']) {
                'entier' => is_numeric($brut) && (int) $brut == $brut && $brut >= $d['min'] && $brut <= $d['max'] ? (string) (int) $brut : null,
                'booleen' => in_array($brut, [true, false, 1, 0, '1', '0', 'true', 'false'], true) ? (filter_var($brut, FILTER_VALIDATE_BOOLEAN) ? '1' : '0') : null,
                'choix' => in_array($brut, $d['choix'], true) ? (string) $brut : null,
                default => is_string($brut) || $brut === null ? mb_substr(trim((string) $brut), 0, 255) : null,
            };

            if ($valide === null) {
                $erreurs[$cle] = match ($d['type']) {
                    'entier' => "Entre {$d['min']} et {$d['max']}.",
                    'choix' => 'Choix inconnu.',
                    default => 'Valeur invalide.',
                };

                continue;
            }

            if ($cle === 'editeur_email' && $valide !== '' && ! filter_var($valide, FILTER_VALIDATE_EMAIL)) {
                $erreurs[$cle] = 'Adresse e-mail invalide.';

                continue;
            }

            $ecritures[$cle] = $valide === '' ? null : $valide;
        }

        if ($erreurs !== []) {
            throw ValidationException::withMessages($erreurs);
        }

        $changes = [];
        foreach ($ecritures as $cle => $valeur) {
            $avant = self::enBase()[$cle] ?? null;

            if ($avant === $valeur) {
                continue;
            }

            $valeur === null
                ? Reglage::query()->where('cle', $cle)->delete()
                : Reglage::query()->updateOrCreate(['cle' => $cle], ['valeur' => $valeur, 'modifie_le' => Carbon::now()]);

            $secret = self::AUTRES[$cle]['type'] === 'secret';
            $changes[] = self::AUTRES[$cle]['libelle'].' : '.($secret ? ($valeur === null ? 'effacé' : 'remplacé') : ($valeur ?? 'vidé'));
        }

        app()->forgetInstance('oikos.reglages');

        if ($changes !== []) {
            Journal::tracer('REGLAGES_MODIFIES', null, 'Réglages modifiés — '.implode(' · ', $changes), ['reglages' => $changes], $par);
        }
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

    /** @return array<string, string|null> */
    private static function enBase(): array
    {
        // Une lecture par requête : `Abonnement::etat()` est appelée par dizaines sur une liste.
        if (! app()->bound('oikos.reglages')) {
            try {
                $valeurs = Reglage::query()->pluck('valeur', 'cle')->all();
            } catch (Throwable) {
                // La table n'existe pas encore : une console mise à jour mais pas encore migrée. Ces
                // durées commandent l'émission d'une clé, la licence servie, l'état d'un abonnement —
                // un 500 partout pour une migration oubliée serait pire que de servir, le temps de
                // migrer, les valeurs de départ de `config/oikos.php`.
                $valeurs = [];
            }

            app()->instance('oikos.reglages', $valeurs);
        }

        return app('oikos.reglages');
    }
}
