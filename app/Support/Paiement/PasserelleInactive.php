<?php

namespace App\Support\Paiement;

use App\Models\Facture;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * La passerelle branchée quand FLEXPAY_ACTIF est faux : aucun compte marchand, donc aucun
 * encaissement en ligne. estActive() renvoie false — les vues masquent le bouton « Payer
 * maintenant » — et toute tentative de démarrage lève une exception plutôt que d'échouer en
 * silence.
 *
 * C'est l'état par défaut du projet, et il est parfaitement fonctionnel : l'encaissement se fait à
 * la main dans /console/factures, exactement comme avant.
 */
class PasserelleInactive implements PasserellePaiement
{
    public function estActive(): bool
    {
        return false;
    }

    public function nom(): string
    {
        return 'aucun agrégateur';
    }

    public function demarrer(Facture $facture, string $telephone, string $operateur, int $montant): ResultatDemarrage
    {
        throw new RuntimeException("L'encaissement en ligne n'est pas configuré (FLEXPAY_ACTIF=false).");
    }

    public function verifier(string $orderNumber): ResultatVerification
    {
        throw new RuntimeException("L'encaissement en ligne n'est pas configuré.");
    }

    public function signatureValide(Request $requete): bool
    {
        return false;
    }

    public function orderNumberDuCallback(array $charge): ?string
    {
        return null;
    }
}
