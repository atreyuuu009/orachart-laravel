@extends('layouts.base')
@php
  $u = auth()->user();
  $role = $u->roleName();
  $nav = match ($role) {
    'patient' => [
      ['dashboard', 'Home', 'dashboard', 'bi-house-door'],
      ['booking.create', 'Book Appointment', 'booking.*', 'bi-calendar-plus'],
      ['appointments.mine', 'My Appointments', 'appointments.mine', 'bi-calendar-check'],
      ['patients.own', 'My Patient Record', 'patients.own', 'bi-file-earmark-medical'],
    ],
    'clinic_staff' => [
      ['dashboard', 'Dashboard', 'dashboard', 'bi-speedometer2'],
      ['patients.index', 'Patients', 'patients.*', 'bi-people'],
      ['booking.staff', 'Create Appointment', 'booking.staff*', 'bi-calendar-plus'],
      ['appointments.index', 'Manage Appointments', 'appointments.index', 'bi-calendar-check'],
      ['calendar', 'Appointment Calendar', 'calendar', 'bi-calendar3'],
    ],
    'dentist' => [
      ['dashboard', 'Dashboard', 'dashboard', 'bi-speedometer2'],
      ['appointments.index', 'My Appointments', 'appointments.index', 'bi-calendar-check'],
      ['calendar', 'My Schedule', 'calendar', 'bi-calendar3'],
      ['schedule', 'Hours & Time Off', 'schedule*', 'bi-clock-history'],
      ['odontogram', 'Digital Odontogram', 'odontogram*', 'bi-clipboard2-pulse'],
      ['patients.index', 'Patients', 'patients.*', 'bi-people'],
    ],
    'admin' => [
      ['dashboard', 'Dashboard', 'dashboard', 'bi-speedometer2'],
      ['appointments.index', 'View Appointments', 'appointments.index', 'bi-calendar-check'],
      ['patients.index', 'Patients', 'patients.*', 'bi-people'],
      ['booking.staff', 'Create Appointment', 'booking.staff*', 'bi-calendar-plus'],
      ['calendar', 'Appointment Calendar', 'calendar', 'bi-calendar3'],
      ['odontogram', 'Digital Odontogram', 'odontogram*', 'bi-clipboard2-pulse'],
      ['schedule', 'Dentist Schedules', 'schedule*', 'bi-clock-history'],
      ['admin.settings', 'Clinic Settings', 'admin.settings*', 'bi-gear'],
      ['admin.users', 'User Management', 'admin.users*', 'bi-person-gear'],
      ['admin.logs', 'Audit & E-mail Logs', 'admin.logs', 'bi-journal-text'],
    ],
    default => [['dashboard', 'Dashboard', 'dashboard', 'bi-speedometer2']],
  };
@endphp
@section('body')
<nav class="app-sidebar offcanvas-lg offcanvas-start d-flex flex-column p-3" tabindex="-1" id="sidebar">
  <div class="d-flex align-items-center gap-2 mb-3 px-1">
    <span class="sidebar-brand-box"><img class="brand-logo" src="{{ asset('images/logo-removebg-preview.png') }}" alt="Logo"></span>
    <div class="lh-sm">
      <div class="brand-title">Guda &amp; Soriano</div>
      <div class="brand-sub">Dental Clinic System</div>
    </div>
  </div>
  <div class="user-chip d-flex align-items-center gap-2 mb-3">
    <div class="avatar">{{ strtoupper(mb_substr($u->first_name, 0, 1)) }}</div>
    <div class="lh-sm text-truncate">
      <div class="fw-semibold text-truncate">{{ $u->full_name }}</div>
      <div class="user-role-badge text-truncate">{{ $u->role_label }}</div>
    </div>
  </div>
  <ul class="nav nav-pills flex-column gap-1 mb-auto">
    @foreach ($nav as [$route, $label, $pattern, $icon])
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ route($route) }}">
          <i class="bi {{ $icon }}"></i>
          <span>{{ $label }}</span>
        </a>
      </li>
    @endforeach
  </ul>
  <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf
    <button class="btn sidebar-logout-btn w-100">
      <i class="bi bi-box-arrow-right"></i>
      <span>Logout</span>
    </button>
  </form>
</nav>

<div class="app-main">
  <header class="topbar px-3 px-lg-4 py-3 d-flex align-items-center gap-3">
    <button class="btn btn-outline-primary d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Menu">&#9776;</button>
    <button class="btn sidebar-toggle d-none d-lg-inline-flex" type="button" id="sidebarToggle" aria-controls="sidebar" aria-expanded="true" aria-label="Collapse sidebar" title="Collapse sidebar">
      <i class="bi bi-list"></i>
    </button>
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