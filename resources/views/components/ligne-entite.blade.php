@props(['entite', 'installation'])

{{--
    UNE LIGNE DE L'ARBRE — l'entité, son abonnement, et le geste commercial qui va avec.

    Elle est dans son propre fichier parce qu'elle est rendue à DEUX endroits : dans le <summary>
    d'une branche qui a des enfants, et telle quelle pour une feuille. La dupliquer aurait garanti
    qu'un jour l'une des deux copies reçoive une correction et pas l'autre — et le jour où le bouton
    « Vendre » ne marcherait que sur les églises sans cellules, personne ne saurait pourquoi.
--}}

@php
    $abonnement = $entite->abonnement();

    $tons = [
        \App\Models\Entite::TYPE_VISION => 'bleu',
        \App\Models\Entite::TYPE_ANTENNE => 'gris',
        \App\Models\Entite::TYPE_EXTENSION => 'gris',
    ];
@endphp

<div class="flex flex-wrap items-center gap-1.5 text-[12.5px] py-1">
    <x-etiq :ton="$tons[$entite->type] ?? 'gris'">
        {{ \App\Models\Entite::TYPES[$entite->type] ?? $entite->type }}
    </x-etiq>

    <span class="font-medium">{{ $entite->nom }}</span>

    @if ($entite->sous_type)
        <span class="text-slate-400">· {{ $entite->sous_type }}</span>
    @endif

    @if ($abonnement)
        <x-etiq :ton="$abonnement->ouvreLEcriture() ? 'vert' : 'rouge'"
                :icone="$abonnement->ouvreLEcriture() ? 'circle-check' : 'circle-x'">{{ \App\Models\Abonnement::STATUTS[$abonnement->statut] }}</x-etiq>

        @if ($abonnement->periode_fin)
            <span class="text-[12px] text-slate-400">jusqu'au {{ $abonnement->periode_fin->format('d/m/Y') }}</span>
        @endif

        {{-- Ce formulaire vit à l'intérieur d'un <summary> quand la branche a des enfants. Le clic
             sur le bouton ne doit donc PAS être compris comme un clic sur le résumé, sinon
             renouveler un abonnement replierait la branche au passage. --}}
        <form method="POST" action="{{ route('abonnements.renouveler', $abonnement) }}" class="inline"
              onclick="event.stopPropagation()">
            @csrf
            <button type="submit"
                    class="inline-flex items-center gap-1 rounded-md bg-white border border-slate-300 px-2 py-0.5 text-[11.5px] font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                <i data-lucide="refresh-cw" class="w-3 h-3"></i> Renouveler
            </button>
        </form>

        {{-- SUSPENDRE / RÉACTIVER. AbonnementController::changerStatut() existait déjà, route
             comprise, mais AUCUNE vue ne l'appelait : on pouvait vendre et renouveler, jamais
             interrompre. Or c'est le seul levier face à un client qui ne paie pas, et c'est aussi
             ce qui permet de vérifier que la fermeture d'un accès produit bien ses effets chez lui.

             Le `stopPropagation` répond au même besoin que pour « Renouveler » ci-dessus : replier
             la branche en cliquant serait déroutant. --}}
        <form method="POST" action="{{ route('abonnements.statut', $abonnement) }}" class="inline"
              onclick="event.stopPropagation()">
            @csrf
            @if ($abonnement->ouvreLEcriture())
                <input type="hidden" name="statut" value="{{ \App\Models\Abonnement::SUSPENDU }}">
                <button type="submit"
                        title="Fermer l'accès de cette entité sans résilier son abonnement"
                        onclick="return confirm('Suspendre l'abonnement de « {{ $entite->nom }} » ? Son espace se fermera à sa prochaine synchronisation.')"
                        class="inline-flex items-center gap-1 rounded-md bg-white border border-slate-300 px-2 py-0.5 text-[11.5px] font-semibold text-amber-700 hover:bg-amber-50">
                    <i data-lucide="pause" class="w-3 h-3"></i> Suspendre
                </button>
            @else
                <input type="hidden" name="statut" value="{{ \App\Models\Abonnement::ACTIF }}">
                <button type="submit"
                        title="Réouvrir l'accès de cette entité"
                        class="inline-flex items-center gap-1 rounded-md bg-white border border-slate-300 px-2 py-0.5 text-[11.5px] font-semibold text-emerald-700 hover:bg-emerald-50">
                    <i data-lucide="play" class="w-3 h-3"></i> Réactiver
                </button>
            @endif
        </form>
    @else
        <x-etiq ton="ambre" icone="circle-alert">sans abonnement</x-etiq>

        <a href="{{ route('abonnements.creer', [$installation, $entite]) }}"
           onclick="event.stopPropagation()"
           class="inline-flex items-center gap-1 rounded-md bg-emerald-600 px-2 py-0.5 text-[11.5px] font-semibold text-white hover:bg-emerald-700 transition">
            <i data-lucide="plus" class="w-3 h-3"></i> Vendre
        </a>
    @endif
</div>
