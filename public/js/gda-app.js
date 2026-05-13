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
let projectMeta = null;
let cachedProjects = [];
let currentUser = null;
let clockTimer = null;
let lastReportId = null;

const photoStore = { avant: [], pendant: [], apres: [], securite: [], qualite: [] };
const PHOTO_CATEGORIES = Object.keys(photoStore);
let lbImages = [], lbIdx = 0;
let currentPhotoTab = 'avant';
let dailyFilter = 'all';

const PHOTO_LABELS = {
  avant: { label: 'Avant travaux', desc: 'Documentation avant le démarrage des travaux', color: 'var(--accent2)' },
  pendant: { label: 'Pendant travaux', desc: 'Suivi photographique en cours de chantier', color: 'var(--accent)' },
  apres: { label: 'Après travaux', desc: 'Réception et livraison finale', color: 'var(--ok)' },
  securite: { label: 'Sécurité', desc: 'Equipements de protection et zonage', color: 'var(--warn)' },
  qualite: { label: 'Contrôle qualité', desc: 'Inspection, tests et vérifications', color: 'var(--danger)' },
};

const STATUS_LABELS = {
  non_demarre: 'Non démarré',
  en_cours: 'En cours',
  termine: 'Terminé',
  annule: 'Annulée',
};

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
    comments: [],
  };
}

function statusBadgeFromSlug(slug) {
  return { non_demarre: 'badge-nd', en_cours: 'badge-ip', termine: 'badge-ok', annule: 'badge-late' }[slug] || 'badge-nd';
}

function statusLabelFromSlug(slug) {
  return STATUS_LABELS[slug] || slug;
}

function displayStatusComment(taskId) {
  if (pendingDaily[taskId]?.comment) return pendingDaily[taskId].comment;
  const row = getDailyRow(taskId);
  if (row?.effective_status_comment) return row.effective_status_comment;
  if (row?.daily_update?.comment && displayStatusSlug(taskId) === 'annule') return row.daily_update.comment;
  const task = tasks.find(x => x.id === taskId);
  return task?.status_comment || '';
}

function renderStatusCell(taskId, slug, label) {
  const comment = slug === 'annule' ? displayStatusComment(taskId) : '';
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
  const required = status === 'annule';
  label.textContent = required ? 'Motif d\'annulation (obligatoire)' : 'Commentaire / Observations';
  comment.placeholder = required ? 'Décrivez la raison de l\'annulation...' : 'Notes du jour...';
  comment.required = required;
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
      msg = j.message || (j.errors && JSON.stringify(j.errors)) || msg;
    } catch (_) {}
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
    preview.innerHTML = '<div style="padding:40px;text-align:center;color:var(--muted)">Chargement de l’aperçu…</div>';
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
    'Date sélectionnée · ' + new Date(selectedDailyDate + 'T12:00:00').toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

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
    new Date().toLocaleString('fr-FR', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

async function changeDailyDate(val) {
  if (!val) return;
  selectedDailyDate = val;
  pendingDaily = {};
  document.getElementById('daily-date-label').textContent =
    'Date sélectionnée · ' + new Date(val + 'T12:00:00').toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  await loadDaily();
  renderDaily();
}

function populatePhaseFilters() {
  const phases = [...new Set(tasks.map(t => t.phase))];
  ['daily-phase-filter', 'task-filter-phase'].forEach(id => {
    const sel = document.getElementById(id);
    if (!sel) return;
    const cur = sel.value;
    sel.innerHTML = id === 'daily-phase-filter'
      ? '<option value="">— Toutes les phases —</option>'
      : '<option value="">Toutes les phases</option>';
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
  if (el) el.textContent = tasks.length + ' activités · Groupées par phase';
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
  if (window.GDA_IS_PARTNER && (page === 'daily' || page === 'photos')) {
    toast('Cette section n’est pas accessible pour votre profil.', 'err');
    return;
  }
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
    syncReportLangToggle();
    void previewReport();
  }
}

function renderDashboard() {
  refreshSidebar();
  if (!dashboardData) return;

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
    ra.innerHTML = '<div style="color:var(--muted);font-size:13px;padding:20px;text-align:center">Aucune activité — commencez la saisie du jour.</div>';
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
}

function escapeHtml(s) {
  if (!s) return '';
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
}

function getDailyRow(taskId) {
  return dailyByTaskId[taskId];
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
    container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted)">Aucune tâche dans ce filtre.</div>';
    return;
  }

  container.innerHTML = filtered.map(t => {
    const prog = displayProgress(t.id);
    const stSlug = displayStatusSlug(t.id);
    const pctClass = prog === 100 ? 'fill-done' : prog > 0 ? 'fill-low' : 'fill-0';
    const stLabel = displayStatusLabel(t.id);
    return `<div class="daily-task-row" id="drow-${t.id}" onclick="openModal(${t.id})">
      <div>
        <div class="task-name">${escapeHtml(t.subphase)} — ${escapeHtml(t.activity)}</div>
        <div class="task-phase">${escapeHtml(t.phase)}</div>
      </div>
      <div>${renderStatusCell(t.id, stSlug, stLabel)}</div>
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
        <button class="btn btn-secondary btn-sm" onclick="event.stopPropagation();openModal(${t.id})">Détail</button>
      </div>
    </div>`;
  }).join('');
}

