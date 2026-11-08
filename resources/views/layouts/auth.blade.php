@extends('layouts.base')
@section('body')
<div class="auth-wrap">
  <div class="card auth-card border-0 p-4 p-md-5">
    <a href="{{ route('landing') }}" class="d-flex align-items-center gap-3 text-decoration-none mb-3">
      <img class="brand-logo" src="{{ asset('images/logo-removebg-preview.png') }}" alt="Guda & Soriano Dental Clinic">
      <div>
        <div class="fw-bold text-primary lh-sm">Guda &amp; Soriano Dental Clinic</div>
        <small class="text-secondary">Dental Clinic Management System</small>
      </div>
    </a>
    @include('partials.flash')
    @yield('content')
  </div>
</div>
@endsection
