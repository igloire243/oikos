<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * LES OFFRES DE DÉPART — une licence, des accès, et un raccourci pour l'église seule.
 *
 * LES PRIX SONT DES VALEURS DE TRAVAIL. Ajustez-les avant la première vente ; ce qui compte ici
 * est la structure, pas les montants. Le taux retenu pour les francs est d'environ 2 800 FC pour
 * un dollar — à revoir, une grille tarifaire qui traîne un vieux taux perd de l'argent en silence.
 *
 * POURQUOI LA LICENCE SE PAIE À L'ANNÉE ET LES ACCÈS AU MOIS
 * -----------------------------------------------------------
 * Parce qu'un accès ne vaut rien sans la licence qui le porte. Si la licence était mensuelle, une
 * église qui achète son accès à dix jours de l'échéance de la vision paierait un mois entier pour
 * dix jours d'usage : le reste tomberait avec la licence, sans qu'elle y soit pour rien. À l'année,
 * la licence est un cadre large dans lequel les mois d'accès se rangent — le cas de bord ne
 * disparaît pas, mais il devient rare, et il se traite au prorata plutôt qu'au coup par coup.
 *
 * Les montants annuels valent DIX mois de l'ancien prix mensuel, pas douze : l'engagement d'un an
 * doit se payer quelque part, sinon il n'y a aucune raison de l'accepter.
 *
 * L'ÉGLISE SEULE RESTE AU MOIS. Rien n'est en dessous d'elle, donc aucun accès n'a à s'aligner sur
 * quoi que ce soit — et un montant annuel demandé d'un coup à une petite assemblée est un refus.
 *
 * COMMENT LES TROIS FAMILLES SE RÉPONDENT
 * ----------------------------------------
 *   LICENCE   payée par la vision, une fois pour toute la structure. Son prix suit la TAILLE du
 *             réseau, et son palier fixe le PLAFOND de ce que les entités peuvent souscrire.
 *             Elle inclut l'espace de la vision : le sommet ne paie pas deux fois.
 *
 *   ACCÈS     payé par chaque antenne, église ou cellule, pour son propre usage. Prix unique quel
 *             que soit le niveau : une cellule et une antenne qui veulent les mêmes modules paient
 *             le même prix. C'est ce qui rend la grille explicable en une phrase.
 *
 *   COMBINÉE  l'église seule, sans réseau au-dessus d'elle. Licence et accès en un seul prix,
 *             volontairement inférieur à la somme des deux : c'est le client le plus fréquent et
 *             le plus sensible au montant affiché.
 *
 * POURQUOI LA LICENCE STARTER N'EST PAS BRADÉE
 * ---------------------------------------------
 * Elle est obligatoire : rien ne fonctionne sans elle. Trop chère, elle devient un péage à
 * l'entrée qui décourage avant que le client ait vu la valeur du produit ; trop basse, elle laisse
 * croire que l'essentiel se paie ailleurs. Le revenu suit la taille par deux chemins — les paliers
 * de la licence, et le nombre d'accès vendus en dessous.
 *
 * LES CLÉS DE FONCTIONNALITÉS NE SONT PAS INVENTÉES : ce sont les treize modules que
 * Génération Joël sait déjà faire respecter (app/Support/SecteurMenu.php + CheckSecteurPermission),
 * et que config/modules.php nomme en clair. Un palier qui n'ouvre pas la trésorerie ne demande
 * aucun code neuf — une clé en moins dans le JSON.
 */
class PlanSeeder extends Seeder
{
    // LES CLÉS SONT CELLES DU PRODUIT, ET ELLES DIFFÈRENT SELON L'ESPACE.
    //
    // Côté vision, la notion « rapports » s'appelle `reports` ; côté secteur, `rapports`. Ce ne
    // sont pas deux orthographes de la même chose : ce sont deux pages, dans deux espaces, avec
    // deux middlewares distincts (`permission:` et `secteur.module:`). Les mélanger produit une
    // offre qui vend un module qu'aucun code n'ouvrira.

