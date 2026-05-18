/**
 * GD&A Chantier — client API Laravel
 */
const API_BASE = window.GDA_API_BASE || (window.location.origin + '/api');

const TOKEN_KEY = 'gda_token';
const USER_KEY = 'gda_user';
const PROJECT_STORAGE_KEY = 'gda_project_id';

let tasks = [];
let dailyByTaskId = {};
let pendingDaily = {};
let selectedDailyDate = '';
let dashboardData = null;
let dashboardChartInstances = [];
let reportChartInstances = [];
/** Filtre graphique activités : nom de phase, ou `__all__` pour tout afficher. `null` = reprendre la 1re phase au prochain sync. */
let dashboardActivityPhaseFilter = null;
let reportActivityPhaseFilter = null;
let reportActivityFilterProjectId = null;

const GDA_CHART_IDS_DASHBOARD = {
  pie: 'chart-status-pie',
  phase: 'chart-phase-bar',
  sub: 'chart-subphase-bar',
  act: 'chart-activity-bar',
  actWrap: 'dashboard-activity-chart-canvas',
  toolbar: 'dashboard-activity-toolbar',
  empty: 'dashboard-activity-empty',
  phaseFilter: 'dashboard-activity-phase-filter',
};

/** Résolution d'export des graphiques vers le PDF (Chart.js devicePixelRatio). */
const PDF_CHART_EXPORT_DPR = 3;
const PDF_CHART_CAPTURE_WIDTH = 1180;

const GDA_CHART_IDS_REPORT = {
  pie: 'report-chart-status-pie',
  phase: 'report-chart-phase-bar',
  sub: 'report-chart-subphase-bar',
  act: 'report-chart-activity-bar',
  actWrap: 'report-activity-chart-canvas',
  toolbar: 'report-activity-toolbar',
  empty: 'report-activity-empty',
  phaseFilter: 'report-activity-phase-filter',
};
let dashboardActivityFilterProjectId = null;
let projectMeta = null;
let cachedProjects = [];
let currentUser = null;
let clockTimer = null;
let lastReportId = null;
let activityLogsPage = 1;
let activityLogsFiltersBound = false;
let modalOpenProgress = 0;
let modalProgressNotes = [];

const photoStore = { avant: [], pendant: [], apres: [], securite: [], qualite: [] };
const PHOTO_CATEGORIES = Object.keys(photoStore);
let lbImages = [], lbIdx = 0;
let currentPhotoTab = 'avant';
let dailyFilter = 'all';

const PHOTO_COLORS = {
  avant: 'var(--accent2)',
  pendant: 'var(--accent)',
  apres: 'var(--ok)',
  securite: 'var(--warn)',
  qualite: 'var(--danger)',
};

function photoTabLabelFor(cat) {
  const keys = { avant: 'photo.avant', pendant: 'photo.pendant', apres: 'photo.apres', securite: 'photo.securite', qualite: 'photo.qualite' };
  return tr(keys[cat] || 'photo.avant');
}

function photoTabDescFor(cat) {
  return tr(`photo.desc.${cat}`) || '';
}

function normTask(row) {
  return {
    id: row.id,
    phase_id: row.phase_id,
    sub_phase_id: row.sub_phase_id,
    phase: row.phase,
    subphase: row.subphase,
    activity: row.activity,
    startDay: row.start_day,
    duration: row.duration_days,
    progress: row.progress,
    status: row.status,
    status_label: row.status_label,
    status_comment: row.status_comment || null,
    status_comment_display: row.status_comment_display ?? null,
    comments: [],
  };
}

function statusBadgeFromSlug(slug) {
  return { non_demarre: 'badge-nd', en_cours: 'badge-ip', termine: 'badge-ok', annule: 'badge-late' }[slug] || 'badge-nd';
}

function statusLabelFromSlug(slug) {
  const map = { non_demarre: 'status.nd', en_cours: 'status.ip', termine: 'status.ok', annule: 'status.cancel' };
  return tr(map[slug] || slug);
}

/** Commentaire brut (FR en base) — pour édition / envoi API. */
function rawStatusComment(taskId) {
  if (pendingDaily[taskId]?.comment != null && pendingDaily[taskId].comment !== '') {
    return pendingDaily[taskId].comment;
  }
  const row = getDailyRow(taskId);
  if (row?.daily_update && displayStatusSlug(taskId) === 'annule') {
    return row.daily_update.comment ?? '';
  }
  const task = tasks.find(x => x.id === taskId);
  return task?.status_comment || '';
}

/** Libellé affiché (EN : dictionnaire + traduction auto côté API). */
function displayStatusComment(taskId, { raw = false } = {}) {
  if (raw) return rawStatusComment(taskId);
  const row = getDailyRow(taskId);
  if (row?.effective_status_comment) return row.effective_status_comment;
  const task = tasks.find(x => x.id === taskId);
  if (task?.status_comment_display) return task.status_comment_display;
  return rawStatusComment(taskId);
}

function renderStatusCell(taskId, slug) {
  const comment = slug === 'annule' ? displayStatusComment(taskId) : '';
  const label = statusLabelFromSlug(slug);
  const note = comment
    ? `<div class="status-note" style="font-size:11px;color:var(--muted);margin-top:4px;max-width:220px">${escapeHtml(comment)}</div>`
    : '';
  return `<span class="badge ${statusBadgeFromSlug(slug)}">${escapeHtml(label)}</span>${note}`;
}

function syncTaskStatusCommentField() {
  const status = document.getElementById('m-status')?.value;
  const label = document.getElementById('m-comment-label');
  const comment = document.getElementById('m-comment');
  if (!label || !comment) return;
  if (isSiteReadOnly()) {
    label.textContent = tr('modal.commentOpt');
    comment.placeholder = '';
    comment.required = false;
    return;
  }
  const progress = parseInt(document.getElementById('m-progress')?.value || '0', 10);
  const progressAdvancing = progress > modalOpenProgress;
  if (status === 'annule') {
    label.textContent = tr('modal.commentReq');
    comment.placeholder = tr('modal.phReq');
    comment.required = true;
  } else if (progressAdvancing) {
    label.textContent = tr('modal.progressNoteReq');
    comment.placeholder = tr('modal.progressNotePh');
    comment.required = true;
  } else {
    label.textContent = tr('modal.progressNoteOpt');
    comment.placeholder = tr('modal.progressNotePh');
    comment.required = false;
  }
}

function renderProgressNotesHistory(notes) {
  const el = document.getElementById('m-progress-notes-list');
  if (!el) return;
  if (!notes || !notes.length) {
    el.innerHTML = `<div class="progress-notes-empty">${escapeHtml(tr('modal.progressHistoryEmpty'))}</div>`;
    return;
  }
  el.innerHTML = notes
    .map(
      (n) => `<div class="progress-note-item">
        <div class="progress-note-meta">
          <span class="progress-note-pct">${n.previous_progress}% → ${n.progress}%</span>
          <span class="progress-note-date">${escapeHtml(n.created_at || '')}</span>
          ${n.user_name ? `<span class="progress-note-user">${escapeHtml(n.user_name)}</span>` : ''}
        </div>
        <div class="progress-note-body">${escapeHtml(n.body || n.body_raw || '')}</div>
      </div>`,
    )
    .join('');
}

function applyModalReadOnly(readOnly) {
  const panel = document.getElementById('modal-task-panel');
  if (panel) panel.classList.toggle('modal-readonly', readOnly);
  const title = document.getElementById('modal-title');
  if (title) title.textContent = readOnly ? tr('modal.titleDetails') : tr('modal.title');
  ['m-status', 'm-progress', 'm-comment'].forEach((id) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.disabled = readOnly;
    if (el.tagName === 'TEXTAREA') el.readOnly = readOnly;
  });
  const commentWrap = document.getElementById('m-comment-wrap');
  if (commentWrap) commentWrap.style.display = readOnly ? 'none' : '';
}

function deriveStatusFromProgress(p) {
  const v = parseInt(p, 10);
  if (v >= 100) return 'termine';
  if (v > 0) return 'en_cours';
  return 'non_demarre';
}

function isSiteReadOnly() {
  return currentUser && currentUser.role === 'partner';
}

function applyUiRolePermissions() {
  const dis = isSiteReadOnly();
  document.querySelectorAll('[data-requires-submit]').forEach(el => {
    el.style.display = dis ? 'none' : '';
  });
  const batchBtn = document.querySelector('[data-daily-batch]');
  if (batchBtn) batchBtn.disabled = dis;
}

function readXsrfFromCookie() {
  const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
  return m ? decodeURIComponent(m[1]) : '';
}

function readMetaCsrf() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

/** Laravel : X-XSRF-TOKEN = valeur du cookie XSRF-TOKEN (chiffrée) ; sinon X-CSRF-TOKEN = meta (clair). */
function applyCsrfHeaders(headers) {
  const fromCookie = readXsrfFromCookie();
  const fromMeta = readMetaCsrf();
  if (fromCookie) {
    headers['X-XSRF-TOKEN'] = fromCookie;
  } else if (fromMeta) {
    headers['X-CSRF-TOKEN'] = fromMeta;
  }
}

async function exportDashboardExcel() {
  const headers = {
    Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'X-Requested-With': 'XMLHttpRequest',
  };
  if (typeof gdaUiLang === 'function') {
    headers['X-GDA-Ui-Lang'] = gdaUiLang();
  }
  applyCsrfHeaders(headers);
  const token = localStorage.getItem(TOKEN_KEY);
  if (token) headers.Authorization = 'Bearer ' + token;
  const projectId = localStorage.getItem(PROJECT_STORAGE_KEY);
  if (projectId) headers['X-Project-Id'] = projectId;

  try {
    const res = await fetch(API_BASE + '/dashboard/export', {
      method: 'GET',
      headers,
      credentials: 'include',
    });
    if (res.status === 401) {
      clearAuth();
      if (window.GDA_AUTH_REQUIRED) showLogin();
      throw new Error('Non autorisé');
    }
    if (!res.ok) {
      let msg = res.statusText;
      try {
        const j = await res.json();
        msg = j.message || msg;
      } catch (_) {
        try {
          const t = await res.text();
          if (t) msg = t.slice(0, 200);
        } catch (_) {}
      }
      throw new Error(msg || 'Export impossible');
    }
    const cd = res.headers.get('Content-Disposition');
    let filename = 'dashboard-export.xlsx';
    if (cd) {
      const m = /filename\*?=(?:UTF-8''|)([^;\s]+)|filename="([^"]+)"/i.exec(cd);
      const raw = m ? (m[1] || m[2] || '').trim() : '';
      if (raw) {
        try {
          filename = decodeURIComponent(raw.replace(/^"|"$/g, ''));
        } catch (_) {
          filename = raw.replace(/^"|"$/g, '');
        }
      }
    }
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
    toast('Fichier Excel téléchargé', 'ok');
  } catch (e) {
    toast(String(e.message || e), 'err');
  }
}

