<?php

namespace App\Http\Controllers\Vitrine;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\Entite;
use Illuminate\Http\Request;

/**
 * LA SEULE ÉCRITURE QUE LE SITE PUBLIC AUTORISE.
 *
 * Une application entièrement en lecture n'a presque pas de surface d'attaque. Dès qu'un
 * inconnu peut écrire une ligne, elle en a une. Quatre précautions, dans l'ordre où elles agissent :
 *
 * 1. LE PIÈGE À ROBOTS. Un champ nommé `site_web`, caché par le style, qu'aucun humain ne voit
 *    donc ne remplit. Les robots remplissent tout ce qu'ils trouvent. S'il est rempli, on répond
 *    « merci » sans rien enregistrer : un robot à qui l'on affiche une erreur réessaie en
 *    corrigeant ; un robot à qui l'on dit merci s'en va.
 *
 * 2. LE PLAFOND. `throttle:5,1` sur la route. Sans lui, ce formulaire devient un moyen de remplir
 *    votre base de milliers de lignes en une nuit.
 *
 * 3. LA VALIDATION AVANT L'ÉCRITURE. Longueurs bornées, e-mail vérifié, niveau contraint à la
 *    liste réelle. Un champ `niveau` libre laisserait entrer n'importe quelle chaîne dans une
 *    colonne dont l'affichage suppose une valeur connue.
 *
 * 4. LE CHOIX DES CHAMPS ENREGISTRÉS. `$fillable` de Demande exclut `statut`, `note_interne` et
 *    `traite_le`. Un champ caché ajouté au formulaire ne peut donc pas faire naître une demande
 *    déjà marquée « traitée », c'est-à-dire déjà invisible dans votre boîte.
 *
 * Ce qui n'est PAS fait ici, volontairement : envoyer un e-mail avec le contenu saisi. La demande
 * est enregistrée, vous la lisez dans la console. Un e-mail dont un inconnu compose le corps est
 * une manière classique de faire relayer autre chose par votre serveur.
 */
class ContactController extends Controller
{
    public function formulaire()
    {
        return view('vitrine.contact', [
            'niveaux' => Entite::TYPES,
        ]);
    }

    public function envoyer(Request $request)
    {
        // Le piège, examiné AVANT la validation : inutile de valider ce qu'on va jeter.
        if (filled($request->input('site_web'))) {
            return redirect()->route('vitrine.contact')
                ->with('ok', 'Merci, votre message a bien été transmis.');
        }

        $donnees = $request->validate([
            'nom' => ['required', 'string', 'min:2', 'max:190'],
            'organisation' => ['nullable', 'string', 'max:190'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'telephone' => ['nullable', 'string', 'max:40'],
            'ville' => ['nullable', 'string', 'max:120'],
            'niveau' => ['nullable', 'string', 'in:'.implode(',', array_keys(Entite::TYPES))],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
        ], [], [
            'nom' => 'nom',
            'email' => 'adresse e-mail',
            'message' => 'message',
        ]);

        Demande::create($donnees + [
            'ip' => $request->ip(),
            // Tronqué à la largeur de la colonne : certains navigateurs annoncent des chaînes
            // très longues, et une insertion refusée pour dépassement perdrait la demande.
            'agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        return redirect()->route('vitrine.contact')
            ->with('ok', 'Merci, votre message a bien été transmis. Nous revenons vers vous rapidement.');
    }
}
