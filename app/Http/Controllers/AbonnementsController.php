<?php

namespace App\Http\Controllers;

use App\Metier\Commerce\Montant;
use App\Metier\Commerce\Ventes;
use App\Models\Abonnement;
use App\Models\Entite;
use App\Models\Offre;
use App\Models\PeriodeAbonnement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * VENDRE DEPUIS LA FICHE CLIENT — l'aperçu, la vente, la résiliation.
 *
 * L'aperçu passe par la MÊME méthode que la vente (`Ventes::apercu()`) : le prix et la date de fin
 * qu'on montre avant de confirmer sont ceux qui seront écrits, jamais un second calcul côté écran.
 */
class AbonnementsController extends Controller
{
    public function apercu(Request $requete, Entite $entite): JsonResponse
    {
        [$offre, $devise, $debut] = $this->lire($requete);
        $apercu = Ventes::apercu($entite, $offre, $devise, $debut);

        return response()->json($apercu + [
            'montant' => $apercu['montant_centimes'] !== null ? Montant::formater($apercu['montant_centimes'], $devise) : null,
            'plein' => $apercu['plein_centimes'] !== null ? Montant::formater($apercu['plein_centimes'], $devise) : null,
            'debut_libelle' => Carbon::parse($apercu['debut'])->translatedFormat('j F Y'),
            'fin_libelle' => $apercu['fin'] ? Carbon::parse($apercu['fin'])->translatedFormat('j F Y') : null,
            'licence_fin_libelle' => $apercu['licence_fin'] ? Carbon::parse($apercu['licence_fin'])->translatedFormat('j F Y') : null,
        ]);
    }

    public function store(Request $requete, Entite $entite): RedirectResponse
    {
        [$offre, $devise, $debut] = $this->lire($requete);

        $periode = Ventes::vendre($entite, $offre, $devise, $debut, $requete->user());

        // « Dès maintenant » : la période vendue pour plus tard commence aujourd'hui. Deux gestes
        // distincts côté règle (vendre, puis avancer) — la vente garde ses contrôles, l'avance les siens.
        if ($requete->boolean('maintenant') && $periode->debut->isFuture()) {
            $periode = Ventes::appliquerMaintenant($periode, $requete->user());
        }

        return back()->with('succes', '« '.$offre->nom.' » vendue à « '.$entite->nom.' » jusqu\'au '
            .$periode->fin->translatedFormat('j F Y').' — '.Montant::formater($periode->montant_centimes, $devise)
            .($periode->au_prorata ? ', au prorata de la licence.' : '.'));
    }

    public function appliquerMaintenant(Request $requete, PeriodeAbonnement $periode): RedirectResponse
    {
        $periode = Ventes::appliquerMaintenant($periode, $requete->user());

        return back()->with('succes', '« '.$periode->offre->nom.' » s\'applique dès aujourd\'hui, jusqu\'au '.$periode->fin->translatedFormat('j F Y').'.');
    }

    public function resilier(Request $requete, Abonnement $abonnement): RedirectResponse
    {
        $donnees = $requete->validate(['motif' => ['required', 'string', 'min:3', 'max:255']]);

        Ventes::resilier($abonnement, $donnees['motif'], $requete->user());

        return back()->with('succes', 'Abonnement résilié. Ses périodes restent dans l\'historique.');
    }

    /** @return array{0: Offre, 1: string, 2: Carbon|null} */
    private function lire(Request $requete): array
    {
        $donnees = $requete->validate([
            'offre_id' => ['required', 'integer', Rule::exists('offres', 'id')],
            'devise' => ['required', Rule::in(Ventes::DEVISES)],
            'debut' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return [
            Offre::query()->findOrFail($donnees['offre_id']),
            $donnees['devise'],
            isset($donnees['debut']) ? Carbon::parse($donnees['debut']) : null,
        ];
    }
}
