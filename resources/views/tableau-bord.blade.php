@extends('layout')
@section('titre', 'Tableau de bord')

@php
    $tuile = 'bg-white border border-slate-200 rounded-2xl px-5 py-4';
    $carte = 'bg-white border border-slate-200 rounded-2xl overflow-hidden mb-5';
    $enteteCarte = 'px-5 py-3.5 border-b border-slate-100 flex items-center gap-2 text-[13px] font-bold';
    $th = 'text-left text-[10.5px] font-bold uppercase tracking-wider text-slate-400 px-5 py-2.5 bg-slate-50/70 border-b border-slate-200';
    $td = 'px-5 py-3 border-b border-slate-100 text-[13.5px]';
@endphp

@section('contenu')
    <h1 class="text-xl font-bold">Tableau de bord</h1>
    <p class="text-[13px] text-slate-500 mb-6">Ce qui demande votre attention aujourd'hui.</p>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
        <div class="{{ $tuile }}">
            <p class="text-[10.5px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                <i data-lucide="users" class="w-3 h-3"></i> Clients actifs
            </p>
            <p class="text-2xl font-bold mt-1">{{ $clients_actifs }}</p>
            <p class="text-[12px] text-slate-500">sur {{ $clients_total }} au total</p>
        </div>
        <div class="{{ $tuile }}">
            <p class="text-[10.5px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                <i data-lucide="server" class="w-3 h-3"></i> Installations
            </p>
            <p class="text-2xl font-bold mt-1">{{ $installations }}</p>
            <p class="text-[12px] text-slate-500">en service</p>
        </div>
        <div class="{{ $tuile }}">
            <p class="text-[10.5px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                <i data-lucide="clock" class="w-3 h-3"></i> À échéance
            </p>
            <p class="text-2xl font-bold mt-1 {{ $a_echeance->count() ? 'text-amber-600' : '' }}">{{ $a_echeance->count() }}</p>
            <p class="text-[12px] text-slate-500">dans 15 jours</p>
        </div>
        <div class="{{ $tuile }}">
            <p class="text-[10.5px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                <i data-lucide="circle-alert" class="w-3 h-3"></i> Impayés
            </p>
            <p class="text-2xl font-bold mt-1 {{ $impayes->count() ? 'text-red-600' : '' }}">{{ $impayes->count() }}</p>
            <p class="text-[12px] text-slate-500">à relancer</p>
        </div>
    </div>

    {{-- LES INSTALLATIONS MUETTES EN PREMIER. Une installation qui ne s'est pas manifestée depuis
         trois jours a perdu son cron, son hébergement ou son client — et dans les trois cas, il
         vaut mieux l'apprendre avant que le pasteur n'appelle. --}}
    @if ($muettes->isNotEmpty())
        <div class="{{ $carte }} border-amber-200">
            <h2 class="{{ $enteteCarte }} bg-amber-50/60 border-amber-100 text-amber-900">
                <i data-lucide="triangle-alert" class="w-4 h-4 text-amber-600"></i>
                Installations sans nouvelles
                <span class="ml-auto text-[11px] font-bold text-amber-700">{{ $muettes->count() }}</span>
            </h2>
            <table class="w-full">
                <thead><tr>
                    <th class="{{ $th }}">Client</th><th class="{{ $th }}">Installation</th>
                    <th class="{{ $th }}">Version</th><th class="{{ $th }}">Dernier contact</th>
                </tr></thead>
                <tbody>
                @foreach ($muettes as $i)
                    <tr class="hover:bg-slate-50/60">
                        <td class="{{ $td }}"><a href="{{ route('clients.fiche', $i->client) }}" class="text-emerald-700 font-semibold hover:underline">{{ $i->client->nom }}</a></td>
                        <td class="{{ $td }}">{{ $i->nom }}</td>
                        <td class="{{ $td }} text-slate-500">{{ $i->version ?: '—' }}</td>
                        <td class="{{ $td }}">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                <i data-lucide="clock" class="w-3 h-3"></i>{{ $i->vue_le?->diffForHumans() ?? 'jamais' }}
                            </span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($impayes->isNotEmpty())
        <div class="{{ $carte }}">
            <h2 class="{{ $enteteCarte }}">
                <i data-lucide="credit-card" class="w-4 h-4 text-red-500"></i>
                Impayés <span class="ml-auto text-[11px] font-bold text-slate-400">{{ $impayes->count() }}</span>
            </h2>
            <table class="w-full">
                <thead><tr>
                    <th class="{{ $th }}">Client</th><th class="{{ $th }}">Entité</th>
                    <th class="{{ $th }}">Offre</th><th class="{{ $th }}">Échéance</th><th class="{{ $th }}">État</th>
                </tr></thead>
                <tbody>
                @foreach ($impayes as $a)
                    <tr class="hover:bg-slate-50/60">
                        <td class="{{ $td }}"><a href="{{ route('clients.fiche', $a->installation->client) }}" class="text-emerald-700 font-semibold hover:underline">{{ $a->installation->client->nom }}</a></td>
                        <td class="{{ $td }} text-slate-600">{{ $a->beneficiaire_type }} n° {{ $a->beneficiaire_ref }}</td>
                        <td class="{{ $td }}">{{ $a->plan->nom }}</td>
                        <td class="{{ $td }} text-slate-600">{{ $a->periode_fin?->translatedFormat('j M Y') ?? '—' }}</td>
                        <td class="{{ $td }}">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-bold border
                                {{ $a->statut === 'SUSPENDU' ? 'bg-red-50 text-red-800 border-red-200' : 'bg-amber-50 text-amber-800 border-amber-200' }}">
                                <i data-lucide="{{ $a->statut === 'SUSPENDU' ? 'lock' : 'clock' }}" class="w-3 h-3"></i>
                                {{ \App\Models\Abonnement::STATUTS[$a->statut] }}
                            </span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="{{ $carte }}">
        <h2 class="{{ $enteteCarte }}">
            <i data-lucide="calendar-clock" class="w-4 h-4 text-slate-400"></i>
            Échéances des 15 prochains jours
            <span class="ml-auto text-[11px] font-bold text-slate-400">{{ $a_echeance->count() }}</span>
        </h2>
        @if ($a_echeance->isEmpty())
            <div class="px-5 py-10 text-center">
                <i data-lucide="calendar-check" class="w-8 h-8 text-slate-300 mx-auto"></i>
                <p class="font-semibold text-sm mt-2">Rien à relancer.</p>
                <p class="text-[13px] text-slate-500">Aucun abonnement n'arrive à échéance d'ici quinze jours.</p>
            </div>
        @else
            <table class="w-full">
                <thead><tr>
                    <th class="{{ $th }}">Client</th><th class="{{ $th }}">Entité</th>
                    <th class="{{ $th }}">Offre</th><th class="{{ $th }}">Échéance</th>
                </tr></thead>
                <tbody>
                @foreach ($a_echeance as $a)
                    <tr class="hover:bg-slate-50/60">
                        <td class="{{ $td }}"><a href="{{ route('clients.fiche', $a->installation->client) }}" class="text-emerald-700 font-semibold hover:underline">{{ $a->installation->client->nom }}</a></td>
                        <td class="{{ $td }} text-slate-600">{{ $a->beneficiaire_type }} n° {{ $a->beneficiaire_ref }}</td>
                        <td class="{{ $td }}">{{ $a->plan->nom }}</td>
                        <td class="{{ $td }} text-slate-600">{{ $a->periode_fin?->translatedFormat('j M Y') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if ($clients_total === 0)
        <div class="bg-white border border-slate-200 rounded-2xl px-5 py-12 text-center">
            <span class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 inline-flex items-center justify-center border border-emerald-100">
                <i data-lucide="users" class="w-7 h-7"></i>
            </span>
            <p class="font-bold mt-3">Aucun client pour l'instant.</p>
            <p class="text-[13px] text-slate-500 mt-0.5 mb-4">Créez-en un, puis ajoutez-lui son installation.</p>
            <a href="{{ route('clients.creer') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold transition">
                <i data-lucide="plus" class="w-4 h-4"></i> Créer le premier client
            </a>
        </div>
    @endif
@endsection
