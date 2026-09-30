<!DOCTYPE html>
{{-- `echelle-reduite` : l'interface à 75 % au-delà de 1024 px, comme le produit (voir app.css). --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="echelle-reduite">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
        <meta name="theme-color" content="#ffffff">
        {{-- La console n'a rien à faire dans un moteur de recherche. --}}
        <meta name="robots" content="noindex, nofollow">

        <title inertia>{{ config('app.name', 'Oikos Console') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
