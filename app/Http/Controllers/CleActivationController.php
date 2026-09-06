<?php

namespace App\Http\Controllers;

use App\Models\CleActivation;
use App\Models\EntreeJournal;
use App\Models\Installation;
use Illuminate\Http\Request;

/**
 * L'ÉMISSION DES CLÉS D'ACTIVATION.
 *
 * LA CLÉ N'EST LISIBLE QU'UNE FOIS, comme la clé de synchronisation — la base n'en garde que
 * l'empreinte. Elle part donc par un flash de session, s'affiche sur la fiche du client, et
 * disparaît au rafraîchissement. Si elle est perdue avant d'avoir été envoyée, on en émet une
 * autre : c'est une opération de quelques secondes, et c'est le prix d'une clé qu'on ne peut pas
 * voler dans la base.
 *
 * POURQUOI ON PEUT EN ÉMETTRE PLUSIEURS
 * ---------------------------------------
 * Un client réinstalle, change de serveur, ou vous rappelle en disant qu'il a perdu le message.
 * Interdire une deuxième clé obligerait à révoquer la première d'abord — un geste de plus, oublié
 * une fois sur deux. Elles coexistent, chacune à usage unique, et se révoquent une par une.
 */
class CleActivationController extends Controller
{
    public function emettre(Request $request, Installation $installation)
    {
        $donnees = $request->validate([
            // Zéro = sans expiration. Possible, mais c'est une clé qui traînera dans une
            // conversation WhatsApp pendant des années — d'où le défaut à trente jours.
            'jours' => ['required', 'integer', 'min:0', 'max:365'],
            'note' => ['nullable', 'string', 'max:190'],
        ]);

        $emission = CleActivation::emettre(
            $installation,
            (int) $donnees['jours'],
            $donnees['note'] ?? null
        );

        EntreeJournal::noter('CLE_ACTIVATION_EMISE', $installation, [
            'apercu' => $emission['modele']->code_apercu,
            'expire_le' => $emission['modele']->expire_le?->toDateString(),
        ]);

        return back()->with('cle_activation_neuve', [
            'code' => $emission['code'],
            'installation' => $installation->nom,
            'expire_le' => $emission['modele']->expire_le?->format('d/m/Y'),
        ]);
    }

    public function revoquer(CleActivation $cle)
    {
        // Une clé déjà utilisée n'est pas révocable : elle n'ouvre plus rien, et changer son statut
        // effacerait la trace de l'activation qu'elle a servie — c'est-à-dire la réponse à
        // « quand cette installation a-t-elle été activée, et depuis quelle empreinte ? ».
        if ($cle->statut === CleActivation::UTILISEE) {
            return back()->with('avertissement',
                "Cette clé a déjà servi : elle n'ouvre plus rien. Pour couper l'accès de "
                ."l'installation, désactivez-la ou renouvelez sa clé de synchronisation.");
        }

        $cle->update(['statut' => CleActivation::REVOQUEE]);
        EntreeJournal::noter('CLE_ACTIVATION_REVOQUEE', $cle->installation, ['apercu' => $cle->code_apercu]);

        return back()->with('ok', 'Clé révoquée.');
    }
}
