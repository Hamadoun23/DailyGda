  <!-- ── PRÉVISIONS MÉTÉO ── -->
  <div class="page" id="page-forecast">
    <div class="page-header">
      <div>
        <div class="page-title" data-i18n="page.forecast.title">Prévisions météo</div>
        <div class="page-sub" data-i18n="page.forecast.sub">Aide simple pour décider si le chantier peut avancer</div>
      </div>
      <button type="button" class="btn btn-secondary btn-sm" id="forecast-refresh-btn" onclick="loadForecastPage(true)" data-i18n="page.forecast.refresh">Actualiser</button>
    </div>

    <div id="forecast-root">
      <div class="forecast-loading card">
        <p data-i18n="page.forecast.loading">Chargement des prévisions…</p>
      </div>
    </div>
  </div>
