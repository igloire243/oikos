<?php

/**
 * LES SECRETS DE LA CONSOLE — ce qui ne doit jamais quitter ce serveur.
 *
 * Une seule valeur ici est un secret, mais c'en est un vrai : la clé privée de signature des
 * licences. Qui la détient peut fabriquer un abonnement valide pour n'importe quelle installation
 * du parc. Elle vit dans le .env, hors du dépôt, et n'est jamais journalisée ni affichée.
 *
 * Si elle est perdue : rien de grave, mais il faut agir. On en fabrique une neuve, on remplace la
 * clé publique dans le produit, et on livre une mise à jour. Les licences déjà posées cessent
 * d'être vérifiables — d'où l'intérêt de la sauvegarder au même endroit que vos autres secrets.
 *
 * Si elle est VOLÉE : il faut la remplacer, et vite. C'est la seule valeur de cette application
 * dont la fuite se paie en abonnements non facturés.
 */
return [
    /** Clé privée Ed25519, en base64. Fabriquée par `php artisan oikos:cles-signature`. */
    'licence_cle_privee' => env('LICENCE_CLE_PRIVEE'),

    /**
     * COMBIEN DE JOURS DE SILENCE UNE INSTALLATION PEUT SUPPORTER.
     *
     * Une licence signée reste valide jusqu'à sa date de fin — y compris si l'installation ne parle
     * plus à la console. Débrancher le réseau deviendrait donc un moyen de figer un abonnement
     * jusqu'à son échéance, puis de rejouer le fichier indéfiniment.
     *
     * Passé ce délai sans échange réussi, le produit se considère périmé quoi que dise la date.
     *
     * QUARANTE-CINQ JOURS, ET PAS QUINZE. Ce compteur doit tolérer ce que la vie d'un serveur en
     * RDC lui inflige : une coupure de fibre de trois semaines, un hébergeur en panne, un client
     * parti au village avec le seul modem. Fermer une église pour une panne de réseau serait pire
     * que le fraudeur qu'on cherche à décourager — et c'est le genre d'erreur qui se raconte.
     */
    'silence_jours' => (int) env('LICENCE_SILENCE_JOURS', 45),
];
