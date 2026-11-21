@extends('layouts.base')
@section('title', 'Guda & Soriano Dental Clinic')
@php
  $dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  $open = $hours->where('is_closed', false);
  $hoursText = $open->isEmpty() ? 'By appointment' : $dayNames[$open->first()->day_of_week].'–'.$dayNames[$open->last()->day_of_week];
@endphp
@section('body')
<nav class="landing-nav navbar navbar-expand-lg sticky-top">
  <div class="landing-nav-frame">
    <div class="landing-nav-inner">
      <div class="container-fluid px-3 px-xl-5">
    <a class="navbar-brand d-flex align-items-center gap-3" href="{{ route('landing') }}">
      <img class="brand-logo" src="{{ asset('images/logo-removebg-preview.png') }}" alt="Logo">
      <span class="lh-sm"><strong class="text-primary">Guda &amp; Soriano</strong><br><small class="text-secondary">Dental Clinic</small></span>
    </a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#lnav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="lnav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-4">
        <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
        <li class="nav-item"><a class="nav-link" href="#why">Why Us</a></li>
        <li class="nav-item"><a class="nav-link" href="#dentists">Dentists</a></li>
        @auth
          <li class="nav-item"><a class="btn btn-primary" href="{{ route('dashboard') }}">Go to my account</a></li>
        @else
          <li class="nav-item"><a class="nav-link nav-login" href="{{ route('login') }}">Login</a></li>
          <li class="nav-item"><a class="btn btn-primary" href="{{ route('register') }}">Register</a></li>
        @endauth
      </ul>
    </div>
      </div>
    </div>
  </div>
</nav>

<header class="landing-hero">
  <div class="container"><div class="row align-items-center g-5">
    <div class="col-lg-7">
      <div class="eyebrow mb-2">GUDA &amp; SORIANO DENTAL CLINIC</div>
      <h1 class="display-5 mb-3">Healthy smiles start with <span>trusted dental care.</span></h1>
      <p class="lead text-secondary">From cleanings and fillings to braces, extractions, and crowns — clear prices, careful treatment, and a calm clinic experience for every patient.</p>
      <div class="d-flex flex-wrap gap-2 my-4">
        <a class="btn btn-primary btn-lg" href="{{ auth()->check() ? route('booking.create') : route('register') }}">Book Appointment</a>
        <a class="btn btn-outline-primary btn-lg" href="#services">See Services &amp; Prices</a>
      </div>
      <div class="row text-center text-sm-start g-3 trust-item">
        <div class="col-4"><strong>{{ $dentists->count() }}</strong><span class="text-secondary small">Licensed Dentists</span></div>
        <div class="col-4"><strong>{{ $serviceCount }}+</strong><span class="text-secondary small">Dental Services</span></div>
        <div class="col-4"><strong>{{ $hoursText }}</strong><span class="text-secondary small">Clinic Hours</span></div>
      </div>
      <p class="small text-secondary mt-3 mb-0">Appointments are subject to clinic availability.</p>
    </div>
    <div class="col-lg-5"><div class="hero-logo-card"><img src="{{ asset('images/logo-removebg-preview.png') }}" alt="Guda &amp; Soriano Dental Clinic logo"></div></div>
  </div></div>
</header>

<section id="services" class="section-pad"><div class="container">
  <div class="text-center mb-4">
    <div class="eyebrow">OUR TREATMENTS</div>
    <h2>Dental Services &amp; Price Guide</h2>
    <p class="text-secondary">Browse our treatments and indicative prices before creating an account.</p>
  </div>
  @include('partials.services', ['categories' => $categories])
</div></section>

<section id="why" class="section-pad bg-white"><div class="container">
  <div class="text-center mb-4"><div class="eyebrow">WHY PATIENTS CHOOSE US</div><h2>Dental care that feels clear and comfortable</h2></div>
  <div class="row g-4">
    @foreach ([['🦷','Experienced dentists','Thorough exams, restorative care, and treatment plans you can understand.'],
               ['₱','Transparent pricing','See indicative service prices up front so you can plan with fewer surprises.'],
               ['📅','Easy online booking','Register, choose your procedure and preferred time, then manage appointments in one place.'],
               ['💙','Gentle clinic experience','A calm, respectful environment for first-time and anxious patients.']] as [$i, $t, $d])
      <div class="col-md-6 col-lg-3"><div class="why-icon">{{ $i }}</div><h5>{{ $t }}</h5><p class="text-secondary">{{ $d }}</p></div>
    @endforeach
  </div>
</div></section>

<section id="dentists" class="section-pad"><div class="container">
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

<section class="pb-5"><div class="container"><div class="cta-band p-4 p-md-5 d-flex flex-wrap align-items-center justify-content-between gap-3">
  <div><h2 class="h3 mb-1">Start your smile journey today</h2><p class="mb-0 opacity-75">Register as a new patient or log in to book with Guda &amp; Soriano Dental Clinic.</p></div>
  <div class="d-flex gap-2"><a class="btn btn-light" href="{{ route('login') }}">Login</a><a class="btn btn-outline-light" href="{{ route('register') }}">Register</a></div>
</div></div></section>

<footer class="bg-white border-top py-4"><div class="container d-flex flex-wrap justify-content-between gap-2 small text-secondary">
  <strong>Guda &amp; Soriano Dental Clinic</strong><span>© {{ date('Y') }} Dental Clinic Management System</span>
</div></footer>
@endsection
