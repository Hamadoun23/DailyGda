/**
 * GD&A Chantier — client API Laravel
 */
const API_BASE = 'http://localhost:8000/api';
const LOCAL_MODE = false;

const TOKEN_KEY = 'gda_token';
const USER_KEY = 'gda_user';

let tasks = [];
let dailyByTaskId = {};
let pendingDaily = {};
let selectedDailyDate = '';
let dashboardData = null;
let projectMeta = null;
let currentUser = null;
let clockTimer = null;
let lastReportId = null;

const photoStore = { avant: [], pendant: [], apres: [], securite: [], qualite: [] };
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

function normTask(row) {
  return {
    id: row.id,
    phase_id: row.phase_id,
    phase: row.phase,
    subphase: row.subphase,
    activity: row.activity,
    startDay: row.start_day,
    duration: row.duration_days,
    responsible: row.responsible || '',
    progress: row.progress,
    status: row.status,
    status_label: row.status_label,
    comments: [],
  };
}

function statusBadgeFromSlug(slug) {
  return { non_demarre: 'badge-nd', en_cours: 'badge-ip', termine: 'badge-ok', bloque: 'badge-late' }[slug] || 'badge-nd';
}

function deriveStatusFromProgress(p) {
  const v = parseInt(p, 10);
  if (v >= 100) return 'termine';
  if (v > 0) return 'en_cours';
  return 'non_demarre';
}

function isDirection() {
  return currentUser && currentUser.role === 'direction';
}

function applyUiRolePermissions() {
  const dis = isDirection();
  document.querySelectorAll('[data-requires-submit]').forEach(el => {
    el.style.display = dis ? 'none' : '';
  });
  const batchBtn = document.querySelector('[data-daily-batch]');
  if (batchBtn) batchBtn.disabled = dis;
}

