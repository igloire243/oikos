@extends('vitrine.layout')
@section('titre', 'Tarifs')
@section('resume', "Les tarifs d'Oikos : une offre tout compris pour une église seule, ou une licence de structure plus un accès par entité pour un réseau.")

@php
    use App\Models\Plan;

    $seule = $parNature->get(Plan::COMBINEE) ?? collect();
    $licences = $parNature->get(Plan::LICENCE) ?? collect();
    $acces = $parNature->get(Plan::ACCES) ?? collect();

    // LES ACCES « EGLISE SEULE » NE SONT PAS DES ACCES DE RESEAU. Ils sont de nature ACCES — meme
    // mecanique, meme periodicite — mais ils s'adressent a une assemblee autonome et coutent trois
    // fois le prix d'un acces en reseau, ou la licence de la vision amortit une partie. Laisses
    // dans le rang du reseau, ils y apparaissaient comme deux offres cheres et inexplicables,
    // pendant que le rang « eglise seule » n'affichait que sa licence annuelle.
    //
    // Le tri se fait sur le CODE et non sur `niveau` ou `quotas` : le code est l'identite du plan
    // (c'est la cle de `updateOrCreate` dans PlanSeeder), les autres champs sont des reglages qui
    // peuvent changer en console sans aucune intention de deplacer l'offre de rang.
    [$accesSeuls, $acces] = $acces->partition(fn ($p) => str_starts_with($p->code, 'ACCES_SEULE'));

    // L'offre mise en avant est le palier STANDARD de chaque famille — celui que la plupart des
    // clients prendront. Sans repère, un visiteur devant trois colonnes équivalentes ne choisit
    // pas : il repousse.
    $enAvant = fn ($plan) => $plan->palier === Plan::STANDARD;

    // L'EXEMPLE CHIFFRÉ EST CALCULÉ, PAS ÉCRIT À LA MAIN. Un exemple recopié dans un texte devient
    // faux à la première modification de prix, et personne ne s'en aperçoit — sauf le client, qui
    // le découvre au moment de la facture.
    $exempleEntites = 7;
    $licenceExemple = $licences->firstWhere('palier', Plan::STANDARD);
    $accesExemple = $acces->firstWhere('palier', Plan::STANDARD);
@endphp

