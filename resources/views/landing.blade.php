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
        <a class="navbar-brand d-flex align-items-center" href="{{ route('landing') }}">
          <span class="brand-name">Guda &amp; Soriano</span>
          <span class="brand-divider" aria-hidden="true"></span>
          <span class="brand-clinic">Dental<br>Clinic</span>
        </a>
        @auth
          <a class="btn btn-primary nav-cta" href="{{ route('dashboard') }}">My Account <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></a>
        @else
          <div class="landing-auth-actions">
            <a class="landing-login-link" href="{{ route('login') }}"><i class="bi bi-person" aria-hidden="true"></i><span>Login</span></a>
            <a class="btn nav-cta landing-register-link" href="{{ route('register') }}"><span>Register</span><span class="landing-register-arrow"><i class="bi bi-arrow-right" aria-hidden="true"></i></span></a>
          </div>
        @endauth
      </div>
    </div>
  </div>
</nav>

<main class="landing-main">
<header class="landing-hero">
  <div class="container">
    <div class="hero-copy">
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
        @foreach ([['Cleaning','cleaning','bi-stars'], ['Braces','braces','bi-braces'], ['Fillings','fillings','bi-shield-plus'], ['Crowns','crowns','bi-award-fill'], ['Whitening','whitening','bi-brightness-high-fill']] as [$treatment, $categoryKey, $icon])
          @php $category = $categories->first(fn ($item) => str_contains(mb_strtolower($item->category_name), $categoryKey)); @endphp
          @if ($category)
            <button type="button" class="treatment-chip" data-service-cat="{{ $category->category_id }}"><span class="treatment-chip-icon" aria-hidden="true"><i class="bi {{ $icon }}"></i></span><span>{{ $treatment }}</span></button>
          @else
            <a class="treatment-chip" href="#services"><span class="treatment-chip-icon" aria-hidden="true"><i class="bi {{ $icon }}"></i></span><span>{{ $treatment }}</span></a>
          @endif
        @endforeach
      </div>
      <a class="treatment-all-link" href="#services">View all treatments <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>
    </div>
    <div class="hero-tooth-stage" aria-hidden="true">
      <div class="hero-tooth-glow"></div>
      <svg class="hero-tooth" viewBox="0 0 420 460" role="presentation" focusable="false">
        <defs>
          <linearGradient id="toothShell" x1=".12" y1=".04" x2=".9" y2=".94">
            <stop offset="0" stop-color="#f5ffff"/>
            <stop offset=".42" stop-color="#d8f2ef"/>
            <stop offset=".76" stop-color="#a9d7d4"/>
            <stop offset="1" stop-color="#75b5b2"/>
          </linearGradient>
          <linearGradient id="toothSide" x1="0" y1="0" x2="1" y2="1">
            <stop stop-color="#b7e2df"/>
            <stop offset="1" stop-color="#6ea9a8"/>
          </linearGradient>
          <filter id="toothShadow" x="-40%" y="-30%" width="180%" height="180%">
            <feGaussianBlur in="SourceAlpha" stdDeviation="12"/>
            <feOffset dy="14"/>
            <feComponentTransfer><feFuncA type="linear" slope=".3"/></feComponentTransfer>
            <feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge>
          </filter>
        </defs>
        <path class="tooth-depth" d="M210 76c-43-42-111-45-151-5-43 44-27 112-13 165l33 130c8 32 31 45 49 18l38-78c11-22 28-22 39 0l38 78c18 27 41 14 49-18l33-130c14-53 30-121-13-165-40-40-108-37-151 5z" transform="translate(18 8)"/>
        <path class="tooth-shell" filter="url(#toothShadow)" d="M210 76c-43-42-111-45-151-5-43 44-27 112-13 165l33 130c8 32 31 45 49 18l38-78c11-22 28-22 39 0l38 78c18 27 41 14 49-18l33-130c14-53 30-121-13-165-40-40-108-37-151 5z"/>
        <path class="tooth-highlight" d="M207 119c-34-31-86-34-116-4-30 31-17 83-6 126l29 107c4 15 12 18 21 2l34-70c23-47 50-47 73 0l34 70c9 16 17 13 21-2l29-107c11-43 24-95-6-126-30-30-82-27-116 4z"/>
        <path class="tooth-inner" d="M207 151c-26-22-62-23-83-2-22 23-11 62-3 94l23 84c3 10 7 11 13 1l27-54c17-34 37-34 54 0l27 54c6 10 10 9 13-1l23-84c8-32 19-71-3-94-21-21-57-20-83 2z"/>
        <path class="tooth-shine" d="M102 103c27-25 66-27 95-11M83 134c-12 25-6 61 1 88M318 103c-27-25-66-27-95-11"/>
      </svg>
    </div>
  </div>
</header>

<section id="services" class="section-pad"><div class="container">
  <div class="text-center mb-4">
    <div class="eyebrow">OUR TREATMENTS</div>
    <h2>Dental Services &amp; Price Guide</h2>
    <p class="text-secondary">Browse our treatments and indicative prices before creating an account.</p>
  </div>
  @include('partials.landing-services', ['categories' => $categories])
</div></section>

<section id="why" class="section-pad why-section"><div class="container">
  <div class="why-panel">
    <div class="text-center mb-4"><div class="eyebrow">WHY PATIENTS CHOOSE US</div><h2>Dental care that feels clear and comfortable</h2></div>
    <div class="row g-4 why-grid">
      @foreach ([['','', 'Experienced dentists','Thorough exams, restorative care, and treatment plans you can understand.'],
                 ['bi-coin','₱','Transparent pricing','See indicative service prices up front so you can plan with fewer surprises.'],
                 ['bi-calendar-event','', 'Easy online booking','Register, choose your procedure and preferred time, then manage appointments in one place.'],
                 ['bi-heart','', 'Gentle clinic experience','A calm, respectful environment for first-time and anxious patients.']] as [$i, $symbol, $t, $d])
        <div class="col-6 col-lg-3"><article class="why-item h-100">
          <div class="why-icon" aria-hidden="true">@if($symbol)<span class="why-icon-symbol">{{ $symbol }}</span>@elseif($t === 'Experienced dentists')<svg class="why-tooth-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7.2 3.5c-2.3 0-3.7 1.8-3.7 4.2 0 2.1.8 4.1 1.5 5.8.6 1.5 1.1 3 1.4 4.4.3 1.4.8 2.6 1.8 2.6 1.2 0 1.4-1.8 1.8-3.6.3-1.3.7-2.4 2-2.4s1.7 1.1 2 2.4c.4 1.8.6 3.6 1.8 3.6 1 0 1.5-1.2 1.8-2.6.3-1.4.8-2.9 1.4-4.4.7-1.7 1.5-3.7 1.5-5.8 0-2.4-1.4-4.2-3.7-4.2-1.8 0-2.8 1-4.8 1s-3-1-4.8-1Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>@else<i class="bi {{ $i }}"></i>@endif</div>
          <h3>{{ $t }}</h3><p>{{ $d }}</p>
        </article></div>
      @endforeach
    </div>
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
