<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * UNE OFFRE. Trois natures, trois paliers.
 *
 * LA LICENCE ALLUME, L'ACCÈS OUVRE
 * ---------------------------------
 * La licence est payée une fois pour toute la structure, par la vision : elle met le système en
 * service, et elle inclut l'espace administratif de la vision elle-même — celle-ci ne paie donc
 * pas d'accès en plus. Ensuite, chaque entité (antenne, église, cellule) paie SON accès, au palier
 * qui lui convient. Une cellule en Standard et une grande église en Premium peuvent coexister dans
 * le même réseau.
 *
 * L'OFFRE COMBINÉE EST UNE LICENCE RÉDUITE, PAS UN FORFAIT TOUT COMPRIS
 * ----------------------------------------------------------------------
 * Une église seule a besoin d'une licence (personne au-dessus d'elle ne la paiera) et d'un accès.
 * COMBINEE est la PREMIÈRE des deux lignes : une licence à l'année, à tarif réduit, qui joue le
 * rôle de licence pour la cascade. L'assemblée souscrit en plus son accès mensuel
 * (ACCES_SEULE_*). Le commentaire précédent annonçait « les deux dans un prix unique » — ce n'est
 * pas ce que fait le seeder, et c'est la lecture qui avait fait écrire « tout compris, un seul
 * prix » sur la page d'accueil, où le client découvrait le mensuel à la signature.
 */
class Plan extends Model
{
    protected $table = 'plans';
    protected $primaryKey = 'plan_id';

    public const LICENCE = 'LICENCE';
    public const ACCES = 'ACCES';
    public const COMBINEE = 'COMBINEE';

    public const NATURES = [
        self::LICENCE => 'Licence de la structure',
        self::ACCES => 'Accès par entité',
        self::COMBINEE => 'Église seule (licence réduite)',
    ];

    public const STARTER = 'STARTER';
    public const STANDARD = 'STANDARD';
    public const PREMIUM = 'PREMIUM';

    /**
     * TOUS LES PALIERS CONNUS, y compris ceux qu'on ne vend plus. Sert à AFFICHER : un abonnement
     * Starter signé avant 2026-09-12 doit continuer à se nommer « Starter » sur sa facture et dans
     * l'historique du client. Retirer la clé afficherait « STARTER » brut à ces endroits.
     */
    public const PALIERS = [
        self::STARTER => 'Starter',
        self::STANDARD => 'Standard',
        self::PREMIUM => 'Premium',
    ];

    /**
     * CE QU'ON PEUT ENCORE COMPOSER. Trois paliers ont été ramenés à deux : laisser « Starter »
     * dans le formulaire de création d'offre permettrait de reconstituer en trois clics la grille
     * qu'on vient de simplifier, sans que personne ne s'en aperçoive avant la page publique.
     */
    public const PALIERS_VENDABLES = [
        self::STANDARD => 'Standard',
        self::PREMIUM => 'Premium',
    ];

    /**
     * Du plus petit au plus grand — c'est cet ordre que `autorise()` compare. STARTER y reste :
     * une licence Starter encore en cours doit continuer à plafonner les accès de son réseau.
     */
    public const RANG_PALIERS = [self::STARTER => 1, self::STANDARD => 2, self::PREMIUM => 3];

    protected $fillable = [
        'code', 'nature', 'palier', 'niveau', 'nom', 'argumentaire',
        'prix_usd_cents', 'prix_cdf', 'prix_par_entite_usd_cents', 'prix_par_entite_cdf',
        'paliers_taille', 'plafond_acces',
        'periode_mois', 'quotas', 'fonctionnalites', 'modes_paiement', 'is_public', 'ordre',
    ];

