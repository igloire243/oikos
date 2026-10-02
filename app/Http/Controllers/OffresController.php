<?php

namespace App\Http\Controllers;

use App\Metier\Catalogue\Modules;
use App\Metier\Commerce\Montant;
use App\Metier\Commerce\Offres;
use App\Models\Entite;
use App\Models\Offre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LES OFFRES — le catalogue commercial. Les règles vivent dans `App\Metier\Commerce\Offres` ; ici
 * on ne fait que traduire le formulaire (des montants tapés « 12,50 ») en centimes.
 */
class OffresController extends Controller
{
    public function index(): Response
    {
        $offres = Offre::query()->withCount('periodes')->orderBy('ordre')->orderBy('nom')->get();

        return Inertia::render('Console/Offres/Index', [
            'offres' => $offres->map(fn (Offre $o) => [
                'id' => $o->id,
                'code' => $o->code,
                'nature' => $o->nature,
                'libelle_nature' => Offre::NATURES[$o->nature],
                'niveau' => $o->niveau,
                'libelle_niveau' => Entite::TYPES[$o->niveau],
                'palier' => $o->palier,
                'libelle_palier' => Offre::PALIERS[$o->palier],
                'nom' => $o->nom,
                'argumentaire' => $o->argumentaire,
                'periode_mois' => $o->periode_mois,
                'prix_usd' => Montant::formater($o->prix_usd_centimes, 'USD'),
                'prix_cdf' => Montant::formater($o->prix_cdf_centimes, 'CDF'),
                'prix_usd_saisie' => $this->saisie($o->prix_usd_centimes, 'USD'),
                'prix_cdf_saisie' => $this->saisie($o->prix_cdf_centimes, 'CDF'),
                'paliers_taille' => array_map(fn (array $t) => [
                    'max' => $t['max'],
                    'prix_usd' => $this->saisie($t['prix_usd_centimes'], 'USD'),
                    'prix_cdf' => $this->saisie($t['prix_cdf_centimes'], 'CDF'),
                    'libelle' => ($t['max'] === null ? 'au-delà' : 'jusqu\'à '.$t['max'].' entités').' : '
                        .Montant::formater($t['prix_usd_centimes'], 'USD').' / '.Montant::formater($t['prix_cdf_centimes'], 'CDF'),
                ], $o->paliers_taille ?? []),
                'plafond_acces' => $o->plafond_acces,
                'libelle_plafond' => $o->plafond_acces ? Offre::PALIERS[$o->plafond_acces] : null,
                'modules' => $o->modules,
                'nombre_modules' => $o->modules === null ? null : count($o->modules),
                // La liste que déroule le clic sur le nombre : ce que l'offre ouvre VRAIMENT
                // (`clesOuvertes()`, la même que celle que la licence sert), rangée par espace —
                // jamais une seconde façon de la lire. Les écrans qui ne se vendent pas (réglages,
                // sécurité) sont toujours ouverts : ils ne comptent pas parmi les « modules ».
                'modules_inclus' => collect($o->clesOuvertes())
                    ->filter(fn (string $cle) => Modules::estVendable($cle))
                    ->groupBy(fn (string $cle) => Modules::toutes()[$cle]['espace'])
                    ->map(fn ($cles, $espace) => [
                        'espace' => Modules::libelleEspace((string) $espace),
                        'modules' => $cles->map(fn (string $cle) => Modules::libelle($cle))->values(),
                    ])->values(),
                'publique' => $o->publique,
                'ordre' => $o->ordre,
                'retiree' => $o->estRetiree(),
                'vendue' => $o->periodes_count > 0,
            ]),
            // Les modules VENDABLES par niveau : ce que le formulaire laisse cocher.
            'modules_par_niveau' => collect(Offre::ESPACES)->map(fn (array $espaces) => collect($espaces)->map(fn (string $espace) => [
                'espace' => $espace,
                'libelle' => Modules::libelleEspace($espace),
                'modules' => collect(Modules::toutes())
                    ->filter(fn (array $m) => $m['espace'] === $espace && $m['vendable'])
                    ->map(fn (array $m, string $cle) => ['cle' => $cle, 'libelle' => $m['libelle']])
                    ->values(),
            ])->values()),
            'paliers' => collect(Offre::PALIERS)->map(fn ($l, $v) => ['valeur' => $v, 'libelle' => $l])->values(),
        ]);
    }

