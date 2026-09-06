@extends('layout')
@section('titre', 'Clients')

@php
    $carte = 'bg-white border border-slate-200 rounded-2xl overflow-hidden';
    $th = 'text-left text-[10.5px] font-bold uppercase tracking-wider text-slate-400 px-5 py-2.5 bg-slate-50/70 border-b border-slate-200';
    $td = 'px-5 py-3 border-b border-slate-100 text-[13.5px]';
    $champ = 'w-full rounded-xl border border-slate-300 px-3 py-2 text-[13.5px] shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none';
    $btn = 'inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-[13px] font-semibold text-white shadow-sm hover:bg-emerald-700 transition cursor-pointer';
    $btnDiscret = 'inline-flex items-center gap-2 rounded-xl bg-white border border-slate-300 px-4 py-2.5 text-[13px] font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer';

    // Le ton de chaque état, décidé ici plutôt que dans la boucle : on lit d'un coup d'œil la
    // convention de couleurs, et on la corrige en un seul endroit.
    $tons = [
        'ACTIF' => ['vert', 'circle-check'],
        'PROSPECT' => ['bleu', 'circle-dashed'],
        'SUSPENDU' => ['ambre', 'pause'],
        'RESILIE' => ['gris', 'circle-slash'],
    ];
@endphp

@section('contenu')
    <div class="flex flex-wrap items-start gap-4 mb-5">
        <div class="flex-1 min-w-[220px]">
            <h1 class="text-xl font-bold">Clients</h1>
            <p class="text-[13px] text-slate-500">Les communautés à qui vous vendez.</p>
        </div>
        <a href="{{ route('clients.creer') }}" class="{{ $btn }}">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Nouveau client
        </a>
    </div>

    <div class="{{ $carte }}">
        <form method="GET" class="flex items-end gap-2.5 px-5 py-4 border-b border-slate-100">
            <div class="flex-1">
                <label for="q" class="block text-[12px] font-semibold text-slate-600 mb-1">Rechercher</label>
                <input id="q" type="text" name="q" value="{{ $q }}" placeholder="Nom de la communauté" class="{{ $champ }}">
            </div>
            <button type="submit" class="{{ $btnDiscret }}">
                <i data-lucide="search" class="w-4 h-4"></i>
                <span class="hidden sm:inline">Filtrer</span>
            </button>
        </form>

        @if ($clients->isEmpty())
            <div class="px-5 py-10 text-center">
                <i data-lucide="users" class="w-7 h-7 text-slate-300 mx-auto mb-2"></i>
                <p class="text-[13.5px] font-semibold">Aucun client.</p>
                <p class="text-[13px] text-slate-500">{{ $q ? 'Aucun résultat pour cette recherche.' : 'Créez le premier pour commencer.' }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="{{ $th }}">Communauté</th>
                            <th class="{{ $th }}">Lieu</th>
                            <th class="{{ $th }}">Contact</th>
                            <th class="{{ $th }}">Installations</th>
                            <th class="{{ $th }}">État</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($clients as $c)
                        @php [$ton, $icone] = $tons[$c->statut] ?? ['gris', 'circle']; @endphp
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="{{ $td }}">
                                <a href="{{ route('clients.fiche', $c) }}" class="font-semibold text-emerald-700 hover:underline">{{ $c->nom }}</a>
                            </td>
                            <td class="{{ $td }} text-slate-500">{{ trim($c->ville.', '.$c->pays, ', ') ?: '—' }}</td>
                            <td class="{{ $td }} text-slate-500">{{ $c->contact_nom ?: '—' }}</td>
                            <td class="{{ $td }} tabular-nums">{{ $c->installations_count }}</td>
                            <td class="{{ $td }}">
                                <x-etiq :ton="$ton" :icone="$icone">{{ \App\Models\Client::STATUTS[$c->statut] }}</x-etiq>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
