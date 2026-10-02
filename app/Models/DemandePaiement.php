<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * UNE TENTATIVE DE PAIEMENT EN LIGNE — ce qu'on a demandé au fournisseur, pas ce qu'on a reçu.
 *
 * Elle ne devient un `Paiement` que lorsque le fournisseur CONFIRME (`PaiementsEnLigne::confirmer`).
 * Le retour du client sur notre site ne prouve rien : n'importe qui peut ouvrir l'adresse de retour.
 *
 * @property int $id
 * @property int $facture_id
 * @property string $reference
 * @property int $montant_centimes
 * @property string $devise
 * @property string $passerelle
 * @property string $statut
 * @property string|null $reference_externe
 * @property int|null $paiement_id
 * @property string|null $motif
 * @property Carbon|null $confirmee_le
 * @property-read Facture $facture
 */
class DemandePaiement extends Model
{
    public const EN_ATTENTE = 'EN_ATTENTE';

    public const CONFIRMEE = 'CONFIRMEE';

    public const ECHOUEE = 'ECHOUEE';

    protected $table = 'demandes_paiement';

    /** @var list<string> */
    protected $fillable = ['facture_id', 'reference', 'montant_centimes', 'devise', 'passerelle', 'statut', 'reference_externe', 'paiement_id', 'motif', 'confirmee_le'];

    /** @var array<string, string> */
    protected $casts = ['montant_centimes' => 'integer', 'confirmee_le' => 'datetime'];

    /** @return BelongsTo<Facture, $this> */
    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }
}
