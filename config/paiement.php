<?php

/*
|--------------------------------------------------------------------------
| MODES D'ENCAISSEMENT
|--------------------------------------------------------------------------
| Vous aviez posé la règle il y a longtemps : « garder tous les modes de paiement activables ».
| Ce fichier en est l'application. Chaque mode s'allume ou s'éteint séparément, et porte les
| coordonnées qu'on montrera au client.
|
| TROIS NIVEAUX DE DÉCISION, QU'IL NE FAUT PAS CONFONDRE
| ------------------------------------------------------
|   1. App\Models\Paiement::FOURNISSEURS  — la liste de ce qui EXISTE. Elle ne bouge que si un
|      nouvel opérateur apparaît en RDC.
|   2. Ce fichier                          — ce que VOUS acceptez aujourd'hui, et sous quel numéro.
|   3. Plan::$modes_paiement               — ce qu'une OFFRE PARTICULIÈRE accepte. null = tous ceux
|      d'ici ; une liste = une offre négociée, plus restrictive.
|
| Un mode doit passer les trois pour être proposé. App\Support\ModesPaiement fait ce croisement —
| ne le refaites pas à la main dans une vue, c'est ainsi qu'on finit par afficher un numéro
| désactivé.
|
| LES NUMÉROS NE SONT PAS ÉCRITS ICI, MAIS DANS LE .env
| ------------------------------------------------------
| Un numéro de téléphone change ; le code, non. Et surtout : ce fichier part sur Git, le .env
| n'en part pas. Un mode sans numéro renseigné est automatiquement retiré de l'affichage plutôt
| que montré vide — un client à qui l'on présente « M-Pesa : (rien) » appelle pour demander, et
| c'est un client qui ne paie pas ce jour-là.
*/

return [

    // Ce qu'on affiche en grand sur la grille tarifaire. Le second prix reste visible à côté.
    'devise_affichee' => env('PAIEMENT_DEVISE_AFFICHEE', 'USD'),

    // Taux indicatif servant à afficher l'équivalent en francs quand un plan ne le porte pas.
    // Indicatif, jamais utilisé pour calculer une facture : une facture se libelle dans UNE devise.
    'taux_indicatif_cdf' => (int) env('PAIEMENT_TAUX_CDF', 2300),

    // Ce qu'on promet au client sur la page « Comment payer ». À tenir : c'est sur cette phrase
    // qu'il jugera le service le jour où son accès est bloqué.
    'delai_reouverture' => env('PAIEMENT_DELAI_REOUVERTURE', '24 heures ouvrables'),

    'titulaire' => env('PAIEMENT_TITULAIRE', ''),

    'modes' => [

        'MPESA' => [
            'actif' => (bool) env('PAIEMENT_MPESA', true),
            'numero' => env('PAIEMENT_MPESA_NUMERO'),
            'icone' => 'smartphone',
            'instructions' => 'Depuis le menu M-Pesa, choisissez « Envoyer de l\'argent », puis saisissez le numéro ci-dessus.',
        ],

        'ORANGE_MONEY' => [
            'actif' => (bool) env('PAIEMENT_ORANGE_MONEY', true),
            'numero' => env('PAIEMENT_ORANGE_MONEY_NUMERO'),
            'icone' => 'smartphone',
            'instructions' => 'Composez #144# puis suivez « Transfert d\'argent ».',
        ],

        'AIRTEL_MONEY' => [
            'actif' => (bool) env('PAIEMENT_AIRTEL_MONEY', true),
            'numero' => env('PAIEMENT_AIRTEL_MONEY_NUMERO'),
            'icone' => 'smartphone',
            'instructions' => 'Composez *501# puis suivez « Envoyer de l\'argent ».',
        ],

        'VIREMENT' => [
            'actif' => (bool) env('PAIEMENT_VIREMENT', true),
            'numero' => env('PAIEMENT_VIREMENT_COMPTE'),
            'banque' => env('PAIEMENT_VIREMENT_BANQUE'),
            'icone' => 'landmark',
            'instructions' => 'Portez le numéro de facture en communication : sans lui, un virement reçu ne peut être rattaché à personne.',
        ],

        'ESPECES' => [
            'actif' => (bool) env('PAIEMENT_ESPECES', true),
            'numero' => null,
            'icone' => 'banknote',
            'instructions' => 'Sur rendez-vous. Un reçu numéroté vous est remis sur place, et la facture est soldée le jour même.',
        ],

        'DEPOT_MARCHAND' => [
            'actif' => (bool) env('PAIEMENT_DEPOT_MARCHAND', false),
            'numero' => env('PAIEMENT_DEPOT_MARCHAND_CODE'),
            'icone' => 'store',
            'instructions' => 'Dépôt auprès d\'un agent agréé, en citant le code marchand ci-dessus.',
        ],

    ],
];
