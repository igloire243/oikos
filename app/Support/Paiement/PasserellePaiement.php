<?php

namespace App\Support\Paiement;

use App\Models\Facture;
use Illuminate\Http\Request;

/**
 * LE PILOTE D'AGRÉGATEUR MOBILE MONEY.
 *
 * Une seule implémentation réelle aujourd'hui — FlexPay — mais le reste du code ne connaît que
 * cette interface. Le jour où l'on ajoute ou remplace un agrégateur, c'est une classe de plus et
 * une ligne dans PaiementServiceProvider, rien d'autre à toucher.
 *
 * Tant qu'aucun compte marchand n'est configuré, c'est PasserelleInactive qui est branchée :
 * estActive() renvoie false, le bouton « Payer maintenant » disparaît, et l'encaissement manuel
 * (FactureController) reste l'unique voie.
 */
interface PasserellePaiement
{
    /** Un compte marchand est-il configuré et l'encaissement en ligne activé ? */
    public function estActive(): bool;

    /** Nom lisible de l'agrégateur, pour les messages et le journal. */
    public function nom(): string;

    /**
     * Pousse une demande de paiement vers le téléphone du client. Ne solde rien : le résultat
     * arrivera par le webhook.
     *
     * @param  string  $telephone  au format international sans « + » (ex. 243810000000)
     * @param  string  $operateur  un code de Paiement::FOURNISSEURS (MPESA | ORANGE_MONEY | AIRTEL_MONEY)
     * @param  int  $montant  en centimes
     */
    public function demarrer(Facture $facture, string $telephone, string $operateur, int $montant): ResultatDemarrage;

    /**
     * Redemande à l'agrégateur l'état d'une transaction. Appelé à la réception du webhook : on ne
     * crédite jamais sur la seule foi du corps du callback.
     */
    public function verifier(string $orderNumber): ResultatVerification;

    /**
     * La requête entrante du webhook provient-elle bien de l'agrégateur ? (signature partagée)
     * Renvoie true quand aucune signature n'est configurée — dans ce cas c'est verifier() qui porte
     * seul la sécurité.
     */
    public function signatureValide(Request $requete): bool;

    /**
     * Extrait l'identifiant de commande (orderNumber) du corps du webhook, pour retrouver le
     * paiement local correspondant. Renvoie null si le corps ne ressemble pas à un callback connu.
     */
    public function orderNumberDuCallback(array $charge): ?string;
}
