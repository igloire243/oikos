<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * L'ABONNEMENT D'UNE ENTITÉ — le contrat, dont les périodes disent ce qui a été vendu et quand.
 *
 * Son état se LIT : sur la période qui couvre le jour, sur le délai de grâce qui suit la dernière,
 * et sur `resilie_le`. Aucun statut stocké ne peut donc rester « actif » après l'échéance.
 *
 * @property int $id
 * @property int $installation_id
 * @property int $entite_id
 * @property Carbon|null $resilie_le
 * @property string|null $motif_resiliation
 * @property-read Entite $entite
 * @property-read Installation $installation
 */
class Abonnement extends Model
{
    public const A_VENIR = 'A_VENIR';

    public const EN_COURS = 'EN_COURS';

    public const EN_GRACE = 'EN_GRACE';

    public const ECHU = 'ECHU';

    public const RESILIE = 'RESILIE';

    /** @var array<string, string> */
    public const ETATS = [
        self::A_VENIR => 'À venir',
        self::EN_COURS => 'En cours',
        self::EN_GRACE => 'En délai de grâce',
        self::ECHU => 'Échu',
        self::RESILIE => 'Résilié',
    ];

    /** @var list<string> */
    protected $fillable = ['installation_id', 'entite_id', 'resilie_le', 'motif_resiliation'];

    /** @var array<string, string> */
    protected $casts = ['resilie_le' => 'datetime'];

    /** @return BelongsTo<Entite, $this> */
    public function entite(): BelongsTo
    {
        return $this->belongsTo(Entite::class);
    }

    /** @return BelongsTo<Installation, $this> */
    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    /** @return HasMany<PeriodeAbonnement, $this> */
    public function periodes(): HasMany
    {
        return $this->hasMany(PeriodeAbonnement::class)->orderBy('debut');
    }

    public function estResilie(): bool
    {
        return $this->resilie_le !== null;
    }

    /** La période qui couvre ce jour — ou null. */
    public function periodeAu(Carbon $jour): ?PeriodeAbonnement
    {
        return $this->periodes->first(fn (PeriodeAbonnement $p) => $p->couvre($jour));
    }

    /** La dernière période vendue, celle dont la fin compte pour la grâce et le renouvellement. */
    public function dernierePeriode(): ?PeriodeAbonnement
    {
        return $this->periodes->sortBy('fin')->last();
    }

    public function etat(?Carbon $maintenant = null): string
    {
        $jour = ($maintenant ?? Carbon::now())->copy()->startOfDay();
        $derniere = $this->dernierePeriode();

        return match (true) {
            $this->estResilie() => self::RESILIE,
            $derniere === null => self::ECHU,
            $this->periodeAu($jour) !== null => self::EN_COURS,
            $this->periodes->every(fn (PeriodeAbonnement $p) => $p->debut->gt($jour)) => self::A_VENIR,
            $jour->lte($derniere->fin->copy()->addDays((int) config('oikos.grace_jours', 14))) => self::EN_GRACE,
            default => self::ECHU,
        };
    }
}
