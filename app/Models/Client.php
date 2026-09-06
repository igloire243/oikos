<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Une communauté cliente. */
class Client extends Model
{
    use HasFactory;

    protected $table = 'clients';
    protected $primaryKey = 'client_id';

    public const STATUT_PROSPECT = 'PROSPECT';
    public const STATUT_ACTIF = 'ACTIF';
    public const STATUT_SUSPENDU = 'SUSPENDU';
    public const STATUT_RESILIE = 'RESILIE';

    public const STATUTS = [
        self::STATUT_PROSPECT => 'Prospect',
        self::STATUT_ACTIF => 'Actif',
        self::STATUT_SUSPENDU => 'Suspendu',
        self::STATUT_RESILIE => 'Résilié',
    ];

    protected $fillable = [
        'nom', 'pays', 'ville', 'contact_nom', 'contact_email', 'contact_telephone',
        'statut', 'notes',
        'vitrine', 'vitrine_accord_le', 'temoignage', 'temoignage_auteur', 'site_url',
    ];

    protected $casts = [
        'vitrine' => 'boolean',
        'vitrine_accord_le' => 'datetime',
    ];

    /**
     * Les clients que le site public a le droit de citer.
     *
     * DEUX CONDITIONS, PAS UNE. `vitrine` dit qu'il a donné son accord ; `statut = ACTIF` dit
     * qu'il l'est encore. Un client résilié qui avait accepté d'être cité l'année dernière n'est
     * pas une référence : le montrer comme tel serait faux, et il pourrait s'en apercevoir.
     */
    public function scopeCitables($query)
    {
        return $query->where('vitrine', true)->where('statut', self::STATUT_ACTIF);
    }

    public function installations(): HasMany
    {
        return $this->hasMany(Installation::class, 'client_id', 'client_id');
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class, 'client_id', 'client_id');
    }
}
