{{-- Étiquette d'état. Un seul endroit décide de la couleur d'un état, pour qu'« actif » ait
     partout la même apparence — et pour que les classes soient écrites EN TOUTES LETTRES : Tailwind
     lit les fichiers source, il ne devine pas les classes assemblées par concaténation.

     L'ICÔNE N'EST PAS DÉCORATIVE. La couleur seule ne dit rien à qui ne distingue pas le vert du
     rouge ; le mot et le pictogramme la doublent.

     <x-etiq ton="vert" icone="circle-check">Actif</x-etiq> --}}
@props(['ton' => 'gris', 'icone' => null])

@php
    $tons = [
        'vert' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'bleu' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'ambre' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        'rouge' => 'bg-red-50 text-red-700 ring-red-600/20',
        'gris' => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    ];
@endphp

<span {{ $attributes->class([
        'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset whitespace-nowrap',
        $tons[$ton] ?? $tons['gris'],
    ]) }}>
    @if ($icone)<i data-lucide="{{ $icone }}" class="w-3 h-3"></i>@endif{{ $slot }}
</span>
