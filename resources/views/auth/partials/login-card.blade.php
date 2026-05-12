<div id="login-screen">
  <div class="login-box">
    <img src="{{ asset('img/GDACONST.png') }}" alt="GDA" class="brand-logo brand-logo--login">
    <div class="login-sub">Gestion de chantier</div>

    <div class="form-group" style="margin-bottom:14px">
      <label class="form-label">Email</label>
      <input type="email" id="login-email" autocomplete="username" placeholder="kone@gda.com" value="kone@gda.com">
    </div>
    <div class="form-group" style="margin-bottom:24px">
      <label class="form-label">Mot de passe</label>
      <input type="password" id="login-pass" autocomplete="current-password" placeholder="••••">
    </div>
    <button type="button" class="btn btn-primary" style="width:100%;justify-content:center;font-size:16px;padding:13px" onclick="doLogin()">
      Connexion →
    </button>
    <div class="login-sep"></div>
    <div style="font-size:11px;color:var(--muted);text-align:center">Projet : Station de lavage engins lourds<br>Construction en cours · Progression globale</div>
  </div>
</div>

<div class="toast" id="toast"></div>
