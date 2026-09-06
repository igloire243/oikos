@extends('layout')
@section('titre', $client->nom)

@php
    $carte = 'bg-white border border-slate-200 rounded-2xl overflow-hidden mb-5';
    $enteteCarte = 'px-5 py-3.5 border-b border-slate-100 flex items-center gap-2 text-[13px] font-bold';
    $th = 'text-left text-[10.5px] font-bold uppercase tracking-wider text-slate-400 px-5 py-2.5 bg-slate-50/70 border-b border-slate-200';
    $td = 'px-5 py-3 border-b border-slate-100 text-[13.5px] align-top';
    $champ = 'w-full rounded-xl border border-slate-300 px-3 py-2 text-[13.5px] shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none';
    $etiquette = 'block text-[12px] font-semibold text-slate-600 mb-1';
    $btn = 'inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-[13px] font-semibold text-white shadow-sm hover:bg-emerald-700 transition cursor-pointer';
    $btnPetit = 'inline-flex items-center gap-1.5 rounded-lg bg-white border border-slate-300 px-2.5 py-1.5 text-[12px] font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer';
    $btnPetitDanger = 'inline-flex items-center gap-1.5 rounded-lg bg-white border border-red-300 px-2.5 py-1.5 text-[12px] font-semibold text-red-700 hover:bg-red-50 transition cursor-pointer';

    // Fond sombre et `select-all` : un secret se copie d'un clic, et se distingue du texte courant.
    $secret = 'block w-full overflow-x-auto rounded-lg bg-emerald-950 text-emerald-100 px-3 py-2 font-mono text-[12px] whitespace-pre select-all my-1.5';
@endphp

