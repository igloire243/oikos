<?php

// LES CLÉS VAPID — signent chaque envoi pour que le service qui pousse (FCM, Mozilla…) sache
// quelle installation les demande, sans jamais transporter de secret vers le navigateur : seule
// la clé PUBLIQUE lui est donnée, au moment de l'abonnement. `php artisan push:cles-vapid` les
// génère une fois.
return [
    'cle_publique' => env('VAPID_CLE_PUBLIQUE'),
    'cle_privee' => env('VAPID_CLE_PRIVEE'),
    'sujet' => env('VAPID_SUJET', env('APP_URL', 'http://localhost')),
];
