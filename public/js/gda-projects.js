/**
 * Page Projets — liste, création, phases & sous-phases
 */
const API_BASE = window.GDA_API_BASE || window.location.origin + '/api';
const TOKEN_KEY = 'gda_token';
const USER_KEY = 'gda_user';
const PROJECT_STORAGE_KEY = 'gda_project_id';

let cachedProjects = [];
let structureProjectId = null;
let structureProjectName = '';

function clearAuth() {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
}

function showLogin() {
  window.location.href = window.GDA_LOGIN_URL || '/login';
}

/**
 * @param {string} path
 * @param {RequestInit & { projectContextId?: string|number|null }} [options]
 */
async function apiFetch(path, options = {}) {
  const opts = { ...options };
  const ctxProject = opts.projectContextId;
  delete opts.projectContextId;

  const headers = {
    Accept: 'application/json',
    ...(opts.body !== undefined && !(opts.body instanceof FormData)
      ? { 'Content-Type': 'application/json' }
      : {}),
    ...(opts.headers || {}),
  };
  const token = localStorage.getItem(TOKEN_KEY);
  if (token) headers.Authorization = 'Bearer ' + token;

  const pid =
    ctxProject !== undefined && ctxProject !== null && ctxProject !== ''
      ? String(ctxProject)
      : localStorage.getItem(PROJECT_STORAGE_KEY);
  if (pid) headers['X-Project-Id'] = pid;

  const res = await fetch(API_BASE + path, { ...opts, headers });
  if (res.status === 401) {
    clearAuth();
    throw new Error('Non autorisé');
  }
  if (!res.ok) {
    let msg = res.statusText;
    try {
      const j = await res.json();
      msg = j.message || (j.errors && JSON.stringify(j.errors)) || msg;
    } catch (_) {}
    throw new Error(msg);
  }
  const ct = res.headers.get('content-type');
  if (ct && ct.includes('application/json')) return res.json();
  return res;
}

function toast(msg, type = '') {
  const el = document.getElementById('toast');
  if (!el) return;
  el.textContent = msg;
  el.className = 'toast show ' + type;
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 3500);
}

async function doLogout() {
  if (!confirm('Se déconnecter ?')) return;
  try {
    await apiFetch('/logout', { method: 'POST', body: '{}' });
  } catch (_) {}
  clearAuth();
  window.location.href = window.GDA_LOGIN_URL || '/login';
}

