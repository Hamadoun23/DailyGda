@extends('layouts.gda')

@section('title', 'GDA — Chantier')

@section('content')
@include('chantier.partials.header')
@include('chantier.partials.sidebar')
@include('chantier.partials.overlays')

<!-- ===== MAIN ===== -->
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
  window.GDA_IS_PARTNER = false;
</script>
<script src="{{ asset('js/report-structure-en.js') }}"></script>
<script src="{{ asset('js/gda-i18n.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script src="{{ asset('js/gda-app.js') }}"></script>
@endpush
