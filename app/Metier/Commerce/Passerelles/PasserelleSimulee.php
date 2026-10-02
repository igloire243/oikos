<?php

namespace App\Metier\Commerce\Passerelles;

use App\Models\DemandePaiement;
use Illuminate\Support\Facades\Cache;

/**
 * UN FOURNISSEUR POUR ESSAYER — il « paie » quand on clique, et ne sert qu'à voir le parcours.
 *
 * Refusé en production (`PaiementsEnLigne::passerelle()`) : un bouton qui encaisse sans argent, laissé
 * actif chez un vrai client, serait une facture qu'il peut solder en un clic. Son verdict vit dans le
 * cache, et non dans l'URL de retour, pour que `verifier()` se comporte comme un vrai fournisseur :
 * il répond à qui l'interroge, pas à qui l'invoque.
 */
class PasserelleSimulee implements Passerelle
{
    public function initier(DemandePaiement $demande): string
    {
        return route('paiement.simulation', $demande->reference);
    }

    public function verifier(DemandePaiement $demande): ?array
    {
        $verdict = Cache::get(self::cle($demande->reference));
        $montant = Cache::get(self::cle($demande->reference).':montant', $demande->montant_centimes);

        if ($verdict === null) {
            return ['statut' => 'EN_ATTENTE', 'montant_centimes' => $demande->montant_centimes, 'devise' => $demande->devise, 'reference_externe' => null];
        }

        return [
            'statut' => $verdict,
            'montant_centimes' => (int) $montant,
            'devise' => $demande->devise,
            'reference_externe' => 'SIM-'.$demande->reference,
        ];
    }

    /** `$montantCentimes` : pour essayer le cas où le fournisseur confirme une autre somme que celle demandée. */
    public static function decider(string $reference, bool $paye, ?int $montantCentimes = null): void
    {
        Cache::put(self::cle($reference), $paye ? 'PAYE' : 'ECHEC', now()->addHour());
        if ($montantCentimes !== null) {
            Cache::put(self::cle($reference).':montant', $montantCentimes, now()->addHour());
        }
    }

    private static function cle(string $reference): string
    {
        return 'paiement-simule:'.$reference;
    }
}
