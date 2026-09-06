<?php

namespace App\Http\Controllers;

use App\Models\Abonnement;
use App\Models\Entite;
use App\Models\EntreeJournal;
use App\Models\Facture;
use App\Models\Installation;
use App\Models\Plan;
use App\Support\Cascade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * VENDRE, RENOUVELER, SUSPENDRE UN ABONNEMENT.
 *
 * LA VENTE ET LA FACTURE NAISSENT ENSEMBLE, DANS UNE TRANSACTION
 * ---------------------------------------------------------------
 * Un abonnement sans facture est un service rendu que rien ne réclame ; une facture sans
 * abonnement est une somme demandée qui n'ouvre rien. Les deux écritures réussissent, ou aucune.
 *
 * LA FACTURE EST ÉMISE AVANT LE PAIEMENT, ET C'EST TOUT L'INTÉRÊT
 * ----------------------------------------------------------------
 * Son numéro est la référence que le client citera en payant par mobile money. Sans référence, un
 * versement reçu ne se rattache à personne : il faut retrouver son auteur, et cela prend des jours.
 * Émettre après coup reviendrait à renoncer à ce numéro au moment précis où il sert.
 *
 * POURQUOI L'ACCÈS S'OUVRE AVANT D'ÊTRE PAYÉ (par défaut)
 * ---------------------------------------------------------
 * L'abonnement naît IMPAYÉ, ce qui — dans ce modèle — ouvre l'écriture pendant le délai de grâce.
 * Un client qui vient de s'engager doit pouvoir travailler pendant qu'il organise son virement ;
 * exiger l'argent avant d'ouvrir transforme chaque vente en semaine d'attente. La case peut être
 * décochée pour un client dont on préfère attendre le paiement.
 */
class AbonnementController extends Controller
{
    public function creer(Installation $installation, Entite $entite)
    {
        return view('abonnements.creer', $this->contexte($installation, $entite));
    }

