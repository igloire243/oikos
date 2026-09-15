<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * LES OFFRES DE DÉPART — trois natures, DEUX paliers, et le cas de l'église seule.
 *
 * LES PRIX SONT DES VALEURS DE TRAVAIL, ajustables ensuite dans la console sans toucher au code
 * (voir PlanController). Taux retenu pour l'équivalent en francs : 1 USD ≈ 2 300 CDF. Un taux qui
 * traîne fait perdre de l'argent en silence — à revoir avant la première vente.
 *
 * QUI PAIE QUOI
 * -------------
 *   LICENCE   payée par la VISION, à l'année, une fois pour toute la structure. Son prix suit la
 *             TAILLE du réseau — un SOCLE (`prix_usd_cents`) plus un montant PAR ENTITÉ
 *             (`prix_par_entite_usd_cents`) —, et son palier fixe le PLAFOND de ce que les entités
 *             peuvent souscrire (plafond_acces). Elle inclut l'espace de la vision.
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
 * `fonctionnalites = null` = TOUS LES MODULES, TOUS ESPACES CONFONDUS — et non « toute la famille
 * que cette nature ouvre », comme on le lit trop vite. `EtatLicence::modules()` abandonne le calcul
 * et rend `null` dès qu'UNE offre ouvrante vaut `null`. Mis sur une licence Vision, il ouvrirait
 * donc les écrans d'église et d'antenne sans qu'aucun accès soit vendu. ON ÉNUMÈRE PARTOUT ; seul
 * FONDATEUR garde `null`, où « tout offert » est précisément l'intention.
 */
class PlanSeeder extends Seeder
{
    // =========================================================================================
    // LES DEUX PALIERS, ET CE QUI LES SÉPARE
    //
    // Trois paliers ont été ramenés à deux (retour du client, 2026-09-12) : trois formules par
    // famille faisaient un argumentaire trop long à téléphone, pour une différence que le client
    // ne retenait pas. STARTER a disparu — son contenu était de toute façon ENTIÈREMENT inclus
    // dans STANDARD, la fusion n'a donc rien retiré à personne.
    //
    // LA LIGNE DE PARTAGE EST LA MÊME PARTOUT, et c'est ce qui la rend explicable en une phrase :
    //
    //     STANDARD = tenir une église au quotidien.
    //     PREMIUM  = piloter un réseau et l'analyser.
    //
    // Passe donc en PREMIUM tout ce qui ne sert PAS à la vie courante d'une assemblée :
    //   • les TRANSFERTS — ils n'ont de sens qu'entre plusieurs entités ;
    //   • les FINANCES consolidées — une église tient sa caisse (secteur.tresorerie reste en
    //     Standard), une antenne ou la vision CONSOLIDENT, ce qui est un autre métier ;
    //   • les MÉDIAS — la vitrine publique, qu'on soigne quand on a déjà le reste ;
    //   • les NOMENCLATURES et les DOSSIERS DE CADRES — de la gouvernance, pas de l'exploitation ;
    //   • l'ESPACE PERSONNEL DES MEMBRES — en Standard on GÈRE des fidèles, en Premium ils
    //     DEVIENNENT des utilisateurs. C'est un changement de nature, pas un écran de plus.
    // =========================================================================================

    // ---- ESPACE VISION (LICENCE + COMBINÉE) ------------------------------------------------

    private const VISION_STANDARD = [
        'superadmin.users', 'superadmin.entites', 'superadmin.programs', 'superadmin.reports',
        'superadmin.communications', 'superadmin.media',
    ];

    // ON ÉNUMÈRE, MÊME POUR « TOUT L'ESPACE DE LA VISION ». La tentation est de mettre `null` et
    // de laisser la console comprendre « toute la famille vision » — c'est ce que promet le
    // commentaire d'en-tête, mais ce n'est PAS ce que fait le code : `EtatLicence::modules()` rend
    // `null` dès qu'une offre vaut `null`, et `null` y signifie TOUS LES MODULES, TOUS ESPACES
    // CONFONDUS. Une licence Premium à `null` ouvrirait donc gratuitement les écrans d'église et
    // d'antenne, c'est-à-dire exactement ce que les accès sont censés facturer.
    //
    // Le seul plan qui garde `null` est FONDATEUR, où « tout offert » est l'intention.
    private const VISION_PREMIUM = [
        'superadmin.users', 'superadmin.entites', 'superadmin.transferts', 'superadmin.profils',
        'superadmin.programs', 'superadmin.finances', 'superadmin.reports',
        'superadmin.communications', 'superadmin.media',
    ];

