{{-- Bloc visuel identique à la page connexion GDA (réutilisable) --}}
<div id="login-screen" class="login-screen--bg-img">
  <div class="login-box">
    @if (session('status'))
      <div class="login-flash login-flash--ok">{{ session('status') }}</div>
    @endif

    <img src="{{ asset('img/logoconst.png') }}" alt="GDA" class="brand-logo brand-logo--login brand-logo--login-only">
    <div class="login-sub">Gestion de chantier</div>

    <form method="POST" action="{{ route('login') }}" class="login-form" autocomplete="off">
      @csrf

      <div class="form-group" style="margin-bottom:14px">
        <label class="form-label" for="username">Nom d'utilisateur</label>
        <input type="text" id="username" name="username" value="{{ $errors->any() ? old('username') : '' }}" required autofocus autocomplete="username" readonly onfocus="this.removeAttribute('readonly')">
        @error('username')
          <div class="login-field-error">{{ $message }}</div>
        @enderror
      </div>

      <div class="form-group" style="margin-bottom:14px">
        <label class="form-label" for="password">Mot de passe</label>
        <input type="password" id="password" name="password" required autocomplete="current-password" readonly onfocus="this.removeAttribute('readonly')">
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
      Connectez-vous avec votre nom d’utilisateur et votre mot de passe pour accéder au chantier.
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>
