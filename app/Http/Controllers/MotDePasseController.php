<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as RegleMotDePasse;

/**
 * Le « mot de passe oublié » de la console.
 *
 * LE SEUL CHEMIN QUI CONTOURNE LA CONNEXION
 * ------------------------------------------
 * Tout le reste de cette application est derrière `auth`. Ces quatre routes ne le sont pas — c'est
 * leur raison d'être — et elles suffisent donc à prendre le contrôle de la console si elles sont
 * mal faites. Trois précautions, qui sont l'essentiel du fichier :
 *
 * 1. LA RÉPONSE EST TOUJOURS LA MÊME. Qu'une adresse ait un compte ou non, `envoyer()` renvoie le
 *    même message. Répondre « adresse inconnue » transformerait ce formulaire en annuaire : on y
 *    essaierait des adresses jusqu'à trouver la vôtre, et la moitié du travail d'une intrusion
 *    serait faite. C'est la même règle que le message unique d'AuthController.
 *
 * 2. LE JETON N'EST PAS LE MOT DE PASSE. `Password::reset()` vérifie que le jeton correspond à
 *    l'adresse, qu'il n'a pas expiré, et le SUPPRIME en cas de succès : un lien intercepté après
 *    coup ne rejoue pas. C'est Laravel qui fait ce travail — d'où l'usage du courtier plutôt qu'une
 *    vérification écrite à la main, qui oublierait l'un des trois.
 *
 * 3. LE CHANGEMENT CHASSE LES AUTRES SESSIONS. On réécrit `remember_token` : les cookies « rester
 *    connecté » posés ailleurs cessent d'ouvrir. Si vous réinitialisez PARCE QUE quelqu'un est
 *    entré, le laisser connecté rendrait l'opération inutile.
 *
 * Et volontairement : aucune connexion automatique après le changement. On repasse par l'écran de
 * connexion — c'est la preuve immédiate que le nouveau mot de passe fonctionne, avant de fermer
 * l'onglet en croyant l'affaire réglée.
 */
class MotDePasseController extends Controller
{
    public function demander()
    {
        return view('mot-de-passe.oublie');
    }

    public function envoyer(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // Le résultat n'est délibérément pas examiné : succès, adresse inconnue ou demande trop
        // rapprochée donnent le même message. Voir le point 1 ci-dessus.
        Password::sendResetLink($request->only('email'));

        return back()->with('ok', "Si cette adresse correspond à un compte, un lien de réinitialisation vient de partir. Il expire dans une heure.");
    }

    public function formulaire(Request $request, string $token)
    {
        return view('mot-de-passe.reinitialiser', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function enregistrer(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            // Dix caractères, des lettres ET des chiffres. Ce mot de passe ouvre la facturation de
            // tous vos clients et les clés de leurs installations : il n'a pas la même valeur qu'un
            // mot de passe de forum.
            'password' => ['required', 'confirmed', RegleMotDePasse::min(10)->letters()->numbers()],
        ], [], [
            'password' => 'mot de passe',
        ]);

        $resultat = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($utilisateur) use ($request) {
                $utilisateur->forceFill([
                    'password' => Hash::make($request->input('password')),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($utilisateur));
            }
        );

        if ($resultat !== Password::PASSWORD_RESET) {
            // Lien expiré, déjà utilisé, ou adresse qui ne correspond pas au jeton : un seul
            // message, pour la raison du point 1.
            return back()->withErrors([
                'email' => "Ce lien n'est plus valable. Demandez-en un nouveau.",
            ]);
        }

        return redirect()->route('connexion')
            ->with('ok', 'Mot de passe changé. Vous pouvez vous connecter.');
    }
}
