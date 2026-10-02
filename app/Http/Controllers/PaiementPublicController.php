<?php

namespace App\Http\Controllers;

use App\Metier\Commerce\Montant;
use App\Metier\Commerce\PaiementsEnLigne;
use App\Metier\Commerce\Passerelles\PasserelleSimulee;
use App\Models\DemandePaiement;
use App\Models\Facture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LA PAGE QUE LE CLIENT OUVRE POUR PAYER — publique, sans compte : celui qui paie n'est pas forcément
 * celui qui administre. L'adresse porte un jeton tiré au hasard (jamais le numéro de facture, qui se
 * devine) et ne montre que ce qu'il faut pour payer : numéro, offre, montant, reste dû.
 *
 * Éteint (`PAIEMENT_EN_LIGNE` faux) : tout répond 404, comme si la page n'existait pas.
 */
class PaiementPublicController extends Controller
{
    public function afficher(string $jeton): Response
    {
        $facture = $this->facture($jeton);

        return Inertia::render('Public/Paiement/Afficher', [
            'facture' => [
                'numero' => $facture->numero,
                'offre' => $facture->periode->offre->nom,
                'entite' => $facture->periode->abonnement->entite->nom,
                'montant' => $facture->montant(),
                'restant' => Montant::formater($facture->restantCentimes(), $facture->devise),
                'solde' => $facture->restantCentimes() === 0,
                'echeance' => $facture->echeance_le->translatedFormat('l j F Y'),
            ],
            'jeton' => $jeton,
        ]);
    }

    public function demarrer(string $jeton): \Symfony\Component\HttpFoundation\Response
    {
        $url = PaiementsEnLigne::initier($this->facture($jeton));

        // Une adresse d'un autre domaine : une visite Inertia ne sait pas la suivre.
        return Inertia::location($url);
    }

    /** Le retour du client : on interroge le fournisseur, on n'écoute pas le navigateur. */
    public function retour(string $reference): Response
    {
        $demande = PaiementsEnLigne::confirmer($this->demande($reference));

        return Inertia::render('Public/Paiement/Retour', [
            'statut' => $demande->statut,
            'numero' => $demande->facture->numero,
            'montant' => Montant::formater($demande->montant_centimes, $demande->devise),
            'motif' => $demande->statut === DemandePaiement::ECHOUEE ? $demande->motif : null,
            'jeton' => $demande->facture->jeton_paiement,
        ]);
    }

    /** Le serveur du fournisseur nous prévient : même vérification que le retour, sans page. */
    public function notification(Request $requete, string $passerelle): \Illuminate\Http\Response
    {
        abort_unless(PaiementsEnLigne::actif(), 404);

        $reference = (string) $requete->input('reference', '');
        $demande = DemandePaiement::query()->where('reference', $reference)->where('passerelle', $passerelle)->first();
        if ($demande !== null) {
            PaiementsEnLigne::confirmer($demande);
        }

        return response('ok');
    }

    /** La fausse page du fournisseur : disponible seulement hors production. */
    public function simulation(string $reference): Response
    {
        $demande = $this->demandeSimulee($reference);

        return Inertia::render('Public/Paiement/Simulation', [
            'reference' => $demande->reference,
            'montant' => Montant::formater($demande->montant_centimes, $demande->devise),
            'numero' => $demande->facture->numero,
        ]);
    }

    public function simuler(Request $requete, string $reference): RedirectResponse
    {
        $demande = $this->demandeSimulee($reference);
        PasserelleSimulee::decider($demande->reference, $requete->boolean('paye'));

        return redirect()->route('paiement.retour', $demande->reference);
    }

    private function facture(string $jeton): Facture
    {
        abort_unless(PaiementsEnLigne::actif(), 404);

        return Facture::query()->with(['periode.offre', 'periode.abonnement.entite'])->where('jeton_paiement', $jeton)->firstOrFail();
    }

    private function demande(string $reference): DemandePaiement
    {
        abort_unless(PaiementsEnLigne::actif(), 404);

        return DemandePaiement::query()->with('facture')->where('reference', $reference)->firstOrFail();
    }

    private function demandeSimulee(string $reference): DemandePaiement
    {
        abort_if(app()->isProduction(), 404);
        $demande = $this->demande($reference);
        abort_unless($demande->passerelle === 'simulee', 404);

        return $demande;
    }
}
