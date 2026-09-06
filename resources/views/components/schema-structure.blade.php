{{-- LE SCHÉMA DE LA STRUCTURE — vision, antennes, églises.

     POURQUOI UN DESSIN PLUTÔT QU'UN PARAGRAPHE. La hiérarchie est ce qu'un visiteur doit saisir
     pour comprendre la grille tarifaire, et c'est précisément ce qu'un texte explique mal : trois
     niveaux emboîtés se voient en une seconde et se lisent en trois phrases.

     C'EST DU SVG ÉCRIT À LA MAIN, PAS UNE IMAGE. Il pèse deux kilo-octets, reste net sur tous les
     écrans, ne demande aucun aller-retour réseau — et se corrige le jour où le vocabulaire change,
     ce qu'un fichier PNG ne permettrait pas.

     `role="img"` et le <title> : sans eux, un lecteur d'écran énumère une trentaine de formes
     géométriques au lieu de dire de quoi il s'agit. --}}
@props(['legende' => true])

<figure {{ $attributes->merge(['class' => 'w-full']) }}>
    <svg viewBox="0 0 620 300" class="w-full h-auto" role="img" aria-labelledby="titre-schema-structure">
        <title id="titre-schema-structure">
            Une vision au sommet, deux antennes en dessous, et quatre églises rattachées aux antennes.
        </title>

        {{-- Les traits d'abord : dessinés après, ils passeraient par-dessus les cartes. --}}
        <g stroke="#cbd5e1" stroke-width="2" fill="none">
            <path d="M310 74 L310 100 M150 100 L470 100 M150 100 L150 126 M470 100 L470 126" />
            <path d="M150 194 L150 220 M70 220 L230 220 M70 220 L70 246 M230 220 L230 246" />
            <path d="M470 194 L470 220 M390 220 L550 220 M390 220 L390 246 M550 220 L550 246" />
        </g>

        {{-- LA VISION — seule teinte pleine du schéma. Le sommet doit se distinguer d'un coup
             d'œil : c'est lui qui paie la licence, et toute la grille tarifaire en découle. --}}
        <g>
            <rect x="230" y="30" width="160" height="44" rx="12" fill="#059669" />
            <text x="310" y="58" text-anchor="middle" fill="#ffffff" font-size="15" font-weight="700">Vision</text>
        </g>

        {{-- LES ANTENNES --}}
        @foreach ([150, 470] as $x)
            <g>
                <rect x="{{ $x - 80 }}" y="126" width="160" height="44" rx="12" fill="#ecfdf5" stroke="#a7f3d0" stroke-width="1.5" />
                <text x="{{ $x }}" y="154" text-anchor="middle" fill="#047857" font-size="14" font-weight="600">Antenne</text>
            </g>
        @endforeach

        {{-- LES ÉGLISES --}}
        @foreach ([70, 230, 390, 550] as $x)
            <g>
                <rect x="{{ $x - 62 }}" y="246" width="124" height="40" rx="10" fill="#ffffff" stroke="#e2e8f0" stroke-width="1.5" />
                <text x="{{ $x }}" y="271" text-anchor="middle" fill="#475569" font-size="13" font-weight="600">Église</text>
            </g>
        @endforeach
    </svg>

    @if ($legende)
        <figcaption class="text-[12.5px] text-slate-500 mt-3 text-center leading-relaxed">
            Les départements sont définis une seule fois, au niveau de la vision. Chaque église y
            rattache ses membres — et un rapport devient possible pour une église, pour une antenne
            ou pour l'ensemble.
        </figcaption>
    @endif
</figure>
