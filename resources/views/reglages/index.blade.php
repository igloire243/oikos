@extends('layout')
@section('titre', 'Réglages')

@php
    $carte = 'bg-white border border-slate-200 rounded-2xl overflow-hidden mb-5';
    $enteteCarte = 'px-5 py-3.5 border-b border-slate-100 flex items-center gap-2 text-[13px] font-bold';
    $champ = 'w-full rounded-xl border border-slate-300 px-3 py-2 text-[13.5px] shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none';
    $etiquette = 'block text-[12.5px] font-semibold text-slate-700';
@endphp

@section('contenu')
    <h1 class="text-xl font-bold">Réglages</h1>
    <p class="text-[13px] text-slate-500 mb-6">
        Ce qui change sans toucher au code : le taux, ce que vous promettez, et où l'argent arrive.
    </p>

    <form method="POST" action="{{ route('reglages.enregistrer') }}">
        @csrf @method('PUT')

        @foreach ($parGroupe as $groupe => $reglages)
            <div class="{{ $carte }}">
                <div class="{{ $enteteCarte }}">
                    <i data-lucide="{{ $groupes[$groupe]['icone'] ?? 'settings' }}" class="w-4 h-4 text-slate-400"></i>
                    {{ $groupes[$groupe]['titre'] ?? ucfirst($groupe) }}
                </div>

                <div class="px-5 py-5 space-y-5">
                    @foreach ($reglages as $reglage)
                        @php $champId = 'r_'.$reglage->cle; @endphp

                        @if ($reglage->type === 'booleen')
                            {{-- La case cochée n'envoie « 1 » que si elle est cochée ; décochée,
                                 elle n'envoie rien. Le contrôleur en tient compte — il parcourt les
                                 réglages attendus, pas ceux qui sont arrivés. --}}
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" name="{{ $champId }}" value="1"
                                       @checked(old($champId, $reglage->valeur) == '1')
                                       class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600/30">
                                <span>
                                    <span class="block text-[13.5px] font-semibold">{{ $reglage->libelle }}</span>
                                    @if ($reglage->aide)
                                        <span class="block text-[12px] text-slate-500 mt-0.5 leading-relaxed">{{ $reglage->aide }}</span>
                                    @endif
                                </span>
                            </label>
                        @else
                            <div>
                                <label for="{{ $champId }}" class="{{ $etiquette }}">{{ $reglage->libelle }}</label>
                                @if ($reglage->aide)
                                    <p class="text-[12px] text-slate-500 mt-0.5 mb-1.5 leading-relaxed max-w-2xl">{{ $reglage->aide }}</p>
                                @else
                                    <div class="mb-1.5"></div>
                                @endif
                                <input id="{{ $champId }}"
                                       type="{{ $reglage->type === 'entier' ? 'number' : 'text' }}"
                                       @if ($reglage->type === 'entier') min="1" step="1" @endif
                                       name="{{ $champId }}"
                                       value="{{ old($champId, $reglage->valeur) }}"
                                       maxlength="255"
                                       class="{{ $champ }} {{ $reglage->type === 'entier' ? 'max-w-[220px] tabular-nums' : 'max-w-2xl' }}">
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- La barre d'enregistrement colle en bas : l'écran est long, et un bouton qu'il faut
             aller chercher tout en bas fait quitter la page sans enregistrer. --}}
        <div class="sticky bottom-0 -mx-5 sm:-mx-8 px-5 sm:px-8 py-4 bg-slate-50/95 backdrop-blur border-t border-slate-200">
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-[13px] font-bold text-white hover:bg-emerald-700 transition cursor-pointer shadow-sm">
                <i data-lucide="save" class="w-4 h-4"></i>
                Enregistrer les réglages
            </button>
        </div>
    </form>

    <div class="flex gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-[13px] text-slate-600 mt-5">
        <i data-lucide="info" class="w-4 h-4 mt-0.5 shrink-0 text-slate-400"></i>
        <p>
            Un mode de paiement actif mais <strong class="font-semibold">sans numéro</strong> est
            retiré du site public plutôt qu'affiché vide : « Orange Money : — » n'est pas un moyen
            de paiement, c'est une question posée au client. Les identifiants de base de données et
            les accès SMTP restent dans le <code class="font-mono bg-white border border-slate-200 rounded px-1 py-0.5">.env</code> —
            ce sont des secrets d'infrastructure, ils n'ont pas leur place dans un écran.
        </p>
    </div>
@endsection
