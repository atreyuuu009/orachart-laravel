@extends('layouts.base')
@section('title', 'Guda & Soriano Dental Clinic')
@php
  $dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  $open = $hours->where('is_closed', false);
  $hoursText = $open->isEmpty() ? 'By appointment' : $dayNames[$open->first()->day_of_week].'–'.$dayNames[$open->last()->day_of_week];
@endphp
@section('body')
<nav class="landing-nav navbar navbar-expand-lg sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('landing') }}">
      <img class="brand-logo" src="{{ asset('images/logo-removebg-preview.png') }}" alt="Logo">
      <span class="lh-sm"><strong>Guda &amp; Soriano</strong><br><small>Dental Clinic</small></span>
    </a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#lnav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="lnav">
      <ul class="navbar-nav mx-auto align-items-lg-center gap-lg-3">
        <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
        <li class="nav-item"><a class="nav-link" href="#why">Why Us</a></li>
        <li class="nav-item"><a class="nav-link" href="#dentists">Dentists</a></li>
      </ul>
      <ul class="navbar-nav align-items-lg-center gap-lg-2">
        @auth
          <li class="nav-item"><a class="btn btn-primary" href="{{ route('dashboard') }}">Go to my account</a></li>
        @else
          <li class="nav-item"><a class="btn btn-outline-primary" href="{{ route('login') }}">Login</a></li>
          <li class="nav-item"><a class="btn btn-primary" href="{{ route('register') }}">Register</a></li>
        @endauth
      </ul>
    </div>
  </div>
</nav>

<header class="landing-hero">
  <div class="container hero-copy">
    <div class="availability-pill"><span>OPEN</span> {{ $hoursText }} · Appointments subject to availability</div>
    <h1>Healthy smiles start with<br>trusted dental care.</h1>
    <p>From cleanings and fillings to braces, extractions, and crowns — clear prices, careful treatment, and a calm clinic experience for every patient.</p>
    <div class="d-flex flex-wrap justify-content-center gap-2 hero-actions">
      <a class="btn btn-light btn-lg" href="{{ auth()->check() ? route('booking.create') : route('register') }}">Book Appointment <span class="hero-arrow">→</span></a>
      <a class="btn btn-hero-outline btn-lg" href="#services">See Services &amp; Prices</a>
    </div>
  </div>
  <div class="hero-art" aria-hidden="true">
    <span class="float-label label-cleaning">Cleaning</span><span class="float-label label-fillings">Fillings</span>
    <span class="float-label label-braces">Braces</span><span class="float-label label-crowns">Crowns &amp; Bridges</span>
    <span class="hero-spark spark-blue"></span><span class="hero-spark spark-yellow"></span>
    <div class="hero-tooth"><svg viewBox="0 0 120 140"><path d="M60 20C43 8 20 13 15 34c-5 19 4 38 8 54 4 18 8 41 18 39 10-2 12-36 19-36s10 34 20 36c10 2 14-21 18-39 4-16 13-35 8-54C101 13 78 8 60 20Z"/></svg></div>
    <div class="hero-platform"></div>
  </div>
</header>

<section id="services" class="section-pad scroll-reveal"><div class="container">
  <div class="text-center mb-4">
    <div class="eyebrow">OUR TREATMENTS</div>
    <h2>Dental Services &amp; Price Guide</h2>
    <p class="text-secondary">Browse our treatments and indicative prices before creating an account.</p>
  </div>
  @include('partials.services', ['categories' => $categories])
</div></section>

<section id="why" class="section-pad why-section scroll-reveal"><div class="container"><div class="row justify-content-center">
  <div class="col-lg-9">
    <div class="why-kicker"><span>＋</span> WHY PATIENTS CHOOSE US</div>
    <h2>Dental care that feels<br class="d-none d-lg-block"> clear and comfortable</h2>
    <div class="why-list">
      @foreach ([['person-check','Experienced dentists','Thorough exams, careful treatment, and plans you can understand.'],
                 ['cash-coin','Transparent pricing','See indicative prices up front so you can plan with fewer surprises.'],
                 ['calendar-check','Easy online booking','Choose your treatment and preferred time, and manage visits in one place.'],
                 ['heart','Gentle clinic experience','A calm, respectful environment for first-time and anxious patients.']] as [$icon, $t, $d])
        <div class="why-item"><div class="why-item-title"><i class="bi bi-{{ $icon }}"></i><strong>{{ $t }}</strong></div><p>{{ $d }}</p></div>
      @endforeach
    </div>
  </div>
</div></div></section>

<section id="dentists" class="section-pad scroll-reveal"><div class="container">
  <div class="text-center mb-4"><div class="eyebrow">MEET YOUR DENTISTS</div><h2>The doctors behind your smile</h2></div>
  <div class="row g-4 justify-content-center">
    @foreach ($dentists as $d)
      <div class="col-md-6 col-lg-5"><div class="card h-100"><div class="card-body d-flex gap-3">
        <div class="dentist-avatar">{{ $d->initials }}</div>
        <div><h5 class="mb-0">{{ $d->name }}</h5><div class="text-primary small fw-semibold mb-2">{{ $d->specialty_label }}</div>
          <p class="text-secondary mb-0">Licensed dental professional focused on quality care and clear communication.</p></div>
      </div></div></div>
    @endforeach
  </div>
</div></section>

<section class="pb-5 scroll-reveal"><div class="container"><div class="cta-band p-4 p-md-5 d-flex flex-wrap align-items-center justify-content-between gap-3">
  <div><h2 class="h3 mb-1">Start your smile journey today</h2><p class="mb-0 opacity-75">Register as a new patient or log in to book with Guda &amp; Soriano Dental Clinic.</p></div>
  <div class="d-flex gap-2"><a class="btn btn-light" href="{{ route('login') }}">Login</a><a class="btn btn-outline-light" href="{{ route('register') }}">Register</a></div>
</div></div></section>

<footer class="bg-white border-top py-4"><div class="container d-flex flex-wrap justify-content-between gap-2 small text-secondary">
  <strong>Guda &amp; Soriano Dental Clinic</strong><span>© {{ date('Y') }} Dental Clinic Management System</span>
</div></footer>
@endsection
