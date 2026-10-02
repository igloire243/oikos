<?php

namespace App\Metier\Notifications;

use App\Models\Abonnement;
use App\Models\Facture;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * CE QUI ATTEND UN OPÉRATEUR, et qu'aucun geste ne déclenche.
 *
 * Une facture ne devient pas « en retard » parce que quelqu'un a cliqué : c'est le calendrier qui
 * la fait basculer. De même un abonnement s'achève un jour sans que personne ne le touche — et un
 * client dont la licence échoit sans qu'on l'ait appelé est du chiffre perdu en silence. Le push
 * est donc posé sur ces deux faits, par une tâche quotidienne, et non sur un geste.
 *
 * UNE SEULE notification par jour et par sujet, qui compte : dix factures en retard ne sonnent pas
 * dix fois. Rien n'est envoyé quand il n'y a rien à traiter. Le texte reste sobre (un nombre et un
 * lien) : une notification s'affiche sur un écran verrouillé, et les montants ne s'y lisent pas.
 */
final class Alertes
{
    /** Combien de jours avant la fin d'un abonnement on prévient. */
    public const PREAVIS_JOURS = 14;

    /** @return array{retards: int, echeances: int} Ce qui a été annoncé. */
    public static function envoyer(?Carbon $jour = null): array
    {
        $jour = ($jour ?? Carbon::today())->copy()->startOfDay();

        $retards = self::facturesEnRetard($jour);
        $echeances = self::abonnementsQuiSAchevent($jour);

        foreach (User::query()->get() as $operateur) {
            if ($retards > 0) {
                PushNotifications::envoyer(
                    $operateur,
                    'Factures en retard',
                    $retards === 1 ? '1 facture dépasse son échéance.' : "{$retards} factures dépassent leur échéance.",
                    route('console.accueil', absolute: false),
                );
            }

            if ($echeances > 0) {
                PushNotifications::envoyer(
                    $operateur,
                    'Abonnements qui s\'achèvent',
                    $echeances === 1
                        ? '1 abonnement s\'achève dans les '.self::PREAVIS_JOURS.' jours.'
                        : "{$echeances} abonnements s'achèvent dans les ".self::PREAVIS_JOURS.' jours.',
                    route('console.accueil', absolute: false),
                );
            }
        }

        return ['retards' => $retards, 'echeances' => $echeances];
    }

    public static function facturesEnRetard(Carbon $jour): int
    {
        // L'état se DÉRIVE des paiements reçus (jamais stocké) : on charge donc les candidats
        // échus et on laisse `enRetard()` trancher, plutôt que de recopier la règle en SQL.
        return Facture::query()
            ->with('paiements')
            ->whereDate('echeance_le', '<', $jour)
            ->get()
            ->filter(fn (Facture $facture) => $facture->enRetard($jour))
            ->count();
    }

    public static function abonnementsQuiSAchevent(Carbon $jour): int
    {
        $limite = $jour->copy()->addDays(self::PREAVIS_JOURS);

        return Abonnement::query()
            ->with('periodes')
            ->get()
            ->filter(function (Abonnement $abonnement) use ($jour, $limite) {
                $derniere = $abonnement->dernierePeriode();

                // Résilié : le client a décidé, ce n'est pas un oubli. Déjà échu : un autre signal.
                return ! $abonnement->estResilie()
                    && $derniere !== null
                    && $derniere->fin->betweenIncluded($jour, $limite);
            })
            ->count();
    }
}
