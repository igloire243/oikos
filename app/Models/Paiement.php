<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * UN VERSEMENT — une partie de ce qu'une facture réclame.
 *
 * Il ne s'efface jamais : un versement annoncé par le client mais jamais arrivé se marque « non
 * reçu » (`non_recu_le`, avec son motif). Effacer la ligne ferait disparaître la preuve qu'on avait
 * été prévenu d'un paiement, et la réponse à « qui a dit avoir payé ? ».
 *
 * @property int $id
 * @property int $facture_id
 * @property int $montant_centimes
 * @property string $moyen
 * @property string|null $reference
 * @property Carbon $recu_le
 * @property string|null $notes
 * @property Carbon|null $non_recu_le
 * @property string|null $motif_non_recu
 * @property int|null $saisi_par_id
 * @property-read Facture $facture
 */
class Paiement extends Model
{
    public const MOYENS = [
        'ESPECES' => 'Espèces',
        'MOBILE_MONEY' => 'Mobile money',
        'EN_LIGNE' => 'Paiement en ligne',
        'VIREMENT' => 'Virement bancaire',
        'CHEQUE' => 'Chèque',
        'AUTRE' => 'Autre',
    ];

    /** @var list<string> */
    protected $fillable = ['facture_id', 'montant_centimes', 'moyen', 'reference', 'recu_le', 'notes', 'non_recu_le', 'motif_non_recu', 'saisi_par_id'];

    /** @var array<string, string> */
    protected $casts = [
        'montant_centimes' => 'integer',
        'recu_le' => 'date',
        'non_recu_le' => 'datetime',
    ];

    /** @return BelongsTo<Facture, $this> */
    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    /** @return BelongsTo<User, $this> */
    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par_id');
    }

    public function estRecu(): bool
    {
        return $this->non_recu_le === null;
    }
}
