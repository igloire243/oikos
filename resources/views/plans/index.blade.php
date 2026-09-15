@extends('layout')
@section('titre', 'Offres')

@php
    use App\Models\Plan;

    $carte = 'bg-white border border-slate-200 rounded-2xl overflow-hidden mb-5';
    $enteteCarte = 'px-5 py-3.5 border-b border-slate-100 flex items-center gap-2 text-[13px] font-bold';
    $th = 'text-left text-[10.5px] font-bold uppercase tracking-wider text-slate-400 px-5 py-2.5 bg-slate-50/70 border-b border-slate-200';
    $td = 'px-5 py-3 border-b border-slate-100 text-[13.5px] align-top';

    $icones = [Plan::LICENCE => 'key-round', Plan::ACCES => 'user-round-check', Plan::COMBINEE => 'church'];

    $explications = [
        Plan::LICENCE => "Payée une fois par la vision. Elle met le système en service pour toute la structure et inclut l'espace de la vision — le sommet ne paie pas d'accès en plus. Son prix suit la taille du réseau, et son palier plafonne ce que les entités peuvent souscrire en dessous.",
        Plan::ACCES => "Payé par chaque antenne, église ou cellule, pour son propre usage. Même prix quel que soit le niveau : c'est ce qui rend la grille explicable en une phrase.",
        Plan::COMBINEE => "Licence et accès en un seul prix, pour une église sans réseau au-dessus d'elle. Volontairement inférieur à la somme des deux.",
    ];
@endphp

