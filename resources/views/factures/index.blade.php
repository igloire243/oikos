@extends('layout')
@section('titre', 'Factures')

@php
    use App\Models\Facture;
    use App\Models\Paiement;

    $carte = 'bg-white border border-slate-200 rounded-2xl overflow-hidden';
    $champ = 'w-full rounded-xl border border-slate-300 px-3 py-2 text-[13.5px] shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none';
    $etiquette = 'block text-[12px] font-semibold text-slate-600 mb-1';

    $onglets = [
        'A_ENCAISSER' => 'À encaisser',
        Facture::PAYEE => 'Payées',
        Facture::ANNULEE => 'Annulées',
        'TOUTES' => 'Toutes',
    ];

    $tonsFacture = [
        Facture::EMISE => ['ambre', 'clock'],
        Facture::PAYEE => ['vert', 'circle-check'],
        Facture::ANNULEE => ['gris', 'ban'],
        Facture::IRRECOUVRABLE => ['rouge', 'circle-x'],
    ];
@endphp

@section('contenu')
    <h1 class="text-xl font-bold">Factures</h1>
    <p class="text-[13px] text-slate-500 mb-5">
        Le numéro de facture est la référence que le client cite en payant. Sans lui, un versement
        reçu ne se rattache à personne.
    </p>

    <div class="flex flex-wrap gap-1.5 mb-5">
        @foreach ($onglets as $code => $libelle)
            <a href="{{ route('factures.index', ['etat' => $code]) }}"
               class="px-3 py-1.5 rounded-lg text-[12.5px] font-semibold transition
                      {{ $filtre === $code ? 'bg-emerald-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                {{ $libelle }}
                @if ($code === 'A_ENCAISSER' && $aEncaisser > 0)
                    <span class="ml-1 tabular-nums">({{ $aEncaisser }})</span>
                @endif
            </a>
        @endforeach
    </div>

    @if ($factures->isEmpty())
        <div class="{{ $carte }} px-5 py-12 text-center">
            <i data-lucide="receipt" class="w-7 h-7 text-slate-300 mx-auto mb-2"></i>
            <p class="text-[13.5px] font-semibold">Aucune facture ici.</p>
            <p class="text-[13px] text-slate-500">Une facture naît avec chaque vente d'abonnement.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($factures as $facture)
                @php
                    [$ton, $icone] = $tonsFacture[$facture->statut] ?? ['gris', 'receipt'];
                    $reste = $facture->resteADevoir();
                @endphp

                <article class="{{ $carte }}">
                    <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <h2 class="font-bold text-[14.5px] flex flex-wrap items-center gap-2">
                                <code class="font-mono">{{ $facture->numero }}</code>
                                <x-etiq :ton="$ton" :icone="$icone">{{ $facture->statut }}</x-etiq>
                            </h2>
                            <p class="text-[12.5px] text-slate-500 mt-0.5">
                                {{ $facture->client?->nom ?? 'Client inconnu' }}
                                @if ($facture->abonnement?->plan)
                                    · {{ $facture->abonnement->plan->nom }}
                                @endif
                                @if ($facture->du_le)
                                    · échéance {{ $facture->du_le->format('d/m/Y') }}
                                @endif
                            </p>
                            @if ($facture->note)
                                <p class="text-[12.5px] text-slate-500 mt-1">{{ $facture->note }}</p>
                            @endif
                        </div>

                        <div class="text-right shrink-0">
                            <p class="text-xl font-bold tabular-nums">{{ number_format($facture->montant / 100, 2, ',', ' ') }} $</p>
                            @if ($reste > 0 && $reste < $facture->montant)
                                {{-- Le reste ne s'affiche QUE s'il y a eu un versement partiel :
                                     sur une facture intacte, il répéterait le montant. --}}
                                <p class="text-[12.5px] font-semibold text-amber-700 tabular-nums">
                                    reste {{ number_format($reste / 100, 2, ',', ' ') }} $
                                </p>
                            @endif
                        </div>
                    </div>

                    @if ($facture->paiements->isNotEmpty())
                        <div class="px-5 py-3 border-b border-slate-100 space-y-1.5">
                            @foreach ($facture->paiements as $paiement)
                                <div class="flex flex-wrap items-center gap-2 text-[12.5px]">
                                    <span class="font-semibold">{{ Paiement::FOURNISSEURS[$paiement->fournisseur] ?? $paiement->fournisseur }}</span>
                                    <code class="font-mono text-slate-500">{{ $paiement->reference }}</code>
                                    <span class="tabular-nums font-semibold">{{ number_format($paiement->montant / 100, 2, ',', ' ') }} $</span>

                                    @if ($paiement->statut === Paiement::CONFIRME)
                                        <x-etiq ton="vert" icone="circle-check">confirmé</x-etiq>
                                    @elseif ($paiement->statut === Paiement::ECHOUE)
                                        <x-etiq ton="gris" icone="circle-x">non reçu</x-etiq>
                                    @else
                                        <x-etiq ton="ambre" icone="clock">en attente</x-etiq>
                                    @endif

                                    @if ($paiement->statut === Paiement::EN_ATTENTE)
                                        <form method="POST" action="{{ route('paiements.confirmer', $paiement) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1 rounded-md bg-emerald-600 px-2 py-0.5 text-[11.5px] font-semibold text-white hover:bg-emerald-700 transition cursor-pointer">
                                                <i data-lucide="check" class="w-3 h-3"></i> Confirmer
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('paiements.rejeter', $paiement) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1 rounded-md bg-white border border-slate-300 px-2 py-0.5 text-[11.5px] font-semibold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                                                Non reçu
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($facture->statut === Facture::EMISE && $encaissementEnLigne && $reste > 0)
                        {{-- « Payer maintenant » : pousse une invite mobile money sur le téléphone du
                             client via l'agrégateur. N'encaisse rien — le versement est confirmé par
                             le webhook. N'apparaît qu'avec un agrégateur configuré. --}}
                        @php
                            $demandeEnCours = $facture->paiements->first(fn ($p) =>
                                $p->statut === Paiement::EN_ATTENTE && $p->order_number && ! $p->estExpire());
                        @endphp
                        <form method="POST" action="{{ route('paiements.en-ligne', $facture) }}"
                              class="px-5 py-4 border-b border-slate-100 bg-emerald-50/40 flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="min-w-[150px]">
                                <label for="op-{{ $facture->facture_id }}" class="{{ $etiquette }}">Opérateur</label>
                                <select id="op-{{ $facture->facture_id }}" name="operateur" class="{{ $champ }}">
                                    @foreach ($operateursEnLigne as $code => $libelle)
                                        <option value="{{ $code }}">{{ $libelle }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex-1 min-w-[170px]">
                                <label for="tel-{{ $facture->facture_id }}" class="{{ $etiquette }}">Téléphone du client</label>
                                <input id="tel-{{ $facture->facture_id }}" type="tel" name="telephone" required
                                       placeholder="0810000000" value="{{ old('telephone') }}"
                                       class="{{ $champ }} tabular-nums">
                            </div>
                            <div class="pb-1 text-[12.5px] text-slate-500">
                                à recevoir <span class="font-semibold tabular-nums text-slate-700">{{ number_format($reste / 100, 2, ',', ' ') }} $</span>
                            </div>
                            <button type="submit" @disabled($demandeEnCours)
                                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-[13px] font-semibold text-white hover:bg-emerald-700 transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                <i data-lucide="smartphone" class="w-4 h-4"></i>
                                {{ $demandeEnCours ? 'Demande en attente…' : 'Payer maintenant' }}
                            </button>
                            @if ($demandeEnCours)
                                <p class="w-full text-[12px] text-emerald-800">
                                    Une invite a été envoyée au {{ $demandeEnCours->telephone }} — le client doit valider sur son téléphone. Le versement s'affichera ici une fois confirmé par {{ $nomPasserelle }}.
                                </p>
                            @endif
                        </form>
                    @endif

                    @if ($facture->statut === Facture::EMISE)
                        <form method="POST" action="{{ route('paiements.enregistrer', $facture) }}"
                              class="px-5 py-4 bg-slate-50/70 flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="min-w-[170px]">
                                <label for="mode-{{ $facture->facture_id }}" class="{{ $etiquette }}">Mode</label>
                                <select id="mode-{{ $facture->facture_id }}" name="fournisseur" class="{{ $champ }}">
                                    @foreach ($fournisseurs as $code => $libelle)
                                        <option value="{{ $code }}">{{ $libelle }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex-1 min-w-[180px]">
                                <label for="ref-{{ $facture->facture_id }}" class="{{ $etiquette }}">Référence de l'opérateur</label>
                                <input id="ref-{{ $facture->facture_id }}" type="text" name="reference" required maxlength="120"
                                       placeholder="QGH7X92B4" class="{{ $champ }} font-mono">
                            </div>
                            <div class="w-32">
                                <label for="montant-{{ $facture->facture_id }}" class="{{ $etiquette }}">Montant ($)</label>
                                <input id="montant-{{ $facture->facture_id }}" type="number" step="0.01" min="0.01" name="montant"
                                       value="{{ number_format($reste / 100, 2, '.', '') }}" required
                                       class="{{ $champ }} tabular-nums">
                            </div>
                            <label class="flex items-center gap-2 text-[12.5px] font-semibold text-slate-700 pb-2">
                                <input type="checkbox" name="confirme" value="1" checked
                                       class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-600/30">
                                Argent reçu
                            </label>
                            <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-[13px] font-semibold text-white hover:bg-emerald-700 transition cursor-pointer">
                                <i data-lucide="banknote" class="w-4 h-4"></i>
                                Encaisser
                            </button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    <div class="flex gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-[13px] text-slate-600 mt-5">
        <i data-lucide="info" class="w-4 h-4 mt-0.5 shrink-0 text-slate-400"></i>
        <p>
            La <strong class="font-semibold">référence de l'opérateur</strong> ne peut être saisie
            qu'une fois par mode : c'est ce qui empêche d'enregistrer deux fois le même versement
            M-Pesa — l'erreur la plus banale quand on encaisse à la main, et celle qui offre un mois
            gratuit sans que personne s'en aperçoive. Un versement partiel est accepté : la facture
            ne se solde qu'une fois entièrement couverte.
        </p>
    </div>
@endsection
