@extends('layout')
@section('titre', 'Vendre un abonnement')

@php
    use App\Models\Plan;

    $carte = 'bg-white border border-slate-200 rounded-2xl overflow-hidden mb-5';
    $enteteCarte = 'px-5 py-3.5 border-b border-slate-100 flex items-center gap-2 text-[13px] font-bold';
    $champ = 'w-full rounded-xl border border-slate-300 px-3 py-2 text-[13.5px] shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none';
    $etiquette = 'block text-[12.5px] font-semibold text-slate-700 mb-1';
    $btn = 'inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-[13px] font-bold text-white shadow-sm hover:bg-emerald-700 transition cursor-pointer';
@endphp

@section('contenu')
    <a href="{{ route('clients.fiche', $installation->client_id) }}"
       class="inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-slate-500 hover:text-slate-800 mb-3 transition">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Retour au client
    </a>

    <h1 class="text-xl font-bold">Vendre un abonnement</h1>
    <p class="text-[13px] text-slate-500 mb-6">
        Pour <strong class="font-semibold text-slate-700">{{ $entite->nom }}</strong>
        ({{ \App\Models\Entite::TYPES[$entite->type] ?? $entite->type }})
        — installation « {{ $installation->nom }} ».
    </p>

    {{-- L'ÉTAT DE LA LICENCE, ANNONCÉ AVANT DE CHOISIR. C'est lui qui décide de ce qu'on peut
         vendre en dessous : le découvrir sous forme de refus après avoir rempli le formulaire
         serait le faire deviner. --}}
    @if ($entite->type !== \App\Models\Entite::TYPE_VISION)
        <div class="mb-5 flex gap-3 rounded-2xl px-5 py-4
                    {{ $licence && $licence->ouvreLEcriture()
                       ? 'border border-emerald-200 bg-emerald-50'
                       : 'border border-amber-200 bg-amber-50' }}">
            <i data-lucide="{{ $licence && $licence->ouvreLEcriture() ? 'shield-check' : 'triangle-alert' }}"
               class="w-4 h-4 mt-0.5 shrink-0 {{ $licence && $licence->ouvreLEcriture() ? 'text-emerald-700' : 'text-amber-700' }}"></i>
            <p class="text-[13.5px] leading-relaxed {{ $licence && $licence->ouvreLEcriture() ? 'text-emerald-900' : 'text-amber-900' }}">
                @if (! $licence)
                    <strong class="font-bold">Cette installation n'a pas encore de licence.</strong>
                    Un accès vendu maintenant n'ouvrirait rien : vendez d'abord la licence à la Vision.
                @elseif (! $licence->ouvreLEcriture())
                    <strong class="font-bold">La licence est {{ mb_strtolower(\App\Models\Abonnement::STATUTS[$licence->statut] ?? $licence->statut) }}.</strong>
                    Régularisez-la avant de vendre en dessous.
                @else
                    Licence en cours : <strong class="font-bold">{{ $licence->plan?->nom }}</strong>,
                    jusqu'au {{ $licence->periode_fin?->format('d/m/Y') ?? '—' }}.
                    @if ($licence->plan?->plafond_acces)
                        Accès autorisés jusqu'au palier
                        <strong class="font-bold">{{ Plan::PALIERS[$licence->plan->plafond_acces] ?? $licence->plan->plafond_acces }}</strong>.
                    @endif
                @endif
            </p>
        </div>
    @endif

    <form method="POST" action="{{ route('abonnements.enregistrer', [$installation, $entite]) }}">
        @csrf

        <div class="{{ $carte }}">
            <div class="{{ $enteteCarte }}">
                <i data-lucide="tag" class="w-4 h-4 text-slate-400"></i> L'offre
            </div>
            <div class="px-5 py-5 space-y-2">
                @foreach ($offres as $offre)
                    @php $p = $offre['plan']; @endphp
                    <label class="flex items-start gap-3 rounded-xl border px-4 py-3 transition
                                  {{ $offre['empechement']
                                     ? 'border-slate-200 bg-slate-50 opacity-60 cursor-not-allowed'
                                     : 'border-slate-200 hover:bg-slate-50 cursor-pointer' }}">
                        <input type="radio" name="plan_id" value="{{ $p->plan_id }}"
                               @disabled((bool) $offre['empechement'])
                               @checked(old('plan_id') == $p->plan_id)
                               class="mt-1 border-slate-300 text-emerald-600 focus:ring-emerald-600/30">
                        <span class="flex-1 min-w-0">
                            <span class="flex flex-wrap items-baseline gap-2">
                                <span class="text-[13.5px] font-semibold">{{ $p->nom }}</span>
                                <x-etiq ton="gris">{{ Plan::NATURES[$p->nature] ?? $p->nature }}</x-etiq>
                                @unless ($p->is_public)
                                    <x-etiq ton="ambre" icone="eye-off">non publiée</x-etiq>
                                @endunless
                            </span>

                            <span class="block text-[13px] text-slate-600 mt-0.5">
                                <strong class="font-bold tabular-nums">{{ number_format($offre['prix']['usd_cents'] / 100, 2, ',', ' ') }} $</strong>
                                · {{ number_format($offre['prix']['cdf'], 0, ',', ' ') }} FC
                                · par {{ $p->libellePeriode() }}
                                @if ($offre['prix']['entites'] > 0)
                                    {{-- On dit sur quel effectif le palier a été calculé : un prix
                                         qui change sans explication passe pour une erreur. --}}
                                    <span class="text-slate-400">(palier pour {{ $offre['prix']['entites'] }} entités déclarées)</span>
                                @endif
                            </span>

                            @if ($offre['empechement'])
                                <span class="block text-[12.5px] text-amber-800 mt-1">{{ $offre['empechement'] }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="{{ $carte }}">
            <div class="{{ $enteteCarte }}">
                <i data-lucide="calendar-days" class="w-4 h-4 text-slate-400"></i> Période et payeur
            </div>
            <div class="px-5 py-5 space-y-4">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="debut" class="{{ $etiquette }}">Début de la période</label>
                        <input id="debut" type="date" name="debut" required
                               value="{{ old('debut', now()->toDateString()) }}" class="{{ $champ }}">
                    </div>
                    <div>
                        <label for="payeur" class="{{ $etiquette }}">Payeur (facultatif)</label>
                        <select id="payeur" name="payeur" class="{{ $champ }}">
                            <option value="">L'entité elle-même</option>
                            @foreach ($payeurs as $candidat)
                                <option value="{{ $candidat->type }}:{{ $candidat->ref }}"
                                        @selected(old('payeur') === $candidat->type.':'.$candidat->ref)>
                                    {{ \App\Models\Entite::TYPES[$candidat->type] ?? $candidat->type }} — {{ $candidat->nom }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[12px] text-slate-500 mt-1">
                            C'est ce qui permet à une vision de régler pour ses douze églises.
                            Chacune garde son abonnement et son échéance.
                        </p>
                    </div>
                </div>

                <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 cursor-pointer">
                    <input type="checkbox" name="ouvrir" value="1" @checked(old('ouvrir', true))
                           class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600/30">
                    <span>
                        <span class="block text-[13.5px] font-semibold">Ouvrir l'accès dès maintenant</span>
                        <span class="block text-[12px] text-slate-500 mt-0.5 leading-relaxed">
                            L'abonnement naît « impayé », ce qui ouvre l'écriture pendant le délai de
                            grâce. Un client qui vient de s'engager peut travailler pendant qu'il
                            organise son virement. Décochez pour n'ouvrir qu'au paiement.
                        </span>
                    </span>
                </label>

                <div>
                    <label for="note" class="{{ $etiquette }}">Note sur la facture</label>
                    <input id="note" type="text" name="note" maxlength="500" value="{{ old('note') }}"
                           placeholder="Tarif négocié, remise consentie…" class="{{ $champ }}">
                </div>
            </div>
        </div>

        <div class="flex gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-[13px] text-slate-600 mb-5">
            <i data-lucide="receipt" class="w-4 h-4 mt-0.5 shrink-0 text-slate-400"></i>
            <p>
                Une <strong class="font-semibold">facture numérotée</strong> est émise en même temps.
                Communiquez son numéro au client : c'est lui, et lui seul, qui rattachera son
                versement mobile money à cet abonnement.
            </p>
        </div>

        <button type="submit" class="{{ $btn }}">
            <i data-lucide="check" class="w-4 h-4"></i>
            Créer l'abonnement et la facture
        </button>
    </form>
@endsection
