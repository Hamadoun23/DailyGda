<!-- ===== LIGHTBOX ===== -->
<div class="lightbox" id="lightbox">
  <button type="button" class="lb-close" onclick="closeLB()">✕</button>
  <button type="button" class="lb-nav lb-prev" onclick="lbNav(-1)">‹</button>
  <img id="lb-img" src="" alt="">
  <button type="button" class="lb-nav lb-next" onclick="lbNav(1)">›</button>
</div>

<!-- ===== MODAL TÂCHE ===== -->
<div class="modal-backdrop" id="modal-task">
  <div class="modal">
    <div class="modal-title" id="modal-title">Modifier la tâche</div>
    <input type="hidden" id="modal-task-id">

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Phase</label>
        <input type="text" id="m-phase" readonly style="opacity:.6">
      </div>
      <div class="form-group">
        <label class="form-label">Sous-phase</label>
        <input type="text" id="m-sub" readonly style="opacity:.6">
      </div>
    </div>
    <div class="form-group" style="margin-bottom:14px">
      <label class="form-label">Activité</label>
      <input type="text" id="m-act" readonly style="opacity:.6">
    </div>
    <div class="form-group" style="margin-bottom:14px">
      <label class="form-label">Statut</label>
      <select id="m-status" onchange="syncTaskStatusCommentField()">
          <option value="non_demarre">Non démarré</option>
          <option value="en_cours">En cours</option>
          <option value="termine">Terminé</option>
          <option value="annule">Annulée</option>
        </select>
    </div>
    <div class="form-group" style="margin-bottom:8px">
      <label class="form-label">Avancement — <span id="m-pct-lbl">0</span>%</label>
      <input type="range" id="m-progress" min="0" max="100" step="1" value="0"
        oninput="document.getElementById('m-pct-lbl').textContent=this.value">
    </div>
    <div class="form-group" style="margin-bottom:14px">
      <label class="form-label" id="m-comment-label">Commentaire / Observations</label>
      <textarea id="m-comment" rows="3" placeholder="Notes du jour..."></textarea>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
      <button type="button" class="btn btn-ok" data-requires-submit onclick="saveTask()">✓ Enregistrer</button>
    </div>
  </div>
</div>

<!-- ===== MODAL LANGUE RAPPORT ===== -->
<div class="modal-backdrop" id="modal-report-lang">
  <div class="modal" style="max-width:420px">
    <div class="modal-title" id="report-lang-modal-title">Langue du rapport</div>
    <p id="report-lang-modal-hint" style="font-size:13px;color:var(--muted);margin:0 0 18px">Choisissez la langue d'affichage du rapport.</p>
    <div class="modal-actions" style="justify-content:flex-end;gap:8px">
      <button type="button" class="btn btn-secondary" onclick="closeReportLangModal()">Annuler</button>
      <button type="button" class="btn btn-secondary" onclick="confirmReportLang('fr')">Français</button>
      <button type="button" class="btn btn-primary" onclick="confirmReportLang('en')">English</button>
    </div>
  </div>
</div>

<!-- ===== TOAST ===== -->
<div class="toast" id="toast"></div>