@section('contenu')
    <div class="flex flex-wrap items-start gap-4 mb-5">
        <div class="flex-1 min-w-[220px]">
            <h1 class="text-xl font-bold">Offres</h1>
            <p class="text-[13px] text-slate-500">
                Une licence pour la structure, un accès par entité — et un raccourci tout compris
                pour l'église seule.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('reglages.index') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-white border border-slate-300 px-4 py-2.5 text-[13px] font-semibold text-slate-700 hover:bg-slate-50 transition">
                <i data-lucide="sliders-horizontal" class="w-4 h-4"></i> Réglages
            </a>
            <a href="{{ route('plans.creer') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-[13px] font-semibold text-white shadow-sm hover:bg-emerald-700 transition">
                <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle offre
            </a>
        </div>
    </div>

    @forelse ($parNature as $nature => $plans)
        <div class="{{ $carte }}">
            <div class="{{ $enteteCarte }}">
                <i data-lucide="{{ $icones[$nature] ?? 'tag' }}" class="w-4 h-4 text-slate-400"></i>
                {{ Plan::NATURES[$nature] ?? $nature }}
                <span class="ml-auto text-[11px] font-semibold text-slate-400">{{ $plans->count() }} offre(s)</span>
            </div>

            @if (isset($explications[$nature]))
                <p class="px-5 py-3 text-[12.5px] text-slate-500 leading-relaxed border-b border-slate-100 bg-slate-50/50">
                    {{ $explications[$nature] }}
                </p>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="{{ $th }}">Offre</th>
                            <th class="{{ $th }}">Prix</th>
                            <th class="{{ $th }}">Quotas</th>
                            <th class="{{ $th }}">Modules ouverts</th>
                            <th class="{{ $th }}">Visibilité</th>
                            <th class="{{ $th }}"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($plans as $p)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="{{ $td }}">
                                <span class="font-semibold">{{ $p->nom }}</span>
                                @if ($p->palier)
                                    <x-etiq ton="gris">{{ Plan::PALIERS[$p->palier] ?? $p->palier }}</x-etiq>
                                @endif
                                <br>
                                <code class="font-mono text-[11px] text-slate-400">{{ $p->code }}</code>
                                @if ($p->plafond_acces && $p->estLicence())
                                    <p class="text-[12px] text-slate-500 mt-1">
                                        Accès plafonné à {{ Plan::PALIERS[$p->plafond_acces] ?? $p->plafond_acces }}
                                    </p>
                                @endif
                            </td>

                            <td class="{{ $td }} whitespace-nowrap">
                                <span class="font-bold tabular-nums">{{ $p->prixUsd() }}</span>
                                <span class="text-[12px] text-slate-500">/ {{ $p->libellePeriode() }}</span><br>
                                <span class="text-[12px] text-slate-500 tabular-nums">{{ number_format($p->prix_cdf, 0, ',', ' ') }} FC</span>

                                @if ($p->aUnTarifParEntite())
                                    {{-- La formule tient sur une ligne : pas de volet à déplier. --}}
                                    <span class="block mt-1.5 text-[12px] font-semibold text-emerald-700 tabular-nums">
                                        + {{ $p->parEntiteUsd() }} par entité
                                    </span>
                                @elseif ($p->aUneGrilleDeTailles())
                                    {{-- La grille de tailles est repliée : dans la console, on la
                                         consulte pour vérifier un prix, on ne la lit pas à chaque
                                         passage. Dépliée, elle triplerait la hauteur du tableau. --}}
                                    <details class="mt-1.5">
                                        <summary class="text-[12px] font-semibold text-emerald-700 cursor-pointer">Selon la taille</summary>
                                        <ul class="mt-1 space-y-0.5">
                                            @foreach ($p->paliers_taille as $echelon)
                                                <li class="text-[12px] text-slate-500 tabular-nums">
                                                    {{ ($echelon['max'] ?? null) === null ? 'au-delà' : '≤ '.$echelon['max'] }} :
                                                    {{ number_format(($echelon['prix_usd_cents'] ?? 0) / 100, 0, ',', ' ') }} $
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                            </td>

                            <td class="{{ $td }} text-[12.5px] text-slate-500">
                                @if ($p->quotas)
                                    @foreach ($p->quotas as $cle => $valeur)
                                        <span class="block">{{ $valeur === null ? '∞' : $valeur }} {{ $cle }}</span>
                                    @endforeach
                                @else
                                    —
                                @endif
                            </td>

                            <td class="{{ $td }} text-[12px] text-slate-500 max-w-[240px]">
                                @if ($p->fonctionnalites === null)
                                    Tous
                                @else
                                    {{ implode(', ', array_map(fn ($c) => \App\Support\Modules::libelle($c), $p->fonctionnalites)) }}
                                @endif
                            </td>

                            <td class="{{ $td }}">
                                @if ($p->is_public)
                                    <x-etiq ton="vert" icone="globe">Publique</x-etiq>
                                @else
                                    <x-etiq ton="gris" icone="eye-off">Non publiée</x-etiq>
                                @endif
                                @if ($p->abonnements_count)
                                    <p class="text-[12px] text-slate-500 mt-1 tabular-nums">
                                        {{ $p->abonnements_count }} abonnement(s)
                                    </p>
                                @endif
                            </td>

                            <td class="{{ $td }} text-right whitespace-nowrap">
                                <a href="{{ route('plans.editer', $p) }}"
                                   class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-slate-300 px-2.5 py-1.5 text-[12px] font-semibold text-slate-700 hover:bg-slate-50 transition">
                                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Modifier
                                </a>

                                <form method="POST" action="{{ route('plans.visibilite', $p) }}" class="inline">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-slate-300 px-2.5 py-1.5 text-[12px] font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                                        <i data-lucide="{{ $p->is_public ? 'eye-off' : 'globe' }}" class="w-3.5 h-3.5"></i>
                                        {{ $p->is_public ? 'Retirer' : 'Publier' }}
                                    </button>
                                </form>

                                {{-- La suppression n'est proposée QUE si aucun abonnement ne s'y
                                     rattache. Le contrôleur refuse de toute façon — mais un bouton
                                     qui échoue toujours use la confiance dans tous les autres. --}}
                                @if (! $p->abonnements_count)
                                    <form method="POST" action="{{ route('plans.supprimer', $p) }}" class="inline"
                                          onsubmit="return confirm('Supprimer définitivement « {{ $p->nom }} » ?')">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-red-300 px-2.5 py-1.5 text-[12px] font-semibold text-red-700 hover:bg-red-50 transition cursor-pointer">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="{{ $carte }}">
            <div class="px-5 py-10 text-center">
                <i data-lucide="tag" class="w-7 h-7 text-slate-300 mx-auto mb-2"></i>
                <p class="text-[13.5px] font-semibold">Aucune offre.</p>
                <p class="text-[13px] text-slate-500">
                    Lancez <code class="font-mono bg-slate-100 rounded px-1.5 py-0.5">php artisan db:seed --class=PlanSeeder</code>
                    pour créer les offres de départ.
                </p>
            </div>
        </div>
    @endforelse

    <div class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-900">
        <i data-lucide="triangle-alert" class="w-4 h-4 mt-0.5 shrink-0 text-amber-600"></i>
        <p>Les prix sont des valeurs de travail, à ajuster avant la première vente. Les montants en
        francs supposent un taux d'environ 2 800 FC pour un dollar — une grille qui traîne un vieux
        taux perd de l'argent en silence.</p>
    </div>
@endsection