    // ---- ESPACE ÉGLISE / CELLULE (ACCÈS) ----------------------------------------------------

    private const EGLISE_STANDARD = [
        'secteur.membres', 'secteur.cultes', 'secteur.programmes', 'secteur.activites',
        'secteur.prieres', 'secteur.visites', 'secteur.discipulariat', 'secteur.equipes',
        'secteur.discipline', 'secteur.tresorerie', 'secteur.rapports', 'secteur.messagerie',
        'department.equipe', 'department.plannings', 'department.rapports',
    ];

    private const EGLISE_PREMIUM = [
        'secteur.membres', 'secteur.cultes', 'secteur.programmes', 'secteur.activites',
        'secteur.prieres', 'secteur.visites', 'secteur.discipulariat', 'secteur.equipes',
        'secteur.discipline', 'secteur.tresorerie', 'secteur.rapports', 'secteur.messagerie',
        'department.equipe', 'department.plannings', 'department.rapports',
        // Ce que Premium ajoute :
        'secteur.transferts', 'secteur.medias',
        'department.programmes', 'department.ressources',

        // L'ESPACE PERSONNEL DES MEMBRES — le seul module que TOUTE L'ASSEMBLÉE voit. Les autres
        // ne se remarquent que du bureau ; celui-ci se remarque du banc. C'est ce qui en fait
        // l'argument de montée en gamme, et la raison de le réserver au Premium.
        'secteur.espace_membre',
    ];

    // ---- ESPACE ANTENNE (ACCÈS) -------------------------------------------------------------

    private const ANTENNE_STANDARD = [
        'antenne.extensions', 'antenne.bergers', 'antenne.membres', 'antenne.rapports',
        'antenne.services', 'antenne.communications', 'antenne.pastoral', 'antenne.visites',
        'antenne.reunions', 'antenne.departements',
    ];