@section('contenu')
    <a href="{{ route('clients.index') }}" class="inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-slate-500 hover:text-slate-800 mb-3 transition">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Clients
    </a>

    <h1 class="text-xl font-bold">{{ $client->nom }}</h1>
    <p class="text-[13px] text-slate-500 mb-6">
        {{ trim($client->ville.', '.$client->pays, ', ') ?: 'Lieu non renseigné' }}
        @if ($client->contact_nom) · {{ $client->contact_nom }} @endif
        @if ($client->contact_email) · {{ $client->contact_email }} @endif
    </p>

    {{-- LA SEULE LIGNE À METTRE DANS LE .env D'UNE INSTALLATION.

         Ce bandeau a remplacé un bloc qui affichait la clé de SYNCHRONISATION et demandait de la
         recopier dans le .env sous le nom CONSOLE_CLE. C'était faux, et durablement trompeur : le
         produit ne lit aucune clé dans son .env. Il en reçoit une, tout seul, au moment de
         l'activation — c'est même la raison d'être de la clé d'activation.

         Il ne reste donc qu'une chose à reporter à la main sur le serveur du client : l'adresse de
         cette console. Elle est publique, elle ne se perd pas, et elle est la même pour tous. --}}
    @if (session('installation_neuve'))
        <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4">
            <p class="flex items-center gap-2 text-[13.5px] font-bold text-emerald-900">
                <i data-lucide="plug-zap" class="w-4 h-4"></i>
                Installation ajoutée. Il reste deux gestes.
            </p>
            <ol class="text-[12.5px] text-emerald-900/80 mt-1.5 mb-2 space-y-1 list-decimal list-inside">
                <li>Dans le <code class="font-mono">.env</code> du serveur client, une seule ligne :</li>
            </ol>

            <div class="relative">
                <code id="env-installation" class="{{ $secret }} pr-24">CONSOLE_URL={{ rtrim(config('app.url'), '/') }}</code>
                <button type="button" data-copier="#env-installation"
                        class="absolute top-2 right-2 inline-flex items-center gap-1.5 rounded-lg bg-white/10 hover:bg-white/20 border border-white/20 px-2.5 py-1 text-[12px] font-semibold text-white transition cursor-pointer">
                    <i data-lucide="copy" class="w-3.5 h-3.5"></i> Copier
                </button>
            </div>

            <p class="text-[12.5px] text-emerald-900/80 mt-2">
                2. Émettez une <strong class="font-semibold">clé d'activation</strong> ci-dessous et
                dictez-la au client. C'est elle — et elle seule — qui branche l'installation. Le nom
                et les coordonnées du client se rempliront tout seuls à ce moment-là.
            </p>
        </div>
    @endif

    <div class="{{ $carte }}">
        <div class="{{ $enteteCarte }}">
            <i data-lucide="server" class="w-4 h-4 text-slate-400"></i>
            Installations
            <span class="ml-auto text-[11px] font-semibold text-slate-400 tabular-nums">{{ $client->installations->count() }}</span>
        </div>

        @if ($client->installations->isEmpty())
            <div class="px-5 py-10 text-center">
                <i data-lucide="server-off" class="w-7 h-7 text-slate-300 mx-auto mb-2"></i>
                <p class="text-[13.5px] font-semibold">Aucune installation.</p>
                <p class="text-[13px] text-slate-500">Ajoutez-en une, puis émettez-lui une clé d'activation.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="{{ $th }}">Nom</th>
                            <th class="{{ $th }}">Adresse</th>
                            <th class="{{ $th }}">Version</th>
                            <th class="{{ $th }}">Dernier contact</th>
                            <th class="{{ $th }}">Entités</th>
                            <th class="{{ $th }}"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($client->installations as $i)
                        <tr>
                            <td class="{{ $td }}">
                                <span class="font-semibold">{{ $i->nom ?: 'Sans nom — en attente d’activation' }}</span><br>
                                <code class="font-mono text-[11px] text-slate-400">{{ $i->cle_apercu }}…</code>
                            </td>
                            <td class="{{ $td }}">
                                @if ($i->url)
                                    <a href="{{ $i->url }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1 text-emerald-700 hover:underline break-all">
                                        {{ $i->url }}<i data-lucide="external-link" class="w-3 h-3 shrink-0"></i>
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="{{ $td }} text-slate-500">{{ $i->version ?: '—' }}</td>
                            <td class="{{ $td }}">
                                @if (! $i->active)
                                    <x-etiq ton="gris" icone="power-off">désactivée</x-etiq>
                                @elseif ($i->estMuette())
                                    <x-etiq ton="ambre" icone="wifi-off">{{ $i->vue_le?->diffForHumans() ?? 'jamais' }}</x-etiq>
                                @else
                                    <x-etiq ton="vert" icone="wifi">{{ $i->vue_le->diffForHumans() }}</x-etiq>
                                @endif
                            </td>
                            <td class="{{ $td }} tabular-nums">{{ $i->entites->count() }}</td>
                            <td class="{{ $td }} text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('installations.cle', $i) }}" class="inline"
                                      onsubmit="return confirm('Couper cette installation ? Sa clé de synchronisation est révoquée immédiatement : elle ne parlera plus à la console tant qu\'elle n\'aura pas été RÉACTIVÉE avec une clé d\'activation neuve.')">
                                    @csrf
                                    <button type="submit" class="{{ $btnPetit }}">
                                        <i data-lucide="unplug" class="w-3.5 h-3.5"></i> Couper
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('installations.bascule', $i) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="{{ $i->active ? $btnPetitDanger : $btnPetit }}">
                                        <i data-lucide="{{ $i->active ? 'power-off' : 'power' }}" class="w-3.5 h-3.5"></i>
                                        {{ $i->active ? 'Désactiver' : 'Réactiver' }}
                                    </button>
                                </form>
                            </td>
                        </tr>

                        @if ($i->entites->isNotEmpty())
                            <tr>
                                {{-- L'ARBRE EST DÉCLARÉ PAR L'INSTALLATION, pas saisi ici : c'est
                                     elle qui connaît ses antennes et ses églises, et qui les pousse
                                     à chaque synchronisation.

                                     Il était affiché à plat, décalé d'un cran par TYPE : toutes les
                                     cellules au même retrait, quel que soit le secteur dont elles
                                     dépendent. Lisible sur trois entités, muet sur un réseau de
                                     cent — et c'est précisément la structure qu'on regarde avant de
                                     vendre. Entite::arbre() reconstitue la vraie parenté, et le
                                     pliage repose sur <details>, sans une ligne de JavaScript. --}}
                                <td colspan="6" class="bg-slate-50 px-5 pt-2 pb-3 border-b border-slate-100">
                                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                        <p class="text-[10.5px] font-bold uppercase tracking-wider text-slate-400">
                                            Arbre déclaré par l'installation
                                        </p>

                                        <span class="text-[11px] text-slate-400">{{ $i->entites->count() }} entités</span>

                                        {{-- Les deux boutons d'ensemble : ouvrir quarante branches
                                             une par une est exactement ce qu'on cherche à éviter. --}}
                                        <span class="ml-auto flex items-center gap-1">
                                            <button type="button"
                                                    data-arbre-action="ouvrir" data-arbre-cible="#arbre-{{ $i->installation_id }}"
                                                    class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-500 hover:bg-slate-50 hover:text-slate-700 transition cursor-pointer">
                                                <i data-lucide="chevrons-down" class="w-3 h-3"></i> Tout déplier
                                            </button>
                                            <button type="button"
                                                    data-arbre-action="fermer" data-arbre-cible="#arbre-{{ $i->installation_id }}"
                                                    class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-500 hover:bg-slate-50 hover:text-slate-700 transition cursor-pointer">
                                                <i data-lucide="chevrons-up" class="w-3 h-3"></i> Tout replier
                                            </button>
                                        </span>
                                    </div>

                                    <div id="arbre-{{ $i->installation_id }}">
                                        @foreach (\App\Models\Entite::arbre($i->entites) as $noeud)
                                            <x-noeud-entite :noeud="$noeud" :installation="$i" />
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="px-5 py-4 bg-slate-50/70 border-t border-slate-100">
            <form method="POST" action="{{ route('installations.enregistrer', $client) }}" class="space-y-3">
                @csrf
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="inst_nom" class="{{ $etiquette }}">Nom de l'installation <span class="font-normal text-slate-400">— facultatif</span></label>
                        <input id="inst_nom" type="text" name="nom" value="{{ old('nom') }}" maxlength="190"
                               placeholder="se remplira à l'activation" class="{{ $champ }}">
                        <p class="text-[11.5px] text-slate-500 mt-1">Laissé vide, il prendra le nom exact de la communauté.</p>
                    </div>
                    <div>
                        <label for="inst_url" class="{{ $etiquette }}">Adresse <span class="font-normal text-slate-400">— recommandé</span></label>
                        <input id="inst_url" type="url" name="url" value="{{ old('url') }}" placeholder="https://eglise-bethel.cd" maxlength="255" class="{{ $champ }}">
                        <p class="text-[11.5px] text-slate-500 mt-1">Sans elle, la réouverture après paiement attend la nuit.</p>
                    </div>
                </div>
                <button type="submit" class="{{ $btn }}">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Ajouter l'installation
                </button>
            </form>
        </div>
    </div>

    {{-- LES CLÉS D'ACTIVATION.

         Courtes et dictables : « OIKOS-7K4P-2M9X-B3TQ ». Le client les tape sur l'écran
         d'activation de son installation, et elles ne servent qu'une fois — ce qu'elles ouvrent
         (la clé de synchronisation) est renouvelé dans la foulée.

         Comme la clé d'installation, une clé d'activation N'EST LISIBLE QU'UNE FOIS : la base n'en
         garde que l'empreinte. Perdue avant d'être envoyée, elle se réémet. --}}
    <div class="{{ $carte }}">
        <div class="{{ $enteteCarte }}">
            <i data-lucide="key-square" class="w-4 h-4 text-slate-400"></i>
            Clés d'activation
        </div>

        @if ($nouvelleCle = session('cle_activation_neuve'))
            <div class="px-5 py-4 border-b border-slate-100 bg-emerald-50">
                <p class="flex items-center gap-2 text-[13.5px] font-bold text-emerald-900">
                    <i data-lucide="key-round" class="w-4 h-4"></i>
                    Clé pour « {{ $nouvelleCle['installation'] }} » — envoyez-la maintenant.
                </p>
                {{-- Le bouton est SOUS le code et non par-dessus : la clé est écrite en grand et
                     centrée pour être dictée au téléphone, et un bouton flottant en masquerait un
                     morceau sur un écran étroit. --}}
                <code id="cle-activation" class="block w-full text-center rounded-xl bg-emerald-950 text-emerald-100 px-4 py-4 font-mono text-lg sm:text-2xl font-bold tracking-widest select-all mt-3">{{ $nouvelleCle['code'] }}</code>

                <div class="flex justify-center my-2">
                    <button type="button" data-copier="#cle-activation"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-3 py-1.5 text-[12.5px] font-semibold text-white transition cursor-pointer">
                        <i data-lucide="copy" class="w-3.5 h-3.5"></i> Copier la clé
                    </button>
                </div>
                <p class="text-[12.5px] text-emerald-900/80">
                    Elle ne sera plus jamais affichée.
                    @if ($nouvelleCle['expire_le'])
                        Elle expire le {{ $nouvelleCle['expire_le'] }}.
                    @else
                        Elle n'expire pas — à éviter, une clé sans échéance traîne indéfiniment.
                    @endif
                    Les lettres O, I et L et les chiffres 0 et 1 n'y figurent jamais : elle se dicte
                    au téléphone sans risque de confusion.
                </p>
            </div>
        @endif

        @if ($client->installations->isEmpty())
            <div class="px-5 py-8 text-center">
                <p class="text-[13px] text-slate-500">Ajoutez d'abord une installation ci-dessus.</p>
            </div>
        @else
            @foreach ($client->installations as $i)
                <div class="px-5 py-4 border-b border-slate-100 last:border-0">
                    <div class="flex flex-wrap items-end gap-3">
                        <p class="text-[13.5px] font-semibold flex-1 min-w-[160px]">{{ $i->nom ?: 'Sans nom — en attente d’activation' }}</p>

                        <form method="POST" action="{{ route('cles.emettre', $i) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <div>
                                <label for="jours-{{ $i->installation_id }}" class="{{ $etiquette }}">Valide (jours)</label>
                                <input id="jours-{{ $i->installation_id }}" type="number" name="jours" value="30"
                                       min="0" max="365" class="{{ $champ }} w-28 tabular-nums">
                            </div>
                            <div>
                                <label for="note-{{ $i->installation_id }}" class="{{ $etiquette }}">Note</label>
                                <input id="note-{{ $i->installation_id }}" type="text" name="note" maxlength="190"
                                       placeholder="Réinstallation du 12/09" class="{{ $champ }} w-56">
                            </div>
                            <button type="submit" class="{{ $btn }}">
                                <i data-lucide="key-round" class="w-4 h-4"></i>
                                Émettre une clé
                            </button>
                        </form>
                    </div>

                    @if ($i->clesActivation->isNotEmpty())
                        <div class="mt-3 overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr>
                                        <th class="{{ $th }}">Clé</th>
                                        <th class="{{ $th }}">État</th>
                                        <th class="{{ $th }}">Échéance</th>
                                        <th class="{{ $th }}">Utilisée</th>
                                        <th class="{{ $th }}"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach ($i->clesActivation as $cle)
                                    <tr>
                                        <td class="{{ $td }}">
                                            <code class="font-mono text-[12px]">{{ $cle->code_apercu }}</code>
                                            @if ($cle->note)
                                                <p class="text-[12px] text-slate-500 mt-0.5">{{ $cle->note }}</p>
                                            @endif
                                        </td>
                                        <td class="{{ $td }}">
                                            @if ($cle->statut === \App\Models\CleActivation::UTILISEE)
                                                <x-etiq ton="vert" icone="circle-check">Utilisée</x-etiq>
                                            @elseif ($cle->statut === \App\Models\CleActivation::REVOQUEE)
                                                <x-etiq ton="gris" icone="ban">Révoquée</x-etiq>
                                            @elseif (! $cle->estUtilisable())
                                                <x-etiq ton="ambre" icone="clock">Expirée</x-etiq>
                                            @else
                                                <x-etiq ton="bleu" icone="key-round">En attente</x-etiq>
                                            @endif
                                        </td>
                                        <td class="{{ $td }} text-slate-500 whitespace-nowrap">
                                            {{ $cle->expire_le?->format('d/m/Y') ?? 'sans' }}
                                        </td>
                                        <td class="{{ $td }} text-slate-500">
                                            @if ($cle->utilisee_le)
                                                {{ $cle->utilisee_le->format('d/m/Y H:i') }}
                                                {{-- L'empreinte dit DEPUIS OÙ la clé a été consommée.
                                                     C'est ce qui permet de reconnaître une deuxième
                                                     installation qui essaierait la même clé. --}}
                                                <code class="block font-mono text-[11px] text-slate-400 truncate max-w-[160px]">{{ $cle->empreinte }}</code>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="{{ $td }} text-right">
                                            @if ($cle->statut === \App\Models\CleActivation::EMISE)
                                                <form method="POST" action="{{ route('cles.revoquer', $cle) }}"
                                                      onsubmit="return confirm('Révoquer cette clé ? Elle ne pourra plus activer aucune installation.')">
                                                    @csrf
                                                    <button type="submit" class="{{ $btnPetitDanger }}">
                                                        <i data-lucide="ban" class="w-3.5 h-3.5"></i> Révoquer
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    <div class="{{ $carte }}">
        <div class="{{ $enteteCarte }}">
            <i data-lucide="clipboard-list" class="w-4 h-4 text-slate-400"></i>
            Fiche du client
        </div>
        <div class="px-5 py-5">
            <form method="POST" action="{{ route('clients.modifier', $client) }}" class="space-y-4">
                @csrf @method('PUT')

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="c_nom" class="{{ $etiquette }}">Nom</label>
                        <input id="c_nom" type="text" name="nom" value="{{ old('nom', $client->nom) }}" required class="{{ $champ }}">
                    </div>
                    <div>
                        <label for="c_statut" class="{{ $etiquette }}">État</label>
                        <select id="c_statut" name="statut" class="{{ $champ }}">
                            @foreach (\App\Models\Client::STATUTS as $code => $libelle)
                                <option value="{{ $code }}" @selected(old('statut', $client->statut) === $code)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="c_pays" class="{{ $etiquette }}">Pays</label>
                        <input id="c_pays" type="text" name="pays" value="{{ old('pays', $client->pays) }}" class="{{ $champ }}">
                    </div>
                    <div>
                        <label for="c_ville" class="{{ $etiquette }}">Ville</label>
                        <input id="c_ville" type="text" name="ville" value="{{ old('ville', $client->ville) }}" class="{{ $champ }}">
                    </div>
                </div>

                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <label for="c_contact" class="{{ $etiquette }}">Contact</label>
                        <input id="c_contact" type="text" name="contact_nom" value="{{ old('contact_nom', $client->contact_nom) }}" class="{{ $champ }}">
                    </div>
                    <div>
                        <label for="c_email" class="{{ $etiquette }}">E-mail</label>
                        <input id="c_email" type="email" name="contact_email" value="{{ old('contact_email', $client->contact_email) }}" class="{{ $champ }}">
                    </div>
                    <div>
                        <label for="c_tel" class="{{ $etiquette }}">Téléphone</label>
                        <input id="c_tel" type="tel" name="contact_telephone" value="{{ old('contact_telephone', $client->contact_telephone) }}" class="{{ $champ }}">
                    </div>
                </div>

                <div>
                    <label for="c_notes" class="{{ $etiquette }}">Notes</label>
                    <textarea id="c_notes" name="notes" rows="4" class="{{ $champ }}">{{ old('notes', $client->notes) }}</textarea>
                </div>

                <button type="submit" class="{{ $btn }}">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Enregistrer
                </button>
            </form>
        </div>
    </div>

    {{-- CITATION PUBLIQUE — volontairement à part, et en dernier.

         Cocher cette case publie le nom de cette église sur Internet. Placée au milieu de la fiche,
         elle finirait par être cochée en corrigeant un numéro de téléphone. Isolée, avec son propre
         bouton, elle demande une intention — et la date d'accord répond à « qui vous a autorisé ? »
         le jour où la question se pose. --}}
    <div class="{{ $carte }}">
        <div class="{{ $enteteCarte }}">
            <i data-lucide="megaphone" class="w-4 h-4 text-slate-400"></i>
            Citation sur le site public
            @if ($client->vitrine)
                <span class="ml-auto"><x-etiq ton="vert" icone="globe">cité publiquement</x-etiq></span>
            @else
                <span class="ml-auto"><x-etiq ton="gris" icone="eye-off">non cité</x-etiq></span>
            @endif
        </div>
        <div class="px-5 py-5">
            <form method="POST" action="{{ route('clients.vitrine', $client) }}" class="space-y-4">
                @csrf @method('PUT')

                <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 cursor-pointer hover:bg-slate-50 transition">
                    <input type="checkbox" name="vitrine" value="1" @checked($client->vitrine)
                           class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600/30">
                    <span>
                        <span class="block text-[13.5px] font-semibold">Ce client accepte d'être cité sur le site public</span>
                        <span class="block text-[12.5px] text-slate-500 mt-0.5">
                            À ne cocher qu'après son accord explicite. Il n'apparaîtra que tant que
                            son état reste « Actif ».
                        </span>
                    </span>
                </label>

                @if ($client->vitrine_accord_le)
                    <p class="text-[12.5px] text-slate-500 flex items-center gap-1.5">
                        <i data-lucide="calendar-check" class="w-3.5 h-3.5"></i>
                        Accord enregistré le {{ $client->vitrine_accord_le->format('d/m/Y') }}
                    </p>
                @endif

                <div>
                    <label for="temoignage" class="{{ $etiquette }}">Témoignage (facultatif)</label>
                    <textarea id="temoignage" name="temoignage" rows="3" maxlength="1000"
                              placeholder="Une phrase du client, reprise telle quelle."
                              class="{{ $champ }}">{{ old('temoignage', $client->temoignage) }}</textarea>
                    <p class="text-[12px] text-slate-500 mt-1">
                        Ne rien écrire ici vaut mieux qu'écrire à sa place : une référence sans
                        témoignage reste vraie.
                    </p>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="temoignage_auteur" class="{{ $etiquette }}">Auteur du témoignage</label>
                        <input id="temoignage_auteur" type="text" name="temoignage_auteur" maxlength="190"
                               value="{{ old('temoignage_auteur', $client->temoignage_auteur) }}"
                               placeholder="Passeur Jean Mbuyi, responsable" class="{{ $champ }}">
                    </div>
                    <div>
                        <label for="site_url" class="{{ $etiquette }}">Site de la communauté</label>
                        <input id="site_url" type="url" name="site_url" maxlength="255"
                               value="{{ old('site_url', $client->site_url) }}"
                               placeholder="https://…" class="{{ $champ }}">
                    </div>
                </div>

                <button type="submit" class="{{ $btn }}">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Enregistrer
                </button>
            </form>
        </div>
    </div>
@endsection
