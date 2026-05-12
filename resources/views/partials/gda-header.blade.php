@php
    $activeNav = $activeNav ?? 'chantier';
@endphp
<!-- ===== HEADER ===== -->
<header class="header">
  <a href="{{ route('home') }}" class="logo">
    <img src="{{ asset('img/GDACONST.png') }}" alt="GDA" class="brand-logo brand-logo--header">
  </a>
  <div class="header-sep"></div>
  @if ($activeNav === 'chantier')
    <div class="project-label" id="project-label"></div>
  @else
    <div class="project-label">Gestion des projets</div>
  @endif
  <div class="header-spacer"></div>
  <nav class="header-nav" aria-label="Navigation principale">
    <a href="{{ route('home') }}" class="header-nav-link {{ $activeNav === 'chantier' ? 'is-active' : '' }}">Chantier</a>
    <a href="{{ route('projects') }}" class="header-nav-link {{ $activeNav === 'projets' ? 'is-active' : '' }}">Projets</a>
  </nav>
  @if ($activeNav === 'chantier')
    <div class="date-live" id="date-live"></div>
    <div style="width:12px"></div>
  @else
    <div style="width:12px"></div>
  @endif
  <div class="user-pill" onclick="doLogout()">
    <div class="user-avatar" id="user-av">K</div>
    <div class="user-name" id="user-nm">—</div>
  </div>
</header>
