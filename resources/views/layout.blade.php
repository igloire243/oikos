{{-- Mise en page de la console.

     LA PALETTE EST VERTE, MAIS PAS PARTOUT DE LA MÊME FAÇON. Le vert de marque — émeraude soutenu —
     habille les SURFACES et les ACTIONS : la barre latérale, les boutons principaux. Le vert d'état
     — pastille claire sur fond très pâle — dit « actif », « payé ». Les deux se ressemblent, d'où
     la règle : jamais un fond plein pour un état, jamais une pastille pâle pour une action. Et
     chaque état porte une ICÔNE en plus de sa couleur, pour qui ne les distingue pas. --}}
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 text-slate-900 antialiased">

<div class="min-h-full lg:flex">

    {{-- BARRE LATÉRALE — colonne fixe sur grand écran, bandeau horizontal sur téléphone. Pas de
         tiroir coulissant : il demanderait du JavaScript pour trois liens.

         ELLE N'EXISTE QUE POUR QUI EST CONNECTÉ. Sur l'écran de connexion et sur ceux du mot de
         passe oublié, elle n'afficherait qu'un bandeau vert vide : ses trois liens mènent à des
         pages fermées, et le nom du produit est déjà sur la carte, au centre. Un décor qui ne
         sert à rien donne à croire qu'on a raté un contenu qui aurait dû s'y trouver. --}}
    @auth
    {{-- FIXE, ET NON SIMPLEMENT COLLÉE EN HAUT DE COLONNE. Sur les écrans longs — la fiche d'un
         client, la liste des offres — la navigation disparaissait dès qu'on faisait défiler, et il
         fallait remonter pour changer de section. `lg:fixed` la sort du flux ; `lg:ml-60` sur le
         contenu lui rend la place qu'elle occupait.

         `overflow-y-auto` sur la barre elle-même : le jour où les liens dépasseront la hauteur de
         l'écran, ils défileront dans la barre au lieu d'être coupés sans recours. --}}
    <aside class="sticky top-0 z-30 lg:fixed lg:inset-y-0 lg:left-0 lg:w-60 lg:h-screen lg:overflow-y-auto
                  bg-emerald-950 text-emerald-100 flex lg:flex-col">
        <div class="px-5 py-4 flex items-center gap-3 lg:border-b border-emerald-900/60">
            <span class="w-9 h-9 rounded-xl bg-emerald-500/15 border border-emerald-400/25 flex items-center justify-center text-emerald-400">
                <i data-lucide="church" class="w-5 h-5"></i>
            </span>
            <span class="font-bold text-white text-[15px] leading-tight hidden sm:block">{{ config('app.name') }}</span>
        </div>

        <nav class="flex lg:flex-col gap-1 px-3 py-3 lg:py-4 ml-auto lg:ml-0 lg:flex-1 overflow-x-auto sans-barre">
            @php
                // Une requête par page, et seulement pour qui est connecté. C'est le prix d'une pastille qui dit
                // « il y a quelque chose à lire » : sans elle, une demande arrivée le mardi se
                // découvre le vendredi, et le prospect a déjà écrit ailleurs.
                $nouvellesDemandes = \App\Models\Demande::where('statut', \App\Models\Demande::NOUVELLE)->count();
                $facturesAEncaisser = \App\Models\Facture::where('statut', \App\Models\Facture::EMISE)->count();

                $liens = [
                    ['tableau-bord', 'Tableau de bord', 'layout-dashboard', 'tableau-bord', null],
                    ['clients.index', 'Clients', 'users', 'clients.*', null],
                    ['plans.index', 'Offres', 'tag', 'plans.*', null],
                    ['factures.index', 'Factures', 'receipt', 'factures.*', $facturesAEncaisser ?: null],
                    ['demandes.index', 'Demandes', 'inbox', 'demandes.*', $nouvellesDemandes ?: null],
                    ['reglages.index', 'Réglages', 'sliders-horizontal', 'reglages.*', null],
                ];
            @endphp
            @foreach ($liens as [$route, $libelle, $icone, $motif, $pastille])
                <a href="{{ route($route) }}"
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-[13.5px] font-semibold whitespace-nowrap transition
                          {{ request()->routeIs($motif)
                             ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-900/40'
                             : 'text-emerald-200/80 hover:bg-emerald-900/70 hover:text-white' }}">
                    <i data-lucide="{{ $icone }}" class="w-4 h-4"></i>
                    <span class="hidden sm:inline">{{ $libelle }}</span>
                    @if ($pastille)
                        <span class="ml-auto rounded-full bg-emerald-500 text-white text-[10.5px] font-bold px-1.5 py-0.5 tabular-nums">{{ $pastille }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="px-3 py-3 lg:border-t border-emerald-900/60 flex items-center gap-2">
            <div class="min-w-0 hidden lg:block flex-1">
                <p class="text-[12.5px] font-semibold text-white truncate">{{ auth()->user()->name }}</p>
                <p class="text-[11px] text-emerald-300/70 truncate">{{ auth()->user()->email }}</p>
            </div>
            <a href="{{ route('vitrine.accueil') }}" target="_blank" rel="noopener" title="Voir le site public"
               class="w-9 h-9 rounded-lg text-emerald-300/80 hover:bg-emerald-900/70 hover:text-white flex items-center justify-center transition">
                <i data-lucide="globe" class="w-4 h-4"></i>
            </a>
            <form method="POST" action="{{ route('deconnexion') }}">
                @csrf
                <button type="submit" title="Se déconnecter"
                        class="w-9 h-9 rounded-lg text-emerald-300/80 hover:bg-emerald-900/70 hover:text-white flex items-center justify-center transition cursor-pointer">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                </button>
            </form>
        </div>
    </aside>
    @endauth

    <main class="flex-1 min-w-0 {{ auth()->check() ? 'lg:ml-60' : '' }}">
        <div class="max-w-5xl mx-auto px-5 sm:px-8 py-7 sm:py-9">

            @if (session('ok'))
                <div class="mb-5 flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-[13px] text-emerald-900">
                    <i data-lucide="circle-check" class="w-4 h-4 mt-0.5 text-emerald-600"></i>
                    <p>{{ session('ok') }}</p>
                </div>
            @endif

            @if (session('avertissement'))
                <div class="mb-5 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-900">
                    <i data-lucide="triangle-alert" class="w-4 h-4 mt-0.5 text-amber-600"></i>
                    <p>{{ session('avertissement') }}</p>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 flex gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[13px] text-red-900">
                    <i data-lucide="circle-alert" class="w-4 h-4 mt-0.5 text-red-600"></i>
                    <div>@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
                </div>
            @endif

            @yield('contenu')
        </div>
    </main>
</div>

</body>
</html>