    private const ANTENNE_PREMIUM = [
        'antenne.extensions', 'antenne.bergers', 'antenne.membres', 'antenne.rapports',
        'antenne.services', 'antenne.communications', 'antenne.pastoral', 'antenne.visites',
        'antenne.reunions', 'antenne.departements',
        // Ce que Premium ajoute :
        'antenne.finances', 'antenne.medias', 'antenne.transferts', 'antenne.dossiers',
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
                'code' => 'LICENCE_VISION_STANDARD',
                'nature' => Plan::LICENCE,
                'palier' => Plan::STANDARD,
                'niveau' => 'VISION',
                'nom' => 'Licence Vision — Standard',
                'argumentaire' => 'Met le système en service pour toute la structure et ouvre le siège : '
                    ."comptes et habilitations, organigramme du réseau, calendrier de la vision, bilans, "
                    ."communications et médias. Autorise un accès Standard pour les entités.",

                // SOCLE + MONTANT PAR ENTITÉ — « 250 $ par an, plus 4 $ par entité ».
                //
                // Les quatre tranches d'avant ne répondaient pas à la question « une entité vaut
                // combien ? », et leur réponse implicite était absurde : 320 $ par entité pour un
                // réseau d'une seule, 5,30 $ pour deux cents, et un saut de 180 $ pour la onzième.
                // Voir la migration tarif_par_entite_sur_les_plans.
                'prix_usd_cents' => 25000,
                'prix_cdf' => 575000,
                'prix_par_entite_usd_cents' => 400,
                'prix_par_entite_cdf' => 9200,
                'paliers_taille' => null,
                'plafond_acces' => Plan::STANDARD,
                'periode_mois' => 12,
                'quotas' => null,
                'fonctionnalites' => self::VISION_STANDARD,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 10,
            ],
            [
                'code' => 'LICENCE_VISION_PREMIUM',
                'nature' => Plan::LICENCE,
                'palier' => Plan::PREMIUM,
                'niveau' => 'VISION',
                'nom' => 'Licence Vision — Premium',
                'argumentaire' => 'Tout le siège : en plus du Standard, la trésorerie consolidée du réseau, '
                    ."les transferts sur tout le parc, les nomenclatures (profils spirituels, ministères, "
                    ."fonctions d'église) et l'organisation des événements. Autorise un accès Premium.",
                // « 380 $ par an, plus 6 $ par entité. »
                'prix_usd_cents' => 38000,
                'prix_cdf' => 874000,
                'prix_par_entite_usd_cents' => 600,
                'prix_par_entite_cdf' => 13800,
                'paliers_taille' => null,
                'plafond_acces' => Plan::PREMIUM,
                'periode_mois' => 12,
                'quotas' => null,
                'fonctionnalites' => self::VISION_PREMIUM,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 11,
            ],
        ];
    }

    private function accesEglise(): array
    {
        return [
            [
                'code' => 'ACCES_EGLISE_STANDARD',
                'nature' => Plan::ACCES,
                'palier' => Plan::STANDARD,
                'niveau' => 'EXTENSION',
                'nom' => 'Accès Église — Standard',
                // ON DIT CE QUI N'Y EST PAS, parce que c'est la question qu'on posera. « Les
                // membres sont gérés, ils ne se connectent pas » évite la déception après vente
                // beaucoup mieux qu'une liste de ce qui est inclus.
                'argumentaire' => "Tout ce qu'il faut pour tenir une église au quotidien : membres, "
                    .'cultes et présences, programmes, activités, prières, visites, discipulariat, '
                    .'équipes, discipline, trésorerie locale, rapports et messagerie. Les membres '
                    ."sont gérés par l'équipe ; ils n'ont pas de compte pour se connecter.",
                'prix_usd_cents' => 1000,
                'prix_cdf' => 23000,
                'paliers_taille' => null,

                // AUCUN QUOTA — ET C'EST DÉLIBÉRÉ. Cette offre portait « 20 comptes, 1 500
                // membres ». Deux chiffres que RIEN ne vérifiait : dans le produit,
                // `Licence::quotaAtteint()` n'est appelée que pour `antennes` et `extensions`.
                // Ils étaient de surcroît faux de portée — `EtatLicence::quotas()` retient le
                // quota le plus généreux et le renvoie pour TOUTE l'installation, si bien qu'un
                // réseau de neuf églises en Standard aurait eu 1 500 membres à se partager.
                //
                // Annoncer une limite qu'on ne tient pas est pire que ne rien annoncer : le jour
                // où on l'appliquerait, le client la découvrirait comme une régression.
                //
                // CONSÉQUENCE ASSUMÉE : la taille d'une entité ne change plus rien au prix de son
                // accès — une assemblée de 3 000 personnes paie comme une cellule de 80. La taille
                // ne se facture que sur la LICENCE (`paliers_taille`, au nombre d'entités). Voir
                // « Limites connues » dans ../CLAUDE.md.
                'quotas' => null,
                'fonctionnalites' => self::EGLISE_STANDARD,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 20,
            ],
            [
                'code' => 'ACCES_EGLISE_PREMIUM',
                'nature' => Plan::ACCES,
                'palier' => Plan::PREMIUM,
                'niveau' => 'EXTENSION',
                'nom' => 'Accès Église — Premium',
                'argumentaire' => 'Pour une église qui grandit et qui rayonne : en plus du Standard, '
                    .'chaque fidèle a son espace personnel et son compte, plus les transferts de '
                    .'membres entre entités, les médias et la page publique, et le pilotage complet '
                    .'des départements.',
                'prix_usd_cents' => 2000,
                'prix_cdf' => 46000,
                'paliers_taille' => null,
                'quotas' => null,
                'fonctionnalites' => self::EGLISE_PREMIUM,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 21,
            ],
        ];
    }

    private function accesAntenne(): array
    {
        return [
            [
                'code' => 'ACCES_ANTENNE_STANDARD',
                'nature' => Plan::ACCES,
                'palier' => Plan::STANDARD,
                'niveau' => 'ANTENNE',
                'nom' => 'Accès Antenne — Standard',
                'argumentaire' => "Animer une zone : les églises rattachées, l'affectation des bergers, "
                    ."l'annuaire régional, les cultes, le suivi pastoral, les visites d'extensions, "
                    .'les réunions mensuelles, les départements et les communications.',

                // Une antenne coûte plus qu'une église : elle en chapeaute plusieurs, et son accès
                // ouvre des écrans qui portent sur tout un territoire.
                'prix_usd_cents' => 2000,
                'prix_cdf' => 46000,
                'paliers_taille' => null,
                'quotas' => null,
                'fonctionnalites' => self::ANTENNE_STANDARD,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 30,
            ],
            [
                'code' => 'ACCES_ANTENNE_PREMIUM',
                'nature' => Plan::ACCES,
                'palier' => Plan::PREMIUM,
                'niveau' => 'ANTENNE',
                'nom' => 'Accès Antenne — Premium',
                'argumentaire' => 'Piloter une région : en plus du Standard, les finances consolidées de '
                    ."l'antenne, les médias, les transferts entre extensions et les dossiers "
                    .'disciplinaires des cadres.',
                'prix_usd_cents' => 3000,
                'prix_cdf' => 69000,
                'paliers_taille' => null,
                'quotas' => null,
                'fonctionnalites' => self::ANTENNE_PREMIUM,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 31,
            ],
        ];
    }

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

                // ÉGLISE SEULE = LE PLUS PETIT RÉSEAU QU'ON PUISSE VENDRE, pas une offre au rabais :
                // elle a l'espace de la vision pour elle toute seule. Son prix se cale sur le premier
                // échelon de la licence réseau (320 $ jusqu'à dix entités) ramené à une entité — pas
                // plus bas, sinon un réseau de trois églises aurait intérêt à ouvrir trois
                // installations « église seule » plutôt qu'une licence.
                'prix_usd_cents' => 15000,
                'prix_cdf' => 345000,
                'paliers_taille' => null,
                'plafond_acces' => Plan::STANDARD,
                'periode_mois' => 12,
                'quotas' => ['antennes' => 0, 'extensions' => 1],
                'fonctionnalites' => self::VISION_STANDARD,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 40,
            ],
            [
                'code' => 'COMBINEE_PREMIUM',
                'nature' => Plan::COMBINEE,
                'palier' => Plan::PREMIUM,
                'niveau' => 'VISION',
                'nom' => 'Église seule — Licence réduite (Premium)',
                'argumentaire' => "Même chose, tous les modules de l'espace vision compris. Autorise un "
                    .'accès église seule Premium.',

                // ÉCARTÉ DU STANDARD À DESSEIN. Les deux étaient au même tarif : à contenu supérieur
                // et prix identique, personne n'aurait jamais pris le Standard, qui n'aurait servi
                // qu'à allonger la page. Un écart, même modeste, rend le choix réel.
                'prix_usd_cents' => 19000,
                'prix_cdf' => 437000,
                'paliers_taille' => null,
                'plafond_acces' => Plan::PREMIUM,
                'periode_mois' => 12,
                'quotas' => ['antennes' => 0, 'extensions' => 1],
                'fonctionnalites' => self::VISION_PREMIUM,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 41,
            ],
            [
                'code' => 'ACCES_SEULE_STANDARD',
                'nature' => Plan::ACCES,
                'palier' => Plan::STANDARD,
                'niveau' => 'EXTENSION',
                'nom' => 'Accès Église seule — Standard',
                'argumentaire' => "L'espace de l'église et du département pour une assemblée autonome : "
                    .'membres, cultes, trésorerie, programmes, pastoral, discipulariat, rapports.',

                // Trois fois l'accès église en réseau (10 $) : en réseau, l'accès ne paie QUE
                // l'espace de l'église, la licence de la vision portant tout le reste. Ici il n'y a
                // aucune vision pour amortir — l'assemblée supporte seule l'installation.
                'prix_usd_cents' => 3000,
                'prix_cdf' => 69000,
                'paliers_taille' => null,
                'plafond_acces' => null,
                'periode_mois' => 1,
                'quotas' => null,
                'fonctionnalites' => self::EGLISE_STANDARD,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 42,
            ],
            [
                'code' => 'ACCES_SEULE_PREMIUM',
                'nature' => Plan::ACCES,
                'palier' => Plan::PREMIUM,
                'niveau' => 'EXTENSION',
                'nom' => 'Accès Église seule — Premium',
                'argumentaire' => "Tout l'espace de l'église et du département, transferts compris.",
                'prix_usd_cents' => 4000,
                'prix_cdf' => 92000,
                'paliers_taille' => null,
                'plafond_acces' => null,
                'periode_mois' => 1,
                'quotas' => null,
                'fonctionnalites' => self::EGLISE_PREMIUM,
                'modes_paiement' => null,
                'is_public' => true,
                'ordre' => 43,
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
