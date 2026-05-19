@extends('layouts.gda')

@push('body-class')
has-sidebar
@endpush

@section('title', 'GDA — Espace partenaire')

@section('content')
@include('partials.gda-header', ['activeNav' => 'chantier'])
@include('partner.partials.sidebar')
@include('chantier.partials.overlays')

<main class="main">
@include('chantier.partials.main-pages')
</main>
@endsection

@push('scripts')
<script>
  window.GDA_API_BASE = @json(url('/api'));
  window.GDA_APP_URL = @json(url('/'));
  window.GDA_LOGIN_URL = @json(route('login'));
  window.GDA_AUTH_REQUIRED = true;
  window.GDA_IS_PARTNER = true;
</script>
<script src="{{ asset('js/report-structure-en.js') }}?v={{ $gdaAssetVer ?? 1 }}"></script>
<script src="{{ asset('js/gda-i18n.js') }}?v={{ $gdaAssetVer ?? 1 }}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script src="{{ asset('js/gda-app.js') }}?v={{ $gdaAssetVer ?? 1 }}"></script>
@endpush
