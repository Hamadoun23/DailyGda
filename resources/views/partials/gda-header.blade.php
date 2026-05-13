@php
    $activeNav = $activeNav ?? 'chantier';
    $hideProjectsNav = $hideProjectsNav ?? false;
    $hideChantierNav = $hideChantierNav ?? false;
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
  @if (! $hideChantierNav || ! $hideProjectsNav)
  <nav class="header-nav" aria-label="Navigation principale">
    @unless ($hideChantierNav)
    <a href="{{ route('home') }}" class="header-nav-link {{ $activeNav === 'chantier' ? 'is-active' : '' }}">Chantier</a>
    @endunless
    @unless ($hideProjectsNav)
    <a href="{{ route('projects') }}" class="header-nav-link {{ $activeNav === 'projets' ? 'is-active' : '' }}">Projets</a>
    @endunless
  </nav>
  @endif
  @if ($activeNav === 'chantier')
    <div class="date-live" id="date-live"></div>
    <div style="width:12px"></div>
  @else
    <div style="width:12px"></div>
  @endif
  <div class="header-user-wrap">
    <div class="user-pill" id="user-pill-btn" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false" aria-controls="logout-popover" aria-label="Compte"
      onclick="toggleLogoutPopover(event)"
      onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();toggleLogoutPopover(event);}">
      <div class="user-avatar" id="user-av">K</div>
      <div class="user-name" id="user-nm">—</div>
    </div>
    <div id="logout-popover" class="logout-popover" role="menu" aria-hidden="true">
      <button type="button" class="logout-popover__btn" role="menuitem" onclick="closeLogoutPopover()">Rester</button>
      <button type="button" class="logout-popover__btn logout-popover__btn--out" role="menuitem" onclick="void performLogout()">Se déconnecter</button>
    </div>
  </div>
</header>
