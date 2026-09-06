<?php

namespace App\Support\Paiement;

use App\Models\Facture;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * FLEXPAY — l'agrégateur mobile money retenu pour la RDC (SDK et API REST, M-Pesa / Orange Money /
 * Airtel Money sous une seule intégration).
 *
 * DÉROULÉ D'UN ENCAISSEMENT EN LIGNE
 * ----------------------------------
 *  1. demarrer()  → POST {base}/paymentService : FlexPay pousse une invite sur le téléphone du
 *                   client et nous rend un « orderNumber » pour suivre l'opération.
 *  2. le client valide (ou non) sur son téléphone.
 *  3. FlexPay POST notre webhook  → on NE crédite pas sur ce corps : on rappelle verifier().
 *  4. verifier()  → GET {base}/check/{orderNumber} : l'état fait foi, signé par notre jeton.
 *
 * NOMS DE CHAMPS DÉFENSIFS
 * ------------------------
 * L'API FlexPay a plusieurs révisions en circulation (`orderNumber` vs `order_number`,
 * `transaction.status` vs `status`…). On lit donc chaque information sous ses variantes connues
 * plutôt que de dépendre d'une seule orthographe — un renommage côté FlexPay ne doit pas faire
 * passer un paiement reçu pour un échec.
 */
class FlexPayPasserelle implements PasserellePaiement
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $marchand,
        private readonly string $jeton,
        private readonly string $secretWebhook,
        private readonly string $devise,
        private readonly ?string $callbackUrl,
        private readonly int $timeout,
    ) {}

    public function estActive(): bool
    {
        return $this->marchand !== '' && $this->jeton !== '';
    }

    public function nom(): string
    {
        return 'FlexPay';
    }

    public function demarrer(Facture $facture, string $telephone, string $operateur, int $montant): ResultatDemarrage
    {
        if (! $this->estActive()) {
            throw new RuntimeException('FlexPay est activé mais le code marchand ou le jeton manque.');
        }

        if (strtoupper($facture->devise) !== $this->devise) {
            return ResultatDemarrage::echec(sprintf(
                "La facture est en %s, or FlexPay encaisse en %s. On ne convertit pas une facture à la volée.",
                $facture->devise,
                $this->devise,
            ));
        }

        $callback = $this->callbackUrl ?: route('webhooks.flexpay');

        try {
            $reponse = Http::withToken($this->jeton)
                ->timeout($this->timeout)
                ->acceptJson()
                ->asJson()
                ->post($this->baseUrl.'/paymentService', [
                    'merchant' => $this->marchand,
                    'type' => '1', // 1 = mobile money
                    'reference' => $facture->numero,
                    'amount' => number_format($montant / 100, 2, '.', ''),
                    'currency' => $this->devise,
                    'callbackUrl' => $callback,
                    'phone' => $this->normaliserTelephone($telephone),
                ]);
        } catch (\Throwable $e) {
            Log::warning('FlexPay demarrer() injoignable', ['facture' => $facture->numero, 'erreur' => $e->getMessage()]);

            return ResultatDemarrage::echec("FlexPay est injoignable pour le moment. Réessayez dans un instant.");
        }

        $corps = $reponse->json() ?? [];
        $code = (string) ($corps['code'] ?? '1');
        $orderNumber = $corps['orderNumber'] ?? $corps['order_number'] ?? null;

        if ($reponse->failed() || $code !== '0' || ! $orderNumber) {
            return ResultatDemarrage::echec(
                $corps['message'] ?? "FlexPay a refusé la demande de paiement.",
                $corps,
            );
        }

        return ResultatDemarrage::succes(
            (string) $orderNumber,
            "Demande de paiement envoyée. Le client doit valider sur son téléphone.",
            $corps,
        );
    }

    public function verifier(string $orderNumber): ResultatVerification
    {
        if (! $this->estActive()) {
            throw new RuntimeException('FlexPay non configuré.');
        }

        try {
            $reponse = Http::withToken($this->jeton)
                ->timeout($this->timeout)
                ->acceptJson()
                ->get($this->baseUrl.'/check/'.urlencode($orderNumber));
        } catch (\Throwable $e) {
            Log::warning('FlexPay verifier() injoignable', ['orderNumber' => $orderNumber, 'erreur' => $e->getMessage()]);

            // Injoignable ≠ échoué : on laisse le paiement EN_ATTENTE, le webhook sera rejoué.
            return new ResultatVerification(Paiement::EN_ATTENTE, null, 0, $this->devise, ['injoignable' => true]);
        }

        return $this->lireTransaction($reponse->json() ?? []);
    }

    public function signatureValide(Request $requete): bool
    {
        // Pas de secret configuré : on ne peut pas vérifier la signature. Ce n'est pas une faille
        // ouverte — verifier() redemande l'état à FlexPay avec notre jeton avant tout encaissement.
        if ($this->secretWebhook === '') {
            return true;
        }

        $fournie = $requete->header('X-Flexpay-Signature', '');
        if ($fournie === '') {
            return false;
        }

        $attendue = hash_hmac('sha256', $requete->getContent(), $this->secretWebhook);

        return hash_equals($attendue, $fournie);
    }

    public function orderNumberDuCallback(array $charge): ?string
    {
        $valeur = $charge['orderNumber']
            ?? $charge['order_number']
            ?? ($charge['transaction']['orderNumber'] ?? null)
            ?? ($charge['transaction']['order_number'] ?? null);

        return $valeur !== null ? (string) $valeur : null;
    }

    /**
     * Traduit la réponse de /check en un ResultatVerification. FlexPay renvoie un `code` global et
     * un bloc `transaction` ; selon les versions le statut fin est dans `transaction.status` ou
     * directement `status`. « 0 » vaut succès des deux côtés.
     */
    private function lireTransaction(array $corps): ResultatVerification
    {
        $tx = $corps['transaction'] ?? $corps;

        $codeGlobal = (string) ($corps['code'] ?? '1');
        $statutTx = (string) ($tx['status'] ?? $tx['statut'] ?? '1');

        $reference = $tx['provider_reference']
            ?? $tx['providerReference']
            ?? $tx['reference']
            ?? null;

        $montantMajeur = (float) ($tx['amount'] ?? $tx['montant'] ?? 0);
        $montantCentimes = (int) round($montantMajeur * 100);
        $devise = strtoupper((string) ($tx['currency'] ?? $tx['devise'] ?? $this->devise));

        if ($codeGlobal === '0' && $statutTx === '0') {
            $statut = Paiement::CONFIRME;
        } elseif (in_array($statutTx, ['1', '2'], true) && $codeGlobal !== '0') {
            // FlexPay : 1 = échec, 2 = annulé/expiré. Le code global non nul confirme que ce n'est
            // pas simplement « en cours ».
            $statut = Paiement::ECHOUE;
        } else {
            $statut = Paiement::EN_ATTENTE;
        }

        return new ResultatVerification(
            $statut,
            $reference !== null ? (string) $reference : null,
            $montantCentimes,
            $devise,
            $corps,
        );
    }

    /** FlexPay attend un numéro international sans « + » ni espaces. */
    private function normaliserTelephone(string $brut): string
    {
        $chiffres = preg_replace('/\D+/', '', $brut) ?? '';

        // Un numéro saisi « 0810000000 » devient « 243810000000 ».
        if (str_starts_with($chiffres, '0')) {
            $chiffres = '243'.substr($chiffres, 1);
        }

        return $chiffres;
    }
}
