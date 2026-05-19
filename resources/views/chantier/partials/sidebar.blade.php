<div class="sidebar-backdrop" id="sidebar-backdrop" aria-hidden="true" onclick="closeSidebar()"></div>
<!-- ===== SIDEBAR ===== -->
<nav class="sidebar" id="app-sidebar">
  <div class="sidebar-section" data-i18n="sidebar.section">Navigation</div>
  <div class="nav-item active" onclick="goTo('dashboard')"><span class="nav-icon">◈</span> <span data-i18n="sidebar.dashboard">Tableau de bord</span></div>
  <div class="sidebar-project-switch">
    <label class="sidebar-project-caption" for="sidebar-project-select" data-i18n="sidebar.activeProject">Projet actif</label>
    <select class="sidebar-project-select" id="sidebar-project-select" aria-label="Changer de projet">
      <option value="">Chargement…</option>
    </select>
  </div>
  <div class="nav-item" onclick="goTo('daily')"><span class="nav-icon">✎</span> <span data-i18n="sidebar.daily">Saisie du jour</span></div>
  <div class="nav-item" onclick="goTo('tasks')"><span class="nav-icon">≡</span> <span data-i18n="sidebar.tasks">Toutes les tâches</span></div>
  <div class="nav-item" onclick="goTo('photos')"><span class="nav-icon">◉</span> <span data-i18n="sidebar.photos">Galerie photos</span></div>
  <div class="nav-item" onclick="goTo('report')"><span class="nav-icon">◻</span> <span data-i18n="sidebar.report">Rapport PDF</span></div>
  <div class="nav-item" data-admin-only style="display:none" onclick="goTo('logs')"><span class="nav-icon">◎</span> <span data-i18n="sidebar.logs">Journal d'activité</span></div>

  <div class="sidebar-section sidebar-section--forecast" data-i18n="sidebar.forecastSection">Prévisions</div>
  <div class="nav-item" onclick="goTo('forecast')"><span class="nav-icon">⛅</span> <span data-i18n="sidebar.forecast">Prévisions météo</span></div>

  <a href="{{ route('home') }}" class="nav-item nav-item--link">
    <span class="nav-icon">⌂</span>
    <span data-i18n="header.chantier">Chantier</span>
  </a>
  <a href="{{ route('projects') }}" class="nav-item nav-item--link" data-admin-only style="display:none">
    <span class="nav-icon">▣</span>
    <span data-i18n="header.projects">Projets</span>
  </a>

  <div style="padding:16px 16px 0">
    <div class="sidebar-progress">
      <div class="sp-label" data-i18n="sidebar.overall">Progression globale</div>
      <div class="sp-num" id="sb-progress">0%</div>
      <div class="sp-bar"><div class="sp-fill" id="sb-fill" style="width:0%"></div></div>
    </div>
  </div>

  <div class="sidebar-footer">
    <div id="sidebar-project-name" style="font-size:10px;letter-spacing:1px;text-transform:uppercase;color:var(--muted);margin-bottom:6px">Chargement…</div>
    <div style="font-size:11px;color:var(--muted)"><span data-i18n="sidebar.footerUpdate">Mise à jour:</span> <span id="last-update">—</span></div>
  </div>
</nav>
