<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends Model
{
    protected $table = 'paiements';
    protected $primaryKey = 'paiement_id';

    public const EN_ATTENTE = 'EN_ATTENTE';
    public const CONFIRME = 'CONFIRME';
    public const ECHOUE = 'ECHOUE';
    public const REMBOURSE = 'REMBOURSE';

    /**
     * Les modes d'encaissement. Par ordre d'usage réel en RDC — le mobile money d'abord, et les
     * espèces bien avant la carte bancaire. Écarter le cash reviendrait à écarter une partie des
     * clients.
     */
    public const FOURNISSEURS = [
        'MPESA' => 'M-Pesa (Vodacom)',
        'ORANGE_MONEY' => 'Orange Money',
        'AIRTEL_MONEY' => 'Airtel Money',
        'VIREMENT' => 'Virement bancaire',
        'ESPECES' => 'Espèces',
        'DEPOT_MARCHAND' => 'Dépôt sur compte marchand',
    ];

    /** Ceux qui exigent une confirmation humaine : personne ne nous préviendra automatiquement. */
    public const CONFIRMATION_MANUELLE = ['VIREMENT', 'ESPECES', 'DEPOT_MARCHAND'];

    /** Ceux qu'un agrégateur mobile money (FlexPay) peut encaisser automatiquement. */
    public const ENCAISSABLES_EN_LIGNE = ['MPESA', 'ORANGE_MONEY', 'AIRTEL_MONEY'];

    protected $fillable = [
        'facture_id', 'fournisseur', 'reference', 'order_number', 'telephone', 'initie_le',
        'montant', 'devise', 'statut', 'charge_brute', 'confirme_par_user_id', 'confirme_le',
    ];

    protected $casts = [
        'charge_brute' => 'array',
        'confirme_le' => 'datetime',
        'initie_le' => 'datetime',
    ];

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class, 'facture_id', 'facture_id');
    }

    public function demandeUneConfirmationHumaine(): bool
    {
        return in_array($this->fournisseur, self::CONFIRMATION_MANUELLE, true);
    }

    /** Une demande « Payer maintenant » restée sans réponse au-delà du délai configuré. */
    public function estExpire(): bool
    {
        return $this->statut === self::EN_ATTENTE
            && $this->initie_le !== null
            && $this->initie_le->addMinutes((int) config('flexpay.delai_expiration_minutes', 15))->isPast();
    }
}