async function apiFetch(path, options = {}) {
  const headers = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(options.body !== undefined && !(options.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
    ...(options.headers || {}),
  };
  applyCsrfHeaders(headers);

  const token = localStorage.getItem(TOKEN_KEY);
  if (token) headers.Authorization = 'Bearer ' + token;

  const projectId = localStorage.getItem(PROJECT_STORAGE_KEY);
  if (projectId) headers['X-Project-Id'] = projectId;

  if (typeof gdaUiLang === 'function') {
    headers['X-GDA-Ui-Lang'] = gdaUiLang();
  }

  const res = await fetch(API_BASE + path, {
    ...options,
    headers,
    credentials: 'include',
  });
  if (res.status === 401) {
    clearAuth();
    if (window.GDA_AUTH_REQUIRED) showLogin();
    throw new Error('Non autorisé');
  }
  if (!res.ok) {
    let msg = res.statusText;
    try {
      const j = await res.json();
      msg = formatApiErrorMessage(j) || msg;
    } catch (_) {
      if (res.status === 419) msg = 'Session expirée — reconnectez-vous.';
      else if (res.status === 413) msg = 'Fichier trop volumineux (limite serveur web).';
      else if (res.status === 403) msg = 'Accès refusé.';
    }
    throw new Error(msg);
  }
  const ct = res.headers.get('content-type');
  if (ct && ct.includes('application/json')) return res.json();
  return res;
}

function clearAuth() {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
}

function showLogin() {
  window.location.href = window.GDA_LOGIN_URL || '/login';
}

function hideLogin() {}

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
  currentUser = null;
  window.location.href = window.GDA_LOGIN_URL || '/login';
}

async function loadProject() {
  const data = await apiFetch('/project');
  projectMeta = data.project;
  if (projectMeta?.id) {
    localStorage.setItem(PROJECT_STORAGE_KEY, String(projectMeta.id));
    const select = document.getElementById('sidebar-project-select');
    if (select && String(select.value) !== String(projectMeta.id)) {
      select.value = String(projectMeta.id);
    }
  }
  const lbl = document.getElementById('project-label');
  if (lbl) lbl.textContent = (projectMeta.name || '').substring(0, 42);
  const sidebarProjectName = document.getElementById('sidebar-project-name');
  if (sidebarProjectName) sidebarProjectName.textContent = projectMeta.name || '—';
  const rp = document.getElementById('r-project');
  if (rp) rp.value = projectMeta.name || rp.value;
}

function renderSidebarProjectSelect() {
  const select = document.getElementById('sidebar-project-select');
  if (!select) return;

  if (!cachedProjects.length) {
    select.innerHTML = '<option value="">Aucun projet</option>';
    select.disabled = true;
    return;
  }

  select.disabled = false;
  select.innerHTML = cachedProjects.map(project => (
    `<option value="${project.id}">${escapeHtml(project.name)}</option>`
  )).join('');

  const saved = localStorage.getItem(PROJECT_STORAGE_KEY);
  if (saved && cachedProjects.some(project => String(project.id) === saved)) {
    select.value = saved;
  } else {
    select.value = String(cachedProjects[0].id);
    localStorage.setItem(PROJECT_STORAGE_KEY, String(cachedProjects[0].id));
  }
}

async function loadProjectCatalog() {
  const data = await apiFetch('/projects');
  cachedProjects = data.projects || [];
  renderSidebarProjectSelect();
  return cachedProjects;
}

async function switchProject(projectId) {
  if (!projectId) return;
  const current = localStorage.getItem(PROJECT_STORAGE_KEY);
  if (String(current) === String(projectId)) return;

  localStorage.setItem(PROJECT_STORAGE_KEY, String(projectId));
  Object.keys(photoStore).forEach(key => { photoStore[key] = []; });
  pendingDaily = {};
  lastReportId = null;

  await loadProject();
  await loadTasks();
  await refreshDashboard();
  await loadDaily();
  populatePhaseFilters();
  applyTasksSubtitleCount();
  refreshSidebar();
  renderDashboard();
  renderDaily();
  renderAllTasks();
  await loadPhotosCategory(currentPhotoTab);
  renderPhotos();
  applyUiRolePermissions();

  const preview = document.getElementById('report-preview-area');
  const reportPage = document.getElementById('page-report');
  if (preview && reportPage?.classList.contains('active')) {
    void previewReport();
  } else if (preview) {
    preview.innerHTML =
      '<div style="padding:40px;text-align:center;color:var(--muted)">' + escapeHtml(tr('page.report.loading')) + '</div>';
  }

  const project = cachedProjects.find(item => String(item.id) === String(projectId));
  toast(project ? `Projet actif : ${project.name}` : 'Projet changé', 'ok');
}

function bindSidebarProjectSelect() {
  const select = document.getElementById('sidebar-project-select');
  if (!select || select.dataset.bound) return;
  select.dataset.bound = '1';
  select.addEventListener('change', () => {
    void switchProject(select.value);
  });
}

async function loadTasks() {
  const data = await apiFetch('/tasks');
  tasks = data.tasks.map(normTask);
}

async function refreshDashboard() {
  dashboardData = await apiFetch('/dashboard');
}

async function loadDaily() {
  const data = await apiFetch('/daily?date=' + encodeURIComponent(selectedDailyDate || todayStr()));
  dailyByTaskId = {};
  (data.items || []).forEach(it => {
    dailyByTaskId[it.task.id] = it;
  });
}

function todayStr() {
  return new Date().toISOString().split('T')[0];
}

function parseProjectDate(value) {
  const [year, month, day] = String(value).slice(0, 10).split('-').map(Number);
  return new Date(year, month - 1, day);
}

function getChantierProjectStartDate() {
  const raw = projectMeta?.start_date;
  if (raw) return String(raw).slice(0, 10);
  return todayStr();
}

