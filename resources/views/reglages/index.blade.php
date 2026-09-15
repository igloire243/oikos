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

    {{-- ÉTAT VIDE EXPLICITE. Cet écran ne fait que dérouler les réglages PRÉSENTS EN BASE (voir
         ReglageController::index) : sur une installation où ReglageSeeder n'a pas été joué, la
         page s'affichait entre son titre et son bouton « Enregistrer », sans un seul champ et
         sans rien dire. Rien n'indiquait qu'il manquait une étape d'installation, et on cherchait
         le défaut du côté du code. --}}
    @if ($parGroupe->isEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-[13px] text-amber-900">
            <div class="flex gap-3">
                <i data-lucide="triangle-alert" class="w-4 h-4 mt-0.5 shrink-0"></i>
                <div>
                    <p class="font-semibold">Aucun réglage enregistré.</p>
                    <p class="mt-1">
                        Les valeurs de départ n'ont pas encore été posées sur cette installation.
                        Lancez&nbsp;:
                    </p>
                    <p class="mt-2">
                        <code class="font-mono bg-white border border-amber-200 rounded px-2 py-1">php artisan db:seed --class=ReglageSeeder</code>
                    </p>
                    <p class="mt-2 text-amber-800">
                        Puis rechargez cette page pour renseigner le taux, le délai promis et vos
                        coordonnées d'encaissement.
                    </p>
                </div>
            </div>
        </div>
    @else
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
    @endif

    {{-- ── PROMO « DÉCEMBRE OFFERT » ────────────────────────────────────────────────────
         Elle est planifiée au 1ᵉʳ décembre, mais la planification suppose un `schedule:run` en
         cron sur le serveur. Quand ce cron n'existe pas, la promo ne part jamais et l'oubli ne se
         voit qu'en janvier. Ce bouton rend l'opération faisable sans accès au terminal, et dit
         combien d'accès attendent AVANT qu'on clique. ──────────────────────────────────────── --}}
    <div class="rounded-2xl border border-slate-200 bg-white mt-5 overflow-hidden">
        <div class="flex items-center gap-2.5 px-5 py-3.5 border-b border-slate-100 text-[13px] font-bold text-slate-700">
            <i data-lucide="gift" class="w-4 h-4 text-emerald-600"></i>
            Promotion « mois des fêtes »
        </div>
        <div class="px-5 py-5">
            <p class="text-[13px] text-slate-600 leading-relaxed max-w-2xl">
                Décembre est offert sur <strong class="font-semibold">tous les accès</strong> — églises,
                cellules et antennes. Leur échéance recule d'un mois, sans facture. La licence annuelle
                n'est pas concernée. Un accès souscrit en novembre en bénéficie comme les autres.
            </p>

            {{-- Deux libellés sans verbe, à dessein : « 0 accès attendent son mois » se lisait mal
                 (le zéro prend le singulier en français) et toute tournure verbale oblige à
                 accorder sur un nombre qu'on ne connaît qu'à l'exécution. --}}
            <dl class="mt-4 flex flex-wrap gap-x-8 gap-y-2 text-[13px]">
                <div>
                    <dt class="text-slate-500">Accès à créditer pour {{ $promo['annee'] }}</dt>
                    <dd class="text-xl font-bold tabular-nums text-slate-900">{{ $promo['eligibles'] }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Déjà crédités cette année</dt>
                    <dd class="text-xl font-bold tabular-nums text-slate-900">{{ $promo['deja'] }}</dd>
                </div>
            </dl>

            <form method="POST" action="{{ route('reglages.offrir-decembre') }}" class="mt-4">
                @csrf
                <input type="hidden" name="annee" value="{{ $promo['annee'] }}">
                <button type="submit" @disabled($promo['eligibles'] === 0)
                        class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-[13px] font-bold transition
                               {{ $promo['eligibles'] === 0
                                    ? 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                    : 'bg-emerald-600 text-white hover:bg-emerald-700 cursor-pointer shadow-sm' }}">
                    <i data-lucide="gift" class="w-4 h-4"></i>
                    Offrir décembre {{ $promo['annee'] }}
                </button>
            </form>

            <p class="text-[12px] text-slate-400 mt-3 leading-relaxed max-w-2xl">
                L'opération peut être relancée sans risque : chaque accès ne reçoit son mois qu'une
                fois par année. Équivalent en ligne de commande&nbsp;:
                <code class="font-mono bg-slate-50 border border-slate-200 rounded px-1 py-0.5">php artisan abonnement:offrir-decembre</code>.
            </p>
        </div>
    </div>

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
