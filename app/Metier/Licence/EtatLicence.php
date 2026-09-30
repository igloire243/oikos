<?php

namespace App\Metier\Licence;

use App\Metier\Catalogue\Modules;
use App\Models\Installation;
use Illuminate\Support\Carbon;

/**
 * CE QUE LA CONSOLE RÉPOND À UNE INSTALLATION QUI DEMANDE « de quoi ai-je le droit ? ».
 *
 * Le SEUL endroit où cette réponse se construit : l'activation et la synchronisation renvoient
 * exactement le même objet. Deux constructions finiraient par dire deux choses, et le client
 * verrait ses modules changer selon qu'il vient d'activer ou qu'il a attendu la nuit.
 *
 * ============================================================================================
 * L'ESSAI A UNE FIN FIXE — un défaut de l'ancienne console, corrigé ici
 * ============================================================================================
 * L'ancienne calculait la fin de l'essai comme « maintenant + 30 jours » à CHAQUE réponse. Une
 * installation qui n'achetait rien voyait donc son essai repoussé d'un jour à chaque nuit de
 * synchronisation : un essai éternel, sans que personne l'ait décidé. Ici la fin part de la
 * PREMIÈRE activation (`activee_le`), et elle ne bouge plus.
 *
 * Au Lot C1 il n'y a encore rien à vendre : toute installation est en ESSAI, tout ouvert. Le Lot
 * C2 y ajoute les abonnements, le détail PAR ENTITÉ et la cascade.
 */
class EtatLicence
{
    public const ESSAI = 'ESSAI';

    /** @return array<string, mixed> */
    public static function pour(Installation $installation): array
    {
        $debut = $installation->activee_le ?? Carbon::now();

        $etat = [
            'emis_le' => Carbon::now()->toIso8601String(),
            'console' => (string) config('app.url'),
            'communaute' => $installation->client?->nom,
            'installation' => $installation->libelle(),

            // L'EMPREINTE DE LA MACHINE DESTINATAIRE, dans le message signé : le produit refuse
            // une licence qui ne porte pas la sienne. Copier le fichier d'un client bien abonné sur
            // un autre serveur est l'attaque la plus simple qui soit — elle est fermée ici.
            'empreinte' => $installation->empreinte,

            'statut' => self::ESSAI,
            'offre' => null,
            'fin' => $debut->copy()->addDays((int) config('oikos.essai_jours', 30))->toIso8601String(),
            'grace_jours' => (int) config('oikos.grace_jours', 14),
            'silence_jours' => (int) config('oikos.silence_jours', 45),

            // L'empreinte du catalogue au nom duquel on répond : le produit compare avec la sienne
            // et peut dire qu'une mise à jour manque, d'un côté ou de l'autre.
            'catalogue' => Modules::empreinte(),

            // null = tout ouvert. C'est l'essai : on laisse voir le produit entier.
            'modules' => null,

            // Le détail par entité (« EXTENSION:44 » → offre, fin, modules) : vide tant que rien
            // n'est vendu. C'est lui que la barrière `module:` du produit lira (Lot P1).
            'entites' => (object) [],
        ];

        // LA SIGNATURE EN DERNIER, sur tout ce qui précède : un champ ajouté après coup ne serait
        // pas couvert, et la signature rassurerait sans rien protéger.
        $etat['signature'] = Signature::signer($etat);

        return $etat;
    }
}
