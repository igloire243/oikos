@extends('layout')
@section('titre', 'Demandes')

@php
    $carte = 'bg-white border border-slate-200 rounded-2xl overflow-hidden';
    $champ = 'w-full rounded-xl border border-slate-300 px-3 py-2 text-[13.5px] shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none';

    $tons = [
        'NOUVELLE' => ['bleu', 'mail'],
        'LUE' => ['ambre', 'mail-open'],
        'TRAITEE' => ['vert', 'circle-check'],
        'SPAM' => ['gris', 'ban'],
    ];

    $onglets = ['OUVERTES' => 'À traiter'] + \App\Models\Demande::STATUTS + ['TOUTES' => 'Toutes'];
@endphp

@section('contenu')
    <div class="flex flex-wrap items-start gap-4 mb-5">
        <div class="flex-1 min-w-[220px]">
            <h1 class="text-xl font-bold">Demandes</h1>
            <p class="text-[13px] text-slate-500">Ce qui arrive par le formulaire du site public.</p>
        </div>
        <a href="{{ route('vitrine.contact') }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 rounded-xl bg-white border border-slate-300 px-4 py-2.5 text-[13px] font-semibold text-slate-700 hover:bg-slate-50 transition">
            <i data-lucide="external-link" class="w-4 h-4"></i>
            Voir le formulaire
        </a>
    </div>

    <div class="flex flex-wrap gap-1.5 mb-5">
        @foreach ($onglets as $code => $libelle)
            <a href="{{ route('demandes.index', ['statut' => $code]) }}"
               class="px-3 py-1.5 rounded-lg text-[12.5px] font-semibold transition
                      {{ $statut === $code ? 'bg-emerald-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                {{ $libelle }}
                @if ($code === 'OUVERTES' && $nouvelles > 0)
                    <span class="ml-1 tabular-nums">({{ $nouvelles }})</span>
                @endif
            </a>
        @endforeach
    </div>

    @if ($demandes->isEmpty())
        <div class="{{ $carte }} px-5 py-12 text-center">
            <i data-lucide="inbox" class="w-7 h-7 text-slate-300 mx-auto mb-2"></i>
            <p class="text-[13.5px] font-semibold">Rien ici.</p>
            <p class="text-[13px] text-slate-500">Aucune demande ne correspond à ce filtre.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($demandes as $demande)
                @php [$ton, $icone] = $tons[$demande->statut] ?? ['gris', 'mail']; @endphp
                <article class="{{ $carte }}">
                    <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <h2 class="font-bold text-[14.5px]">
                                {{ $demande->nom }}
                                @if ($demande->organisation)
                                    <span class="font-normal text-slate-500">· {{ $demande->organisation }}</span>
                                @endif
                            </h2>
                            <p class="text-[12.5px] text-slate-500 mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                                {{-- Adresse et téléphone cliquables : la réponse à une demande part
                                     presque toujours dans la minute qui suit sa lecture. --}}
                                <a href="mailto:{{ $demande->email }}" class="inline-flex items-center gap-1 text-emerald-700 hover:underline">
                                    <i data-lucide="mail" class="w-3 h-3"></i> {{ $demande->email }}
                                </a>
                                @if ($demande->telephone)
                                    <a href="tel:{{ $demande->telephone }}" class="inline-flex items-center gap-1 text-emerald-700 hover:underline">
                                        <i data-lucide="phone" class="w-3 h-3"></i> {{ $demande->telephone }}
                                    </a>
                                @endif
                                @if ($demande->ville)
                                    <span class="inline-flex items-center gap-1"><i data-lucide="map-pin" class="w-3 h-3"></i> {{ $demande->ville }}</span>
                                @endif
                                @if ($demande->niveau)
                                    <span class="inline-flex items-center gap-1"><i data-lucide="layers" class="w-3 h-3"></i> {{ \App\Models\Entite::TYPES[$demande->niveau] ?? $demande->niveau }}</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <x-etiq :ton="$ton" :icone="$icone">{{ \App\Models\Demande::STATUTS[$demande->statut] ?? $demande->statut }}</x-etiq>
                            <span class="text-[12px] text-slate-400 whitespace-nowrap">{{ $demande->cree_le?->diffForHumans() }}</span>
                        </div>
                    </div>

                    <div class="px-5 py-4">
                        {{-- CE TEXTE VIENT D'UN INCONNU. `{{ }}` échappe le HTML : jamais de
                             `{!! !!}` ici, sous aucun prétexte de mise en forme. `whitespace-pre-line`
                             respecte ses retours à la ligne sans interpréter quoi que ce soit. --}}
                        <p class="text-[13.5px] text-slate-700 leading-relaxed whitespace-pre-line">{{ $demande->message }}</p>
                    </div>

                    <form method="POST" action="{{ route('demandes.marquer', $demande) }}"
                          class="px-5 py-4 bg-slate-50/70 border-t border-slate-100 flex flex-wrap items-end gap-3">
                        @csrf
                        <div class="flex-1 min-w-[200px]">
                            <label for="note-{{ $demande->demande_id }}" class="block text-[12px] font-semibold text-slate-600 mb-1">Note interne</label>
                            <input id="note-{{ $demande->demande_id }}" type="text" name="note_interne"
                                   value="{{ $demande->note_interne }}" maxlength="5000"
                                   placeholder="Ce qui a été convenu, la suite à donner…" class="{{ $champ }}">
                        </div>
                        <div>
                            <label for="statut-{{ $demande->demande_id }}" class="block text-[12px] font-semibold text-slate-600 mb-1">État</label>
                            <select id="statut-{{ $demande->demande_id }}" name="statut" class="{{ $champ }}">
                                @foreach (\App\Models\Demande::STATUTS as $code => $libelle)
                                    <option value="{{ $code }}" @selected($demande->statut === $code)>{{ $libelle }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-[13px] font-semibold text-white hover:bg-emerald-700 transition cursor-pointer">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            Enregistrer
                        </button>
                    </form>
                </article>
            @endforeach
        </div>
    @endif
@endsection
