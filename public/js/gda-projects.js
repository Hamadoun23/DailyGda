/**
 * Page Projets — liste, création, phases & sous-phases
 */
const API_BASE = window.GDA_API_BASE || window.location.origin + '/api';
const TOKEN_KEY = 'gda_token';
const USER_KEY = 'gda_user';
const PROJECT_STORAGE_KEY = 'gda_project_id';

function readXsrfFromCookie() {
  const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
  return m ? decodeURIComponent(m[1]) : '';
}

function readMetaCsrf() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

/** Laravel : X-XSRF-TOKEN = cookie XSRF-TOKEN ; sinon X-CSRF-TOKEN = meta. */
function applyCsrfHeaders(headers) {
  const fromCookie = readXsrfFromCookie();
  const fromMeta = readMetaCsrf();
  if (fromCookie) {
    headers['X-XSRF-TOKEN'] = fromCookie;
  } else if (fromMeta) {
    headers['X-CSRF-TOKEN'] = fromMeta;
  }
}

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
 * @param {RequestInit & { projectContextId?: string|number|null, skipProjectHeader?: boolean }} [options]
 */
async function apiFetch(path, options = {}) {
  const opts = { ...options };
  const ctxProject = opts.projectContextId;
  const skipProjectHeader = !!opts.skipProjectHeader;
  delete opts.projectContextId;
  delete opts.skipProjectHeader;

  const headers = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(opts.body !== undefined && !(opts.body instanceof FormData)
      ? { 'Content-Type': 'application/json' }
      : {}),
    ...(opts.headers || {}),
  };
  applyCsrfHeaders(headers);

  const token = localStorage.getItem(TOKEN_KEY);
  if (token) headers.Authorization = 'Bearer ' + token;

  if (!skipProjectHeader) {
    const pid =
      ctxProject !== undefined && ctxProject !== null && ctxProject !== ''
        ? String(ctxProject)
        : localStorage.getItem(PROJECT_STORAGE_KEY);
    if (pid) headers['X-Project-Id'] = pid;
  }

  if (typeof gdaUiLang === 'function') {
    headers['X-GDA-Ui-Lang'] = gdaUiLang();
  }

  const res = await fetch(API_BASE + path, { ...opts, headers, credentials: 'include' });
  if (res.status === 401) {
    clearAuth();
    if (window.GDA_AUTH_REQUIRED) showLogin();
    throw new Error(tr('projects.errUnauthorized'));
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

function gripHandle() {
  const label = tr('projects.dragHandle');
  return `<span class="gda-drag-handle" draggable="true" role="button" aria-label="${escapeAttr(label)}" title="${escapeAttr(label)}"><i></i><i></i><i></i><i></i><i></i><i></i></span>`;
}

function partnerHiddenBadge() {
  return `<span class="partner-hidden-badge">${escapeHtml(tr('projects.partnerHidden'))}</span>`;
}

function partnerVisibilityBtn(entity, id, hidden, inherited) {
  if (inherited) {
    return `<span class="partner-hidden-badge partner-hidden-badge--inherited" title="${escapeAttr(tr('projects.partnerHiddenInherited'))}">${escapeHtml(tr('projects.partnerHiddenInherited'))}</span>`;
  }
  const label = hidden ? tr('projects.partnerShow') : tr('projects.partnerHide');
  const cls = hidden ? 'partner-show-btn' : 'partner-hide-btn';
  return `<button type="button" class="btn btn-secondary btn-sm ${cls}" data-entity="${escapeAttr(entity)}" data-id="${id}" data-hidden="${hidden ? '1' : '0'}">${escapeHtml(label)}</button>`;
}

async function togglePartnerVisibility(entity, id, hide) {
  const paths = {
    phase: `/phases/${id}`,
    subphase: `/sub-phases/${id}`,
    task: `/tasks/${id}`,
  };
  const path = paths[entity];
  if (!path || !structureProjectId) return;
  await apiFetch(path, {
    method: 'PUT',
    body: JSON.stringify({ hidden_from_partner: hide }),
    projectContextId: structureProjectId,
  });
  toast(tr('projects.toast.partnerVisibility'), 'ok');
  await loadStructureTree();
}

/**
 * Liste réordonnable par glisser-déposer (poignée uniquement).
 * @param {HTMLElement} container
 * @param {{ itemSelector: string, idAttr: string, onReorder: (ids: number[]) => Promise<void>, onError?: () => void }} opts
 */
function initSortableList(container, opts) {
  if (!container || container.dataset.sortBound === '1') return;
  container.dataset.sortBound = '1';

  let dragId = null;

  container.addEventListener('dragstart', e => {
    const handle = e.target.closest('.gda-drag-handle');
    if (!handle) return;
    const item = handle.closest(opts.itemSelector);
    if (!item || !container.contains(item)) return;
    e.stopPropagation();
    dragId = item.getAttribute(opts.idAttr);
    item.classList.add('gda-sort-dragging');
    if (e.dataTransfer) {
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', dragId || '');
    }
  });

  container.addEventListener('dragend', () => {
    container.querySelectorAll(opts.itemSelector).forEach(el => {
      el.classList.remove('gda-sort-dragging', 'gda-sort-drop-target');
    });
    dragId = null;
  });

  container.addEventListener('dragover', e => {
    if (!dragId) return;
    const over = e.target.closest(opts.itemSelector);
    if (!over || !container.contains(over)) return;
    e.preventDefault();
    if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
    container.querySelectorAll(opts.itemSelector).forEach(el => {
      el.classList.toggle('gda-sort-drop-target', el === over && el.getAttribute(opts.idAttr) !== dragId);
    });
  });

  container.addEventListener('drop', async e => {
    e.preventDefault();
    const fromId = dragId || (e.dataTransfer ? e.dataTransfer.getData('text/plain') : '');
    const target = e.target.closest(opts.itemSelector);
    if (!fromId || !target) return;
    const toId = target.getAttribute(opts.idAttr);
    if (fromId === toId) return;

    const items = [...container.querySelectorAll(opts.itemSelector)];
    const fromEl = items.find(el => el.getAttribute(opts.idAttr) === fromId);
    const toEl = items.find(el => el.getAttribute(opts.idAttr) === toId);
    if (!fromEl || !toEl) return;

    const fromIdx = items.indexOf(fromEl);
    const toIdx = items.indexOf(toEl);
    if (fromIdx < toIdx) toEl.after(fromEl);
    else toEl.before(fromEl);

    container.querySelectorAll('.gda-sort-drop-target').forEach(el => el.classList.remove('gda-sort-drop-target'));

    const order = [...container.querySelectorAll(opts.itemSelector)].map(el => Number(el.getAttribute(opts.idAttr)));
    try {
      await opts.onReorder(order);
      toast(tr('projects.toast.orderSaved'), 'ok');
    } catch (err) {
      toast(err.message || tr('projects.errGeneric'), 'err');
      if (typeof opts.onError === 'function') opts.onError();
    }
  });

  container.addEventListener('click', e => {
    if (e.target.closest('.gda-drag-handle')) e.stopPropagation();
  });
}

function bindProjectsTableSort(tbody) {
  delete tbody.dataset.sortBound;
  initSortableList(tbody, {
    itemSelector: 'tr.sortable-project-row',
    idAttr: 'data-project-id',
    onReorder: async order => {
      await apiFetch('/projects/reorder', {
        method: 'POST',
        body: JSON.stringify({ order }),
        skipProjectHeader: true,
      });
      const byId = new Map(cachedProjects.map(p => [Number(p.id), p]));
      cachedProjects = order.map(id => byId.get(id)).filter(Boolean);
    },
    onError: () => refreshProjectList(),
  });
}

function bindStructureTreeSort() {
  const tree = document.getElementById('structure-tree');
  if (!tree || !structureProjectId) return;
  delete tree.dataset.sortBound;

  initSortableList(tree, {
    itemSelector: '.sortable-phase',
    idAttr: 'data-phase-id',
    onReorder: async order => {
      await apiFetch(`/projects/${structureProjectId}/phases/reorder`, {
        method: 'POST',
        body: JSON.stringify({ order }),
        projectContextId: structureProjectId,
      });
    },
    onError: () => loadStructureTree(),
  });

  tree.querySelectorAll('.structure-sort-sub').forEach(list => {
    const phaseId = list.closest('.sortable-phase')?.getAttribute('data-phase-id');
    if (!phaseId) return;
    delete list.dataset.sortBound;
    initSortableList(list, {
      itemSelector: '.sortable-subphase',
      idAttr: 'data-sub-id',
      onReorder: async order => {
        await apiFetch(`/phases/${phaseId}/sub-phases/reorder`, {
          method: 'POST',
          body: JSON.stringify({ order }),
          projectContextId: structureProjectId,
        });
      },
      onError: () => loadStructureTree(),
    });
  });
}

function logoutOutsideClick(e) {
  const wrap = document.querySelector('.header-user-wrap');
  if (wrap && wrap.contains(e.target)) return;
  closeLogoutPopover();
}

function logoutEscape(e) {
  if (e.key === 'Escape') {
    e.preventDefault();
    closeLogoutPopover();
  }
}

function toggleLogoutPopover(ev) {
  ev.stopPropagation();
  const pop = document.getElementById('logout-popover');
  const btn = document.getElementById('user-pill-btn');
  if (!pop || !btn) return;
  const open = pop.classList.toggle('is-open');
  btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  pop.setAttribute('aria-hidden', open ? 'false' : 'true');
  if (open) {
    queueMicrotask(() => {
      document.addEventListener('click', logoutOutsideClick, true);
      document.addEventListener('keydown', logoutEscape, true);
    });
  } else {
    document.removeEventListener('click', logoutOutsideClick, true);
    document.removeEventListener('keydown', logoutEscape, true);
  }
}

function closeLogoutPopover() {
  const pop = document.getElementById('logout-popover');
  const btn = document.getElementById('user-pill-btn');
  pop?.classList.remove('is-open');
  pop?.setAttribute('aria-hidden', 'true');
  btn?.setAttribute('aria-expanded', 'false');
  document.removeEventListener('click', logoutOutsideClick, true);
  document.removeEventListener('keydown', logoutEscape, true);
}

async function performLogout() {
  closeLogoutPopover();
  const csrf = readMetaCsrf();
  const base = (window.GDA_APP_URL || window.location.origin + '/').replace(/\/?$/, '/');
  try {
    const hdr = {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
      Accept: 'text/html,application/json',
    };
    applyCsrfHeaders(hdr);
    await fetch(base + 'logout', {
      method: 'POST',
      credentials: 'include',
      headers: hdr,
      body: new URLSearchParams({ _token: csrf || '' }),
    });
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
  const label = parseDateInput(startDate).toLocaleDateString(gdaDateLocale());
  return trTpl('projects.schedule', { date: label, days: durationDays ?? 1 });
}

function closeStructureEditor() {
  structureEditorState = null;
  const panel = document.getElementById('structure-editor-panel');
  if (panel) panel.style.display = 'none';
}

function editorTitle(config) {
  const k = `projects.editor.${config.entity}_${config.action}`;
  if (!(k in (window.GDA_I18N?.fr || {}))) return tr('projects.editor.defaultTitle');
  return tr(k);
}

function editorHint(config) {
  const k = `projects.hint.${config.entity}_${config.action}`;
  return tr(k) || '';
}

function editorDeleteMessage(config) {
  if (config.entity === 'project' && config.action === 'delete') {
    const name = config.data?.name || tr('projects.thisProject');
    return trTpl('projects.delProject', { name });
  }

  const map = {
    'task:delete': 'projects.delTask',
    'phase:delete': 'projects.delPhase',
    'subphase:delete': 'projects.delSubphase',
  };
  const key = map[`${config.entity}:${config.action}`];
  return key ? tr(key) : tr('projects.delConfirmDefault');
}

function renderStructureEditorFields(config) {
  const data = config.data || {};
  if (config.entity === 'task') {
    const projectId = config.projectId ?? structureProjectId;
    const startDate = startDayToDateStr(getProjectStartDate(projectId), data.start_day ?? 1);
    return `
      <div class="form-group" style="margin-bottom:14px">
        <label class="form-label" for="structure-editor-activity">${escapeHtml(tr('projects.lblActivity'))}</label>
        <input type="text" id="structure-editor-activity" value="${escapeAttr(data.activity || '')}" required maxlength="500">
      </div>
      <div class="form-row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
        <div class="form-group">
          <label class="form-label" for="structure-editor-start-date">${escapeHtml(tr('projects.lblStartDate'))}</label>
          <input type="date" id="structure-editor-start-date" value="${escapeAttr(startDate)}" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="structure-editor-duration-days">${escapeHtml(tr('projects.lblDurationDays'))}</label>
          <input type="number" id="structure-editor-duration-days" min="1" step="1" value="${escapeAttr(String(data.duration_days ?? 1))}" required>
        </div>
      </div>`;
  }

  const label =
    config.entity === 'project'
      ? tr('projects.lblProjectName')
      : config.entity === 'phase'
        ? tr('projects.lblPhaseName')
        : tr('projects.lblSubphaseName');

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
    if (!activity) throw new Error(tr('projects.errActivityRequired'));
    if (!startDate) throw new Error(tr('projects.errStartRequired'));
    if (!Number.isInteger(startDay) || startDay < 1) throw new Error(tr('projects.errStartInvalid'));
    if (!Number.isInteger(durationDays) || durationDays < 1) throw new Error(tr('projects.errDurationInvalid'));
    return { activity, start_day: startDay, duration_days: durationDays };
  }

  const name = document.getElementById('structure-editor-name')?.value.trim() || '';
  if (!name) throw new Error(tr('projects.errNameRequired'));
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
        toast(tr('projects.toast.projectCreated'), 'ok');
        await refreshProjectList();
      } else {
        const projectId = structureEditorState.projectId;
        await apiFetch(`/projects/${projectId}`, {
          method: 'PUT',
          body: JSON.stringify({ name: values.name }),
          skipProjectHeader: true,
        });
        toast(tr('projects.toast.projectUpdated'), 'ok');
        if (structureProjectId === projectId) {
          structureProjectName = values.name;
          const title = document.getElementById('structure-panel-title');
          if (title) title.textContent = structureProjectName;
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
        toast(tr('projects.toast.phaseAdded'), 'ok');
        await loadStructureTree();
        await refreshProjectList();
      } else {
        await apiFetch(`/phases/${structureEditorState.phaseId}`, {
          method: 'PUT',
          body: JSON.stringify({ name: values.name }),
          projectContextId: projectId,
        });
        toast(tr('projects.toast.phaseUpdated'), 'ok');
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
        toast(tr('projects.toast.subAdded'), 'ok');
        await loadStructureTree();
      } else {
        await apiFetch(`/sub-phases/${structureEditorState.subPhaseId}`, {
          method: 'PUT',
          body: JSON.stringify({ name: values.name }),
          projectContextId: projectId,
        });
        toast(tr('projects.toast.subUpdated'), 'ok');
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
        toast(tr('projects.toast.taskAdded'), 'ok');
        await loadStructureTree();
        await refreshProjectList();
      } else {
        await apiFetch(`/tasks/${structureEditorState.taskId}`, {
          method: 'PUT',
          body: JSON.stringify(values),
          projectContextId: projectId,
        });
        toast(tr('projects.toast.taskUpdated'), 'ok');
        await loadStructureTree();
        await refreshProjectList();
      }
    }

    closeStructureEditor();
  } catch (err) {
    toast(err.message || tr('projects.errGeneric'), 'err');
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
      toast(tr('projects.toast.taskDeleted'), 'ok');
      await loadStructureTree();
      await refreshProjectList();
    } else if (structureEditorState.entity === 'phase') {
      await apiFetch(`/phases/${structureEditorState.phaseId}`, {
        method: 'DELETE',
        projectContextId: projectId,
      });
      toast(tr('projects.toast.phaseDeleted'), 'ok');
      await loadStructureTree();
      await refreshProjectList();
    } else if (structureEditorState.entity === 'subphase') {
      await apiFetch(`/sub-phases/${structureEditorState.subPhaseId}`, {
        method: 'DELETE',
        projectContextId: projectId,
      });
      toast(tr('projects.toast.subDeleted'), 'ok');
      await loadStructureTree();
      await refreshProjectList();
    } else if (structureEditorState.entity === 'project') {
      const projectId = structureEditorState.projectId;
      if (!projectId) {
        throw new Error(tr('projects.errProjectNotFound'));
      }
      await apiFetch(`/projects/${projectId}`, {
        method: 'DELETE',
        skipProjectHeader: true,
      });
      toast(tr('projects.toast.projectDeleted'), 'ok');
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
    toast(err.message || tr('projects.errGeneric'), 'err');
    if (structureEditorState?.entity === 'project') {
      await refreshProjectList();
    }
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
      openStructurePanel(id, row ? row.name : tr('projects.defaultProjectName'));
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
    empty.textContent = tr('projects.empty');
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
      <tr class="project-row sortable-project-row" data-project-id="${p.id}" style="cursor:pointer">
        <td class="tbl-col-drag">${gripHandle()}</td>
        <td><strong>${escapeHtml(p.name)}</strong>${isCur ? ` <span style="color:var(--accent);font-size:11px">${escapeHtml(tr('projects.openBadge'))}</span>` : ''}</td>
        <td>${escapeHtml(p.status || '—')}</td>
        <td style="text-align:center;font-weight:700;color:var(--accent2)">${p.overall_progress ?? 0}%</td>
        <td style="text-align:center">${p.tasks_count ?? 0}</td>
        <td style="text-align:right">
          <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">
          <button type="button" class="btn btn-secondary btn-sm project-edit-btn" data-id="${p.id}">${escapeHtml(tr('projects.btnEdit'))}</button>
          <button type="button" class="btn btn-secondary btn-sm project-del-btn" data-id="${p.id}" style="color:var(--danger);border-color:rgba(192,26,26,.35)">${escapeHtml(tr('projects.btnDelete'))}</button>
          <button type="button" class="btn btn-primary btn-sm open-project-btn" data-id="${p.id}">${escapeHtml(tr('projects.btnChantier'))}</button>
          </div>
        </td>
      </tr>`;
    })
    .join('');

  bindProjectsTableSort(tbody);
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
      empty.textContent = tr('projects.structureEmpty');
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
      stats.textContent = trTpl('projects.stats', {
        phases: phaseCount,
        subs: subCount,
        tasks: taskCount,
      });
      stats.style.display = 'block';
    }
    tree.innerHTML = `<div class="structure-sort-phases">${phases
      .map(
        ph => `
      <div class="structure-phase-block sortable-phase${ph.hidden_from_partner ? ' structure-partner-hidden' : ''}" data-phase-id="${ph.id}" style="border-bottom:1px solid var(--border);padding:16px 0">
        <div class="structure-phase-head structure-sort-row">
          <div class="structure-row-label">
            <strong style="font-size:15px;color:var(--accent2)">${escapeHtml(ph.name)}</strong>
            ${ph.hidden_from_partner ? partnerHiddenBadge() : ''}
          </div>
          <div class="structure-row-grip">${gripHandle()}</div>
          <div class="structure-row-actions">
            ${partnerVisibilityBtn('phase', ph.id, !!ph.hidden_from_partner, false)}
            <button type="button" class="btn btn-secondary btn-sm phase-edit-btn" data-phase-id="${ph.id}" data-name="${escapeAttr(ph.name)}">${escapeHtml(tr('projects.btnEdit'))}</button>
            <button type="button" class="btn btn-secondary btn-sm subphase-add-btn" data-phase-id="${ph.id}">${escapeHtml(tr('projects.subphaseAdd'))}</button>
            <button type="button" class="btn btn-secondary btn-sm phase-del-btn" data-phase-id="${ph.id}" style="color:var(--danger);border-color:rgba(192,26,26,.35)">${escapeHtml(tr('projects.phaseDel'))}</button>
          </div>
        </div>
        <ul class="structure-sort-sub">
          ${(ph.sub_phases || [])
            .map(sp => {
              const tasks = sp.tasks || [];
              const taskRows = tasks.length
                ? tasks
                    .map(
                      t => `
            <li data-task-id="${t.id}" class="${ph.hidden_from_partner || sp.hidden_from_partner || t.hidden_from_partner ? 'structure-partner-hidden' : ''}">
              <div class="structure-task-row">
                <div class="structure-row-label">
                  <div>${escapeHtml(t.activity)}${t.hidden_from_partner && !ph.hidden_from_partner && !sp.hidden_from_partner ? ' ' + partnerHiddenBadge() : ''}</div>
                  <div style="font-size:11px;color:var(--muted);margin-top:2px">${formatTaskScheduleLabel(t.start_day, t.duration_days)}</div>
                </div>
                <div class="structure-row-actions">
                  ${partnerVisibilityBtn('task', t.id, !!t.hidden_from_partner, !!(ph.hidden_from_partner || sp.hidden_from_partner))}
                  <button type="button" class="btn btn-secondary btn-sm task-edit-btn" data-task-id="${t.id}" data-activity="${escapeAttr(t.activity)}" data-start-day="${t.start_day ?? 1}" data-duration-days="${t.duration_days ?? 1}" style="font-size:10px">${escapeHtml(tr('projects.btnEdit'))}</button>
                  <button type="button" class="btn btn-secondary btn-sm task-del-btn" data-task-id="${t.id}" style="font-size:10px;color:var(--danger)">${escapeHtml(tr('projects.btnDelete'))}</button>
                </div>
              </div>
            </li>`
                    )
                    .join('')
                : `<li style="color:var(--muted);font-size:12px;padding:6px 0">${escapeHtml(tr('projects.treeNoTasks'))}</li>`;
              return `
            <li class="sortable-subphase${ph.hidden_from_partner || sp.hidden_from_partner ? ' structure-partner-hidden' : ''}" data-sub-id="${sp.id}">
              <div class="structure-sub-head structure-sort-row">
                <div class="structure-row-label">
                  <span style="font-weight:600">${escapeHtml(sp.name)}</span>
                  ${sp.hidden_from_partner && !ph.hidden_from_partner ? partnerHiddenBadge() : ''}
                </div>
                <div class="structure-row-grip">${gripHandle()}</div>
                <div class="structure-row-actions">
                  ${partnerVisibilityBtn('subphase', sp.id, !!sp.hidden_from_partner, !!ph.hidden_from_partner)}
                  <button type="button" class="btn btn-secondary btn-sm subphase-edit-btn" data-sub-id="${sp.id}" data-name="${escapeAttr(sp.name)}">${escapeHtml(tr('projects.btnEdit'))}</button>
                  <button type="button" class="btn btn-secondary btn-sm task-add-btn" data-sub-id="${sp.id}">${escapeHtml(tr('projects.taskAdd'))}</button>
                  <button type="button" class="btn btn-secondary btn-sm subphase-del-btn" data-sub-id="${sp.id}" style="font-size:10px;color:var(--danger);flex-shrink:0">${escapeHtml(tr('projects.remove'))}</button>
                </div>
              </div>
              <ul class="structure-sort-task">${taskRows}</ul>
            </li>`;
            })
            .join('') ||
            '<li style="color:var(--muted);font-size:13px;padding:8px 0">' + escapeHtml(tr('projects.treeNoSubphases')) + '</li>'}
        </ul>
      </div>`
      )
      .join('')}</div>`;
    bindStructureTreeSort();
  } catch (e) {
    loading.style.display = 'none';
    empty.style.display = 'block';
    empty.textContent = e.message || tr('projects.errLoad');
    toast(e.message || tr('projects.errGeneric'), 'err');
  }
}

function openStructurePanel(projectId, name) {
  closeStructureEditor();
  structureProjectId = projectId;
  structureProjectName = name || tr('projects.defaultProjectName');
  const panel = document.getElementById('structure-panel');
  const title = document.getElementById('structure-panel-title');
  panel.style.display = 'block';
  title.textContent = structureProjectName;
  panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  loadStructureTree();
}

function closeStructurePanel() {
  structureProjectId = null;
  structureProjectName = '';
  document.getElementById('structure-panel').style.display = 'none';
  const title = document.getElementById('structure-panel-title');
  if (title) title.textContent = tr('projects.structureIntro');
  closeStructureEditor();
}

document.addEventListener('DOMContentLoaded', async () => {
  const authRequired = !!window.GDA_AUTH_REQUIRED;
  applyI18nDocument();
  syncUiLangButtons();
  try {
    const { user } = await apiFetch('/whoami');
    const av = document.getElementById('user-av');
    const nm = document.getElementById('user-nm');
    if (av) av.textContent = user.avatar_initials || user.name.charAt(0);
    if (nm) nm.textContent = user.name;
    localStorage.setItem(USER_KEY, JSON.stringify(user));
  } catch (_) {
    if (authRequired) {
      showLogin();
      return;
    }
    const nm = document.getElementById('user-nm');
    if (nm) nm.textContent = tr('projects.guest');
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
    const partnerToggle = e.target.closest('.partner-hide-btn, .partner-show-btn');
    if (partnerToggle && structureProjectId) {
      e.stopPropagation();
      const entity = partnerToggle.getAttribute('data-entity');
      const id = Number(partnerToggle.getAttribute('data-id'));
      const hide = partnerToggle.getAttribute('data-hidden') !== '1';
      void togglePartnerVisibility(entity, id, hide).catch(err => {
        toast(err.message || tr('projects.errGeneric'), 'err');
      });
      return;
    }

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
      e.message || tr('projects.errLoadList');
    toast(e.message || tr('projects.errGeneric'), 'err');
  }
});

window.gdaOnUiLangChange = function gdaProjectsOnUiLangChange() {
  renderProjects(cachedProjects);
  const panel = document.getElementById('structure-panel');
  const titleEl = document.getElementById('structure-panel-title');
  if (titleEl && panel && panel.style.display === 'none') {
    titleEl.textContent = tr('projects.structureIntro');
  } else if (titleEl && structureProjectName) {
    titleEl.textContent = structureProjectName;
  }
  if (structureProjectId) void loadStructureTree();
  const editorPanel = document.getElementById('structure-editor-panel');
  if (structureEditorState && editorPanel && editorPanel.style.display !== 'none') {
    openStructureEditor(structureEditorState);
  }
};
