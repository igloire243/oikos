@extends('vitrine.layout')
@section('titre', 'Fonctionnalités')
@section('resume', "Les modules d'Oikos : membres, départements, plannings, appel nominal, cultes, activités, discipulariat, trésorerie, rapports et médias.")

@section('contenu')

    <section class="max-w-6xl mx-auto px-5 sm:px-8 pt-14 pb-8">
        <h1 class="text-3xl sm:text-4xl font-bold">Ce que le système fait</h1>
        <p class="text-[15px] text-slate-600 mt-3 max-w-2xl leading-relaxed">
            {{ $nombreModules }} modules, répartis sur deux espaces qui ne s'adressent pas aux mêmes
            personnes — celui d'une église au quotidien, et celui du siège qui voit l'ensemble. Tous
            s'appuient sur le même fichier de membres et les mêmes départements : rien n'est à
            ressaisir de l'un à l'autre.
        </p>
    </section>

    {{-- DEUX SECTIONS, PAS UNE SEULE LISTE. Un pasteur d'église cherche ce qu'il fera le dimanche ;
         un responsable de réseau cherche ce qu'il verra depuis le siège. Mélangés, les deux se
         perdent — et l'on donne à croire que le produit est plus petit qu'il n'est. --}}
    @foreach ($familles as $famille => $modulesFamille)
        <section class="max-w-6xl mx-auto px-5 sm:px-8 pb-10">
            <div class="flex flex-wrap items-baseline gap-3 mb-5">
                <h2 class="text-xl font-bold">{{ $nomsFamilles[$famille] ?? $famille }}</h2>
                <span class="text-[12.5px] font-semibold text-slate-400">{{ count($modulesFamille) }} modules</span>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($modulesFamille as $module)
                    <div class="rounded-2xl border border-slate-200 p-6">
                        <span class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                            <i data-lucide="{{ $module['icone'] }}" class="w-5 h-5"></i>
                        </span>
                        <h3 class="font-bold mt-4">{{ $module['nom'] }}</h3>
                        <p class="text-[13.5px] text-slate-600 mt-1.5 leading-relaxed">{{ $module['texte'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    {{-- CE QUI DISTINGUE VRAIMENT LE PRODUIT, et qui ne se voit pas dans une liste de modules :
         la façon dont les départements sont définis une seule fois pour toute la communauté, et
         l'appel qui se fait sur place. Chacun reçoit son illustration — ce sont deux mécanismes,
         et un mécanisme se montre mieux qu'il ne se raconte. --}}
    <section class="bg-slate-50 border-y border-slate-200 mt-8">
        <div class="max-w-6xl mx-auto px-5 sm:px-8 py-16 space-y-16">

            <div class="grid gap-8 md:grid-cols-2 md:items-center">
                <div>
                    <h2 class="text-2xl font-bold">Les départements se définissent une seule fois</h2>
                    <p class="text-[14px] text-slate-600 mt-3 leading-relaxed">
                        « Chorale », « Protocole », « École du dimanche » n'existent pas quinze fois,
                        une par église. Ils sont définis au niveau de la vision, et chaque église y
                        rattache ses membres. Un rapport sur la Chorale est alors possible pour une
                        église, pour une antenne, ou pour l'ensemble — sans rapprocher quinze listes
                        qui ne portent pas le même nom.
                    </p>
                </div>
                <x-schema-structure :legende="false" class="rounded-2xl bg-white border border-slate-200 p-6" />
            </div>

            <div class="grid gap-8 md:grid-cols-2 md:items-center">
                <div class="md:order-2">
                    <h2 class="text-2xl font-bold">L'appel nominal se fait sur place</h2>
                    <p class="text-[14px] text-slate-600 mt-3 leading-relaxed">
                        Depuis un téléphone, pendant la répétition ou le culte : présent, absent,
                        excusé, en retard. Une fois enregistré, l'appel se ferme et ne se modifie
                        plus qu'avec un code — pour que le bilan du mois reflète ce qui s'est passé,
                        et non ce qu'on a reconstitué le trentième jour.
                    </p>
                </div>
                <div class="md:order-1 flex justify-center">
                    <x-apercu-appel />
                </div>
            </div>

        </div>
    </section>

    <section class="max-w-6xl mx-auto px-5 sm:px-8 py-16 text-center">
        <h2 class="text-2xl font-bold">Tous les modules ne sont pas ouverts partout</h2>
        <p class="text-[14px] text-slate-600 mt-3 max-w-2xl mx-auto leading-relaxed">
            Une licence ouvre l'espace de la vision, un accès celui d'une église. La grille
            tarifaire indique, pour chaque offre, exactement quels modules elle contient.
        </p>
        <a href="{{ route('vitrine.tarifs') }}"
           class="inline-flex items-center gap-2 mt-7 rounded-xl bg-emerald-600 px-5 py-3 text-[14px] font-bold text-white hover:bg-emerald-700 transition">
            <i data-lucide="tag" class="w-4 h-4"></i> Voir les tarifs
        </a>
    </section>

@endsection
