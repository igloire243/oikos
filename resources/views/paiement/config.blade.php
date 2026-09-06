@extends('layout')
@section('titre', 'Paiement')

@php
    $carte = 'bg-white border border-slate-200 rounded-2xl overflow-hidden mb-5';
    $enteteCarte = 'px-5 py-3.5 border-b border-slate-100 flex items-center gap-2 text-[13px] font-bold';
    $champ = 'w-full rounded-xl border border-slate-300 px-3 py-2 text-[13.5px] shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none';
    $etiquette = 'block text-[12.5px] font-semibold text-slate-700 mb-1';
@endphp

@section('contenu')
    <h1 class="text-xl font-bold">Encaissement en ligne</h1>
    <p class="text-[13px] text-slate-500 mb-6">
        L'agrégateur mobile money et ses identifiants. Sans agrégateur actif, tout se fait à la main
        dans les Factures — c'est le mode par défaut, parfaitement fonctionnel.
    </p>

    @if (session('ok'))
        <div class="mb-5 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-[13px] font-semibold text-emerald-800">
            {{ session('ok') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-5 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-[13px] font-semibold text-rose-800 space-y-1">
            @foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
    @endif

    <div class="mb-5 rounded-xl border px-4 py-3 text-[13px] font-semibold
        {{ $passerelle->estActive() ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-slate-50 border-slate-200 text-slate-600' }}">
        État actuel : {{ $passerelle->estActive() ? 'actif — '.$passerelle->nom() : 'inactif (encaissement manuel)' }}
    </div>

    <form method="POST" action="{{ route('paiement.config.update') }}"
          x-data="{ choix: @js($choisi) }">
        @csrf @method('PUT')

        <div class="{{ $carte }}">
            <div class="{{ $enteteCarte }}">
                <i data-lucide="wallet" class="w-4 h-4 text-slate-400"></i> Agrégateur
            </div>
            <div class="px-5 py-5 space-y-5">
                <div>
                    <label class="{{ $etiquette }}">Choix de l'agrégateur</label>
                    <select name="agregateur" x-model="choix" class="{{ $champ }}">
                        @foreach ($agregateurs as $cle => $info)
                            <option value="{{ $cle }}" @selected($cle === $choisi)>{{ $info['nom'] }}</option>
                        @endforeach
                    </select>
                </div>

                <label class="flex items-center gap-2.5 text-[13.5px] font-semibold text-slate-700"
                       x-show="choix !== 'AUCUN'">
                    <input type="checkbox" name="actif" value="1" @checked($actif)
                           class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    Activer l'encaissement en ligne (le bouton « Payer maintenant » apparaît)
                </label>
            </div>
        </div>

        @foreach ($agregateurs as $cle => $info)
            @if (! empty($info['champs']))
                <div class="{{ $carte }}" x-show="choix === @js($cle)" x-cloak>
                    <div class="{{ $enteteCarte }}">
                        <i data-lucide="key-round" class="w-4 h-4 text-slate-400"></i>
                        Identifiants — {{ $info['nom'] }}
                    </div>
                    <div class="px-5 py-5 space-y-5">
                        @foreach ($info['champs'] as $champCle => $champDef)
                            @php $defini = ($champDef['secret'] ?? false) && ! empty($secrets[$champCle]); @endphp
                            <div>
                                <label class="{{ $etiquette }}">{{ $champDef['libelle'] }}</label>
                                @if ($champDef['secret'] ?? false)
                                    <input type="password" name="champs[{{ $champCle }}]" autocomplete="new-password"
                                           placeholder="{{ $defini ? '●●●●●● (défini — laisser vide pour ne pas changer)' : '(vide)' }}"
                                           class="{{ $champ }}">
                                @else
                                    <input type="text" name="champs[{{ $champCle }}]"
                                           value="{{ $cle === $choisi ? ($secrets[$champCle] ?? $champDef['defaut'] ?? '') : ($champDef['defaut'] ?? '') }}"
                                           class="{{ $champ }}">
                                @endif
                            </div>
                        @endforeach
                        <p class="text-[12px] text-slate-400">
                            Les champs secrets sont chiffrés en base et ne sont jamais réaffichés.
                        </p>
                    </div>
                </div>
            @endif
        @endforeach

        <button type="submit"
                class="rounded-xl bg-emerald-600 px-5 py-2.5 text-[13.5px] font-bold text-white shadow-sm hover:bg-emerald-700">
            Enregistrer
        </button>
    </form>
@endsection
