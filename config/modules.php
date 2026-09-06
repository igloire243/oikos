<?php

/*
|--------------------------------------------------------------------------
| LE CATALOGUE DES MODULES — LE MIROIR EXACT DE CELUI DU PRODUIT
|--------------------------------------------------------------------------
| Ce fichier doit dire la MÊME chose que app/Support/Modules.php dans Génération Joël. Les clés
| sont identiques, à la lettre : ce sont elles qui voyagent dans la licence, et une clé qui diffère
| d'un caractère entre les deux côtés produit un module vendu et jamais ouvert.
|
| LA CLÉ PORTE SON ESPACE, ET CE N'EST PAS DÉCORATIF
| ----------------------------------------------------
| `rapports` désigne trois pages différentes selon qu'on est dans le secteur, l'antenne ou le
| département. Sans le préfixe d'espace, vendre « rapports » ouvrirait les trois — ou aucune,
| selon la façon dont le code trancherait. D'où `secteur.rapports`, `antenne.rapports`,
| `department.rapports`.
|
| CE QUI EST INVENDABLE, ET POURQUOI
| ------------------------------------
| Les paramètres d'une entité, sa gestion des comptes, le tableau de bord d'une commission :
| `vendable => false`. Les fermer empêcherait le client de réparer sa propre installation —
| y compris pour venir payer. Ils apparaissent dans le catalogue pour que l'inventaire soit
| complet et vérifiable, mais aucune offre ne peut les retirer.
|
| LES SIX ESPACES ONT ÉTÉ RELEVÉS DANS LES ROUTES DU PRODUIT, groupe par groupe. « extension »
| n'est pas un espace séparé — c'est un autre nom de l'espace secteur, les routes sont les mêmes.
| Et « commission » n'a qu'un tableau de bord : il n'y a rien à y découper aujourd'hui.
*/