    protected $casts = [
        'quotas' => 'array',
        'fonctionnalites' => 'array',
        'modes_paiement' => 'array',
        'paliers_taille' => 'array',
        'is_public' => 'boolean',
    ];

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class, 'plan_id', 'plan_id');
    }

    public function estLicence(): bool
    {
        return $this->nature === self::LICENCE;
    }

    /**
     * « mois », « an », ou « N mois ».
     *
     * Écrit ici plutôt que dans chaque vue : trois gabarits affichaient déjà la période, et
     * « par 12 mois » — ce que donnait la formule précédente pour une licence annuelle — n'est
     * pas du français. Un prix annoncé dans une unité qu'on ne dit pas comme ça se relit deux fois.
     */
    public function libellePeriode(): string
    {
        return match ((int) $this->periode_mois) {
            1 => 'mois',
            12 => 'an',
            default => $this->periode_mois.' mois',
        };
    }

    public function estAnnuel(): bool
    {
        return (int) $this->periode_mois === 12;
    }

    public function estCombinee(): bool
    {
        return $this->nature === self::COMBINEE;
    }

    public function prixUsd(): string
    {
        return $this->formaterUsd($this->prix_usd_cents);
    }

    /**
     * Le prix affiché en tête de carte.
     *
     * Pour une licence, `prix_usd_cents` porte le PREMIER palier — le plus petit réseau. C'est ce
     * chiffre qu'on annonce, précédé d'« à partir de » : annoncer le plus cher ferait fuir une
     * petite communauté qui ne paiera jamais ce montant.
     */
    public function prixAfficheUsd(): string
    {
        return $this->prixUsd();
    }

    /**
     * Cette offre se facture-t-elle à la FORMULE — un socle, plus un montant par entité ?
     *
     * C'est le mode retenu depuis le 2026-09-12. Le socle est `prix_usd_cents` ; le montant par
     * entité est la colonne dédiée. Voir la migration tarif_par_entite_sur_les_plans pour ce que
     * les tranches avaient d'intenable.
     */
    public function aUnTarifParEntite(): bool
    {
        return $this->prix_par_entite_usd_cents !== null;
    }

    /** L'ancien mode : un forfait par tranche de taille. Conservé pour les offres déjà vendues. */
    public function aUneGrilleDeTailles(): bool
    {
        return is_array($this->paliers_taille) && count($this->paliers_taille) > 1;
    }

    /** Le prix dépend-il de la taille du réseau, d'une manière ou d'une autre ? */
    public function suitLaTaille(): bool
    {
        return $this->aUnTarifParEntite() || $this->aUneGrilleDeTailles();
    }

    /**
     * Le prix réel pour un réseau de N entités.
     *
     * LA FORMULE D'ABORD, LES TRANCHES EN REPLI. Une offre signée sous l'ancien mode doit continuer
     * à se facturer comme elle a été vendue : on ne migre pas les abonnements en place, on laisse
     * les deux modes cohabiter et c'est la présence du montant par entité qui tranche.
     */
    public function prixPourTaille(int $entites): array
    {
        if ($this->aUnTarifParEntite()) {
            return [
                'usd_cents' => (int) $this->prix_usd_cents + $entites * (int) $this->prix_par_entite_usd_cents,
                'cdf' => (int) $this->prix_cdf + $entites * (int) $this->prix_par_entite_cdf,
            ];
        }

        $grille = $this->paliers_taille ?: [];

        foreach ($grille as $palier) {
            $max = $palier['max'] ?? null;

            if ($max === null || $entites <= (int) $max) {
                return [
                    'usd_cents' => (int) ($palier['prix_usd_cents'] ?? $this->prix_usd_cents),
                    'cdf' => (int) ($palier['prix_cdf'] ?? $this->prix_cdf),
                ];
            }
        }

        return ['usd_cents' => (int) $this->prix_usd_cents, 'cdf' => (int) $this->prix_cdf];
    }

    /** Le montant par entité, formaté — pour les écrans. */
    public function parEntiteUsd(): string
    {
        return $this->formaterUsd((int) $this->prix_par_entite_usd_cents);
    }

    /**
     * Cette licence autorise-t-elle une entité à souscrire tel palier d'accès ?
     *
     * C'est ce qui donne un contenu vérifiable aux niveaux de licence. Une licence sans plafond
     * déclaré n'interdit rien — l'absence de règle vaut permission, sans quoi une ancienne offre
     * sans `plafond_acces` bloquerait silencieusement toutes les ventes du client concerné.
     */
    public function autorise(string $palierAcces): bool
    {
        if (! $this->plafond_acces) {
            return true;
        }

        $demande = self::RANG_PALIERS[$palierAcces] ?? 99;
        $plafond = self::RANG_PALIERS[$this->plafond_acces] ?? 99;

        return $demande <= $plafond;
    }

    /**
     * null = tous les modes publics sont ouverts. Une liste = une offre négociée, qui n'en ouvre
     * que certains.
     */
    public function accepte(string $mode): bool
    {
        return $this->modes_paiement === null || in_array($mode, $this->modes_paiement, true);
    }

    private function formaterUsd(int $centimes): string
    {
        return number_format($centimes / 100, 2, ',', ' ').' $';
    }
}