async function apiFetch(path, options = {}) {
  const headers = {
    Accept: 'application/json',
    ...(options.body !== undefined && !(options.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
    ...(options.headers || {}),
  };
  const token = localStorage.getItem(TOKEN_KEY);
  if (token) headers.Authorization = 'Bearer ' + token;

  const res = await fetch(API_BASE + path, { ...options, headers });
  if (res.status === 401) {
    clearAuth();
    showLogin();
    throw new Error('Session expirée');
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
  document.getElementById('login-screen').style.display = 'flex';
}

function hideLogin() {
  document.getElementById('login-screen').style.display = 'none';
}

async function doLogin() {
  const email = document.getElementById('login-email').value.trim();
  const password = document.getElementById('login-pass').value;
  try {
    const data = await fetch(API_BASE + '/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email, password }),
    }).then(async r => {
      const j = await r.json().catch(() => ({}));
      if (!r.ok) throw new Error(j.message || j.errors?.email?.[0] || 'Échec connexion');
      return j;
    });
    localStorage.setItem(TOKEN_KEY, data.token);
    localStorage.setItem(USER_KEY, JSON.stringify(data.user));
    currentUser = data.user;
    document.getElementById('user-av').textContent = data.user.avatar_initials || data.user.name.charAt(0);
    document.getElementById('user-nm').textContent = data.user.name;
    hideLogin();
    await initApp();
    toast('Bienvenue, ' + data.user.name, 'ok');
  } catch (e) {
    toast(e.message || 'Erreur', 'err');
  }
}

async function doLogout() {
  if (!confirm('Se déconnecter ?')) return;
  try {
    await apiFetch('/logout', { method: 'POST', body: '{}' });
  } catch (_) {}
  clearAuth();
  currentUser = null;
  showLogin();
}

async function loadProject() {
  const data = await apiFetch('/project');
  projectMeta = data.project;
  const lbl = document.querySelector('.project-label');
  if (lbl) lbl.textContent = (projectMeta.name || '').substring(0, 42);
  const rp = document.getElementById('r-project');
  if (rp) rp.value = projectMeta.name || rp.value;
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
  await loadDaily();
  pendingDaily = {};

  populatePhaseFilters();
  applyTasksSubtitleCount();
  refreshSidebar();
  renderDashboard();
  renderDaily();
  renderAllTasks();
  await loadPhotosCategory(currentPhotoTab);
  renderPhotos();
  applyUiRolePermissions();
}

function updateClock() {
  document.getElementById('date-live').textContent =
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
    document.getElementById('st-late').textContent = dashboardData.stats.blocked;
    const ra = dashboardData.recent_activity;
    if (ra && ra.length) {
      document.getElementById('last-update').textContent = ra[0].time || '—';
    }
  } else {
    document.getElementById('st-total').textContent = tasks.length;
  }
}

function goTo(page) {
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
  const slug = displayStatusSlug(taskId);
  return { non_demarre: 'Non démarré', en_cours: 'En cours', termine: 'Terminé', bloque: 'Bloqué' }[slug] || slug;
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
  if (dailyFilter === 'ip') filtered = filtered.filter(t => slug(t.id) === 'en_cours' || displayProgress(t.id) > 0);
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
    const badgeCls = statusBadgeFromSlug(stSlug);
    const stLabel = displayStatusLabel(t.id);
    return `<div class="daily-task-row" id="drow-${t.id}" onclick="openModal(${t.id})">
      <div>
        <div class="task-name">${escapeHtml(t.subphase)} — ${escapeHtml(t.activity)}</div>
        <div class="task-phase">${escapeHtml(t.phase)}</div>
      </div>
      <div style="font-size:12px;color:var(--muted)">${escapeHtml(t.responsible)}</div>
      <div><span class="badge ${badgeCls}">${escapeHtml(stLabel)}</span></div>
      <div class="range-wrap" onclick="event.stopPropagation()">
        <input type="range" min="0" max="100" step="5" value="${prog}"
          data-id="${t.id}"
          oninput="quickUpdate(${t.id},this.value)"
          style="width:100%" ${isDirection() ? 'disabled' : ''}>
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
  if (isDirection()) return;
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
  if (isDirection()) return;
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
  document.getElementById('m-resp').value = t.responsible;
  document.getElementById('m-status').value = displayStatusSlug(id);
  document.getElementById('m-progress').value = displayProgress(id);
  document.getElementById('m-pct-lbl').textContent = displayProgress(id);
  document.getElementById('m-comment').value = '';
  document.getElementById('modal-task').classList.add('open');
}

function closeModal() {
  document.getElementById('modal-task').classList.remove('open');
}

async function saveTask() {
  if (isDirection()) return;
  const id = parseInt(document.getElementById('modal-task-id').value, 10);
  const t = tasks.find(x => x.id === id);
  if (!t) return;

  const progress = parseInt(document.getElementById('m-progress').value, 10);
  const status = document.getElementById('m-status').value;
  const comment = document.getElementById('m-comment').value.trim();
  const responsible = document.getElementById('m-resp').value;

  try {
    await apiFetch('/tasks/' + id, {
      method: 'PUT',
      body: JSON.stringify({ responsible }),
    });

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
      <td colspan="8">${escapeHtml(ph)}
        <span style="margin-left:12px;font-weight:400;color:var(--muted)">${phasePct}% complété</span>
      </td>
    </tr>`;
    pt.forEach(t => {
      const pctCls = t.progress === 100 ? 'fill-done' : t.progress > 50 ? 'fill-mid' : t.progress > 0 ? 'fill-low' : 'fill-0';
      html += `<tr>
        <td><div style="font-weight:600;font-size:12px">${escapeHtml(t.subphase)}</div></td>
        <td style="font-size:12px">${escapeHtml(t.activity)}</td>
        <td style="font-size:12px;color:var(--muted)">${escapeHtml(t.responsible)}</td>
        <td style="font-size:12px;color:var(--muted)">J+${t.startDay}</td>
        <td style="font-size:12px;color:var(--muted)">${t.duration}j</td>
        <td>
          <div class="pbar-wrap">
            <div class="pbar"><div class="pbar-fill ${pctCls}" style="width:${t.progress}%"></div></div>
            <div class="pct-num" style="color:${t.progress === 100 ? 'var(--ok)' : 'var(--accent)'}">${t.progress}%</div>
          </div>
        </td>
        <td><span class="badge ${statusBadgeFromSlug(t.status)}">${escapeHtml(t.status_label)}</span></td>
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

  const container = document.getElementById('photo-sections');
  container.innerHTML = `
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
      <div style="font-family:'Barlow Condensed',sans-serif;font-size:22px;font-weight:800">${info.label}</div>
      <div style="font-size:12px;color:var(--muted)">${info.desc}</div>
      <div style="margin-left:auto;background:${info.color};color:#fff;font-family:'Barlow Condensed',sans-serif;font-size:13px;font-weight:700;padding:4px 14px;border-radius:20px">
        ${photos.length} photo${photos.length !== 1 ? 's' : ''}
      </div>
    </div>

    <div class="drop-zone" id="dz-${currentPhotoTab}">
      <div class="dz-icon">📷</div>
      <div class="dz-text">
        <strong>Cliquez ici</strong> ou glissez vos photos — <strong>${info.label}</strong><br>
        <span style="font-size:11px">JPG, PNG · max 20 Mo par photo</span>
      </div>
    </div>
    <input type="file" id="ph-input-${currentPhotoTab}" multiple accept="image/*" style="display:none"
      onchange="addPhotos('${currentPhotoTab}', this.files)">

    <div class="photo-grid" id="pg-${currentPhotoTab}">
      ${photos.map((p, i) => photoThumb(p, i, currentPhotoTab)).join('') || emptyPhotos()}
    </div>
  `;

  const dz = document.getElementById('dz-' + currentPhotoTab);
  if (dz) {
    dz.onclick = () => { if (!isDirection()) document.getElementById('ph-input-' + currentPhotoTab).click(); };
    dz.style.opacity = isDirection() ? '0.55' : '';
    dz.style.pointerEvents = isDirection() ? 'none' : '';
  }
  if (dz && !isDirection()) {
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

function photoThumb(p, i, tab) {
  return `<div class="photo-item" onclick="openLB('${tab}',${i})">
    <img src="${p.src.replace(/"/g, '&quot;')}" alt="">
    <div class="photo-date-badge">${escapeHtml(p.date)}</div>
    <div class="photo-overlay">
      <div class="photo-overlay-btns">
        <button class="photo-action-btn del" onclick="event.stopPropagation();removePhoto('${tab}',${i})">✕ Suppr.</button>
      </div>
    </div>
  </div>`;
}

function emptyPhotos() {
  return `<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--muted);font-size:13px">
    Aucune photo dans cette catégorie — importez-en avec la zone ci-dessus.
  </div>`;
}

async function addPhotos(tab, files) {
  if (isDirection()) return;
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
  if (isDirection()) return;
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

async function previewReport() {
  document.getElementById('report-preview-area').innerHTML = buildReportHTML(false);
}

async function downloadReportPdf(reportId) {
  const token = localStorage.getItem(TOKEN_KEY);
  const res = await fetch(API_BASE + '/reports/' + reportId + '/pdf', {
    headers: { Authorization: 'Bearer ' + token, Accept: 'application/pdf' },
  });
  if (!res.ok) throw new Error('PDF indisponible');
  return res.blob();
}

async function printReport() {
  if (isDirection()) {
    toast('Génération réservée aux rôles opérationnels', 'err');
    return;
  }
  const date = document.getElementById('r-date').value;
  const temp = document.getElementById('r-temp').value;
  const weather = document.getElementById('r-weather').value;
  const page = document.getElementById('r-page').value;
  try {
    const gen = await apiFetch('/reports/generate', {
      method: 'POST',
      body: JSON.stringify({
        date,
        temperature: temp === '' ? null : parseFloat(temp),
        weather,
        page_number: page,
        notes: null,
      }),
    });
    lastReportId = gen.report.id;
    const blob = await downloadReportPdf(gen.report.id);
    const url = URL.createObjectURL(blob);
    window.open(url, '_blank');
    toast('PDF généré', 'ok');
  } catch (e) {
    toast(e.message || 'Erreur', 'err');
  }
}

function buildReportHTML(forPrint) {
  const date = document.getElementById('r-date').value;
  const temp = document.getElementById('r-temp').value;
  const weather = document.getElementById('r-weather').value;
  const project = document.getElementById('r-project').value;
  const page = document.getElementById('r-page').value;
  const pct = dashboardData ? dashboardData.overall_progress : overallProgress();

  const dateF = date ? new Date(date + 'T00:00:00').toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—';

  const phases = [...new Set(tasks.map(t => t.phase))];
  const statusColor = s => ({ Terminé: '#1a7a42', 'En cours': '#c8521a', 'Non démarré': '#8a8070', Bloqué: '#c01a1a' })[s] || '#888';

  const tableRows = phases.map(ph => {
    const pt = tasks.filter(t => t.phase === ph);
    return pt.map((t, i) => `
      <tr style="background:${i % 2 === 0 ? '#faf8f4' : '#fff'}">
        ${i === 0 ? `<td rowspan="${pt.length}" class="rp-phase-cell" style="border:0.5px solid #d5cfc2;border-right:2px solid #d5cfc2;vertical-align:top;padding:5px 7px">${escapeHtml(ph)}</td>` : ''}
        <td style="border:0.5px solid #d5cfc2;padding:5px 7px;font-size:9px">${escapeHtml(t.subphase)} — ${escapeHtml(t.activity)}</td>
        <td style="border:0.5px solid #d5cfc2;padding:5px 7px;text-align:center;font-size:9px">J+${t.startDay}</td>
        <td style="border:0.5px solid #d5cfc2;padding:5px 7px;text-align:center">
          <div style="display:flex;align-items:center;gap:4px">
            <div class="rp-mini-bar"><div class="rp-mini-fill" style="width:${t.progress}%;background:${t.progress === 100 ? '#1a7a42' : '#c8521a'}"></div></div>
            <span style="font-size:9px;font-weight:bold">${t.progress}%</span>
          </div>
        </td>
        <td style="border:0.5px solid #d5cfc2;padding:5px 7px;text-align:center;font-size:9px">${t.duration}j</td>
        <td style="border:0.5px solid #d5cfc2;padding:5px 7px;text-align:center;font-size:9px;font-weight:bold;color:${statusColor(t.status_label)}">${escapeHtml(t.status_label)}</td>
        <td style="border:0.5px solid #d5cfc2;padding:5px 7px;font-size:8px;color:#8a8070">${escapeHtml(t.responsible)}</td>
      </tr>
    `).join('');
  }).join('');

  const photosSection = Object.entries(photoStore).filter(([, v]) => v.length > 0).map(([cat, photos]) => `
    <div style="page-break-before:always;padding:10px">
      <div style="font-size:11px;font-weight:bold;color:#1a3a5c;margin-bottom:10px;border-bottom:2px solid #c8521a;padding-bottom:4px">
        Photos — ${PHOTO_LABELS[cat].label} (${photos.length})
      </div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">
        ${photos.map(p => `<img src="${p.src.replace(/"/g, '&quot;')}" style="width:100%;height:150px;object-fit:cover;border:1px solid #d5cfc2;border-radius:4px">`).join('')}
      </div>
    </div>
  `).join('');

  const printStyles = forPrint ? `<style>@media print{@page{size:A4 landscape;margin:8mm}}</style>` : '';

  return `${printStyles}
  <div style="font-family:Arial,sans-serif;font-size:10px;${forPrint ? '' : 'max-height:600px;overflow:auto'}">
    <div class="rp-header" style="display:grid;grid-template-columns:1fr auto;border-bottom:3px solid #1a3a5c;padding:12px 16px;margin-bottom:10px">
      <div>
        <div style="font-size:11px;font-weight:bold;color:#1a3a5c;text-align:center">${escapeHtml(project.toUpperCase())}</div>
        <div style="font-size:11px;font-weight:bold;color:#1a3a5c;text-align:center">PROGRESS (${pct}%)</div>
        <div style="font-size:16px;font-weight:bold;color:#c8521a;text-align:center;margin-top:4px">DAILY SITE PROGRESS REPORT</div>
      </div>
      <div style="border-left:2px solid #1a3a5c;padding-left:14px;font-size:11px;min-width:200px">
        <div style="margin-bottom:3px"><b style="color:#1a3a5c">Date :</b> <b style="color:#c8521a">${dateF}</b></div>
        <div style="margin-bottom:3px"><b style="color:#1a3a5c">Température :</b> <b style="color:#c8521a">${escapeHtml(temp)}°C</b></div>
        <div style="margin-bottom:3px"><b style="color:#1a3a5c">Météo :</b> <b style="color:#c8521a">${escapeHtml(weather)}</b></div>
        <div><b style="color:#1a3a5c">Page :</b> <b style="color:#c8521a">${escapeHtml(page)}</b></div>
      </div>
    </div>
    <table class="rp-table" style="width:100%;border-collapse:collapse;font-size:9px">
      <thead>
        <tr style="background:#8b2c1c">
          <th style="color:#fff;padding:5px 7px;text-align:left">Phase</th>
          <th style="color:#fff;padding:5px 7px;text-align:left">Activité</th>
          <th style="color:#fff;padding:5px 7px;text-align:center">Début</th>
          <th style="color:#fff;padding:5px 7px;text-align:center">Avancement</th>
          <th style="color:#fff;padding:5px 7px;text-align:center">Durée</th>
          <th style="color:#fff;padding:5px 7px;text-align:center">Statut</th>
          <th style="color:#fff;padding:5px 7px;text-align:left">Responsable</th>
        </tr>
      </thead>
      <tbody>${tableRows}</tbody>
    </table>
    <div style="text-align:center;background:#f4f1eb;padding:6px;margin-top:8px;font-weight:bold;font-size:10px;color:#1a3a5c">Overall Project Progress</div>
    <div style="text-align:center;font-size:28px;font-weight:bold;color:#c8521a;margin:6px 0">${pct}%</div>
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

document.getElementById('modal-task')?.addEventListener('click', function (e) {
  if (e.target === this) closeModal();
});

document.addEventListener('DOMContentLoaded', () => {
  const raw = localStorage.getItem(USER_KEY);
  if (raw) {
    try {
      currentUser = JSON.parse(raw);
      document.getElementById('user-av').textContent = currentUser.avatar_initials || currentUser.name.charAt(0);
      document.getElementById('user-nm').textContent = currentUser.name;
    } catch (_) {}
  }

  const token = localStorage.getItem(TOKEN_KEY);
  if (token) {
    apiFetch('/project')
      .then(() => {
        hideLogin();
        initApp();
      })
      .catch(() => {
        clearAuth();
        showLogin();
      });
  } else {
    showLogin();
  }
});
