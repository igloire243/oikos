<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * UNE PÉRIODE VENDUE — une offre, deux dates, un prix figé.
 *
 * Elle ne se modifie pas : changer d'offre se fait au renouvellement, par une nouvelle période. La
 * facture du lot C3 s'y rattachera, et une facture ne doit jamais dire autre chose que la période
 * qu'elle facture.
 *
 * @property int $id
 * @property int $abonnement_id
 * @property int $offre_id
 * @property Carbon $debut
 * @property Carbon $fin
 * @property int $montant_centimes
 * @property string $devise
 * @property bool $au_prorata
 * @property int|null $vendue_par_id
 * @property-read Offre $offre
 * @property-read Abonnement $abonnement
 */
class PeriodeAbonnement extends Model
{
    protected $table = 'periodes_abonnement';

    /** @var list<string> */
    protected $fillable = ['abonnement_id', 'offre_id', 'debut', 'fin', 'montant_centimes', 'devise', 'au_prorata', 'vendue_par_id'];

    /** @var array<string, string> */
    protected $casts = [
        'debut' => 'date',
        'fin' => 'date',
        'montant_centimes' => 'integer',
        'au_prorata' => 'boolean',
    ];

    /** @return BelongsTo<Offre, $this> */
    public function offre(): BelongsTo
    {
        return $this->belongsTo(Offre::class);
    }

    /** @return BelongsTo<Abonnement, $this> */
    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class);
    }

    /** @return BelongsTo<User, $this> */
    public function vendeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendue_par_id');
    }

    /** `fin` est le dernier jour COUVERT : une période du 1er au 31 couvre le 31. */
    public function couvre(Carbon $jour): bool
    {
        $jour = $jour->copy()->startOfDay();

        return $jour->gte($this->debut) && $jour->lte($this->fin);
    }
}
