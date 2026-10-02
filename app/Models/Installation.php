<?php

namespace App\Models;

use Database\Factories\InstallationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * UNE INSTALLATION — un serveur où tourne une copie du produit.
 *
 * Son état se LIT sur ses dates (voir `etat()`) : jamais activée, active, muette, désactivée.
 *
 * @property int $id
 * @property int $client_id
 * @property string|null $nom
 * @property string|null $url
 * @property string|null $empreinte
 * @property string|null $cle_synchro_hash
 * @property string|null $cle_synchro_apercu
 * @property string|null $rappel_jeton
 * @property Carbon|null $rappel_le
 * @property string|null $version
 * @property string|null $catalogue_empreinte
 * @property array<string, int>|null $compteurs
 * @property Carbon|null $activee_le
 * @property Carbon|null $essai_relance_le
 * @property Carbon|null $vue_le
 * @property Carbon|null $desactivee_le
 */
class Installation extends Model
{
    /** @use HasFactory<InstallationFactory> */
    use HasFactory;

    public const JAMAIS_ACTIVEE = 'JAMAIS_ACTIVEE';

    public const ACTIVE = 'ACTIVE';

    public const MUETTE = 'MUETTE';

    public const DESACTIVEE = 'DESACTIVEE';

    /** @var array<string, string> */
    public const ETATS = [
        self::JAMAIS_ACTIVEE => 'Jamais activée',
        self::ACTIVE => 'Active',
        self::MUETTE => 'Muette',
        self::DESACTIVEE => 'Désactivée',
    ];

    /**
     * Au-delà de ce silence, une installation se signale : sa synchronisation nocturne ne passe
     * plus. Plus court que le délai au bout duquel le produit se déclare périmé (`silence_jours`),
     * pour qu'on s'en aperçoive AVANT que le client ne soit fermé.
     */
    public const JOURS_AVANT_MUETTE = 3;

    /** @var list<string> */
    protected $fillable = [
        'client_id', 'nom', 'url', 'empreinte', 'version', 'catalogue_empreinte', 'compteurs',
        'activee_le', 'essai_relance_le', 'vue_le', 'desactivee_le',
    ];

    /** @var list<string> */
    protected $hidden = ['cle_synchro_hash', 'rappel_jeton'];

    /** @var array<string, string> */
    protected $casts = [
        'compteurs' => 'array',
        'rappel_jeton' => 'encrypted',
        'rappel_le' => 'datetime',
        'activee_le' => 'datetime',
        'essai_relance_le' => 'datetime',
        'vue_le' => 'datetime',
        'desactivee_le' => 'datetime',
    ];

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<Entite, $this> */
    public function entites(): HasMany
    {
        return $this->hasMany(Entite::class);
    }

    /** @return HasMany<Abonnement, $this> */
    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }

    /** @return HasMany<CleActivation, $this> */
    public function clesActivation(): HasMany
    {
        return $this->hasMany(CleActivation::class);
    }

    /** @param  Builder<Installation>  $requete */
    public function scopeActives(Builder $requete): void
    {
        $requete->whereNull('desactivee_le');
    }

    public function estDesactivee(): bool
    {
        return $this->desactivee_le !== null;
    }

    public function etat(): string
    {
        return match (true) {
            $this->estDesactivee() => self::DESACTIVEE,
            $this->empreinte === null => self::JAMAIS_ACTIVEE,
            $this->vue_le === null || $this->vue_le->lt(Carbon::now()->subDays(self::JOURS_AVANT_MUETTE)) => self::MUETTE,
            default => self::ACTIVE,
        };
    }

    public function libelle(): string
    {
        return $this->nom ?: 'Installation n° '.$this->id;
    }
}
