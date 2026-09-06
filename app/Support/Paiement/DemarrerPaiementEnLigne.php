<?php

namespace App\Support\Paiement;

use App\Models\EntreeJournal;
use App\Models\Facture;
use App\Models\Paiement;

/**
 * DÉMARRER UN ENCAISSEMENT EN LIGNE SUR UNE FACTURE — logique partagée entre les deux entrées :
 *   - la console (opérateur qui clique « Payer maintenant »),
 *   - l'API que le produit appelle (bouton « Payer » côté client, dans « Mon abonnement »).
 *
 * Les deux doivent créer EXACTEMENT le même paiement EN_ATTENTE et journaliser de la même façon,
 * sinon un paiement lancé depuis la console et un lancé depuis le produit laisseraient la base
 * dans deux états différents.
 *
 * Ne solde rien : le résultat arrive par le webhook.
 */
class DemarrerPaiementEnLigne
{
    public function __construct(private readonly PasserellePaiement $passerelle) {}

    /**
     * @param  string  $origine  d'où vient la demande, pour le journal ('console' | 'produit')
     * @return array{ok: bool, message: string, order_number?: string}
     */
    public function pour(Facture $facture, string $operateur, string $telephone, string $origine = 'console'): array
    {
        if (! $this->passerelle->estActive()) {
            return ['ok' => false, 'message' => "L'encaissement en ligne n'est pas configuré."];
        }

        if ($facture->statut !== Facture::EMISE) {
            return ['ok' => false, 'message' => "Cette facture n'est pas en attente d'encaissement."];
        }

        if (! in_array($operateur, Paiement::ENCAISSABLES_EN_LIGNE, true)) {
            return ['ok' => false, 'message' => "Opérateur non pris en charge pour l'encaissement en ligne."];
        }

        $montant = $facture->resteADevoir();
        if ($montant <= 0) {
            return ['ok' => false, 'message' => 'Cette facture est déjà entièrement couverte.'];
        }

        // Une demande déjà en cours et non expirée : on ne la double pas.
        $enCours = $facture->paiements()
            ->where('statut', Paiement::EN_ATTENTE)
            ->whereNotNull('order_number')
            ->latest('initie_le')
            ->first();

        if ($enCours && ! $enCours->estExpire()) {
            return ['ok' => false, 'message' => "Une demande de paiement est déjà en attente de validation sur le téléphone du client."];
        }

        $resultat = $this->passerelle->demarrer($facture, $telephone, $operateur, $montant);

        if (! $resultat->ok) {
            EntreeJournal::noter('PAIEMENT_EN_LIGNE_REFUSE', $facture, [
                'numero' => $facture->numero,
                'operateur' => $operateur,
                'origine' => $origine,
                'motif' => $resultat->message,
            ]);

            return ['ok' => false, 'message' => $resultat->message];
        }

        Paiement::create([
            'facture_id' => $facture->facture_id,
            'fournisseur' => $operateur,
            'reference' => null,
            'order_number' => $resultat->orderNumber,
            'telephone' => $telephone,
            'initie_le' => now(),
            'montant' => $montant,
            'devise' => $facture->devise,
            'statut' => Paiement::EN_ATTENTE,
            'charge_brute' => $resultat->brut ?: null,
        ]);

        EntreeJournal::noter('PAIEMENT_EN_LIGNE_INITIE', $facture, [
            'numero' => $facture->numero,
            'operateur' => $operateur,
            'origine' => $origine,
            'order_number' => $resultat->orderNumber,
            'montant_usd' => $montant / 100,
        ]);

        return ['ok' => true, 'message' => $resultat->message, 'order_number' => (string) $resultat->orderNumber];
    }
}
