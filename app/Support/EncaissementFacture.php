<?php

namespace App\Support;

use App\Models\Abonnement;
use App\Models\EntreeJournal;
use App\Models\Facture;
use Illuminate\Support\Facades\DB;

/**
 * CE QUI SUIT LA CONFIRMATION D'UN VERSEMENT — extrait de FactureController pour être partagé avec
 * l'encaissement en ligne (webhook FlexPay). Les deux voies doivent solder une facture EXACTEMENT
 * de la même façon, sinon un paiement mobile money et un paiement saisi à la main laisseraient la
 * base dans deux états différents.
 *
 * L'ORDRE DES ÉCRITURES N'EST PAS INDIFFÉRENT (voir FactureController) :
 *   1. l'argent : la facture passe PAYÉE, dans une transaction ;
 *   2. l'accès : l'abonnement repasse ACTIF, dans la même transaction ;
 *   3. le rappel de l'installation : EN DERNIER et HORS transaction — un client injoignable ne doit
 *      jamais faire échouer l'enregistrement d'un paiement.
 */
class EncaissementFacture
{
    /**
     * @return array{soldee: bool, message: string}
     */
    public static function traiter(Facture $facture): array
    {
        $facture->refresh();

        if ($facture->resteADevoir() > 0) {
            return [
                'soldee' => false,
                'message' => sprintf(
                    'Versement confirmé. Il reste %s $ à recevoir sur la facture %s.',
                    number_format($facture->resteADevoir() / 100, 2, ',', ' '),
                    $facture->numero,
                ),
            ];
        }

        $abonnement = $facture->abonnement;

        DB::transaction(function () use ($facture, $abonnement) {
            $facture->update(['statut' => Facture::PAYEE, 'payee_le' => now()]);

            // On n'active QUE ce qui attendait un paiement : un abonnement résilié ou bloqué pour
            // une autre raison ne se rouvre pas parce qu'une vieille facture est soldée.
            if ($abonnement && in_array($abonnement->statut, [Abonnement::IMPAYE, Abonnement::SUSPENDU, Abonnement::ESSAI], true)) {
                $abonnement->update(['statut' => Abonnement::ACTIF]);
            }
        });

        EntreeJournal::noter('FACTURE_SOLDEE', $facture, ['numero' => $facture->numero]);

        $message = "Facture {$facture->numero} soldée.";

        if ($abonnement?->installation) {
            $rappel = RappelInstallation::prevenir($abonnement->installation);
            $message .= ' '.$rappel['message'];
        }

        return ['soldee' => true, 'message' => $message];
    }
}
