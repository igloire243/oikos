@extends('layout')
@section('titre', $plan->exists ? 'Modifier une offre' : 'Nouvelle offre')

@php
    use App\Models\Plan;

    $carte = 'bg-white border border-slate-200 rounded-2xl overflow-hidden mb-5';
    $enteteCarte = 'px-5 py-3.5 border-b border-slate-100 flex items-center gap-2 text-[13px] font-bold';
    $champ = 'w-full rounded-xl border border-slate-300 px-3 py-2 text-[13.5px] shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none';
    $etiquette = 'block text-[12.5px] font-semibold text-slate-700 mb-1';
    $aide = 'text-[12px] text-slate-500 mt-1 leading-relaxed';

    // Les valeurs affichées viennent d'abord de la saisie refusée (old), sinon de l'offre.
    // Sans ce repli, une erreur de validation ferait perdre tout ce qui avait été coché.
    $tousModules = old('tous_modules', $plan->exists && $plan->fonctionnalites === null ? '1' : null) == '1';
    $modulesCoches = old('fonctionnalites', $plan->fonctionnalites ?? []);

    $tousModes = old('tous_modes', $plan->exists && $plan->modes_paiement === null ? '1' : null) == '1';
    $modesCoches = old('modes_paiement', $plan->modes_paiement ?? []);

    $quotas = $plan->quotas ?? [];

    $echelons = $plan->paliers_taille ?? [];
@endphp

