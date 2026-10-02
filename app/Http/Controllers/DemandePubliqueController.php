<?php

namespace App\Http\Controllers;

use App\Models\DemandeContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LE FORMULAIRE DU SITE COMMERCIAL — la seule porte publique de la console, avec l'API machine.
 *
 * Tout arrive d'Internet : chaque champ est borné, la route est freinée (cinq envois par minute), et
 * un champ piège (`site_web`, invisible pour une personne) renvoie un faux succès aux robots plutôt
 * qu'une erreur qui leur apprendrait quoi contourner. Une demande s'ENREGISTRE toujours ; aucun envoi
 * d'e-mail ne conditionne son existence — elle se lit dans « Demandes de contact ».
 */
class DemandePubliqueController extends Controller
{
    public function formulaire(): Response
    {
        return Inertia::render('Public/Demande');
    }

    public function envoyer(Request $requete): RedirectResponse
    {
        $donnees = $requete->validate([
            'nom' => ['required', 'string', 'max:150'],
            'organisation' => ['nullable', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190'],
            'telephone' => ['nullable', 'string', 'max:40'],
            'pays' => ['nullable', 'string', 'max:100'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ], [
            'message.min' => 'Dites-nous en quelques mots ce que vous cherchez.',
        ]);

        if ($requete->filled('site_web')) {
            return back()->with('succes', 'Merci : votre demande est bien arrivée.');
        }

        DemandeContact::query()->create($donnees);

        return back()->with('succes', 'Merci : votre demande est bien arrivée. Nous vous répondons rapidement.');
    }
}
