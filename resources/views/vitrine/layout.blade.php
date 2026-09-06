{{-- LE GABARIT DU SITE PUBLIC — délibérément distinct de celui de la console.

     POURQUOI NE PAS RÉUTILISER L'AUTRE. Les deux n'ont ni le même lecteur ni le même but : la
     console est un outil (dense, gris, tabulaire), la vitrine est une brochure (aérée, large,
     une idée par écran). Les faire cohabiter dans un seul gabarit produit toujours le même
     résultat — un site qui a l'air d'un panneau d'administration, et une administration qui perd
     de la place à faire joli.

     Ce qu'ils PARTAGENT, en revanche : la palette. Même émeraude, mêmes règles — l'aplat pour la
     marque et les actions, la pastille pâle pour les états. Un client qui verra un jour son écran
     de renouvellement doit reconnaître la même maison. --}}
<!DOCTYPE html>
<html lang="fr" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre') · {{ config('app.name') }}</title>
    <meta name="description" content="@yield('resume', 'Oikos — le système de gestion ecclésiastique, de la vision à la cellule. Membres, départements, plannings, appel nominal, rapports et trésorerie.')">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-white text-slate-900 antialiased">

@php
    $liens = [
        ['vitrine.fonctionnalites', 'Fonctionnalités'],
        ['vitrine.tarifs', 'Tarifs'],
        ['vitrine.paiement', 'Comment payer'],
        ['vitrine.references', 'Références'],
    ];
@endphp

<header class="sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-5 sm:px-8 h-16 flex items-center gap-4">
        <a href="{{ route('vitrine.accueil') }}" class="flex items-center gap-2.5 shrink-0">
            <span class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center">
                <i data-lucide="church" class="w-5 h-5"></i>
            </span>
            <span class="font-bold text-[16px]">{{ config('produit.nom', 'Oikos') }}</span>
        </a>

        <nav class="hidden md:flex items-center gap-1 ml-4">
            @foreach ($liens as [$route, $libelle])
                <a href="{{ route($route) }}"
                   class="px-3 py-2 rounded-lg text-[13.5px] font-semibold transition
                          {{ request()->routeIs($route) ? 'text-emerald-700 bg-emerald-50' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                    {{ $libelle }}
                </a>
            @endforeach
        </nav>

        <a href="{{ route('vitrine.contact') }}"
           class="ml-auto inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-[13px] font-bold text-white hover:bg-emerald-700 transition shadow-sm shadow-emerald-600/25">
            <i data-lucide="mail" class="w-4 h-4"></i>
            <span class="hidden sm:inline">Nous écrire</span>
        </a>
    </div>

    {{-- Sur téléphone, la navigation passe sous la marque plutôt que dans un tiroir : quatre liens
         ne valent pas le JavaScript qu'un menu coulissant demanderait. --}}
    <nav class="md:hidden flex gap-1 px-4 pb-2.5 overflow-x-auto border-t border-slate-100 pt-2">
        @foreach ($liens as [$route, $libelle])
            <a href="{{ route($route) }}"
               class="px-3 py-1.5 rounded-lg text-[12.5px] font-semibold whitespace-nowrap transition
                      {{ request()->routeIs($route) ? 'text-emerald-700 bg-emerald-50' : 'text-slate-600 hover:bg-slate-100' }}">
                {{ $libelle }}
            </a>
        @endforeach
    </nav>
</header>

<main>
    @if (session('ok'))
        <div class="max-w-6xl mx-auto px-5 sm:px-8 pt-6">
            <div class="flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-[13.5px] text-emerald-900">
                <i data-lucide="circle-check" class="w-4 h-4 mt-0.5 shrink-0 text-emerald-600"></i>
                <p>{{ session('ok') }}</p>
            </div>
        </div>
    @endif

    @yield('contenu')
</main>

<footer class="mt-20 bg-emerald-950 text-emerald-100/80">
    <div class="max-w-6xl mx-auto px-5 sm:px-8 py-12 grid gap-8 sm:grid-cols-3">
        <div>
            <div class="flex items-center gap-2.5 mb-3">
                <span class="w-9 h-9 rounded-xl bg-emerald-500/15 border border-emerald-400/25 text-emerald-400 flex items-center justify-center">
                    <i data-lucide="church" class="w-5 h-5"></i>
                </span>
                <span class="font-bold text-white text-[15px]">{{ config('produit.nom', 'Oikos') }}</span>
            </div>
            <p class="text-[13px] leading-relaxed">{{ config('produit.nom_long', 'Gestion ecclésiastique — de la vision à la cellule.') }}</p>
        </div>

        <div>
            <p class="text-[10.5px] font-bold uppercase tracking-wider text-emerald-400/70 mb-2.5">Le produit</p>
            <ul class="space-y-1.5 text-[13px]">
                @foreach ($liens as [$route, $libelle])
                    <li><a href="{{ route($route) }}" class="hover:text-white transition">{{ $libelle }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <p class="text-[10.5px] font-bold uppercase tracking-wider text-emerald-400/70 mb-2.5">Nous joindre</p>
            <ul class="space-y-1.5 text-[13px]">
                <li><a href="{{ route('vitrine.contact') }}" class="hover:text-white transition">Formulaire de contact</a></li>
                @if (config('produit.editeur'))
                    <li>{{ config('produit.editeur') }}</li>
                @endif
            </ul>
        </div>
    </div>

    <div class="border-t border-emerald-900/70">
        <div class="max-w-6xl mx-auto px-5 sm:px-8 py-4 flex flex-wrap items-center gap-3 text-[12px]">
            <p>© {{ date('Y') }} {{ config('produit.editeur') ?: config('produit.nom', 'Oikos') }}</p>
            {{-- Le lien vers la console reste discret : l'adresse n'est pas un secret — la cacher
                 ne protégerait rien — mais elle ne s'adresse qu'à une personne. --}}
            <a href="{{ route('connexion') }}" class="ml-auto inline-flex items-center gap-1.5 text-emerald-400/70 hover:text-white transition">
                <i data-lucide="lock" class="w-3 h-3"></i> Console
            </a>
        </div>
    </div>
</footer>

</body>
</html>