function quickUpdate(id, val) {
  if (isSiteReadOnly()) return;
  const v = parseInt(val, 10);
  pendingDaily[id] = { ...pendingDaily[id], progress: v, status: deriveStatusFromProgress(v) };
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
  const updates = keys.map(tid => ({
    task_id: parseInt(tid, 10),
    progress: pendingDaily[tid].progress,
    status: pendingDaily[tid].status || deriveStatusFromProgress(pendingDaily[tid].progress),
    comment: pendingDaily[tid].comment || null,
  }));
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

function openModal(id) {
  const t = tasks.find(x => x.id === id);
  if (!t) return;
  document.getElementById('modal-task-id').value = id;
  document.getElementById('modal-title').textContent = t.subphase + ' · ' + t.activity;
  document.getElementById('m-phase').value = t.phase;
  document.getElementById('m-sub').value = t.subphase;
  document.getElementById('m-act').value = t.activity;
  document.getElementById('m-status').value = displayStatusSlug(id);
  document.getElementById('m-progress').value = displayProgress(id);
  document.getElementById('m-pct-lbl').textContent = displayProgress(id);
  document.getElementById('m-comment').value = displayStatusComment(id);
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
  const comment = document.getElementById('m-comment').value.trim();

  if (status === 'annule' && !comment) {
    syncTaskStatusCommentField();
    toast('Une description est obligatoire pour le statut Annulée.', 'err');
    return;
  }

  try {
    const row = getDailyRow(id);
    const payload = { progress, status, comment: comment || null };
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
          progress,
          status,
          comment: comment || null,
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
        <span style="margin-left:12px;font-weight:400;color:var(--muted)">${phasePct}% complété</span>
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
        <td>${renderStatusCell(t.id, t.status, t.status_label)}</td>
        <td>
          <button class="btn btn-secondary btn-sm btn-icon" onclick="openModal(${t.id})" title="Modifier">✎</button>
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
    src: p.url,
    date: p.taken_at ? new Date(p.taken_at).toLocaleDateString('fr-FR') : new Date(p.created_at).toLocaleDateString('fr-FR'),
  }));
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
  const info = PHOTO_LABELS[currentPhotoTab];
  const photos = photoStore[currentPhotoTab];
  const countLabel = `${photos.length} photo${photos.length !== 1 ? 's' : ''}`;

  const container = document.getElementById('photo-sections');
  container.innerHTML = `
    <section class="photo-stage">
      <header class="photo-hero">
        <div class="photo-hero-copy">
          <span class="photo-hero-eyebrow">Documentation visuelle</span>
          <h2 class="photo-hero-title">${escapeHtml(info.label)}</h2>
          <p class="photo-hero-desc">${escapeHtml(info.desc)}</p>
        </div>
        <div class="photo-hero-meta">
          <span class="photo-count-badge">${countLabel}</span>
        </div>
      </header>

      <div class="photo-upload-card">
        <div class="drop-zone" id="dz-${currentPhotoTab}">
          <div class="dz-icon-ring">📷</div>
          <div class="dz-text">
            <strong>Cliquez ici</strong> ou glissez vos photos dans <strong>${escapeHtml(info.label)}</strong>
            <span>JPG, PNG · max 20 Mo par photo</span>
          </div>
        </div>
        <input type="file" id="ph-input-${currentPhotoTab}" multiple accept="image/*" style="display:none"
          onchange="addPhotos('${currentPhotoTab}', this.files)">
      </div>

      <div class="photo-grid" id="pg-${currentPhotoTab}">
        ${photos.map((p, i) => photoThumb(p, i, currentPhotoTab, info.label)).join('') || emptyPhotos()}
      </div>
    </section>
  `;

  const dz = document.getElementById('dz-' + currentPhotoTab);
  if (dz) {
    dz.onclick = () => { if (!isSiteReadOnly()) document.getElementById('ph-input-' + currentPhotoTab).click(); };
    dz.style.opacity = isSiteReadOnly() ? '0.55' : '';
    dz.style.pointerEvents = isSiteReadOnly() ? 'none' : '';
  }
  if (dz && !isSiteReadOnly()) {
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag-active'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('drag-active'));
    dz.addEventListener('drop', e => {
      e.preventDefault();
      dz.classList.remove('drag-active');
      addPhotos(currentPhotoTab, e.dataTransfer.files);
    });
  }

  lbImages = photos.map(p => p.src);
}

