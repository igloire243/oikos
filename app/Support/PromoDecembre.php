<?php

namespace App\Support;

use App\Models\Abonnement;
use App\Models\EntreeJournal;
use App\Models\Plan;

/**
 * LA PROMOTION « MOIS DES FÊTES » — décembre offert sur tous les accès.
 *
 * POURQUOI CETTE CLASSE EXISTE
 * -----------------------------
 * La promo n'avait qu'une porte : la commande `abonnement:offrir-decembre`, planifiée au 1ᵉʳ
 * décembre. Or `Schedule::command()` ne fait rien tout seul — il suppose un `schedule:run` en cron
 * sur le serveur. Sans ce cron, la promo ne partait jamais, et personne ne s'en apercevait avant
 * janvier : le client n'était pas suspendu (rien ne l'avait crédité), mais rien ne signalait
 * l'oubli non plus.
 *
 * Il fallait donc un bouton dans la console. Recopier la logique dans un contrôleur en aurait fait
 * deux versions, qui auraient divergé à la première retouche — et la divergence se serait vue sous
 * la forme d'un client crédité deux fois, ou pas du tout. La règle vit ici ; la commande et le
 * bouton l'appellent tous les deux.
 *
 * CE QU'ELLE FAIT : repousse d'un mois `periode_fin` et `grace_fin` de tout abonnement d'ACCÈS
 * (église, cellule, antenne) qui laisse encore écrire. La licence annuelle n'est pas concernée.
 * Aucune facture n'est émise : c'est un cadeau, pas une ligne à zéro.
 *
 * ELLE EST IDEMPOTENTE. `abonnements.promo_decembre_annee` retient l'année déjà offerte : rejouer la
 * commande, ou cliquer deux fois sur le bouton, ne crédite personne une seconde fois. C'est ce qui
 * rend le bouton sans danger — et c'est exactement pourquoi il ne demande pas de confirmation
 * élaborée.
 *
 * ANCIENNETÉ : AUCUNE, ET C'EST VOULU (décision du client). Un accès souscrit le 25 novembre reçoit
 * son mois gratuit six jours plus tard, comme les autres. « Décembre est offert à tout le monde »
 * est une phrase qu'on peut dire au téléphone ; « décembre est offert si vous êtes client depuis
 * trois mois » ne s'explique pas et se discute à chaque vente.
 */
class PromoDecembre
{
    /**
     * Crédite tous les accès éligibles et rend le compte rendu.
     *
     * @return array{annee: int, offerts: int, deja: int}
     *         `deja` = les accès qui portaient déjà cette année-là. On le rend séparément pour que
     *         l'écran puisse dire « rien à faire, c'est déjà fait » au lieu de « 0 accès crédité »,
     *         qui se lit comme un échec.
     */
    public static function offrir(?int $annee = null): array
    {
        $annee = $annee ?: (int) now()->year;

        $accesOuvrants = Abonnement::with('plan')
            ->whereIn('statut', Abonnement::OUVRENT_ECRITURE)
            ->get()
            ->filter(fn (Abonnement $a) => $a->plan?->nature === Plan::ACCES);

        $deja = $accesOuvrants
            ->filter(fn (Abonnement $a) => (int) $a->promo_decembre_annee >= $annee)
            ->count();

        $aCrediter = $accesOuvrants
            ->filter(fn (Abonnement $a) => (int) $a->promo_decembre_annee < $annee);

        foreach ($aCrediter as $abonnement) {
            $finAvant = $abonnement->periode_fin?->copy();

            // addMonthNoOverflow : un accès qui finit le 31 janvier doit aller au 28 ou 29 février,
            // pas déborder au 2 ou 3 mars. addMonth() ferait ce débordement, et le client y gagnerait
            // deux jours chaque année bissextile — silencieusement.
            $abonnement->update([
                'periode_fin' => $abonnement->periode_fin
                    ? $abonnement->periode_fin->copy()->addMonthNoOverflow()
                    : now()->addMonthNoOverflow(),
                'grace_fin' => $abonnement->grace_fin
                    ? $abonnement->grace_fin->copy()->addMonthNoOverflow()
                    : null,
                'promo_decembre_annee' => $annee,
            ]);

            EntreeJournal::noter('PROMO_DECEMBRE_OFFERTE', $abonnement->installation, [
                'annee' => $annee,
                'offre' => $abonnement->plan?->nom,
                'beneficiaire' => $abonnement->beneficiaire_type.' #'.$abonnement->beneficiaire_ref,
                'periode_fin_avant' => $finAvant?->toDateString(),
                'periode_fin_apres' => $abonnement->periode_fin?->toDateString(),
            ]);
        }

        return [
            'annee' => $annee,
            'offerts' => $aCrediter->count(),
            'deja' => $deja,
        ];
    }

    /**
     * De quoi renseigner le bouton AVANT de cliquer : combien d'accès attendent leur mois.
     *
     * Un bouton qui ne dit pas ce qu'il va faire se clique à l'aveugle, et sur une action qui touche
     * tout le parc d'un coup, ça n'est pas acceptable.
     *
     * @return array{annee: int, eligibles: int, deja: int}
     */
    public static function etat(?int $annee = null): array
    {
        $annee = $annee ?: (int) now()->year;

        $acces = Abonnement::with('plan')
            ->whereIn('statut', Abonnement::OUVRENT_ECRITURE)
            ->get()
            ->filter(fn (Abonnement $a) => $a->plan?->nature === Plan::ACCES);

        return [
            'annee' => $annee,
            'eligibles' => $acces->filter(fn (Abonnement $a) => (int) $a->promo_decembre_annee < $annee)->count(),
            'deja' => $acces->filter(fn (Abonnement $a) => (int) $a->promo_decembre_annee >= $annee)->count(),
        ];
    }
}
