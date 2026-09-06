@extends('layout')
@section('titre', 'Mot de passe oublié')

@php
    $champ = 'w-full pl-9 pr-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm
              focus:outline-none focus:border-emerald-600 focus:bg-white focus:ring-2 focus:ring-emerald-600/15 transition';
@endphp

@section('contenu')
    <div class="max-w-sm mx-auto mt-10 sm:mt-16">
        <div class="text-center mb-6">
            <span class="w-14 h-14 rounded-2xl bg-emerald-600 text-white inline-flex items-center justify-center shadow-lg shadow-emerald-600/25">
                <i data-lucide="key-round" class="w-7 h-7"></i>
            </span>
            <h1 class="text-xl font-bold mt-3">Mot de passe oublié</h1>
            <p class="text-[13px] text-slate-500 mt-0.5">Indiquez l'adresse de votre compte, un lien vous sera envoyé.</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
            <form method="POST" action="{{ route('mot-de-passe.lien') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-[11.5px] font-bold text-slate-600 mb-1.5">Adresse e-mail</label>
                    <div class="relative">
                        <i data-lucide="mail" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                               autocomplete="username" class="{{ $champ }}">
                    </div>
                </div>

                <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700
                               text-white text-sm font-bold transition cursor-pointer shadow-sm shadow-emerald-600/25">
                    <i data-lucide="send" class="w-4 h-4"></i>
                    Envoyer le lien
                </button>
            </form>
        </div>

        <p class="text-center mt-4">
            <a href="{{ route('connexion') }}" class="inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-slate-500 hover:text-slate-800 transition">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Revenir à la connexion
            </a>
        </p>
    </div>
@endsection
