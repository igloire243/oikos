<?php

namespace App\Http\Controllers;

use App\Models\EntreeJournal;
use App\Models\Paiement;
use App\Models\Plan;
use App\Support\ModesPaiement;
use App\Support\Modules;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * LES OFFRES, MODIFIABLES DEPUIS LA CONSOLE.
 *
 * TROIS PRÉCAUTIONS QUI NE SE VOIENT PAS DANS LE FORMULAIRE
 * ----------------------------------------------------------
 * 1. ON NE SUPPRIME PAS UNE OFFRE VENDUE. `abonnements.plan_id` a une clé étrangère : la
 *    suppression échouerait avec une erreur SQL incompréhensible — ou, si la contrainte venait à
 *    manquer, laisserait des abonnements dont plus personne ne saurait ce qui avait été vendu ni à
 *    quel prix. On refuse, en disant pourquoi, et on propose le retrait de la vente à la place.
 *
 * 2. LE PRIX EST SAISI EN DOLLARS ET STOCKÉ EN CENTIMES. La conversion se fait ici, une fois, avec
 *    `round()` : `(int) (12.30 * 100)` vaut 1229 en virgule flottante, et une offre à 12,29 $ est
 *    une offre fausse dont personne ne comprendra l'origine.
 *
 * 3. « TOUS LES MODULES » N'EST PAS LA LISTE COMPLÈTE COCHÉE. C'est `null`, et la nuance compte :
 *    une offre à null ouvre les modules ajoutés APRÈS sa création, une offre qui les énumère reste
 *    figée. Le jour où un quatorzième module arrive, les offres Premium doivent le recevoir sans
 *    qu'on ait à rouvrir chaque fiche.
 */
class PlanController extends Controller
{
    private const NIVEAUX = [
        'TOUS' => 'Toutes les entités',
        'VISION' => 'Vision',
        'ANTENNE' => 'Antenne',
        'EXTENSION' => 'Église',
    ];

    private const QUOTAS = [
        'membres' => 'Membres',
        'comptes' => 'Comptes utilisateurs',
        'antennes' => 'Antennes',
        'extensions' => 'Églises',
    ];

    public function index()
    {
        $ordre = [Plan::LICENCE => 1, Plan::ACCES => 2, Plan::COMBINEE => 3];

        return view('plans.index', [
            // Groupées par NATURE : c'est ainsi qu'elles se vendent — une licence pour la
            // structure, un accès par entité.
            //
            // Les offres retirées de la vente restent affichées ICI : ce sont elles qui expliquent
            // les abonnements en cours. Seul le site public les masque.
            'parNature' => Plan::withCount('abonnements')->orderBy('ordre')->orderBy('prix_usd_cents')->get()
                ->groupBy('nature')
                ->sortBy(fn ($plans, $nature) => $ordre[$nature] ?? 9),
        ]);
    }

    public function creer()
    {
        return view('plans.formulaire', $this->contexte(new Plan([
            'nature' => Plan::ACCES,
            'palier' => Plan::STANDARD,
            'niveau' => 'TOUS',
            'periode_mois' => 1,
            'is_public' => true,
            'ordre' => 50,
        ])));
    }

    public function enregistrer(Request $request)
    {
        $plan = Plan::create($this->valider($request));
        EntreeJournal::noter('OFFRE_CREEE', $plan, ['code' => $plan->code]);

        return redirect()->route('plans.index')->with('ok', "Offre « {$plan->nom} » créée.");
    }

    public function editer(Plan $plan)
    {
        return view('plans.formulaire', $this->contexte($plan));
    }

    public function modifier(Request $request, Plan $plan)
    {
        $plan->update($this->valider($request, $plan));
        EntreeJournal::noter('OFFRE_MODIFIEE', $plan, ['code' => $plan->code]);

        return redirect()->route('plans.index')->with('ok', "Offre « {$plan->nom} » enregistrée.");
    }

