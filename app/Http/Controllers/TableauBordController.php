<?php

namespace App\Http\Controllers;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\Facture;
use App\Models\Installation;

class TableauBordController extends Controller
{
    public function index()
    {
        return view('tableau-bord', [
            // Ce qui compte au quotidien, pas ce qui fait joli : combien de clients, combien
            // d'argent en attente, et surtout QUI a un problème en ce moment.
            'clients_actifs' => Client::where('statut', Client::STATUT_ACTIF)->count(),
            'clients_total' => Client::count(),
            'installations' => Installation::where('active', true)->count(),

            'a_echeance' => Abonnement::whereIn('statut', [Abonnement::ACTIF, Abonnement::ESSAI])
                ->whereNotNull('periode_fin')
                ->where('periode_fin', '<=', now()->addDays(15))
                ->with(['installation.client', 'plan'])
                ->orderBy('periode_fin')
                ->get(),

            'impayes' => Abonnement::whereIn('statut', [Abonnement::IMPAYE, Abonnement::SUSPENDU])
                ->with(['installation.client', 'plan'])
                ->orderBy('periode_fin')
                ->get(),

            'factures_dues' => Facture::where('statut', Facture::EMISE)
                ->with('client')
                ->orderBy('du_le')
                ->get(),

            // Une installation qui ne se manifeste plus a soit perdu son cron, soit son
            // hébergement, soit son client. Dans les trois cas, il faut le savoir AVANT le client.
            'muettes' => Installation::where('active', true)
                ->where(fn ($q) => $q->whereNull('vue_le')->orWhere('vue_le', '<', now()->subDays(3)))
                ->with('client')
                ->get(),
        ]);
    }
}