function photoThumb(p, i, tab, categoryLabel) {
  return `<article class="photo-item" onclick="openLB('${tab}',${i})">
    <div class="photo-frame">
      <img src="${p.src.replace(/"/g, '&quot;')}" alt="">
    <div class="photo-date-badge">${escapeHtml(p.date)}</div>
      <div class="photo-overlay">
        <span class="photo-zoom-hint">Agrandir</span>
        <div class="photo-overlay-btns">
          <button class="photo-action-btn del" onclick="event.stopPropagation();removePhoto('${tab}',${i})">Supprimer</button>
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
    <p class="photo-empty-title">Aucune photo pour l'instant</p>
    <p class="photo-empty-text">Importez vos premières images avec la zone de dépôt ci-dessus.</p>
  </div>`;
}

async function addPhotos(tab, files) {
  if (isSiteReadOnly()) return;
  for (const file of Array.from(files)) {
    if (!file.type.startsWith('image/')) continue;
    const fd = new FormData();
    fd.append('photo', file);
    fd.append('category', tab);
    try {
      await apiFetch('/photos', { method: 'POST', body: fd });
    } catch (e) {
      toast(e.message || 'Erreur upload', 'err');
    }
  }
  await loadPhotosCategory(tab);
  renderPhotos();
  toast('Photo(s) envoyée(s)', 'ok');
}

