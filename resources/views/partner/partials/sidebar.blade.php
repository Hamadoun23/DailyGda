<!-- ===== SIDEBAR PARTENAIRE (lecture : tableau de bord, tâches, rapport) ===== -->
<nav class="sidebar">
  <div class="sidebar-section">Espace partenaire</div>
  <div class="nav-item active" onclick="goTo('dashboard')"><span class="nav-icon">◈</span> Tableau de bord</div>
  <div class="nav-item" onclick="goTo('tasks')"><span class="nav-icon">≡</span> Toutes les tâches</div>
  <div class="nav-item" onclick="goTo('report')"><span class="nav-icon">◻</span> Rapport PDF</div>

  <div style="padding:16px 16px 0">
    <div class="sidebar-progress">
      <div class="sp-label">Progression globale</div>
      <div class="sp-num" id="sb-progress">0%</div>
      <div class="sp-bar"><div class="sp-fill" id="sb-fill" style="width:0%"></div></div>
    </div>
  </div>

  <div class="sidebar-footer">
    <div id="sidebar-project-name" style="font-size:10px;letter-spacing:1px;text-transform:uppercase;color:var(--muted);margin-bottom:6px">Chargement…</div>
    <div style="font-size:11px;color:var(--muted)">Mise à jour: <span id="last-update">—</span></div>
  </div>
</nav>
