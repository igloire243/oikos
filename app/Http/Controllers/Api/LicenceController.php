<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Metier\Clients\IdentiteRecue;
use App\Metier\Licence\Activation;
use App\Metier\Licence\ActivationRefusee;
use App\Metier\Licence\Synchronisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * LES DEUX SEULES ROUTES MACHINE DE LA CONSOLE — activation et synchronisation.
 *
 * Tout ce qui arrive ici vient d'Internet : chaque champ est borné et typé, l'empreinte et le jeton
 * de rappel ont une forme imposée, et l'arbre des entités est plafonné. Aucune session, aucun
 * cookie : un serveur parle à un serveur, la synchronisation s'authentifie par sa clé en Bearer.
 */
class LicenceController extends Controller
{
    private const EMPREINTE = ['string', 'min:16', 'max:128', 'regex:/^[A-Za-z0-9_\-]+$/'];

    public function activer(Request $requete): JsonResponse
    {
        $donnees = $requete->validate([
            'cle' => ['required', 'string', 'max:64'],
            'empreinte' => ['required', ...self::EMPREINTE],
            ...$this->reglesCommunes(),
        ]);

        try {
            return response()->json(Activation::activer($donnees, $requete->ip()));
        } catch (ActivationRefusee $refus) {
            return response()->json(['message' => $refus->getMessage()], 422);
        }
    }

    public function synchroniser(Request $requete): JsonResponse
    {
        $installation = Synchronisation::installationPourCle($requete->bearerToken());

        if ($installation === null) {
            return response()->json(['message' => 'Clé de synchronisation inconnue ou installation désactivée.'], 401);
        }

        $donnees = $requete->validate([
            ...$this->reglesCommunes(),
            'compteurs' => ['nullable', 'array'],
            'compteurs.*' => ['nullable', 'integer', 'min:0', 'max:100000000'],

            // Deux mille entités : au-delà, ce n'est plus un réseau d'églises, c'est une erreur ou
            // un envoi malveillant. Une borne explicite vaut mieux qu'un serveur qui rame.
            'entites' => ['nullable', 'array', 'max:2000'],
            'entites.*.type' => ['required', 'in:VISION,ANTENNE,EXTENSION'],
            'entites.*.ref' => ['required', 'integer', 'min:1'],
            'entites.*.nom' => ['required', 'string', 'max:190'],
            'entites.*.sous_type' => ['nullable', 'string', 'in:SECTEUR,CELLULE'],
            'entites.*.parent_ref' => ['nullable', 'integer', 'min:1'],
            'entites.*.effectif' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ]);

        return response()->json(['licence' => Synchronisation::recevoir($installation, $donnees)]);
    }

    /** @return array<string, mixed> */
    private function reglesCommunes(): array
    {
        return [
            'url' => ['nullable', 'url', 'max:255'],
            'version' => ['nullable', 'string', 'max:20'],
            'rappel' => ['nullable', ...self::EMPREINTE],
            'catalogue' => ['nullable', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
            ...IdentiteRecue::REGLES,
        ];
    }
}