async function removePhoto(tab, i) {
  if (isSiteReadOnly()) return;
  if (!confirm('Supprimer cette photo ?')) return;
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

let reportPreviewLang = 'fr';

function syncReportLangToggle() {
  const fr = document.getElementById('report-lang-fr');
  const en = document.getElementById('report-lang-en');
  if (!fr || !en) return;
  const isFr = reportPreviewLang !== 'en';
  fr.classList.toggle('btn-primary', isFr);
  fr.classList.toggle('btn-secondary', !isFr);
  en.classList.toggle('btn-primary', !isFr);
  en.classList.toggle('btn-secondary', isFr);
}

function setReportLang(lang) {
  reportPreviewLang = lang === 'en' ? 'en' : 'fr';
  syncReportLangToggle();
  void previewReport();
}

function reportCopy(lang = reportPreviewLang) {
  return REPORT_COPY[lang] || REPORT_COPY.fr;
}

function reportStructureText(value, lang, kind) {
  if (lang !== 'en') return value || '';
  const map = window.REPORT_STRUCTURE_EN?.[kind];
  return map?.[value] || value || '';
}

function reportPhaseLabel(task, lang = reportPreviewLang) {
  return reportStructureText(task.phase, lang, 'phases');
}

function reportSubphaseLabel(task, lang = reportPreviewLang) {
  return reportStructureText(task.subphase, lang, 'subphases');
}

function reportActivityLabel(task, lang = reportPreviewLang) {
  return reportStructureText(task.activity, lang, 'activities');
}

function reportStatusComment(text, lang = reportPreviewLang) {
  if (lang !== 'en' || !text) return text || '';
  return window.REPORT_STRUCTURE_EN?.comments?.[text] || text;
}

function reportWeatherLabel(weather, lang = reportPreviewLang) {
  if (lang !== 'en' || !weather) return weather || '—';
  return window.REPORT_STRUCTURE_EN?.weather?.[weather] || weather;
}

function reportPhotoCategoryLabel(cat, lang = reportPreviewLang) {
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

function reportDurationLabel(days, lang = reportPreviewLang) {
  return lang === 'en' ? `${days} d` : `${days}j`;
}

function reportStatusLabel(task, lang = reportPreviewLang) {
  if (lang === 'en') return STATUS_LABELS_EN[task.status] || task.status_label;
  return task.status_label;
}

async function previewReport() {
  await loadAllPhotoCategories();
  const area = document.getElementById('report-preview-area');
  if (area) area.innerHTML = buildReportHTML(false, reportPreviewLang);
}

async function downloadReportPdf(reportId, locale = reportPreviewLang) {
  const projectId = localStorage.getItem(PROJECT_STORAGE_KEY);
  const headers = {
    Accept: 'application/pdf',
    'X-Requested-With': 'XMLHttpRequest',
  };
  applyCsrfHeaders(headers);
  const token = localStorage.getItem(TOKEN_KEY);
  if (token) headers.Authorization = 'Bearer ' + token;
  if (projectId) headers['X-Project-Id'] = projectId;
  const res = await fetch(API_BASE + '/reports/' + reportId + '/pdf?locale=' + encodeURIComponent(locale), {
    headers,
    credentials: 'include',
  });
  if (!res.ok) throw new Error('PDF indisponible');
  return res.blob();
}

async function printReport() {
  const date = document.getElementById('r-date').value;
  const temp = document.getElementById('r-temp').value;
  const weather = document.getElementById('r-weather').value;
  try {
    const gen = await apiFetch('/reports/generate', {
      method: 'POST',
      body: JSON.stringify({
        date,
        temperature: temp === '' ? null : parseFloat(temp),
        weather,
        notes: null,
      }),
    });
    lastReportId = gen.report.id;
    const blob = await downloadReportPdf(gen.report.id, reportPreviewLang);
    const url = URL.createObjectURL(blob);
    window.open(url, '_blank');
    toast('PDF généré', 'ok');
  } catch (e) {
    toast(e.message || 'Erreur', 'err');
  }
}

function countReportPages() {
  const taskPages = Math.max(1, Math.ceil(tasks.length / 14));
  const photoPages = PHOTO_CATEGORIES.filter(cat => photoStore[cat].length > 0).length;
  return taskPages + photoPages;
}

function formatReportPageLabel(page = 1) {
  return `${page} / ${countReportPages()}`;
}

function buildReportHTML(forPrint, lang = reportPreviewLang) {
  const copy = reportCopy(lang);
  const date = document.getElementById('r-date').value;
  const temp = document.getElementById('r-temp').value;
  const weather = document.getElementById('r-weather').value;
  const project = document.getElementById('r-project').value;
  const page = formatReportPageLabel(1);
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
    const statusNote = t.status === 'annule' && t.status_comment
      ? `<span class="rp-status-note">${escapeHtml(reportStatusComment(t.status_comment, lang))}</span>`
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

  return `${printStyles}
  <div>
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
        <div><b style="color:#1a3a5c">${copy.page} :</b> <span>${escapeHtml(page)}</span></div>
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
  if (dismiss) dismiss.textContent = 'Retour';
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

  try {
    bindSidebarProjectSelect();
    if (window.GDA_IS_PARTNER) {
      await loadProject();
    } else {
      await loadProjectCatalog();
    }
    await initApp();
    syncReportLangToggle();
    bindReportPreviewAutoRefresh();
  } catch (e) {
    toast(e.message || 'Impossible de charger le chantier.', 'err');
  }
});
