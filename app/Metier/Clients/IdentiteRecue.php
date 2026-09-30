<?php

namespace App\Metier\Clients;

use App\Models\Installation;

/**
 * CE QU'UNE INSTALLATION DIT D'ELLE-MÊME — et ce qu'on accepte d'en faire.
 *
 * LE PROBLÈME QUE CELA RÉSOUT
 * -----------------------------
 * Créer un client puis son installation demandait de retaper le nom de l'église, la ville, le nom
 * du responsable, son adresse électronique et son téléphone. L'installation connaît tout cela
 * exactement ; l'humain qui recopie, non. Depuis, elle l'envoie — à l'activation, puis à chaque
 * synchronisation.
 *
 * LA RÈGLE QUI REND CET ENVOI SANS DANGER : ON NE REMPLIT QUE LE VIDE
 * --------------------------------------------------------------------
 * Une case déjà renseignée n'est JAMAIS écrasée. Ni par la première activation, ni par la
 * cent-cinquantième synchronisation. Trois raisons, et chacune suffirait :
 *
 *   · VOTRE CORRECTION DOIT GAGNER. Si vous avez rectifié « Bethel » en « Béthel », ou saisi le
 *     numéro du trésorier parce que c'est lui qui paie, une tâche de nuit ne doit pas défaire
 *     votre travail pendant que vous dormez.
 *
 *   · LA FICHE CLIENT EST UN DOCUMENT COMMERCIAL. Le nom qui y figure est celui qui apparaîtra sur
 *     les factures. Il ne peut pas dépendre de ce que quelqu'un a tapé ce matin dans les réglages
 *     de son église.
 *
 *   · C'EST UNE SURFACE EXPOSÉE. Ces champs arrivent d'Internet. Les laisser écraser une fiche
 *     ferait de la synchronisation un moyen de modifier votre base — exactement ce que le reste de
 *     cette API s'interdit.
 *
 * Ce qui est reçu et refusé n'est pas perdu pour autant : le journal garde la trace de ce qui a été
 * rempli. Une divergence durable entre ce que vous avez saisi et ce que l'installation déclare se
 * lit sur sa fiche, et se tranche à la main — c'est une décision commerciale, pas une écriture
 * automatique.
 */
class IdentiteRecue
{
    /**
     * Les règles de validation à fusionner dans celles du contrôleur appelant.
     *
     * Bornées et typées : ces valeurs viennent d'Internet et finiront affichées dans la console et
     * imprimées sur des factures.
     */
    public const REGLES = [
        'identite' => ['nullable', 'array'],
        'identite.communaute' => ['nullable', 'string', 'max:190'],
        'identite.pays' => ['nullable', 'string', 'max:120'],
        'identite.ville' => ['nullable', 'string', 'max:120'],
        'identite.responsable' => ['nullable', 'string', 'max:190'],
        'identite.email' => ['nullable', 'email', 'max:190'],
        'identite.telephone' => ['nullable', 'string', 'max:40'],
    ];

    /**
     * Le champ de la fiche client, et la clé qui le remplit.
     *
     * Le nom de la communauté devient le nom du client : dans ce métier, ce sont la même chose —
     * on vend à une église, pas à une société qui possède une église.
     */
    private const CORRESPONDANCES = [
        'nom' => 'communaute',
        'ville' => 'ville',
        'pays' => 'pays',
        'contact_nom' => 'responsable',
        'contact_email' => 'email',
        'contact_telephone' => 'telephone',
    ];

    /**
     * Remplit ce qui est vide, sur le client ET sur l'installation.
     *
     * @param  array<string, mixed>|null  $identite
     * @return list<string> les champs effectivement remplis — pour le journal
     */
    public static function appliquer(Installation $installation, ?array $identite): array
    {
        if (! is_array($identite) || $identite === []) {
            return [];
        }

        $remplis = [];

        $client = $installation->client;

        if ($client) {
            foreach (self::CORRESPONDANCES as $colonne => $cle) {
                $valeur = self::propre($identite[$cle] ?? null);

                if ($valeur === '') {
                    continue;
                }

                // LA RÈGLE. Une case tenue par un humain ne se laisse pas reprendre.
                if (self::propre($client->{$colonne}) !== '') {
                    continue;
                }

                $client->{$colonne} = $valeur;
                $remplis[] = $colonne;
            }

            if ($remplis !== []) {
                $client->save();
            }
        }

        // Le nom de l'installation. Il peut rester vide à la création justement pour être rempli
        // ici : « Église Béthel » vaut mieux que « Installation 3 », et personne ne l'a tapé.
        $communaute = self::propre($identite['communaute'] ?? null);

        if ($communaute !== '' && self::propre($installation->nom) === '') {
            $installation->nom = $communaute;
            $remplis[] = 'installation.nom';
        }

        return $remplis;
    }

    private static function propre(mixed $valeur): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $valeur));
    }
}
