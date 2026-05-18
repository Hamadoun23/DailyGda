{{-- Bloc visuel identique à la page connexion GDA (réutilisable) --}}
<div id="login-screen" class="login-screen--bg-img" style="background-image:url('{{ asset('img/Bgconst.jpeg') }}')">
  <div class="login-box">
    @if (session('status'))
      <div class="login-flash login-flash--ok">{{ session('status') }}</div>
    @endif

    <img src="{{ asset('img/logoconst.png') }}?v={{ $gdaAssetVer ?? 1 }}" alt="GDA" class="brand-logo brand-logo--login brand-logo--login-only">
    <div class="login-sub">BuildOps Management</div>

    <form method="POST" action="{{ route('login') }}" class="login-form" autocomplete="off">
      @csrf

      <div class="form-group" style="margin-bottom:14px">
        <label class="form-label" for="username">Nom d'utilisateur</label>
        <input type="text" id="username" name="username" value="{{ $errors->any() ? old('username') : '' }}" required autofocus autocomplete="username" readonly onfocus="this.removeAttribute('readonly')">
        @error('username')
          <div class="login-field-error">{{ $message }}</div>
        @enderror
      </div>

      <div class="form-group login-password-group" style="margin-bottom:14px">
        <label class="form-label" for="password">Mot de passe</label>
        <div class="login-password-field">
          <input type="password" id="password" name="password" class="login-password-input" required autocomplete="current-password" readonly onfocus="this.removeAttribute('readonly')">
          <button type="button" class="login-password-toggle" id="login-password-toggle" aria-label="Afficher le mot de passe" aria-pressed="false" tabindex="0">
            <svg class="login-password-toggle__icon login-password-toggle__icon--show" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
            <svg class="login-password-toggle__icon login-password-toggle__icon--hide" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none">
              <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
              <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
              <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
              <line x1="2" y1="2" x2="22" y2="22"/>
            </svg>
          </button>
        </div>
        @error('password')
          <div class="login-field-error">{{ $message }}</div>
        @enderror
      </div>

      <div class="login-remember">
        <label class="login-remember-label">
          <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
          <span>Souviens-toi de moi</span>
        </label>
      </div>

      <button type="submit" class="btn btn-primary login-submit">Connexion</button>
    </form>

    <div class="login-sep"></div>
    <div class="login-footnote">
      Connectez-vous avec votre nom d’utilisateur et votre mot de passe pour accéder à l'application.
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

@push('scripts')
<script>
(function () {
  var pw = document.getElementById('password');
  var btn = document.getElementById('login-password-toggle');
  if (!pw || !btn) return;
  var iconShow = btn.querySelector('.login-password-toggle__icon--show');
  var iconHide = btn.querySelector('.login-password-toggle__icon--hide');
  btn.addEventListener('click', function (e) {
    e.preventDefault();
    var plain = pw.type !== 'text';
    pw.type = plain ? 'text' : 'password';
    btn.setAttribute('aria-pressed', plain ? 'true' : 'false');
    btn.setAttribute('aria-label', plain ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    if (iconShow && iconHide) {
      iconShow.style.display = plain ? 'none' : '';
      iconHide.style.display = plain ? '' : 'none';
    }
  });
})();
</script>
@endpush