function taskStartDateLabel(startDay) {
  const date = parseProjectDate(getChantierProjectStartDate());
  date.setDate(date.getDate() + Math.max(0, Number(startDay || 1) - 1));
  return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

async function initApp() {
  selectedDailyDate = todayStr();
  const ddi = document.getElementById('daily-date-input');
  if (ddi) ddi.value = selectedDailyDate;

  if (clockTimer) clearInterval(clockTimer);
  clockTimer = setInterval(updateClock, 1000);
  updateClock();

  document.getElementById('r-date').value = todayStr();
  document.getElementById('daily-date-label').textContent =
    tr('daily.datePrefix') +
    ' ' +
    new Date(selectedDailyDate + 'T12:00:00').toLocaleDateString(gdaDateLocale(), { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

  await loadProject();
  await loadTasks();
  await refreshDashboard();
  if (!window.GDA_IS_PARTNER) {
    await loadDaily();
    pendingDaily = {};
  } else {
    pendingDaily = {};
  }

  populatePhaseFilters();
  applyTasksSubtitleCount();
  refreshSidebar();
  renderDashboard();
  if (!window.GDA_IS_PARTNER) {
    renderDaily();
  }
  renderAllTasks();
  if (!window.GDA_IS_PARTNER) {
    await loadPhotosCategory(currentPhotoTab);
    renderPhotos();
  }
  applyUiRolePermissions();
}

function updateClock() {
  const el = document.getElementById('date-live');
  if (!el) return;
  el.textContent =
    new Date().toLocaleString(gdaDateLocale(), { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

async function changeDailyDate(val) {
  if (!val) return;
  selectedDailyDate = val;
  pendingDaily = {};
  document.getElementById('daily-date-label').textContent =
    tr('daily.datePrefix') +
    ' ' +
    new Date(val + 'T12:00:00').toLocaleDateString(gdaDateLocale(), { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  await loadDaily();
  renderDaily();
}

function populatePhaseFilters() {
  const phases = [...new Set(tasks.map(t => t.phase))];
  ['daily-phase-filter', 'task-filter-phase'].forEach(id => {
    const sel = document.getElementById(id);
    if (!sel) return;
    const cur = sel.value;
    sel.innerHTML =
      id === 'daily-phase-filter'
        ? `<option value="">${escapeHtml(tr('page.daily.optAllPhases'))}</option>`
        : `<option value="">${escapeHtml(tr('page.tasks.optAllPhases'))}</option>`;
    phases.forEach(p => {
      const o = document.createElement('option');
      o.value = p;
      o.textContent = p;
      sel.appendChild(o);
    });
    sel.value = phases.includes(cur) ? cur : '';
  });
}

function applyTasksSubtitleCount() {
  const el = document.querySelector('#page-tasks .page-sub');
  if (el) el.textContent = trTpl('page.tasks.sub', { n: tasks.length });
}

function overallProgress() {
  if (!tasks.length) return 0;
  return Math.round(tasks.reduce((s, t) => s + t.progress, 0) / tasks.length);
}

function phaseProgress(phase) {
  const pt = tasks.filter(t => t.phase === phase);
  if (!pt.length) return 0;
  return Math.round(pt.reduce((s, t) => s + t.progress, 0) / pt.length);
}

function refreshSidebar() {
  const pct = dashboardData ? dashboardData.overall_progress : overallProgress();
  document.getElementById('sb-progress').textContent = pct + '%';
  document.getElementById('sb-fill').style.width = pct + '%';

  if (dashboardData) {
    document.getElementById('st-total').textContent = dashboardData.stats.total;
    document.getElementById('st-done').textContent = dashboardData.stats.done;
    document.getElementById('st-prog').textContent = dashboardData.stats.in_progress;
    document.getElementById('st-late').textContent = dashboardData.stats.cancelled ?? dashboardData.stats.blocked ?? 0;
    const ra = dashboardData.recent_activity;
    if (ra && ra.length) {
      document.getElementById('last-update').textContent = ra[0].time || '—';
    }
  } else {
    document.getElementById('st-total').textContent = tasks.length;
  }
}

function goTo(page) {
  if (typeof closeSidebar === 'function') closeSidebar();
  if (window.GDA_IS_PARTNER && (page === 'daily' || page === 'photos')) {
    toast('Cette section n’est pas accessible pour votre profil.', 'err');
    return;
  }
  if (page !== 'dashboard') destroyDashboardCharts();
  if (page !== 'report') destroyReportStatsCharts();
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('page-' + page).classList.add('active');
  document.querySelectorAll('.nav-item').forEach(n => {
    if (n.getAttribute('onclick')?.includes(`'${page}'`)) n.classList.add('active');
  });
  if (page === 'dashboard') renderDashboard();
  if (page === 'daily') {
    loadDaily().then(() => { renderDaily(); });
  }
  if (page === 'tasks') renderAllTasks();
  if (page === 'photos') {
    loadPhotosCategory(currentPhotoTab).then(() => renderPhotos());
  }
  if (page === 'report') {
    void previewReport();
  }
  if (page === 'logs') {
    if (!currentUser?.is_admin) {
      toast(tr('page.logs.loadErr'), 'err');
      goTo('dashboard');
      return;
    }
    void loadActivityLogs(1);
  }
}

function destroyDashboardCharts() {
  while (dashboardChartInstances.length) {
    const c = dashboardChartInstances.pop();
    try {
      c.destroy();
    } catch (_) {}
  }
}

function destroyReportStatsCharts() {
  while (reportChartInstances.length) {
    const c = reportChartInstances.pop();
    try {
      c.destroy();
    } catch (_) {}
  }
}

function syncReportActivityPhaseSelect(root = document) {
  const sel = root.querySelector
    ? root.querySelector(`#${GDA_CHART_IDS_REPORT.phaseFilter}`)
    : document.getElementById(GDA_CHART_IDS_REPORT.phaseFilter);
  if (!sel || !dashboardData) return;
  const phases = dashboardData.progress_by_phase || [];
  const names = phases.map(p => p.phase);
  const allActs = dashboardData.charts?.activities || [];
  const pid = String(localStorage.getItem(PROJECT_STORAGE_KEY) || '');
  if (pid !== reportActivityFilterProjectId) {
    reportActivityFilterProjectId = pid;
    reportActivityPhaseFilter = null;
  }
  if (reportActivityPhaseFilter === null) {
    reportActivityPhaseFilter = names[0] || '__all__';
  }
  if (reportActivityPhaseFilter !== '__all__' && !names.includes(reportActivityPhaseFilter)) {
    reportActivityPhaseFilter = names[0] || '__all__';
  }
  const totalLabel = allActs.length ? ` (${allActs.length})` : '';
  sel.innerHTML =
    names
      .map(n => `<option value="${escapeHtmlAttr(n)}">${escapeHtml(n)}</option>`)
      .join('') + `<option value="__all__">${escapeHtml(tr('dash.filterShowAll') || 'Toutes les phases')}${totalLabel}</option>`;
  if (reportActivityPhaseFilter === '__all__') sel.value = '__all__';
  else if (names.includes(reportActivityPhaseFilter)) sel.value = reportActivityPhaseFilter;
  else sel.value = names[0] || '__all__';
  reportActivityPhaseFilter = sel.value;
}

function onReportActivityPhaseFilterChange(el) {
  reportActivityPhaseFilter = el.value;
  renderReportStatsCharts();
}

function renderReportStatsCharts(opts = {}) {
  destroyReportStatsCharts();
  const root = opts.root || document;
  syncReportActivityPhaseSelect(root);
  renderGdaCharts(GDA_CHART_IDS_REPORT, reportChartInstances, () => reportActivityPhaseFilter, { ...opts, root });
}

function isValidChartDataUrl(url) {
  return typeof url === 'string' && /^data:image\/(png|jpeg);base64,/i.test(url) && url.length > 4000;
}

function reportChartKeysToWait() {
  const keys = ['pie', 'phase'];
  const ch = dashboardData?.charts || {};
  if ((ch.subphases || []).length > 0) keys.push('sub');
  if ((ch.activities || []).length > 0) keys.push('act');
  return keys;
}

function isReportCanvasReady(root, key) {
  const canvas = root.querySelector(`#${GDA_CHART_IDS_REPORT[key]}`);
  return canvas && canvas.width >= 80 && canvas.height >= 80;
}

async function waitForReportChartCanvases(root, maxAttempts = 30) {
  const keys = reportChartKeysToWait();
  const minCharts = keys.length;
  for (let attempt = 0; attempt < maxAttempts; attempt++) {
    const ready = keys.every(key => isReportCanvasReady(root, key));
    if (ready && reportChartInstances.length >= Math.min(2, minCharts)) {
      reportChartInstances.forEach(c => {
        try {
          c.resize();
          c.update('none');
        } catch (_) {}
      });
      await new Promise(r => setTimeout(r, 200));
      return true;
    }
    reportChartInstances.forEach(c => {
      try {
        c.resize();
      } catch (_) {}
    });
    await new Promise(r => setTimeout(r, 120));
  }
  return false;
}

function chartDomRoot(root) {
  const scope = root && root.querySelector ? root : document;
  return id => scope.querySelector(`#${id}`);
}

function renderGdaCharts(ids, instances, getPhaseFilter, opts = {}) {
  if (typeof Chart === 'undefined' || !dashboardData?.charts) return;

  const forPdf = !!opts.forPdf;
  const root = opts.root || document;
  const $id = chartDomRoot(root);
  const prevDpr = Chart.defaults.devicePixelRatio;
  const prevAnim = Chart.defaults.animation;
  if (forPdf) {
    Chart.defaults.devicePixelRatio = PDF_CHART_EXPORT_DPR;
    Chart.defaults.animation = false;
  }

  const ch = dashboardData.charts;
  Chart.defaults.font.family = "'Barlow', sans-serif";
  Chart.defaults.color = '#6b6358';

  const pieEl = $id(ids.pie);
  const barPhaseEl = $id(ids.phase);
  const barSubEl = $id(ids.sub);
  const barActEl = $id(ids.act);
  const actCanvasWrap = $id(ids.actWrap);
  if (!pieEl || !barPhaseEl || !barSubEl) {
    if (forPdf) {
      Chart.defaults.devicePixelRatio = prevDpr;
      Chart.defaults.animation = prevAnim;
    }
    return;
  }

  const sc = ch.status_counts || {};
  const pieDef = [
    ['termine', tr('stat.done'), '#1a7a42'],
    ['en_cours', tr('stat.prog'), '#c8521a'],
    ['non_demarre', tr('page.daily.filterNd'), '#9a9285'],
    ['annule', tr('stat.cancel'), '#c01a1a'],
  ];
  const pieLabels = [];
  const pieData = [];
  const pieColors = [];
  pieDef.forEach(([key, label, color]) => {
    const v = Number(sc[key] || 0);
    if (v > 0) {
      pieLabels.push(label);
      pieData.push(v);
      pieColors.push(color);
    }
  });

  if (pieLabels.length) {
    const pie = new Chart(pieEl, {
      type: 'pie',
      data: {
        labels: pieLabels,
        datasets: [{ data: pieData, backgroundColor: pieColors, borderWidth: 1, borderColor: '#fff' }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10, font: { size: 11 } } },
          tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw} activité(s)` } },
        },
      },
    });
    instances.push(pie);
  }

  const phases = dashboardData.progress_by_phase || [];
  if (phases.length) {
    const bp = new Chart(barPhaseEl, {
      type: 'bar',
      data: {
        labels: phases.map(p => p.phase),
        datasets: [
          {
            label: tr('dash.chartPhase'),
            data: phases.map(p => p.progress),
            backgroundColor: phases.map(p =>
              p.progress === 100 ? '#1a7a42' : p.progress > 0 ? '#c8521a' : '#d5cfc2',
            ),
            borderRadius: 6,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } },
          x: { ticks: { maxRotation: 40, minRotation: 0, autoSkip: true, maxTicksLimit: 14 } },
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx => {
                const p = phases[ctx.dataIndex];
                const n = p.task_count != null ? p.task_count : '—';
                return ` ${p.progress}% · ${n} activité(s)`;
              },
            },
          },
        },
      },
    });
    instances.push(bp);
  }

  const subs = ch.subphases || [];
  if (subs.length) {
    const labels = subs.map(s =>
      String(s.subphase).length > 44 ? `${String(s.subphase).slice(0, 42)}…` : s.subphase,
    );
    const sub = new Chart(barSubEl, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: tr('dash.chartSub'),
            data: subs.map(s => s.avg_progress),
            backgroundColor: subs.map(s =>
              s.avg_progress >= 100 ? '#1a7a42' : s.avg_progress > 0 ? '#c8521a' : '#d5cfc2',
            ),
            borderRadius: 5,
          },
        ],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } },
          y: { ticks: { font: { size: 11 } } },
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              title: items => {
                const i = items[0].dataIndex;
                return `${subs[i].phase} — ${subs[i].subphase}`;
              },
              label: item => {
                const i = item.dataIndex;
                return ` ${item.raw}% · ${subs[i].task_count} activité(s)`;
              },
            },
          },
        },
      },
    });
    instances.push(sub);
  }

  const toolbar = $id(ids.toolbar);
  const emptyEl = $id(ids.empty);
  const allActs = ch.activities || [];
  if (toolbar) toolbar.style.display = allActs.length ? '' : 'none';

  const phaseFilter = (typeof getPhaseFilter === 'function' ? getPhaseFilter() : null) || '__all__';
  const filteredActs =
    phaseFilter === '__all__' ? allActs.slice() : allActs.filter(a => a.phase === phaseFilter);

  if (emptyEl) {
    emptyEl.hidden = true;
    emptyEl.textContent = '';
  }
  if (actCanvasWrap) {
    actCanvasWrap.hidden = false;
    actCanvasWrap.style.height = '200px';
  }

  if (barActEl && allActs.length && filteredActs.length) {
    const acts = filteredActs;
    const fullLabels = acts.map(a => `${a.subphase} — ${a.activity}`);
    const labels = fullLabels.map(s => (String(s).length > 48 ? `${String(s).slice(0, 46)}…` : s));
    const barColor = a => {
      if (a.status === 'annule') return '#c01a1a';
      if (a.progress >= 100 || a.status === 'termine') return '#1a7a42';
      if (a.progress > 0 || a.status === 'en_cours') return '#c8521a';
      return '#d5cfc2';
    };
    if (actCanvasWrap) {
      const h = Math.min(780, Math.max(200, 22 * acts.length + 72));
      actCanvasWrap.style.height = `${h}px`;
    }
    const actChart = new Chart(barActEl, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: tr('dash.chartAct'),
            data: acts.map(a => a.progress),
            backgroundColor: acts.map(barColor),
            borderRadius: 5,
          },
        ],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } },
          y: { ticks: { font: { size: 10 } } },
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              title: items => {
                const i = items[0].dataIndex;
                return `${acts[i].phase} — ${acts[i].subphase} — ${acts[i].activity}`;
              },
              label: item => {
                const i = item.dataIndex;
                const a = acts[i];
                const extra = a.status === 'annule' ? ' · annulée' : '';
                return ` ${item.raw}%${extra}`;
              },
            },
          },
        },
      },
    });
    instances.push(actChart);
  } else if (barActEl && allActs.length && !filteredActs.length) {
    if (emptyEl) {
      emptyEl.hidden = false;
      emptyEl.textContent = tr('dash.activityEmptyPhase');
    }
    if (actCanvasWrap) actCanvasWrap.hidden = true;
  } else if (barActEl && !allActs.length) {
    if (emptyEl) {
      emptyEl.hidden = false;
      emptyEl.textContent = tr('dash.activityEmptyProject');
    }
    if (actCanvasWrap) actCanvasWrap.hidden = true;
  }

  if (forPdf) {
    const tallWrap = barSubEl.closest('.dashboard-chart-canvas--tall');
    if (tallWrap) tallWrap.style.height = '520px';
    const pieWrap = pieEl.parentElement;
    const phaseWrap = barPhaseEl.parentElement;
    if (pieWrap) pieWrap.style.height = '280px';
    if (phaseWrap) phaseWrap.style.height = '280px';
    const acts = ch.activities || [];
    const phaseFilter = (typeof getPhaseFilter === 'function' ? getPhaseFilter() : null) || '__all__';
    const actCount =
      phaseFilter === '__all__' ? acts.length : acts.filter(a => a.phase === phaseFilter).length;
    if (actCanvasWrap && actCount > 0) {
      actCanvasWrap.style.height = `${Math.min(900, Math.max(280, 28 * actCount + 80))}px`;
    }
    instances.forEach(c => {
      try {
        c.resize();
        c.update('none');
      } catch (_) {}
    });
    Chart.defaults.devicePixelRatio = prevDpr;
    Chart.defaults.animation = prevAnim;
  }
}

function syncDashboardActivityPhaseSelect() {
  const sel = document.getElementById('dashboard-activity-phase-filter');
  if (!sel || !dashboardData) return;
  const phases = dashboardData.progress_by_phase || [];
  const names = phases.map(p => p.phase);
  const allActs = dashboardData.charts?.activities || [];
  const pid = String(localStorage.getItem(PROJECT_STORAGE_KEY) || '');
  if (pid !== dashboardActivityFilterProjectId) {
    dashboardActivityFilterProjectId = pid;
    dashboardActivityPhaseFilter = null;
  }
  if (dashboardActivityPhaseFilter === null) {
    dashboardActivityPhaseFilter = names[0] || '__all__';
  }
  if (dashboardActivityPhaseFilter !== '__all__' && !names.includes(dashboardActivityPhaseFilter)) {
    dashboardActivityPhaseFilter = names[0] || '__all__';
  }
  const totalLabel = allActs.length ? ` (${allActs.length})` : '';
  sel.innerHTML =
    names
      .map(n => `<option value="${escapeHtmlAttr(n)}">${escapeHtml(n)}</option>`)
      .join('') + `<option value="__all__">Toutes les phases${totalLabel}</option>`;
  if (dashboardActivityPhaseFilter === '__all__') sel.value = '__all__';
  else if (names.includes(dashboardActivityPhaseFilter)) sel.value = dashboardActivityPhaseFilter;
  else sel.value = names[0] || '__all__';
  dashboardActivityPhaseFilter = sel.value;
}

function onDashboardActivityPhaseFilterChange(el) {
  dashboardActivityPhaseFilter = el.value;
  renderDashboardCharts();
}

function renderDashboardCharts() {
  destroyDashboardCharts();
  renderGdaCharts(GDA_CHART_IDS_DASHBOARD, dashboardChartInstances, () => dashboardActivityPhaseFilter);
}

function renderDashboard() {
  refreshSidebar();
  if (!dashboardData) return;

  syncDashboardActivityPhaseSelect();

  const phases = dashboardData.progress_by_phase || [];
  const container = document.getElementById('phase-progress-list');
  container.innerHTML = phases.map(p => {
    const pct = p.progress;
    const cls = pct === 100 ? 'fill-done' : pct > 0 ? 'fill-low' : 'fill-0';
    return `<div style="display:grid;grid-template-columns:180px 1fr 50px;align-items:center;gap:14px;margin-bottom:10px">
      <div style="font-size:12px;font-weight:600;color:var(--text)">${p.phase}</div>
      <div class="pbar"><div class="pbar-fill ${cls}" style="width:${pct}%"></div></div>
      <div style="font-family:'Barlow Condensed',sans-serif;font-size:16px;font-weight:700;color:${pct === 100 ? 'var(--ok)' : 'var(--accent)'}">${pct}%</div>
    </div>`;
  }).join('');

  const ra = document.getElementById('recent-activity');
  const items = dashboardData.recent_activity || [];
  if (!items.length) {
    ra.innerHTML = '<div style="color:var(--muted);font-size:13px;padding:20px;text-align:center">' + escapeHtml(tr('dash.recentNone')) + '</div>';
  } else {
    ra.innerHTML = items.map(a => `
      <div style="display:flex;align-items:flex-start;gap:14px;padding:10px 0;border-bottom:1px solid var(--bg2)">
        <div style="background:var(--bg2);border-radius:6px;padding:6px 10px;font-family:'Barlow Condensed',sans-serif;font-size:12px;color:var(--muted);white-space:nowrap">${a.time}</div>
        <div>
          <div style="font-weight:600;font-size:13px">${escapeHtml(a.task_name)}</div>
          <div style="font-size:11px;color:var(--muted)">${escapeHtml(a.action)} · <span style="color:var(--accent)">${escapeHtml(a.user)}</span></div>
        </div>
        <div style="margin-left:auto;font-family:'Barlow Condensed',sans-serif;font-size:18px;font-weight:700;color:var(--accent)">${a.progress}%</div>
      </div>`).join('');
  }
  renderDashboardCharts();
}

function escapeHtml(s) {
  if (!s) return '';
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
}

function escapeHtmlAttr(s) {
  if (s == null) return '';
  return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

function getDailyRow(taskId) {
  return dailyByTaskId[taskId];
}

/** Avancement de référence pour la date de saisie (aligné sur la validation API). */
function saveBaselineProgress(taskId) {
  const row = getDailyRow(taskId);
  if (row?.daily_update) {
    return row.daily_update.progress;
  }
  const t = tasks.find(x => x.id === taskId);
  return t ? t.progress : 0;
}

function displayProgress(taskId) {
  if (pendingDaily[taskId] !== undefined) return pendingDaily[taskId].progress;
  const row = getDailyRow(taskId);
  if (row) return row.effective_progress;
  const t = tasks.find(x => x.id === taskId);
  return t ? t.progress : 0;
}

function displayStatusSlug(taskId) {
  if (pendingDaily[taskId]?.status) return pendingDaily[taskId].status;
  const row = getDailyRow(taskId);
  if (row) return row.effective_status;
  const t = tasks.find(x => x.id === taskId);
  return t ? t.status : 'non_demarre';
}

function displayStatusLabel(taskId) {
  return statusLabelFromSlug(displayStatusSlug(taskId));
}

function filterDaily(f) {
  dailyFilter = f;
  renderDaily();
}

function renderDaily() {
  const phaseF = document.getElementById('daily-phase-filter')?.value || '';
  let filtered = tasks.slice();
  if (phaseF) filtered = filtered.filter(t => t.phase === phaseF);
  const slug = (id) => displayStatusSlug(id);
  if (dailyFilter === 'ip') {
    filtered = filtered.filter(t => {
      const s = slug(t.id);
      if (s === 'annule') return false;
      return s === 'en_cours' || displayProgress(t.id) > 0;
    });
  }
  if (dailyFilter === 'nd') filtered = filtered.filter(t => slug(t.id) === 'non_demarre');

  const container = document.getElementById('daily-tasks-list');
  if (!filtered.length) {
    container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted)">' + escapeHtml(tr('page.daily.emptyFilter')) + '</div>';
    return;
  }

  container.innerHTML = filtered.map(t => {
    const prog = displayProgress(t.id);
    const stSlug = displayStatusSlug(t.id);
    const pctClass = prog === 100 ? 'fill-done' : prog > 0 ? 'fill-low' : 'fill-0';
    return `<div class="daily-task-row" id="drow-${t.id}" onclick="openModal(${t.id})">
      <div>
        <div class="task-name">${escapeHtml(t.subphase)} — ${escapeHtml(t.activity)}</div>
        <div class="task-phase">${escapeHtml(t.phase)}</div>
      </div>
      <div>${renderStatusCell(t.id, stSlug)}</div>
      <div class="range-wrap" onclick="event.stopPropagation()">
        <input type="range" min="0" max="100" step="1" value="${prog}"
          data-id="${t.id}"
          oninput="quickUpdate(${t.id},this.value)"
          style="width:100%" ${isSiteReadOnly() ? 'disabled' : ''}>
        <div class="range-val" id="rv-${t.id}">${prog}%</div>
      </div>
      <div>
        <div class="pbar"><div class="pbar-fill ${pctClass}" style="width:${prog}%" id="pb-${t.id}"></div></div>
      </div>
      <div style="text-align:right">
        <button class="btn btn-secondary btn-sm" onclick="event.stopPropagation();openModal(${t.id})">${escapeHtml(isSiteReadOnly() ? tr('tasks.details') : tr('page.daily.detail'))}</button>
      </div>
    </div>`;
  }).join('');
}

function quickUpdate(id, val) {
  if (isSiteReadOnly()) return;
  const v = parseInt(val, 10);
  const prev = pendingDaily[id] ?? { _baseProgress: saveBaselineProgress(id) };
  if (prev._baseProgress === undefined) prev._baseProgress = saveBaselineProgress(id);
  pendingDaily[id] = { ...prev, progress: v, status: deriveStatusFromProgress(v) };
  document.getElementById('rv-' + id).textContent = v + '%';
  const pb = document.getElementById('pb-' + id);
  if (pb) {
    pb.style.width = v + '%';
    pb.className = 'pbar-fill ' + (v === 100 ? 'fill-done' : v > 0 ? 'fill-low' : 'fill-0');
  }
}

async function saveDailyAll() {
  if (isSiteReadOnly()) return;
  const keys = Object.keys(pendingDaily);
  if (!keys.length) {
    toast('Aucune modification à enregistrer', '');
    return;
  }
  const updates = keys.map(tid => {
    const p = pendingDaily[tid];
    const base = p._baseProgress ?? saveBaselineProgress(parseInt(tid, 10));
    const prog = p.progress;
    return {
      task_id: parseInt(tid, 10),
      progress: prog,
      status: p.status || deriveStatusFromProgress(prog),
      comment: p.comment || null,
      progress_note: prog > base ? p.progress_note || p.comment || null : null,
    };
  });
  try {
    await apiFetch('/daily/batch', {
      method: 'POST',
      body: JSON.stringify({ date: selectedDailyDate, updates }),
    });
    pendingDaily = {};
    await Promise.all([loadTasks(), loadDaily(), refreshDashboard()]);
    refreshSidebar();
    renderDaily();
    renderAllTasks();
    renderDashboard();
    toast('Toutes les modifications enregistrées ✓', 'ok');
  } catch (e) {
    toast(e.message || 'Erreur', 'err');
  }
}

async function openModal(id) {
  const t = tasks.find(x => x.id === id);
  if (!t) return;
  const readOnly = isSiteReadOnly();
  modalOpenProgress = saveBaselineProgress(id);
  const shownProgress = displayProgress(id);
  document.getElementById('modal-task-id').value = id;
  document.getElementById('m-phase').value = t.phase;
  document.getElementById('m-sub').value = t.subphase;
  document.getElementById('m-act').value = t.activity;
  document.getElementById('m-status').value = displayStatusSlug(id);
  document.getElementById('m-progress').value = shownProgress;
  document.getElementById('m-pct-lbl').textContent = shownProgress;
  document.getElementById('m-comment').value = readOnly ? '' : displayStatusComment(id, { raw: true });
  const progEl = document.getElementById('m-progress');
  if (progEl) {
    progEl.oninput = () => {
      document.getElementById('m-pct-lbl').textContent = progEl.value;
      syncTaskStatusCommentField();
    };
  }
  applyModalReadOnly(readOnly);
  renderProgressNotesHistory([]);
  try {
    const data = await apiFetch('/tasks/' + id);
    modalProgressNotes = data.progress_notes || [];
    renderProgressNotesHistory(modalProgressNotes);
  } catch (_) {
    modalProgressNotes = [];
  }
  syncTaskStatusCommentField();
  document.getElementById('modal-task').classList.add('open');
}

function closeModal() {
  document.getElementById('modal-task').classList.remove('open');
}

async function saveTask() {
  if (isSiteReadOnly()) return;
  const id = parseInt(document.getElementById('modal-task-id').value, 10);
  const t = tasks.find(x => x.id === id);
  if (!t) return;

  const progress = parseInt(document.getElementById('m-progress').value, 10);
  const status = document.getElementById('m-status').value;
  const noteText = document.getElementById('m-comment').value.trim();

  if (status === 'annule' && !noteText) {
    syncTaskStatusCommentField();
    toast(tr('modal.annuleNoteMissing'), 'err');
    return;
  }
  const progressIncreased = progress > modalOpenProgress;
  if (progressIncreased && !noteText) {
    syncTaskStatusCommentField();
    toast(tr('modal.progressNoteMissing'), 'err');
    return;
  }

  try {
    const row = getDailyRow(id);
    const payload = {
      progress,
      status,
      comment: status === 'annule' ? noteText : null,
      progress_note: progressIncreased ? noteText : null,
    };
    if (row && row.daily_update) {
      await apiFetch('/daily/' + row.daily_update.id, {
        method: 'PUT',
        body: JSON.stringify(payload),
      });
    } else {
      await apiFetch('/daily', {
        method: 'POST',
        body: JSON.stringify({
          task_id: id,
          date: selectedDailyDate,
          ...payload,
        }),
      });
    }

    delete pendingDaily[id];
    await Promise.all([loadTasks(), loadDaily(), refreshDashboard()]);
    refreshSidebar();
    closeModal();
    renderDaily();
    renderAllTasks();
    renderDashboard();
    toast('✓ "' + escapeHtml(t.subphase) + '" mis à jour — ' + progress + '%', 'ok');
  } catch (e) {
    toast(e.message || 'Erreur', 'err');
  }
}

function renderAllTasks() {
  const phaseF = document.getElementById('task-filter-phase')?.value || '';
  const statusF = document.getElementById('task-filter-status')?.value || '';

  let filtered = tasks.slice();
  if (phaseF) filtered = filtered.filter(t => t.phase === phaseF);
  if (statusF) filtered = filtered.filter(t => t.status === statusF);

  const phases = [...new Set(filtered.map(t => t.phase))];
  let html = '';

  phases.forEach(ph => {
    const pt = filtered.filter(t => t.phase === ph);
    const phasePct = phaseProgress(ph);
    html += `<tr class="phase-row">
      <td colspan="7">${escapeHtml(ph)}
        <span style="margin-left:12px;font-weight:400;color:var(--muted)">${phasePct}% ${escapeHtml(tr('tasks.phaseDone'))}</span>
      </td>
    </tr>`;
    pt.forEach(t => {
      const pctCls = t.progress === 100 ? 'fill-done' : t.progress > 50 ? 'fill-mid' : t.progress > 0 ? 'fill-low' : 'fill-0';
      html += `<tr>
        <td><div style="font-weight:600;font-size:12px">${escapeHtml(t.subphase)}</div></td>
        <td style="font-size:12px">${escapeHtml(t.activity)}</td>
        <td style="font-size:12px;color:var(--muted)">${escapeHtml(taskStartDateLabel(t.startDay))}</td>
        <td style="font-size:12px;color:var(--muted)">${t.duration}j</td>
        <td>
          <div class="pbar-wrap">
            <div class="pbar"><div class="pbar-fill ${pctCls}" style="width:${t.progress}%"></div></div>
            <div class="pct-num" style="color:${t.progress === 100 ? 'var(--ok)' : 'var(--accent)'}">${t.progress}%</div>
          </div>
        </td>
        <td>${renderStatusCell(t.id, t.status)}</td>
        <td>
          <button class="btn btn-secondary btn-sm${isSiteReadOnly() ? '' : ' btn-icon'}" onclick="openModal(${t.id})" title="${escapeHtml(isSiteReadOnly() ? tr('tasks.details') : tr('tasks.edit'))}">${isSiteReadOnly() ? escapeHtml(tr('tasks.details')) : '✎'}</button>
        </td>
      </tr>`;
    });
  });

  document.getElementById('tasks-tbody').innerHTML = html;
}

async function loadPhotosCategory(tab) {
  const data = await apiFetch('/photos?category=' + encodeURIComponent(tab));
  photoStore[tab] = (data.photos || []).map(p => ({
    id: p.id,
    path: p.path || '',
    src: photoFileUrl(p.id, p.url),
    date: p.taken_at ? new Date(p.taken_at).toLocaleDateString(gdaDateLocale()) : new Date(p.created_at).toLocaleDateString(gdaDateLocale()),
  }));
}

function gdaApiRoot() {
  return (window.GDA_API_BASE || (window.location.origin + '/api')).replace(/\/$/, '');
}

function gdaAppRoot() {
  return (window.GDA_APP_URL || window.location.origin).replace(/\/$/, '');
}

/** URL d'affichage photo — construite côté navigateur (fiable en prod / sous-dossier). */
function photoFileUrl(photoId, legacyUrl) {
  if (photoId) {
    return gdaApiRoot() + '/photos/' + photoId + '/file';
  }
  return normalizePhotoSrc(legacyUrl || '');
}

function photoPublicFallbackUrl(storagePath) {
  if (!storagePath) return '';
  const encoded = String(storagePath)
    .replace(/^\//, '')
    .split('/')
    .map(part => encodeURIComponent(part))
    .join('/');
  return gdaAppRoot() + '/fichiers/' + encoded;
}

function normalizePhotoSrc(url) {
  if (!url) return '';
  if (url.startsWith('http://') || url.startsWith('https://')) {
    try {
      const parsed = new URL(url);
      if (parsed.hostname !== window.location.hostname) {
        url = parsed.pathname + parsed.search;
      } else {
        return url;
      }
    } catch (_) {
      return url;
    }
  }
  if (url.startsWith('/api/')) {
    return gdaAppRoot() + url;
  }
  if (url.startsWith('/fichiers/')) {
    return gdaAppRoot() + url;
  }
  if (url.startsWith('/')) {
    return gdaAppRoot() + url;
  }
  return url;
}

async function loadAllPhotoCategories() {
  await Promise.all(PHOTO_CATEGORIES.map(tab => loadPhotosCategory(tab)));
}

function switchPhotoTab(tab) {
  currentPhotoTab = tab;
  document.querySelectorAll('.photo-tab').forEach((el, i) => {
    const tabs = ['avant', 'pendant', 'apres', 'securite', 'qualite'];
    el.classList.toggle('active', tabs[i] === tab);
  });
  loadPhotosCategory(tab).then(() => renderPhotos());
}

function renderPhotos() {
  const tab = currentPhotoTab;
  const label = photoTabLabelFor(tab);
  const desc = photoTabDescFor(tab);
  const photos = photoStore[tab];
  const countLabel = photos.length === 1 ? tr('photo.count1') : trTpl('photo.count', { n: photos.length });

  const container = document.getElementById('photo-sections');
  container.innerHTML = `
    <section class="photo-stage">
      <header class="photo-hero">
        <div class="photo-hero-copy">
          <span class="photo-hero-eyebrow">${escapeHtml(tr('photo.heroEyebrow'))}</span>
          <h2 class="photo-hero-title">${escapeHtml(label)}</h2>
          <p class="photo-hero-desc">${escapeHtml(desc)}</p>
        </div>
        <div class="photo-hero-meta">
          <span class="photo-count-badge">${escapeHtml(countLabel)}</span>
        </div>
      </header>

      <div class="photo-upload-card">
        <div class="drop-zone" id="dz-${tab}">
          <div class="dz-icon-ring">📷</div>
          <div class="dz-text">
            <strong>${escapeHtml(tr('photo.dzBold'))}</strong> ${escapeHtml(tr('photo.dzMid'))} <strong>${escapeHtml(label)}</strong>
            <span>${escapeHtml(tr('photo.dzFmt'))}</span>
          </div>
        </div>
        <input type="file" id="ph-input-${tab}" multiple accept="image/*" style="display:none"
          onchange="addPhotos('${tab}', this.files)">
      </div>

      <div class="photo-grid" id="pg-${tab}">
        ${photos.map((p, i) => photoThumb(p, i, tab, label)).join('') || emptyPhotos()}
      </div>
    </section>
  `;

  const dz = document.getElementById('dz-' + tab);
  if (dz) {
    dz.onclick = () => { if (!isSiteReadOnly()) document.getElementById('ph-input-' + tab).click(); };
    dz.style.opacity = isSiteReadOnly() ? '0.55' : '';
    dz.style.pointerEvents = isSiteReadOnly() ? 'none' : '';
  }
  if (dz && !isSiteReadOnly()) {
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag-active'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('drag-active'));
    dz.addEventListener('drop', e => {
      e.preventDefault();
      dz.classList.remove('drag-active');
      addPhotos(tab, e.dataTransfer.files);
    });
  }

  lbImages = photos.map(p => p.src);
}

function photoThumb(p, i, tab, categoryLabel) {
  const src = escapeHtmlAttr(p.src);
  const fallback = p.path ? escapeHtmlAttr(photoPublicFallbackUrl(p.path)) : '';
  const onerr = fallback
    ? ` onerror="if(this.dataset.fallback){this.onerror=null;this.src=this.dataset.fallback;}" data-fallback="${fallback}"`
    : '';
  return `<article class="photo-item" onclick="openLB('${tab}',${i})">
    <div class="photo-frame">
      <img src="${src}" alt="" loading="lazy"${onerr}>
    <div class="photo-date-badge">${escapeHtml(p.date)}</div>
      <div class="photo-overlay">
        <span class="photo-zoom-hint">${escapeHtml(tr('photo.zoom'))}</span>
        <div class="photo-overlay-btns">
          <button class="photo-action-btn del" onclick="event.stopPropagation();removePhoto('${tab}',${i})">${escapeHtml(tr('photo.del'))}</button>
        </div>
      </div>
    </div>
    <div class="photo-caption">
      <span class="photo-caption-label">${escapeHtml(categoryLabel)}</span>
      <span class="photo-caption-date">${escapeHtml(p.date)}</span>
    </div>
  </article>`;
}

function emptyPhotos() {
  return `<div class="photo-empty">
    <div class="photo-empty-icon">◇</div>
    <p class="photo-empty-title">${escapeHtml(tr('photo.emptyTitle'))}</p>
    <p class="photo-empty-text">${escapeHtml(tr('photo.emptyText'))}</p>
  </div>`;
}

function isLikelyImageFile(file) {
  if (!file) return false;
  if (file.type && file.type.startsWith('image/')) return true;
  return /\.(jpe?g|png|gif|webp|bmp|heic|heif)$/i.test(file.name || '');
}

function readImageDimensions(file) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      URL.revokeObjectURL(url);
      resolve({ w: img.naturalWidth, h: img.naturalHeight });
    };
    img.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error('invalid'));
    };
    img.src = url;
  });
}

function compressImageFile(file, maxEdge = 2560, quality = 0.88) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      URL.revokeObjectURL(url);
      let w = img.naturalWidth;
      let h = img.naturalHeight;
      const scale = Math.min(1, maxEdge / Math.max(w, h, 1));
      w = Math.max(1, Math.round(w * scale));
      h = Math.max(1, Math.round(h * scale));
      const canvas = document.createElement('canvas');
      canvas.width = w;
      canvas.height = h;
      canvas.getContext('2d').drawImage(img, 0, 0, w, h);
      canvas.toBlob(
        blob => {
          if (!blob) {
            reject(new Error(tr('photo.compressErr') || 'Compression impossible'));
            return;
          }
          const base = (file.name || 'photo').replace(/\.[^.]+$/i, '');
          resolve(new File([blob], base + '.jpg', { type: 'image/jpeg', lastModified: Date.now() }));
        },
        'image/jpeg',
        quality,
      );
    };
    img.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error(tr('photo.invalidImg') || 'Image illisible'));
    };
    img.src = url;
  });
}

/** Envoie le fichier original — pas de compression navigateur (évite les échecs sur 6000×4000, etc.). */
async function preparePhotoForUpload(file) {
  return file;
}

function formatApiErrorMessage(j) {
  if (!j) return '';
  const known = {
    'validation.uploaded': 'Échec de l’envoi fichier. Rechargez la page (Ctrl+F5) et réessayez.',
    'validation.required': 'Champ obligatoire manquant.',
    'validation.max.string': 'Données trop volumineuses pour le serveur.',
  };
  if (j.errors && typeof j.errors === 'object') {
    for (const arr of Object.values(j.errors)) {
      const raw = Array.isArray(arr) ? arr[0] : arr;
      if (!raw) continue;
      const key = String(raw);
      if (known[key]) return known[key];
      if (key.startsWith('validation.')) {
        return 'Erreur serveur (« '+key+' »). Rechargez la page (Ctrl+F5).';
      }
      return key;
    }
  }
  return j.message ? String(j.message) : '';
}

function formatPhotoUploadError(message, file) {
  const msg = String(message || '');
  if (/too large|trop volumineux|20\s*mo|upload_max|post_max|413/i.test(msg)) {
    return tr('photo.tooLarge') || 'Fichier trop lourd pour le serveur.';
  }
  if (/mimes|format|image/i.test(msg) && /non support/i.test(msg)) {
    return tr('photo.badFormat') || 'Format non supporté (JPG, PNG, GIF, WebP).';
  }
  return msg || tr('common.error') || 'Erreur';
}

function fileToBase64(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      const result = reader.result;
      if (typeof result !== 'string') {
        reject(new Error(tr('photo.invalidImg') || 'Image illisible'));
        return;
      }
      const i = result.indexOf(',');
      resolve(i >= 0 ? result.slice(i + 1) : result);
    };
    reader.onerror = () => reject(reader.error || new Error(tr('photo.invalidImg') || 'Image illisible'));
    reader.readAsDataURL(file);
  });
}

/** Envoi JSON base64 — contourne $_FILES vide sur certains hébergeurs cPanel. */
async function uploadPhotoFile(tab, file) {
  const name = file.name || 'photo.jpg';
  const b64 = await fileToBase64(file);
  return apiFetch('/photos', {
    method: 'POST',
    body: JSON.stringify({
      category: tab,
      photo_base64: b64,
      photo_name: name,
    }),
  });
}

async function addPhotos(tab, files) {
  if (isSiteReadOnly()) return;
  let ok = 0;
  let fail = 0;
  let skip = 0;
  let lastErr = '';
  for (const file of Array.from(files)) {
    if (!isLikelyImageFile(file)) {
      skip++;
      lastErr = tr('photo.uploadSkip') || 'Format non reconnu';
      continue;
    }
    try {
      await uploadPhotoFile(tab, file);
      ok++;
    } catch (e) {
      fail++;
      lastErr = formatPhotoUploadError(e.message, file);
      toast((file.name || 'Image') + ' — ' + lastErr, 'err');
    }
  }
  await loadPhotosCategory(tab);
  renderPhotos();
  if (ok > 0 && fail === 0 && skip === 0) {
    toast(tr('photo.uploadOk') || 'Photo(s) envoyée(s)', 'ok');
  } else if (ok > 0) {
    toast(trTpl('photo.uploadPartial', { ok, fail, skip }) || `${ok} photo(s) envoyée(s).`, fail > 0 ? 'err' : 'ok');
  } else if (skip > 0 && fail === 0) {
    toast(lastErr || tr('photo.uploadSkip') || 'Format de fichier non reconnu.', 'err');
  } else if (fail > 0 && ok === 0) {
    toast(lastErr || tr('photo.uploadFail') || 'Aucune photo n’a pu être envoyée.', 'err');
  }
}

async function removePhoto(tab, i) {
  if (isSiteReadOnly()) return;
  if (!confirm(tr('photo.confirmDel'))) return;
  const p = photoStore[tab][i];
  if (!p || !p.id) return;
  try {
    await apiFetch('/photos/' + p.id, { method: 'DELETE' });
    await loadPhotosCategory(tab);
    renderPhotos();
  } catch (e) {
    toast(e.message || 'Erreur', 'err');
  }
}

function openLB(tab, idx) {
  lbImages = photoStore[tab].map(p => p.src);
  lbIdx = idx;
  document.getElementById('lb-img').src = lbImages[lbIdx];
  document.getElementById('lightbox').classList.add('open');
}

function closeLB() {
  document.getElementById('lightbox').classList.remove('open');
}

function lbNav(dir) {
  lbIdx = (lbIdx + dir + lbImages.length) % lbImages.length;
  document.getElementById('lb-img').src = lbImages[lbIdx];
}

document.addEventListener('keydown', e => {
  if (!document.getElementById('lightbox').classList.contains('open')) return;
  if (e.key === 'Escape') closeLB();
  if (e.key === 'ArrowLeft') lbNav(-1);
  if (e.key === 'ArrowRight') lbNav(1);
});

const REPORT_COPY = {
  fr: {
    progressTitle: pct => `Avancement (${pct}%)`,
    reportTitle: 'Rapport journalier de chantier',
    overallProgress: 'Avancement global du projet',
    date: 'Date',
    temperature: 'Température',
    weather: 'Météo',
    page: 'Page',
    photosPrefix: 'Photos',
    cols: {
      phase: 'Phase',
      subphase: 'Sous-phase',
      activity: 'Activité',
      start: 'Début',
      progress: 'Avancement',
      duration: 'Durée',
      status: 'Statut',
    },
  },
  en: {
    progressTitle: pct => `PROGRESS (${pct}%)`,
    reportTitle: 'DAILY SITE PROGRESS REPORT',
    overallProgress: 'Overall Project Progress',
    date: 'Date',
    temperature: 'Temperature',
    weather: 'Weather',
    page: 'Page',
    photosPrefix: 'Photos',
    cols: {
      phase: 'Phase',
      subphase: 'Sub-phase',
      activity: 'Activity',
      start: 'Start',
      progress: 'Progress',
      duration: 'Duration',
      status: 'Status',
    },
  },
};

const STATUS_LABELS_EN = {
  non_demarre: 'Not started',
  en_cours: 'In progress',
  termine: 'Completed',
  annule: 'Cancelled',
};

function reportCopy(lang = gdaUiLang()) {
  return REPORT_COPY[lang] || REPORT_COPY.fr;
}

function reportStructureText(value, lang, kind) {
  if (lang !== 'en') return value || '';
  const map = window.REPORT_STRUCTURE_EN?.[kind];
  return map?.[value] || value || '';
}

function reportPhaseLabel(task, lang = gdaUiLang()) {
  return reportStructureText(task.phase, lang, 'phases');
}

function reportSubphaseLabel(task, lang = gdaUiLang()) {
  return reportStructureText(task.subphase, lang, 'subphases');
}

function reportActivityLabel(task, lang = gdaUiLang()) {
  return reportStructureText(task.activity, lang, 'activities');
}

function reportStatusComment(text, lang = gdaUiLang()) {
  if (lang !== 'en' || !text) return text || '';
  const map = window.REPORT_STRUCTURE_EN?.comments;
  if (map?.[text]) return map[text];
  const lower = String(text).toLowerCase();
  if (map) {
    for (const [fr, en] of Object.entries(map)) {
      if (fr.toLowerCase() === lower) return en;
    }
  }
  return text;
}

function reportWeatherLabel(weather, lang = gdaUiLang()) {
  if (lang !== 'en' || !weather) return weather || '—';
  return window.REPORT_STRUCTURE_EN?.weather?.[weather] || weather;
}

function reportPhotoCategoryLabel(cat, lang = gdaUiLang()) {
  const labels = {
    avant: { fr: 'Avant travaux', en: 'Before works' },
    pendant: { fr: 'Pendant travaux', en: 'During works' },
    apres: { fr: 'Après travaux', en: 'After works' },
    securite: { fr: 'Sécurité', en: 'Safety' },
    qualite: { fr: 'Contrôle qualité', en: 'Quality control' },
  };
  const entry = labels[cat] || { fr: cat, en: cat };
  return lang === 'en' ? entry.en : entry.fr;
}

function reportDurationLabel(days, lang = gdaUiLang()) {
  return lang === 'en' ? `${days} d` : `${days}j`;
}

function reportStatusLabel(task, lang = gdaUiLang()) {
  if (lang === 'en') return STATUS_LABELS_EN[task.status] || task.status_label;
  return task.status_label;
}

async function previewReport() {
  if (!dashboardData) {
    try {
      await refreshDashboard();
    } catch (_) {
      /* aperçu sans stats si dashboard indisponible */
    }
  }
  await loadAllPhotoCategories();
  const area = document.getElementById('report-preview-area');
  if (area) {
    area.innerHTML = buildReportHTML(false, gdaUiLang());
    requestAnimationFrame(() => renderReportStatsCharts());
  }
}

async function downloadReportPdf(reportId, locale = gdaUiLang()) {
  const projectId = localStorage.getItem(PROJECT_STORAGE_KEY);
  const headers = {
    Accept: 'application/pdf',
    'X-Requested-With': 'XMLHttpRequest',
  };
  applyCsrfHeaders(headers);
  const token = localStorage.getItem(TOKEN_KEY);
  if (token) headers.Authorization = 'Bearer ' + token;
  if (projectId) headers['X-Project-Id'] = projectId;
  if (typeof gdaUiLang === 'function') {
    headers['X-GDA-Ui-Lang'] = gdaUiLang();
  }
  const res = await fetch(API_BASE + '/reports/' + reportId + '/pdf?locale=' + encodeURIComponent(locale), {
    headers,
    credentials: 'include',
  });
  if (!res.ok) throw new Error('PDF indisponible');
  return res.blob();
}

function getReportPdfCaptureRoot() {
  let el = document.getElementById('report-pdf-capture-root');
  if (!el) {
    el = document.createElement('div');
    el.id = 'report-pdf-capture-root';
    el.className = 'report-preview report-preview--pdf-capture';
    el.setAttribute('aria-hidden', 'true');
    el.style.cssText =
      `position:fixed;left:0;top:0;width:${PDF_CHART_CAPTURE_WIDTH}px;max-width:100vw;z-index:99999;opacity:0.02;pointer-events:none;background:#faf8f4;overflow:auto;max-height:100vh`;
    document.body.appendChild(el);
  }
  return el;
}

async function ensureReportChartsForPdf() {
  if (!dashboardData) {
    await refreshDashboard();
  }
  if (!dashboardData?.charts) {
    throw new Error(tr('report.chartsCaptureErr') || 'Impossible de capturer les graphiques');
  }
  const root = getReportPdfCaptureRoot();
  root.innerHTML = buildReportStatsHTML(gdaUiLang());
  await new Promise(resolve => {
    requestAnimationFrame(() => {
      renderReportStatsCharts({ forPdf: true, root });
      requestAnimationFrame(resolve);
    });
  });
  const ok = await waitForReportChartCanvases(root);
  if (!ok) {
    root.innerHTML = '';
    throw new Error(tr('report.chartsCaptureErr') || 'Impossible de capturer les graphiques');
  }
  return root;
}

function captureReportChartImages(root) {
  const scope = root && root.querySelector ? root : document.getElementById('report-pdf-capture-root') || document;
  const map = {
    status: GDA_CHART_IDS_REPORT.pie,
    phase: GDA_CHART_IDS_REPORT.phase,
    sub: GDA_CHART_IDS_REPORT.sub,
    act: GDA_CHART_IDS_REPORT.act,
  };
  const out = {};
  for (const [key, id] of Object.entries(map)) {
    const canvas = scope.querySelector(`#${id}`);
    if (!canvas || canvas.width <= 0 || canvas.height <= 0) continue;
    const chart = reportChartInstances.find(c => c.canvas === canvas);
    let dataUrl = '';
    if (chart && typeof chart.toBase64Image === 'function') {
      dataUrl = chart.toBase64Image('image/png', 1.0);
    } else {
      dataUrl = canvas.toDataURL('image/png', 1.0);
    }
    if (isValidChartDataUrl(dataUrl)) {
      out[key] = dataUrl;
    }
  }
  return out;
}

async function printReport() {
  const date = document.getElementById('r-date').value;
  const temp = document.getElementById('r-temp').value;
  const weather = document.getElementById('r-weather').value;
  try {
    toast(tr('report.pdfPreparing') || 'Préparation des graphiques…', '');
    const captureRoot = await ensureReportChartsForPdf();
    const chart_images = captureReportChartImages(captureRoot);
    captureRoot.innerHTML = '';
    destroyReportStatsCharts();
    if (!chart_images.status || !chart_images.phase) {
      toast(tr('report.chartsCaptureErr') || 'Impossible de capturer les graphiques', 'err');
      return;
    }
    const gen = await apiFetch('/reports/generate', {
      method: 'POST',
      body: JSON.stringify({
        date,
        temperature: temp === '' ? null : parseFloat(temp),
        weather,
        notes: null,
        chart_images,
      }),
    });
    lastReportId = gen.report.id;
    const blob = await downloadReportPdf(gen.report.id, gdaUiLang());
    const url = URL.createObjectURL(blob);
    window.open(url, '_blank');
    const preview = document.getElementById('report-preview-area');
    if (preview?.querySelector(`#${GDA_CHART_IDS_REPORT.pie}`)) {
      requestAnimationFrame(() => renderReportStatsCharts({ root: preview }));
    }
    toast(tr('report.pdfOk') || 'PDF généré', 'ok');
  } catch (e) {
    toast(e.message || tr('common.error') || 'Erreur', 'err');
  }
}

function countReportPages() {
  const statsPage = tasks.length > 0 ? 1 : 0;
  const taskPages = Math.max(1, Math.ceil(tasks.length / 14));
  const photoPages = PHOTO_CATEGORIES.filter(cat => photoStore[cat].length > 0).length;
  return statsPage + taskPages + photoPages;
}

function formatReportPageLabel(page = 1) {
  return `${page} / ${countReportPages()}`;
}

function reportStatusClass(status) {
  if (status === 'termine') return 'st-termine';
  if (status === 'en_cours') return 'st-encours';
  if (status === 'annule') return 'st-annule';
  return 'st-nondemarre';
}

function buildReportStatsHTML(lang = gdaUiLang()) {
  if (!dashboardData) return '';
  const overall = dashboardData.overall_progress ?? overallProgress();
  const project = document.getElementById('r-project')?.value || '';

  return `
    <section class="rp-stats-section">
      <h2 class="rp-stats-main-title">${escapeHtml(tr('dash.statsHead'))}</h2>
      <p class="rp-stats-project">${escapeHtml(project.toUpperCase())} — ${escapeHtml(tr('sidebar.overall'))} : <strong>${overall}%</strong></p>
      <p class="dashboard-charts-intro">${escapeHtml(tr('dash.statsIntro'))}</p>
      <div class="dashboard-charts-grid">
        <div class="dashboard-chart-wrap">
          <div class="dashboard-chart-title">${escapeHtml(tr('dash.chartStatus'))}</div>
          <div class="dashboard-chart-canvas">
            <canvas id="report-chart-status-pie" aria-label="${escapeHtmlAttr(tr('dash.chartStatus'))}"></canvas>
          </div>
        </div>
        <div class="dashboard-chart-wrap">
          <div class="dashboard-chart-title">${escapeHtml(tr('dash.chartPhase'))}</div>
          <div class="dashboard-chart-canvas">
            <canvas id="report-chart-phase-bar" aria-label="${escapeHtmlAttr(tr('dash.chartPhase'))}"></canvas>
          </div>
        </div>
        <div class="dashboard-chart-wrap dashboard-chart-wrap--wide">
          <div class="dashboard-chart-title">${escapeHtml(tr('dash.chartSub'))}</div>
          <div class="dashboard-chart-canvas dashboard-chart-canvas--tall">
            <canvas id="report-chart-subphase-bar" aria-label="${escapeHtmlAttr(tr('dash.chartSub'))}"></canvas>
          </div>
        </div>
        <div class="dashboard-chart-wrap dashboard-chart-wrap--wide">
          <div class="dashboard-chart-head-row">
            <div class="dashboard-chart-title">${escapeHtml(tr('dash.chartAct'))}</div>
            <div id="report-activity-toolbar" class="dashboard-activity-toolbar" style="display:none">
              <label for="report-activity-phase-filter" class="dashboard-activity-filter-lbl">${escapeHtml(tr('dash.filterShow'))}</label>
              <select id="report-activity-phase-filter" class="dashboard-activity-phase-select" onchange="onReportActivityPhaseFilterChange(this)">
                <option value="">—</option>
              </select>
            </div>
          </div>
          <div id="report-activity-empty" class="dashboard-activity-empty" hidden></div>
          <div id="report-activity-chart-canvas" class="dashboard-chart-canvas dashboard-chart-canvas--activities">
            <canvas id="report-chart-activity-bar" aria-label="${escapeHtmlAttr(tr('dash.chartAct'))}"></canvas>
          </div>
        </div>
      </div>
    </section>`;
}


function buildReportHTML(forPrint, lang = gdaUiLang()) {
  const copy = reportCopy(lang);
  const date = document.getElementById('r-date').value;
  const temp = document.getElementById('r-temp').value;
  const weather = document.getElementById('r-weather').value;
  const project = document.getElementById('r-project').value;
  const pct = dashboardData ? dashboardData.overall_progress : overallProgress();

  const dateLocale = lang === 'en' ? 'en-GB' : 'fr-FR';
  const dateF = date ? new Date(date + 'T00:00:00').toLocaleDateString(dateLocale, { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—';

  const statusColor = s => {
    if (lang === 'en') {
      return ({ Completed: '#1a7a42', 'In progress': '#c8521a', 'Not started': '#8a8070', Cancelled: '#c01a1a' })[s] || '#888';
    }
    return ({ Terminé: '#1a7a42', 'En cours': '#c8521a', 'Non démarré': '#8a8070', Annulée: '#c01a1a' })[s] || '#888';
  };

  const sorted = [...tasks];
  const phaseCounts = {};
  const subKeyCounts = {};
  sorted.forEach(t => {
    phaseCounts[t.phase] = (phaseCounts[t.phase] || 0) + 1;
    const sk = `${t.phase}\u0000${t.subphase}`;
    subKeyCounts[sk] = (subKeyCounts[sk] || 0) + 1;
  });
  const seenP = {};
  const seenS = {};
  const tableRows = sorted.map((t, i) => {
    const sk = `${t.phase}\u0000${t.subphase}`;
    const showP = !seenP[t.phase];
    const showS = !seenS[sk];
    seenP[t.phase] = true;
    seenS[sk] = true;
    const cancelComment =
      lang === 'en' && t.status_comment_display ? t.status_comment_display : t.status_comment;
    const statusNote = t.status === 'annule' && cancelComment
      ? `<span class="rp-status-note">${escapeHtml(cancelComment)}</span>`
      : '';
    return `
      <tr>
        ${showP ? `<td rowspan="${phaseCounts[t.phase]}" class="rp-phase-cell">${escapeHtml(reportPhaseLabel(t, lang))}</td>` : ''}
        ${showS ? `<td rowspan="${subKeyCounts[sk]}" style="font-weight:600;color:#1a3a5c">${escapeHtml(reportSubphaseLabel(t, lang))}</td>` : ''}
        <td>${escapeHtml(reportActivityLabel(t, lang))}</td>
        <td style="text-align:center">${escapeHtml(taskStartDateLabel(t.startDay))}</td>
        <td style="text-align:center">
          <div style="display:flex;align-items:center;justify-content:center;gap:6px">
            <div class="rp-mini-bar"><div class="rp-mini-fill" style="width:${t.progress}%;background:${t.progress === 100 ? '#1a7a42' : '#c8521a'}"></div></div>
            <strong>${t.progress}%</strong>
          </div>
        </td>
        <td style="text-align:center">${reportDurationLabel(t.duration, lang)}</td>
        <td style="text-align:center;font-weight:bold;color:${statusColor(reportStatusLabel(t, lang))}">${escapeHtml(reportStatusLabel(t, lang))}${statusNote}</td>
      </tr>
    `;
  }).join('');

  const photosSection = PHOTO_CATEGORIES
    .map(cat => [cat, photoStore[cat]])
    .filter(([, photos]) => photos.length > 0)
    .map(([cat, photos]) => `
    <section class="rp-photo-section">
      <header class="rp-photo-header">
        <div>
          <span class="rp-photo-eyebrow">${copy.photosPrefix}</span>
          <h3 class="rp-photo-title">${escapeHtml(reportPhotoCategoryLabel(cat, lang))}</h3>
        </div>
        <span class="rp-photo-count">${photos.length}</span>
      </header>
      <div class="rp-photo-grid">
        ${photos.map(p => `<figure class="rp-photo-card"><img src="${p.src.replace(/"/g, '&quot;')}" alt=""></figure>`).join('')}
      </div>
    </section>
  `).join('');

  const printStyles = forPrint ? `<style>@media print{@page{size:A4 landscape;margin:8mm}}</style>` : '';
  const statsHtml = buildReportStatsHTML(lang);
  const dataPage = formatReportPageLabel(statsHtml ? 2 : 1);

  return `${printStyles}
  <div class="report-preview-root">
    ${statsHtml}
    <section class="rp-report-data-section">
    <h2 class="rp-data-section-title">${escapeHtml(tr('report.dataSection'))}</h2>
    <div class="rp-header">
      <div>
        <div class="rp-title" style="text-align:center">${escapeHtml(project.toUpperCase())}</div>
        <div class="rp-title" style="text-align:center;margin-top:4px">${copy.progressTitle(pct)}</div>
        <div class="rp-report-title" style="text-align:center">${copy.reportTitle}</div>
      </div>
      <div class="rp-meta">
        <div><b style="color:#1a3a5c">${copy.date} :</b> <span>${dateF}</span></div>
        <div><b style="color:#1a3a5c">${copy.temperature} :</b> <span>${escapeHtml(temp)}°C</span></div>
        <div><b style="color:#1a3a5c">${copy.weather} :</b> <span>${escapeHtml(reportWeatherLabel(weather, lang))}</span></div>
        <div><b style="color:#1a3a5c">${copy.page} :</b> <span>${escapeHtml(dataPage)}</span></div>
      </div>
    </div>
    <table class="rp-table">
      <thead>
        <tr>
          <th>${copy.cols.phase}</th>
          <th>${copy.cols.subphase}</th>
          <th>${copy.cols.activity}</th>
          <th style="text-align:center">${copy.cols.start}</th>
          <th style="text-align:center">${copy.cols.progress}</th>
          <th style="text-align:center">${copy.cols.duration}</th>
          <th style="text-align:center">${copy.cols.status}</th>
        </tr>
      </thead>
      <tbody>${tableRows}</tbody>
    </table>
    <div class="rp-footer-band">${copy.overallProgress}</div>
    <div class="rp-footer-pct">${pct}%</div>
    </section>
    ${photosSection}
  </div>`;
}

function toast(msg, type = '') {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = 'toast show ' + type;
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 3500);
}

function applyPartnerShellHides() {
  if (!window.GDA_IS_PARTNER) return;
  document.querySelectorAll('[data-hide-for-partner]').forEach((el) => {
    el.style.display = 'none';
  });
  const dismiss = document.getElementById('modal-task-dismiss');
  if (dismiss) dismiss.textContent = tr('partner.modalBack');
}

function applyAdminShellShows() {
  const isAdmin = !!(currentUser && currentUser.is_admin);
  document.querySelectorAll('[data-admin-only]').forEach((el) => {
    el.style.display = isAdmin ? '' : 'none';
  });
  if (isAdmin) bindActivityLogsFilters();
}

function logActionLabel(action) {
  const key = 'logs.action.' + action;
  const t = tr(key);
  return t !== key ? t : action;
}

function renderActivityLogsTable(logs) {
  const tbody = document.getElementById('activity-logs-tbody');
  if (!tbody) return;
  if (!logs || !logs.length) {
    tbody.innerHTML = `<tr><td colspan="6" class="logs-empty">${escapeHtml(tr('page.logs.empty'))}</td></tr>`;
    return;
  }
  tbody.innerHTML = logs
    .map((log) => {
      const user = log.user_name
        ? `${escapeHtml(log.user_name)} <span class="logs-user-meta">@${escapeHtml(log.user_username || '')}</span>`
        : escapeHtml(tr('page.logs.systemUser'));
      const project = log.project_name ? escapeHtml(log.project_name) : '—';
      const badgeClass = log.action.includes('delete') || log.action === 'login_failed' ? 'log-badge-warn' : log.action.includes('login') ? 'log-badge-info' : 'log-badge-neutral';
      return `<tr>
        <td class="logs-date">${escapeHtml(log.created_at_fmt || log.created_at || '')}</td>
        <td class="logs-user">${user}</td>
        <td><span class="log-badge ${badgeClass}">${escapeHtml(logActionLabel(log.action))}</span></td>
        <td class="logs-desc">${escapeHtml(log.description || '')}</td>
        <td>${project}</td>
        <td class="logs-ip">${escapeHtml(log.ip_address || '—')}</td>
      </tr>`;
    })
    .join('');
}

function renderActivityLogsPagination(meta) {
  const el = document.getElementById('logs-pagination');
  if (!el || !meta) {
    if (el) el.innerHTML = '';
    return;
  }
  const { current_page: page, last_page: last, total } = meta;
  const label = tr('page.logs.pageOf')
    .replace('{page}', String(page))
    .replace('{last}', String(last))
    .replace('{total}', String(total));
  el.innerHTML = `
    <span class="logs-page-info">${escapeHtml(label)}</span>
    <span class="logs-page-btns">
      <button type="button" class="btn btn-secondary btn-sm" ${page <= 1 ? 'disabled' : ''} onclick="void loadActivityLogs(${page - 1})">${escapeHtml(tr('page.logs.prev'))}</button>
      <button type="button" class="btn btn-secondary btn-sm" ${page >= last ? 'disabled' : ''} onclick="void loadActivityLogs(${page + 1})">${escapeHtml(tr('page.logs.next'))}</button>
    </span>`;
}

async function loadActivityLogs(page = 1) {
  if (!currentUser?.is_admin) return;
  activityLogsPage = page;
  const tbody = document.getElementById('activity-logs-tbody');
  if (tbody) {
    tbody.innerHTML = `<tr><td colspan="6" class="logs-empty">${escapeHtml(tr('common.loading'))}</td></tr>`;
  }
  const params = new URLSearchParams({ page: String(page), per_page: '50' });
  const q = document.getElementById('logs-filter-q')?.value?.trim();
  const action = document.getElementById('logs-filter-action')?.value;
  const from = document.getElementById('logs-filter-from')?.value;
  const to = document.getElementById('logs-filter-to')?.value;
  if (q) params.set('q', q);
  if (action) params.set('action', action);
  if (from) params.set('from', from);
  if (to) params.set('to', to);
  try {
    const data = await apiFetch('/activity-logs?' + params.toString());
    const sel = document.getElementById('logs-filter-action');
    if (sel && data.filters?.actions?.length) {
      const cur = sel.value;
      const opts = [`<option value="">${escapeHtml(tr('page.logs.filterAll'))}</option>`]
        .concat(
          data.filters.actions.map((a) => `<option value="${escapeHtmlAttr(a)}">${escapeHtml(logActionLabel(a))}</option>`),
        )
        .join('');
      sel.innerHTML = opts;
      if (cur && data.filters.actions.includes(cur)) sel.value = cur;
    }
    renderActivityLogsTable(data.logs || []);
    renderActivityLogsPagination(data.meta);
  } catch (e) {
    if (tbody) {
      tbody.innerHTML = `<tr><td colspan="6" class="logs-empty">${escapeHtml(e.message || tr('page.logs.loadErr'))}</td></tr>`;
    }
    toast(e.message || tr('page.logs.loadErr'), 'err');
  }
}

function bindActivityLogsFilters() {
  if (activityLogsFiltersBound) return;
  activityLogsFiltersBound = true;
  let debounce;
  const schedule = () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
      if (document.getElementById('page-logs')?.classList.contains('active')) {
        void loadActivityLogs(1);
      }
    }, 400);
  };
  ['logs-filter-q', 'logs-filter-action', 'logs-filter-from', 'logs-filter-to'].forEach((id) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', schedule);
    el.addEventListener('change', schedule);
  });
}

function bindReportPreviewAutoRefresh() {
  let t;
  const schedule = () => {
    clearTimeout(t);
    t = setTimeout(() => {
      if (document.getElementById('page-report')?.classList.contains('active')) {
        void previewReport();
      }
    }, 380);
  };
  ['r-date', 'r-temp', 'r-weather', 'r-project'].forEach((id) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('change', schedule);
    el.addEventListener('input', schedule);
  });
}

document.getElementById('modal-task')?.addEventListener('click', function (e) {
  if (e.target === this) closeModal();
});

document.addEventListener('DOMContentLoaded', async () => {
  const authRequired = !!window.GDA_AUTH_REQUIRED;
  try {
    const { user } = await apiFetch('/whoami');
    currentUser = user;
    localStorage.setItem(USER_KEY, JSON.stringify(user));
    const av = document.getElementById('user-av');
    const nm = document.getElementById('user-nm');
    if (av) av.textContent = user.avatar_initials || user.name.charAt(0);
    if (nm) nm.textContent = user.name;
  } catch (_) {
    currentUser = null;
    if (authRequired) {
      showLogin();
      return;
    }
    const nm = document.getElementById('user-nm');
    if (nm) nm.textContent = 'Invité';
  }

  applyPartnerShellHides();
  applyAdminShellShows();
  applyUiRolePermissions();

  try {
    bindSidebarProjectSelect();
    if (window.GDA_IS_PARTNER) {
      await loadProject();
    } else {
      await loadProjectCatalog();
    }
    await initApp();
    applyI18nDocument();
    syncUiLangButtons();
    applyPartnerShellHides();
    applyAdminShellShows();
    applyUiRolePermissions();
    bindReportPreviewAutoRefresh();
  } catch (e) {
    toast(e.message || 'Impossible de charger le chantier.', 'err');
  }
});

window.gdaOnUiLangChange = function gdaOnUiLangChange() {
  updateClock();
  const ddl = document.getElementById('daily-date-label');
  if (ddl && selectedDailyDate) {
    ddl.textContent =
      tr('daily.datePrefix') +
      ' ' +
      new Date(selectedDailyDate + 'T12:00:00').toLocaleDateString(gdaDateLocale(), {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      });
  }
  populatePhaseFilters();
  applyTasksSubtitleCount();
  syncTaskStatusCommentField();
  const active = document.querySelector('.page.active')?.id;
  if (active === 'page-dashboard') void refreshDashboard().then(() => renderDashboard());
  if (active === 'page-daily' && !window.GDA_IS_PARTNER) void loadDaily().then(() => renderDaily());
  void loadTasks().then(() => {
    if (active === 'page-tasks') renderAllTasks();
    if (active === 'page-report') void previewReport();
  });
  if (active === 'page-photos' && !window.GDA_IS_PARTNER) {
    void loadPhotosCategory(currentPhotoTab).then(() => renderPhotos());
  }
  if (active === 'page-logs' && currentUser?.is_admin) void loadActivityLogs(activityLogsPage);
  applyPartnerShellHides();
  applyAdminShellShows();
};
