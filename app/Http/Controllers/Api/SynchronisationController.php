<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entite;
use App\Models\EntreeJournal;
use App\Models\Installation;
use App\Support\EtatLicence;
use App\Support\IdentiteRecue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * LA SYNCHRONISATION NOCTURNE — l'installation pousse ce qu'elle est, et lit ce qu'elle a droit.
 *
 * L'AUTHENTIFICATION EST LA CLÉ DE L'INSTALLATION, présentée en Bearer. Pas de session, pas de
 * cookie : c'est un serveur qui parle à un serveur.
 *
 * CE QUI MONTE, ET POURQUOI
 * --------------------------
 * L'arbre des entités — antennes et églises — parce que sans lui vous ignorez à qui vendre. Et des
 * compteurs (membres, comptes) parce qu'ils justifient le palier de taille de la licence. Rien
 * d'autre : ni membres, ni finances, ni contenu. Cette route ne doit jamais devenir un canal par
 * lequel les données d'une église remonteraient chez son fournisseur.
 *
 * CE QUI NE DESCEND PAS : rien de modifiable. La réponse est un état, pas un ordre. Une console
 * compromise ne doit pas pouvoir dire à mille installations d'exécuter quoi que ce soit.
 *
 * ON NE SUPPRIME JAMAIS UNE ENTITÉ DISPARUE DU LOT
 * --------------------------------------------------
 * Une église absente d'un envoi peut avoir été supprimée chez le client — ou l'envoi peut être
 * partiel, tronqué, ou avoir échoué à mi-course. Supprimer effacerait un abonnement facturé sur la
 * foi d'une requête incomplète. On met à jour ce qui arrive, on laisse le reste : une entité
 * réellement disparue se voit à sa date de dernière vue, et se retire à la main.
 */
class SynchronisationController extends Controller
{
    public function synchroniser(Request $request): JsonResponse
    {
        $installation = Installation::parCle($request->bearerToken());

        if (! $installation) {
            return response()->json([
                'message' => 'Clé de synchronisation inconnue ou installation désactivée.',
            ], 401);
        }

        $donnees = $request->validate([
            'version' => ['nullable', 'string', 'max:20'],
            'url' => ['nullable', 'url', 'max:255'],

            // Renvoyée à chaque nuit : c'est ainsi que les installations activées AVANT la mise en
            // place de la signature finissent par déclarer leur empreinte, sans qu'on ait à les
            // réactiver une à une. Elle n'est pas un secret — elle identifie, elle n'authentifie
            // pas ; c'est la clé de synchronisation, en Bearer, qui a déjà fait ce travail.
            'empreinte' => ['nullable', 'string', 'min:16', 'max:128', 'regex:/^[A-Za-z0-9_\-]+$/'],

            // Renvoyé à chaque appel : si l'installation a changé d'adresse ou régénéré son
            // fichier, on repart du bon secret sans qu'il faille la réactiver à la main.
            'rappel' => ['nullable', 'string', 'min:16', 'max:128', 'regex:/^[A-Za-z0-9_\-]+$/'],

            'compteurs' => ['nullable', 'array'],
            'compteurs.*' => ['nullable', 'integer', 'min:0', 'max:100000000'],

            // Deux mille entités : au-delà, ce n'est plus un réseau d'églises, c'est une erreur ou
            // un envoi malveillant. Une borne explicite vaut mieux qu'un serveur qui rame.
            'entites' => ['nullable', 'array', 'max:2000'],
            'entites.*.type' => ['required', 'in:VISION,ANTENNE,EXTENSION'],
            'entites.*.ref' => ['required', 'integer', 'min:1'],
            'entites.*.nom' => ['required', 'string', 'max:190'],
            'entites.*.sous_type' => ['nullable', 'string', 'max:60'],
            'entites.*.parent_ref' => ['nullable', 'integer', 'min:1'],

            // La carte de visite, renvoyée à chaque nuit. Elle ne remplit que les cases VIDES de la
            // fiche client : une synchronisation ne peut pas réécrire ce que vous avez saisi. C'est
            // ce qui permet de l'accepter sur une route ouverte. Voir App\Support\IdentiteRecue.
            ...IdentiteRecue::REGLES,
        ]);

        $remplis = IdentiteRecue::appliquer($installation, $donnees['identite'] ?? null);

        // Une entrée de journal SEULEMENT quand quelque chose a été rempli. Une synchronisation
        // nocturne qui ne change rien — le cas normal — ne doit pas noircir le journal chaque nuit :
        // un journal qu'on ne lit plus ne sert à rien le jour où il faudrait le lire.
        if ($remplis !== []) {
            EntreeJournal::noter('FICHE_COMPLETEE', $installation, ['champs' => $remplis]);
        }

        foreach (($donnees['entites'] ?? []) as $entite) {
            Entite::updateOrCreate(
                [
                    'installation_id' => $installation->installation_id,
                    'type' => $entite['type'],
                    'ref' => $entite['ref'],
                ],
                [
                    'nom' => $entite['nom'],
                    'sous_type' => $entite['sous_type'] ?? null,
                    'parent_ref' => $entite['parent_ref'] ?? null,
                    'vue_le' => now(),
                ]
            );
        }

        $installation->forceFill([
            'vue_le' => now(),
            'version' => $donnees['version'] ?? $installation->version,
            'url' => $donnees['url'] ?? $installation->url,
            'empreinte' => $donnees['empreinte'] ?? $installation->empreinte,
            'rappel_jeton' => $donnees['rappel'] ?? $installation->rappel_jeton,
            'compteurs' => $donnees['compteurs'] ?? $installation->compteurs,
        ])->save();

        return response()->json([
            'licence' => EtatLicence::pour($installation),
        ]);
    }
}
