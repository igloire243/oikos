<?php

namespace App\Support\Paiement;

use App\Models\Reglage;
use Illuminate\Support\Facades\Crypt;

/**
 * D'OÙ VIENT LA CONFIGURATION DE L'AGRÉGATEUR — depuis la console, plus depuis le seul .env.
 *
 * L'écran /console/paiement (voir PaiementConfigController) choisit l'agrégateur et saisit ses
 * identifiants. Tout est rangé dans deux réglages :
 *   · `paiement_agregateur` : AUCUN | FLEXPAY | CINETPAY | PAWAPAY
 *   · `paiement_actif`      : 0 | 1
 *   · `paiement_secrets`    : un JSON CHIFFRÉ (Crypt) — les clés d'API ne dorment jamais en clair.
 *
 * REPLI SUR LE .env : si les réglages n'ont pas encore été renseignés, on retombe sur l'ancienne
 * config FlexPay (config/flexpay.php + FLEXPAY_ACTIF), pour ne rien casser sur une installation
 * déjà branchée.
 */
class ConfigPasserelle
{
    public const AUCUN = 'AUCUN';
    public const FLEXPAY = 'FLEXPAY';
    public const CINETPAY = 'CINETPAY';
    public const PAWAPAY = 'PAWAPAY';

    /**
     * Le catalogue des agrégateurs et de leurs champs. `secret => true` = champ masqué, chiffré,
     * jamais réaffiché ; on ne le réécrit que si l'opérateur saisit une nouvelle valeur.
     */
    public const AGREGATEURS = [
        self::AUCUN => [
            'nom' => 'Aucun — encaissement manuel uniquement',
            'champs' => [],
        ],
        // PRIORITÉ 1 — l'agrégateur visé par défaut.
        self::PAWAPAY => [
            'nom' => 'PawaPay (recommandé)',
            'champs' => [
                'base_url' => ['libelle' => "URL de l'API", 'secret' => false, 'defaut' => 'https://api.pawapay.io'],
                'jeton' => ['libelle' => 'Jeton API (Bearer)', 'secret' => true, 'defaut' => ''],
                'secret_webhook' => ['libelle' => 'Secret de signature du callback', 'secret' => true, 'defaut' => ''],
                'devise' => ['libelle' => 'Devise encaissée', 'secret' => false, 'defaut' => 'USD'],
            ],
        ],
        // PRIORITÉ 2.
        self::CINETPAY => [
            'nom' => 'CinetPay',
            'champs' => [
                'base_url' => ['libelle' => "URL de l'API", 'secret' => false, 'defaut' => 'https://api-checkout.cinetpay.com/v2'],
                'site_id' => ['libelle' => 'Site ID', 'secret' => false, 'defaut' => ''],
                'api_key' => ['libelle' => 'Clé API', 'secret' => true, 'defaut' => ''],
                'secret_key' => ['libelle' => 'Clé secrète (HMAC du callback)', 'secret' => true, 'defaut' => ''],
                'devise' => ['libelle' => 'Devise encaissée', 'secret' => false, 'defaut' => 'USD'],
            ],
        ],
        // Historique — gardé branché si le compte marchand finit par s'ouvrir.
        self::FLEXPAY => [
            'nom' => 'FlexPay',
            'champs' => [
                'base_url' => ['libelle' => "URL de l'API", 'secret' => false, 'defaut' => 'https://backend.flexpay.cd/api/rest/v1'],
                'marchand' => ['libelle' => 'Code marchand', 'secret' => false, 'defaut' => ''],
                'jeton' => ['libelle' => 'Jeton Bearer', 'secret' => true, 'defaut' => ''],
                'secret_webhook' => ['libelle' => 'Secret de signature du webhook', 'secret' => true, 'defaut' => ''],
                'devise' => ['libelle' => 'Devise encaissée', 'secret' => false, 'defaut' => 'USD'],
            ],
        ],
    ];

    /** L'agrégateur choisi. Repli : FLEXPAY si l'ancienne config .env l'activait, sinon AUCUN. */
    public static function agregateur(): string
    {
        $choix = self::reglage('paiement_agregateur');

        if ($choix && array_key_exists($choix, self::AGREGATEURS)) {
            return $choix;
        }

        return config('flexpay.actif') ? self::FLEXPAY : self::AUCUN;
    }

    /** L'encaissement en ligne est-il activé ? (agrégateur choisi ET interrupteur sur « oui »). */
    public static function actif(): bool
    {
        if (self::agregateur() === self::AUCUN) {
            return false;
        }

        $brut = self::reglage('paiement_actif');

        if ($brut === null) {
            return (bool) config('flexpay.actif');   // repli .env
        }

        return filter_var($brut, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }

    /** Les identifiants de l'agrégateur courant, déchiffrés, complétés par les valeurs par défaut. */
    public static function secrets(): array
    {
        $champs = self::AGREGATEURS[self::agregateur()]['champs'] ?? [];
        $stockes = self::secretsBruts();
        $sortie = [];

        foreach ($champs as $cle => $def) {
            $sortie[$cle] = $stockes[$cle] ?? self::replEnv($cle) ?? $def['defaut'] ?? null;
        }

        return $sortie;
    }

    public static function valeur(string $champ, mixed $defaut = null): mixed
    {
        return self::secrets()[$champ] ?? $defaut;
    }

    /** Le JSON déchiffré tel qu'il est en base (sans replis), pour l'écran de configuration. */
    public static function secretsBruts(): array
    {
        $chiffre = self::reglage('paiement_secrets');

        if (! $chiffre) {
            return [];
        }

        try {
            $json = Crypt::decryptString($chiffre);
            $tab = json_decode($json, true);

            return is_array($tab) ? $tab : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Chiffre et range le jeu d'identifiants (appelé par PaiementConfigController). */
    public static function enregistrerSecrets(array $secrets): void
    {
        Reglage::updateOrCreate(
            ['cle' => 'paiement_secrets'],
            [
                'valeur' => Crypt::encryptString(json_encode($secrets)),
                'type' => 'secret',
                'groupe' => 'paiement',
                'libelle' => "Identifiants de l'agrégateur (chiffrés)",
                'ordre' => 999,
            ]
        );
    }

    private static function reglage(string $cle): ?string
    {
        try {
            $v = Reglage::tous()[$cle] ?? null;

            return ($v === null || $v === '') ? null : (string) $v;
        } catch (\Throwable $e) {
            return null;   // table absente (migration en cours…) : on répond « rien »
        }
    }

    /** Repli sur l'ancienne config FlexPay du .env, champ par champ. */
    private static function replEnv(string $champ): ?string
    {
        if (self::agregateur() !== self::FLEXPAY) {
            return null;
        }

        return match ($champ) {
            'base_url' => config('flexpay.base_url'),
            'marchand' => config('flexpay.marchand') ?: null,
            'jeton' => config('flexpay.jeton') ?: null,
            'secret_webhook' => config('flexpay.secret_webhook') ?: null,
            'devise' => config('flexpay.devise'),
            default => null,
        };
    }
}