    public function store(Request $requete): RedirectResponse
    {
        Offres::enregistrer(null, $this->valider($requete), $requete->user());

        return back()->with('succes', 'Offre créée.');
    }

    public function update(Request $requete, Offre $offre): RedirectResponse
    {
        Offres::enregistrer($offre, $this->valider($requete, $offre), $requete->user());

        return back()->with('succes', 'Offre enregistrée. Les périodes déjà vendues gardent leur prix.');
    }

    public function retirer(Request $requete, Offre $offre): RedirectResponse
    {
        Offres::retirer($offre, $requete->user());

        return back()->with('succes', 'Offre retirée de la vente. Ce qui court continue jusqu\'à son terme.');
    }

    public function retablir(Request $requete, Offre $offre): RedirectResponse
    {
        Offres::retablir($offre, $requete->user());

        return back()->with('succes', 'Offre remise en vente.');
    }

    /** @return array<string, mixed> */
    private function valider(Request $requete, ?Offre $offre = null): array
    {
        $prix = ['required', 'string', 'max:20', 'regex:/^\s*[0-9][0-9 \x{00A0}]*([.,][0-9]{1,2})?\s*$/u'];

        $donnees = $requete->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_]+$/', Rule::unique('offres', 'code')->ignore($offre?->id)],
            'nature' => ['required', Rule::in(array_keys(Offre::NATURES))],
            'niveau' => ['required', Rule::in(array_keys(Entite::TYPES))],
            'palier' => ['required', Rule::in(array_keys(Offre::PALIERS))],
            'nom' => ['required', 'string', 'max:120'],
            'argumentaire' => ['nullable', 'string', 'max:2000'],
            'periode_mois' => ['required', 'integer', 'min:1', 'max:36'],
            'prix_usd' => $prix,
            'prix_cdf' => $prix,
            'plafond_acces' => ['nullable', Rule::in(array_keys(Offre::PALIERS))],
            'paliers_taille' => ['nullable', 'array', 'max:10'],
            'paliers_taille.*.max' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'paliers_taille.*.prix_usd' => $prix,
            'paliers_taille.*.prix_cdf' => $prix,
            'tous_les_modules' => ['boolean'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', 'max:80'],
            'publique' => ['boolean'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'code.regex' => 'Le code s\'écrit en MAJUSCULES, chiffres et _ : il apparaîtra dans les licences.',
        ]);

        return [
            'code' => $donnees['code'],
            'nature' => $donnees['nature'],
            'niveau' => $donnees['niveau'],
            'palier' => $donnees['palier'],
            'nom' => $donnees['nom'],
            'argumentaire' => $donnees['argumentaire'] ?? null,
            'periode_mois' => (int) $donnees['periode_mois'],
            'prix_usd_centimes' => Montant::enCentimes($donnees['prix_usd'], 'USD'),
            'prix_cdf_centimes' => Montant::enCentimes($donnees['prix_cdf'], 'CDF'),
            'plafond_acces' => $donnees['plafond_acces'] ?? null,
            'paliers_taille' => array_map(fn (array $t) => [
                'max' => isset($t['max']) ? (int) $t['max'] : null,
                'prix_usd_centimes' => Montant::enCentimes($t['prix_usd'], 'USD'),
                'prix_cdf_centimes' => Montant::enCentimes($t['prix_cdf'], 'CDF'),
            ], $donnees['paliers_taille'] ?? []),
            // « Tous les modules » n'est pas une liste de tous les modules d'aujourd'hui : c'est
            // null, qui ouvre aussi ceux qu'une mise à jour du produit ajoutera.
            'modules' => ($donnees['tous_les_modules'] ?? false) ? null : ($donnees['modules'] ?? []),
            'publique' => (bool) ($donnees['publique'] ?? true),
            'ordre' => (int) ($donnees['ordre'] ?? 50),
        ];
    }

    private function saisie(int $centimes, string $devise): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format(Montant::enUnites($centimes, $devise), 2, '.', ''), '0'), '.'));
    }
}
