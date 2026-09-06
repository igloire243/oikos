<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use App\Models\Installation;
use App\Support\Paiement\DemarrerPaiementEnLigne;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * « PAYER » DEPUIS LE PRODUIT.
 *
 * Le produit (generation-joel) expose au client, sur sa page « Mon abonnement », un bouton
 * « Payer maintenant » par facture ouverte. Ce bouton appelle CETTE route — jamais FlexPay
 * directement : le produit n'a ni le compte marchand, ni les factures, ni les abonnements. Il
 * délègue à la console, qui possède tout cela.
 *
 * AUTHENTIFICATION : la clé de synchronisation de l'installation, en Bearer — exactement comme
 * /api/v1/synchronisation. Pas de session, pas de cookie.
 *
 * CE QUE CETTE ROUTE NE FAIT PAS : encaisser. Elle pousse une invite vers le téléphone du client
 * et rend la main. Le versement est confirmé plus tard par /webhooks/flexpay, et la resynchro
 * nocturne (ou le bouton « Actualiser » du produit) fait bouger la date côté client.
 *
 * PORTÉE : une installation ne peut lancer un paiement que sur SES factures — celles de son
 * client, rattachées à un de ses abonnements. On ne prend jamais le numéro de facture pour argent
 * comptant.
 */
class PaiementController extends Controller
{
    public function demarrer(Request $request, DemarrerPaiementEnLigne $action): JsonResponse
    {
        $installation = Installation::parCle($request->bearerToken());

        if (! $installation) {
            return response()->json([
                'ok' => false,
                'message' => 'Clé de synchronisation inconnue ou installation désactivée.',
            ], 401);
        }

        $donnees = $request->validate([
            'numero' => ['required', 'string', 'max:30'],
            'operateur' => ['required', Rule::in(\App\Models\Paiement::ENCAISSABLES_EN_LIGNE)],
            'telephone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\s().-]{9,20}$/'],
        ]);

        // La facture doit appartenir au client de CETTE installation, et lui être rattachée dès
        // qu'elle porte un abonnement — un client à deux serveurs ne paie pas sur l'un les
        // factures de l'autre.
        $facture = Facture::where('numero', $donnees['numero'])
            ->where('client_id', $installation->client_id)
            ->where(function ($q) use ($installation) {
                $q->whereNull('abonnement_id')
                    ->orWhereHas('abonnement', fn ($a) => $a->where('installation_id', $installation->installation_id));
            })
            ->first();

        if (! $facture) {
            return response()->json([
                'ok' => false,
                'message' => 'Facture introuvable pour cette installation.',
            ], 404);
        }

        $resultat = $action->pour($facture, $donnees['operateur'], $donnees['telephone'], 'produit');

        // 200 même en cas de refus métier (déjà couverte, demande en cours…) : ce n'est pas une
        // erreur de protocole, et le produit affiche simplement le message.
        return response()->json($resultat);
    }
}
