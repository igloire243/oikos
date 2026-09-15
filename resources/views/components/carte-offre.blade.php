{{-- UNE CARTE D'OFFRE, pour les trois familles à la fois.

     La page des tarifs présente trois rangs — église seule, licence, accès — dont les cartes ne
     diffèrent que par deux détails : la licence montre une grille de tailles et son plafond
     d'accès, les autres non. Trois copies du même balisage divergeraient dès la première
     retouche, et la grille redeviendrait incohérente d'un rang à l'autre. --}}
@props(['plan', 'enAvant' => false, 'base' => null])

@php
    $tonsPalier = [
        \App\Models\Plan::STARTER => 'bg-slate-100 text-slate-600',
        \App\Models\Plan::STANDARD => 'bg-emerald-50 text-emerald-700',
        \App\Models\Plan::PREMIUM => 'bg-emerald-600 text-white',
    ];

    // SINGULIER ET PLURIEL. « 1 églises » se lisait sur l'offre église seule, dont le quota vaut
    // justement 1 : le seul endroit où la carte affiche un chiffre unitaire est aussi celui qu'un
    // prospect lit en premier. On garde donc les deux formes.
    $libellesQuotas = [
        'antennes' => ['antenne', 'antennes'],
        'extensions' => ['église', 'églises'],
        'membres' => ['membre', 'membres'],
        'comptes' => ['compte utilisateur', 'comptes utilisateurs'],
    ];
@endphp

