<?php

namespace App\Metier\Journal;

use App\Models\EntreeJournal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * LE JOURNAL — ce qui se conteste six mois plus tard, écrit depuis les RÈGLES MÉTIER.
 *
 * Jamais depuis un contrôleur : un second écran faisant le même geste laisserait sinon une trace
 * différente, ou aucune. On trace les décisions (une clé émise, une installation activée, une
 * vente, un encaissement), pas chaque écriture. Le libellé est écrit en toutes lettres, et jamais
 * un secret : on trace le geste sur une clé, jamais la clé.
 *
 * En lecture seule, comme celui du produit : pas de purge, pas de correction.
 */
class Journal
{
    /** @param  array<string, mixed>  $details */
    public static function tracer(string $action, ?Model $sujet, string $libelle, array $details = [], ?User $par = null): EntreeJournal
    {
        $auteur = $par ?? Auth::user();

        return EntreeJournal::query()->create([
            'user_id' => $auteur instanceof User ? $auteur->id : null,
            'action' => $action,
            'sujet_type' => $sujet ? class_basename($sujet) : null,
            'sujet_id' => $sujet?->getKey(),
            'libelle' => mb_substr($libelle, 0, 255),
            'details' => $details === [] ? null : $details,
            'ip' => Request::ip(),
        ]);
    }
}