function escapeHtml(s) {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

function escapeAttr(s) {
  return String(s ?? '')
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;');
}

async function refreshProjectList() {
  const data = await apiFetch('/projects');
  renderProjects(data.projects || []);
  return data.projects || [];
}

let structureEditorState = null;

function parseDateInput(value) {
  const [year, month, day] = String(value).split('-').map(Number);
  return new Date(year, month - 1, day);
}

function getProjectStartDate(projectId) {
  const project = cachedProjects.find(x => Number(x.id) === Number(projectId));
  const raw = project?.start_date;
  if (raw) return String(raw).slice(0, 10);
  return new Date().toISOString().slice(0, 10);
}

function startDayToDateStr(projectStartDate, startDay) {
  const date = parseDateInput(projectStartDate);
  date.setDate(date.getDate() + Math.max(0, Number(startDay || 1) - 1));
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function dateStrToStartDay(projectStartDate, dateStr) {
  const start = parseDateInput(projectStartDate);
  const selected = parseDateInput(dateStr);
  const diff = Math.round((selected - start) / 86400000);
  return Math.max(1, diff + 1);
}

function formatTaskScheduleLabel(startDay, durationDays, projectId = structureProjectId) {
  const startDate = startDayToDateStr(getProjectStartDate(projectId), startDay ?? 1);
  const label = parseDateInput(startDate).toLocaleDateString('fr-FR');
  return `Début ${label} · ${durationDays ?? 1} j`;
}

function closeStructureEditor() {
  structureEditorState = null;
  const panel = document.getElementById('structure-editor-panel');
  if (panel) panel.style.display = 'none';
}

function editorTitle(config) {
  const map = {
    'project:create': 'Nouveau projet',
    'project:edit': 'Modifier le projet',
    'project:delete': 'Supprimer le projet',
    'phase:create': 'Nouvelle phase',
    'phase:edit': 'Modifier la phase',
    'subphase:create': 'Nouvelle sous-phase',
    'subphase:edit': 'Modifier la sous-phase',
    'task:create': 'Nouvelle activité',
    'task:edit': 'Modifier l’activité',
    'task:delete': 'Supprimer l’activité',
    'phase:delete': 'Supprimer la phase',
    'subphase:delete': 'Supprimer la sous-phase',
  };
  return map[`${config.entity}:${config.action}`] || 'Formulaire';
}

function editorHint(config) {
  const map = {
    'project:create': 'Nommez le chantier avant d’ajouter phases et activités.',
    'project:edit': 'Le nom est affiché dans la liste des projets et sur le chantier.',
    'phase:create': 'Une phase regroupe plusieurs sous-phases.',
    'phase:edit': 'Le libellé est visible dans la structure et sur le chantier.',
    'subphase:create': 'La sous-phase accueillera les activités du chantier.',
    'subphase:edit': 'Le libellé est visible dans la structure et sur le chantier.',
    'task:create': 'Définissez l’activité, sa date de début et sa durée.',
    'task:edit': 'Ajustez le libellé, la date de début et la durée de l’activité.',
  };
  return map[`${config.entity}:${config.action}`] || '';
}

function editorDeleteMessage(config) {
  if (config.entity === 'project' && config.action === 'delete') {
    const name = config.data?.name || 'ce projet';
    return `Le projet « ${name} » et toute sa structure (phases, sous-phases, activités) seront supprimés définitivement.`;
  }

  const map = {
    'task:delete': 'Cette activité sera retirée du chantier.',
    'phase:delete': 'La phase et tout son contenu (sous-phases, activités) seront supprimés.',
    'subphase:delete': 'La sous-phase et ses activités seront supprimées.',
  };
  return map[`${config.entity}:${config.action}`] || 'Confirmez la suppression.';
}

function renderStructureEditorFields(config) {
  const data = config.data || {};
  if (config.entity === 'task') {
    const projectId = config.projectId ?? structureProjectId;
    const startDate = startDayToDateStr(getProjectStartDate(projectId), data.start_day ?? 1);
    return `
      <div class="form-group" style="margin-bottom:14px">
        <label class="form-label" for="structure-editor-activity">Activité</label>
        <input type="text" id="structure-editor-activity" value="${escapeAttr(data.activity || '')}" required maxlength="500">
      </div>
      <div class="form-row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
        <div class="form-group">
          <label class="form-label" for="structure-editor-start-date">Date de début</label>
          <input type="date" id="structure-editor-start-date" value="${escapeAttr(startDate)}" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="structure-editor-duration-days">Durée (jours)</label>
          <input type="number" id="structure-editor-duration-days" min="1" step="1" value="${escapeAttr(String(data.duration_days ?? 1))}" required>
        </div>
      </div>`;
  }

  const label =
    config.entity === 'project'
      ? 'Nom du projet'
      : config.entity === 'phase'
        ? 'Nom de la phase'
        : 'Nom de la sous-phase';

  return `
    <div class="form-group" style="margin-bottom:14px">
      <label class="form-label" for="structure-editor-name">${label}</label>
      <input type="text" id="structure-editor-name" value="${escapeAttr(data.name || '')}" required maxlength="255">
    </div>`;
}

function openStructureEditor(config) {
  structureEditorState = config;
  const panel = document.getElementById('structure-editor-panel');
  const title = document.getElementById('structure-editor-title');
  const hint = document.getElementById('structure-editor-hint');
  const fields = document.getElementById('structure-editor-fields');
  const deleteBox = document.getElementById('structure-editor-delete');
  const deleteText = document.getElementById('structure-editor-delete-text');
  const actions = document.getElementById('structure-editor-actions');
  const isDelete = config.action === 'delete';

  title.textContent = editorTitle(config);
  const hintText = editorHint(config);
  if (hintText) {
    hint.textContent = hintText;
    hint.style.display = 'block';
  } else {
    hint.textContent = '';
    hint.style.display = 'none';
  }

  fields.innerHTML = isDelete ? '' : renderStructureEditorFields(config);
  deleteBox.style.display = isDelete ? 'block' : 'none';
  deleteText.textContent = editorDeleteMessage(config);
  actions.style.display = isDelete ? 'none' : 'flex';

  panel.style.display = 'block';
  panel.scrollIntoView({ behavior: 'smooth', block: 'start' });

  if (!isDelete) {
    const firstInput = fields.querySelector('input');
    if (firstInput) {
      firstInput.focus();
      if (firstInput.select) firstInput.select();
    }
  }
}

function readStructureEditorValues() {
  if (!structureEditorState) return null;
  if (structureEditorState.entity === 'task') {
    const activity = document.getElementById('structure-editor-activity')?.value.trim() || '';
    const startDate = document.getElementById('structure-editor-start-date')?.value || '';
    const durationDays = Number(document.getElementById('structure-editor-duration-days')?.value);
    const projectId = structureEditorState.projectId ?? structureProjectId;
    const startDay = dateStrToStartDay(getProjectStartDate(projectId), startDate);
    if (!activity) throw new Error('Le libellé de l’activité est obligatoire.');
    if (!startDate) throw new Error('La date de début est obligatoire.');
    if (!Number.isInteger(startDay) || startDay < 1) throw new Error('Date de début invalide.');
    if (!Number.isInteger(durationDays) || durationDays < 1) throw new Error('Durée invalide.');
    return { activity, start_day: startDay, duration_days: durationDays };
  }

  const name = document.getElementById('structure-editor-name')?.value.trim() || '';
  if (!name) throw new Error('Le nom est obligatoire.');
  return { name };
}

async function submitStructureEditor(event) {
  event.preventDefault();
  if (!structureEditorState || structureEditorState.action === 'delete') return;

  try {
    const values = readStructureEditorValues();
    const { entity, action } = structureEditorState;

    if (entity === 'project') {
      if (action === 'create') {
        await apiFetch('/projects', {
          method: 'POST',
          body: JSON.stringify({ name: values.name, status: 'planifie' }),
        });
        toast('Projet créé — cliquez sur « Phases » pour la suite.', 'ok');
        await refreshProjectList();
      } else {
        const projectId = structureEditorState.projectId;
        await apiFetch(`/projects/${projectId}`, {
          method: 'PUT',
          body: JSON.stringify({ name: values.name }),
          projectContextId: projectId,
        });
        toast('Projet mis à jour', 'ok');
        if (structureProjectId === projectId) {
          structureProjectName = values.name;
          const title = document.getElementById('structure-panel-title');
          if (title) title.textContent = 'Structure — ' + structureProjectName;
        }
        await refreshProjectList();
      }
    } else if (entity === 'phase') {
      const projectId = structureEditorState.projectId ?? structureProjectId;
      if (action === 'create') {
        await apiFetch(`/projects/${projectId}/phases`, {
          method: 'POST',
          body: JSON.stringify({ name: values.name }),
          projectContextId: projectId,
        });
        toast('Phase ajoutée', 'ok');
        await loadStructureTree();
        await refreshProjectList();
      } else {
        await apiFetch(`/phases/${structureEditorState.phaseId}`, {
          method: 'PUT',
          body: JSON.stringify({ name: values.name }),
          projectContextId: projectId,
        });
        toast('Phase mise à jour', 'ok');
        await loadStructureTree();
      }
    } else if (entity === 'subphase') {
      const projectId = structureEditorState.projectId ?? structureProjectId;
      if (action === 'create') {
        await apiFetch(`/phases/${structureEditorState.phaseId}/sub-phases`, {
          method: 'POST',
          body: JSON.stringify({ name: values.name }),
          projectContextId: projectId,
        });
        toast('Sous-phase ajoutée', 'ok');
        await loadStructureTree();
      } else {
        await apiFetch(`/sub-phases/${structureEditorState.subPhaseId}`, {
          method: 'PUT',
          body: JSON.stringify({ name: values.name }),
          projectContextId: projectId,
        });
        toast('Sous-phase mise à jour', 'ok');
        await loadStructureTree();
      }
    } else if (entity === 'task') {
      const projectId = structureEditorState.projectId ?? structureProjectId;
      if (action === 'create') {
        await apiFetch('/tasks', {
          method: 'POST',
          body: JSON.stringify({
            sub_phase_id: Number(structureEditorState.subPhaseId),
            activity: values.activity,
            start_day: values.start_day,
            duration_days: values.duration_days,
          }),
          projectContextId: projectId,
        });
        toast('Activité ajoutée', 'ok');
        await loadStructureTree();
        await refreshProjectList();
      } else {
        await apiFetch(`/tasks/${structureEditorState.taskId}`, {
          method: 'PUT',
          body: JSON.stringify(values),
          projectContextId: projectId,
        });
        toast('Activité mise à jour', 'ok');
        await loadStructureTree();
        await refreshProjectList();
      }
    }

    closeStructureEditor();
  } catch (err) {
    toast(err.message || 'Erreur', 'err');
  }
}

async function confirmStructureEditorDelete() {
  if (!structureEditorState || structureEditorState.action !== 'delete') return;
  const projectId = structureEditorState.projectId ?? structureProjectId;

  try {
    if (structureEditorState.entity === 'task') {
      await apiFetch(`/tasks/${structureEditorState.taskId}`, {
        method: 'DELETE',
        projectContextId: projectId,
      });
      toast('Activité supprimée', 'ok');
      await loadStructureTree();
      await refreshProjectList();
    } else if (structureEditorState.entity === 'phase') {
      await apiFetch(`/phases/${structureEditorState.phaseId}`, {
        method: 'DELETE',
        projectContextId: projectId,
      });
      toast('Phase supprimée', 'ok');
      await loadStructureTree();
      await refreshProjectList();
    } else if (structureEditorState.entity === 'subphase') {
      await apiFetch(`/sub-phases/${structureEditorState.subPhaseId}`, {
        method: 'DELETE',
        projectContextId: projectId,
      });
      toast('Sous-phase supprimée', 'ok');
      await loadStructureTree();
      await refreshProjectList();
    } else if (structureEditorState.entity === 'project') {
      await apiFetch(`/projects/${structureEditorState.projectId}`, {
        method: 'DELETE',
        projectContextId: structureEditorState.projectId,
      });
      toast('Projet supprimé', 'ok');
      if (structureProjectId === structureEditorState.projectId) {
        closeStructurePanel();
      }
      const cur = localStorage.getItem(PROJECT_STORAGE_KEY);
      if (cur && String(cur) === String(structureEditorState.projectId)) {
        localStorage.removeItem(PROJECT_STORAGE_KEY);
      }
      await refreshProjectList();
    }
    closeStructureEditor();
  } catch (err) {
    toast(err.message || 'Erreur', 'err');
  }
}

function bindProjectTableOnce(tbody) {
  tbody.addEventListener('click', async e => {
    const openBtn = e.target.closest('.open-project-btn');
    if (openBtn) {
      localStorage.setItem(PROJECT_STORAGE_KEY, String(openBtn.getAttribute('data-id')));
      window.location.href = '/';
      return;
    }
    const editBtn = e.target.closest('.project-edit-btn');
    if (editBtn) {
      const id = Number(editBtn.getAttribute('data-id'));
      const row = cachedProjects.find(x => Number(x.id) === id);
      openStructureEditor({
        action: 'edit',
        entity: 'project',
        projectId: id,
        data: { name: row ? row.name : '' },
      });
      return;
    }
    const delBtn = e.target.closest('.project-del-btn');
    if (delBtn) {
      const id = Number(delBtn.getAttribute('data-id'));
      const row = cachedProjects.find(x => Number(x.id) === id);
      openStructureEditor({
        action: 'delete',
        entity: 'project',
        projectId: id,
        data: { name: row ? row.name : '' },
      });
      return;
    }
    const projectRow = e.target.closest('tr.project-row');
    if (projectRow && !e.target.closest('button')) {
      const id = Number(projectRow.getAttribute('data-project-id'));
      const row = cachedProjects.find(x => Number(x.id) === id);
      openStructurePanel(id, row ? row.name : 'Projet');
    }
  });
}

function renderProjects(projects) {
  cachedProjects = projects;
  const loading = document.getElementById('projects-loading');
  const empty = document.getElementById('projects-empty');
  const table = document.getElementById('projects-table');
  const tbody = document.getElementById('projects-tbody');

  loading.style.display = 'none';

  if (!projects.length) {
    empty.style.display = 'block';
    table.style.display = 'none';
    return;
  }

  empty.style.display = 'none';
  table.style.display = 'table';

  const cur = localStorage.getItem(PROJECT_STORAGE_KEY);

  tbody.innerHTML = projects
    .map(p => {
      const isCur = cur && String(p.id) === String(cur);
      return `
      <tr class="project-row" data-project-id="${p.id}" style="cursor:pointer">
        <td><strong>${escapeHtml(p.name)}</strong>${isCur ? ' <span style="color:var(--accent);font-size:11px">· ouvert</span>' : ''}</td>
        <td>${escapeHtml(p.status || '—')}</td>
        <td style="text-align:center;font-weight:700;color:var(--accent2)">${p.overall_progress ?? 0}%</td>
        <td style="text-align:center">${p.tasks_count ?? 0}</td>
        <td style="text-align:right">
          <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">
          <button type="button" class="btn btn-secondary btn-sm project-edit-btn" data-id="${p.id}">Modifier</button>
          <button type="button" class="btn btn-secondary btn-sm project-del-btn" data-id="${p.id}" style="color:var(--danger);border-color:rgba(192,26,26,.35)">Supprimer</button>
          <button type="button" class="btn btn-primary btn-sm open-project-btn" data-id="${p.id}">Chantier</button>
          </div>
        </td>
      </tr>`;
    })
    .join('');
}

async function loadStructureTree() {
  if (!structureProjectId) return;
  const loading = document.getElementById('structure-loading');
  const empty = document.getElementById('structure-empty');
  const stats = document.getElementById('structure-stats');
  const tree = document.getElementById('structure-tree');

  loading.style.display = 'block';
  empty.style.display = 'none';
  if (stats) stats.style.display = 'none';
  tree.innerHTML = '';

  try {
    const data = await apiFetch(`/projects/${structureProjectId}/phases`, {
      projectContextId: structureProjectId,
    });
    const phases = data.phases || [];
    const meta = data.meta || {};
    loading.style.display = 'none';

    if (!phases.length) {
      empty.style.display = 'block';
      tree.innerHTML = '';
      return;
    }

    empty.style.display = 'none';
    if (stats) {
      const phaseCount = meta.phases_count ?? phases.length;
      const subCount =
        meta.sub_phases_count ??
        phases.reduce((sum, ph) => sum + (ph.sub_phases ? ph.sub_phases.length : 0), 0);
      const taskCount =
        meta.tasks_count ??
        phases.reduce(
          (sum, ph) =>
            sum +
            (ph.sub_phases || []).reduce((subSum, sp) => subSum + (sp.tasks ? sp.tasks.length : 0), 0),
          0
        );
      stats.textContent = `${phaseCount} phases · ${subCount} sous-phases · ${taskCount} activités`;
      stats.style.display = 'block';
    }
    tree.innerHTML = phases
      .map(
        ph => `
      <div class="structure-phase-block" style="border-bottom:1px solid var(--border);padding:16px 0">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
          <strong style="font-size:15px;color:var(--accent2)">${escapeHtml(ph.name)}</strong>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <button type="button" class="btn btn-secondary btn-sm phase-edit-btn" data-phase-id="${ph.id}" data-name="${escapeAttr(ph.name)}">Modifier</button>
            <button type="button" class="btn btn-secondary btn-sm subphase-add-btn" data-phase-id="${ph.id}">+ Sous-phase</button>
            <button type="button" class="btn btn-secondary btn-sm phase-del-btn" data-phase-id="${ph.id}" style="color:var(--danger);border-color:rgba(192,26,26,.35)">Supprimer phase</button>
          </div>
        </div>
        <ul style="margin:12px 0 0 0;padding:0 0 0 18px;list-style:none">
          ${(ph.sub_phases || [])
            .map(sp => {
              const tasks = sp.tasks || [];
              const taskRows = tasks.length
                ? tasks
                    .map(
                      t => `
            <li style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;padding:6px 0;font-size:13px;border-bottom:1px dotted var(--border2)">
              <div style="min-width:0">
                <div>${escapeHtml(t.activity)}</div>
                <div style="font-size:11px;color:var(--muted);margin-top:2px">${formatTaskScheduleLabel(t.start_day, t.duration_days)}</div>
              </div>
              <div style="display:flex;gap:6px;flex-shrink:0">
              <button type="button" class="btn btn-secondary btn-sm task-edit-btn" data-task-id="${t.id}" data-activity="${escapeAttr(t.activity)}" data-start-day="${t.start_day ?? 1}" data-duration-days="${t.duration_days ?? 1}" style="font-size:10px">Modifier</button>
              <button type="button" class="btn btn-secondary btn-sm task-del-btn" data-task-id="${t.id}" style="font-size:10px;color:var(--danger)">Supprimer</button>
              </div>
            </li>`
                    )
                    .join('')
                : '<li style="color:var(--muted);font-size:12px;padding:6px 0">Aucune activité — utilisez « + Activité ».</li>';
              return `
            <li style="padding:10px 0;border-bottom:1px dashed var(--border2)">
              <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
                <span style="font-weight:600">${escapeHtml(sp.name)}</span>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <button type="button" class="btn btn-secondary btn-sm subphase-edit-btn" data-sub-id="${sp.id}" data-name="${escapeAttr(sp.name)}">Modifier</button>
                  <button type="button" class="btn btn-secondary btn-sm task-add-btn" data-sub-id="${sp.id}">+ Activité</button>
                  <button type="button" class="btn btn-secondary btn-sm subphase-del-btn" data-sub-id="${sp.id}" style="font-size:10px;color:var(--danger);flex-shrink:0">Retirer</button>
                </div>
              </div>
              <ul style="margin:10px 0 0 14px;padding:0 0 0 12px;list-style:none;border-left:2px solid var(--border2)">${taskRows}</ul>
            </li>`;
            })
            .join('') ||
            '<li style="color:var(--muted);font-size:13px;padding:8px 0">Aucune sous-phase — utilisez « + Sous-phase ».</li>'}
        </ul>
      </div>`
      )
      .join('');
  } catch (e) {
    loading.style.display = 'none';
    empty.style.display = 'block';
    empty.textContent = e.message || 'Erreur de chargement.';
    toast(e.message || 'Erreur', 'err');
  }
}

function openStructurePanel(projectId, name) {
  closeStructureEditor();
  structureProjectId = projectId;
  structureProjectName = name || 'Projet';
  const panel = document.getElementById('structure-panel');
  const title = document.getElementById('structure-panel-title');
  panel.style.display = 'block';
  title.textContent = 'Structure — ' + structureProjectName;
  panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  loadStructureTree();
}

function closeStructurePanel() {
  structureProjectId = null;
  structureProjectName = '';
  document.getElementById('structure-panel').style.display = 'none';
  closeStructureEditor();
}

document.addEventListener('DOMContentLoaded', async () => {
  const raw = localStorage.getItem(USER_KEY);
  if (raw) {
    try {
      const u = JSON.parse(raw);
      const av = document.getElementById('user-av');
      const nm = document.getElementById('user-nm');
      if (av) av.textContent = u.avatar_initials || u.name.charAt(0);
      if (nm) nm.textContent = u.name;
    } catch (_) {}
  } else {
    try {
      const { user } = await apiFetch('/whoami');
      const av = document.getElementById('user-av');
      const nm = document.getElementById('user-nm');
      if (av) av.textContent = user.avatar_initials || user.name.charAt(0);
      if (nm) nm.textContent = user.name;
    } catch (_) {
      const nm = document.getElementById('user-nm');
      if (nm) nm.textContent = 'Invité';
    }
  }

  const tbody = document.getElementById('projects-tbody');
  if (tbody) bindProjectTableOnce(tbody);

  document.getElementById('structure-close')?.addEventListener('click', closeStructurePanel);

  document.getElementById('structure-editor-form')?.addEventListener('submit', submitStructureEditor);
  document.getElementById('structure-editor-close')?.addEventListener('click', closeStructureEditor);
  document.getElementById('structure-editor-form-cancel')?.addEventListener('click', closeStructureEditor);
  document.getElementById('structure-editor-delete-cancel')?.addEventListener('click', closeStructureEditor);
  document.getElementById('structure-editor-delete-confirm')?.addEventListener('click', confirmStructureEditorDelete);

  document.getElementById('structure-edit-project')?.addEventListener('click', () => {
    if (!structureProjectId) return;
    openStructureEditor({
      action: 'edit',
      entity: 'project',
      projectId: structureProjectId,
      data: { name: structureProjectName },
    });
  });

  document.getElementById('structure-add-phase')?.addEventListener('click', () => {
    if (!structureProjectId) return;
    openStructureEditor({
      action: 'create',
      entity: 'phase',
      projectId: structureProjectId,
      data: { name: '' },
    });
  });

  document.getElementById('structure-tree')?.addEventListener('click', e => {
    const phaseEdit = e.target.closest('.phase-edit-btn');
    if (phaseEdit && structureProjectId) {
      openStructureEditor({
        action: 'edit',
        entity: 'phase',
        projectId: structureProjectId,
        phaseId: Number(phaseEdit.getAttribute('data-phase-id')),
        data: { name: phaseEdit.getAttribute('data-name') || '' },
      });
      return;
    }

    const subEdit = e.target.closest('.subphase-edit-btn');
    if (subEdit && structureProjectId) {
      openStructureEditor({
        action: 'edit',
        entity: 'subphase',
        projectId: structureProjectId,
        subPhaseId: Number(subEdit.getAttribute('data-sub-id')),
        data: { name: subEdit.getAttribute('data-name') || '' },
      });
      return;
    }

    const subAdd = e.target.closest('.subphase-add-btn');
    if (subAdd && structureProjectId) {
      openStructureEditor({
        action: 'create',
        entity: 'subphase',
        projectId: structureProjectId,
        phaseId: Number(subAdd.getAttribute('data-phase-id')),
        data: { name: '' },
      });
      return;
    }

    const taskAdd = e.target.closest('.task-add-btn');
    if (taskAdd && structureProjectId) {
      openStructureEditor({
        action: 'create',
        entity: 'task',
        projectId: structureProjectId,
        subPhaseId: Number(taskAdd.getAttribute('data-sub-id')),
        data: { activity: '', start_day: 1, duration_days: 1 },
      });
      return;
    }

    const taskEdit = e.target.closest('.task-edit-btn');
    if (taskEdit && structureProjectId) {
      openStructureEditor({
        action: 'edit',
        entity: 'task',
        projectId: structureProjectId,
        taskId: Number(taskEdit.getAttribute('data-task-id')),
        data: {
          activity: taskEdit.getAttribute('data-activity') || '',
          start_day: Number(taskEdit.getAttribute('data-start-day') || 1),
          duration_days: Number(taskEdit.getAttribute('data-duration-days') || 1),
        },
      });
      return;
    }

    const taskDel = e.target.closest('.task-del-btn');
    if (taskDel && structureProjectId) {
      openStructureEditor({
        action: 'delete',
        entity: 'task',
        projectId: structureProjectId,
        taskId: Number(taskDel.getAttribute('data-task-id')),
      });
      return;
    }

    const phaseDel = e.target.closest('.phase-del-btn');
    if (phaseDel && structureProjectId) {
      openStructureEditor({
        action: 'delete',
        entity: 'phase',
        projectId: structureProjectId,
        phaseId: Number(phaseDel.getAttribute('data-phase-id')),
      });
      return;
    }

    const subDel = e.target.closest('.subphase-del-btn');
    if (subDel && structureProjectId) {
      openStructureEditor({
        action: 'delete',
        entity: 'subphase',
        projectId: structureProjectId,
        subPhaseId: Number(subDel.getAttribute('data-sub-id')),
      });
    }
  });

  const btnNew = document.getElementById('btn-new-project');
  if (btnNew) {
    btnNew.addEventListener('click', () => {
      openStructureEditor({
        action: 'create',
        entity: 'project',
        data: { name: '' },
      });
    });
  }

  try {
    const data = await apiFetch('/projects');
    renderProjects(data.projects || []);
  } catch (e) {
    document.getElementById('projects-loading').style.display = 'none';
    document.getElementById('projects-empty').style.display = 'block';
    document.getElementById('projects-empty').textContent =
      e.message || 'Impossible de charger les projets (vérifiez que MySQL tourne et que php artisan migrate --seed a été exécuté).';
    toast(e.message || 'Erreur chargement', 'err');
  }
});
