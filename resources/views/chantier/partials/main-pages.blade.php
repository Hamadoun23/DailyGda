  <!-- ── DASHBOARD ── -->
  <div class="page active" id="page-dashboard">
    <div class="page-header">
      <div>
        <div class="page-title" data-i18n="page.dashboard.title">Tableau de bord</div>
        <div class="page-sub" data-i18n="page.dashboard.sub">Vue d'ensemble du projet</div>
      </div>
        <button type="button" class="btn btn-primary" onclick="goTo('daily')" data-hide-for-partner data-i18n="page.dashboard.ctaDaily">✎ Saisie du jour</button>
    </div>

    <div class="stats-row">
      <div class="stat-card s-total">
        <div class="stat-val" id="st-total">35</div>
        <div class="stat-lbl" data-i18n="stat.total">Tâches totales</div>
      </div>
      <div class="stat-card s-done">
        <div class="stat-val" id="st-done">0</div>
        <div class="stat-lbl" data-i18n="stat.done">Terminées</div>
      </div>
      <div class="stat-card s-prog">
        <div class="stat-val" id="st-prog">0</div>
        <div class="stat-lbl" data-i18n="stat.prog">En cours</div>
      </div>
      <div class="stat-card s-late">
        <div class="stat-val" id="st-late">0</div>
        <div class="stat-lbl" data-i18n="stat.cancel">Annulées</div>
      </div>
    </div>

    <!-- Phase progress -->
    <div class="card">
      <div class="card-head" data-i18n="dash.phaseHead">Avancement par phase</div>
      <div id="phase-progress-list"></div>
    </div>

    <!-- Recent activity (admin uniquement) -->
    <div class="card" data-hide-for-partner>
      <div class="card-head" data-i18n="dash.recentHead">Activité récente</div>
      <div id="recent-activity">
        <div style="color:var(--muted);font-size:13px;padding:20px;text-align:center">
          Aucune activité enregistrée — commencez la saisie du jour.
        </div>
      </div>
    </div>

    <!-- Stats & graphiques (projet actif) -->
    <div class="card" id="dashboard-stats-card">
      <div class="card-head dashboard-stats-card-head">
        <span class="dashboard-stats-card-head__title" data-i18n="dash.statsHead">Données & statistiques</span>
        <button type="button" class="btn btn-excel btn-sm dashboard-stats-export-btn" onclick="exportDashboardExcel()" data-i18n="dash.exportExcel">Exporter Excel</button>
      </div>
      <p class="dashboard-charts-intro" data-i18n="dash.statsIntro">Synthèse du projet : statuts, phases, sous-phases. Les activités sont affichées par phase (1re phase par défaut) ; vous pouvez tout afficher si besoin.</p>
      <div class="dashboard-charts-grid">
        <div class="dashboard-chart-wrap">
          <div class="dashboard-chart-title" data-i18n="dash.chartStatus">Répartition par statut</div>
          <div class="dashboard-chart-canvas">
            <canvas id="chart-status-pie" aria-label="Camembert des statuts"></canvas>
          </div>
        </div>
        <div class="dashboard-chart-wrap">
          <div class="dashboard-chart-title" data-i18n="dash.chartPhase">Avancement par phase (%)</div>
          <div class="dashboard-chart-canvas">
            <canvas id="chart-phase-bar" aria-label="Histogramme par phase"></canvas>
          </div>
        </div>
        <div class="dashboard-chart-wrap dashboard-chart-wrap--wide">
          <div class="dashboard-chart-title" data-i18n="dash.chartSub">Sous-phases — progression moyenne</div>
          <div class="dashboard-chart-canvas dashboard-chart-canvas--tall">
            <canvas id="chart-subphase-bar" aria-label="Barres des sous-phases"></canvas>
          </div>
        </div>
        <div class="dashboard-chart-wrap dashboard-chart-wrap--wide">
          <div class="dashboard-chart-head-row">
            <div class="dashboard-chart-title" data-i18n="dash.chartAct">Activités — progression</div>
            <div id="dashboard-activity-toolbar" class="dashboard-activity-toolbar" style="display:none">
              <label for="dashboard-activity-phase-filter" class="dashboard-activity-filter-lbl" data-i18n="dash.filterShow">Afficher</label>
              <select id="dashboard-activity-phase-filter" class="dashboard-activity-phase-select" onchange="onDashboardActivityPhaseFilterChange(this)">
                <option value="">—</option>
              </select>
            </div>
          </div>
          <div id="dashboard-activity-empty" class="dashboard-activity-empty" hidden></div>
          <div id="dashboard-activity-chart-canvas" class="dashboard-chart-canvas dashboard-chart-canvas--activities">
            <canvas id="chart-activity-bar" aria-label="Barres par activité"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── DAILY INPUT ── -->
  <div class="page" id="page-daily">
    <div class="page-header">
      <div>
        <div class="page-title" data-i18n="page.daily.title">Saisie du jour</div>
        <div class="page-sub" id="daily-date-label">Chargement...</div>
      </div>
      <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="margin-bottom:0;min-width:170px">
          <label class="form-label" data-i18n="page.daily.inputDate">Date de saisie</label>
          <input type="date" id="daily-date-input" onchange="changeDailyDate(this.value)">
        </div>
        <button type="button" class="btn btn-secondary" onclick="filterDaily('all')" data-i18n="page.daily.filterAll">Toutes</button>
        <button type="button" class="btn btn-secondary" onclick="filterDaily('ip')" data-i18n="page.daily.filterIp">En cours</button>
        <button type="button" class="btn btn-secondary" onclick="filterDaily('nd')" data-i18n="page.daily.filterNd">Non démarrées</button>
      </div>
    </div>

    <div class="card" style="margin-bottom:12px">
      <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <div style="font-size:13px;color:var(--muted)" data-i18n="page.daily.hint">
          Mettez à jour l'avancement de vos tâches. Cliquez sur une ligne pour voir les détails.
        </div>
        <div style="margin-left:auto;display:flex;gap:8px">
          <div style="font-size:12px;color:var(--muted)" data-i18n="page.daily.phaseFilter">Filtre phase :</div>
          <select id="daily-phase-filter" style="padding:5px 10px;font-size:12px" onchange="renderDaily()">
            <option value="">— Toutes les phases —</option>
          </select>
        </div>
      </div>
    </div>

    <div id="daily-tasks-list"></div>

    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">
      <button type="button" class="btn btn-ok btn-sm" data-requires-submit data-daily-batch onclick="saveDailyAll()" data-i18n="page.daily.saveAll">✓ Enregistrer toutes les modifications</button>
    </div>
  </div>

  <!-- ── ALL TASKS ── -->
  <div class="page" id="page-tasks">
    <div class="page-header">
      <div>
        <div class="page-title" data-i18n="page.tasks.title">Toutes les tâches</div>
        <div class="page-sub">35 activités · Groupées par phase</div>
      </div>
      <div style="display:flex;gap:8px">
        <select id="task-filter-phase" style="padding:8px 12px;font-size:12px" onchange="renderAllTasks()">
          <option value="">Toutes les phases</option>
        </select>
        <select id="task-filter-status" style="padding:8px 12px;font-size:12px" onchange="renderAllTasks()">
          <option value="">Tous statuts</option>
          <option value="non_demarre">Non démarré</option>
          <option value="en_cours">En cours</option>
          <option value="termine">Terminé</option>
          <option value="annule">Annulée</option>
        </select>
      </div>
    </div>

    <div class="card" style="padding:0;overflow:hidden">
      <table class="tbl" id="all-tasks-table">
        <thead>
          <tr>
            <th data-i18n="page.tasks.thPhase">Phase / Sous-phase</th>
            <th data-i18n="page.tasks.thAct">Activité</th>
            <th data-i18n="page.tasks.thStart">Début</th>
            <th style="min-width:160px" data-i18n="page.tasks.thProg">Avancement</th>
            <th data-i18n="page.tasks.thStat">Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="tasks-tbody"></tbody>
      </table>
    </div>
  </div>

  <!-- ── PHOTOS ── -->
  <div class="page" id="page-photos">
    <div class="page-header">
      <div>
        <div class="page-title" data-i18n="page.photos.title">Galerie photos</div>
        <div class="page-sub" data-i18n="page.photos.sub">Documentation visuelle du chantier</div>
      </div>
    </div>

    <div class="photo-gallery-shell">
      <div class="photo-tabs" role="tablist" data-i18n-aria="page.photos.tabsAria" aria-label="Catégories de photos">
        <div class="photo-tab active" role="tab" data-photo-tab="avant" onclick="switchPhotoTab('avant')">Avant travaux</div>
        <div class="photo-tab" role="tab" data-photo-tab="pendant" onclick="switchPhotoTab('pendant')">Pendant travaux</div>
        <div class="photo-tab" role="tab" data-photo-tab="apres" onclick="switchPhotoTab('apres')">Après travaux</div>
        <div class="photo-tab" role="tab" data-photo-tab="securite" onclick="switchPhotoTab('securite')">Sécurité</div>
        <div class="photo-tab" role="tab" data-photo-tab="qualite" onclick="switchPhotoTab('qualite')">Contrôle qualité</div>
      </div>

      <div id="photo-sections"></div>
    </div>
  </div>

  <!-- ── ACTIVITY LOGS (admin) ── -->
  <div class="page" id="page-logs" data-admin-only style="display:none">
    <div class="page-header">
      <div>
        <div class="page-title" data-i18n="page.logs.title">Journal d’activité</div>
        <div class="page-sub" data-i18n="page.logs.sub">Historique des connexions et modifications</div>
      </div>
      <button type="button" class="btn btn-secondary" id="logs-refresh-btn" onclick="void loadActivityLogs(1)" data-i18n="page.logs.refresh">↻ Actualiser</button>
    </div>

    <div class="card logs-filters-card">
      <div class="form-row cols4 logs-filters-row">
        <div class="form-group">
          <label class="form-label" for="logs-filter-q" data-i18n="page.logs.filterSearch">Recherche</label>
          <input type="search" id="logs-filter-q" placeholder="Description…" data-i18n-placeholder="page.logs.filterSearchPh">
        </div>
        <div class="form-group">
          <label class="form-label" for="logs-filter-action" data-i18n="page.logs.filterAction">Type d’action</label>
          <select id="logs-filter-action">
            <option value="" data-i18n="page.logs.filterAll">Toutes</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="logs-filter-from" data-i18n="page.logs.filterFrom">Du</label>
          <input type="date" id="logs-filter-from">
        </div>
        <div class="form-group">
          <label class="form-label" for="logs-filter-to" data-i18n="page.logs.filterTo">Au</label>
          <input type="date" id="logs-filter-to">
        </div>
      </div>
    </div>

    <div class="card" style="padding:0;overflow:hidden">
      <table class="tbl logs-table" id="activity-logs-table">
        <thead>
          <tr>
            <th data-i18n="page.logs.thDate">Date</th>
            <th data-i18n="page.logs.thUser">Utilisateur</th>
            <th data-i18n="page.logs.thAction">Action</th>
            <th data-i18n="page.logs.thDesc">Description</th>
            <th data-i18n="page.logs.thProject">Projet</th>
            <th data-i18n="page.logs.thIp">IP</th>
          </tr>
        </thead>
        <tbody id="activity-logs-tbody">
          <tr><td colspan="6" class="logs-empty" data-i18n="common.loading">Chargement…</td></tr>
        </tbody>
      </table>
    </div>

    <div class="logs-pagination" id="logs-pagination"></div>
  </div>

  <!-- ── REPORT ── -->
  <div class="page" id="page-report">
    <div class="page-header">
      <div>
        <div class="page-title" data-i18n="page.report.title">Rapport journalier</div>
        <div class="page-sub" data-i18n="page.report.sub">Génération et export PDF</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <button type="button" class="btn btn-primary" onclick="printReport()" data-i18n="page.report.print">⬇ Imprimer / PDF</button>
      </div>
    </div>

    <!-- Report config (admin / chantier uniquement) -->
    <div class="card" data-hide-for-partner>
      <div class="card-head" data-i18n="page.report.params">Paramètres du rapport</div>
      <div class="form-row cols3">
        <div class="form-group">
          <label class="form-label" data-i18n="page.report.lblDate">Date du rapport</label>
          <input type="date" id="r-date">
        </div>
        <div class="form-group">
          <label class="form-label" data-i18n="page.report.lblTemp">Température (°C)</label>
          <input type="number" id="r-temp" value="37" placeholder="37">
        </div>
        <div class="form-group">
          <label class="form-label" data-i18n="page.report.lblWeather">Météo</label>
          <select id="r-weather">
            <option>Ensoleillé</option>
            <option selected>Ensoleillé et venteux</option>
            <option>Nuageux</option>
            <option>Pluvieux</option>
            <option>Venteux</option>
            <option>Orageux</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" data-i18n="page.report.lblProject">Nom du projet</label>
          <input type="text" id="r-project" value="Construction Project of a Heavy Equipment Washing Station">
        </div>
      </div>
    </div>

    <!-- Preview area -->
    <div class="card">
      <div class="card-head" data-i18n="page.report.preview">Aperçu du rapport</div>
      <div class="report-preview" id="report-preview-area">
        <div style="padding:40px;text-align:center;color:var(--muted)" data-i18n="page.report.loading">
          Chargement de l’aperçu…
        </div>
      </div>
    </div>
  </div>

@include('chantier.partials.page-forecast')
