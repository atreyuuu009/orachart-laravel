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
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('landing') }}">
          <span class="brand-tooth" aria-hidden="true">
            <svg viewBox="0 0 32 32" focusable="false">
              <path d="M10.1 4.2c-3.5 0-6.1 2.7-6.1 6.3 0 4.4 2.1 7.8 3 12.7.4 2 1.1 4.6 2.7 4.6 1.3 0 1.8-2.6 2.5-5.3.6-2.4 1.1-4 2.1-4s1.5 1.6 2.1 4c.7 2.7 1.2 5.3 2.5 5.3 1.6 0 2.3-2.6 2.7-4.6.9-4.9 3-8.3 3-12.7 0-3.6-2.6-6.3-6.1-6.3-1.8 0-3.1.9-4.2 2-1.1-1.1-2.4-2-4.2-2Z" fill="currentColor"/>
              <path d="M10.2 10.2c1.5-.6 2.9-.6 4.3 0m3.1 0c1.4-.6 2.8-.6 4.3 0" fill="none" stroke="#0b8e76" stroke-linecap="round" stroke-width="1.4"/>
            </svg>
          </span>
          <span class="lh-sm"><strong>Guda &amp; Soriano</strong><br><small>Dental Clinic</small></span>
        </a>
        @auth
          <a class="btn btn-primary" href="{{ route('dashboard') }}">My Account</a>
        @else
          <a class="btn btn-primary" href="{{ route('register') }}">Register</a>
        @endauth
      </div>
    </div>
  </div>
</nav>

<main class="landing-main">
<header class="landing-hero">
  <div class="container">
    <div class="eyebrow mb-3">Guda &amp; Soriano Dental Clinic</div>
    <h1>Healthy Smiles Start With <span>Trusted<br class="d-none d-md-block"> Dental Care.</span></h1>
    <p class="lead">From cleanings and fillings to braces, extractions, and crowns — clear<br class="d-none d-md-block"> prices, careful treatment, and a calm clinic experience for every<br class="d-none d-md-block"> patient.</p>
    <div class="d-flex flex-wrap justify-content-center gap-2 hero-actions">
      <a class="btn btn-primary btn-lg" href="{{ auth()->check() ? route('booking.create') : route('register') }}">Book Appointment <i class="bi bi-arrow-right ms-2"></i></a>
      <a class="btn btn-outline-primary btn-lg" href="#services">See Services &amp; Prices</a>
    </div>
    <div class="popular-treatments">
      <p>Browse our most popular <a href="#services">dental treatments</a></p>
      <div class="treatment-list">
        @foreach (['Cleaning', 'Braces', 'Fillings', 'Crowns', 'Whitening'] as $treatment)
          <a href="#services"><span aria-hidden="true">&#10022;</span>{{ $treatment }}</a>
        @endforeach
      </div>
    </div>
  </div>
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

</main>
<footer class="bg-white border-top py-4"><div class="container d-flex flex-wrap justify-content-between gap-2 small text-secondary">
  <strong>Guda &amp; Soriano Dental Clinic</strong><span>© {{ date('Y') }} Dental Clinic Management System</span>
</div></footer>
@endsection
