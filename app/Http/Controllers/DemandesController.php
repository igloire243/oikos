<?php

namespace App\Http\Controllers;

use App\Metier\Journal\Journal;
use App\Models\DemandeContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LES DEMANDES DU SITE COMMERCIAL — les plus anciennes non traitées d'abord : une demande oubliée est
 * un client perdu en silence. On les traite, on ne les supprime pas : « qui a répondu, et quand ? »
 * garde sa réponse.
 */
class DemandesController extends Controller
{
    public function index(Request $requete): Response
    {
        $etat = $requete->query('etat') === 'traitees' ? 'traitees' : 'a_traiter';

        $demandes = DemandeContact::query()
            ->with('traiteePar')
            ->when($etat === 'a_traiter', fn ($q) => $q->whereNull('traitee_le')->orderBy('created_at'))
            ->when($etat === 'traitees', fn ($q) => $q->whereNotNull('traitee_le')->orderByDesc('traitee_le'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DemandeContact $d) => [
                'id' => $d->id,
                'nom' => $d->nom,
                'organisation' => $d->organisation,
                'email' => $d->email,
                'telephone' => $d->telephone,
                'pays' => $d->pays,
                'message' => $d->message,
                'recue_le' => $d->created_at->translatedFormat('l j F Y \à H\hi'),
                'traitee' => $d->estTraitee(),
                'traitee_le' => $d->traitee_le?->translatedFormat('l j F Y'),
                'traitee_par' => $d->traiteePar?->name,
                'note' => $d->note,
            ]);

        return Inertia::render('Console/Demandes/Index', [
            'demandes' => $demandes,
            'etat' => $etat,
            'comptes' => [
                'a_traiter' => DemandeContact::query()->whereNull('traitee_le')->count(),
                'traitees' => DemandeContact::query()->whereNotNull('traitee_le')->count(),
            ],
        ]);
    }

    public function traiter(Request $requete, DemandeContact $demande): RedirectResponse
    {
        $donnees = $requete->validate(['note' => ['nullable', 'string', 'max:500']]);

        if ($demande->estTraitee()) {
            return back()->with('erreur', 'Cette demande est déjà traitée.');
        }

        $demande->forceFill([
            'traitee_le' => Carbon::now(),
            'traitee_par_id' => $requete->user()->id,
            'note' => $donnees['note'] ?? null,
        ])->save();

        Journal::tracer('DEMANDE_TRAITEE', $demande, "Demande de {$demande->nom} traitée", [], $requete->user());

        return back()->with('succes', 'Demande marquée comme traitée.');
    }
}
