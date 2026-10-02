<?php

namespace App\Metier\Commerce\Passerelles;

use App\Metier\Commerce\Montant;
use App\Models\DemandePaiement;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FLUTTERWAVE (API v3, « Standard ») — le client paie sur la page hébergée du fournisseur : carte,
 * mobile money, virement. Aucune donnée de paiement ne passe par la console.
 *
 * Trois points qui ne se devinent pas :
 *   · Flutterwave compte en UNITÉS (12.50), nous en centimes : la conversion passe par `Montant`, et
 *     la comparaison au retour se refait en centimes, jamais en flottants.
 *   · Le statut qui compte est « successful » lu sur `verify_by_reference` — pas celui que l'adresse
 *     de retour porte (`?status=successful`), que n'importe qui peut taper.
 *   · La notification est authentifiée par l'en-tête `verif-hash`, qui doit être égal au secret posé
 *     dans le tableau de bord. Sans secret configuré, TOUTE notification est refusée : un contrôle
 *     qui s'ouvre quand il n'est pas réglé n'en est pas un.
 */
class Flutterwave implements Passerelle
{
    public function initier(DemandePaiement $demande): string
    {
        $client = $demande->facture->periode->abonnement->entite->installation->client;

        $reponse = $this->http()->post('/payments', [
            'tx_ref' => $demande->reference,
            'amount' => Montant::enUnites($demande->montant_centimes, $demande->devise),
            'currency' => $demande->devise,
            'redirect_url' => route('paiement.retour', $demande->reference),
            'customer' => [
                'email' => $client->contact_email ?: 'facturation@invalid.example',
                'name' => $client->contact_nom ?: $client->nom,
                'phonenumber' => $client->contact_telephone,
            ],
            'customizations' => [
                'title' => 'Oikos',
                'description' => 'Facture '.$demande->facture->numero,
            ],
        ]);

        $lien = $reponse->json('data.link');
        if (! $reponse->successful() || ! is_string($lien) || $lien === '') {
            Log::warning('Flutterwave : paiement non initié', ['demande' => $demande->reference, 'statut' => $reponse->status(), 'message' => $reponse->json('message')]);

            throw new \RuntimeException('Le prestataire de paiement est indisponible. Réessayez dans un instant.');
        }

        return $lien;
    }

    public function verifier(DemandePaiement $demande): ?array
    {
        $reponse = $this->http()->get('/transactions/verify_by_reference', ['tx_ref' => $demande->reference]);

        // Inconnue du fournisseur : le client n'est jamais allé au bout de la page de paiement.
        if (! $reponse->successful() || $reponse->json('data') === null) {
            return null;
        }

        $statut = (string) $reponse->json('data.status');

        return [
            'statut' => match ($statut) {
                'successful' => 'PAYE',
                'failed', 'cancelled' => 'ECHEC',
                default => 'EN_ATTENTE',
            },
            'montant_centimes' => Montant::enCentimes((string) $reponse->json('data.amount'), (string) $reponse->json('data.currency')),
            'devise' => (string) $reponse->json('data.currency'),
            'reference_externe' => $reponse->json('data.id') !== null ? 'FLW-'.$reponse->json('data.id') : null,
        ];
    }

    public function referenceNotifiee(Request $requete): ?string
    {
        $secret = config('oikos.flutterwave.hash_notification');

        if (! is_string($secret) || $secret === '' || ! hash_equals($secret, (string) $requete->header('verif-hash'))) {
            return null;
        }

        $reference = $requete->input('data.tx_ref') ?? $requete->input('txRef');

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    private function http(): PendingRequest
    {
        $cle = config('oikos.flutterwave.cle_secrete');
        if (! is_string($cle) || $cle === '') {
            throw new \RuntimeException('Flutterwave : FLUTTERWAVE_SECRET_KEY n\'est pas renseignée.');
        }

        return Http::withToken($cle)->baseUrl((string) config('oikos.flutterwave.url'))->acceptJson()->timeout(15);
    }
}
