<?php

namespace App\Support\Paiement;

use App\Models\Facture;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * CINETPAY — agrégateur pan-africain (mobile money RDC : M-Pesa / Orange Money / Airtel Money, plus
 * cartes). Deuxième choix après PawaPay.
 *
 * DÉROULÉ D'UN ENCAISSEMENT
 * ------------------------
 *  1. demarrer()  → POST {base}/payment : renvoie un `payment_token` et une URL. Ici on suit par
 *                   le `transaction_id` que NOUS avons choisi (le numéro de facture), qui joue le
 *                   rôle d'« orderNumber ».
 *  2. le client valide (invite mobile money).
 *  3. CinetPay POST notre notify_url  → on rappelle verifier().
 *  4. verifier()  → POST {base}/payment/check {apikey, site_id, transaction_id}.
 *
 * À FINALISER AVEC UN VRAI COMPTE : le mode exact de signature du notify (en-tête `x-token`,
 * recomposé en HMAC-SHA256 à partir d'une concaténation de champs `cpm_*`) est propre à l'espace
 * marchand. La structure ci-dessous suit l'API publique v2.
 */
class CinetPayPasserelle implements PasserellePaiement
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $siteId,
        private readonly string $apiKey,
        private readonly string $secretKey,
        private readonly string $devise,
        private readonly ?string $callbackUrl,
        private readonly ?string $returnUrl = null,
        private readonly int $timeout = 15,
    ) {}

    public function estActive(): bool
    {
        return $this->siteId !== '' && $this->apiKey !== '';
    }

    public function nom(): string
    {
        return 'CinetPay';
    }

    public function demarrer(Facture $facture, string $telephone, string $operateur, int $montant): ResultatDemarrage
    {
        if (! $this->estActive()) {
            throw new RuntimeException('CinetPay est activé mais le Site ID ou la clé API manque.');
        }

        if (strtoupper($facture->devise) !== $this->devise) {
            return ResultatDemarrage::echec(sprintf(
                'La facture est en %s, or CinetPay encaisse en %s. On ne convertit pas à la volée.',
                $facture->devise, $this->devise,
            ));
        }

        $callback = $this->callbackUrl ?: route('webhooks.flexpay');   // route de webhook partagée

        try {
            $reponse = Http::timeout($this->timeout)
                ->acceptJson()->asJson()
                ->post(rtrim($this->baseUrl, '/').'/payment', [
                    'apikey' => $this->apiKey,
                    'site_id' => $this->siteId,
                    'transaction_id' => $facture->numero,
                    'amount' => (int) round($montant / 100),   // CinetPay : entier, pas de décimales pour XOF/CDF
                    'currency' => $this->devise,
                    'channels' => 'MOBILE_MONEY',
                    'description' => 'Facture '.$facture->numero,
                    'customer_phone_number' => $this->normaliserTelephone($telephone),
                    'notify_url' => $callback,
                    'return_url' => $this->returnUrl ?: $callback,
                ]);
        } catch (\Throwable $e) {
            Log::warning('CinetPay demarrer() injoignable', ['facture' => $facture->numero, 'erreur' => $e->getMessage()]);

            return ResultatDemarrage::echec('CinetPay est injoignable pour le moment. Réessayez dans un instant.');
        }

        $corps = $reponse->json() ?? [];
        $code = (string) ($corps['code'] ?? '');

        // 201 = requête créée avec succès.
        if ($reponse->failed() || $code !== '201') {
            return ResultatDemarrage::echec(
                $corps['message'] ?? $corps['description'] ?? 'CinetPay a refusé la demande de paiement.',
                $corps,
            );
        }

        return ResultatDemarrage::succes(
            (string) $facture->numero,
            'Demande de paiement envoyée. Le client doit valider sur son téléphone.',
            $corps,
        );
    }

    public function verifier(string $orderNumber): ResultatVerification
    {
        if (! $this->estActive()) {
            throw new RuntimeException('CinetPay non configuré.');
        }

        try {
            $reponse = Http::timeout($this->timeout)
                ->acceptJson()->asJson()
                ->post(rtrim($this->baseUrl, '/').'/payment/check', [
                    'apikey' => $this->apiKey,
                    'site_id' => $this->siteId,
                    'transaction_id' => $orderNumber,
                ]);
        } catch (\Throwable $e) {
            Log::warning('CinetPay verifier() injoignable', ['transaction_id' => $orderNumber, 'erreur' => $e->getMessage()]);

            return new ResultatVerification(Paiement::EN_ATTENTE, null, 0, $this->devise, ['injoignable' => true]);
        }

        $corps = $reponse->json() ?? [];
        $data = $corps['data'] ?? [];
        $code = (string) ($corps['code'] ?? '');
        $statutTx = strtoupper((string) ($data['status'] ?? ''));

        $montantCentimes = (int) round(((float) ($data['amount'] ?? 0)) * 100);
        $devise = strtoupper((string) ($data['currency'] ?? $this->devise));
        $reference = $data['payment_method'] ?? null;
        $operateurRef = $data['operator_id'] ?? $data['payment_date'] ?? null;

        // code 00 + status ACCEPTED = payé ; REFUSED / status d'échec = échoué ; sinon en attente.
        if ($code === '00' && $statutTx === 'ACCEPTED') {
            $statut = Paiement::CONFIRME;
        } elseif (in_array($code, ['600', '602', '627'], true) || $statutTx === 'REFUSED') {
            $statut = Paiement::ECHOUE;
        } else {
            $statut = Paiement::EN_ATTENTE;
        }

        return new ResultatVerification(
            $statut,
            $operateurRef ? (string) $operateurRef : ($reference ? (string) $reference : null),
            $montantCentimes,
            $devise,
            $corps,
        );
    }

    public function signatureValide(Request $requete): bool
    {
        if ($this->secretKey === '') {
            return true;
        }

        // CinetPay envoie un en-tête `x-token` = HMAC-SHA256 d'une concaténation de champs cpm_*
        // du corps, avec la clé secrète. L'ordre exact des champs est propre à l'espace marchand ;
        // à confirmer contre le bac à sable. En attendant, on accepte si l'en-tête est présent et
        // que verifier() confirmera de toute façon l'état.
        $token = $requete->header('x-token', '');
        if ($token === '') {
            return false;
        }

        $charge = $requete->all();
        $donnees = ($charge['cpm_site_id'] ?? '')
            .($charge['cpm_trans_id'] ?? '')
            .($charge['cpm_trans_date'] ?? '')
            .($charge['cpm_amount'] ?? '')
            .($charge['cpm_currency'] ?? '')
            .($charge['signature'] ?? '')
            .($charge['payment_method'] ?? '')
            .($charge['cel_phone_num'] ?? '')
            .($charge['cpm_phone_prefixe'] ?? '')
            .($charge['cpm_language'] ?? '')
            .($charge['cpm_version'] ?? '')
            .($charge['cpm_payment_config'] ?? '')
            .($charge['cpm_page_action'] ?? '')
            .($charge['cpm_custom'] ?? '')
            .($charge['cpm_designation'] ?? '')
            .($charge['cpm_error_message'] ?? '');

        $attendu = hash_hmac('sha256', $donnees, $this->secretKey);

        return hash_equals($attendu, $token);
    }

    public function orderNumberDuCallback(array $charge): ?string
    {
        $valeur = $charge['cpm_trans_id'] ?? $charge['transaction_id'] ?? null;

        return $valeur !== null ? (string) $valeur : null;
    }

    private function normaliserTelephone(string $brut): string
    {
        $chiffres = preg_replace('/\D+/', '', $brut) ?? '';

        if (str_starts_with($chiffres, '0')) {
            $chiffres = '243'.substr($chiffres, 1);
        }

        return $chiffres;
    }
}