    /** ESPACE VISION — le socle du siège : les comptes, le réseau, le calendrier, les bilans.
     *  transferts + profils spirituels suivent le réseau (superadmin.entites) : ce sont ses
     *  sous-modules, découpés pour pouvoir les déléguer un par un. */
    private const VISION_STARTER = [
        'superadmin.users', 'superadmin.entites', 'superadmin.transferts', 'superadmin.profils',
        'superadmin.programs', 'superadmin.reports',
    ];

    /** ESPACE VISION — le socle, plus la diffusion et les médias. */
    private const VISION_STANDARD = [
        'superadmin.users', 'superadmin.entites', 'superadmin.transferts', 'superadmin.profils',
        'superadmin.programs', 'superadmin.reports',
        'superadmin.communications', 'superadmin.media',
    ];

    /**
     * LE SOCLE D'UNE ENTITÉ — et il traverse DEUX espaces.
     *
     * Le fichier des membres et les départements vivent dans l'espace de l'église ; l'appel nominal,
     * lui, est dans l'espace du DÉPARTEMENT — c'est l'écran du dimanche, celui qu'un chef de chorale
     * ouvre sur son téléphone. Vendre `secteur.programmes` sans `department.plannings` livrerait un
     * planning que personne ne peut pointer.
     */
    private const ENTITE_STARTER = [
        'secteur.membres', 'secteur.equipes', 'secteur.cultes', 'secteur.programmes',
        'department.equipe', 'department.plannings',
        // Le socle de coordination d'une ANTENNE (le même accès sert antenne, église et cellule).
        'antenne.extensions', 'antenne.bergers', 'antenne.membres', 'antenne.services', 'antenne.rapports',
    ];

    /** LE SOCLE, plus le suivi pastoral, les activités et les bilans des trois espaces. */
    private const ENTITE_STANDARD = [
        'secteur.membres', 'secteur.transferts', 'secteur.equipes', 'secteur.cultes', 'secteur.programmes',
        'secteur.activites', 'secteur.visites', 'secteur.prieres', 'secteur.rapports',
        'secteur.messagerie',
        'department.equipe', 'department.plannings', 'department.programmes', 'department.rapports',
        // L'antenne au complet : transferts, visites d'extensions, réunions mensuelles, dossiers
        // disciplinaires des cadres, départements, finances, communications.
        'antenne.extensions', 'antenne.bergers', 'antenne.membres', 'antenne.transferts',
        'antenne.pastoral', 'antenne.visites', 'antenne.services', 'antenne.departements',
        'antenne.finances', 'antenne.rapports', 'antenne.reunions', 'antenne.dossiers', 'antenne.communications',
    ];

