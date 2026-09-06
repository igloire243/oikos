<?php

namespace App\Http\Controllers;

use App\Models\EntreeJournal;
use App\Models\Reglage;
use Illuminate\Http\Request;

/**
 * L'écran de configuration : taux, devise, délai promis, coordonnées d'encaissement.
 *
 * LES CASES À COCHER NON COCHÉES N'ARRIVENT PAS
 * ----------------------------------------------
 * Un navigateur n'envoie RIEN pour une case décochée. Si l'on se contentait de parcourir ce que le
 * formulaire a transmis, décocher « Airtel Money » ne le désactiverait jamais : la clé serait
 * simplement absente, et l'ancienne valeur resterait en base. On parcourt donc les réglages
 * ATTENDUS — ceux qui existent en base — et non ceux qui sont arrivés. C'est le piège classique
 * des formulaires de préférences, et il ne se voit qu'à l'usage : « j'ai décoché, ça revient ».
 *
 * LA VALIDATION EST TYPÉE PAR RÉGLAGE, PAS GLOBALE. Un taux de change doit être un entier positif ;
 * un taux à zéro, ou négatif, produirait des prix en francs absurdes sur toute la vitrine sans
 * qu'aucune erreur ne soit levée.
 */
class ReglageController extends Controller
{
    private const GROUPES = [
        'general' => ['titre' => 'Général', 'icone' => 'sliders-horizontal'],
        'paiement' => ['titre' => 'Encaissement', 'icone' => 'receipt'],
        'modes' => ['titre' => 'Modes de paiement', 'icone' => 'wallet'],
    ];

    public function index()
    {
        return view('reglages.index', [
            'parGroupe' => Reglage::orderBy('ordre')->get()->groupBy('groupe'),
            'groupes' => self::GROUPES,
        ]);
    }

    public function enregistrer(Request $request)
    {
        $reglages = Reglage::all();

        $regles = [];
        foreach ($reglages as $reglage) {
            $champ = 'r_'.$reglage->cle;

            $regles[$champ] = match ($reglage->type) {
                'entier' => ['nullable', 'integer', 'min:1', 'max:100000000'],
                'booleen' => ['nullable', 'boolean'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        $donnees = $request->validate($regles, [], $this->nomsLisibles($reglages));

        foreach ($reglages as $reglage) {
            $champ = 'r_'.$reglage->cle;

            // Le booléen se déduit de la PRÉSENCE de la clé, pas de sa valeur : voir l'explication
            // en tête de classe.
            $valeur = $reglage->type === 'booleen'
                ? ($request->boolean($champ) ? '1' : '0')
                : (string) ($donnees[$champ] ?? '');

            if ($valeur !== (string) $reglage->valeur) {
                $reglage->update(['valeur' => $valeur]);
            }
        }

        EntreeJournal::noter('REGLAGES_MODIFIES', null);

        return back()->with('ok', 'Réglages enregistrés.');
    }

    /**
     * Les messages d'erreur doivent nommer le réglage tel qu'il est écrit à l'écran. « Le champ
     * r_taux_cdf doit être un entier » n'aide personne à trouver la ligne fautive.
     */
    private function nomsLisibles($reglages): array
    {
        $noms = [];

        foreach ($reglages as $reglage) {
            $noms['r_'.$reglage->cle] = mb_strtolower($reglage->libelle);
        }

        return $noms;
    }
}
