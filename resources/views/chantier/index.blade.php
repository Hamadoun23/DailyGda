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
  window.GDA_LOGIN_URL = @json(route('login'));
  {{-- Auth désactivée temporairement : la racine affiche l’app sans token obligatoire --}}
  window.GDA_AUTH_REQUIRED = false;
</script>
<script src="{{ asset('js/report-structure-en.js') }}"></script>
<script src="{{ asset('js/gda-app.js') }}"></script>
@endpush