    /** Retirer de la vente, ou remettre. Réversible, et sans effet sur les abonnements en cours. */
    public function basculer(Plan $plan)
    {
        $plan->update(['is_public' => ! $plan->is_public]);
        EntreeJournal::noter($plan->is_public ? 'OFFRE_PUBLIEE' : 'OFFRE_RETIREE', $plan);

        return back()->with('ok', $plan->is_public
            ? "« {$plan->nom} » est de nouveau proposée sur le site public."
            : "« {$plan->nom} » est retirée de la vente. Les abonnements en cours ne changent pas.");
    }

    public function supprimer(Plan $plan)
    {
        $vendus = $plan->abonnements()->count();

        if ($vendus > 0) {
            return back()->with('avertissement',
                "« {$plan->nom} » ne peut pas être supprimée : {$vendus} abonnement(s) s'y rattachent. "
                ."La supprimer effacerait la trace de ce qui leur a été vendu et à quel prix. "
                .'Retirez-la de la vente : elle disparaîtra du site public et restera lisible ici.');
        }

        $nom = $plan->nom;
        $plan->delete();
        EntreeJournal::noter('OFFRE_SUPPRIMEE', null, ['nom' => $nom]);

        return redirect()->route('plans.index')->with('ok', "Offre « {$nom} » supprimée.");
    }

    private function contexte(Plan $plan): array
    {
        return [
            'plan' => $plan,
            'natures' => Plan::NATURES,
            'paliers' => Plan::PALIERS,
            'niveaux' => self::NIVEAUX,
            'quotasPossibles' => self::QUOTAS,
            'espaces' => Modules::espaces(),
            // Quels espaces s'appliquent à quelle nature — affiché dans le formulaire pour qu'on
            // sache, en cochant, ce qui sera réellement retenu.
            'espacesParNature' => [
                Plan::LICENCE => Modules::espacesPour(Plan::LICENCE),
                Plan::ACCES => Modules::espacesPour(Plan::ACCES),
                Plan::COMBINEE => Modules::espacesPour(Plan::COMBINEE),
            ],
            'fournisseurs' => Paiement::FOURNISSEURS,
            'taux' => ModesPaiement::tauxCdf(),
        ];
    }

