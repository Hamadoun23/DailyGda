<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#c8521a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="GDA BuildOps">
    <title>@yield('title', 'GDA — Gestion de Chantier')</title>
    @php
        $gdaAssetVer = (string) max(
            @filemtime(public_path('css/gda.css')) ?: 0,
            @filemtime(public_path('js/gda-app.js')) ?: 0,
            @filemtime(public_path('js/gda-projects.js')) ?: 0,
            @filemtime(public_path('js/gda-i18n.js')) ?: 0,
            @filemtime(public_path('js/gda-pwa.js')) ?: 0,
            @filemtime(public_path('img/inavbar.png')) ?: 0,
        ) ?: time();
        $gdaHeaderBg = asset('img/inavbar.png').'?v='.$gdaAssetVer;
    @endphp
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <link rel="icon" type="image/jpeg" href="{{ asset('img/Constfondblanc.jpg') }}?v={{ $gdaAssetVer }}">
    <link rel="apple-touch-icon" href="{{ asset('img/Constfondblanc.jpg') }}?v={{ $gdaAssetVer }}">
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@300;400;500;600;700;800&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/gda.css') }}?v={{ $gdaAssetVer }}">
    <style>:root { --gda-header-bg: url('{{ $gdaHeaderBg }}'); }</style>
    @stack('head')
</head>
<body class="@stack('body-class')">
    @yield('content')
    <script>window.GDA_SW_URL = @json(asset('sw.js'));</script>
    <script src="{{ asset('js/gda-pwa.js') }}?v={{ $gdaAssetVer }}"></script>
    @stack('scripts')
</body>
</html>
