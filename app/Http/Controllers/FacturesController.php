<?php

namespace App\Http\Controllers;

use App\Metier\Commerce\Facturation;
use App\Metier\Commerce\Montant;
use App\Models\Facture;
use App\Models\Paiement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FACTURES ET ENCAISSEMENTS — ce qui est dû, ce qui est arrivé, et ce qui est en retard.
 *
 * Aucune règle ici : `Facturation` est le seul écrivain, et le contrôleur ne fait que traduire le
 * formulaire (un montant tapé « 12,50 ») en centimes. L'état d'une facture se DÉRIVE de ses
 * paiements reçus — jamais stocké —, donc le filtre se fait sur la liste chargée, pas en SQL :
 * recopier la règle dans une requête serait une seconde définition de « soldée ».
 */
class FacturesController extends Controller
{
    private const FILTRES = ['impayees', 'partielles', 'retard', 'soldees'];

    public function index(Request $requete): Response
    {
        $filtre = in_array($requete->query('etat'), self::FILTRES, true) ? (string) $requete->query('etat') : null;
        $recherche = trim((string) $requete->query('recherche', ''));

        $toutes = Facture::query()
            ->with(['paiements.saisiPar', 'periode.offre', 'periode.abonnement.entite.installation.client'])
            ->when($recherche !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('numero', 'like', "%{$recherche}%")
                ->orWhereHas('periode.abonnement.entite', fn ($e) => $e->where('nom', 'like', "%{$recherche}%"))
                ->orWhereHas('periode.abonnement.entite.installation.client', fn ($c) => $c->where('nom', 'like', "%{$recherche}%"))))
            ->orderByDesc('emise_le')
            ->orderByDesc('id')
            ->get();

        $comptes = [
            'tous' => $toutes->count(),
            'impayees' => $toutes->filter(fn (Facture $f) => $f->etat() === Facture::EN_ATTENTE)->count(),
            'partielles' => $toutes->filter(fn (Facture $f) => $f->etat() === Facture::PARTIELLE)->count(),
            'retard' => $toutes->filter(fn (Facture $f) => $f->enRetard())->count(),
            'soldees' => $toutes->filter(fn (Facture $f) => $f->estSoldee())->count(),
        ];

        $affichees = $toutes->filter(fn (Facture $f) => match ($filtre) {
            'impayees' => $f->etat() === Facture::EN_ATTENTE,
            'partielles' => $f->etat() === Facture::PARTIELLE,
            'retard' => $f->enRetard(),
            'soldees' => $f->estSoldee(),
            default => true,
        })->values();

        return Inertia::render('Console/Factures/Index', [
            'factures' => $affichees->take(100)->map(fn (Facture $f) => $this->presenter($f))->values(),
            'tronquee' => $affichees->count() > 100,
            'comptes' => $comptes,
            // Une ligne par devise : on n'additionne jamais des dollars et des francs (n° 19).
            'a_recevoir' => $toutes->reject(fn (Facture $f) => $f->estSoldee())->groupBy('devise')
                ->map(fn ($groupe, $devise) => Montant::formater((int) $groupe->sum(fn (Facture $f) => $f->restantCentimes()), (string) $devise))
                ->values(),
            'filtres' => ['etat' => $filtre, 'recherche' => $recherche],
            'moyens' => collect(Paiement::MOYENS)->map(fn ($libelle, $valeur) => ['valeur' => $valeur, 'libelle' => $libelle])->values(),
        ]);
    }

    public function encaisser(Request $requete, Facture $facture): RedirectResponse
    {
        $donnees = $requete->validate([
            'montant' => ['required', 'string', 'max:30'],
            'moyen' => ['required', Rule::in(array_keys(Paiement::MOYENS))],
            'reference' => ['nullable', 'string', 'max:120'],
            'recu_le' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $paiement = Facturation::encaisser(
            $facture,
            Montant::enCentimes($donnees['montant'], $facture->devise),
            $donnees['moyen'],
            $donnees['reference'] ?? null,
            Carbon::parse($donnees['recu_le']),
            $donnees['notes'] ?? null,
            $requete->user(),
        );

        return back()->with('succes', Montant::formater($paiement->montant_centimes, $facture->devise)." encaissés sur {$facture->numero}.");
    }

    public function nonRecu(Request $requete, Paiement $paiement): RedirectResponse
    {
        $donnees = $requete->validate(['motif' => ['required', 'string', 'min:3', 'max:300']]);

        Facturation::marquerNonRecu($paiement, $donnees['motif'], $requete->user());

        return back()->with('succes', 'Versement marqué « non reçu ».');
    }

    public function retablir(Request $requete, Paiement $paiement): RedirectResponse
    {
        Facturation::retablir($paiement, $requete->user());

        return back()->with('succes', 'Versement rétabli.');
    }

    /** @return array<string, mixed> */
    private function presenter(Facture $f): array
    {
        $entite = $f->periode->abonnement->entite;
        $installation = $entite->installation;

        return [
            'id' => $f->id,
            'numero' => $f->numero,
            'client' => $installation->client->nom,
            'installation_id' => $installation->id,
            'client_id' => $installation->client_id,
            'entite' => $entite->nom,
            'offre' => $f->periode->offre->nom,
            'periode' => $f->periode->debut->translatedFormat('j M Y').' → '.$f->periode->fin->translatedFormat('j M Y'),
            'devise' => $f->devise,
            'montant' => $f->montant(),
            'recu' => Montant::formater($f->recuCentimes(), $f->devise),
            'restant' => Montant::formater($f->restantCentimes(), $f->devise),
            'restant_saisie' => Montant::enUnites($f->restantCentimes(), $f->devise),
            'etat' => $f->etat(),
            'etat_libelle' => Facture::ETATS[$f->etat()],
            'en_retard' => $f->enRetard(),
            'emise_le' => $f->emise_le->translatedFormat('l j F Y'),
            'echeance_le' => $f->echeance_le->translatedFormat('l j F Y'),
            'paiements' => $f->paiements->sortBy('recu_le')->map(fn (Paiement $p) => [
                'id' => $p->id,
                'montant' => Montant::formater($p->montant_centimes, $f->devise),
                'moyen' => Paiement::MOYENS[$p->moyen] ?? $p->moyen,
                'reference' => $p->reference,
                'recu_le' => $p->recu_le->translatedFormat('D j M Y'),
                'par' => $p->saisiPar?->name,
                'notes' => $p->notes,
                'recu' => $p->estRecu(),
                'motif_non_recu' => $p->motif_non_recu,
            ])->values(),
        ];
    }
}
