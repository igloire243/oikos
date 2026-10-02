<!DOCTYPE html>
{{-- `echelle-reduite` : l'interface à 75 % au-delà de 1024 px, comme le produit (voir app.css). --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="echelle-reduite">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
        <meta name="theme-color" content="#ffffff">

        {{-- PWA : manifeste, icônes et couleur. Sans manifeste valide et sans service worker, le
             navigateur ne propose pas d'installer l'application. Le fond de l'icône iOS est plein :
             Safari noircit la transparence. --}}
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/favicon-32.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Oikos">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">

        {{-- L'ÉCRAN DE DÉMARRAGE de l'application installée : le logo sur fond blanc. Android le fabrique
             lui-même depuis le manifeste (nom, icône 512, couleur de fond) ; iOS exige une image par
             taille d'écran, sinon il montre un écran blanc le temps du chargement. --}}
        <link rel="apple-touch-startup-image" href="{{ asset('icons/demarrage/1290x2796.png') }}" media="(device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
        <link rel="apple-touch-startup-image" href="{{ asset('icons/demarrage/1179x2556.png') }}" media="(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
        <link rel="apple-touch-startup-image" href="{{ asset('icons/demarrage/1284x2778.png') }}" media="(device-width: 428px) and (device-height: 926px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
        <link rel="apple-touch-startup-image" href="{{ asset('icons/demarrage/1170x2532.png') }}" media="(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
        <link rel="apple-touch-startup-image" href="{{ asset('icons/demarrage/1125x2436.png') }}" media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
        <link rel="apple-touch-startup-image" href="{{ asset('icons/demarrage/1242x2688.png') }}" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
        <link rel="apple-touch-startup-image" href="{{ asset('icons/demarrage/828x1792.png') }}" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
        <link rel="apple-touch-startup-image" href="{{ asset('icons/demarrage/1242x2208.png') }}" media="(device-width: 414px) and (device-height: 736px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
        <link rel="apple-touch-startup-image" href="{{ asset('icons/demarrage/750x1334.png') }}" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
        <link rel="apple-touch-startup-image" href="{{ asset('icons/demarrage/1536x2048.png') }}" media="(device-width: 768px) and (device-height: 1024px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
        <link rel="apple-touch-startup-image" href="{{ asset('icons/demarrage/1668x2388.png') }}" media="(device-width: 834px) and (device-height: 1194px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
        <link rel="apple-touch-startup-image" href="{{ asset('icons/demarrage/2048x2732.png') }}" media="(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">

        {{-- Le même écran DANS la page, pour l'application installée seulement (`display-mode:
             standalone`) : entre l'image du système et le premier écran de Vue, il reste un blanc
             pendant le chargement du JavaScript. Retiré dès que l'application est montée. --}}
        <style>
            #demarrage { display: none; }
            @media (display-mode: standalone) {
                #demarrage { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: #ffffff; transition: opacity .25s ease; }
                #demarrage.fini { opacity: 0; pointer-events: none; }
                #demarrage img { width: 7rem; height: 7rem; border-radius: 1.6rem; box-shadow: 0 .4rem 1.4rem rgba(0,0,0,.15); }
            }
        </style>
        {{-- La console n'a rien à faire dans un moteur de recherche. --}}
        <meta name="robots" content="noindex, nofollow">

        <title inertia>{{ config('app.name', 'Oikos Console') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        {{-- La clé PUBLIQUE VAPID : de quoi s'abonner aux notifications sans passer par le serveur. --}}
        @if (config('webpush.cle_publique'))
            <meta name="vapid-cle-publique" content="{{ config('webpush.cle_publique') }}">
        @endif

        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        <div id="demarrage" aria-hidden="true"><img src="{{ asset('icons/icon-192.png') }}" alt=""></div>
        <script>setTimeout(function () { var d = document.getElementById('demarrage'); if (d) d.classList.add('fini'); }, 6000);</script>
        @inertia
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
            }
        </script>
    </body>
</html>
