@extends('layouts.gda')

@section('title', 'GDA — Projets')

@section('content')
@include('partials.gda-header', ['activeNav' => 'projets'])

<main class="main main--solo">
  <div class="page-header">
    <div>
      <div class="page-title" data-i18n="projects.pageTitle">Projets</div>
      <div class="page-sub" data-i18n="projects.pageSub">Créez un projet, ouvrez « Phases », puis ajoutez les phases et sous-phases avant les activités sur le chantier.</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a href="{{ route('home') }}" class="btn btn-secondary" data-i18n="projects.backChantier">← Retour au chantier</a>
      <button type="button" class="btn btn-primary" id="btn-new-project" data-i18n="projects.btnNew">+ Nouveau projet</button>
    </div>
  </div>

  <div class="card" style="padding:0;overflow:hidden">
    <div class="card-head" style="margin:0;border-radius:0" data-i18n="projects.listHead">Liste des projets</div>
    <div id="projects-loading" style="padding:24px;color:var(--muted);text-align:center" data-i18n="common.loading">Chargement…</div>
    <div id="projects-empty" style="display:none;padding:24px;color:var(--muted);text-align:center" data-i18n="projects.empty">Aucun projet accessible.</div>
    <table class="tbl" id="projects-table" style="display:none">
      <thead>
        <tr>
          <th class="tbl-col-drag" aria-hidden="true"></th>
          <th data-i18n="projects.colName">Nom</th>
          <th data-i18n="projects.colStatus">Statut</th>
          <th style="text-align:center" data-i18n="projects.colProgress">Progression</th>
          <th style="text-align:center" data-i18n="projects.colTasks">Tâches</th>
          <th style="text-align:right;width:1%" data-i18n="projects.colActions">Actions</th>
        </tr>
      </thead>
      <tbody id="projects-tbody"></tbody>
    </table>
  </div>

  <div id="structure-panel" class="card" style="display:none;margin-top:20px">
    <div class="card-head" style="margin:0;border-radius:0;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
      <span id="structure-panel-title" class="structure-panel-title" data-i18n="projects.structureIntro">Structure du projet</span>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="button" class="btn btn-secondary btn-sm" id="structure-edit-project" data-i18n="projects.editProject">Modifier le projet</button>
        <button type="button" class="btn btn-primary btn-sm" id="structure-add-phase" data-i18n="projects.addPhase">+ Phase</button>
        <button type="button" class="btn btn-secondary btn-sm" id="structure-close" data-i18n="projects.close">Fermer</button>
      </div>
    </div>
    <div id="structure-loading" style="padding:20px;color:var(--muted);display:none" data-i18n="common.loading">Chargement…</div>
    <div id="structure-empty" style="padding:24px;color:var(--muted);display:none;text-align:center" data-i18n="projects.structureEmpty">Aucune phase. Cliquez sur « + Phase » pour commencer.</div>
    <div id="structure-stats" style="display:none;padding:12px 20px 0;color:var(--muted);font-size:12px"></div>
    <div id="structure-tree" style="padding:0 20px 20px;max-height:70vh;overflow-y:auto"></div>
  </div>

  <div id="structure-editor-panel" class="card" style="display:none;margin-top:16px">
    <div class="card-head" style="margin:0;border-radius:0;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
      <span id="structure-editor-title" data-i18n="projects.editor.defaultTitle">Formulaire</span>
      <button type="button" class="btn btn-secondary btn-sm" id="structure-editor-close" data-i18n="projects.close">Fermer</button>
    </div>
    <div style="padding:20px">
      <p id="structure-editor-hint" style="display:none;color:var(--muted);font-size:13px;margin:0 0 14px"></p>
      <form id="structure-editor-form" novalidate>
        <div id="structure-editor-fields"></div>
        <div id="structure-editor-delete" style="display:none">
          <p id="structure-editor-delete-text" style="margin:0 0 16px;color:var(--text)"></p>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button type="button" class="btn btn-secondary btn-sm" id="structure-editor-delete-cancel" data-i18n="modal.cancel">Annuler</button>
            <button type="button" class="btn btn-primary btn-sm" id="structure-editor-delete-confirm" style="background:var(--danger);border-color:var(--danger)" data-i18n="projects.delConfirm">Confirmer la suppression</button>
          </div>
        </div>
        <div class="modal-actions" id="structure-editor-actions">
          <button type="button" class="btn btn-secondary" id="structure-editor-form-cancel" data-i18n="modal.cancel">Annuler</button>
          <button type="submit" class="btn btn-primary" id="structure-editor-submit" data-i18n="modal.save">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>

  <div class="toast" id="toast"></div>
</main>
@endsection

@push('scripts')
<script>
  window.GDA_API_BASE = @json(url('/api'));
  window.GDA_APP_URL = @json(url('/'));
  window.GDA_LOGIN_URL = @json(route('login'));
  window.GDA_AUTH_REQUIRED = true;
  window.GDA_IS_PARTNER = false;
</script>
<script src="{{ asset('js/gda-i18n.js') }}"></script>
<script src="{{ asset('js/gda-projects.js') }}"></script>
@endpush
