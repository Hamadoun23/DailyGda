/**
 * Connexion GDA — POST /api/login (nom d'utilisateur + mot de passe) puis redirection.
 */
const API_BASE = window.GDA_API_BASE || window.location.origin + '/api';
const APP_URL = window.GDA_APP_URL || '/';
const TOKEN_KEY = 'gda_token';
const USER_KEY = 'gda_user';

function toast(msg, type) {
  const el = document.getElementById('toast');
  if (!el) {
    window.alert(msg);
    return;
  }
  el.textContent = msg;
  el.className = 'toast show ' + (type || '');
  clearTimeout(el._t);
  el._t = setTimeout(function () {
    el.classList.remove('show');
  }, 3500);
}

async function doLogin() {
  const userEl = document.getElementById('login-username') || document.getElementById('login-email');
  const passEl = document.getElementById('login-pass');
  const username = userEl && userEl.value ? userEl.value.trim() : '';
  const password = passEl && passEl.value ? passEl.value : '';

  if (!username || !password) {
    toast('Nom d\'utilisateur et mot de passe requis.', 'err');
    return;
  }

  try {
    const res = await fetch(API_BASE + '/login', {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ username: username, password: password }),
    });
    const data = await res.json().catch(function () {
      return {};
    });

    if (!res.ok) {
      const msg =
        data.message ||
        (data.errors && data.errors.username && data.errors.username[0]) ||
        'Connexion impossible.';
      toast(msg, 'err');
      return;
    }

    if (!data.token || !data.user) {
      toast('Réponse serveur inattendue.', 'err');
      return;
    }

    localStorage.setItem(TOKEN_KEY, data.token);
    localStorage.setItem(USER_KEY, JSON.stringify(data.user));
    window.location.href = APP_URL;
  } catch (e) {
    toast(e.message || 'Erreur réseau.', 'err');
  }
}

document.addEventListener('DOMContentLoaded', function () {
  if (localStorage.getItem(TOKEN_KEY)) {
    window.location.href = APP_URL;
  }

  const pass = document.getElementById('login-pass');
  if (pass) {
    pass.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') doLogin();
    });
  }
});
