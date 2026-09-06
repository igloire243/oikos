@extends('layout')
@section('titre', 'Connexion')

@section('contenu')
    <div class="max-w-sm mx-auto mt-10 sm:mt-16">
        <div class="text-center mb-6">
            <span class="w-14 h-14 rounded-2xl bg-emerald-600 text-white inline-flex items-center justify-center shadow-lg shadow-emerald-600/25">
                <i data-lucide="church" class="w-7 h-7"></i>
            </span>
            <h1 class="text-xl font-bold mt-3">{{ config('app.name') }}</h1>
            <p class="text-[13px] text-slate-500 mt-0.5">Vos clients, leurs installations, leurs abonnements.</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
            <form method="POST" action="{{ route('connexion') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11.5px] font-bold text-slate-600 mb-1.5">Adresse e-mail</label>
                    <div class="relative">
                        <i data-lucide="mail" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                               class="w-full pl-9 pr-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm
                                      focus:outline-none focus:border-emerald-600 focus:bg-white focus:ring-2 focus:ring-emerald-600/15 transition">
                    </div>
                </div>

                <div>
                    <div class="flex items-baseline justify-between mb-1.5">
                        <label class="block text-[11.5px] font-bold text-slate-600">Mot de passe</label>
                        <a href="{{ route('mot-de-passe.oubli') }}" class="text-[11.5px] font-semibold text-emerald-700 hover:underline">Oublié ?</a>
                    </div>
                    <div class="relative">
                        <i data-lucide="lock" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="password" name="password" required autocomplete="current-password"
                               class="w-full pl-9 pr-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm
                                      focus:outline-none focus:border-emerald-600 focus:bg-white focus:ring-2 focus:ring-emerald-600/15 transition">
                    </div>
                </div>

                <label class="flex items-center gap-2 text-[13px] text-slate-600">
                    <input type="checkbox" name="memoriser" value="1" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-600/30">
                    Rester connecté
                </label>

                <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700
                               text-white text-sm font-bold transition cursor-pointer shadow-sm shadow-emerald-600/25">
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                    Entrer
                </button>
            </form>
        </div>

        <p class="text-center text-[11.5px] text-slate-400 mt-4">
            Aucune inscription en ligne — les comptes se créent avec
            <code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-600">php artisan oikos:admin</code>
        </p>
    </div>
@endsection
