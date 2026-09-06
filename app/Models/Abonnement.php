<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Le droit d'écrire, pour UNE entité, jusqu'à UNE date.
 *
 * LES SIX ÉTATS, ET LE SEUL CHEMIN ENTRE EUX
 * --------------------------------------------
 *   ESSAI    → tout, pendant N jours          → paiement : ACTIF   · échéance : IMPAYE
 *   ACTIF    → tout                            → fin de période sans paiement : IMPAYE
 *   IMPAYE   → tout, pendant le délai de grâce → paiement : ACTIF   · fin du délai : SUSPENDU
 *   SUSPENDU → LECTURE SEULE                   → paiement : ACTIF   · 90 j : RESILIE
 *   RESILIE  → rien, sauf l'export des données
 *   BLOQUE   → rien (décision manuelle : fraude, litige)
 *
 * SUSPENDU EST EN LECTURE SEULE, JAMAIS UNE COUPURE TOTALE. Une église qui a saisi trois ans de
 * membres et de finances ne doit pas perdre l'accès à ses propres données parce qu'un paiement
 * mobile money a échoué un vendredi soir. Couper l'écriture suffit à faire payer ; couper la
 * lecture vaut la réputation d'un logiciel qui prend les données en otage — et entre pasteurs,
 * cela se sait vite.
 */
class Abonnement extends Model
{
    protected $table = 'abonnements';
    protected $primaryKey = 'abonnement_id';

    public const ESSAI = 'ESSAI';
    public const ACTIF = 'ACTIF';
    public const IMPAYE = 'IMPAYE';
    public const SUSPENDU = 'SUSPENDU';
    public const RESILIE = 'RESILIE';
    public const BLOQUE = 'BLOQUE';

    public const STATUTS = [
        self::ESSAI => "Période d'essai",
        self::ACTIF => 'Actif',
        self::IMPAYE => 'Impayé (délai de grâce)',
        self::SUSPENDU => 'Suspendu — lecture seule',
        self::RESILIE => 'Résilié',
        self::BLOQUE => 'Bloqué',
    ];

    /** Les états qui laissent écrire. C'est la seule liste qui compte pour l'accès. */
    public const OUVRENT_ECRITURE = [self::ESSAI, self::ACTIF, self::IMPAYE];

    protected $fillable = [
        'installation_id', 'plan_id',
        'beneficiaire_type', 'beneficiaire_ref', 'payeur_type', 'payeur_ref',
        'statut', 'essai_fin', 'periode_debut', 'periode_fin', 'grace_fin',
        'resilie_le', 'motif_resiliation',
    ];

    protected $casts = [
        'essai_fin' => 'datetime',
        'periode_debut' => 'datetime',
        'periode_fin' => 'datetime',
        'grace_fin' => 'datetime',
        'resilie_le' => 'datetime',
    ];

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class, 'installation_id', 'installation_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'plan_id');
    }

    public function ouvreLEcriture(): bool
    {
        return in_array($this->statut, self::OUVRENT_ECRITURE, true);
    }

    /** Le payeur est-il quelqu'un d'autre que le bénéficiaire ? */
    public function payeParUnTiers(): bool
    {
        return $this->payeur_type !== null
            && ($this->payeur_type !== $this->beneficiaire_type
                || (int) $this->payeur_ref !== (int) $this->beneficiaire_ref);
    }

    public function joursRestants(): ?int
    {
        return $this->periode_fin?->diffInDays(now(), false) * -1;
    }
}
