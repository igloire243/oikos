<?php

namespace App\Metier\Licence;

use App\Models\Installation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * LE RAPPEL — demander à une installation de se resynchroniser tout de suite.
 *
 * Il ne transporte AUCUNE donnée : il présente le jeton que l'installation nous a confié, et
 * l'installation rappelle la console par le canal habituel. Une console compromise ne peut donc
 * rien faire écrire à mille installations — au pire, les faire se synchroniser.
 *
 * Il ne lève jamais : un client injoignable ne doit pas faire échouer le geste qui l'appelle
 * (un encaissement, au Lot C3). Six secondes au plus, puis on passe.
 */
class Rappel
{
    public static function prevenir(Installation $installation): bool
    {
        if (! $installation->url || ! $installation->rappel_jeton || $installation->estDesactivee()) {
            return false;
        }

        try {
            $reponse = Http::timeout(6)
                ->acceptJson()
                ->withToken($installation->rappel_jeton)
                ->post(rtrim($installation->url, '/').'/oikos/rafraichir');

            if ($reponse->successful()) {
                $installation->forceFill(['rappel_le' => Carbon::now()])->save();

                return true;
            }

            Log::warning("Rappel de l'installation {$installation->id} refusé : HTTP {$reponse->status()}");
        } catch (Throwable $erreur) {
            Log::warning("Rappel de l'installation {$installation->id} impossible : ".$erreur->getMessage());
        }

        return false;
    }
}
