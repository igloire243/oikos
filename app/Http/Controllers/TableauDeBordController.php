<?php

namespace App\Http\Controllers;

use App\Metier\Catalogue\Modules;
use App\Metier\Console\Reglages;
use App\Metier\Notifications\Alertes;
use App\Models\Client;
use App\Models\DemandeContact;
use App\Models\Installation;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * L'ACCUEIL DE LA CONSOLE — d'abord ce qui attend quelqu'un, ensuite où l'on en est.
 *
 * Un chiffre dit où l'on en est, il ne dit pas quoi faire. La liste « À traiter » reprend EXACTEMENT
 * les calculs que lit la notification du matin (`Alertes`) : le tableau de bord ne dit jamais autre
 * chose que ce que le téléphone a sonné. Une ligne à zéro n'apparaît pas — une liste vide dit
 * « tout est à jour » en clair.
 */
class TableauDeBordController extends Controller
{
    public function __invoke(): Response
    {
        $retards = Alertes::facturesEnRetard(Carbon::today());
        $echeances = Alertes::abonnementsQuiSAchevent(Carbon::today());
        $demandes = DemandeContact::query()->whereNull('traitee_le')->count();

        // Une installation qui ne s'est plus synchronisée depuis le silence toléré est périmée
        // d'elle-même : c'est le client qui perd l'accès, et il faut le savoir avant qu'il appelle.
        $silence = Reglages::valeur('silence_jours');
        $silencieuses = Installation::query()
            ->actives()
            ->whereNotNull('activee_le')
            ->where(fn ($q) => $q->whereNull('vue_le')->orWhere('vue_le', '<', Carbon::now()->subDays($silence)))
            ->count();

        $aTraiter = collect([
            ['cle' => 'demandes', 'compte' => $demandes, 'libelle' => $demandes > 1 ? 'demandes de contact attendent une réponse' : 'demande de contact attend une réponse', 'route' => route('console.demandes.index')],
            ['cle' => 'retards', 'compte' => $retards, 'libelle' => $retards > 1 ? 'factures ont dépassé leur échéance' : 'facture a dépassé son échéance', 'route' => route('console.factures.index', ['etat' => 'retard'])],
            ['cle' => 'echeances', 'compte' => $echeances, 'libelle' => $echeances > 1 ? "abonnements s'achèvent sous ".Alertes::PREAVIS_JOURS.' jours' : "abonnement s'achève sous ".Alertes::PREAVIS_JOURS.' jours', 'route' => route('console.clients.index')],
            ['cle' => 'silencieuses', 'compte' => $silencieuses, 'libelle' => $silencieuses > 1 ? "installations ne se sont plus synchronisées depuis {$silence} jours" : "installation ne s'est plus synchronisée depuis {$silence} jours", 'route' => route('console.clients.index')],
        ])->filter(fn (array $ligne) => $ligne['compte'] > 0)->values();

        return Inertia::render('Console/Accueil', [
            'a_traiter' => $aTraiter,
            'chiffres' => [
                'clients' => Client::query()->count(),
                'installations' => Installation::query()->actives()->count(),
                'a_traiter' => $aTraiter->sum('compte'),
            ],
            'catalogue' => [
                'espaces' => count(Modules::espaces()),
                'modules' => count(Modules::toutes()),
                'vendables' => count(Modules::vendables()),
                'empreinte' => substr(Modules::empreinte(), 0, 8),
            ],
        ]);
    }
}
