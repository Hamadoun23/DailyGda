<!-- ===== SIDEBAR PARTENAIRE (lecture : tableau de bord, tâches, rapport) ===== -->
<nav class="sidebar">
  <div class="sidebar-section" data-i18n="partner.section">Espace partenaire</div>
  <div class="nav-item active" onclick="goTo('dashboard')"><span class="nav-icon">◈</span> <span data-i18n="sidebar.dashboard">Tableau de bord</span></div>
  <div class="nav-item" onclick="goTo('tasks')"><span class="nav-icon">≡</span> <span data-i18n="sidebar.tasks">Toutes les tâches</span></div>
  <div class="nav-item" onclick="goTo('report')"><span class="nav-icon">◻</span> <span data-i18n="sidebar.report">Rapport PDF</span></div>

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
