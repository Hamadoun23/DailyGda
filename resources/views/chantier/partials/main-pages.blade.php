  <!-- ── DASHBOARD ── -->
  <div class="page active" id="page-dashboard">
    <div class="page-header">
      <div>
        <div class="page-title">Tableau de bord</div>
        <div class="page-sub">Vue d'ensemble du projet</div>
      </div>
      <button type="button" class="btn btn-primary" onclick="goTo('daily')">✎ Saisie du jour</button>
    </div>

    <div class="stats-row">
      <div class="stat-card s-total">
        <div class="stat-val" id="st-total">35</div>
        <div class="stat-lbl">Tâches totales</div>
      </div>
      <div class="stat-card s-done">
        <div class="stat-val" id="st-done">0</div>
        <div class="stat-lbl">Terminées</div>
      </div>
      <div class="stat-card s-prog">
        <div class="stat-val" id="st-prog">0</div>
        <div class="stat-lbl">En cours</div>
      </div>
      <div class="stat-card s-late">
        <div class="stat-val" id="st-late">0</div>
        <div class="stat-lbl">Annulées</div>
      </div>
    </div>

    <!-- Phase progress -->
    <div class="card">
      <div class="card-head">Avancement par phase</div>
      <div id="phase-progress-list"></div>
    </div>

    <!-- Recent activity -->
    <div class="card">
      <div class="card-head">Activité récente</div>
      <div id="recent-activity">
        <div style="color:var(--muted);font-size:13px;padding:20px;text-align:center">
          Aucune activité enregistrée — commencez la saisie du jour.
        </div>
      </div>
    </div>
  </div>

  <!-- ── DAILY INPUT ── -->
  <div class="page" id="page-daily">
    <div class="page-header">
      <div>
        <div class="page-title">Saisie du jour</div>
        <div class="page-sub" id="daily-date-label">Chargement...</div>
      </div>
      <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="margin-bottom:0;min-width:170px">
          <label class="form-label">Date de saisie</label>
          <input type="date" id="daily-date-input" onchange="changeDailyDate(this.value)">
        </div>
        <button type="button" class="btn btn-secondary" onclick="filterDaily('all')">Toutes</button>
        <button type="button" class="btn btn-secondary" onclick="filterDaily('ip')">En cours</button>
        <button type="button" class="btn btn-secondary" onclick="filterDaily('nd')">Non démarrées</button>
      </div>
    </div>

    <div class="card" style="margin-bottom:12px">
      <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <div style="font-size:13px;color:var(--muted)">
          Mettez à jour l'avancement de vos tâches. Cliquez sur une ligne pour voir les détails.
        </div>
        <div style="margin-left:auto;display:flex;gap:8px">
          <div style="font-size:12px;color:var(--muted)">Filtre phase :</div>
          <select id="daily-phase-filter" style="padding:5px 10px;font-size:12px" onchange="renderDaily()">
            <option value="">— Toutes les phases —</option>
          </select>
        </div>
      </div>
    </div>

    <div id="daily-tasks-list"></div>

    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">
      <button type="button" class="btn btn-ok btn-sm" data-requires-submit data-daily-batch onclick="saveDailyAll()">✓ Enregistrer toutes les modifications</button>
    </div>
  </div>

  <!-- ── ALL TASKS ── -->
  <div class="page" id="page-tasks">
    <div class="page-header">
      <div>
        <div class="page-title">Toutes les tâches</div>
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
            <th>Phase / Sous-phase</th>
            <th>Activité</th>
            <th>Début</th>
            <th>Durée</th>
            <th style="min-width:160px">Avancement</th>
            <th>Statut</th>
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
        <div class="page-title">Galerie photos</div>
        <div class="page-sub">Documentation visuelle du chantier</div>
      </div>
    </div>

    <div class="photo-gallery-shell">
      <div class="photo-tabs" role="tablist" aria-label="Catégories de photos">
        <div class="photo-tab active" role="tab" onclick="switchPhotoTab('avant')">Avant travaux</div>
        <div class="photo-tab" role="tab" onclick="switchPhotoTab('pendant')">Pendant travaux</div>
        <div class="photo-tab" role="tab" onclick="switchPhotoTab('apres')">Après travaux</div>
        <div class="photo-tab" role="tab" onclick="switchPhotoTab('securite')">Sécurité</div>
        <div class="photo-tab" role="tab" onclick="switchPhotoTab('qualite')">Contrôle qualité</div>
      </div>

      <div id="photo-sections"></div>
    </div>
  </div>

  <!-- ── REPORT ── -->
  <div class="page" id="page-report">
    <div class="page-header">
      <div>
        <div class="page-title">Rapport journalier</div>
        <div class="page-sub">Génération et export PDF</div>
      </div>
      <div style="display:flex;gap:8px">
        <button type="button" class="btn btn-secondary" onclick="openReportLangModal('preview')">👁 Aperçu</button>
        <button type="button" class="btn btn-primary" onclick="openReportLangModal('pdf')">⬇ Imprimer / PDF</button>
      </div>
    </div>

    <!-- Report config -->
    <div class="card">
      <div class="card-head">Paramètres du rapport</div>
      <div class="form-row cols3">
        <div class="form-group">
          <label class="form-label">Date du rapport</label>
          <input type="date" id="r-date">
        </div>
        <div class="form-group">
          <label class="form-label">Température (°C)</label>
          <input type="number" id="r-temp" value="37" placeholder="37">
        </div>
        <div class="form-group">
          <label class="form-label">Météo</label>
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
          <label class="form-label">Nom du projet</label>
          <input type="text" id="r-project" value="Construction Project of a Heavy Equipment Washing Station">
        </div>
      </div>
    </div>

    <!-- Preview area -->
    <div class="card">
      <div class="card-head">Aperçu du rapport</div>
      <div class="report-preview" id="report-preview-area">
        <div style="padding:40px;text-align:center;color:var(--muted)">
          Cliquez sur "Aperçu" pour générer le rapport.
        </div>
      </div>
    </div>
  </div>
