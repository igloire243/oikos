<?php

namespace App\Http\Controllers;

use App\Models\EntreeJournal;
use App\Models\Facture;
use App\Models\Paiement;
use App\Support\EncaissementFacture;
use App\Support\Paiement\DemarrerPaiementEnLigne;
use App\Support\Paiement\PasserellePaiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * ENCAISSEMENT EN LIGNE PAR AGRÉGATEUR MOBILE MONEY (FlexPay).
 *
 * DEUX ENTRÉES, DEUX MONDES :
 *   - demarrer()  est appelée par un opérateur connecté qui clique « Payer maintenant » sur une
 *     facture. Elle POUSSE une demande vers le téléphone du client. Rien n'est encaissé.
 *   - webhook()   est appelée par FlexPay, sans session ni cookie. Elle n'encaisse JAMAIS sur la
 *     foi du corps reçu : elle redemande l'état de la transaction à FlexPay (verifier()), puis
 *     réutilise EXACTEMENT la même logique que l'encaissement manuel (EncaissementFacture).
 *
 * POURQUOI LE WEBHOOK NE REND JAMAIS D'ERREUR 4xx/5xx
 * ---------------------------------------------------
 * Un agrégateur rejoue son appel tant qu'il n'a pas reçu un 2xx. Une facture introuvable, une
 * signature absente, un paiement déjà soldé : tout cela se répond « 200 OK » avec un corps
 * explicatif. Le seul cas où l'on refuse (403), c'est une signature FOURNIE mais FAUSSE — là,
 * quelqu'un se fait passer pour FlexPay, et le rejeu ne changera rien.
 */
class PaiementEnLigneController extends Controller
{
    public function __construct(private readonly PasserellePaiement $passerelle) {}

    /**
     * « Payer maintenant » depuis la console : pousse une invite de paiement sur le téléphone du
     * client. La logique est dans DemarrerPaiementEnLigne, partagée avec l'API que le produit
     * appelle (Api\PaiementController).
     */
    public function demarrer(Request $request, Facture $facture, DemarrerPaiementEnLigne $action)
    {
        $donnees = $request->validate([
            'operateur' => ['required', Rule::in(Paiement::ENCAISSABLES_EN_LIGNE)],
            'telephone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\s().-]{9,20}$/'],
        ], [], [
            'telephone' => 'numéro de téléphone',
        ]);

        $resultat = $action->pour($facture, $donnees['operateur'], $donnees['telephone'], 'console');

        return back()->with($resultat['ok'] ? 'ok' : 'erreur', $resultat['message']);
    }

    /**
     * Le callback de FlexPay. Pas de session, pas de CSRF (exempté dans bootstrap/app.php).
     */
    public function webhook(Request $request)
    {
        if (! $this->passerelle->signatureValide($request)) {
            Log::warning('Webhook FlexPay : signature invalide', ['ip' => $request->ip()]);

            return response('Signature invalide', 403);
        }

        $charge = $request->json()->all() ?: $request->all();
        $orderNumber = $this->passerelle->orderNumberDuCallback($charge);

        if (! $orderNumber) {
            Log::info('Webhook FlexPay : corps sans orderNumber', ['charge' => $charge]);

            return response('Corps non reconnu', 200);
        }

        $paiement = Paiement::where('order_number', $orderNumber)->first();

        if (! $paiement) {
            Log::info('Webhook FlexPay : order_number inconnu', ['order_number' => $orderNumber]);

            return response('Inconnu', 200);
        }

        // Déjà traité : un rejeu. On acquitte sans rien refaire.
        if (in_array($paiement->statut, [Paiement::CONFIRME, Paiement::ECHOUE], true)) {
            return response('Déjà traité', 200);
        }

        // ON NE CROIT PAS LE CORPS DU CALLBACK. On redemande l'état à FlexPay, signé par notre jeton.
        $verif = $this->passerelle->verifier($orderNumber);

        if ($verif->statut === Paiement::EN_ATTENTE) {
            // Toujours en cours (ou FlexPay injoignable) : on laisse tel quel, le webhook reviendra.
            return response('En attente', 200);
        }

        if ($verif->estEchoue()) {
            $paiement->update([
                'statut' => Paiement::ECHOUE,
                'charge_brute' => $verif->brut ?: $paiement->charge_brute,
            ]);
            EntreeJournal::noter('PAIEMENT_EN_LIGNE_ECHOUE', $paiement->facture, [
                'order_number' => $orderNumber,
                'numero' => $paiement->facture->numero,
            ]);

            return response('Échec enregistré', 200);
        }

        // CONFIRMÉ. On pose la référence réelle de la transaction — c'est elle, via l'index unique
        // (fournisseur, reference), qui rend un futur rejeu inoffensif.
        $paiement->update([
            'reference' => $verif->reference ?: ('FLEXPAY-'.$orderNumber),
            'statut' => Paiement::CONFIRME,
            'confirme_le' => now(),
            'charge_brute' => $verif->brut ?: $paiement->charge_brute,
        ]);

        EntreeJournal::noter('PAIEMENT_EN_LIGNE_CONFIRME', $paiement->facture, [
            'order_number' => $orderNumber,
            'reference' => $paiement->reference,
            'montant_usd' => $paiement->montant / 100,
        ]);

        $suite = EncaissementFacture::traiter($paiement->facture);

        return response($suite['message'], 200);
    }
}
