<?php

/*
|--------------------------------------------------------------------------
| L'IDENTITÉ DU PRODUIT
|--------------------------------------------------------------------------
| À ne pas confondre avec `config('app.name')`, qui vaut « Oikos Console » et nomme CETTE
| application-ci — l'outil de l'éditeur. Ici, c'est le PRODUIT que vous vendez qui est nommé,
| celui dont le site public parle et que vos clients installent chez eux.
|
| Le même fichier existe, sous le même nom, dans l'installation livrée au client. Les deux doivent
| dire la même chose : c'est ce qui fait qu'un client reconnaît la même maison entre le site où il
| a lu les tarifs et le logiciel qu'il ouvre chaque matin.
|
| Ce que ce fichier ne contient PAS : le nom de la communauté cliente. Celui-là est lu en base,
| côté installation, et change d'un client à l'autre. Les mélanger aboutirait à afficher « Oikos »
| là où le fidèle attend le nom de son église.
*/

return [

    'nom' => 'Oikos',

    'nom_long' => 'Gestion ecclésiastique — de la vision à la cellule',

    'accroche' => "Un seul système pour la vision, ses antennes et ses églises : les membres, les départements, les plannings, l'appel nominal, les rapports et la trésorerie.",

    'editeur' => env('PRODUIT_EDITEUR', ''),

    'version' => '1.0',

];
