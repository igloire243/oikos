@extends('layout')
@section('titre', 'Nouveau client')

@php
    $champ = 'w-full rounded-xl border border-slate-300 px-3 py-2 text-[13.5px] shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none';
    $etiquette = 'block text-[12px] font-semibold text-slate-600 mb-1';
    $btn = 'inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-[13px] font-semibold text-white shadow-sm hover:bg-emerald-700 transition cursor-pointer';
    $btnDiscret = 'inline-flex items-center gap-2 rounded-xl bg-white border border-slate-300 px-4 py-2.5 text-[13px] font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer';
@endphp

@section('contenu')
    <a href="{{ route('clients.index') }}" class="inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-slate-500 hover:text-slate-800 mb-3 transition">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Clients
    </a>

    <h1 class="text-xl font-bold">Nouveau client</h1>
    <p class="text-[13px] text-slate-500 mb-6">La fiche de la communauté. Son installation s'ajoute juste après.</p>

    <div class="bg-white border border-slate-200 rounded-2xl p-5 sm:p-6">
        <form method="POST" action="{{ route('clients.enregistrer') }}" class="space-y-4">
            @csrf

            <div>
                <label for="nom" class="{{ $etiquette }}">Nom de la communauté</label>
                <input id="nom" type="text" name="nom" value="{{ old('nom') }}" required minlength="2" maxlength="200"
                       placeholder="ex. Génération Joël" autofocus class="{{ $champ }}">
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="pays" class="{{ $etiquette }}">Pays</label>
                    <input id="pays" type="text" name="pays" value="{{ old('pays', 'RD Congo') }}" maxlength="120" class="{{ $champ }}">
                </div>
                <div>
                    <label for="ville" class="{{ $etiquette }}">Ville</label>
                    <input id="ville" type="text" name="ville" value="{{ old('ville') }}" maxlength="120" class="{{ $champ }}">
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="contact_nom" class="{{ $etiquette }}">Personne de contact</label>
                    <input id="contact_nom" type="text" name="contact_nom" value="{{ old('contact_nom') }}" maxlength="190"
                           placeholder="Le pasteur, ou son secrétaire" class="{{ $champ }}">
                </div>
                <div>
                    <label for="statut" class="{{ $etiquette }}">État</label>
                    <select id="statut" name="statut" class="{{ $champ }}">
                        @foreach (\App\Models\Client::STATUTS as $code => $libelle)
                            <option value="{{ $code }}" @selected(old('statut', 'PROSPECT') === $code)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="contact_email" class="{{ $etiquette }}">E-mail de contact</label>
                    <input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email') }}" maxlength="190" class="{{ $champ }}">
                </div>
                <div>
                    <label for="contact_telephone" class="{{ $etiquette }}">Téléphone</label>
                    <input id="contact_telephone" type="tel" name="contact_telephone" value="{{ old('contact_telephone') }}"
                           maxlength="40" placeholder="+243 812 345 678" class="{{ $champ }}">
                </div>
            </div>

            <div>
                <label for="notes" class="{{ $etiquette }}">Notes</label>
                <textarea id="notes" name="notes" rows="4" maxlength="5000"
                          placeholder="Ce qui a été convenu, le tarif négocié, qui décide…" class="{{ $champ }}">{{ old('notes') }}</textarea>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <button type="submit" class="{{ $btn }}">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    Créer le client
                </button>
                <a href="{{ route('clients.index') }}" class="{{ $btnDiscret }}">Annuler</a>
            </div>
        </form>
    </div>
@endsection
