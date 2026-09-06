<?php

namespace App\Support;

use App\Models\Installation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PRÉVENIR UNE INSTALLATION QU'ELLE DOIT SE METTRE À JOUR.
 *
 * Appelé après la confirmation d'un paiement ou un changement d'abonnement, pour que l'accès
 * rouvre dans la minute plutôt qu'à la synchronisation de la nuit. Un client qui vient de payer et
 * qu'on fait attendre au lendemain rappelle — et il a raison.
 *
 * CE QU'ON ENVOIE : rien. Un jeton d'authentification, et c'est tout. L'installation, prévenue,
 * appelle ELLE-MÊME sa console pour relire son état. On ne lui dicte pas son abonnement ; on lui
 * dit d'aller le chercher. La différence est ce qui empêche qu'une console compromise puisse
 * ouvrir des modules chez mille clients.
 *
 * L'ÉCHEC N'EST JAMAIS BLOQUANT. Une installation injoignable — hébergeur en panne, adresse
 * changée, serveur derrière un pare-feu — ne doit pas faire échouer l'enregistrement d'un
 * paiement. L'argent est encaissé, la facture est soldée : le reste se rattrapera cette nuit.
 * Faire dépendre une écriture comptable de la disponibilité d'un tiers serait une faute.
 */
class RappelInstallation
{
    private const DELAI = 6;

    /**
     * @return array{ok: bool, message: string}
     */
    public static function prevenir(Installation $installation): array
    {
        if (! $installation->url) {
            return ['ok' => false, 'message' => "L'adresse de cette installation n'est pas connue."];
        }

        if (! $installation->rappel_jeton) {
            return ['ok' => false, 'message' => "Cette installation n'a pas encore transmis son secret de rappel (activez-la, ou attendez sa prochaine synchronisation)."];
        }

        try {
            $reponse = Http::timeout(self::DELAI)
                ->acceptJson()
                ->withToken($installation->rappel_jeton)
                ->post(rtrim($installation->url, '/').'/oikos/rafraichir');
        } catch (Throwable $e) {
            Log::info('Rappel installation : injoignable', [
                'installation' => $installation->installation_id,
                'erreur' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => "L'installation est injoignable. Elle se mettra à jour cette nuit."];
        }

        if ($reponse->successful()) {
            $installation->forceFill(['rappel_le' => now()])->save();

            return ['ok' => true, 'message' => "L'installation a été mise à jour immédiatement."];
        }

        if ($reponse->status() === 401) {
            // Le secret ne correspond plus : l'installation a été réinstallée, ou son fichier de
            // rappel a été perdu. Elle en transmettra un neuf à sa prochaine synchronisation.
            return ['ok' => false, 'message' => 'Le secret de rappel a changé côté installation. Elle se resynchronisera cette nuit.'];
        }

        return ['ok' => false, 'message' => "L'installation a répondu ".$reponse->status().'. Elle se mettra à jour cette nuit.'];
    }
}
