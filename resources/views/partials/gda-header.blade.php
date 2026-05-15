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
    <div class="project-label" data-i18n="header.projectsMgmt">Gestion des projets</div>
  @endif
  <div class="header-spacer"></div>
  @if (! $hideChantierNav || ! $hideProjectsNav)
  <nav class="header-nav" aria-label="Navigation principale">
    @unless ($hideChantierNav)
    <a href="{{ route('home') }}" class="header-nav-link {{ $activeNav === 'chantier' ? 'is-active' : '' }}" data-i18n="header.chantier">Chantier</a>
    @endunless
    @unless ($hideProjectsNav)
    <a href="{{ route('projects') }}" class="header-nav-link {{ $activeNav === 'projets' ? 'is-active' : '' }}" data-i18n="header.projects">Projets</a>
    @endunless
  </nav>
  @endif
  @if ($activeNav === 'chantier')
    <div class="date-live" id="date-live"></div>
    <div style="width:12px"></div>
  @endif
  @if ($activeNav === 'chantier' || $activeNav === 'projets')
    <div class="header-lang" role="group" aria-label="Language">
      <span class="header-lang__lbl" data-i18n="ui.lang">Langue</span>
      <div class="header-lang__toggle">
        <button type="button" class="header-lang__btn" id="ui-lang-fr" onclick="setUiLang('fr')" lang="fr">FR</button>
        <button type="button" class="header-lang__btn" id="ui-lang-en" onclick="setUiLang('en')" lang="en">EN</button>
      </div>
    </div>
    <div style="width:12px"></div>
  @endif
  <div class="header-user-wrap">
    <div class="user-pill" id="user-pill-btn" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false" aria-controls="logout-popover" data-i18n-aria="header.account" aria-label="Compte"
      onclick="toggleLogoutPopover(event)"
      onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();toggleLogoutPopover(event);}">
      <div class="user-avatar" id="user-av">K</div>
      <div class="user-name" id="user-nm">—</div>
    </div>
    <div id="logout-popover" class="logout-popover" role="menu" aria-hidden="true">
      <button type="button" class="logout-popover__btn" role="menuitem" onclick="closeLogoutPopover()" data-i18n="logout.stay">Rester</button>
      <button type="button" class="logout-popover__btn logout-popover__btn--out" role="menuitem" onclick="void performLogout()" data-i18n="logout.out">Se déconnecter</button>
    </div>
  </div>
</header>
