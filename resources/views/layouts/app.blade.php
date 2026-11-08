@extends('layouts.base')
@php
  $u = auth()->user();
  $role = $u->roleName();
  $nav = match ($role) {
    'patient' => [
      ['dashboard', 'Home', 'dashboard'],
      ['booking.create', 'Book Appointment', 'booking.*'],
      ['appointments.mine', 'My Appointments', 'appointments.mine'],
    ],
    'clinic_staff' => [
      ['dashboard', 'Dashboard', 'dashboard'],
      ['booking.staff', 'Create Appointment', 'booking.staff*'],
      ['appointments.index', 'Manage Appointments', 'appointments.index'],
      ['calendar', 'Appointment Calendar', 'calendar'],
    ],
    'dentist' => [
      ['dashboard', 'Dashboard', 'dashboard'],
      ['appointments.index', 'My Appointments', 'appointments.index'],
      ['calendar', 'My Schedule', 'calendar'],
      ['schedule', 'Hours & Time Off', 'schedule*'],
      ['odontogram', 'Digital Odontogram', 'odontogram*'],
    ],
    'admin' => [
      ['dashboard', 'Dashboard', 'dashboard'],
      ['appointments.index', 'View Appointments', 'appointments.index'],
      ['booking.staff', 'Create Appointment', 'booking.staff*'],
      ['calendar', 'Appointment Calendar', 'calendar'],
      ['odontogram', 'Digital Odontogram', 'odontogram*'],
      ['schedule', 'Dentist Schedules', 'schedule*'],
      ['admin.settings', 'Clinic Settings', 'admin.settings*'],
      ['admin.logs', 'Audit & E-mail Logs', 'admin.logs'],
    ],
    default => [['dashboard', 'Dashboard', 'dashboard']],
  };
@endphp
@section('body')
<nav class="app-sidebar offcanvas-lg offcanvas-start d-flex flex-column p-3" tabindex="-1" id="sidebar">
  <div class="d-flex align-items-center gap-2 mb-3 px-1">
    <span class="bg-white rounded-3 p-1"><img class="brand-logo" src="{{ asset('images/logo-removebg-preview.png') }}" alt="Logo"></span>
    <div class="lh-sm"><strong>Guda &amp; Soriano</strong><br><small class="opacity-75">Dental Clinic System</small></div>
  </div>
  <div class="user-chip d-flex align-items-center gap-2 mb-3">
    <div class="avatar">{{ strtoupper(mb_substr($u->first_name, 0, 1)) }}</div>
    <div class="lh-sm text-truncate"><strong>{{ $u->full_name }}</strong><br><small class="opacity-75">{{ $u->role_label }}</small></div>
  </div>
  <ul class="nav nav-pills flex-column gap-1 mb-auto">
    @foreach ($nav as [$route, $label, $pattern])
      <li class="nav-item"><a class="nav-link {{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ route($route) }}">{{ $label }}</a></li>
    @endforeach
  </ul>
  <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf
    <button class="btn btn-light w-100 fw-semibold">Logout</button>
  </form>
</nav>

<div class="app-main">
  <header class="topbar px-3 px-lg-4 py-3 d-flex align-items-center gap-3">
    <button class="btn btn-outline-primary d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Menu">&#9776;</button>
    <div>
      <h1 class="h4 mb-0">@yield('page_title')</h1>
      <small class="text-secondary">@yield('page_subtitle')</small>
    </div>
    <div class="ms-auto">@yield('page_actions')</div>
  </header>
  <main class="p-3 p-lg-4">
    @include('partials.flash')
    @yield('content')
  </main>
</div>
@endsection
