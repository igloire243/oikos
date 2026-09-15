<?php

namespace App\Support;

use App\Models\Abonnement;
use App\Models\Entite;
use App\Models\Installation;
use App\Models\Plan;

/**
 * LES RÈGLES DE VENTE — ce qu'on a le droit de vendre, à qui, et dans quel ordre.
 *
 * POURQUOI ELLES VIVENT ICI ET NON DANS LE CONTRÔLEUR
 * -----------------------------------------------------
 * La même question se pose à trois moments : quand on remplit la liste des offres proposables,
 * quand on enregistre la vente, et quand on renouvelle. Trois copies de la règle finiraient par
 * diverger — et la divergence se verrait sous la forme d'un abonnement vendu que le produit
 * n'ouvrira jamais, c'est-à-dire d'un client qui a payé pour rien.
 *
 * LA RÈGLE DE FOND, EN UNE PHRASE : une entité n'écrit que si la LICENCE de sa structure est
 * active et si son propre ACCÈS l'est. Vendre un accès sous une licence absente ou expirée revient
 * à encaisser pour une porte qui restera fermée.
 */
class Cascade
{
    /**
     * L'abonnement de LICENCE de cette installation, s'il existe.
     *
     * C'est celui dont le bénéficiaire est la Vision et dont l'offre est de nature LICENCE ou
     * COMBINEE. Une église seule n'a qu'une offre combinée : elle joue le rôle de licence.
     */
    public static function licence(Installation $installation): ?Abonnement
    {
        return $installation->abonnements()
            ->with('plan')
            ->get()
            ->first(fn (Abonnement $a) => in_array(
                $a->plan?->nature, [Plan::LICENCE, Plan::COMBINEE], true
            ));
    }

    /**
     * Pourquoi cette offre ne peut PAS être vendue à cette entité — ou null si elle le peut.
     *
     * On rend un MESSAGE et non un booléen : « refusé » n'apprend rien à qui est en train de
     * vendre, et l'oblige à deviner ce qu'il faut corriger.
     */
    public static function empechement(Installation $installation, Entite $entite, Plan $plan): ?string
    {
        $estSommet = $entite->type === Entite::TYPE_VISION;

        // ---- 1. La licence se vend au sommet, et nulle part ailleurs ------------------------
        if (in_array($plan->nature, [Plan::LICENCE, Plan::COMBINEE], true) && ! $estSommet) {
            return "Une licence se vend à la Vision, pas à « {$entite->nom} ». "
                .'Vendez-lui plutôt un accès.';
        }

        // ---- 2. Le sommet ne paie pas d'accès : sa licence l'inclut --------------------------
        if ($plan->nature === Plan::ACCES && $estSommet) {
            return "La Vision n'achète pas d'accès : sa licence inclut déjà son espace. "
                .'Choisissez une offre de licence.';
        }

        // ---- 3. Un accès suppose une licence vivante au-dessus -------------------------------
        if ($plan->nature === Plan::ACCES) {
            $licence = self::licence($installation);

            if (! $licence) {
                return "Cette installation n'a pas encore de licence. Vendez d'abord la licence à "
                    ."la Vision : sans elle, l'accès de « {$entite->nom} » n'ouvrirait rien.";
            }

            if (! $licence->ouvreLEcriture()) {
                return 'La licence de cette installation est '
                    .mb_strtolower(Abonnement::STATUTS[$licence->statut] ?? $licence->statut)
                    .'. Régularisez-la avant de vendre un accès en dessous — il resterait fermé.';
            }

            // ---- 4. Le palier de la licence plafonne ce qu'on peut vendre en dessous ---------
            if ($licence->plan && ! $licence->plan->autorise($plan->palier)) {
                return 'La licence « '.$licence->plan->nom.' » n\'autorise les accès que jusqu\'au '
                    .'palier '.(Plan::PALIERS[$licence->plan->plafond_acces] ?? $licence->plan->plafond_acces)
                    .'. Faites évoluer la licence pour vendre un '.(Plan::PALIERS[$plan->palier] ?? $plan->palier).'.';
            }
        }

        return null;
    }

    /**
     * L'abonnement déjà en place pour cette entité, s'il y en a un.
     *
     * La base impose l'unicité (installation + bénéficiaire) : on ne vend pas deux fois, on
     * RENOUVELLE. Sans cette vérification, l'insertion échouerait sur une erreur SQL que personne
     * ne saurait relier à « cette église a déjà un abonnement ».
     */
    public static function abonnementExistant(Installation $installation, Entite $entite): ?Abonnement
    {
        return $installation->abonnements()
            ->where('beneficiaire_type', $entite->type)
            ->where('beneficiaire_ref', $entite->ref)
            ->first();
    }

    /**
     * Le prix à facturer pour cette offre sur cette installation, en centimes de dollar.
     *
     * Pour une licence, il dépend de la TAILLE du réseau — comptée sur les entités réellement
     * déclarées par l'installation, hors Vision. C'est un nombre que la console connaît, donc
     * vérifiable, et non un chiffre que le client s'attribue.
     *
     * @return array{usd_cents: int, cdf: int, entites: int}
     */
    public static function prix(Installation $installation, Plan $plan): array
    {
        if (! $plan->suitLaTaille()) {
            return [
                'usd_cents' => (int) $plan->prix_usd_cents,
                'cdf' => (int) $plan->prix_cdf,
                'entites' => 0,
            ];
        }

        $entites = $installation->entites()
            ->where('type', '!=', Entite::TYPE_VISION)
            ->count();

        $prix = $plan->prixPourTaille($entites);

        return $prix + ['entites' => $entites];
    }
}
