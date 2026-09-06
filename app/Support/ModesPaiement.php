<?php

namespace App\Support;

use App\Models\Paiement;
use App\Models\Plan;
use App\Models\Reglage;

/**
 * LE CROISEMENT DES SOURCES qui décident qu'un mode de paiement est proposable.
 *
 *   1. Paiement::FOURNISSEURS   — ce qui existe, et son libellé. Ne bouge que si un nouvel
 *                                 opérateur apparaît en RDC.
 *   2. Les réglages en base     — ce que VOUS acceptez aujourd'hui, et sous quel numéro. Modifiable
 *                                 depuis la console, sans toucher au code.
 *   3. config/paiement.php      — les instructions de chaque mode, et le repli si la base est
 *                                 encore vide.
 *   4. Plan::$modes_paiement    — ce que l'offre acceptée par ce client-là autorise.
 *
 * POURQUOI CENTRALISER CE CALCUL
 * -------------------------------
 * Quatre sources, c'est quatre occasions de se contredire. Refait à la main dans une vue, l'un des
 * filtres saute tôt ou tard, et on affiche un numéro qu'on n'utilise plus — un client enverra de
 * l'argent dessus, et il faudra le lui rendre.
 *
 * LA CONDITION LA MOINS ÉVIDENTE : un mode ACTIF mais SANS COORDONNÉES n'est pas proposable. Il est
 * retiré plutôt qu'affiché vide, parce qu'« Orange Money : — » n'est pas un moyen de paiement,
 * c'est une question posée au client.
 */
class ModesPaiement
{
    /**
     * Les modes réellement proposables, prêts à afficher.
     *
     * @return array<string, array{code:string, libelle:string, numero:?string, banque:?string, icone:string, instructions:string, manuel:bool}>
     */
    public static function proposables(?Plan $plan = null): array
    {
        $resultat = [];

        foreach (Paiement::FOURNISSEURS as $code => $libelle) {
            $reglageConfig = config('paiement.modes.'.$code, []);

            $actif = Reglage::booleen(
                'mode_'.$code.'_actif',
                (bool) ($reglageConfig['actif'] ?? false),
            );

            if (! $actif) {
                continue;
            }

            $numero = trim((string) Reglage::valeur('mode_'.$code.'_numero', $reglageConfig['numero'] ?? ''));

            // Les espèces sont le seul mode légitimement sans coordonnées : on ne « verse » pas
            // sur un numéro, on se déplace. Partout ailleurs, l'absence vaut désactivation.
            if ($code !== 'ESPECES' && $numero === '') {
                continue;
            }

            if ($plan && ! $plan->accepte($code)) {
                continue;
            }

            $resultat[$code] = [
                'code' => $code,
                'libelle' => $libelle,
                'numero' => $numero !== '' ? $numero : null,
                'banque' => $code === 'VIREMENT'
                    ? (trim((string) Reglage::valeur('mode_VIREMENT_banque', $reglageConfig['banque'] ?? '')) ?: null)
                    : null,
                'icone' => $reglageConfig['icone'] ?? 'wallet',
                'instructions' => $reglageConfig['instructions'] ?? '',
                'manuel' => in_array($code, Paiement::CONFIRMATION_MANUELLE, true),
            ];
        }

        return $resultat;
    }

    /** Y a-t-il seulement quelque chose à montrer ? La page « Comment payer » en dépend. */
    public static function auMoinsUn(): bool
    {
        return self::proposables() !== [];
    }

    /** Le taux du jour, tel que la console l'a enregistré. */
    public static function tauxCdf(): int
    {
        return max(1, Reglage::entier('taux_cdf', 2800, 'paiement.taux_indicatif_cdf'));
    }
}
