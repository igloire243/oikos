/**
 * LA PALETTE DES GRAPHIQUES, ET LES QUELQUES OUTILS QUE TOUS PARTAGENT.
 *
 * ============================================================================================
 * DEUX FAMILLES DE COULEURS, ET IL NE FAUT PAS LES MÉLANGER
 * ============================================================================================
 *   1. UNE SEULE SÉRIE — « les présences du dimanche », « l'effectif » : la couleur de l'ESPACE
 *      (`var(--marque-*)`). Le graphique appartient alors à l'écran qui le montre, et se teinte
 *      tout seul du bleu de la Vision, de l'ambre du Berger, etc.
 *
 *   2. DES CATÉGORIES — « hommes / femmes / garçons / filles », « les dix profils spirituels » :
 *      une palette FIXE, identique dans les cinq espaces. Si « Hommes » était bleu dans la Vision
 *      et orange chez le Berger, quelqu'un qui passe de l'un à l'autre lirait le graphique de
 *      travers sans jamais s'en rendre compte. C'est le même raisonnement que les tons
 *      sémantiques du `Badge`.
 *
 * ============================================================================================
 * POURQUOI DES COMPOSANTS MAISON ET PAS UNE BIBLIOTHÈQUE
 * ============================================================================================
 *   · la couleur d'espace est une VARIABLE CSS : un SVG en hérite sans une ligne de JavaScript,
 *     là où une bibliothèque demande de la réinjecter à chaque changement d'espace ;
 *   · les rapports d'activités s'impriment. Un SVG s'imprime net à n'importe quelle taille ;
 *     un `<canvas>` s'imprime en bitmap, flou ;
 *   · le produit tourne souvent en réseau local, parfois sur des connexions lentes. Ce qu'on
 *     n'embarque pas ne coûte rien à télécharger.
 *
 * En échange : pas de zoom, pas d'export natif, et chaque nouveau type de graphique est à écrire.
 * C'est un choix assumé — les quatre familles ci-dessous couvrent ce que le produit montre.
 */

/**
 * Huit teintes distinguables, y compris pour les daltonismes les plus courants (deutéranopie et
 * protanopie) : on alterne les tons chauds et froids plutôt que de parcourir un dégradé, qui
 * donnerait deux catégories voisines quasi identiques.
 */
export const PALETTE = [
    '#2563eb', // bleu
    '#f59e0b', // ambre
    '#059669', // émeraude
    '#db2777', // rose
    '#7c3aed', // violet
    '#0891b2', // cyan
    '#ea580c', // orange
    '#65a30d', // vert olive
];

/** La couleur d'une catégorie, par son rang. Au-delà de huit, on recommence — c'est voulu :
 *  passé huit parts, un graphique de composition n'est déjà plus lisible, et la vraie réponse
 *  est de regrouper les petites parts. */
export function couleurCategorie(rang) {
    return PALETTE[rang % PALETTE.length];
}

/** Les nombres se lisent à la française : 1 234, pas 1,234. */
export function nombre(valeur) {
    return Number(valeur ?? 0).toLocaleString('fr-FR');
}

/** Un pourcentage entier, sans décimale trompeuse sur de petits effectifs. */
export function pourcentage(part, total) {
    if (!total) {
        return 0;
    }

    return Math.round((part / total) * 100);
}

/**
 * Le plafond de l'axe vertical.
 *
 * On arrondit au « joli » nombre supérieur (10, 25, 50, 100, 250…) plutôt que de coller au
 * maximum réel : une barre qui touche le haut du cadre se lit comme tronquée, et les graduations
 * tombent sur des nombres qu'on ne retient pas.
 */
export function plafond(valeurs) {
    const max = Math.max(0, ...valeurs.map((v) => Number(v) || 0));

    if (max === 0) {
        return 1;
    }

    const puissance = 10 ** Math.floor(Math.log10(max));

    for (const pas of [1, 1.25, 1.5, 2, 2.5, 3, 4, 5, 7.5, 10]) {
        if (max <= pas * puissance) {
            return pas * puissance;
        }
    }

    return 10 * puissance;
}

/** Les graduations horizontales d'un graphique : le zéro, le plafond, et ce qu'il y a entre. */
export function graduations(max, nombreDeLignes = 4) {
    return Array.from({ length: nombreDeLignes + 1 }, (_, i) => (max / nombreDeLignes) * i);
}
