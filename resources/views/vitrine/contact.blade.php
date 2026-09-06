@extends('vitrine.layout')
@section('titre', 'Contact')
@section('resume', "Écrire à l'équipe Oikos : démonstration, devis, question sur les offres.")

@php
    $champ = 'w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-[14px] shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none';
    $etiquette = 'block text-[12.5px] font-semibold text-slate-700 mb-1.5';
@endphp

@section('contenu')

    <section class="max-w-3xl mx-auto px-5 sm:px-8 pt-14 pb-8">
        <h1 class="text-3xl sm:text-4xl font-bold">Parlons de votre communauté</h1>
        <p class="text-[15px] text-slate-600 mt-3 leading-relaxed">
            Dites-nous combien vous êtes et comment vous êtes organisés. Nous répondons avec l'offre
            qui correspond, et une démonstration si vous le souhaitez.
        </p>
    </section>

    <section class="max-w-3xl mx-auto px-5 sm:px-8 pb-16">
        @if ($errors->any())
            <div class="mb-5 flex gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[13.5px] text-red-900">
                <i data-lucide="circle-alert" class="w-4 h-4 mt-0.5 shrink-0 text-red-600"></i>
                <div>@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
            </div>
        @endif

        <form method="POST" action="{{ route('vitrine.contact.envoyer') }}"
              class="relative rounded-2xl border border-slate-200 p-6 sm:p-8 space-y-5">
            @csrf

            {{-- LE PIÈGE À ROBOTS. Caché par le style, hors du parcours du clavier, et annoncé aux
                 lecteurs d'écran comme à ignorer. Un humain ne le voit pas et ne le remplit donc
                 jamais ; un robot remplit tout ce qu'il trouve. S'il arrive rempli, le message est
                 abandonné — et on répond « merci » quand même : un robot à qui l'on montre une
                 erreur réessaie en corrigeant. --}}
            <div class="absolute w-px h-px overflow-hidden -m-px" aria-hidden="true">
                <label for="site_web">Ne remplissez pas ce champ</label>
                <input type="text" id="site_web" name="site_web" tabindex="-1" autocomplete="off" value="">
            </div>

            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label for="nom" class="{{ $etiquette }}">Votre nom</label>
                    <input id="nom" type="text" name="nom" value="{{ old('nom') }}" required maxlength="190" autofocus class="{{ $champ }}">
                </div>
                <div>
                    <label for="organisation" class="{{ $etiquette }}">Communauté ou église</label>
                    <input id="organisation" type="text" name="organisation" value="{{ old('organisation') }}" maxlength="190" class="{{ $champ }}">
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label for="email" class="{{ $etiquette }}">Adresse e-mail</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="190" class="{{ $champ }}">
                </div>
                <div>
                    <label for="telephone" class="{{ $etiquette }}">Téléphone ou WhatsApp</label>
                    <input id="telephone" type="tel" name="telephone" value="{{ old('telephone') }}" maxlength="40"
                           placeholder="+243 812 345 678" class="{{ $champ }}">
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label for="ville" class="{{ $etiquette }}">Ville</label>
                    <input id="ville" type="text" name="ville" value="{{ old('ville') }}" maxlength="120" class="{{ $champ }}">
                </div>
                <div>
                    <label for="niveau" class="{{ $etiquette }}">Vous êtes…</label>
                    <select id="niveau" name="niveau" class="{{ $champ }}">
                        <option value="">Je ne sais pas encore</option>
                        @foreach ($niveaux as $code => $libelle)
                            <option value="{{ $code }}" @selected(old('niveau') === $code)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label for="message" class="{{ $etiquette }}">Votre message</label>
                <textarea id="message" name="message" rows="6" required minlength="10" maxlength="4000"
                          placeholder="Combien de membres, combien d'églises, ce que vous cherchez à régler en premier…"
                          class="{{ $champ }}">{{ old('message') }}</textarea>
            </div>

            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-[14px] font-bold text-white hover:bg-emerald-700 transition shadow-sm shadow-emerald-600/25">
                <i data-lucide="send" class="w-4 h-4"></i>
                Envoyer
            </button>

            <p class="text-[12px] text-slate-500">
                Vos coordonnées servent uniquement à vous répondre. Elles ne sont ni revendues, ni
                transmises à un tiers.
            </p>
        </form>
    </section>

@endsection
