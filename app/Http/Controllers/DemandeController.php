<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use Illuminate\Http\Request;

/**
 * La boîte de réception des demandes venues du site public.
 *
 * ON MARQUE, ON NE SUPPRIME PAS. « Indésirable » est un statut, pas une suppression : un vrai
 * client mal classé — un pasteur qui écrit en trois lignes sans majuscules ressemble beaucoup à un
 * robot — doit pouvoir être retrouvé. Une demande effacée par erreur est un client perdu sans
 * qu'on sache jamais qu'il avait écrit.
 */
class DemandeController extends Controller
{
    public function index(Request $request)
    {
        $statut = $request->query('statut', 'OUVERTES');

        $requete = Demande::query();

        if ($statut === 'OUVERTES') {
            $requete->whereIn('statut', [Demande::NOUVELLE, Demande::LUE]);
        } elseif (array_key_exists($statut, Demande::STATUTS)) {
            $requete->where('statut', $statut);
        }

        return view('demandes.index', [
            'demandes' => $requete->orderByDesc('cree_le')->limit(200)->get(),
            'statut' => $statut,
            'nouvelles' => Demande::where('statut', Demande::NOUVELLE)->count(),
        ]);
    }

    public function marquer(Request $request, Demande $demande)
    {
        $donnees = $request->validate([
            'statut' => ['required', 'string', 'in:'.implode(',', array_keys(Demande::STATUTS))],
            'note_interne' => ['nullable', 'string', 'max:5000'],
        ]);

        $demande->statut = $donnees['statut'];

        if ($request->has('note_interne')) {
            $demande->note_interne = $donnees['note_interne'];
        }

        // La date de traitement n'est posée qu'une fois, et jamais retirée : elle dit « on s'en est
        // occupé ce jour-là ». La réécrire à chaque changement de statut effacerait cette réponse.
        if (in_array($demande->statut, [Demande::TRAITEE, Demande::SPAM], true) && ! $demande->traite_le) {
            $demande->traite_le = now();
        }

        $demande->save();

        return back()->with('ok', 'Demande mise à jour.');
    }
}
