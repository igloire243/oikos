<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * LES OFFRES DE DÉPART — trois natures, trois paliers, et le cas de l'église seule.
 *
 * LES PRIX SONT DES VALEURS DE TRAVAIL, ajustables ensuite dans la console sans toucher au code
 * (voir PlanController). Taux retenu pour l'équivalent en francs : 1 USD ≈ 2 300 CDF. Un taux qui
 * traîne fait perdre de l'argent en silence — à revoir avant la première vente.
 *
 * QUI PAIE QUOI
 * -------------
 *   LICENCE   payée par la VISION, à l'année, une fois pour toute la structure. Son prix suit la
 *             TAILLE du réseau (paliers_taille), et son palier fixe le PLAFOND de ce que les
 *             entités peuvent souscrire (plafond_acces). Elle inclut l'espace de la vision.
 *
 *   ACCÈS     payé au MOIS par chaque ÉGLISE / CELLULE (ouvre l'espace de l'église ET l'espace
 *             Département — le département ne paie jamais séparément) et par chaque ANTENNE
 *             (ouvre l'espace antenne). Deux grilles distinctes : église et antenne.
 *
 *   COMBINÉE  l'ÉGLISE SEULE, sans réseau. Elle paie DEUX lignes : une licence réduite à l'année
 *             (qui joue le rôle de licence pour la cascade et finance l'hébergement) + un accès
 *             mensuel « église seule » à plancher plus élevé (elle a tout l'espace vision pour
 *             elle). Quotas verrouillés : 0 antenne, 1 extension.
 *
 * LES CLÉS DE FONCTIONNALITÉS sont celles de config/modules.php, et elles portent leur espace :
 * `superadmin.reports` (vision) ≠ `secteur.rapports` (église) ≠ `antenne.rapports`. Seules les clés
 * VENDABLES comptent ici (les invendables — paramètres, sécurité, messagerie déléguable — sont
 * toujours ouvertes et ignorées à l'émission de la licence).
 *
 * `fonctionnalites = null` = « tous les modules de la ou des familles que cette nature ouvre, y
 * compris ceux ajoutés plus tard ». À n'utiliser que pour une LICENCE (une seule famille : vision).
 * Pour un ACCÈS ou une COMBINÉE, on ÉNUMÈRE — sinon un accès église Premium ouvrirait aussi les
 * écrans d'antenne.
 */
class PlanSeeder extends Seeder
{
    // ---- ESPACE VISION (LICENCE + COMBINÉE) ------------------------------------------------
    private const VISION_STARTER = [
        'superadmin.users', 'superadmin.entites', 'superadmin.programs', 'superadmin.reports',
        'superadmin.communications',
    ];
    private const VISION_STANDARD = [
        'superadmin.users', 'superadmin.entites', 'superadmin.programs', 'superadmin.reports',
        'superadmin.communications', 'superadmin.media', 'superadmin.finances',
    ];
    private const VISION_PREMIUM = [
        'superadmin.users', 'superadmin.entites', 'superadmin.transferts', 'superadmin.profils',
        'superadmin.programs', 'superadmin.finances', 'superadmin.reports',
        'superadmin.communications', 'superadmin.media',
    ];

    // ---- ACCÈS ÉGLISE / CELLULE : espace de l'église + espace Département ------------------
    private const EGLISE_STARTER = [
        'secteur.membres', 'secteur.cultes', 'secteur.tresorerie', 'secteur.rapports',
        'secteur.equipes', 'secteur.messagerie',
        'department.equipe', 'department.plannings', 'department.rapports',
    ];
    private const EGLISE_STANDARD = [
        'secteur.membres', 'secteur.cultes', 'secteur.tresorerie', 'secteur.rapports',
        'secteur.equipes', 'secteur.messagerie', 'secteur.programmes', 'secteur.prieres',
        'secteur.visites', 'secteur.activites', 'secteur.discipulariat', 'secteur.medias',
        'secteur.discipline',
        'department.equipe', 'department.plannings', 'department.rapports',
        'department.programmes', 'department.ressources',
    ];
    private const EGLISE_PREMIUM = [
        'secteur.membres', 'secteur.transferts', 'secteur.visites', 'secteur.prieres',
        'secteur.cultes', 'secteur.programmes', 'secteur.activites', 'secteur.discipulariat',
        'secteur.equipes', 'secteur.discipline', 'secteur.tresorerie', 'secteur.rapports',
        'secteur.medias', 'secteur.messagerie',
        'department.equipe', 'department.plannings', 'department.programmes', 'department.rapports',
        'department.ressources',
    ];

    // ---- ACCÈS ANTENNE ------------------------------------------------------------------------
    private const ANTENNE_STARTER = [
        'antenne.extensions', 'antenne.bergers', 'antenne.membres', 'antenne.rapports',
        'antenne.services', 'antenne.communications',
    ];
    private const ANTENNE_STANDARD = [
        'antenne.extensions', 'antenne.bergers', 'antenne.membres', 'antenne.rapports',
        'antenne.services', 'antenne.communications', 'antenne.pastoral', 'antenne.visites',
        'antenne.reunions', 'antenne.departements', 'antenne.finances',
    ];
    private const ANTENNE_PREMIUM = [
        'antenne.extensions', 'antenne.bergers', 'antenne.membres', 'antenne.transferts',
        'antenne.pastoral', 'antenne.visites', 'antenne.services', 'antenne.departements',
        'antenne.finances', 'antenne.rapports', 'antenne.reunions', 'antenne.dossiers',
        'antenne.communications',
    ];

    public function run(): void
    {
        $offres = array_merge(
            $this->licencesVision(),
            $this->accesEglise(),
            $this->accesAntenne(),
            $this->egliseSeule(),
            [$this->fondateur()],
        );

        $codes = array_column($offres, 'code');

        foreach ($offres as $offre) {
            Plan::updateOrCreate(['code' => $offre['code']], $offre);
        }

        // Retrait des offres d'un ancien jeu (LICENCE_STARTER, ACCES_STARTER, SEULE_*…) — mais
        // SEULEMENT si elles n'ont jamais été vendues. Une offre rattachée à un abonnement reste :
        // c'est elle qui explique ce que le client a payé (clé étrangère + valeur d'historique).
        Plan::whereNotIn('code', $codes)
            ->whereDoesntHave('abonnements')
            ->delete();
    }

    /**
     * LICENCE VISION — annuel. `prix_usd_cents` porte le premier échelon (« à partir de »), la
     * grille complète vit dans `paliers_taille`. Dernier échelon `max = null` : il attrape les
     * réseaux qui dépassent tout. La taille se compte en entités déclarées (antennes + églises),
     * hors vision — un nombre que la console connaît, pas que le client s'attribue.
     */
    private function licencesVision(): array
    {
        return [
            [
                'code' => 'LICENCE_VISION_STARTER',
                'nature' => Plan::LICENCE,
                'palier' => Plan::STARTER,
                'niveau' => 'VISION',
                'nom' => 'Licence Vision — Starter',
                'argumentaire' => "Met le système en service pour toute la structure et ouvre l'espace "
                    ."de la vision : comptes, réseau, calendrier, bilans et communications.",
                'prix_usd_cents' => 15000,
                'prix_cdf' => 345000,
                'paliers_taille' => [
                    ['max' => 10, 'prix_usd_cents' => 15000, 'prix_cdf' => 345000],
                    ['max' => 50, 'prix_usd_cents' => 26000, 'prix_cdf' => 598000],
                    ['max' => 90, 'prix_usd_cents' => 40000, 'prix_cdf' => 920000],
                    ['max' => null, 'prix_usd_cents' => 60000, 'prix_cdf' => 1380000],
                ],
                'plafond_acces' => Plan::STARTER,
                'periode_mois' => 12,
                'quotas' => null,
                'fonctionnalites' => self::VISION_STARTER,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 10,
            ],
            [
                'code' => 'LICENCE_VISION_STANDARD',
                'nature' => Plan::LICENCE,
                'palier' => Plan::STANDARD,
                'niveau' => 'VISION',
                'nom' => 'Licence Vision — Standard',
                'argumentaire' => "Ajoute les médias de la vision et la trésorerie consolidée du réseau. "
                    .'Autorise un accès Standard pour les entités.',
                'prix_usd_cents' => 26000,
                'prix_cdf' => 598000,
                'paliers_taille' => [
                    ['max' => 10, 'prix_usd_cents' => 26000, 'prix_cdf' => 598000],
                    ['max' => 50, 'prix_usd_cents' => 44000, 'prix_cdf' => 1012000],
                    ['max' => 90, 'prix_usd_cents' => 66000, 'prix_cdf' => 1518000],
                    ['max' => null, 'prix_usd_cents' => 100000, 'prix_cdf' => 2300000],
                ],
                'plafond_acces' => Plan::STANDARD,
                'periode_mois' => 12,
                'quotas' => null,
                'fonctionnalites' => self::VISION_STANDARD,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 11,
            ],
            [
                'code' => 'LICENCE_VISION_PREMIUM',
                'nature' => Plan::LICENCE,
                'palier' => Plan::PREMIUM,
                'niveau' => 'VISION',
                'nom' => 'Licence Vision — Premium',
                'argumentaire' => 'Tous les modules au niveau de la vision : transferts sur tout le parc, '
                    .'profils spirituels, accompagnement et sauvegardes suivies. Autorise un accès Premium.',
                'prix_usd_cents' => 40000,
                'prix_cdf' => 920000,
                'paliers_taille' => [
                    ['max' => 10, 'prix_usd_cents' => 40000, 'prix_cdf' => 920000],
                    ['max' => 50, 'prix_usd_cents' => 69000, 'prix_cdf' => 1587000],
                    ['max' => 90, 'prix_usd_cents' => 105000, 'prix_cdf' => 2415000],
                    ['max' => null, 'prix_usd_cents' => 156000, 'prix_cdf' => 3588000],
                ],
                'plafond_acces' => Plan::PREMIUM,
                'periode_mois' => 12,
                'quotas' => null,
                'fonctionnalites' => null,   // LICENCE : une seule famille (vision), null = tout, futurs compris
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 12,
            ],
        ];
    }

    /** ACCÈS ÉGLISE / CELLULE — mensuel. Ouvre l'espace de l'église ET l'espace Département. */
    private function accesEglise(): array
    {
        return [
            [
                'code' => 'ACCES_EGLISE_STARTER',
                'nature' => Plan::ACCES,
                'palier' => Plan::STARTER,
                'niveau' => 'EXTENSION',
                'nom' => 'Accès Église — Starter',
                'argumentaire' => 'Le socle pour tenir une assemblée : membres, cultes et présences, '
                    .'trésorerie locale, rapports, équipes, et le pointage côté département.',
                'prix_usd_cents' => 500,
                'prix_cdf' => 11500,
                'paliers_taille' => null,
                'plafond_acces' => null,
                'periode_mois' => 1,
                'quotas' => ['membres' => 300, 'comptes' => 5],
                'fonctionnalites' => self::EGLISE_STARTER,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 20,
            ],
            [
                'code' => 'ACCES_EGLISE_STANDARD',
                'nature' => Plan::ACCES,
                'palier' => Plan::STANDARD,
                'niveau' => 'EXTENSION',
                'nom' => 'Accès Église — Standard',
                'argumentaire' => 'Ajoute les programmes et le calendrier des activités, le suivi pastoral, '
                    .'le discipulariat, les médias, la discipline, et les programmes internes du département.',
                'prix_usd_cents' => 1000,
                'prix_cdf' => 23000,
                'paliers_taille' => null,
                'plafond_acces' => null,
                'periode_mois' => 1,
                'quotas' => ['membres' => 1500, 'comptes' => 20],
                'fonctionnalites' => self::EGLISE_STANDARD,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 21,
            ],
            [
                'code' => 'ACCES_EGLISE_PREMIUM',
                'nature' => Plan::ACCES,
                'palier' => Plan::PREMIUM,
                'niveau' => 'EXTENSION',
                'nom' => 'Accès Église — Premium',
                'argumentaire' => 'Tout l\'espace de l\'église et du département, transferts de membres compris, '
                    .'sans limite de membres ni de comptes.',
                'prix_usd_cents' => 1500,
                'prix_cdf' => 34500,
                'paliers_taille' => null,
                'plafond_acces' => null,
                'periode_mois' => 1,
                'quotas' => ['membres' => null, 'comptes' => null],
                'fonctionnalites' => self::EGLISE_PREMIUM,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 22,
            ],
        ];
    }

    /** ACCÈS ANTENNE — mensuel. Ouvre l'espace de coordination régionale. */
    private function accesAntenne(): array
    {
        return [
            [
                'code' => 'ACCES_ANTENNE_STARTER',
                'nature' => Plan::ACCES,
                'palier' => Plan::STARTER,
                'niveau' => 'ANTENNE',
                'nom' => 'Accès Antenne — Starter',
                'argumentaire' => 'Coordonner sa zone : églises rattachées, affectation des bergers, '
                    .'annuaire régional, services, rapports et communications.',
                'prix_usd_cents' => 1000,
                'prix_cdf' => 23000,
                'paliers_taille' => null,
                'plafond_acces' => null,
                'periode_mois' => 1,
                'quotas' => ['comptes' => 10],
                'fonctionnalites' => self::ANTENNE_STARTER,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 25,
            ],
            [
                'code' => 'ACCES_ANTENNE_STANDARD',
                'nature' => Plan::ACCES,
                'palier' => Plan::STANDARD,
                'niveau' => 'ANTENNE',
                'nom' => 'Accès Antenne — Standard',
                'argumentaire' => 'Ajoute le suivi pastoral, les visites d\'extensions avec rapport et PV, '
                    .'les réunions mensuelles, les départements et les finances de l\'antenne.',
                'prix_usd_cents' => 1500,
                'prix_cdf' => 34500,
                'paliers_taille' => null,
                'plafond_acces' => null,
                'periode_mois' => 1,
                'quotas' => ['comptes' => 25],
                'fonctionnalites' => self::ANTENNE_STANDARD,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 26,
            ],
            [
                'code' => 'ACCES_ANTENNE_PREMIUM',
                'nature' => Plan::ACCES,
                'palier' => Plan::PREMIUM,
                'niveau' => 'ANTENNE',
                'nom' => 'Accès Antenne — Premium',
                'argumentaire' => 'Tout l\'espace antenne : transferts de membres et dossiers disciplinaires '
                    .'des cadres compris, sans limite de comptes.',
                'prix_usd_cents' => 2000,
                'prix_cdf' => 46000,
                'paliers_taille' => null,
                'plafond_acces' => null,
                'periode_mois' => 1,
                'quotas' => ['comptes' => null],
                'fonctionnalites' => self::ANTENNE_PREMIUM,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 27,
            ],
        ];
    }

    /**
     * L'ÉGLISE SEULE — deux lignes.
     *
     *   1. LICENCE COMBINÉE (annuel, prix fixe) : joue le rôle de licence pour la cascade et
     *      finance l'hébergement du client. Quotas verrouillés à 0 antenne / 1 extension : une
     *      église seule ne peut pas se transformer en réseau sans changer d'offre.
     *   2. ACCÈS ÉGLISE SEULE (mensuel, plancher 20 $) : l'espace de l'église et du département,
     *      plus cher qu'un accès en réseau parce qu'elle a tout l'espace vision pour elle.
     */
    private function egliseSeule(): array
    {
        return [
            [
                'code' => 'COMBINEE_STANDARD',
                'nature' => Plan::COMBINEE,
                'palier' => Plan::STANDARD,
                'niveau' => 'VISION',
                'nom' => 'Église seule — Licence réduite (Standard)',
                'argumentaire' => "Licence à l'année pour une assemblée sans réseau : ouvre l'espace de la "
                    ."vision (config, site public, médias, comptes) et finance l'hébergement. À compléter "
                    .'par un accès église seule mensuel.',
                'prix_usd_cents' => 13000,
                'prix_cdf' => 299000,
                'paliers_taille' => null,
                'plafond_acces' => Plan::STANDARD,
                'periode_mois' => 12,
                'quotas' => ['antennes' => 0, 'extensions' => 1],
                'fonctionnalites' => self::VISION_STANDARD,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 30,
            ],
            [
                'code' => 'COMBINEE_PREMIUM',
                'nature' => Plan::COMBINEE,
                'palier' => Plan::PREMIUM,
                'niveau' => 'VISION',
                'nom' => 'Église seule — Licence réduite (Premium)',
                'argumentaire' => "Même chose, tous les modules de l'espace vision compris. Autorise un "
                    .'accès église seule Premium.',
                'prix_usd_cents' => 13000,
                'prix_cdf' => 299000,
                'paliers_taille' => null,
                'plafond_acces' => Plan::PREMIUM,
                'periode_mois' => 12,
                'quotas' => ['antennes' => 0, 'extensions' => 1],
                'fonctionnalites' => self::VISION_PREMIUM,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 31,
            ],
            [
                'code' => 'ACCES_SEULE_STANDARD',
                'nature' => Plan::ACCES,
                'palier' => Plan::STANDARD,
                'niveau' => 'EXTENSION',
                'nom' => 'Accès Église seule — Standard',
                'argumentaire' => "L'espace de l'église et du département pour une assemblée autonome : "
                    .'membres, cultes, trésorerie, programmes, pastoral, discipulariat, médias.',
                'prix_usd_cents' => 2000,
                'prix_cdf' => 46000,
                'paliers_taille' => null,
                'plafond_acces' => null,
                'periode_mois' => 1,
                'quotas' => ['membres' => 1500, 'comptes' => 20],
                'fonctionnalites' => self::EGLISE_STANDARD,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 32,
            ],
            [
                'code' => 'ACCES_SEULE_PREMIUM',
                'nature' => Plan::ACCES,
                'palier' => Plan::PREMIUM,
                'niveau' => 'EXTENSION',
                'nom' => 'Accès Église seule — Premium',
                'argumentaire' => 'Tout l\'espace de l\'église et du département, transferts compris, sans limite.',
                'prix_usd_cents' => 3000,
                'prix_cdf' => 69000,
                'paliers_taille' => null,
                'plafond_acces' => null,
                'periode_mois' => 1,
                'quotas' => ['membres' => null, 'comptes' => null],
                'fonctionnalites' => self::EGLISE_PREMIUM,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 33,
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
