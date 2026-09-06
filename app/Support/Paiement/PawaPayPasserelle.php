<?php

namespace App\Support\Paiement;

use App\Models\Facture;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * PAWAPAY — agrégateur spécialisé mobile money africain (collecte + reversement, une seule API
 * multi-pays / multi-opérateurs). Agrégateur retenu par défaut pour la RDC.
 *
 * DÉROULÉ D'UN ENCAISSEMENT
 * ------------------------
 *  1. demarrer()  → POST {base}/deposits avec un depositId (UUID que NOUS générons) : PawaPay pousse
 *                   une invite sur le téléphone du client. Le depositId sert d'« orderNumber ».
 *  2. le client valide sur son téléphone.
 *  3. PawaPay POST notre webhook  → on NE crédite pas dessus : on rappelle verifier().
 *  4. verifier()  → GET {base}/deposits/{depositId} : l'état de ce bloc fait foi.
 *
 * À FINALISER AVEC UN VRAI COMPTE : les codes `correspondent` par opérateur et le mode exact de
 * signature du callback dépendent de l'espace marchand PawaPay. La structure ci-dessous est
 * conforme à l'API publique ; les constantes CORRESPONDANTS et la vérification de signature seront
 * à confirmer contre le bac à sable.
 */
class PawaPayPasserelle implements PasserellePaiement
{
    /** Nos codes opérateur (Paiement::FOURNISSEURS) → codes `correspondent` PawaPay pour la RDC. */
    private const CORRESPONDANTS = [
        'MPESA' => 'VODACOM_MPESA_COD',
        'ORANGE_MONEY' => 'ORANGE_COD',
        'AIRTEL_MONEY' => 'AIRTEL_COD',
    ];

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $jeton,
        private readonly string $secretWebhook,
        private readonly string $devise,
        private readonly ?string $callbackUrl,
        private readonly int $timeout = 15,
    ) {}

    public function estActive(): bool
    {
        return $this->jeton !== '';
    }

    public function nom(): string
    {
        return 'PawaPay';
    }

    public function demarrer(Facture $facture, string $telephone, string $operateur, int $montant): ResultatDemarrage
    {
        if (! $this->estActive()) {
            throw new RuntimeException('PawaPay est activé mais le jeton API manque.');
        }

        if (strtoupper($facture->devise) !== $this->devise) {
            return ResultatDemarrage::echec(sprintf(
                'La facture est en %s, or PawaPay encaisse en %s. On ne convertit pas à la volée.',
                $facture->devise, $this->devise,
            ));
        }

        $correspondant = self::CORRESPONDANTS[$operateur] ?? null;
        if (! $correspondant) {
            return ResultatDemarrage::echec("Opérateur « {$operateur} » non pris en charge par PawaPay.");
        }

        $depositId = (string) Str::uuid();

        try {
            $reponse = Http::withToken($this->jeton)
                ->timeout($this->timeout)
                ->acceptJson()->asJson()
                ->post(rtrim($this->baseUrl, '/').'/deposits', [
                    'depositId' => $depositId,
                    'amount' => number_format($montant / 100, 2, '.', ''),
                    'currency' => $this->devise,
                    'correspondent' => $correspondant,
                    'payer' => [
                        'type' => 'MSISDN',
                        'address' => ['value' => $this->normaliserTelephone($telephone)],
                    ],
                    'customerTimestamp' => now()->toIso8601String(),
                    'statementDescription' => Str::limit('Facture '.$facture->numero, 22, ''),
                ]);
        } catch (\Throwable $e) {
            Log::warning('PawaPay demarrer() injoignable', ['facture' => $facture->numero, 'erreur' => $e->getMessage()]);

            return ResultatDemarrage::echec('PawaPay est injoignable pour le moment. Réessayez dans un instant.');
        }

        $corps = $reponse->json() ?? [];
        $statut = strtoupper((string) ($corps['status'] ?? ''));

        // ACCEPTED / SUBMITTED = la demande est partie ; REJECTED = refus immédiat.
        if ($reponse->failed() || in_array($statut, ['REJECTED', 'FAILED'], true)) {
            return ResultatDemarrage::echec(
                $corps['rejectionReason']['rejectionMessage'] ?? $corps['message'] ?? 'PawaPay a refusé la demande.',
                $corps,
            );
        }

        return ResultatDemarrage::succes(
            $depositId,
            'Demande de paiement envoyée. Le client doit valider sur son téléphone.',
            $corps + ['depositId' => $depositId],
        );
    }

    public function verifier(string $orderNumber): ResultatVerification
    {
        if (! $this->estActive()) {
            throw new RuntimeException('PawaPay non configuré.');
        }

        try {
            $reponse = Http::withToken($this->jeton)
                ->timeout($this->timeout)
                ->acceptJson()
                ->get(rtrim($this->baseUrl, '/').'/deposits/'.urlencode($orderNumber));
        } catch (\Throwable $e) {
            Log::warning('PawaPay verifier() injoignable', ['depositId' => $orderNumber, 'erreur' => $e->getMessage()]);

            return new ResultatVerification(Paiement::EN_ATTENTE, null, 0, $this->devise, ['injoignable' => true]);
        }

        // GET /deposits/{id} renvoie un tableau à un élément.
        $corps = $reponse->json();
        $bloc = is_array($corps) ? ($corps[0] ?? $corps) : [];

        $statutPp = strtoupper((string) ($bloc['status'] ?? ''));
        $montantCentimes = (int) round(((float) ($bloc['amount'] ?? $bloc['depositedAmount'] ?? 0)) * 100);
        $devise = strtoupper((string) ($bloc['currency'] ?? $this->devise));
        $reference = $bloc['providerTransactionId'] ?? $bloc['financialTransactionId'] ?? null;

        $statut = match ($statutPp) {
            'COMPLETED' => Paiement::CONFIRME,
            'FAILED', 'REJECTED', 'CANCELLED' => Paiement::ECHOUE,
            default => Paiement::EN_ATTENTE,   // SUBMITTED / ACCEPTED / PROCESSING
        };

        return new ResultatVerification($statut, $reference ? (string) $reference : null, $montantCentimes, $devise, $bloc);
    }

    public function signatureValide(Request $requete): bool
    {
        if ($this->secretWebhook === '') {
            return true;   // pas de secret : verifier() porte seul la sécurité
        }

        // PawaPay signe le corps brut du callback (en-tête à confirmer contre l'espace marchand ;
        // « Signature » est le nom courant). HMAC-SHA256.
        $fournie = $requete->header('Signature', $requete->header('X-Pawapay-Signature', ''));
        if ($fournie === '') {
            return false;
        }

        $attendue = hash_hmac('sha256', $requete->getContent(), $this->secretWebhook);

        return hash_equals($attendue, $fournie);
    }

    public function orderNumberDuCallback(array $charge): ?string
    {
        $valeur = $charge['depositId']
            ?? ($charge['data']['depositId'] ?? null)
            ?? ($charge['deposit']['depositId'] ?? null);

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
