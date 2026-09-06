<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une Vision, une antenne ou une extension, telle que l'installation nous la déclare.
 *
 * COPIE, PAS SOURCE. La vérité vit chez le client. Cette table existe pour une seule raison :
 * pouvoir VENDRE un abonnement à « l'antenne de Lubumbashi ». Sans elle, vous ignorez qu'elle
 * existe et ne pouvez rien lui facturer.
 */
class Entite extends Model
{
    protected $table = 'entites';
    protected $primaryKey = 'entite_id';

    public const TYPE_VISION = 'VISION';
    public const TYPE_ANTENNE = 'ANTENNE';
    public const TYPE_EXTENSION = 'EXTENSION';

    public const TYPES = [
        self::TYPE_VISION => 'Vision',
        self::TYPE_ANTENNE => 'Antenne',
        self::TYPE_EXTENSION => 'Extension',
    ];

    /** Du plus général au plus local — l'ordre de la cascade d'abonnements. */
    public const ORDRE = [self::TYPE_VISION => 1, self::TYPE_ANTENNE => 2, self::TYPE_EXTENSION => 3];

    /**
     * `sous_type` EST VOLATIL. NE JAMAIS RIEN Y ACCROCHER.
     *
     * SECTEUR et CELLULE ne sont pas deux natures d'entité : c'est le même objet à deux tailles.
     * Une extension de moins de deux cents membres est une cellule ; au-delà du seuil, le produit
     * la requalifie en secteur — tout seul, sans que personne décide rien. Un dimanche de baptêmes
     * peut donc faire changer ce champ entre deux synchronisations.
     *
     * TOUT LE RESTE EST CONSTRUIT POUR QUE CE CHANGEMENT NE CASSE RIEN, et il faut que ça le reste :
     *
     *   · `updateOrCreate` s'appuie sur (installation_id, TYPE, ref) — et le type reste EXTENSION
     *     des deux côtés du seuil. La ligne est donc mise à jour, jamais dupliquée.
     *   · Un abonnement vise (beneficiaire_type, beneficiaire_ref), soit EXTENSION + ref. Il survit
     *     à la promotion : l'église franchit deux cents membres un dimanche et reste abonnée.
     *   · Les modules de l'espace berger portent le préfixe `secteur.` et couvrent les deux.
     *
     * Le jour où l'on serait tenté de vendre « une offre cellule » moins chère qu'une offre
     * secteur, ce champ ferait basculer un client d'un tarif à l'autre au milieu de sa période,
     * sans qu'aucun humain ait rien fait. Ce qui se facture, ce sont les entités et leur nombre —
     * pas leur taille du moment. `sous_type` ne sert qu'à l'affichage.
     */
    protected $fillable = [
        'installation_id', 'type', 'ref', 'nom', 'sous_type', 'parent_ref', 'vue_le',
    ];

