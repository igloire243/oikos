<?php

/*
|--------------------------------------------------------------------------
| AGRÉGATEUR MOBILE MONEY — FlexPay
|--------------------------------------------------------------------------
| L'encaissement automatique : le client valide sur son téléphone, FlexPay nous rappelle, la
| facture se solde toute seule. Tant qu'aucun compte marchand n'est renseigné (FLEXPAY_ACTIF=false),
| toute la couture reste inerte et l'encaissement se fait à la main comme avant — voir
| App\Support\Paiement\PasserelleInactive.
|
| LES SECRETS VIVENT DANS LE .env, PAS ICI. Ce fichier part sur Git, le .env non. Un jeton d'API
| commité est un jeton à révoquer.
|
| DEUX GARDE-FOUS SUR LE WEBHOOK, ET ILS SE CUMULENT :
|   1. la signature partagée (FLEXPAY_SECRET_WEBHOOK) : rejette un appel qui ne vient pas de FlexPay ;
|   2. la re-vérification serveur à serveur : même signé, on ne crédite jamais sur la foi du seul
|      corps du callback — on redemande l'état de la transaction à FlexPay avant d'encaisser.
| Le webhook d'un agrégateur est rejoué tant qu'il n'a pas reçu un 200 : l'idempotence
| (index unique fournisseur+référence) fait le reste.
*/

return [

    // Rien ne part vers FlexPay tant que ceci est faux. Le bouton « Payer maintenant » n'apparaît
    // même pas.
    'actif' => (bool) env('FLEXPAY_ACTIF', false),

    // Racine de l'API REST. La valeur par défaut vise la production FlexPay ; le bac à sable a sa
    // propre URL, à mettre dans le .env de préproduction.
    'base_url' => rtrim((string) env('FLEXPAY_BASE_URL', 'https://backend.flexpay.cd/api/rest/v1'), '/'),

    // Le code marchand et le jeton Bearer fournis à l'ouverture du compte FlexPay.
    'marchand' => (string) env('FLEXPAY_MARCHAND', ''),
    'jeton' => (string) env('FLEXPAY_JETON', ''),

    // Secret partagé servant à vérifier la signature du callback (en-tête X-Flexpay-Signature,
    // HMAC-SHA256 du corps brut). Vide = on saute cette vérification et on s'appuie uniquement sur
    // la re-vérification serveur à serveur ci-dessous — moins strict, mais jamais aveugle.
    'secret_webhook' => (string) env('FLEXPAY_SECRET_WEBHOOK', ''),

    // Devise dans laquelle FlexPay encaisse. Une facture se libelle dans UNE devise : si elle ne
    // correspond pas, on refuse de démarrer plutôt que de convertir en douce.
    'devise' => strtoupper((string) env('FLEXPAY_DEVISE', 'USD')),

    // URL publique que FlexPay rappellera. Laisser vide pour laisser le code la déduire de la route
    // nommée 'webhooks.flexpay' — utile en local où l'hôte change (tunnel ngrok, etc.).
    'callback_url' => env('FLEXPAY_CALLBACK_URL'),

    // Au-delà de ce délai sans nouvelle de FlexPay, une demande « Payer maintenant » est considérée
    // comme expirée et l'opérateur peut en relancer une.
    'delai_expiration_minutes' => (int) env('FLEXPAY_EXPIRATION_MIN', 15),

    // Délai d'attente réseau des appels sortants vers FlexPay (secondes). Court : l'API ne fait que
    // recevoir la demande, le résultat arrive par le webhook.
    'timeout' => (int) env('FLEXPAY_TIMEOUT', 15),
];