@section('contenu')
    <a href="{{ route('plans.index') }}" class="inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-slate-500 hover:text-slate-800 mb-3 transition">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Offres
    </a>

    <h1 class="text-xl font-bold">{{ $plan->exists ? $plan->nom : 'Nouvelle offre' }}</h1>
    <p class="text-[13px] text-slate-500 mb-6">
        Ce que vous saisissez ici part directement sur la page publique des tarifs.
    </p>

    <form method="POST" action="{{ $plan->exists ? route('plans.modifier', $plan) : route('plans.enregistrer') }}"
          data-taux="{{ $taux }}">
        @csrf
        @if ($plan->exists) @method('PUT') @endif

        {{-- ── IDENTITÉ ───────────────────────────────────────────────────────────────── --}}
        <div class="{{ $carte }}">
            <div class="{{ $enteteCarte }}">
                <i data-lucide="tag" class="w-4 h-4 text-slate-400"></i> Identité de l'offre
            </div>
            <div class="px-5 py-5 space-y-4">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="nom" class="{{ $etiquette }}">Nom affiché</label>
                        <input id="nom" type="text" name="nom" value="{{ old('nom', $plan->nom) }}"
                               required maxlength="120" placeholder="Accès Standard" class="{{ $champ }}">
                    </div>
                    <div>
                        <label for="code" class="{{ $etiquette }}">Code</label>
                        <input id="code" type="text" name="code" value="{{ old('code', $plan->code) }}"
                               required maxlength="40" placeholder="ACCES_STANDARD"
                               class="{{ $champ }} font-mono uppercase">
                        <p class="{{ $aide }}">
                            L'identifiant stable de l'offre. Majuscules, chiffres et tirets bas.
                            {{-- Le code sert de clé dans le seeder et dans les abonnements : le
                                 changer sur une offre déjà vendue casserait le lien mental entre
                                 ce qui est en base et ce que vous lisez dans le code. --}}
                            <strong class="font-semibold">Évitez de le modifier une fois l'offre vendue.</strong>
                        </p>
                    </div>
                </div>

                <div>
                    <label for="argumentaire" class="{{ $etiquette }}">Argumentaire</label>
                    <textarea id="argumentaire" name="argumentaire" rows="3" maxlength="1000"
                              placeholder="Ce que cette offre apporte, en une ou deux phrases."
                              class="{{ $champ }}">{{ old('argumentaire', $plan->argumentaire) }}</textarea>
                    <p class="{{ $aide }}">Affiché sous le nom, sur la carte de la page Tarifs.</p>
                </div>

                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <label for="nature" class="{{ $etiquette }}">Nature</label>
                        <select id="nature" name="nature" class="{{ $champ }}">
                            @foreach ($natures as $code => $libelle)
                                <option value="{{ $code }}" @selected(old('nature', $plan->nature) === $code)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                        <p class="{{ $aide }}">Les échelons de taille et le plafond d'accès ne s'appliquent qu'à une licence.</p>
                    </div>
                    <div>
                        <label for="palier" class="{{ $etiquette }}">Palier</label>
                        <select id="palier" name="palier" class="{{ $champ }}">
                            @foreach ($paliers as $code => $libelle)
                                <option value="{{ $code }}" @selected(old('palier', $plan->palier) === $code)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="niveau" class="{{ $etiquette }}">S'applique à</label>
                        <select id="niveau" name="niveau" class="{{ $champ }}">
                            @foreach ($niveaux as $code => $libelle)
                                <option value="{{ $code }}" @selected(old('niveau', $plan->niveau) === $code)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── PRIX ───────────────────────────────────────────────────────────────────── --}}
        <div class="{{ $carte }}">
            <div class="{{ $enteteCarte }}">
                <i data-lucide="banknote" class="w-4 h-4 text-slate-400"></i> Prix
            </div>
            <div class="px-5 py-5 space-y-4">
                <div class="grid sm:grid-cols-3 gap-4 items-end">
                    <div>
                        <label for="prix_usd" class="{{ $etiquette }}">Prix en dollars</label>
                        <input id="prix_usd" type="number" step="0.01" min="0" name="prix_usd"
                               value="{{ old('prix_usd', $plan->exists ? number_format($plan->prix_usd_cents / 100, 2, '.', '') : '') }}"
                               required data-prix-usd class="{{ $champ }} tabular-nums">
                    </div>
                    <div>
                        <label for="prix_cdf" class="{{ $etiquette }}">Prix en francs</label>
                        <input id="prix_cdf" type="number" step="1" min="0" name="prix_cdf"
                               value="{{ old('prix_cdf', $plan->prix_cdf) }}"
                               required data-prix-cdf class="{{ $champ }} tabular-nums">
                    </div>
                    <div>
                        {{-- CONVERSION À LA DEMANDE, PAS AUTOMATIQUE. Un recalcul à chaque frappe
                             écraserait un prix en francs volontairement arrondi — 70 000 FC se
                             retient et s'annonce, 69 972 FC non. --}}
                        <button type="button" data-convertir
                                class="inline-flex items-center gap-2 rounded-xl bg-white border border-slate-300 px-3 py-2 text-[12.5px] font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                            Convertir au taux ({{ number_format($taux, 0, ',', ' ') }})
                        </button>
                    </div>
                </div>

                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <label for="periode_mois" class="{{ $etiquette }}">Période (mois)</label>
                        <input id="periode_mois" type="number" min="1" max="36" name="periode_mois"
                               value="{{ old('periode_mois', $plan->periode_mois ?: 1) }}" required
                               class="{{ $champ }} tabular-nums">
                    </div>
                    <div>
                        <label for="plafond_acces" class="{{ $etiquette }}">Plafond d'accès (licence)</label>
                        <select id="plafond_acces" name="plafond_acces" class="{{ $champ }}">
                            <option value="">Aucun plafond</option>
                            @foreach ($paliers as $code => $libelle)
                                <option value="{{ $code }}" @selected(old('plafond_acces', $plan->plafond_acces) === $code)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                        <p class="{{ $aide }}">Palier maximum que les entités du réseau pourront souscrire.</p>
                    </div>
                    <div>
                        <label for="ordre" class="{{ $etiquette }}">Ordre d'affichage</label>
                        <input id="ordre" type="number" min="0" max="999" name="ordre"
                               value="{{ old('ordre', $plan->ordre ?? 50) }}" required class="{{ $champ }} tabular-nums">
                        <p class="{{ $aide }}">Le plus petit passe en premier.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── ÉCHELONS DE TAILLE ─────────────────────────────────────────────────────── --}}
        <div class="{{ $carte }}">
            <div class="{{ $enteteCarte }}">
                <i data-lucide="layers" class="w-4 h-4 text-slate-400"></i> Prix selon la taille du réseau
                <span class="ml-auto text-[11px] font-semibold text-slate-400">licences seulement</span>
            </div>
            <div class="px-5 py-5">
                {{-- LE MODE RETENU : une formule, socle + montant par entité. Il vient en premier
                     et occupe la place, parce que c'est celui qu'on remplit. --}}
                <p class="text-[12.5px] text-slate-500 mb-4 leading-relaxed max-w-2xl">
                    Le prix de la licence, c'est le <strong class="font-semibold">prix ci-dessus</strong>
                    (le socle) <strong class="font-semibold">plus un montant par entité</strong>. Une formule
                    s'explique en une phrase et ne fait pas de marche : avec des tranches, franchir une borne
                    coûtait plus cher que la tranche elle-même.
                </p>

                <div class="grid sm:grid-cols-2 gap-3 max-w-xl">
                    <div>
                        <label for="par_entite_usd" class="{{ $etiquette }}">Par entité, en dollars</label>
                        <input id="par_entite_usd" type="number" step="0.01" min="0" name="par_entite_usd"
                               value="{{ old('par_entite_usd', $plan->prix_par_entite_usd_cents !== null ? number_format($plan->prix_par_entite_usd_cents / 100, 2, '.', '') : '') }}"
                               placeholder="vide = prix fixe"
                               class="{{ $champ }} tabular-nums">
                    </div>
                    <div>
                        <label for="par_entite_cdf" class="{{ $etiquette }}">Par entité, en francs</label>
                        <input id="par_entite_cdf" type="number" step="1" min="0" name="par_entite_cdf"
                               value="{{ old('par_entite_cdf', $plan->prix_par_entite_cdf) }}"
                               class="{{ $champ }} tabular-nums">
                    </div>
                </div>

                {{-- LES ANCIENNES TRANCHES, REPLIÉES. On ne les propose plus, mais une offre déjà
                     vendue sous ce mode doit rester modifiable : effacer ses échelons en ouvrant
                     l'écran changerait le prix d'un contrat en cours. --}}
                <details class="mt-6" @if (! empty($echelons)) open @endif>
                    <summary class="text-[12.5px] font-semibold text-slate-500 cursor-pointer">
                        Ancien mode : prix par tranche de taille
                    </summary>
                    <p class="text-[12.5px] text-slate-500 mt-3 mb-4 leading-relaxed max-w-2xl">
                        Conservé pour les offres déjà vendues ainsi. Ignoré dès qu'un montant par entité est
                        renseigné ci-dessus. Trois échelons au plus, plafond du dernier laissé
                        <strong class="font-semibold">vide</strong> pour attraper ce qui dépasse.
                    </p>

                <div class="space-y-3">
                    @for ($i = 0; $i < 3; $i++)
                        @php $e = $echelons[$i] ?? null; @endphp
                        <div class="grid sm:grid-cols-3 gap-3">
                            <div>
                                <label for="echelon_max_{{ $i }}" class="{{ $etiquette }}">Jusqu'à (entités)</label>
                                <input id="echelon_max_{{ $i }}" type="number" min="1" name="echelon_max[{{ $i }}]"
                                       value="{{ old('echelon_max.'.$i, $e['max'] ?? '') }}"
                                       placeholder="{{ $i === 2 ? 'vide = au-delà' : '' }}"
                                       class="{{ $champ }} tabular-nums">
                            </div>
                            <div>
                                <label for="echelon_usd_{{ $i }}" class="{{ $etiquette }}">Prix en dollars</label>
                                <input id="echelon_usd_{{ $i }}" type="number" step="0.01" min="0" name="echelon_usd[{{ $i }}]"
                                       value="{{ old('echelon_usd.'.$i, isset($e['prix_usd_cents']) ? number_format($e['prix_usd_cents'] / 100, 2, '.', '') : '') }}"
                                       class="{{ $champ }} tabular-nums">
                            </div>
                            <div>
                                <label for="echelon_cdf_{{ $i }}" class="{{ $etiquette }}">Prix en francs</label>
                                <input id="echelon_cdf_{{ $i }}" type="number" step="1" min="0" name="echelon_cdf[{{ $i }}]"
                                       value="{{ old('echelon_cdf.'.$i, $e['prix_cdf'] ?? '') }}"
                                       class="{{ $champ }} tabular-nums">
                            </div>
                        </div>
                    @endfor
                    </div>
                </details>
            </div>
        </div>

        {{-- ── QUOTAS ─────────────────────────────────────────────────────────────────── --}}
        <div class="{{ $carte }}">
            <div class="{{ $enteteCarte }}">
                <i data-lucide="gauge" class="w-4 h-4 text-slate-400"></i> Quotas
            </div>
            <div class="px-5 py-5">
                <p class="text-[12.5px] text-slate-500 mb-4 leading-relaxed max-w-2xl">
                    Cochez ce que l'offre annonce. Une case cochée <strong class="font-semibold">sans
                    nombre</strong> signifie « sans limite » — et non zéro, qui interdirait tout.
                </p>

                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach ($quotasPossibles as $cle => $libelle)
                        <div class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3">
                            <input type="checkbox" id="quota_actif_{{ $cle }}" name="quota_actif[{{ $cle }}]" value="1"
                                   @checked(old('quota_actif.'.$cle, array_key_exists($cle, $quotas) ? '1' : null) == '1')
                                   class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-600/30">
                            <label for="quota_actif_{{ $cle }}" class="text-[13.5px] font-semibold flex-1">{{ $libelle }}</label>
                            <input type="number" min="0" name="quota_valeur[{{ $cle }}]"
                                   value="{{ old('quota_valeur.'.$cle, $quotas[$cle] ?? '') }}"
                                   placeholder="sans limite"
                                   aria-label="Nombre pour {{ $libelle }}"
                                   class="{{ $champ }} w-32 tabular-nums">
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── MODULES ────────────────────────────────────────────────────────────────── --}}
        <div class="{{ $carte }}">
            <div class="{{ $enteteCarte }}">
                <i data-lucide="blocks" class="w-4 h-4 text-slate-400"></i> Modules ouverts
            </div>
            <div class="px-5 py-5">
                <label class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 cursor-pointer">
                    <input type="checkbox" name="tous_modules" value="1" @checked($tousModules)
                           class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600/30">
                    <span>
                        <span class="block text-[13.5px] font-semibold text-emerald-900">Tous les modules</span>
                        <span class="block text-[12px] text-emerald-900/80 mt-0.5 leading-relaxed">
                            {{-- « Tous » n'est pas la liste complète cochée : c'est l'absence de
                                 liste. La nuance compte le jour où un module s'ajoute au produit —
                                 une offre « tous » le recevra, une offre qui énumère restera figée. --}}
                            Y compris ceux qui seront ajoutés plus tard. Coché, les cases ci-dessous sont ignorées.
                        </span>
                    </span>
                </label>

                {{-- LES DEUX FAMILLES SONT AFFICHÉES, PAS SEULEMENT CELLE DE LA NATURE CHOISIE.
                     La nature se change dans ce même formulaire, sans rechargement : masquer une
                     famille demanderait du JavaScript, et l'oublier laisserait des cases invisibles
                     mais cochées. On montre tout, on dit clairement ce qui s'applique, et le
                     serveur ne retient que les clés de la bonne famille. --}}
                <div class="mt-4 flex gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <i data-lucide="info" class="w-4 h-4 mt-0.5 shrink-0 text-slate-400"></i>
                    <div class="text-[12.5px] text-slate-600 leading-relaxed">
                        <p>Chaque nature d'offre n'ouvre que sa famille de modules. Les cases de
                        l'autre famille sont ignorées à l'enregistrement.</p>
                        <ul class="mt-1.5 space-y-0.5">
                            @foreach ($espacesParNature as $codeNature => $espacesNature)
                                <li>
                                    <strong class="font-semibold">{{ $natures[$codeNature] ?? $codeNature }}</strong> :
                                    {{ implode(', ', array_map(fn ($e) => \App\Support\Modules::libelleEspace($e), $espacesNature)) }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                @foreach ($espaces as $espace => $definitionEspace)
                    <div class="mt-5">
                        <p class="text-[10.5px] font-bold uppercase tracking-wider text-slate-400 mb-2">
                            {{ $definitionEspace['libelle'] }}
                            <span class="normal-case tracking-normal font-semibold text-slate-400">· {{ count($definitionEspace['modules']) }} modules</span>
                        </p>
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            @foreach ($definitionEspace['modules'] as $cle => $module)
                                <label class="flex items-center gap-2.5 rounded-lg border border-slate-200 px-3 py-2 cursor-pointer hover:bg-slate-50 transition">
                                    <input type="checkbox" name="fonctionnalites[]" value="{{ $cle }}"
                                           @checked(in_array($cle, (array) $modulesCoches, true))
                                           class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-600/30">
                                    <i data-lucide="{{ $module['icone'] }}" class="w-3.5 h-3.5 text-slate-400"></i>
                                    <span class="text-[13px] font-semibold">{{ $module['nom'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ── MODES DE PAIEMENT ──────────────────────────────────────────────────────── --}}
        <div class="{{ $carte }}">
            <div class="{{ $enteteCarte }}">
                <i data-lucide="wallet" class="w-4 h-4 text-slate-400"></i> Modes de paiement acceptés
            </div>
            <div class="px-5 py-5">
                <label class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 cursor-pointer">
                    <input type="checkbox" name="tous_modes" value="1" @checked($tousModes)
                           class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600/30">
                    <span>
                        <span class="block text-[13.5px] font-semibold text-emerald-900">Tous les modes actifs</span>
                        <span class="block text-[12px] text-emerald-900/80 mt-0.5 leading-relaxed">
                            Ceux que vous avez activés dans les Réglages. Ne restreignez que pour une offre négociée.
                        </span>
                    </span>
                </label>

                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2 mt-4">
                    @foreach ($fournisseurs as $code => $libelle)
                        <label class="flex items-center gap-2.5 rounded-lg border border-slate-200 px-3 py-2 cursor-pointer hover:bg-slate-50 transition">
                            <input type="checkbox" name="modes_paiement[]" value="{{ $code }}"
                                   @checked(in_array($code, (array) $modesCoches, true))
                                   class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-600/30">
                            <span class="text-[13px] font-semibold">{{ $libelle }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── VISIBILITÉ + ENREGISTRER ───────────────────────────────────────────────── --}}
        <div class="{{ $carte }}">
            <div class="px-5 py-5">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="is_public" value="1"
                           @checked(old('is_public', $plan->exists ? $plan->is_public : true))
                           class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600/30">
                    <span>
                        <span class="block text-[13.5px] font-semibold">Proposer cette offre sur le site public</span>
                        <span class="block text-[12px] text-slate-500 mt-0.5 leading-relaxed">
                            Décochée, l'offre reste utilisable ici — pour un tarif négocié que vous
                            ne voulez pas afficher.
                        </span>
                    </span>
                </label>
            </div>
        </div>

        <div class="sticky bottom-0 -mx-5 sm:-mx-8 px-5 sm:px-8 py-4 bg-slate-50/95 backdrop-blur border-t border-slate-200 flex items-center gap-2">
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-[13px] font-bold text-white hover:bg-emerald-700 transition cursor-pointer shadow-sm">
                <i data-lucide="save" class="w-4 h-4"></i>
                {{ $plan->exists ? 'Enregistrer' : 'Créer l\'offre' }}
            </button>
            <a href="{{ route('plans.index') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-white border border-slate-300 px-4 py-2.5 text-[13px] font-semibold text-slate-700 hover:bg-slate-50 transition">
                Annuler
            </a>
        </div>
    </form>
@endsection
