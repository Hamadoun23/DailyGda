/**
 * Page de connexion GD&A — POST /api/login puis redirection /chantier
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
  var emailEl = document.getElementById('login-email');
  var passEl = document.getElementById('login-pass');
  var email = emailEl && emailEl.value ? emailEl.value.trim() : '';
  var password = passEl && passEl.value ? passEl.value : '';

  if (!email || !password) {
    toast('Email et mot de passe requis.', 'err');
    return;
  }

  try {
    var res = await fetch(API_BASE + '/login', {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: email, password: password }),
    });
    var data = await res.json().catch(function () {
      return {};
    });

    if (!res.ok) {
      var msg =
        data.message ||
        (data.errors && data.errors.email && data.errors.email[0]) ||
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

  var pass = document.getElementById('login-pass');
  if (pass) {
    pass.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') doLogin();
    });
  }
});
