<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une clé d'activation : courte, à usage unique, dictable au téléphone.
 *
 * FORMAT : OIKOS-XXXX-XXXX-XXXX, soit douze caractères utiles pris dans un alphabet de trente et
 * un — environ 7,9 × 10¹⁷ combinaisons. Deviner une clé au hasard est hors de portée, et de toute
 * façon chacune expire et ne sert qu'une fois.
 *
 * L'ALPHABET ÉCARTE O, 0, I, 1 et L. Cette clé sera lue à voix haute au téléphone et retapée sur un
 * clavier de téléphone : la confusion entre O et 0 ne se voit pas à l'oreille, et produit un appel
 * de plus.
 */
class CleActivation extends Model
{
    protected $table = 'cles_activation';
    protected $primaryKey = 'cle_activation_id';

    public const EMISE = 'EMISE';
    public const UTILISEE = 'UTILISEE';
    public const REVOQUEE = 'REVOQUEE';

    public const STATUTS = [
        self::EMISE => 'Émise',
        self::UTILISEE => 'Utilisée',
        self::REVOQUEE => 'Révoquée',
    ];

    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const PREFIXE = 'OIKOS';

    protected $fillable = [
        'installation_id', 'code_hash', 'code_apercu', 'statut',
        'expire_le', 'utilisee_le', 'empreinte', 'ip', 'note',
    ];

    protected $casts = [
        'expire_le' => 'datetime',
        'utilisee_le' => 'datetime',
    ];

    protected $hidden = ['code_hash'];

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class, 'installation_id', 'installation_id');
    }

    /**
     * Émet une clé et RETOURNE sa valeur en clair. C'est le seul moment où elle est lisible.
     *
     * @return array{modele: self, code: string}
     */
    public static function emettre(Installation $installation, int $joursValidite = 30, ?string $note = null): array
    {
        $code = self::fabriquerCode();

        $modele = self::create([
            'installation_id' => $installation->installation_id,
            'code_hash' => self::hacher($code),
            'code_apercu' => substr($code, 0, 10).'…',
            'statut' => self::EMISE,
            'expire_le' => $joursValidite > 0 ? now()->addDays($joursValidite) : null,
            'note' => $note,
        ]);

        return ['modele' => $modele, 'code' => $code];
    }

    /**
     * Normalise avant de hacher.
     *
     * Le client recopiera la clé avec des espaces, en minuscules, ou sans les tirets — selon ce que
     * son téléphone aura fait du message. Refuser ces saisies serait une correction de plus à
     * expliquer par téléphone, pour une différence qui n'en est pas une.
     */
    public static function normaliser(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    }

    public static function hacher(string $code): string
    {
        return hash('sha256', self::normaliser($code));
    }

    public static function parCode(?string $code): ?self
    {
        if (! $code) {
            return null;
        }

        return static::where('code_hash', self::hacher($code))->first();
    }

    public function estUtilisable(): bool
    {
        return $this->statut === self::EMISE
            && ($this->expire_le === null || $this->expire_le->isFuture());
    }

    /** Pourquoi elle ne l'est pas — pour répondre à l'installation autrement que « refusé ». */
    public function raisonDuRefus(): ?string
    {
        if ($this->statut === self::UTILISEE) {
            return 'Cette clé a déjà servi à activer une installation.';
        }

        if ($this->statut === self::REVOQUEE) {
            return 'Cette clé a été révoquée.';
        }

        if ($this->expire_le && $this->expire_le->isPast()) {
            return 'Cette clé a expiré. Demandez-en une nouvelle.';
        }

        return null;
    }

    public function consommer(string $empreinte, ?string $ip): void
    {
        $this->update([
            'statut' => self::UTILISEE,
            'utilisee_le' => now(),
            'empreinte' => $empreinte,
            'ip' => $ip,
        ]);
    }

    private static function fabriquerCode(): string
    {
        $groupes = [];

        for ($g = 0; $g < 3; $g++) {
            $groupe = '';

            for ($i = 0; $i < 4; $i++) {
                // random_int et non rand() : c'est un secret, même court.
                $groupe .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            $groupes[] = $groupe;
        }

        return self::PREFIXE.'-'.implode('-', $groupes);
    }
}