<article class="snap-start shrink-0 w-[280px] sm:w-[320px] rounded-2xl bg-white p-6 flex flex-col
                {{ $enAvant ? 'border-2 border-emerald-500 shadow-lg shadow-emerald-600/10' : 'border border-slate-200' }}">

    <div class="flex items-center gap-2">
        <span class="inline-flex items-center rounded-md px-2 py-1 text-[11px] font-bold uppercase tracking-wider
                     {{ $tonsPalier[$plan->palier] ?? 'bg-slate-100 text-slate-600' }}">
            {{ \App\Models\Plan::PALIERS[$plan->palier] ?? $plan->palier }}
        </span>
        @if ($enAvant)
            <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Le plus courant</span>
        @endif
    </div>

    <h3 class="font-bold text-[15px] mt-3">{{ $plan->nom }}</h3>

    @if ($plan->argumentaire)
        <p class="text-[13px] text-slate-600 mt-1.5 leading-relaxed">{{ $plan->argumentaire }}</p>
    @endif

    {{-- LE PRIX. Pour une licence, le montant en gros caractères est le SOCLE : ce n'est pas le
         prix final, et le dire est la seule façon honnête de l'afficher. « À partir de » convient
         aux deux modes — formule comme anciennes tranches. --}}
    <div class="mt-5">
        @if ($plan->suitLaTaille())
            <p class="text-[11.5px] font-semibold text-slate-500">à partir de</p>
        @endif
        <p class="text-3xl font-bold tabular-nums">{{ $plan->prixAfficheUsd() }}</p>
        <p class="text-[13px] text-slate-500">
            {{ number_format($plan->prix_cdf, 0, ',', ' ') }} FC ·
            par {{ $plan->libellePeriode() }}
            @if ($plan->nature === \App\Models\Plan::ACCES)
                <span class="block">et par entité</span>
            @endif
        </p>
    </div>

    @if ($plan->estAnnuel())
        <p class="mt-2 inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2 py-1 text-[11.5px] font-semibold text-slate-600">
            <i data-lucide="calendar-check" class="w-3 h-3"></i>
            Engagement d'un an
        </p>
    @endif

    {{-- LA FORMULE, ÉCRITE EN TOUTES LETTRES ET CHIFFRÉE.
         Un tableau de tranches se relit trois fois ; une phrase et trois exemples se comprennent
         du premier coup. Les exemples sont CALCULÉS — recopiés à la main, ils mentiraient au
         premier changement de tarif, et le client s'en apercevrait sur sa facture. --}}
    @if ($plan->aUnTarifParEntite())
        <div class="mt-4 rounded-xl bg-slate-50 border border-slate-100 p-3">
            <p class="text-[10.5px] font-bold uppercase tracking-wider text-slate-400 mb-2">Selon la taille du réseau</p>
            <p class="text-[12.5px] text-slate-700 leading-relaxed">
                <strong class="font-bold">{{ $plan->prixUsd() }}</strong> par {{ $plan->libellePeriode() }},
                plus <strong class="font-bold">{{ $plan->parEntiteUsd() }}</strong> par entité.
            </p>
            <ul class="mt-2 space-y-1 border-t border-slate-200 pt-2">
                @foreach ([5, 20, 60] as $exemple)
                    <li class="flex items-baseline justify-between gap-3 text-[12.5px]">
                        <span class="text-slate-600">{{ $exemple }} entités</span>
                        <span class="font-bold tabular-nums whitespace-nowrap">
                            {{ number_format($plan->prixPourTaille($exemple)['usd_cents'] / 100, 0, ',', ' ') }} $
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @elseif ($plan->aUneGrilleDeTailles())
        <div class="mt-4 rounded-xl bg-slate-50 border border-slate-100 p-3">
            <p class="text-[10.5px] font-bold uppercase tracking-wider text-slate-400 mb-2">Selon la taille du réseau</p>
            <ul class="space-y-1">
                @foreach ($plan->paliers_taille as $echelon)
                    <li class="flex items-baseline justify-between gap-3 text-[12.5px]">
                        <span class="text-slate-600">
                            {{-- `max` nul = le palier sans plafond, celui qui attrape tout ce qui
                                 dépasse. Écrit « au-delà de N », il se lit ; laissé vide, il
                                 ressemblerait à une ligne oubliée. --}}
                            @if (($echelon['max'] ?? null) === null)
                                au-delà
                            @else
                                jusqu'à {{ $echelon['max'] }} entités
                            @endif
                        </span>
                        <span class="font-bold tabular-nums whitespace-nowrap">
                            {{ number_format(($echelon['prix_usd_cents'] ?? 0) / 100, 0, ',', ' ') }} $
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($plan->plafond_acces && $plan->estLicence())
        <p class="mt-3 flex items-start gap-1.5 text-[12.5px] text-slate-600">
            <i data-lucide="shield-check" class="w-3.5 h-3.5 mt-0.5 shrink-0 text-emerald-600"></i>
            Accès jusqu'au palier <strong class="font-semibold">{{ \App\Models\Plan::PALIERS[$plan->plafond_acces] }}</strong>
            pour les entités du réseau
        </p>
    @endif

    @if ($plan->quotas)
        <ul class="mt-5 space-y-1.5 border-t border-slate-100 pt-4">
            @foreach ($plan->quotas as $cle => $valeur)
                <li class="flex items-start gap-2 text-[13px] text-slate-700">
                    <i data-lucide="check" class="w-3.5 h-3.5 mt-0.5 shrink-0 text-emerald-600"></i>
                    <span>
                        {{-- null = sans limite. C'est un avantage, pas une donnée manquante :
                             il faut l'écrire, pas laisser un vide. --}}
                        @php
                            $formes = $libellesQuotas[$cle] ?? [$cle, $cle];
                            // « Sans limite de » appelle toujours le pluriel : « sans limite de membre »
                            // se lirait comme une faute.
                            $libelle = $formes[($valeur !== null && (int) $valeur <= 1) ? 0 : 1];
                        @endphp
                        {{ $valeur === null ? 'Sans limite de' : number_format((int) $valeur, 0, ',', ' ') }}
                        {{ $libelle }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- LES MODULES — PAR DIFFÉRENCE AVEC LE PALIER PRÉCÉDENT.

         Réafficher les quinze modules du Standard dans la carte Premium produisait des
         colonnes d'une hauteur d'écran que personne ne compare : l'œil relit deux fois la même
         liste au lieu de repérer ce qui change. « Tout ce que contient Standard, plus… » dit la
         même chose en trois lignes, et met en évidence la seule information qui décide d'un
         achat — l'écart.

         L'HÉRITAGE N'EST AFFIRMÉ QUE S'IL EST VRAI : on vérifie que le palier précédent est
         RÉELLEMENT inclus. Si vous composez un jour une offre Premium qui retire un module du
         Standard, la carte repasse d'elle-même à la liste complète plutôt que d'annoncer un
         « tout ce qui précède » mensonger. --}}
    @php
        $tous = $plan->fonctionnalites;
        $deBase = $base?->fonctionnalites;

        $herite = is_array($tous) && is_array($deBase) && $deBase !== []
            && array_diff($deBase, $tous) === [];

        $aMontrer = $herite ? array_values(array_diff($tous, $deBase)) : ($tous ?? []);

        // Au-delà de huit puces, la carte s'allonge plus qu'elle n'informe : on replie. En dessous,
        // un volet fermé ferait cliquer pour rien.
        $replier = count($aMontrer) > 8;
    @endphp

    <div class="mt-4 border-t border-slate-100 pt-4">
        <p class="text-[10.5px] font-bold uppercase tracking-wider text-slate-400 mb-2">Modules ouverts</p>

        @if ($tous === null)
            <p class="text-[13px] font-semibold text-emerald-700">Tous les modules</p>
        @else
            @if ($herite)
                <p class="flex items-start gap-1.5 text-[12.5px] font-semibold text-emerald-700 mb-2">
                    <i data-lucide="corner-down-right" class="w-3.5 h-3.5 mt-0.5 shrink-0"></i>
                    Tout ce que contient {{ \App\Models\Plan::PALIERS[$base->palier] ?? $base->palier }}@if ($aMontrer), plus :@endif
                </p>
            @endif

            @if ($aMontrer)
                @if ($replier)
                    <details class="group">
                        <summary class="list-none [&::-webkit-details-marker]:hidden cursor-pointer inline-flex items-center gap-1 text-[12.5px] font-semibold text-emerald-700">
                            <i data-lucide="chevron-right" class="w-3.5 h-3.5 transition-transform group-open:rotate-90"></i>
                            {{ count($aMontrer) }} modules — voir le détail
                        </summary>
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            @foreach ($aMontrer as $cle)
                                <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11.5px] font-semibold text-slate-600">
                                    <i data-lucide="{{ \App\Support\Modules::icone($cle) }}" class="w-3 h-3"></i>
                                    {{ \App\Support\Modules::libelle($cle) }}
                                </span>
                            @endforeach
                        </div>
                    </details>
                @else
                    <div class="flex flex-wrap gap-1.5">
                        {{-- Les libellés viennent du catalogue, jamais de la clé brute : une offre
                             qui annonce « discipulariat » au lieu de « Discipulariat et baptêmes »
                             a l'air d'une fuite de code sur une page de vente. --}}
                        @foreach ($aMontrer as $cle)
                            <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11.5px] font-semibold text-slate-600">
                                <i data-lucide="{{ \App\Support\Modules::icone($cle) }}" class="w-3 h-3"></i>
                                {{ \App\Support\Modules::libelle($cle) }}
                            </span>
                        @endforeach
                    </div>
                @endif
            @endif
        @endif
    </div>

    {{-- `mt-auto` colle le bouton en bas : les cartes d'un même rang prennent la hauteur de la plus
         grande, et sans cela les boutons flotteraient à des hauteurs différentes. --}}
    <div class="mt-auto pt-6">
        <a href="{{ route('vitrine.contact') }}"
           class="w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-[13px] font-bold transition
                  {{ $enAvant ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-slate-900 text-white hover:bg-slate-800' }}">
            Demander cette offre
        </a>
    </div>
</article>
