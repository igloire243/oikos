<?php

namespace App\Models;

use App\Metier\Commerce\Montant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * UNE FACTURE — ce qui est DÛ pour une période vendue.
 *
 * Elle ne se modifie pas et n'a pas de colonne d'état : on la lit sur les paiements. « Soldée » se
 * recalcule à chaque lecture — une colonne se décalerait au premier versement marqué « non reçu ».
 *
 * `EN_RETARD` n'est pas un état de plus mais une lecture par-dessus : une facture partiellement
 * payée peut être en retard, et c'est même le cas qui demande un coup de téléphone.
 *
 * @property int $id
 * @property string $numero
 * @property string|null $jeton_paiement
 * @property int $periode_abonnement_id
 * @property int $montant_centimes
 * @property string $devise
 * @property Carbon $emise_le
 * @property Carbon $echeance_le
 * @property int|null $emise_par_id
 * @property-read PeriodeAbonnement $periode
 * @property-read Collection<int, Paiement> $paiements
 */
class Facture extends Model
{
    public const EN_ATTENTE = 'EN_ATTENTE';

    public const PARTIELLE = 'PARTIELLE';

    public const SOLDEE = 'SOLDEE';

    public const ETATS = [
        self::EN_ATTENTE => 'En attente',
        self::PARTIELLE => 'Partiellement payée',
        self::SOLDEE => 'Soldée',
    ];

    /** @var list<string> */
    protected $fillable = ['numero', 'periode_abonnement_id', 'montant_centimes', 'devise', 'emise_le', 'echeance_le', 'emise_par_id', 'jeton_paiement'];

    /** @var array<string, string> */
    protected $casts = [
        'montant_centimes' => 'integer',
        'emise_le' => 'date',
        'echeance_le' => 'date',
    ];

    /** @return BelongsTo<PeriodeAbonnement, $this> */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeAbonnement::class, 'periode_abonnement_id');
    }

    /** @return HasMany<Paiement, $this> */
    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    /** Ce qui est réellement arrivé : un versement « non reçu » ne compte pas. */
    public function recuCentimes(): int
    {
        $paiements = $this->relationLoaded('paiements') ? $this->paiements : $this->paiements()->get();

        return (int) $paiements->whereNull('non_recu_le')->sum('montant_centimes');
    }

    public function restantCentimes(): int
    {
        return max(0, $this->montant_centimes - $this->recuCentimes());
    }

    public function etat(): string
    {
        $recu = $this->recuCentimes();

        return match (true) {
            $recu >= $this->montant_centimes => self::SOLDEE,
            $recu > 0 => self::PARTIELLE,
            default => self::EN_ATTENTE,
        };
    }

    public function estSoldee(): bool
    {
        return $this->etat() === self::SOLDEE;
    }

    public function enRetard(?Carbon $jour = null): bool
    {
        return ! $this->estSoldee() && $this->echeance_le->lt(($jour ?? Carbon::today())->copy()->startOfDay());
    }

    public function montant(): string
    {
        return Montant::formater($this->montant_centimes, $this->devise);
    }
}
