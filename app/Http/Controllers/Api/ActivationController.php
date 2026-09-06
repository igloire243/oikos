<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CleActivation;
use App\Models\EntreeJournal;
use App\Support\EtatLicence;
use App\Support\IdentiteRecue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * L'ACTIVATION — le seul moment où une clé courte sert.
 *
 * CE QUI SE PASSE ICI, EN UNE PHRASE : une installation présente une clé courte et son empreinte ;
 * la console vérifie, marque la clé consommée, ÉMET UNE NOUVELLE CLÉ DE SYNCHRONISATION, et rend
 * l'état d'abonnement. La clé courte ne resservira jamais.
 *
 * POURQUOI LA CLÉ DE SYNCHRONISATION EST RENOUVELÉE À CET INSTANT
 * ----------------------------------------------------------------
 * Parce qu'elle ne peut pas être relue : la base n'en garde que l'empreinte. La seule façon d'en
 * remettre une à l'installation est d'en fabriquer une neuve. Effet secondaire heureux : réactiver
 * une installation invalide la clé précédente — si un ancien serveur tournait encore quelque part,
 * il cesse de se synchroniser.
 *
 * CE QUE CETTE ROUTE NE FAIT PAS
 * --------------------------------
 * Elle ne crée pas d'installation, ne crée pas de client, ne vend rien. Tout existe déjà dans la
 * console avant qu'une clé soit émise. Une route publique qui pourrait créer des lignes serait un
 * moyen de remplir votre base depuis Internet.
 */
class ActivationController extends Controller
{
    public function activer(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'cle' => ['required', 'string', 'max:64'],

            // L'empreinte identifie l'installation qui consomme la clé. Bornée et contrainte :
            // elle est stockée puis réaffichée dans la console, et rien de ce qui vient de
            // l'extérieur ne doit y arriver sans forme imposée.
            'empreinte' => ['required', 'string', 'min:16', 'max:128', 'regex:/^[A-Za-z0-9_\-]+$/'],

            'url' => ['nullable', 'url', 'max:255'],
            'version' => ['nullable', 'string', 'max:20'],

            // Le secret que l'installation nous confie pour qu'on puisse la rappeler. C'est le
            // seul moment où elle peut nous le donner sans qu'on sache déjà la joindre.
            'rappel' => ['nullable', 'string', 'min:16', 'max:128', 'regex:/^[A-Za-z0-9_\-]+$/'],

            // La carte de visite de l'installation : nom de la communauté, ville, coordonnées du
            // responsable. Elle ne remplit que les cases vides de la fiche client — jamais plus.
            // Voir App\Support\IdentiteRecue, qui porte la règle et l'explique.
            ...IdentiteRecue::REGLES,
        ]);

        $cle = CleActivation::parCode($donnees['cle']);

        if (! $cle) {
            // Message unique et vague : préciser « clé inconnue » plutôt que « clé expirée »
            // aiderait quelqu'un qui essaie des clés au hasard à savoir quand il approche.
            return response()->json(['message' => "Clé d'activation invalide."], 422);
        }

        if (! $cle->estUtilisable()) {
            // Ici, en revanche, la clé EST la bonne : dire pourquoi elle est refusée fait gagner
            // un appel téléphonique.
            return response()->json(['message' => $cle->raisonDuRefus()], 422);
        }

        $installation = $cle->installation;

        if (! $installation || ! $installation->active) {
            return response()->json([
                'message' => 'Cette installation est désactivée. Contactez votre fournisseur.',
            ], 422);
        }

        $jeton = $installation->renouvelerCle();

        // AVANT la sauvegarde : la carte de visite peut renseigner le nom de l'installation, qu'on
        // a le droit de laisser vide à la création justement pour qu'il se remplisse ici.
        $remplis = IdentiteRecue::appliquer($installation, $donnees['identite'] ?? null);

        $installation->forceFill([
            // L'empreinte devient l'identité de l'installation, et non plus seulement une trace sur
            // la clé consommée : la licence signée la porte, et le produit refuse une licence qui
            // ne serait pas la sienne. Voir la migration ajouter_empreinte_aux_installations.
            'empreinte' => $donnees['empreinte'],

            'url' => $donnees['url'] ?? $installation->url,
            'version' => $donnees['version'] ?? $installation->version,
            'rappel_jeton' => $donnees['rappel'] ?? $installation->rappel_jeton,
            'vue_le' => now(),
        ])->save();

        $cle->consommer($donnees['empreinte'], $request->ip());

        EntreeJournal::noter('INSTALLATION_ACTIVEE', $installation, [
            'empreinte' => $donnees['empreinte'],
            'url' => $donnees['url'] ?? null,

            // Ce que la fiche a gagné toute seule. Le noter permet, plus tard, de distinguer une
            // information que vous avez saisie d'une information que l'installation a déclarée.
            'fiche_remplie' => $remplis,
        ]);

        return response()->json([
            'jeton' => $jeton,
            'licence' => EtatLicence::pour($installation),
        ]);
    }
}
