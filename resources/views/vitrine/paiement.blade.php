@extends('vitrine.layout')
@section('titre', 'Comment payer')
@section('resume', "Régler son abonnement Oikos : mobile money, virement ou espèces. La marche à suivre et le délai de réouverture de l'accès.")

@section('contenu')

    <section class="max-w-4xl mx-auto px-5 sm:px-8 pt-14 pb-8">
        <h1 class="text-3xl sm:text-4xl font-bold">Comment payer</h1>
        <p class="text-[15px] text-slate-600 mt-3 leading-relaxed">
            Quatre étapes, dans cet ordre. La troisième est celle qu'on oublie, et c'est celle qui
            fait qu'un paiement est retrouvé ou non.
        </p>
    </section>

    <section class="max-w-4xl mx-auto px-5 sm:px-8 pb-10">
        <ol class="space-y-4">
            @php
                $etapes = [
                    ["Vous demandez le renouvellement", "Depuis votre système, à l'approche de l'échéance, ou en nous écrivant. Précisez l'entité concernée : la vision, une antenne, ou une église."],
                    ["Nous émettons la facture", "Elle porte un numéro, un montant et une échéance. Vous la recevez avant de payer — jamais après."],
                    ["Vous payez EN CITANT LE NUMÉRO DE FACTURE", "C'est ce numéro, et lui seul, qui rattache votre versement à votre abonnement. Un paiement reçu sans référence est un paiement que nous ne pouvons attribuer à personne : il faut alors vous retrouver, et cela prend des jours."],
                    ["Nous confirmons et l'accès se prolonge", "Le paiement est enregistré, la facture soldée, et la période d'abonnement repoussée d'autant."],
                ];
            @endphp
            @foreach ($etapes as $i => [$titre, $texte])
                <li class="flex gap-4 rounded-2xl border border-slate-200 p-5">
                    <span class="w-8 h-8 shrink-0 rounded-full bg-emerald-600 text-white font-bold text-[14px] flex items-center justify-center">{{ $i + 1 }}</span>
                    <div>
                        <h2 class="font-bold text-[14.5px]">{{ $titre }}</h2>
                        <p class="text-[13.5px] text-slate-600 mt-1.5 leading-relaxed">{{ $texte }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="max-w-4xl mx-auto px-5 sm:px-8 pb-10">
        <h2 class="text-2xl font-bold">Où verser</h2>

        @if ($modes)
            @if ($titulaire)
                <p class="text-[13.5px] text-slate-600 mt-2">
                    Tous les comptes ci-dessous sont au nom de <strong class="font-semibold">{{ $titulaire }}</strong>.
                    {{-- Dire au nom de QUI est le compte n'est pas une formalité : c'est ce qui
                         permet à un client de vérifier qu'il ne verse pas à un homonyme, et de
                         reconnaître une fausse coordonnée qu'on lui aurait transmise. --}}
                </p>
            @endif

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                @foreach ($modes as $mode)
                    <div class="rounded-2xl border border-slate-200 p-5">
                        <div class="flex items-center gap-2.5">
                            <span class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                                <i data-lucide="{{ $mode['icone'] }}" class="w-4 h-4"></i>
                            </span>
                            <h3 class="font-bold text-[14px]">{{ $mode['libelle'] }}</h3>
                        </div>

                        @if ($mode['numero'])
                            <p class="mt-3 font-mono text-[15px] font-bold select-all break-all">{{ $mode['numero'] }}</p>
                            @if ($mode['banque'])
                                <p class="text-[12.5px] text-slate-500">{{ $mode['banque'] }}</p>
                            @endif
                        @endif

                        @if ($mode['instructions'])
                            <p class="text-[13px] text-slate-600 mt-2.5 leading-relaxed">{{ $mode['instructions'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            {{-- Aucun mode configuré : plutôt qu'une page vide, on renvoie vers le contact. Une
                 grille de paiement sans coordonnées ferait douter de l'existence du service. --}}
            <div class="mt-4 flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4">
                <i data-lucide="triangle-alert" class="w-4 h-4 mt-0.5 shrink-0 text-amber-600"></i>
                <p class="text-[13.5px] text-amber-900">
                    Les coordonnées de paiement ne sont pas encore publiées.
                    <a href="{{ route('vitrine.contact') }}" class="font-bold underline">Écrivez-nous</a>,
                    nous vous les transmettrons directement.
                </p>
            </div>
        @endif
    </section>

    <section class="max-w-4xl mx-auto px-5 sm:px-8 pb-16">
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-6 py-5">
            <h2 class="font-bold text-emerald-900 flex items-center gap-2">
                <i data-lucide="clock" class="w-4 h-4"></i> Délai de réouverture
            </h2>
            <p class="text-[13.5px] text-emerald-900/85 mt-2 leading-relaxed">
                Un accès suspendu est rétabli sous <strong class="font-bold">{{ $delai }}</strong>
                après confirmation du paiement. Les paiements par virement, en espèces ou par dépôt
                marchand demandent une vérification humaine : ils ne sont pas confirmés
                automatiquement.
            </p>
            <p class="text-[13.5px] text-emerald-900/85 mt-3 leading-relaxed">
                Pendant une suspension, <strong class="font-bold">vos données restent intactes et
                consultables</strong> : c'est l'écriture qui est fermée, pas la lecture. Vous ne
                perdez rien, et vous retrouvez tout en l'état.
            </p>
        </div>
    </section>

@endsection
