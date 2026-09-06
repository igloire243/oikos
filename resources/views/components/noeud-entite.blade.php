@props(['noeud', 'installation', 'niveau' => 0])

{{--
    UNE BRANCHE DE L'ARBRE, ET SA DESCENDANCE — récursif.

    LE PLIAGE NE DEMANDE AUCUN JAVASCRIPT. <details>/<summary> est fait exactement pour ça : le
    navigateur ouvre et ferme tout seul, l'état survit à l'impression, ça se pilote au clavier, et
    ça continue de fonctionner si un script échoue à charger. Une implémentation maison en JS aurait
    coûté trente lignes pour rendre le même service en moins bien.

    UNE FEUILLE N'EST PAS UN <details>. Une entité sans enfant n'a rien à déplier : lui donner un
    chevron inerte apprendrait aux yeux à ignorer les chevrons, y compris ceux qui servent. On lui
    laisse à la place un retrait équivalent, pour que les libellés d'un même niveau s'alignent.

    LES DEUX PREMIERS NIVEAUX SONT OUVERTS À L'ARRIVÉE. La Vision et ses antennes se voient tout de
    suite — c'est la vue d'ensemble qu'on vient chercher. Les cellules, souvent nombreuses, restent
    pliées : sur un réseau réel, tout ouvrir d'emblée redonnerait la liste illisible qu'on remplace.
--}}

@php
    $entite = $noeud['entite'];
    $enfants = $noeud['enfants'] ?? [];
@endphp

@if ($enfants !== [])
    <details class="group/branche" @if ($niveau < 2) open @endif>
        <summary class="flex items-start gap-1 cursor-pointer list-none rounded hover:bg-slate-100/70 -ml-1 pl-1">
            {{-- Le chevron pivote à l'ouverture. Le groupe est NOMMÉ : ces branches s'imbriquent,
                 et un groupe anonyme ferait pivoter les chevrons des parents en même temps. --}}
            <i data-lucide="chevron-right"
               class="w-3.5 h-3.5 mt-[7px] shrink-0 text-slate-400 transition-transform group-open/branche:rotate-90"></i>

            <div class="min-w-0 flex-1">
                <x-ligne-entite :entite="$entite" :installation="$installation" />
            </div>

            <span class="mt-[5px] shrink-0 text-[11px] font-semibold text-slate-400 tabular-nums">
                {{ count($enfants) }}
            </span>
        </summary>

        {{-- Le filet vertical à gauche est ce qui rend la parenté lisible d'un coup d'œil : sans
             lui, on relit les retraits pour savoir de qui dépend quoi. --}}
        <div class="ml-[6px] border-l border-slate-200 pl-3">
            @foreach ($enfants as $enfant)
                <x-noeud-entite :noeud="$enfant" :installation="$installation" :niveau="$niveau + 1" />
            @endforeach
        </div>
    </details>
@else
    <div class="flex items-start gap-1 pl-[14px]">
        <x-ligne-entite :entite="$entite" :installation="$installation" />
    </div>
@endif
