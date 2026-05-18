<!DOCTYPE html>
<html lang="fr">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'GDA — Gestion de Chantier')</title>
    @php
        $gdaAssetVer = (string) max(
            @filemtime(public_path('css/gda.css')) ?: 0,
            @filemtime(public_path('js/gda-app.js')) ?: 0,
            @filemtime(public_path('js/gda-i18n.js')) ?: 0,
        ) ?: time();
    @endphp
    <link rel="icon" type="image/jpeg" href="{{ asset('img/Constfondblanc.jpg') }}?v={{ $gdaAssetVer }}">
    <link rel="apple-touch-icon" href="{{ asset('img/Constfondblanc.jpg') }}?v={{ $gdaAssetVer }}">
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@300;400;500;600;700;800&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/gda.css') }}?v={{ $gdaAssetVer }}">
    @stack('head')
</head>
<body>
    @yield('content')
    @stack('scripts')
</body>
</html>
