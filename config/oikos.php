<?php

/*
| Les réglages de la console qui ne sont pas (encore) éditables à l'écran. Le Lot C4 déplacera en
| base ceux qu'un opérateur doit pouvoir changer sans déploiement ; ceux-ci restent les valeurs de
| départ.
*/
return [
    // La clé PRIVÉE qui signe les licences, en base64 sur une ligne (voir `oikos:cles-signature`).
    // Sans elle la console répond sans signer, et le produit — qui ne vérifie que s'il détient la
    // clé publique — continue de fonctionner : les deux moitiés s'allument séparément.
    'licence_cle_privee' => env('LICENCE_CLE_PRIVEE'),

    // Combien de temps une installation peut se taire avant de se déclarer périmée d'elle-même.
    // Généreux exprès : il doit absorber une coupure de fibre de trois semaines, pas punir une panne.
    'silence_jours' => (int) env('OIKOS_SILENCE_JOURS', 45),

    // L'essai accordé à une installation activée qui n'a encore rien acheté.
    'essai_jours' => (int) env('OIKOS_ESSAI_JOURS', 30),

    // Le délai après l'échéance pendant lequel l'écriture reste ouverte.
    'grace_jours' => (int) env('OIKOS_GRACE_JOURS', 14),

    // La durée de vie d'une clé d'activation non utilisée.
    'cle_validite_jours' => (int) env('OIKOS_CLE_VALIDITE_JOURS', 30),

    // Payer en ligne : éteint tant qu'un fournisseur réel n'est pas branché (PaiementsEnLigne).
    'paiement_en_ligne' => (bool) env('PAIEMENT_EN_LIGNE', false),
    'passerelle_paiement' => env('PASSERELLE_PAIEMENT', 'simulee'),
];
