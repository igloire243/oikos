<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\EntreeJournal;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::withCount('installations')
            ->when($request->filled('q'), fn ($r) => $r->where('nom', 'like', '%'.$request->input('q').'%'))
            ->when($request->filled('statut'), fn ($r) => $r->where('statut', $request->input('statut')))
            ->orderBy('nom')
            ->get();

        return view('clients.index', ['clients' => $clients, 'q' => $request->input('q')]);
    }

    public function creer()
    {
        return view('clients.creer');
    }

    public function enregistrer(Request $request)
    {
        $data = $this->valider($request);

        $client = Client::create($data);
        EntreeJournal::noter('CLIENT_CREE', $client, ['nom' => $client->nom]);

        return redirect()->route('clients.fiche', $client)
            ->with('ok', 'Client créé. Ajoutez-lui une installation pour commencer.');
    }

    public function fiche(Client $client)
    {
        $client->load(['installations.entites', 'installations.abonnements.plan', 'installations.clesActivation']);

        return view('clients.fiche', ['client' => $client]);
    }

    public function modifier(Request $request, Client $client)
    {
        $client->update($this->valider($request));
        EntreeJournal::noter('CLIENT_MODIFIE', $client);

        return back()->with('ok', 'Fiche mise à jour.');
    }

    /**
     * Autoriser — ou retirer — la citation de ce client sur le site public.
     *
     * POURQUOI CE N'EST PAS UN CHAMP DE PLUS DANS LA FICHE. Cocher cette case publie le nom d'une
     * église sur Internet. Mêlée aux coordonnées du pasteur et aux notes internes, la case se
     * cocherait un jour par mégarde, en corrigeant un numéro de téléphone. Séparée, avec son
     * propre bouton, elle demande une intention.
     */
    public function vitrine(Request $request, Client $client)
    {
        $donnees = $request->validate([
            'temoignage' => ['nullable', 'string', 'max:1000'],
            'temoignage_auteur' => ['nullable', 'string', 'max:190'],
            'site_url' => ['nullable', 'url', 'max:255'],
        ]);

        $citer = $request->boolean('vitrine');

        $client->vitrine = $citer;
        $client->temoignage = $donnees['temoignage'] ?? null;
        $client->temoignage_auteur = $donnees['temoignage_auteur'] ?? null;
        $client->site_url = $donnees['site_url'] ?? null;

        // La date répond à « depuis quand, et sur quel accord ». On la pose à la première
        // autorisation et on l'efface au retrait : un client qui n'est plus cité ne doit pas
        // garder une date d'accord qui laisserait croire qu'il l'est encore.
        if ($citer && ! $client->vitrine_accord_le) {
            $client->vitrine_accord_le = now();
        } elseif (! $citer) {
            $client->vitrine_accord_le = null;
        }

        $client->save();
        EntreeJournal::noter($citer ? 'CLIENT_VITRINE_OUVERTE' : 'CLIENT_VITRINE_RETIREE', $client);

        return back()->with('ok', $citer
            ? 'Ce client est désormais cité sur le site public.'
            : "Ce client n'est plus cité sur le site public.");
    }

    private function valider(Request $request): array
    {
        // \p{L} et non [A-Za-z] : « Église Bethel », « Ntumba wa Nkoy » sont les noms que ce
        // logiciel doit accueillir, pas des exceptions à refuser.
        return $request->validate([
            'nom' => ['required', 'string', 'min:2', 'max:200', 'regex:/\p{L}/u'],
            'pays' => ['nullable', 'string', 'max:120'],
            'ville' => ['nullable', 'string', 'max:120'],
            'contact_nom' => ['nullable', 'string', 'max:190'],
            'contact_email' => ['nullable', 'email:rfc', 'max:190'],
            'contact_telephone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+\s\-().]*$/'],
            'statut' => ['required', 'in:'.implode(',', array_keys(Client::STATUTS))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'nom.regex' => 'Le nom doit contenir au moins une lettre.',
            'contact_telephone.regex' => 'Chiffres, espaces, +, -, points et parenthèses uniquement.',
        ]);
    }
}