    protected $casts = ['vue_le' => 'datetime'];

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class, 'installation_id', 'installation_id');
    }

    public function abonnement(): ?Abonnement
    {
        return Abonnement::where('installation_id', $this->installation_id)
            ->where('beneficiaire_type', $this->type)
            ->where('beneficiaire_ref', $this->ref)
            ->first();
    }

    /**
     * L'arbre réel de la communauté.
     *
     * LA HIÉRARCHIE, EXACTEMENT — trois niveaux, pas quatre :
     *
     *     Vision
     *       └── Antenne
     *             ├── Secteur    ┐  frères, au MÊME niveau
     *             └── Cellule    ┘
     *
     * Secteurs et cellules sont tous deux des « extensions » et sont FRÈRES : une cellule ne dépend
     * pas d'un secteur. L'antenne est leur seule parente directe, et sa propre parente est la
     * Vision. Une cellule ou un secteur ne peuvent jamais être référencés comme parent.
     *
     * C'est pourquoi seules les ANTENNE sont indexées comme parents possibles ci-dessous. La même
     * règle est écrite dans lignee() — et elle DOIT rester la même aux deux endroits : si l'arbre
     * affiché et la cascade de facturation ne s'accordaient pas sur ce qu'est un parent, on
     * vendrait selon une hiérarchie et on montrerait l'autre.
     *
     * POURQUOI ICI ET PAS DANS LA VUE. La fiche client se contentait d'une liste à plat, décalée
     * d'un cran par TYPE : toutes les extensions au même retrait, sans dire de quelle antenne
     * chacune relève. Sur trois entités c'est lisible ; sur un réseau de cent églises, cela ne dit
     * plus rien de la structure — et c'est justement la structure qu'on regarde avant de vendre.
     *
     * LES RÉFÉRENCES NE VIENNENT PAS TOUTES DE LA MÊME TABLE, et c'est le piège de cette méthode.
     * Côté produit, `ref` vaut `tenant_id` pour une VISION et `extension_id` pour les autres : rien
     * n'empêche une Vision de porter la même référence qu'une antenne. N'indexer que les ANTENNE
     * ferme aussi cette porte — sans quoi une cellule pourrait se retrouver rattachée à une Vision
     * par pure collision de numéros.
     *
     * UNE ENTITÉ DONT LE PARENT EST INTROUVABLE DEVIENT UNE RACINE plutôt que de disparaître. La
     * synchronisation peut être partielle, ou une antenne avoir été retirée du lot : mieux vaut
     * afficher une église orpheline au premier niveau que de la faire s'évanouir de la fiche —
     * c'est peut-être précisément celle qu'on cherchait à facturer.
     *
     * @param  iterable<int, self>  $entites
     * @return array<int, array{entite: self, enfants: array}>
     */
    public static function arbre(iterable $entites): array
    {
        $toutes = collect($entites);

        // Ce qui s'affiche sous la Vision : antennes, secteurs et cellules.
        $branchables = $toutes->whereIn('type', [self::TYPE_ANTENNE, self::TYPE_EXTENSION]);

        // Ce qui peut être PARENT : les antennes, et elles seules. Voir la hiérarchie ci-dessus.
        $parRef = $toutes->where('type', self::TYPE_ANTENNE)->keyBy('ref');

        $enfants = [];
        $racines = [];

        // Antennes d'abord, puis par nom : l'ordre d'un même niveau ne doit pas dépendre de
        // l'ordre d'insertion en base, sans quoi la fiche se réorganise à chaque synchronisation.
        foreach ($branchables->sortBy(fn (self $e) => [self::ORDRE[$e->type] ?? 9, $e->nom]) as $entite) {
            $parent = $entite->parent_ref;

            if ($parent !== null && $parent !== $entite->ref && $parRef->has($parent)) {
                $enfants[$parent][] = $entite;

                continue;
            }

            $racines[] = $entite;
        }

        $vus = [];
        $branches = [];

        foreach ($racines as $racine) {
            $branches[] = self::brancher($racine, $enfants, 0, $vus);
        }

        // AUCUNE ENTITÉ NE DOIT DISPARAÎTRE DE LA FICHE, jamais.
        //
        // Si A se déclare enfant de B et B enfant de A, aucun des deux n'est une racine : sans ce
        // rattrapage, tous deux s'évanouissaient purement et simplement — et une église absente de
        // la fiche est une église qu'on ne facture pas, sans que rien ne signale l'oubli. On les
        // remonte donc au premier niveau. Un arbre bizarre reste préférable à un arbre incomplet.
        foreach ($branchables as $entite) {
            if (! isset($vus[$entite->ref])) {
                $branches[] = self::brancher($entite, $enfants, 0, $vus);
            }
        }

        $visions = $toutes->where('type', self::TYPE_VISION)->sortBy('nom')->values();

        if ($visions->isEmpty()) {
            // Pas encore de Vision déclarée — l'installation n'a peut-être pas fini de se
            // synchroniser. On rend les antennes telles quelles plutôt que rien.
            return $branches;
        }

        // La Vision est la racine de tout : les antennes se rangent dessous. Il ne devrait y en
        // avoir qu'une (une installation = une communauté), mais s'il en arrivait plusieurs, les
        // suivantes s'affichent à côté plutôt que d'être tues.
        $arbre = [['entite' => $visions->first(), 'enfants' => $branches]];

        foreach ($visions->slice(1) as $autre) {
            $arbre[] = ['entite' => $autre, 'enfants' => []];
        }

        return $arbre;
    }

    /**
     * Une branche et sa descendance.
     *
     * DEUX GARDE-FOUS, ET AUCUN N'EST DÉCORATIF : ces liens de parenté viennent d'Internet.
     *
     *   · `$vus` empêche de redescendre dans une entité déjà placée. C'est ce qui coupe les cycles
     *     — sans lui, A parent de B et B parent de A feraient tourner cette récursion jusqu'à
     *     épuisement de la mémoire : un serveur mis à genoux par une requête parfaitement formée.
     *
     *   · La profondeur est bornée en plus, en ceinture et bretelles. Trois niveaux suffisent au
     *     métier — vision, antenne, secteur, cellule —, on en autorise dix.
     *
     * `$vus` est passé par référence parce qu'il sert aussi à l'appelant : ce qu'il reste dedans à
     * la fin dit quelles entités n'ont été rattachées à rien, et doivent être remontées à la main.
     *
     * @param  array<int|string, array<int, self>>  $enfants
     * @param  array<int|string, true>  $vus
     * @return array{entite: self, enfants: array}
     */
    private static function brancher(self $entite, array $enfants, int $profondeur, array &$vus): array
    {
        $vus[$entite->ref] = true;

        $suivants = ($profondeur >= 10) ? [] : ($enfants[$entite->ref] ?? []);

        $descendance = [];

        foreach ($suivants as $enfant) {
            if (isset($vus[$enfant->ref])) {
                continue;   // déjà placé ailleurs : on ne boucle pas
            }

            $descendance[] = self::brancher($enfant, $enfants, $profondeur + 1, $vus);
        }

        return ['entite' => $entite, 'enfants' => $descendance];
    }

    /**
     * La lignée : cette entité, puis ses ancêtres jusqu'à la Vision.
     *
     * C'est elle qui décide de l'accès — une entité n'écrit que si son abonnement ET ceux de tous
     * ses ancêtres sont actifs. On la calcule ici plutôt que dans la vue : la même règle sert à
     * l'affichage, à l'émission d'un abonnement et à la réponse de synchronisation, et trois
     * copies finiraient par diverger.
     *
     * LE `whereIn` CI-DESSOUS EST DÉLIBÉRÉ, PAS UN OUBLI — merci de ne pas l'« élargir ».
     *
     * On ne remonte que vers une VISION ou une ANTENNE parce que ce sont les deux seuls types qui
     * peuvent être parents. Secteurs et cellules sont FRÈRES, au même niveau sous leur antenne : une
     * cellule ne dépend pas d'un secteur, et aucune extension n'est jamais parente d'une autre.
     * Accepter EXTENSION ici rallongerait la chaîne selon une hiérarchie qui n'existe pas, et
     * exigerait pour vendre l'abonnement d'une entité qui n'est au-dessus de rien.
     *
     * La même règle est encodée dans arbre(), qui n'indexe que les ANTENNE comme parents. Les deux
     * doivent bouger ensemble ou pas du tout.
     *
     * @return array<int, self>
     */
    public function lignee(): array
    {
        $chaine = [$this];
        $courante = $this;
        $garde = 0;

        // Garde-fou : un arbre mal formé côté client (un parent qui pointe sur son propre enfant)
        // boucherait indéfiniment. Trois niveaux suffisent, on en autorise dix.
        while ($courante->parent_ref !== null && $garde++ < 10) {
            $parent = static::where('installation_id', $courante->installation_id)
                ->where('ref', $courante->parent_ref)
                ->whereIn('type', [self::TYPE_VISION, self::TYPE_ANTENNE])
                ->first();

            if (! $parent) {
                break;
            }

            $chaine[] = $parent;
            $courante = $parent;
        }

        return $chaine;
    }
}
