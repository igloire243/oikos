<?php

namespace App\Http\Controllers;

use App\Models\AbonnementPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * L'ABONNEMENT AUX NOTIFICATIONS PUSH — une porte d'atterrissage, pas un module.
 *
 * Comme la recherche globale ou le tableau de bord (invariant n° 6) : ce n'est pas un écran qu'on
 * achète, c'est un réglage personnel de l'appareil sur lequel on est. Aucun `permission:` ici —
 * n'importe quel compte connecté, dans n'importe quel espace, peut s'abonner sur l'appareil qu'il
 * tient en main.
 */
class PushController extends Controller
{
    public function abonner(Request $requete): JsonResponse
    {
        $donnees = $requete->validate([
            'endpoint' => ['required', 'string'],
            'cle_p256dh' => ['required', 'string'],
            'cle_auth' => ['required', 'string'],
        ]);

        $hache = hash('sha256', $donnees['endpoint']);

        // MÊME APPAREIL, DEUX FOIS : la permission redemandée, ou le cache vidé, redonne le même
        // endpoint. On remplace la ligne plutôt que d'en empiler une seconde, morte.
        AbonnementPush::query()->updateOrCreate(
            ['endpoint_hache' => $hache],
            [
                'user_id' => $requete->user()->id,
                'endpoint' => $donnees['endpoint'],
                'cle_p256dh' => $donnees['cle_p256dh'],
                'cle_auth' => $donnees['cle_auth'],
            ],
        );

        return response()->json(['abonne' => true]);
    }

    public function desabonner(Request $requete): RedirectResponse|JsonResponse
    {
        $donnees = $requete->validate(['endpoint' => ['required', 'string']]);

        AbonnementPush::query()
            ->where('user_id', $requete->user()->id)
            ->where('endpoint_hache', hash('sha256', $donnees['endpoint']))
            ->delete();

        return response()->json(['abonne' => false]);
    }
}