    public function enregistrer(Request $request, Installation $installation, Entite $entite)
    {
        $donnees = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'plan_id')],
            'debut' => ['required', 'date'],
            'payeur' => ['nullable', 'string', 'max:40'],
            'ouvrir' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $plan = Plan::findOrFail($donnees['plan_id']);

        if ($existant = Cascade::abonnementExistant($installation, $entite)) {
            return back()->with('avertissement',
                "« {$entite->nom} » a déjà un abonnement. Renouvelez-le plutôt que d'en créer un "
                .'second : la base n\'en accepte qu\'un par entité, et deux lignes concurrentes ne '
                .'diraient plus laquelle détermine l\'accès.');
        }

        if ($empechement = Cascade::empechement($installation, $entite, $plan)) {
            return back()->withInput()->withErrors(['plan_id' => $empechement]);
        }

        $prix = Cascade::prix($installation, $plan);
        $debut = \Carbon\Carbon::parse($donnees['debut'])->startOfDay();
        $fin = $debut->copy()->addMonths((int) $plan->periode_mois);

        $abonnement = DB::transaction(function () use ($installation, $entite, $plan, $donnees, $prix, $debut, $fin, $request) {
            $abonnement = Abonnement::create([
                'installation_id' => $installation->installation_id,
                'plan_id' => $plan->plan_id,

                // Le bénéficiaire est désigné comme l'installation le désigne — type + référence —
                // et non par une clé étrangère : une resynchronisation peut recréer les lignes
                // d'entités, l'abonnement ne doit jamais perdre son bénéficiaire.
                'beneficiaire_type' => $entite->type,
                'beneficiaire_ref' => $entite->ref,

                'payeur_type' => $donnees['payeur'] ? explode(':', $donnees['payeur'])[0] : null,
                'payeur_ref' => $donnees['payeur'] ? (int) explode(':', $donnees['payeur'])[1] : null,

                'statut' => $request->boolean('ouvrir') ? Abonnement::IMPAYE : Abonnement::SUSPENDU,
                'periode_debut' => $debut,
                'periode_fin' => $fin,
                'grace_fin' => $fin->copy()->addDays(\App\Models\Reglage::entier('grace_jours', 14)),
            ]);

            Facture::create([
                'client_id' => $installation->client_id,
                'abonnement_id' => $abonnement->abonnement_id,
                'numero' => Facture::prochainNumero(),
                'montant' => $prix['usd_cents'],
                'devise' => 'USD',
                'statut' => Facture::EMISE,
                'du_le' => now()->addDays(15),
                'note' => $donnees['note'] ?? null,
            ]);

            return $abonnement;
        });

        EntreeJournal::noter('ABONNEMENT_VENDU', $installation, [
            'entite' => $entite->nom,
            'offre' => $plan->nom,
            'montant_usd' => $prix['usd_cents'] / 100,
        ]);

        return redirect()->route('clients.fiche', $installation->client_id)
            ->with('ok', "Abonnement « {$plan->nom} » créé pour « {$entite->nom} », "
                .'et la facture émise. Communiquez son numéro au client : c\'est lui qui rattachera '
                .'son versement.');
    }

    /**
     * Renouveler : la nouvelle période part de la FIN de l'ancienne, pas d'aujourd'hui.
     *
     * Un client qui règle trois jours en avance ne doit pas perdre ces trois jours ; un client qui
     * règle avec deux semaines de retard ne doit pas s'en voir offrir deux — sauf si l'ancienne
     * période est si loin derrière qu'on repart de zéro, ce que la comparaison ci-dessous fait.
     */
    public function renouveler(Request $request, Abonnement $abonnement)
    {
        $plan = $abonnement->plan;

        if (! $plan) {
            return back()->with('avertissement', "Cet abonnement n'a plus d'offre rattachée.");
        }

        $installation = $abonnement->installation;
        $prix = Cascade::prix($installation, $plan);

        $depart = $abonnement->periode_fin && $abonnement->periode_fin->isFuture()
            ? $abonnement->periode_fin->copy()
            : now()->startOfDay();

        $fin = $depart->copy()->addMonths((int) $plan->periode_mois);

        DB::transaction(function () use ($abonnement, $installation, $plan, $prix, $depart, $fin) {
            $abonnement->update([
                'statut' => Abonnement::IMPAYE,
                'periode_debut' => $depart,
                'periode_fin' => $fin,
                'grace_fin' => $fin->copy()->addDays(\App\Models\Reglage::entier('grace_jours', 14)),
            ]);

            Facture::create([
                'client_id' => $installation->client_id,
                'abonnement_id' => $abonnement->abonnement_id,
                'numero' => Facture::prochainNumero(),
                'montant' => $prix['usd_cents'],
                'devise' => 'USD',
                'statut' => Facture::EMISE,
                'du_le' => now()->addDays(15),
            ]);
        });

        EntreeJournal::noter('ABONNEMENT_RENOUVELE', $installation, [
            'offre' => $plan->nom,
            'jusquau' => $fin->toDateString(),
        ]);

        return back()->with('ok', 'Renouvelé jusqu\'au '.$fin->format('d/m/Y').'. Une facture a été émise.');
    }

    public function changerStatut(Request $request, Abonnement $abonnement)
    {
        $donnees = $request->validate([
            'statut' => ['required', Rule::in(array_keys(Abonnement::STATUTS))],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        $abonnement->update([
            'statut' => $donnees['statut'],
            'resilie_le' => $donnees['statut'] === Abonnement::RESILIE ? now() : null,
            'motif_resiliation' => $donnees['statut'] === Abonnement::RESILIE ? ($donnees['motif'] ?? null) : null,
        ]);

        EntreeJournal::noter('ABONNEMENT_STATUT', $abonnement->installation, [
            'statut' => $donnees['statut'],
            'motif' => $donnees['motif'] ?? null,
        ]);

        return back()->with('ok', 'Abonnement mis à jour. '
            .'L\'installation le verra à sa prochaine synchronisation, ou immédiatement si le client '
            .'clique « Actualiser » sur son écran d\'activation.');
    }

    private function contexte(Installation $installation, Entite $entite): array
    {
        // Les offres proposables : celles que la cascade autorise ICI, avec leur prix calculé pour
        // CETTE installation. Présenter les autres reviendrait à laisser vendre ce qui sera refusé.
        $offres = Plan::orderBy('ordre')->orderBy('prix_usd_cents')->get()
            ->map(function (Plan $plan) use ($installation, $entite) {
                return [
                    'plan' => $plan,
                    'empechement' => Cascade::empechement($installation, $entite, $plan),
                    'prix' => Cascade::prix($installation, $plan),
                ];
            });

        return [
            'installation' => $installation,
            'entite' => $entite,
            'offres' => $offres,
            'licence' => Cascade::licence($installation),

            // Qui peut payer : l'entité elle-même, ou n'importe laquelle de ses ancêtres. C'est ce
            // qui permet à une vision de régler pour ses douze églises.
            'payeurs' => $installation->entites()->orderBy('type')->orderBy('nom')->get(),
        ];
    }
}
