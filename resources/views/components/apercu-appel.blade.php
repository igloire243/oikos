{{-- APERÇU DE L'ÉCRAN D'APPEL NOMINAL.

     CE QUE C'EST, ET CE QUE CE N'EST PAS. Une illustration dessinée en HTML, dans le langage
     graphique du site — pas une capture d'écran, et elle ne se donne pas pour telle. Elle montre
     fidèlement ce que le produit fait : une liste de noms, quatre statuts d'un seul geste, une
     progression, un bouton d'enregistrement. Le jour où vous fournirez de vraies captures, elles
     vaudront mieux que ceci : une photographie du produit prouve qu'il existe, un dessin ne fait
     que le décrire.

     LES PRÉNOMS SONT VOLONTAIREMENT ORDINAIRES et ne désignent personne. Reprendre de vrais noms
     de membres sur une page publique serait une fuite, même avec leur accord — un site de vitrine
     n'est pas l'endroit où l'appartenance religieuse de quelqu'un se publie.

     `aria-hidden` : le contenu de cette maquette n'a aucun sens lu à voix haute — une liste de
     faux noms suivie de lettres isolées. Le texte alentour porte l'information. --}}

<div {{ $attributes->merge(['class' => 'w-full max-w-sm']) }} aria-hidden="true">
    <div class="rounded-3xl bg-white shadow-2xl shadow-emerald-950/30 border border-slate-200 overflow-hidden">

        <div class="px-5 py-4 border-b border-slate-100">
            <p class="text-[10.5px] font-bold uppercase tracking-wider text-slate-400">Appel nominal</p>
            <p class="text-[14px] font-bold text-slate-900 mt-0.5">Chorale · Culte du dimanche</p>

            <div class="mt-3 flex items-center gap-2.5">
                <div class="flex-1 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full w-3/5 rounded-full bg-emerald-500"></div>
                </div>
                <span class="text-[11.5px] font-bold text-slate-500 tabular-nums">6 / 10</span>
            </div>
        </div>

        <div class="divide-y divide-slate-100">
            @php
                // Le statut retenu par ligne : la maquette montre les quatre états possibles plutôt
                // qu'une colonne uniforme de « présent », qui ne dirait rien du fonctionnement.
                $lignes = [
                    ['Espérance M.', 'P'],
                    ['Josué K.', 'E'],
                    ['Grâce N.', 'P'],
                    ['Emmanuel T.', 'R'],
                ];

                $tons = [
                    'P' => 'bg-emerald-600 text-white',
                    'A' => 'bg-red-500 text-white',
                    'E' => 'bg-amber-500 text-white',
                    'R' => 'bg-sky-500 text-white',
                ];
            @endphp

            @foreach ($lignes as [$nom, $choisi])
                <div class="px-5 py-2.5 flex items-center gap-2">
                    <span class="text-[13px] font-semibold text-slate-700 flex-1 truncate">{{ $nom }}</span>
                    <div class="flex gap-1">
                        @foreach (['P', 'A', 'E', 'R'] as $statut)
                            <span class="w-7 h-7 rounded-lg flex items-center justify-center text-[11px] font-bold
                                         {{ $statut === $choisi ? $tons[$statut] : 'bg-slate-100 text-slate-400' }}">
                                {{ $statut }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="px-5 py-4 bg-slate-50 border-t border-slate-100">
            <div class="w-full rounded-xl bg-emerald-600 text-white text-[13px] font-bold py-2.5 text-center">
                Enregistrer l'appel
            </div>
            <p class="text-[11px] text-slate-500 text-center mt-2">
                Une fois enregistré, l'appel se ferme et ne se rouvre qu'avec un code.
            </p>
        </div>
    </div>
</div>
