<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une copie du logiciel qui tourne chez un client, avec sa propre base.
 *
 * LA CLÉ N'EST JAMAIS STOCKÉE EN CLAIR, ET N'EST MONTRÉE QU'UNE FOIS
 * -------------------------------------------------------------------
 * Elle joue le rôle de mot de passe pour la synchronisation. En clair, une base dérobée donnerait
 * accès à toutes les installations d'un coup. On garde son empreinte SHA-256 — qui suffit à
 * retrouver l'installation lors d'un appel — et ses huit premiers caractères pour l'afficher.
 *
 * Conséquence assumée : une clé perdue ne se retrouve pas, elle se REMPLACE (voir renouvelerCle).
 * C'est le prix d'une clé qu'on ne peut pas voler dans la base.
 */
class Installation extends Model
{
    use HasFactory;

    protected $table = 'installations';
    protected $primaryKey = 'installation_id';

    protected $fillable = [
        'client_id', 'nom', 'url', 'cle_hash', 'cle_apercu',

        // L'empreinte n'est PAS un secret, et ne rejoint donc pas $hidden : elle identifie une
        // machine, elle ne l'authentifie pas. On l'affiche d'ailleurs sur la fiche client, pour
        // pouvoir vérifier au téléphone qu'on parle bien de la même installation.
        'empreinte',

        'rappel_jeton', 'rappel_le',
        'version', 'vue_le', 'compteurs', 'active',
    ];

    protected $casts = [
        'compteurs' => 'array',
        'vue_le' => 'datetime',
        'rappel_le' => 'datetime',
        'active' => 'boolean',
    ];

    protected $hidden = ['cle_hash', 'rappel_jeton'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id', 'client_id');
    }

    public function entites(): HasMany
    {
        return $this->hasMany(Entite::class, 'installation_id', 'installation_id');
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class, 'installation_id', 'installation_id');
    }

    /** Les clés d'activation émises pour cette installation — les plus récentes d'abord. */
    public function clesActivation(): HasMany
    {
        return $this->hasMany(CleActivation::class, 'installation_id', 'installation_id')
            ->orderByDesc('created_at');
    }

    /**
     * Génère une clé, l'enregistre sous forme d'empreinte, et RETOURNE sa valeur en clair.
     *
     * C'est le seul moment où elle est lisible. À l'appelant de l'afficher immédiatement — elle
     * n'est plus récupérable ensuite.
     */
    public function renouvelerCle(): string
    {
        $cle = 'ins_'.bin2hex(random_bytes(16));

        $this->forceFill([
            'cle_hash' => hash('sha256', $cle),
            'cle_apercu' => substr($cle, 0, 12),
        ])->save();

        return $cle;
    }

    /** Retrouve une installation à partir de la clé présentée dans un appel de synchronisation. */
    public static function parCle(?string $cle): ?self
    {
        if (! $cle) {
            return null;
        }

        return static::where('cle_hash', hash('sha256', $cle))->where('active', true)->first();
    }

    /** Une installation muette depuis plus de trois jours mérite un coup de téléphone. */
    public function estMuette(): bool
    {
        return $this->vue_le === null || $this->vue_le->lt(now()->subDays(3));
    }
}
