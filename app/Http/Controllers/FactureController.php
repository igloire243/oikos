<?php

namespace App\Http\Controllers;

use App\Models\EntreeJournal;
use App\Models\Facture;
use App\Models\Paiement;
use App\Support\EncaissementFacture;
use App\Support\Paiement\PasserellePaiement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * LES FACTURES ET LEUR ENCAISSEMENT.
 *
 * LA RÈGLE QUI COMMANDE TOUT LE RESTE : LA FACTURE DÉCIDE, PAS LE PAIEMENT
 * -------------------------------------------------------------------------
 * Un paiement ne solde rien par lui-même. On enregistre des versements, puis on demande à la
 * facture ce qu'il reste à devoir — et c'est seulement quand ce reste tombe à zéro qu'elle passe
 * à PAYÉE et que l'abonnement s'active. Cela règle sans effort les versements PARTIELS, fréquents
 * en mobile money où l'on paie parfois en deux fois faute de plafond suffisant.
 *
 * L'ORDRE DES ÉCRITURES N'EST PAS INDIFFÉRENT
 * ---------------------------------------------
 * L'argent d'abord, l'accès ensuite, et le rappel de l'installation en DERNIER — hors transaction.
 * Un client injoignable ne doit pas faire échouer l'enregistrement d'un paiement : l'argent est
 * reçu, la comptabilité doit le dire, et la réouverture se rattrapera à la synchronisation de la
 * nuit. Faire dépendre une écriture comptable de la disponibilité d'un tiers serait une faute.
 *
 * LA RÉFÉRENCE DE L'OPÉRATEUR EST UNIQUE EN BASE
 * ------------------------------------------------
 * `paiements` porte un index unique (fournisseur, référence). C'est ce qui empêche de saisir deux
 * fois le même versement M-Pesa — l'erreur la plus banale quand on encaisse à la main, et celle
 * qui fait offrir un mois gratuit sans que personne s'en aperçoive.
 */
class FactureController extends Controller
{
    public function index(Request $request)
    {
        $filtre = $request->query('etat', 'A_ENCAISSER');

        $requete = Facture::with(['client', 'abonnement.plan', 'abonnement.installation', 'paiements']);

        if ($filtre === 'A_ENCAISSER') {
            $requete->where('statut', Facture::EMISE);
        } elseif (in_array($filtre, [Facture::EMISE, Facture::PAYEE, Facture::ANNULEE, Facture::IRRECOUVRABLE], true)) {
            $requete->where('statut', $filtre);
        }

        $passerelle = app(PasserellePaiement::class);

        return view('factures.index', [
            'factures' => $requete->orderByDesc('created_at')->limit(200)->get(),
            'filtre' => $filtre,
            'aEncaisser' => Facture::where('statut', Facture::EMISE)->count(),
            'fournisseurs' => Paiement::FOURNISSEURS,

            // Encaissement en ligne (« Payer maintenant ») : n'apparaît que si un agrégateur est
            // configuré. Sinon, seul l'encaissement manuel ci-dessous reste.
            'encaissementEnLigne' => $passerelle->estActive(),
            'nomPasserelle' => $passerelle->nom(),
            'operateursEnLigne' => array_intersect_key(Paiement::FOURNISSEURS, array_flip(Paiement::ENCAISSABLES_EN_LIGNE)),
        ]);
    }

    public function enregistrerPaiement(Request $request, Facture $facture)
    {
        $donnees = $request->validate([
            'fournisseur' => ['required', Rule::in(array_keys(Paiement::FOURNISSEURS))],

            // La référence de l'opérateur : c'est elle qui permet de retrouver le versement chez
            // M-Pesa ou à la banque le jour où le client conteste.
            'reference' => [
                'required', 'string', 'max:120',
                Rule::unique('paiements', 'reference')->where('fournisseur', $request->input('fournisseur')),
            ],

            // Saisi en dollars, stocké en centimes. Comme les prix : un montant en flottant finit
            // par produire des totaux faux de quelques centimes.
            'montant' => ['required', 'numeric', 'min:0.01', 'max:100000'],

            'confirme' => ['nullable', 'boolean'],
        ], [
            'reference.unique' => 'Ce versement a déjà été enregistré : cette référence existe pour ce mode de paiement.',
        ], [
            'reference' => "référence de l'opérateur",
        ]);

        $paiement = Paiement::create([
            'facture_id' => $facture->facture_id,
            'fournisseur' => $donnees['fournisseur'],
            'reference' => trim($donnees['reference']),
            'montant' => (int) round(((float) $donnees['montant']) * 100),
            'devise' => $facture->devise,
            'statut' => $request->boolean('confirme') ? Paiement::CONFIRME : Paiement::EN_ATTENTE,
            'confirme_par_user_id' => $request->boolean('confirme') ? auth()->id() : null,
            'confirme_le' => $request->boolean('confirme') ? now() : null,
        ]);

        EntreeJournal::noter('PAIEMENT_ENREGISTRE', $facture, [
            'numero' => $facture->numero,
            'mode' => $donnees['fournisseur'],
            'montant_usd' => $paiement->montant / 100,
            'confirme' => $paiement->statut === Paiement::CONFIRME,
        ]);

        if ($paiement->statut !== Paiement::CONFIRME) {
            return back()->with('ok', 'Versement enregistré, en attente de confirmation.');
        }

        return back()->with('ok', EncaissementFacture::traiter($facture)['message']);
    }

    public function confirmer(Paiement $paiement)
    {
        if ($paiement->statut === Paiement::CONFIRME) {
            return back()->with('avertissement', 'Ce versement est déjà confirmé.');
        }

        $paiement->update([
            'statut' => Paiement::CONFIRME,
            'confirme_par_user_id' => auth()->id(),
            'confirme_le' => now(),
        ]);

        EntreeJournal::noter('PAIEMENT_CONFIRME', $paiement->facture, [
            'reference' => $paiement->reference,
            'montant_usd' => $paiement->montant / 100,
        ]);

        return back()->with('ok', EncaissementFacture::traiter($paiement->facture)['message']);
    }

    /**
     * Rejeter un versement annoncé mais jamais reçu.
     *
     * On ne le SUPPRIME pas : « ce client a annoncé un paiement le 12 qui n'est jamais arrivé » est
     * une information qui sert le jour où il affirme le contraire. Effacer, c'est perdre l'argument.
     */
    public function rejeter(Paiement $paiement)
    {
        $paiement->update(['statut' => Paiement::ECHOUE]);

        EntreeJournal::noter('PAIEMENT_REJETE', $paiement->facture, ['reference' => $paiement->reference]);

        return back()->with('ok', 'Versement marqué comme non reçu.');
    }
}