return [

    /*
    |----------------------------------------------------------------------
    | L'ESPACE DE LA VISION — ce qu'une LICENCE ouvre
    |----------------------------------------------------------------------
    */
    'superadmin' => [
        'libelle' => 'Espace de la Vision',
        'modules' => [
            'superadmin.users' => [
                'nom' => 'Comptes et utilisateurs',
                'icone' => 'users',
                'vendable' => true,
                'texte' => 'Créer, modifier et suspendre les comptes de toute la structure, avec la matrice des rôles et des permissions.',
            ],
            'superadmin.entites' => [
                'nom' => 'Implantations, réseau et commissions',
                'icone' => 'network',
                'vendable' => true,
                'texte' => "Les antennes, les églises, les départements et les commissions — l'organigramme réel de la communauté.",
            ],
            'superadmin.programs' => [
                'nom' => 'Programmes et cultes de la vision',
                'icone' => 'calendar-days',
                'vendable' => true,
                'texte' => 'Le calendrier de la vision et les feuilles de présence, consolidés sur tout le réseau.',
            ],
            'superadmin.finances' => [
                'nom' => 'Finances de la vision',
                'icone' => 'wallet',
                'vendable' => true,
                'texte' => 'Cotisations, dépenses et rapports financiers au niveau de la communauté entière.',
            ],
            'superadmin.reports' => [
                'nom' => 'Rapports et statistiques',
                'icone' => 'chart-column',
                'vendable' => true,
                'texte' => "Bilans d'activités, courbes de croissance et exports — calculés sur ce qui a été réellement pointé.",
            ],
            'superadmin.communications' => [
                'nom' => 'Communications',
                'icone' => 'megaphone',
                'vendable' => true,
                'texte' => 'Annonces et diffusion vers les responsables, sans passer par des groupes que personne ne retrouve.',
            ],
            'superadmin.media' => [
                'nom' => 'Médias de la vision',
                'icone' => 'video',
                'vendable' => true,
                'texte' => 'Photos, albums, flyers, vidéos, audios et directs, publiés depuis le même espace.',
            ],
            'superadmin.settings' => [
                'nom' => 'Paramètres et sécurité',
                'icone' => 'shield-check',
                'vendable' => false,
                'texte' => 'Configuration générale, site public, journaux et maintenance. Toujours ouvert.',
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | L'ESPACE D'UNE ANTENNE — la coordination régionale
    |----------------------------------------------------------------------
    */
    'antenne' => [
        'libelle' => "Espace d'une antenne",
        'modules' => [
            'antenne.extensions' => [
                'nom' => "Églises rattachées",
                'icone' => 'church',
                'vendable' => true,
                'texte' => "Les églises rattachées à l'antenne, leurs responsables et leur rattachement.",
            ],
            'antenne.bergers' => [
                'nom' => 'Affectation des bergers',
                'icone' => 'user-check',
                'vendable' => true,
                'texte' => "Qui dirige quelle église : nommer un berger, le retirer, suivre les postes vacants.",
            ],
            'antenne.membres' => [
                'nom' => "Annuaire régional des membres",
                'icone' => 'contact',
                'vendable' => true,
                'texte' => "La consolidation des membres de toutes les églises de l'antenne, en consultation — matricule, profil, mouvements.",
            ],
            'antenne.transferts' => [
                'nom' => 'Transferts de membres',
                'icone' => 'arrow-left-right',
                'vendable' => true,
                'texte' => "Les membres qui changent d'église : demande, validation par l'antenne d'accueil, historique.",
            ],
            'antenne.pastoral' => [
                'nom' => 'Requêtes de prière',
                'icone' => 'hand-heart',
                'vendable' => true,
                'texte' => "Les demandes de prière remontées des églises de la région et leur suivi.",
            ],
            'antenne.visites' => [
                'nom' => "Visites d'extensions",
                'icone' => 'route',
                'vendable' => true,
                'texte' => "L'antenne envoie une délégation visiter une église : rapport structuré, procès-verbal, PDF.",
            ],
            'antenne.services' => [
                'nom' => 'Services et cultes',
                'icone' => 'calendar-days',
                'vendable' => true,
                'texte' => "Le calendrier des célébrations de l'antenne et les affectations de service.",
            ],
            'antenne.departements' => [
                'nom' => 'Départements',
                'icone' => 'users-round',
                'vendable' => true,
                'texte' => "Les départements suivis au niveau de l'antenne, sur le catalogue commun de la vision.",
            ],
            'antenne.finances' => [
                'nom' => "Finances de l'antenne",
                'icone' => 'wallet',
                'vendable' => true,
                'texte' => 'Offrandes, dépenses et remontées financières des églises de la région.',
            ],
            'antenne.rapports' => [
                'nom' => "Rapports d'activités",
                'icone' => 'chart-column',
                'vendable' => true,
                'texte' => "Les bilans consolidés des églises de l'antenne, sans repasser par le siège.",
            ],
            'antenne.reunions' => [
                'nom' => 'Réunions mensuelles',
                'icone' => 'users',
                'vendable' => true,
                'texte' => "La réunion de fin de mois : ordre du jour, synthèse des activités figée, procès-verbal, PDF.",
            ],
            'antenne.dossiers' => [
                'nom' => 'Dossiers disciplinaires des cadres',
                'icone' => 'gavel',
                'vendable' => true,
                'texte' => "Les dossiers instruits par l'antenne sur un berger, une église ou un membre de commission, avec historique.",
            ],
            'antenne.communications' => [
                'nom' => 'Communications',
                'icone' => 'megaphone',
                'vendable' => true,
                'texte' => "Annonces et diffusion vers les responsables d'églises de la région.",
            ],
            'antenne.parametres' => [
                'nom' => "Paramètres de l'antenne",
                'icone' => 'settings',
                'vendable' => false,
                'texte' => "Profil et réglages de l'antenne. Toujours ouvert.",
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | L'ESPACE D'UNE ÉGLISE OU D'UNE CELLULE — ce qu'un ACCÈS ouvre
    |----------------------------------------------------------------------
    */
    'secteur' => [
        'libelle' => "Espace d'une église ou d'une cellule",
        'modules' => [
            'secteur.membres' => [
                'nom' => 'Annuaire des membres',
                'icone' => 'users',
                'vendable' => true,
                'texte' => "Le fichier des fidèles : coordonnées, parcours, appartenance aux départements. La base sur laquelle tout le reste s'appuie.",
            ],
            'secteur.transferts' => [
                'nom' => 'Transferts de membres',
                'icone' => 'arrow-left-right',
                'vendable' => true,
                'texte' => "Un membre qui déménage : demande de transfert vers l'église la plus proche, validation, et déclaration à l'enregistrement d'un nouveau venu.",
            ],
            'secteur.equipes' => [
                'nom' => 'Équipes et départements',
                'icone' => 'users-round',
                'vendable' => true,
                'texte' => 'Les départements, leurs responsables et leurs membres — définis une seule fois pour toute la communauté, et non recopiés dans chaque église.',
            ],
            'secteur.cultes' => [
                'nom' => 'Cultes et présences',
                'icone' => 'church',
                'vendable' => true,
                'texte' => 'Le calendrier des célébrations, les affectations de service, et la fréquentation relevée dimanche après dimanche.',
            ],
            'secteur.programmes' => [
                'nom' => 'Programmes et calendriers',
                'icone' => 'calendar-days',
                'vendable' => true,
                'texte' => "Qui sert, où, et quand. Chaque département voit son planning, et l'appel nominal se fait depuis le téléphone, sur place.",
            ],
            'secteur.activites' => [
                'nom' => 'Calendrier annuel des activités',
                'icone' => 'calendar-range',
                'vendable' => true,
                'texte' => 'Séminaires, retraites, campagnes : leurs séances, leurs inscrits, leur présence effective et leur bilan.',
            ],
            'secteur.visites' => [
                'nom' => 'Visites et suivi pastoral',
                'icone' => 'handshake',
                'vendable' => true,
                'texte' => "Qui a été visité, par qui, quand, et ce qui en est ressorti. De quoi voir qui n'a été vu par personne depuis trois mois.",
            ],
            'secteur.prieres' => [
                'nom' => 'Requêtes de prière',
                'icone' => 'hand-heart',
                'vendable' => true,
                'texte' => "Les demandes reçues, leur suivi, et les réponses constatées — pour que « on priera pour vous » se vérifie.",
            ],
            'secteur.messagerie' => [
                'nom' => 'Messagerie interne',
                'icone' => 'messages-square',
                'vendable' => true,
                'texte' => 'Les annonces et les échanges restent dans le système, rattachés au département concerné.',
            ],
            'secteur.rapports' => [
                'nom' => 'Rapports du secteur',
                'icone' => 'chart-column',
                'vendable' => true,
                'texte' => "Les bilans d'activité et de présence, calculés à partir de ce qui a été pointé — pas ressaisis à la fin du mois.",
            ],
            'secteur.discipulariat' => [
                'nom' => 'Discipulariat et baptêmes',
                'icone' => 'graduation-cap',
                'vendable' => true,
                'texte' => "Le parcours d'affermissement, étape par étape, avec ce qui a été suivi et ce qui reste à faire pour chacun.",
            ],
            'secteur.discipline' => [
                'nom' => 'Discipline des ouvriers',
                'icone' => 'scale',
                'vendable' => true,
                'texte' => 'Les mesures prises, leur durée et leur levée, consignées avec la discrétion que ce sujet exige.',
            ],
            'secteur.tresorerie' => [
                'nom' => 'Trésorerie locale',
                'icone' => 'banknote',
                'vendable' => true,
                'texte' => "Offrandes, dîmes et dépenses, par période et par poste, avec de quoi rendre des comptes sans repartir d'un cahier.",
            ],
            'secteur.medias' => [
                'nom' => "Médias de l'église",
                'icone' => 'video',
                'vendable' => true,
                'texte' => 'Prédications, photos et documents, publiés sur le site de la communauté.',
            ],
            'secteur.comptes' => [
                'nom' => 'Gestion des comptes',
                'icone' => 'user-cog',
                'vendable' => false,
                'texte' => "Réservé au berger, propriétaire de l'église. Jamais délégué, jamais vendu.",
            ],
            'secteur.parametres' => [
                'nom' => "Paramètres de l'église",
                'icone' => 'settings',
                'vendable' => false,
                'texte' => "Profil et réglages de l'église. Toujours ouvert.",
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | L'ESPACE D'UN DÉPARTEMENT — le responsable au quotidien
    |----------------------------------------------------------------------
    */
    'department' => [
        'libelle' => "Espace d'un département",
        'modules' => [
            'department.equipe' => [
                'nom' => "Membres de l'équipe",
                'icone' => 'users',
                'vendable' => true,
                'texte' => "Les serviteurs du département, leur approbation et leur retrait — la même table que « Équipes » côté berger.",
            ],
            'department.plannings' => [
                'nom' => 'Plannings et appel nominal',
                'icone' => 'clipboard-check',
                'vendable' => true,
                'texte' => "L'écran du dimanche : qui sert, et l'appel fait sur place depuis un téléphone. Une fois enregistré, il se ferme.",
            ],
            'department.programmes' => [
                'nom' => 'Programmes du département',
                'icone' => 'calendar-days',
                'vendable' => true,
                'texte' => 'Les répétitions, les réunions et les séances propres au département.',
            ],
            'department.rapports' => [
                'nom' => 'Rapports du département',
                'icone' => 'chart-column',
                'vendable' => true,
                'texte' => "Le bilan mensuel du département, calculé sur les appels réellement faits.",
            ],
            'department.ressources' => [
                'nom' => 'Ressources',
                'icone' => 'folder-open',
                'vendable' => true,
                'texte' => 'Partitions, documents et supports mis à disposition de son équipe.',
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | L'ESPACE D'UNE COMMISSION — un seul écran aujourd'hui
    |----------------------------------------------------------------------
    */
    'commission' => [
        'libelle' => "Espace d'une commission",
        'modules' => [
            'commission.tableau' => [
                'nom' => 'Tableau de bord de la commission',
                'icone' => 'layout-dashboard',
                'vendable' => false,
                'texte' => "Un seul écran : il n'y a rien à découper tant que cet espace n'a pas de sections distinctes.",
            ],
        ],
    ],

];
