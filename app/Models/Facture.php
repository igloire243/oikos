<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facture extends Model
{
    protected $table = 'factures';
    protected $primaryKey = 'facture_id';

    public const EMISE = 'EMISE';
    public const PAYEE = 'PAYEE';
    public const ANNULEE = 'ANNULEE';
    public const IRRECOUVRABLE = 'IRRECOUVRABLE';

    protected $fillable = [
        'client_id', 'abonnement_id', 'numero', 'montant', 'devise',
        'statut', 'du_le', 'payee_le', 'note',
    ];

    protected $casts = ['du_le' => 'date', 'payee_le' => 'datetime'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id', 'client_id');
    }

    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class, 'abonnement_id', 'abonnement_id');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class, 'facture_id', 'facture_id');
    }

    /**
     * Un numéro lisible et continu : FACT-2026-0042.
     *
     * Le compteur repart à chaque année civile — une facture doit pouvoir se retrouver par son
     * millésime, et un compteur qui ne se remet jamais à zéro atteint des nombres qu'on ne dicte
     * plus au téléphone.
     */
    public static function prochainNumero(): string
    {
        $annee = now()->year;

        $dernier = static::where('numero', 'like', "FACT-{$annee}-%")
            ->orderByDesc('numero')
            ->value('numero');

        $suite = $dernier ? ((int) substr($dernier, -4)) + 1 : 1;

        return sprintf('FACT-%d-%04d', $annee, $suite);
    }

    public function resteADevoir(): int
    {
        return max(0, $this->montant - $this->paiements()
            ->where('statut', Paiement::CONFIRME)->sum('montant'));
    }
}