    private function valider(Request $request, ?Plan $existant = null): array
    {
        $donnees = $request->validate([
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Z][A-Z0-9_]*$/',
                Rule::unique('plans', 'code')->ignore($existant?->plan_id, 'plan_id'),
            ],
            'nature' => ['required', Rule::in(array_keys(Plan::NATURES))],
            'palier' => ['required', Rule::in(array_keys(Plan::PALIERS))],
            'niveau' => ['required', Rule::in(array_keys(self::NIVEAUX))],
            'nom' => ['required', 'string', 'max:120'],
            'argumentaire' => ['nullable', 'string', 'max:1000'],

            'prix_usd' => ['required', 'numeric', 'min:0', 'max:100000'],
            'prix_cdf' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'periode_mois' => ['required', 'integer', 'min:1', 'max:36'],

            'plafond_acces' => ['nullable', Rule::in(array_keys(Plan::PALIERS))],
            'ordre' => ['required', 'integer', 'min:0', 'max:999'],

            'echelon_max' => ['array'],
            'echelon_max.*' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'echelon_usd' => ['array'],
            'echelon_usd.*' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'echelon_cdf' => ['array'],
            'echelon_cdf.*' => ['nullable', 'integer', 'min:0', 'max:2000000000'],

            'quota_actif' => ['array'],
            'quota_valeur' => ['array'],
            'quota_valeur.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],

            'tous_modules' => ['nullable', 'boolean'],
            'fonctionnalites' => ['array'],
            // L'UNION DES DEUX FAMILLES ICI, PAS LES SEULES CLÉS AUTORISÉES. On change de nature
            // dans le même formulaire, sans rechargement : rejeter les cases devenues hors-sujet
            // afficherait une erreur incompréhensible sur des cases que l'utilisateur voit encore
            // cochées. On accepte tout ce qui existe, et on filtre juste avant d'enregistrer.
            'fonctionnalites.*' => [Rule::in(array_keys(Modules::toutes()))],

            'tous_modes' => ['nullable', 'boolean'],
            'modes_paiement' => ['array'],
            'modes_paiement.*' => [Rule::in(array_keys(Paiement::FOURNISSEURS))],
        ], [
            'code.regex' => 'Le code s\'écrit en majuscules, chiffres et tirets bas — par exemple ACCES_STANDARD.',
        ], [
            'prix_usd' => 'prix en dollars',
            'prix_cdf' => 'prix en francs',
            'periode_mois' => 'période',
        ]);

        return [
            'code' => $donnees['code'],
            'nature' => $donnees['nature'],
            'palier' => $donnees['palier'],
            'niveau' => $donnees['niveau'],
            'nom' => $donnees['nom'],
            'argumentaire' => $donnees['argumentaire'] ?? null,

            // round() avant le cast : (int) (12.30 * 100) vaut 1229 en virgule flottante.
            'prix_usd_cents' => (int) round(((float) $donnees['prix_usd']) * 100),
            'prix_cdf' => (int) $donnees['prix_cdf'],
            'periode_mois' => (int) $donnees['periode_mois'],

            'paliers_taille' => $this->echelons($request, $donnees),
            'plafond_acces' => $donnees['nature'] === Plan::LICENCE ? ($donnees['plafond_acces'] ?? null) : null,

            'quotas' => $this->quotas($donnees),
            'fonctionnalites' => $request->boolean('tous_modules')
                ? null
                : $this->modulesRetenus($donnees['nature'], $donnees['fonctionnalites'] ?? []),
            'modes_paiement' => $request->boolean('tous_modes') ? null : array_values($donnees['modes_paiement'] ?? []),

            'is_public' => $request->boolean('is_public'),
            'ordre' => (int) $donnees['ordre'],
        ];
    }

    /**
     * Ne garder que les modules que cette nature d'offre peut réellement ouvrir.
     *
     * Une licence ouvre l'espace de la vision, un accès celui d'une entité, une offre combinée les
     * deux. Enregistrer une clé hors famille produirait une offre qui annonce un module qu'aucun
     * middleware du produit n'ouvrira — le client paierait pour une page qui reste fermée, et le
     * défaut ne se verrait qu'à l'usage, chez lui.
     */
    private function modulesRetenus(string $nature, array $choisis): array
    {
        $autorisees = Modules::clesPour($nature);

        return array_values(array_intersect($choisis, $autorisees));
    }

    /**
     * Les échelons de taille — uniquement pour une licence.
     *
     * On ignore les lignes sans prix, et on TRIE par plafond croissant : `prixPourTaille()` retient
     * le premier échelon qui couvre la taille demandée, donc un échelon « au-delà » saisi en
     * première position attraperait tout et rendrait les autres inaccessibles. Trier ici évite
     * d'avoir à faire confiance à l'ordre de saisie.
     */
    private function echelons(Request $request, array $donnees): ?array
    {
        if ($donnees['nature'] !== Plan::LICENCE) {
            return null;
        }

        $echelons = [];

        foreach (($donnees['echelon_usd'] ?? []) as $i => $usd) {
            if ($usd === null || $usd === '') {
                continue;
            }

            $echelons[] = [
                // null = l'échelon sans plafond, celui qui attrape ce qui dépasse.
                'max' => ($donnees['echelon_max'][$i] ?? null) ?: null,
                'prix_usd_cents' => (int) round(((float) $usd) * 100),
                'prix_cdf' => (int) ($donnees['echelon_cdf'][$i] ?? 0),
            ];
        }

        if (count($echelons) < 2) {
            return null;   // un seul échelon n'est pas une grille : le prix simple suffit
        }

        usort($echelons, fn ($a, $b) => ($a['max'] ?? PHP_INT_MAX) <=> ($b['max'] ?? PHP_INT_MAX));

        return $echelons;
    }

    /**
     * Un quota coché sans valeur vaut « sans limite » (null) — et non zéro, qui interdirait tout.
     * Un quota non coché n'apparaît pas du tout dans l'offre.
     */
    private function quotas(array $donnees): ?array
    {
        $quotas = [];

        foreach (array_keys(self::QUOTAS) as $cle) {
            if (! isset($donnees['quota_actif'][$cle])) {
                continue;
            }

            $valeur = $donnees['quota_valeur'][$cle] ?? null;
            $quotas[$cle] = ($valeur === null || $valeur === '') ? null : (int) $valeur;
        }

        return $quotas ?: null;
    }
}
