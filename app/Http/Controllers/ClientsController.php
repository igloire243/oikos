<?php

namespace App\Http\Controllers;

use App\Metier\Catalogue\Modules;
use App\Metier\Clients\Installations;
use App\Metier\Commerce\Montant;
use App\Models\Abonnement;
use App\Models\CleActivation;
use App\Models\Client;
use App\Models\Entite;
use App\Models\Installation;
use App\Models\Offre;
use App\Models\PeriodeAbonnement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LES CLIENTS — la liste, et la fiche où tout se fait : installations, entités, clés.
 */
class ClientsController extends Controller
{
    public function index(Request $requete): Response
    {
        $recherche = trim((string) $requete->query('recherche', ''));

        $clients = Client::query()
            ->when($recherche !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('nom', 'like', "%{$recherche}%")
                ->orWhere('ville', 'like', "%{$recherche}%")
                ->orWhere('contact_nom', 'like', "%{$recherche}%")
                ->orWhere('contact_email', 'like', "%{$recherche}%")))
            ->withCount('installations')
            ->with('installations')
            ->orderBy('nom')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Client $client) => [
                'id' => $client->id,
                'nom' => $client->nom,
                'lieu' => collect([$client->ville, $client->pays])->filter()->implode(', ') ?: null,
                'contact' => $client->contact_nom,
                'installations' => $client->installations_count,
                // Le pire état de ses installations : c'est lui qu'on veut voir d'abord.
                'alerte' => $client->installations->contains(fn (Installation $i) => $i->etat() === Installation::MUETTE),
            ]);

        return Inertia::render('Console/Clients/Index', [
            'clients' => $clients,
            'recherche' => $recherche,
        ]);
    }

    public function store(Request $requete): RedirectResponse
    {
        $client = Installations::creerClient($this->valider($requete), $requete->user());

        return redirect()->route('console.clients.show', $client)
            ->with('succes', 'Client créé. Ajoutez son installation, puis émettez une clé d\'activation.');
    }

    public function update(Request $requete, Client $client): RedirectResponse
    {
        $client->update($this->valider($requete));

        return back()->with('succes', 'Fiche enregistrée. Une synchronisation ne l\'écrasera pas.');
    }

    public function show(Request $requete, Client $client): Response
    {
        $client->load(['installations.entites.abonnement.periodes.offre', 'installations.clesActivation.emisePar']);

        return Inertia::render('Console/Clients/Fiche', [
            'client' => [
                'id' => $client->id,
                'nom' => $client->nom,
                'pays' => $client->pays,
                'ville' => $client->ville,
                'contact_nom' => $client->contact_nom,
                'contact_email' => $client->contact_email,
                'contact_telephone' => $client->contact_telephone,
                'notes' => $client->notes,
            ],
            'installations' => $client->installations->sortBy('id')->values()->map(fn (Installation $i) => $this->presenterInstallation($i)),
            // Montrée UNE fois, juste après l'émission : la base ne garde que son empreinte.
            'cle_emise' => $requete->session()->get('cle_emise'),
            // Ce qu'on peut vendre, rangé par niveau : la modale de vente ne propose à une église
            // que des offres d'église. Le PRIX affiché vient de l'aperçu, pas d'ici — une licence
            // dépend de la taille du réseau.
            'offres_en_vente' => Offre::query()->enVente()->orderBy('ordre')->get()->groupBy('niveau')
                ->map(fn ($offres) => $offres->map(fn (Offre $o) => [
                    'valeur' => $o->id,
                    'libelle' => $o->nom.' — '.($o->periode_mois === 12 ? 'annuelle' : ($o->periode_mois === 1 ? 'mensuelle' : $o->periode_mois.' mois')),
                ])->values()),
        ]);
    }

    /** @return array<string, mixed> */
    private function presenterInstallation(Installation $installation): array
    {
        return [
            'id' => $installation->id,
            'nom' => $installation->nom,
            'libelle' => $installation->libelle(),
            'url' => $installation->url,
            'etat' => $installation->etat(),
            'libelle_etat' => Installation::ETATS[$installation->etat()],
            'version' => $installation->version,
            'empreinte' => $installation->empreinte ? substr($installation->empreinte, 0, 12).'…' : null,
            'activee_le' => $installation->activee_le?->translatedFormat('j F Y'),
            'vue_le' => $installation->vue_le?->diffForHumans(),
            'rappel_possible' => $installation->url !== null && $installation->rappel_jeton !== null,
            'rappel_le' => $installation->rappel_le?->diffForHumans(),
            // Un catalogue différent de celui de la console : le produit ou la console n'est pas à
            // jour, et un module vendu risquerait de ne rien ouvrir.
            'catalogue_different' => $installation->catalogue_empreinte !== null
                && $installation->catalogue_empreinte !== Modules::empreinte(),
            'compteurs' => $installation->compteurs ?? [],
            'arbre' => $this->arbre($installation->entites),
            'cles' => $installation->clesActivation->sortByDesc('id')->values()->map(fn (CleActivation $cle) => [
                'id' => $cle->id,
                'apercu' => $cle->code_apercu,
                'etat' => $cle->etat(),
                'libelle_etat' => CleActivation::ETATS[$cle->etat()],
                'expire_le' => $cle->expire_le->translatedFormat('j F Y'),
                'utilisee_le' => $cle->utilisee_le?->translatedFormat('j F Y à H\hi'),
                'emise_par' => $cle->emisePar?->name,
                'note' => $cle->note,
            ]),
        ];
    }

    /**
     * L'arbre tel que l'installation l'a remonté : la Vision, ses antennes, leurs églises. Une
     * entité dont le parent n'a pas été remonté reste visible, rangée à part — la cacher ferait
     * disparaître une église à qui l'on vend peut-être déjà.
     *
     * @param  Collection<int, Entite>  $entites
     * @return list<array<string, mixed>>
     */
    private function arbre(Collection $entites): array
    {
        $presenter = fn (Entite $e) => [
            'id' => $e->id,
            'reference' => $e->reference(),
            'type' => $e->type,
            'libelle_type' => $e->sous_type ? ucfirst(strtolower($e->sous_type)) : Entite::TYPES[$e->type],
            'nom' => $e->nom,
            'effectif' => $e->effectif,
            'vue_le' => $e->vue_le?->translatedFormat('j M Y'),
            'abonnement' => $this->presenterAbonnement($e),
        ];

        $antennes = $entites->where('type', Entite::ANTENNE)->sortBy('nom');
        $eglises = $entites->where('type', Entite::EXTENSION)->sortBy('nom');
        $refsAntennes = $antennes->pluck('ref')->all();

        $noeuds = [];

        foreach ($entites->where('type', Entite::VISION) as $vision) {
            $noeuds[] = $presenter($vision) + ['enfants' => []];
        }

        foreach ($antennes as $antenne) {
            $noeuds[] = $presenter($antenne) + [
                'enfants' => $eglises->where('parent_ref', $antenne->ref)->values()->map($presenter)->all(),
            ];
        }

        $orphelines = $eglises->reject(fn (Entite $e) => in_array($e->parent_ref, $refsAntennes, true));
        if ($orphelines->isNotEmpty()) {
            $noeuds[] = ['id' => 0, 'reference' => null, 'type' => 'AUTRES', 'libelle_type' => 'Sans antenne remontée', 'nom' => 'Églises sans antenne', 'effectif' => null, 'vue_le' => null,
                'enfants' => $orphelines->values()->map($presenter)->all()];
        }

        return $noeuds;
    }

    /**
     * Ce qu'on sait de l'abonnement d'une entité, pour la ligne de l'arbre : son état, l'offre qui
     * court, jusqu'à quand, et l'historique des périodes vendues.
     *
     * @return array<string, mixed>|null
     */
    private function presenterAbonnement(Entite $entite): ?array
    {
        $abonnement = $entite->abonnement;

        if ($abonnement === null) {
            return null;
        }

        $aujourdhui = now()->startOfDay();
        $courante = $abonnement->periodeAu($aujourdhui) ?? $abonnement->dernierePeriode();
        $etat = $abonnement->etat();

        return [
            'id' => $abonnement->id,
            'etat' => $etat,
            'libelle_etat' => Abonnement::ETATS[$etat],
            'offre' => $courante?->offre->nom,
            'fin' => $abonnement->dernierePeriode()?->fin->translatedFormat('j F Y'),
            'motif_resiliation' => $abonnement->motif_resiliation,
            'periodes' => $abonnement->periodes->sortByDesc('debut')->values()->map(fn (PeriodeAbonnement $p) => [
                'id' => $p->id,
                'offre' => $p->offre->nom,
                'du' => $p->debut->translatedFormat('j M Y'),
                'au' => $p->fin->translatedFormat('j M Y'),
                'montant' => Montant::formater($p->montant_centimes, $p->devise),
                'au_prorata' => $p->au_prorata,
            ]),
        ];
    }

    /** @return array<string, mixed> */
    private function valider(Request $requete): array
    {
        return $requete->validate([
            'nom' => ['required', 'string', 'max:200'],
            'pays' => ['nullable', 'string', 'max:120'],
            'ville' => ['nullable', 'string', 'max:120'],
            'contact_nom' => ['nullable', 'string', 'max:190'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'contact_telephone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
