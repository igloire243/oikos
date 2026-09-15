<?php

namespace App\Http\Controllers;

use App\Models\EntreeJournal;
use App\Models\Reglage;
use App\Support\PromoDecembre;
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

            // Combien d'accès attendent leur mois offert. Un bouton qui touche tout le parc d'un
            // coup doit dire ce qu'il va faire AVANT qu'on clique.
            'promo' => PromoDecembre::etat(),
        ]);
    }

    /**
     * OFFRIR DÉCEMBRE À LA MAIN.
     *
     * La promo est planifiée au 1ᵉʳ décembre (routes/console.php), mais `Schedule::command()` ne
     * fait rien sans un `schedule:run` en cron sur le serveur. Sans ce cron, la promo ne partait
     * jamais et l'oubli ne se voyait qu'en janvier. Ce bouton est le filet.
     *
     * Sans confirmation élaborée, et c'est délibéré : l'opération est IDEMPOTENTE (voir
     * PromoDecembre), donc cliquer deux fois ne crédite personne deux fois. Une confirmation
     * suggérerait un danger qui n'existe pas.
     */
    public function offrirDecembre(Request $request)
    {
        $donnees = $request->validate([
            // On accepte une année pour rattraper un décembre manqué, mais jamais une année à
            // venir : créditer 2027 en septembre 2026 déplacerait des échéances sans raison, et
            // l'idempotence empêcherait ensuite de le faire au bon moment.
            'annee' => ['nullable', 'integer', 'min:2024', 'max:'.now()->year],
        ], [
            // Message écrit à la main : la console tourne en locale `en` (config/app.php), donc un
            // message de validation par défaut sortirait en anglais au milieu d'un écran français.
            'annee.max' => 'On ne peut offrir décembre que pour une année déjà entamée — :max au plus.',
            'annee.min' => 'Année trop ancienne : la promotion existe depuis 2024.',
        ]);

        $resultat = PromoDecembre::offrir($donnees['annee'] ?? null);

        if ($resultat['offerts'] === 0) {
            return back()->with('avertissement', $resultat['deja'] > 0
                ? "Décembre {$resultat['annee']} était déjà offert sur {$resultat['deja']} accès — rien à faire."
                : "Aucun accès actif à créditer pour {$resultat['annee']}.");
        }

        return back()->with('ok',
            "Décembre {$resultat['annee']} offert sur {$resultat['offerts']} accès — leur échéance "
            .'a reculé d\'un mois. Les installations le verront à leur prochaine synchronisation.');
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
