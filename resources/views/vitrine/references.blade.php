@extends('vitrine.layout')
@section('titre', 'Références')
@section('resume', "Les communautés qui utilisent Oikos et ont accepté d'être citées.")

@section('contenu')

    <section class="max-w-6xl mx-auto px-5 sm:px-8 pt-14 pb-8">
        <h1 class="text-3xl sm:text-4xl font-bold">Ils utilisent {{ config('produit.nom') }}</h1>
        <p class="text-[15px] text-slate-600 mt-3 max-w-2xl leading-relaxed">
            Cette page ne montre que les communautés qui ont donné leur accord pour y figurer.
            Beaucoup d'autres utilisent le système sans souhaiter être nommées, et c'est leur droit.
        </p>
    </section>

    @if ($clients->isEmpty())
        {{-- L'ÉTAT VIDE EST HONNÊTE, et il le reste. La tentation, sur une page de références, est
             de remplir avec des noms inventés ou des logos « à titre d'exemple ». Un prospect qui
             s'en aperçoit ne revient pas — et un nom inventé qui ressemble à une vraie église est
             un mensonge, pas une maquette. Tant que personne n'a accepté, la page le dit. --}}
        <section class="max-w-6xl mx-auto px-5 sm:px-8 pb-16">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-6 py-12 text-center">
                <i data-lucide="users" class="w-8 h-8 text-slate-300 mx-auto mb-3"></i>
                <p class="font-semibold">Aucune référence publiée pour le moment.</p>
                <p class="text-[13.5px] text-slate-500 mt-1.5 max-w-md mx-auto leading-relaxed">
                    Nous préférons une page vide à une page de noms que personne ne nous a autorisés
                    à citer. Écrivez-nous : nous vous mettrons en relation avec une communauté
                    utilisatrice qui acceptera d'en parler avec vous.
                </p>
                <a href="{{ route('vitrine.contact') }}"
                   class="inline-flex items-center gap-2 mt-6 rounded-xl bg-emerald-600 px-5 py-2.5 text-[13.5px] font-bold text-white hover:bg-emerald-700 transition">
                    <i data-lucide="mail" class="w-4 h-4"></i> Nous écrire
                </a>
            </div>
        </section>
    @else
        <section class="max-w-6xl mx-auto px-5 sm:px-8 pb-8">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($clients as $client)
                    <div class="rounded-2xl border border-slate-200 p-6 flex flex-col">
                        <span class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                            <i data-lucide="church" class="w-5 h-5"></i>
                        </span>

                        <h2 class="font-bold mt-4">{{ $client->nom }}</h2>

                        @php $lieu = trim(($client->ville ?? '').', '.($client->pays ?? ''), ', '); @endphp
                        @if ($lieu !== '')
                            <p class="text-[13px] text-slate-500 flex items-center gap-1.5 mt-1">
                                <i data-lucide="map-pin" class="w-3.5 h-3.5"></i> {{ $lieu }}
                            </p>
                        @endif

                        @if ($client->temoignage)
                            <blockquote class="mt-4 border-l-2 border-emerald-300 pl-3 text-[13.5px] text-slate-700 italic leading-relaxed">
                                {{ $client->temoignage }}
                            </blockquote>
                            @if ($client->temoignage_auteur)
                                <p class="text-[12.5px] text-slate-500 mt-2">— {{ $client->temoignage_auteur }}</p>
                            @endif
                        @endif

                        @if ($client->site_url)
                            <a href="{{ $client->site_url }}" target="_blank" rel="noopener nofollow"
                               class="mt-auto pt-4 inline-flex items-center gap-1.5 text-[13px] font-semibold text-emerald-700 hover:underline">
                                Leur site <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="max-w-6xl mx-auto px-5 sm:px-8 py-12 text-center">
            <p class="text-[14px] text-slate-600">Vous souhaitez échanger avec l'une d'elles avant de décider ?</p>
            <a href="{{ route('vitrine.contact') }}"
               class="inline-flex items-center gap-2 mt-4 rounded-xl bg-emerald-600 px-5 py-2.5 text-[13.5px] font-bold text-white hover:bg-emerald-700 transition">
                <i data-lucide="mail" class="w-4 h-4"></i> Nous écrire
            </a>
        </section>
    @endif

@endsection