@section('contenu')

    <section class="max-w-6xl mx-auto px-5 sm:px-8 pt-14 pb-8">
        <h1 class="text-3xl sm:text-4xl font-bold">Tarifs</h1>
        <p class="text-[15px] text-slate-600 mt-3 max-w-2xl leading-relaxed">
            Deux questions suffisent : <strong class="font-semibold text-slate-800">êtes-vous une
            église seule ou un réseau ?</strong> et de quels modules avez-vous besoin ?
        </p>
    </section>

    {{-- ═══ 1. L'ÉGLISE SEULE — en premier, parce que c'est le cas le plus fréquent et le plus
         simple. Un pasteur d'une assemblée de deux cents personnes doit trouver son prix sans
         lire un mot sur les visions et les antennes. ═══ --}}
    @if ($seule->isNotEmpty())
        <section class="max-w-6xl mx-auto pb-12">
            <div class="px-5 sm:px-8 mb-5">
                <h2 class="text-2xl font-bold flex items-center gap-2.5">
                    <span class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                        <i data-lucide="church" class="w-5 h-5"></i>
                    </span>
                    Vous êtes une église seule
                </h2>
                <p class="text-[14px] text-slate-600 mt-2 max-w-2xl leading-relaxed">
                    Aucune structure au-dessus de vous. Deux lignes seulement&nbsp;:
                    <strong class="font-semibold text-slate-800">la licence, une fois par an</strong>,
                    et <strong class="font-semibold text-slate-800">l'accès à votre espace, au
                    mois</strong>. Rien d'autre ne s'y ajoute.
                </p>
            </div>

            <h3 class="px-5 sm:px-8 text-[13px] font-bold uppercase tracking-wider text-slate-400 mb-4">
                1. La licence — une fois par an
            </h3>
            <div class="relative" data-bandeau>
                <div data-bandeau-piste tabindex="0" role="region" aria-label="Licences pour une église seule"
                     class="flex items-stretch gap-4 overflow-x-auto px-5 sm:px-8 pb-4
                            snap-x snap-mandatory scroll-px-5 sm:scroll-px-8
                            focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30 rounded-2xl">
                    @php $precedent = null; @endphp
                    @foreach ($seule as $plan)
                        <x-carte-offre :plan="$plan" :base="$precedent" :en-avant="$enAvant($plan)" />
                        @php $precedent = $plan; @endphp
                    @endforeach
                </div>
                <div data-bandeau-gauche aria-hidden="true"
                     class="pointer-events-none absolute inset-y-0 left-0 w-10 sm:w-14 bg-gradient-to-r from-white via-white/80 to-transparent opacity-0 transition-opacity duration-200"></div>
                <div data-bandeau-droite aria-hidden="true"
                     class="pointer-events-none absolute inset-y-0 right-0 w-10 sm:w-14 bg-gradient-to-l from-white via-white/80 to-transparent transition-opacity duration-200"></div>
            </div>

            @if ($accesSeuls->isNotEmpty())
                <h3 class="px-5 sm:px-8 mt-8 text-[13px] font-bold uppercase tracking-wider text-slate-400 mb-4">
                    2. L'accès à votre espace — au mois
                </h3>
                <div class="relative" data-bandeau>
                    <div data-bandeau-piste tabindex="0" role="region" aria-label="Accès pour une église seule"
                         class="flex items-stretch gap-4 overflow-x-auto px-5 sm:px-8 pb-4
                                snap-x snap-mandatory scroll-px-5 sm:scroll-px-8
                                focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30 rounded-2xl">
                        @php $precedent = null; @endphp
                        @foreach ($accesSeuls as $plan)
                            <x-carte-offre :plan="$plan" :base="$precedent" :en-avant="$enAvant($plan)" />
                            @php $precedent = $plan; @endphp
                        @endforeach
                    </div>
                    <div data-bandeau-gauche aria-hidden="true"
                         class="pointer-events-none absolute inset-y-0 left-0 w-10 sm:w-14 bg-gradient-to-r from-white via-white/80 to-transparent opacity-0 transition-opacity duration-200"></div>
                    <div data-bandeau-droite aria-hidden="true"
                         class="pointer-events-none absolute inset-y-0 right-0 w-10 sm:w-14 bg-gradient-to-l from-white via-white/80 to-transparent transition-opacity duration-200"></div>
                </div>
            @endif

            {{-- L'ADDITION, POSEE. Deux lignes annoncees separement laissent le visiteur faire un
                 calcul qu'il fera de travers, ou pas du tout. On la pose nous-memes, a partir des
                 offres reelles — jamais recopiee a la main, sinon elle ment au premier changement
                 de prix. --}}
            @php
                $licenceSeule = $seule->firstWhere('palier', Plan::STANDARD) ?? $seule->first();
                $accesSeul = $accesSeuls->firstWhere('palier', Plan::STANDARD) ?? $accesSeuls->first();
            @endphp
            @if ($licenceSeule && $accesSeul)
                <div class="px-5 sm:px-8 mt-6">
                    <p class="text-[13.5px] text-slate-600 leading-relaxed max-w-2xl">
                        En Standard, la première année revient à
                        <strong class="font-semibold text-slate-800 tabular-nums">{{ number_format(($licenceSeule->prix_usd_cents + 12 * $accesSeul->prix_usd_cents) / 100, 0, ',', ' ') }}&nbsp;$</strong>
                        — {{ number_format($licenceSeule->prix_usd_cents / 100, 0, ',', ' ') }}&nbsp;$ de licence
                        et douze mois d'accès à {{ number_format($accesSeul->prix_usd_cents / 100, 0, ',', ' ') }}&nbsp;$.
                    </p>
                </div>
            @endif
        </section>
    @endif

    {{-- ═══ 2. LE RÉSEAU — deux lignes, expliquées AVANT les prix. C'est la seule chose que le
         visiteur doit comprendre, et la comprendre après avoir vu deux montants donnerait
         l'impression d'un supplément caché. ═══ --}}
    @if ($licences->isNotEmpty() || $acces->isNotEmpty())
        <section class="bg-slate-50 border-y border-slate-200">
            <div class="max-w-6xl mx-auto pt-12 pb-4">
                <div class="px-5 sm:px-8">
                    <h2 class="text-2xl font-bold flex items-center gap-2.5">
                        <span class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                            <i data-lucide="network" class="w-5 h-5"></i>
                        </span>
                        Vous êtes un réseau
                    </h2>
                    <p class="text-[14px] text-slate-600 mt-2 max-w-2xl leading-relaxed">
                        Une vision, ses antennes et ses églises. Le tarif se lit en deux lignes.
                    </p>

                    {{-- Le schéma avant les deux lignes : « qui paie quoi » se comprend mieux
                         quand on a d'abord vu qui est où. --}}
                    <x-schema-structure class="mt-6 max-w-2xl" :legende="false" />

                    <div class="mt-6 grid gap-4 md:grid-cols-2 max-w-4xl">
                        <div class="rounded-2xl bg-white border border-slate-200 p-5">
                            <p class="inline-flex items-center gap-2 text-[13px] font-bold">
                                <span class="w-6 h-6 rounded-full bg-emerald-600 text-white text-[12px] flex items-center justify-center">1</span>
                                La vision paie la licence
                            </p>
                            <p class="text-[13.5px] text-slate-600 mt-2 leading-relaxed">
                                Elle met le système en service pour toute la structure et inclut
                                l'espace de la vision elle-même — <strong class="font-semibold">le
                                sommet ne paie pas deux fois</strong>. Son prix suit la taille du réseau.
                            </p>
                        </div>
                        <div class="rounded-2xl bg-white border border-slate-200 p-5">
                            <p class="inline-flex items-center gap-2 text-[13px] font-bold">
                                <span class="w-6 h-6 rounded-full bg-emerald-600 text-white text-[12px] flex items-center justify-center">2</span>
                                Chaque entité paie son accès
                            </p>
                            <p class="text-[13.5px] text-slate-600 mt-2 leading-relaxed">
                                Antenne, église ou cellule : le même prix pour le même palier.
                                Une cellule en Standard et une grande église en Premium peuvent
                                coexister dans le même réseau.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            @if ($licences->isNotEmpty())
                <div class="max-w-6xl mx-auto pb-8">
                    <h3 class="px-5 sm:px-8 text-[13px] font-bold uppercase tracking-wider text-slate-400 mb-4">
                        La licence — une seule, payée par la vision
                    </h3>
                    <div class="relative" data-bandeau>
                        <div data-bandeau-piste tabindex="0" role="region" aria-label="Licences de structure"
                             class="flex items-stretch gap-4 overflow-x-auto px-5 sm:px-8 pb-4
                                    snap-x snap-mandatory scroll-px-5 sm:scroll-px-8
                                    focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30 rounded-2xl">
                            @php $precedent = null; @endphp
                            @foreach ($licences as $plan)
                                <x-carte-offre :plan="$plan" :base="$precedent" :en-avant="$enAvant($plan)" />
                                @php $precedent = $plan; @endphp
                            @endforeach
                        </div>
                        {{-- Les voiles reprennent le fond de CETTE section, gris et non blanc :
                             un dégradé vers le blanc sur fond gris dessine une bande claire. --}}
                        <div data-bandeau-gauche aria-hidden="true"
                             class="pointer-events-none absolute inset-y-0 left-0 w-10 sm:w-14 bg-gradient-to-r from-slate-50 via-slate-50/80 to-transparent opacity-0 transition-opacity duration-200"></div>
                        <div data-bandeau-droite aria-hidden="true"
                             class="pointer-events-none absolute inset-y-0 right-0 w-10 sm:w-14 bg-gradient-to-l from-slate-50 via-slate-50/80 to-transparent transition-opacity duration-200"></div>
                    </div>
                </div>
            @endif

            @if ($acces->isNotEmpty())
                <div class="max-w-6xl mx-auto pb-10">
                    <h3 class="px-5 sm:px-8 text-[13px] font-bold uppercase tracking-wider text-slate-400 mb-4">
                        Les accès — un par entité, au palier qu'elle choisit
                    </h3>
                    <div class="relative" data-bandeau>
                        <div data-bandeau-piste tabindex="0" role="region" aria-label="Accès par entité"
                             class="flex items-stretch gap-4 overflow-x-auto px-5 sm:px-8 pb-4
                                    snap-x snap-mandatory scroll-px-5 sm:scroll-px-8
                                    focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30 rounded-2xl">
                            @php $precedent = null; @endphp
                            @foreach ($acces as $plan)
                                <x-carte-offre :plan="$plan" :base="$precedent" :en-avant="$enAvant($plan)" />
                                @php $precedent = $plan; @endphp
                            @endforeach
                        </div>
                        <div data-bandeau-gauche aria-hidden="true"
                             class="pointer-events-none absolute inset-y-0 left-0 w-10 sm:w-14 bg-gradient-to-r from-slate-50 via-slate-50/80 to-transparent opacity-0 transition-opacity duration-200"></div>
                        <div data-bandeau-droite aria-hidden="true"
                             class="pointer-events-none absolute inset-y-0 right-0 w-10 sm:w-14 bg-gradient-to-l from-slate-50 via-slate-50/80 to-transparent transition-opacity duration-200"></div>
                    </div>
                </div>
            @endif

            {{-- L'EXEMPLE CHIFFRÉ. C'est lui qui fait comprendre le modèle : deux lignes lues
                 restent abstraites, une addition posée ne l'est plus. Il est CALCULÉ à partir des
                 offres réelles — un exemple écrit en dur mentirait dès le premier changement de
                 prix, et le client s'en apercevrait au moment de la facture.

                 L'HORIZON EST CELUI DE LA LICENCE, PAS « douze » écrit en dur : si vous repassez
                 un jour la licence au trimestre, l'addition suit toute seule au lieu de raconter
                 une année qui n'existe plus. --}}
            @if ($licenceExemple && $accesExemple)
                @php
                    $prixLicence = $licenceExemple->prixPourTaille($exempleEntites);
                    $horizon = max(1, (int) $licenceExemple->periode_mois);
                    $echeancesAcces = intdiv($horizon, max(1, (int) $accesExemple->periode_mois));
                    $accesParEcheance = $exempleEntites * $accesExemple->prix_usd_cents;
                    $totalHorizon = $prixLicence['usd_cents'] + $echeancesAcces * $accesParEcheance;
                @endphp
                <div class="max-w-6xl mx-auto px-5 sm:px-8 pb-12">
                    <div class="rounded-2xl bg-white border border-slate-200 p-6 max-w-2xl">
                        <h3 class="font-bold flex items-center gap-2">
                            <i data-lucide="calculator" class="w-4 h-4 text-emerald-700"></i>
                            Un exemple concret
                        </h3>
                        <p class="text-[13.5px] text-slate-600 mt-2 leading-relaxed">
                            Une vision avec 1 antenne et 6 églises, soit
                            <strong class="font-semibold">{{ $exempleEntites }} entités</strong>, toutes en Standard :
                        </p>
                        <dl class="mt-4 space-y-2 text-[13.5px]">
                            <div class="flex items-baseline justify-between gap-4 border-b border-slate-100 pb-2">
                                <dt class="text-slate-600">
                                    {{ $licenceExemple->nom }} ({{ $exempleEntites }} entités)
                                    <span class="block text-[12px] text-slate-400">payée par la vision, par {{ $licenceExemple->libellePeriode() }}</span>
                                </dt>
                                <dd class="font-semibold tabular-nums whitespace-nowrap">{{ number_format($prixLicence['usd_cents'] / 100, 0, ',', ' ') }} $</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-4 border-b border-slate-100 pb-2">
                                <dt class="text-slate-600">
                                    {{ $exempleEntites }} × {{ $accesExemple->nom }}
                                    <span class="block text-[12px] text-slate-400">par {{ $accesExemple->libellePeriode() }}</span>
                                </dt>
                                <dd class="font-semibold tabular-nums whitespace-nowrap">{{ number_format($accesParEcheance / 100, 0, ',', ' ') }} $</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-4 pt-1">
                                <dt class="font-bold">Sur {{ $horizon }} mois</dt>
                                <dd class="text-xl font-bold tabular-nums whitespace-nowrap">{{ number_format($totalHorizon / 100, 0, ',', ' ') }} $</dd>
                            </div>
                        </dl>
                        <p class="text-[12.5px] text-slate-500 mt-4 leading-relaxed">
                            Une entité qui n'a besoin que de l'essentiel reste en Standard et paie
                            moins. Rien n'oblige tout le réseau à prendre le même palier.
                        </p>
                    </div>
                </div>
            @endif

            {{-- LA RÈGLE DE BORD. C'est la question que se pose un trésorier prudent : « et si
                 j'achète mon accès juste avant l'échéance de la vision ? ». Y répondre AVANT
                 qu'il la pose vaut mieux que de la traiter en réclamation. --}}
            <div class="max-w-6xl mx-auto px-5 sm:px-8 pb-12">
                <div class="flex gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-4 max-w-2xl">
                    <i data-lucide="calendar-clock" class="w-4 h-4 mt-0.5 shrink-0 text-emerald-700"></i>
                    <p class="text-[13.5px] text-slate-600 leading-relaxed">
                        <strong class="font-semibold text-slate-800">Un accès ne survit pas à la
                        licence qui le porte.</strong> S'il ne reste que quelques jours de licence
                        au moment où une église souscrit, son accès est facturé au prorata de ces
                        jours-là — elle ne paie jamais un mois que la licence ne couvre pas.
                        C'est aussi pour cela que la licence se paie à l'année : le cas devient rare.
                    </p>
                </div>
            </div>
        </section>
    @endif

    @if ($seule->isEmpty() && $licences->isEmpty() && $acces->isEmpty())
        <section class="max-w-6xl mx-auto px-5 sm:px-8 py-16 text-center">
            <i data-lucide="tag" class="w-8 h-8 text-slate-300 mx-auto mb-3"></i>
            <p class="font-semibold">La grille tarifaire n'est pas encore publiée.</p>
            <p class="text-[13.5px] text-slate-500 mt-1">
                <a href="{{ route('vitrine.contact') }}" class="text-emerald-700 font-semibold hover:underline">Écrivez-nous</a>,
                nous vous ferons une proposition.
            </p>
        </section>
    @endif

    {{-- LA RÈGLE DE CASCADE, DITE APRÈS LES PRIX ET AVANT L'ENGAGEMENT. Elle ne concerne que les
         réseaux, et la placer en tête de page ferait buter dessus une église seule que cela ne
         regarde pas. --}}
    @if ($licences->isNotEmpty())
        <section class="max-w-6xl mx-auto px-5 sm:px-8 py-10">
            <div class="flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 max-w-3xl">
                <i data-lucide="git-merge" class="w-4 h-4 mt-0.5 shrink-0 text-amber-700"></i>
                <p class="text-[13.5px] text-amber-900 leading-relaxed">
                    <strong class="font-bold">Si la licence expire, tout le réseau passe en lecture
                    seule</strong> — même les entités à jour de leur accès. De même, une église dont
                    l'accès expire ne peut plus écrire, mais ses données restent intactes et
                    consultables. C'est voulu : sans cette règle, chacun paierait pour lui et
                    personne pour l'ensemble.
                </p>
            </div>
        </section>
    @endif

    @if ($modes)
        <section class="max-w-6xl mx-auto px-5 sm:px-8 pb-16">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-6 py-6">
                <h2 class="font-bold">Modes de paiement acceptés</h2>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($modes as $mode)
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-slate-200 px-3 py-1.5 text-[13px] font-semibold">
                            <i data-lucide="{{ $mode['icone'] }}" class="w-3.5 h-3.5 text-emerald-700"></i>
                            {{ $mode['libelle'] }}
                        </span>
                    @endforeach
                </div>
                <a href="{{ route('vitrine.paiement') }}"
                   class="inline-flex items-center gap-1.5 mt-5 text-[13.5px] font-bold text-emerald-700 hover:underline">
                    Comment payer, étape par étape <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>
        </section>
    @endif

@endsection
