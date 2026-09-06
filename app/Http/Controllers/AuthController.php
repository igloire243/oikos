<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function formulaire()
    {
        return view('connexion');
    }

    public function connexion(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Cette console commande les accès de TOUS vos clients : c'est la cible la plus
        // intéressante de votre infrastructure. Cinq essais par minute et par adresse.
        $cle = 'connexion:'.$request->ip();

        if (RateLimiter::tooManyAttempts($cle, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Trop de tentatives. Réessayez dans '
                    .RateLimiter::availableIn($cle).' secondes.',
            ]);
        }

        if (! Auth::attempt($data, $request->boolean('memoriser'))) {
            RateLimiter::hit($cle, 300);

            // Un message unique : dire « cette adresse n'existe pas » révélerait quelles adresses
            // ont un compte.
            throw ValidationException::withMessages([
                'email' => 'Identifiants incorrects.',
            ]);
        }

        RateLimiter::clear($cle);
        $request->session()->regenerate();

        return redirect()->intended(route('tableau-bord'));
    }

    public function deconnexion(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('connexion');
    }
}
