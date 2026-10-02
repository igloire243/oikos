<?php

namespace App\Http\Controllers;

use App\Metier\Clients\Installations;
use App\Metier\Licence\Cles;
use App\Metier\Licence\Rappel;
use App\Models\CleActivation;
use App\Models\Client;
use App\Models\Installation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * LES GESTES SUR UNE INSTALLATION — tous depuis la fiche du client.
 */
class InstallationsController extends Controller
{
    public function store(Request $requete, Client $client): RedirectResponse
    {
        $donnees = $requete->validate([
            // Vide, c'est bien : l'installation le remplira avec le nom exact de la communauté.
            'nom' => ['nullable', 'string', 'max:190'],
            // L'adresse permet de la RAPPELER après un paiement ; elle se corrige aussi à chaque
            // synchronisation.
            'url' => ['nullable', 'url', 'max:255'],
        ]);

        Installations::ajouter($client, $donnees, $requete->user());

        return back()->with('succes', 'Installation ajoutée. Émettez maintenant une clé d\'activation.');
    }

    public function update(Request $requete, Installation $installation): RedirectResponse
    {
        $installation->update($requete->validate([
            'nom' => ['nullable', 'string', 'max:190'],
            'url' => ['nullable', 'url', 'max:255'],
        ]));

        return back()->with('succes', 'Installation enregistrée.');
    }

    public function activation(Request $requete, Installation $installation): RedirectResponse
    {
        $requete->boolean('active')
            ? Installations::reactiver($installation, $requete->user())
            : Installations::desactiver($installation, $requete->user());

        return back()->with('succes', $installation->estDesactivee()
            ? 'Installation désactivée : elle ne peut plus ni s\'activer ni se synchroniser.'
            : 'Installation réactivée.');
    }

    public function remettreALEssai(Request $requete, Installation $installation): RedirectResponse
    {
        $donnees = $requete->validate(['motif' => ['required', 'string', 'min:3', 'max:255']]);

        Installations::remettreALEssai($installation, $donnees['motif'], $requete->user());

        return back()->with('succes', 'Installation remise à l\'essai : tout est ouvert dès sa prochaine synchronisation, pour la durée d\'essai des réglages.');
    }

    public function emettreCle(Request $requete, Installation $installation): RedirectResponse
    {
        $donnees = $requete->validate(['note' => ['nullable', 'string', 'max:190']]);

        $emise = Cles::emettre($installation, $requete->user(), $donnees['note'] ?? null);

        // Par la session, jamais par l'URL : une URL se retrouve dans l'historique du navigateur
        // et les journaux du serveur, et la clé serait lisible par qui les ouvre.
        return back()->with('cle_emise', ['installation_id' => $installation->id, 'code' => $emise['code']]);
    }

    public function revoquerCle(Request $requete, CleActivation $cle): RedirectResponse
    {
        Cles::revoquer($cle, $requete->user());

        return back()->with('succes', 'Clé révoquée : elle ne pourra plus servir.');
    }

    public function rappeler(Installation $installation): RedirectResponse
    {
        return back()->with(...(Rappel::prevenir($installation)
            ? ['succes', 'L\'installation a été prévenue : elle se resynchronise.']
            : ['avertissement', 'L\'installation n\'a pas répondu. Elle se synchronisera la nuit prochaine, ou depuis son bouton « Actualiser ».']));
    }
}
