@extends('vitrine.layout')
@section('titre', config('produit.nom_long'))
@section('resume', config('produit.accroche'))

@section('contenu')

    {{-- BANDEAU D'ENTRÉE. C'est la page qui remplace l'écran de connexion à la racine : un
         visiteur doit comprendre en une phrase ce que fait le produit, et voir tout de suite les
         deux seules choses qu'il peut vouloir — le prix, ou vous parler.

         L'APERÇU À DROITE N'EST PAS UN ORNEMENT. Un site de logiciel qui ne montre jamais le
         logiciel demande au visiteur de croire sur parole. Cette maquette dit en une image ce que
         trois paragraphes expliquent mal : à quoi ressemble le geste quotidien du produit. --}}
    <section class="relative overflow-hidden bg-emerald-950 text-white">

        {{-- Deux halos très diffus, en position absolue et sans interaction : ils cassent
             l'aplat de couleur sans rien coûter en poids ni en requêtes. --}}
        <div class="pointer-events-none absolute inset-0" aria-hidden="true"
             style="background:
                    radial-gradient(60rem 30rem at 85% 0%, rgba(16,185,129,.22), transparent 60%),
                    radial-gradient(40rem 24rem at 0% 100%, rgba(5,150,105,.18), transparent 60%);"></div>

        <div class="relative max-w-6xl mx-auto px-5 sm:px-8 py-16 sm:py-24">
            <div class="grid lg:grid-cols-2 gap-12 lg:gap-8 items-center">

                <div>
                    <p class="inline-flex items-center gap-2 rounded-full bg-emerald-500/15 border border-emerald-400/25 px-3 py-1 text-[12px] font-semibold text-emerald-300">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                        Conçu en RDC, pour les églises d'ici
                    </p>

                    <h1 class="mt-5 text-3xl sm:text-5xl font-bold leading-tight">
                        {{ config('produit.nom_long') }}
                    </h1>

                    <p class="mt-5 text-[15px] sm:text-lg text-emerald-100/80 max-w-xl leading-relaxed">
                        {{ config('produit.accroche') }}
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('vitrine.tarifs') }}"
                           class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 text-[14px] font-bold text-emerald-950 hover:bg-emerald-50 transition">
                            <i data-lucide="tag" class="w-4 h-4"></i> Voir les tarifs
                        </a>
                        <a href="{{ route('vitrine.contact') }}"
                           class="inline-flex items-center gap-2 rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-5 py-3 text-[14px] font-bold text-white hover:bg-emerald-500/20 transition">
                            <i data-lucide="message-circle" class="w-4 h-4"></i> Demander une démonstration
                        </a>
                    </div>
                </div>

                <div class="flex justify-center lg:justify-end">
                    <x-apercu-appel />
                </div>
            </div>
        </div>
    </section>

    {{-- À QUI ÇA S'ADRESSE. La question qu'on se pose avant le prix : « est-ce fait pour une
         structure comme la mienne ? ». Les trois niveaux répondent, et préparent la grille
         tarifaire, qui est bâtie sur eux. --}}
    <section class="max-w-6xl mx-auto px-5 sm:px-8 py-16">
        <h2 class="text-2xl font-bold">Trois tailles de structure, un seul système</h2>
        <p class="text-[14px] text-slate-500 mt-2 max-w-2xl">
            Vous n'avez pas besoin d'être un réseau d'églises pour l'utiliser. Une assemblée seule
            s'installe et s'en sert le dimanche suivant.
        </p>

        <x-schema-structure class="mt-8 max-w-3xl mx-auto" :legende="false" />

        <div class="mt-8 grid gap-4 md:grid-cols-3">
            @php
                $profils = [
                    ['eye', 'Une vision et ses antennes', "Le siège voit l'ensemble : chaque antenne, chaque église, et les départements définis une seule fois pour tout le monde."],
                    ['radio-tower', 'Une antenne régionale', "Un coordinateur suit les églises de sa région, leurs plannings et leurs rapports, sans dépendre du siège pour chaque détail."],
                    ['church', 'Une église seule', "Vos membres, vos départements, vos cultes et votre trésorerie. Sans réseau au-dessus, et sans rien d'inutile à l'écran."],
                ];
            @endphp
            @foreach ($profils as [$icone, $titre, $texte])
                <div class="rounded-2xl border border-slate-200 p-6">
                    <span class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                        <i data-lucide="{{ $icone }}" class="w-5 h-5"></i>
                    </span>
                    <h3 class="font-bold mt-4">{{ $titre }}</h3>
                    <p class="text-[13.5px] text-slate-600 mt-1.5 leading-relaxed">{{ $texte }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- CE QUE ÇA FAIT. Six modules seulement ici : la page Fonctionnalités les détaille tous.
         Une page d'accueil qui énumère treize choses n'en fait retenir aucune. --}}
    <section class="bg-slate-50 border-y border-slate-200">
        <div class="max-w-6xl mx-auto px-5 sm:px-8 py-16">
            <h2 class="text-2xl font-bold">Ce que le système prend en charge</h2>

            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (collect($modulesEntite)->take(6) as $module)
                    <div class="rounded-2xl bg-white border border-slate-200 p-5">
                        <div class="flex items-center gap-2.5">
                            <span class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                                <i data-lucide="{{ $module['icone'] }}" class="w-4 h-4"></i>
                            </span>
                            <h3 class="font-bold text-[14px]">{{ $module['nom'] }}</h3>
                        </div>
                        <p class="text-[13px] text-slate-600 mt-2.5 leading-relaxed">{{ $module['texte'] }}</p>
                    </div>
                @endforeach
            </div>

            <a href="{{ route('vitrine.fonctionnalites') }}"
               class="inline-flex items-center gap-1.5 mt-7 text-[13.5px] font-bold text-emerald-700 hover:underline">
                Les {{ $nombreModules }} modules en détail <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </div>
    </section>

    {{-- APERÇU DES PRIX — DEUX CHIFFRES, PAS TROIS FAMILLES. Le visiteur se range en une seconde
         dans l'un des deux cas : « je suis une église seule » ou « nous sommes un réseau ». Étaler
         ici la grille complète ramènerait la confusion que la page Tarifs vient de dissiper. --}}
    @if ($departSeule || $departLicence)
        <section class="max-w-6xl mx-auto px-5 sm:px-8 py-16">
            <h2 class="text-2xl font-bold">Combien ça coûte</h2>
            <p class="text-[14px] text-slate-500 mt-2 max-w-2xl">
                Deux cas, deux façons de compter.
            </p>

            <div class="mt-8 grid gap-4 md:grid-cols-2 max-w-4xl">
                @if ($departSeule)
                    <div class="rounded-2xl border border-slate-200 p-6">
                        <span class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                            <i data-lucide="church" class="w-5 h-5"></i>
                        </span>
                        <h3 class="font-bold mt-4">Une église seule</h3>
                        <p class="text-[13px] text-slate-600 mt-1">Une licence pour l'assemblée, puis son accès mensuel.</p>
                        <p class="mt-4 text-3xl font-bold tabular-nums">
                            {{ $departSeule->prixUsd() }}
                            <span class="text-[14px] font-semibold text-slate-500">/ {{ $departSeule->libellePeriode() }}</span>
                        </p>
                        {{-- « Tout compris, un seul prix » etait faux : une eglise seule paie DEUX
                             lignes, comme un reseau. Afficher la licence toute seule laissait
                             decouvrir l'abonnement mensuel a la signature — le pire moment. --}}
                        @if ($departAccesSeule)
                            <p class="text-[13px] text-slate-500 mt-1">
                                + {{ $departAccesSeule->prixUsd() }} par {{ $departAccesSeule->libellePeriode() }}
                            </p>
                        @endif
                    </div>
                @endif

                @if ($departLicence)
                    <div class="rounded-2xl border border-slate-200 p-6">
                        <span class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                            <i data-lucide="network" class="w-5 h-5"></i>
                        </span>
                        <h3 class="font-bold mt-4">Un réseau</h3>
                        <p class="text-[13px] text-slate-600 mt-1">Une licence pour la structure, puis un accès par entité.</p>
                        <p class="mt-4 text-3xl font-bold tabular-nums">
                            {{ $departLicence->prixUsd() }}
                            <span class="text-[14px] font-semibold text-slate-500">/ {{ $departLicence->libellePeriode() }}</span>
                        </p>
                        @if ($departAcces)
                            <p class="text-[13px] text-slate-500 mt-1">
                                + {{ $departAcces->prixUsd() }} par entité et par {{ $departAcces->libellePeriode() }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <a href="{{ route('vitrine.tarifs') }}"
               class="inline-flex items-center gap-1.5 mt-7 text-[13.5px] font-bold text-emerald-700 hover:underline">
                La grille complète <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </section>
    @endif

    @if ($nombreReferences > 0)
        <section class="max-w-6xl mx-auto px-5 sm:px-8 pb-4">
            <a href="{{ route('vitrine.references') }}"
               class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-6 py-5 hover:border-emerald-300 transition">
                <i data-lucide="users" class="w-5 h-5 text-emerald-700"></i>
                <p class="text-[14px] font-semibold">
                    {{ $nombreReferences }} communauté{{ $nombreReferences > 1 ? 's' : '' }}
                    utilise{{ $nombreReferences > 1 ? 'nt' : '' }} déjà {{ config('produit.nom') }}
                </p>
                <span class="ml-auto inline-flex items-center gap-1.5 text-[13.5px] font-bold text-emerald-700">
                    Les voir <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </span>
            </a>
        </section>
    @endif

    <section class="max-w-6xl mx-auto px-5 sm:px-8 py-16">
        <div class="rounded-3xl bg-emerald-950 text-white px-6 sm:px-10 py-12 text-center">
            <h2 class="text-2xl sm:text-3xl font-bold">Parlons de votre communauté</h2>
            <p class="text-[14.5px] text-emerald-100/80 mt-3 max-w-xl mx-auto leading-relaxed">
                Dites-nous combien vous êtes et comment vous êtes organisés : nous vous répondrons
                avec l'offre qui correspond, et une démonstration si vous le souhaitez.
            </p>
            <a href="{{ route('vitrine.contact') }}"
               class="inline-flex items-center gap-2 mt-7 rounded-xl bg-white px-5 py-3 text-[14px] font-bold text-emerald-950 hover:bg-emerald-50 transition">
                <i data-lucide="mail" class="w-4 h-4"></i> Nous écrire
            </a>
        </div>
    </section>

@endsection
