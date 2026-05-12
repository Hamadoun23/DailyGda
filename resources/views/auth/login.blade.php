@extends('layouts.gda')

@section('title', 'GDA — Connexion')

@section('content')
@include('auth.partials.login-card')
@endsection

@push('scripts')
<script>
  window.GDA_API_BASE = @json(url('/api'));
  window.GDA_APP_URL = @json(url('/'));
</script>
<script src="{{ asset('js/gda-login.js') }}"></script>
@endpush