    public function run(): void
    {
        foreach ([...$this->licences(), ...$this->acces(), ...$this->egliseSeule(), $this->fondateur()] as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }

    /**
     * LES LICENCES. `prix_usd_cents` porte le PREMIER palier — celui qu'on annonce précédé d'« à
     * partir de ». La grille complète vit dans `paliers_taille`, et son dernier échelon a un `max`
     * nul : c'est lui qui attrape les réseaux qui dépassent tout, sans quoi un client de deux cents
     * églises ne trouverait aucun prix.
     *
     * La taille se compte en ENTITÉS DÉCLARÉES par l'installation — antennes et églises — hors
     * vision. C'est un nombre que la console connaît déjà, donc vérifiable, et non un chiffre que
     * le client s'attribue lui-même.
     */
    private function licences(): array
    {
        return [
            [
                'code' => 'LICENCE_STARTER',
                'nature' => Plan::LICENCE,
                'palier' => Plan::STARTER,
                'niveau' => 'VISION',
                'nom' => 'Licence Starter',
                'argumentaire' => "Met le système en service pour toute la structure et ouvre l'espace "
                    ."de la vision. Pour un réseau qui démarre et veut d'abord voir tourner l'essentiel.",
                'prix_usd_cents' => 25000,
                'prix_cdf' => 700000,
                'paliers_taille' => [
                    ['max' => 5, 'prix_usd_cents' => 25000, 'prix_cdf' => 700000],
                    ['max' => 20, 'prix_usd_cents' => 45000, 'prix_cdf' => 1260000],
                    ['max' => null, 'prix_usd_cents' => 75000, 'prix_cdf' => 2100000],
                ],
                'plafond_acces' => Plan::STANDARD,
                'periode_mois' => 12,
                'quotas' => ['antennes' => null, 'extensions' => null],
                'fonctionnalites' => self::VISION_STARTER,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 10,
            ],
            [
                'code' => 'LICENCE_STANDARD',
                'nature' => Plan::LICENCE,
                'palier' => Plan::STANDARD,
                'niveau' => 'VISION',
                'nom' => 'Licence Standard',
                'argumentaire' => "Rapports consolidés sur l'ensemble du réseau, médias, et accès "
                    .'Premium autorisé pour les entités qui en ont besoin.',
                'prix_usd_cents' => 40000,
                'prix_cdf' => 1120000,
                'paliers_taille' => [
                    ['max' => 5, 'prix_usd_cents' => 40000, 'prix_cdf' => 1120000],
                    ['max' => 20, 'prix_usd_cents' => 70000, 'prix_cdf' => 1960000],
                    ['max' => null, 'prix_usd_cents' => 110000, 'prix_cdf' => 3080000],
                ],
                'plafond_acces' => Plan::PREMIUM,
                'periode_mois' => 12,
                'quotas' => ['antennes' => null, 'extensions' => null],
                'fonctionnalites' => self::VISION_STANDARD,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 11,
            ],
            [
                'code' => 'LICENCE_PREMIUM',
                'nature' => Plan::LICENCE,
                'palier' => Plan::PREMIUM,
                'niveau' => 'VISION',
                'nom' => 'Licence Premium',
                'argumentaire' => 'Tous les modules au niveau de la vision, accompagnement à la mise '
                    .'en place, formation des responsables et sauvegardes suivies.',
                'prix_usd_cents' => 60000,
                'prix_cdf' => 1680000,
                'paliers_taille' => [
                    ['max' => 5, 'prix_usd_cents' => 60000, 'prix_cdf' => 1680000],
                    ['max' => 20, 'prix_usd_cents' => 100000, 'prix_cdf' => 2800000],
                    ['max' => null, 'prix_usd_cents' => 160000, 'prix_cdf' => 4480000],
                ],
                'plafond_acces' => Plan::PREMIUM,
                'periode_mois' => 12,
                'quotas' => ['antennes' => null, 'extensions' => null],
                'fonctionnalites' => null,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 12,
            ],
        ];
    }

    /**
     * LES ACCÈS. `niveau = TOUS` : une antenne et une cellule achètent le même palier au même prix.
     * C'est le choix qui rend la grille lisible — le prix suit ce dont on se sert, pas la place
     * qu'on occupe dans l'organigramme.
     */
    private function acces(): array
    {
        return [
            [
                'code' => 'ACCES_STARTER',
                'nature' => Plan::ACCES,
                'palier' => Plan::STARTER,
                'niveau' => 'TOUS',
                'nom' => 'Accès Starter',
                'argumentaire' => 'Le socle : les membres, les départements, les cultes et les plannings '
                    ."avec l'appel nominal. De quoi tenir une assemblée au quotidien.",
                'prix_usd_cents' => 800,
                'prix_cdf' => 22000,
                'paliers_taille' => null,
                'plafond_acces' => null,
                'periode_mois' => 1,
                'quotas' => ['membres' => 300, 'comptes' => 5],
                'fonctionnalites' => self::ENTITE_STARTER,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 20,
            ],
            [
                'code' => 'ACCES_STANDARD',
                'nature' => Plan::ACCES,
                'palier' => Plan::STANDARD,
                'niveau' => 'TOUS',
                'nom' => 'Accès Standard',
                'argumentaire' => 'Ajoute le suivi pastoral — visites, sujets de prière —, les activités '
                    .'et les rapports calculés sur ce qui a été réellement pointé.',
                'prix_usd_cents' => 1500,
                'prix_cdf' => 42000,
                'paliers_taille' => null,
                'plafond_acces' => null,
                'periode_mois' => 1,
                'quotas' => ['membres' => 1500, 'comptes' => 20],
                'fonctionnalites' => self::ENTITE_STANDARD,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 21,
            ],
            [
                'code' => 'ACCES_PREMIUM',
                'nature' => Plan::ACCES,
                'palier' => Plan::PREMIUM,
                'niveau' => 'TOUS',
                'nom' => 'Accès Premium',
                'argumentaire' => 'Tous les modules, sans limite de membres ni de comptes : discipulariat, '
                    .'discipline, trésorerie et médias compris.',
                'prix_usd_cents' => 2500,
                'prix_cdf' => 70000,
                'paliers_taille' => null,
                'plafond_acces' => null,
                'periode_mois' => 1,
                'quotas' => ['membres' => null, 'comptes' => null],
                'fonctionnalites' => null,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 22,
            ],
        ];
    }

    /**
     * L'ÉGLISE SEULE. Licence et accès en un seul prix, inférieur à la somme des deux — c'est
     * délibéré, et c'est là que se joue le volume : une assemblée sans réseau au-dessus d'elle est
     * le client le plus fréquent, et celui qui compare le montant affiché avant tout le reste.
     */
    private function egliseSeule(): array
    {
        return [
            [
                'code' => 'SEULE_STARTER',
                'nature' => Plan::COMBINEE,
                'palier' => Plan::STARTER,
                'niveau' => 'EXTENSION',
                'nom' => 'Église seule — Starter',
                'argumentaire' => 'Tout compris pour une assemblée sans réseau au-dessus d\'elle : '
                    .'membres, départements, cultes et plannings.',
                'prix_usd_cents' => 1500,
                'prix_cdf' => 42000,
                'paliers_taille' => null,
                'plafond_acces' => null,
                'periode_mois' => 1,
                'quotas' => ['membres' => 300, 'comptes' => 5],
                'fonctionnalites' => array_merge(['superadmin.users'], self::ENTITE_STARTER),
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 30,
            ],
            [
                'code' => 'SEULE_STANDARD',
                'nature' => Plan::COMBINEE,
                'palier' => Plan::STANDARD,
                'niveau' => 'EXTENSION',
                'nom' => 'Église seule — Standard',
                'argumentaire' => 'Le suivi pastoral, les activités et les rapports en plus. '
                    .'Le choix courant pour une église déjà organisée en départements.',
                'prix_usd_cents' => 2500,
                'prix_cdf' => 70000,
                'paliers_taille' => null,
                'plafond_acces' => null,
                'periode_mois' => 1,
                'quotas' => ['membres' => 1500, 'comptes' => 20],
                'fonctionnalites' => array_merge(['superadmin.users', 'superadmin.reports', 'superadmin.media'], self::ENTITE_STANDARD),
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 31,
            ],
            [
                'code' => 'SEULE_PREMIUM',
                'nature' => Plan::COMBINEE,
                'palier' => Plan::PREMIUM,
                'niveau' => 'EXTENSION',
                'nom' => 'Église seule — Premium',
                'argumentaire' => 'Tous les modules, sans limite : discipulariat, discipline, '
                    .'trésorerie et médias compris.',
                'prix_usd_cents' => 4000,
                'prix_cdf' => 112000,
                'paliers_taille' => null,
                'plafond_acces' => null,
                'periode_mois' => 1,
                'quotas' => ['membres' => null, 'comptes' => null],
                'fonctionnalites' => null,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 32,
            ],
        ];
    }

    /** L'offre maison : gratuite, invisible du site public, aucun mode de paiement à encaisser. */
    private function fondateur(): array
    {
        return [
            'code' => 'FONDATEUR',
            'nature' => Plan::LICENCE,
            'palier' => Plan::PREMIUM,
            'niveau' => 'VISION',
            'nom' => 'Fondateur',
            'argumentaire' => 'Offert. Pour la communauté d\'origine et les installations de démonstration.',
            'prix_usd_cents' => 0,
            'prix_cdf' => 0,
            'paliers_taille' => null,
            'plafond_acces' => Plan::PREMIUM,
            'periode_mois' => 12,
            'quotas' => null,
            'fonctionnalites' => null,
            'modes_paiement' => [],
            'is_public' => false,
            'ordre' => 90,
        ];
    }
}
