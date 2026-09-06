@extends('layout')
@section('titre', 'Nouveau mot de passe')

@php
    $champ = 'w-full pl-9 pr-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm
              focus:outline-none focus:border-emerald-600 focus:bg-white focus:ring-2 focus:ring-emerald-600/15 transition';
@endphp

@section('contenu')
    <div class="max-w-sm mx-auto mt-10 sm:mt-16">
        <div class="text-center mb-6">
            <span class="w-14 h-14 rounded-2xl bg-emerald-600 text-white inline-flex items-center justify-center shadow-lg shadow-emerald-600/25">
                <i data-lucide="lock-keyhole" class="w-7 h-7"></i>
            </span>
            <h1 class="text-xl font-bold mt-3">Nouveau mot de passe</h1>
            <p class="text-[13px] text-slate-500 mt-0.5">Dix caractères au minimum, avec des lettres et des chiffres.</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
            <form method="POST" action="{{ route('mot-de-passe.enregistrer') }}" class="space-y-4">
                @csrf

                {{-- Le jeton voyage avec le formulaire : c'est LUI qui prouve que la demande vient
                     bien du lien reçu par e-mail, et pas de quelqu'un qui a simplement deviné une
                     adresse. Le courtier vérifie qu'il correspond à cette adresse-là. --}}
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="email" class="block text-[11.5px] font-bold text-slate-600 mb-1.5">Adresse e-mail</label>
                    <div class="relative">
                        <i data-lucide="mail" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        {{-- En lecture seule : elle vient du lien. La modifier ne donnerait accès à
                             rien — le jeton ne vaut que pour l'adresse à laquelle il a été émis —
                             mais on éviterait un échec incompréhensible dû à une faute de frappe. --}}
                        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required readonly
                               autocomplete="username" class="{{ $champ }} text-slate-500 cursor-not-allowed">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-[11.5px] font-bold text-slate-600 mb-1.5">Nouveau mot de passe</label>
                    <div class="relative">
                        <i data-lucide="lock" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input id="password" type="password" name="password" required autofocus
                               autocomplete="new-password" minlength="10" class="{{ $champ }}">
                    </div>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-[11.5px] font-bold text-slate-600 mb-1.5">Confirmez le mot de passe</label>
                    <div class="relative">
                        <i data-lucide="lock" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input id="password_confirmation" type="password" name="password_confirmation" required
                               autocomplete="new-password" minlength="10" class="{{ $champ }}">
                    </div>
                </div>

                <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700
                               text-white text-sm font-bold transition cursor-pointer shadow-sm shadow-emerald-600/25">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    Changer le mot de passe
                </button>
            </form>
        </div>

        <p class="text-center text-[11.5px] text-slate-400 mt-4">
            Les sessions « rester connecté » ouvertes ailleurs seront fermées.
        </p>
    </div>
@endsection
