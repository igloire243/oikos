<?php

namespace App\Metier\Licence;

use App\Metier\Clients\IdentiteRecue;
use App\Metier\Journal\Journal;
use App\Models\Entite;
use App\Models\Installation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * LA SYNCHRONISATION — l'installation pousse ce qu'elle est, et lit ce à quoi elle a droit.
 *
 * CE QUI MONTE : l'arbre des entités (sans lui on ignore à qui vendre), des compteurs (qui
 * justifient la taille du réseau), une carte de visite, l'empreinte de son catalogue. Rien
 * d'autre : ni membre, ni finance, ni contenu. Cette route ne doit jamais devenir un canal par
 * lequel les données d'une église remonteraient chez son fournisseur.
 *
 * CE QUI DESCEND : un état signé, jamais un ordre.
 *
 * ON NE SUPPRIME JAMAIS UNE ENTITÉ ABSENTE DU LOT : l'envoi peut être partiel ou tronqué, et
 * supprimer effacerait un abonnement facturé sur la foi d'une requête incomplète. Une entité
 * réellement disparue se lit à sa date de dernière vue.
 */
class Synchronisation
{
    /**
     * @param  array<string, mixed>  $donnees  validées par le contrôleur
     * @return array<string, mixed> l'état de licence
     */
    public static function recevoir(Installation $installation, array $donnees): array
    {
        DB::transaction(function () use ($installation, $donnees) {
            $remplis = IdentiteRecue::appliquer($installation, $donnees['identite'] ?? null);

            // Une entrée SEULEMENT quand quelque chose a été rempli : une nuit qui ne change rien
            // — le cas normal — ne doit pas noircir le journal. Un journal qu'on ne lit plus ne
            // sert à rien le jour où il faudrait le lire.
            if ($remplis !== []) {
                Journal::tracer('FICHE_COMPLETEE', $installation, 'Fiche complétée par « '.$installation->libelle().' »', ['champs' => $remplis]);
            }

            $maintenant = Carbon::now();

            foreach ($donnees['entites'] ?? [] as $entite) {
                Entite::query()->updateOrCreate(
                    ['installation_id' => $installation->id, 'type' => $entite['type'], 'ref' => $entite['ref']],
                    [
                        'nom' => $entite['nom'],
                        'sous_type' => $entite['sous_type'] ?? null,
                        'parent_ref' => $entite['parent_ref'] ?? null,
                        'effectif' => $entite['effectif'] ?? null,
                        'vue_le' => $maintenant,
                    ],
                );
            }

            $installation->forceFill([
                'vue_le' => $maintenant,
                'version' => $donnees['version'] ?? $installation->version,
                'url' => $donnees['url'] ?? $installation->url,
                // Renvoyé à chaque appel : si l'installation a régénéré son secret de rappel ou
                // changé d'adresse, on repart du bon sans la réactiver.
                'rappel_jeton' => $donnees['rappel'] ?? $installation->rappel_jeton,
                'catalogue_empreinte' => $donnees['catalogue'] ?? $installation->catalogue_empreinte,
                'compteurs' => $donnees['compteurs'] ?? $installation->compteurs,
            ])->save();
        });

        return EtatLicence::pour($installation->refresh());
    }

    /** L'installation qui présente cette clé de synchronisation — active seulement. */
    public static function installationPourCle(?string $cle): ?Installation
    {
        if ($cle === null || $cle === '') {
            return null;
        }

        return Installation::query()->actives()->where('cle_synchro_hash', hash('sha256', $cle))->first();
    }
}
