@extends('layouts.app')
@section('title','User Management')
@section('page_title','User Management')
@section('page_subtitle','Manage account access and staff profiles.')
@section('page_actions')
  <a class="btn btn-primary" href="{{ route('admin.users.create') }}">Add Staff User</a>
@endsection

@section('content')
<style>
  .user-action { border: 0; border-radius: 999px; padding: .35rem .7rem; font-weight: 600; transition: transform .15s ease, box-shadow .15s ease; }
  .user-action:hover { transform: translateY(-1px); box-shadow: 0 .25rem .6rem #24324b20; }
  .user-action-edit { color: #6546a5; background: #f0eaff; }
  .user-action-deactivate { color: #a9435e; background: #ffe8ee; }
  .user-action-reactivate { color: #287b58; background: #e2f7eb; }
  .user-action-reset { color: #4866a8; background: #e8efff; }
  .user-action-unlock { color: #90651c; background: #fff3d6; }
  .user-action-verify { color: #39778a; background: #e2f6fa; }
</style>

@php
  $activeRole = (string) request('role', '');
  $activeStatus = (string) request('status', '');
  $roleLabels = ['admin' => 'Admin', 'clinic_staff' => 'Clinic Staff', 'dentist' => 'Dentist', 'patient' => 'Patient'];
@endphp

<form id="userFilters" method="GET" action="{{ route('admin.users') }}">
  @if ($activeStatus !== '')
    <input type="hidden" name="status" value="{{ $activeStatus }}">
  @endif
  <input type="hidden" name="role" id="userFilterRole" value="{{ $activeRole }}">

  <div class="apt-toolbar">
    <div class="apt-search-wrap">
      <i class="bi bi-search"></i>
      <input type="search" class="form-control apt-search-input" name="search" value="{{ request('search') }}" placeholder="Search name, username or email" aria-label="Search users">
    </div>

    <div class="apt-period-seg" role="group" aria-label="Filter by role">
      <button type="button" class="apt-period-btn {{ $activeRole === '' ? 'active' : '' }}" data-role="">All roles</button>
      @foreach ($roles as $role)
        <button type="button" class="apt-period-btn {{ $activeRole === $role->role_name ? 'active' : '' }}" data-role="{{ $role->role_name }}">
          {{ $roleLabels[$role->role_name] ?? ucfirst(str_replace('_', ' ', $role->role_name)) }}
        </button>
      @endforeach
    </div>

    @if (request()->anyFilled(['search', 'role', 'status']))
      <a class="btn apt-filter-btn" href="{{ route('admin.users') }}">Reset</a>
    @endif
  </div>
</form>

@php
  $statusTabs = [
    '' => ['label' => 'All', 'count' => $statusCounts['all'], 'warning' => false],
    'active' => ['label' => 'Active', 'count' => $statusCounts['active'], 'warning' => false],
    'inactive' => ['label' => 'Inactive', 'count' => $statusCounts['inactive'], 'warning' => true],
    'locked' => ['label' => 'Locked', 'count' => $statusCounts['locked'], 'warning' => true],
  ];
@endphp

<div class="apt-status-bar">
  <div class="apt-status-tabs">
    @foreach ($statusTabs as $tabKey => $tab)
      @php
        $tabUrl = $tabKey === ''
          ? route('admin.users', request()->except('status', 'page'))
          : route('admin.users', array_merge(request()->except('page'), ['status' => $tabKey]));
      @endphp
      <a href="{{ $tabUrl }}" class="apt-status-tab {{ $activeStatus === $tabKey ? 'active' : '' }}">
        <span>{{ $tab['label'] }}</span>
        <span class="apt-tab-count {{ $tab['warning'] && $tab['count'] > 0 ? 'apt-tab-count-warning' : '' }}">{{ $tab['count'] }}</span>
      </a>
    @endforeach
  </div>
</div>

<script>
(() => {
  const form = document.getElementById('userFilters');
  if (!form) return;
  form.querySelectorAll('[data-role]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('userFilterRole').value = btn.dataset.role;
      form.requestSubmit();
    });
  });
})();
</script>

<div class="apt-list-card">
  <div class="apt-date-header">
    <span>User accounts</span>
    <span class="apt-date-count">{{ $users->total() }} {{ \Illuminate\Support\Str::plural('user', $users->total()) }}</span>
  </div>

  @forelse($users as $u)
    <div class="usr-row {{ $u->is_active ? 'usr-row-active' : 'usr-row-inactive' }}">
      <div class="usr-col-main">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="fw-semibold text-dark">{{ $u->full_name }}</span>
          <span class="apt-badge apt-badge-rescheduled">{{ $u->role_label }}</span>
        </div>
        @if($u->hasRole('dentist'))
          <div class="pat-sub">
            License: {{ $u->dentist?->license_number ?: '—' }} · {{ $u->dentist?->specialties->pluck('specialty_name')->join(', ') ?: 'General Dentistry' }}<br>
            Online bookings: {{ $u->dentist?->is_accepting_bookings ? 'Yes' : 'No' }}
          </div>
        @elseif($u->hasRole('patient'))
          <div class="pat-sub">Patient code: {{ $u->patient?->patient_code ?: '—' }}</div>
        @endif
      </div>

      <div class="usr-col-contact">
        <div class="pat-contact-main">{{ $u->username }}</div>
        <div class="pat-sub">{{ $u->email }}</div>
        <div class="pat-sub">{{ $u->contact_number ?: 'No contact number' }}</div>
      </div>

      <div class="usr-col-status">
        <div class="d-flex flex-wrap gap-1 align-items-center">
          @if(!$u->is_active)
            <span class="apt-badge apt-badge-cancelled">Inactive</span>
          @else
            <span class="apt-badge apt-badge-completed">Active</span>
          @endif
          @if($u->locked_until?->isFuture())
            <span class="apt-badge apt-badge-emergency">Locked</span>
          @endif
        </div>
        <div class="pat-sub mt-1">{{ $u->is_email_verified ? 'Verified' : 'Unverified' }} · {{ $u->failed_login_count }} failed</div>
      </div>

      <div class="usr-col-activity">
        <div class="pat-visit-main">{{ $u->last_login_at?->format('Y-m-d H:i') ?: 'Never' }}</div>
        <div class="pat-sub">Created {{ $u->created_at?->format('Y-m-d') }}</div>
      </div>

      <div class="usr-col-actions">
        <div class="d-flex flex-wrap gap-1 justify-content-lg-end">
          <a class="btn btn-sm user-action user-action-edit" href="{{ route('admin.users.edit',$u) }}">Edit</a>
          @if($u->is_active)
            <form method="POST" action="{{ route('admin.users.deactivate',$u) }}">@csrf<button class="btn btn-sm user-action user-action-deactivate" onclick="return confirm('Deactivate this account?')">Deactivate</button></form>
          @else
            <form method="POST" action="{{ route('admin.users.reactivate',$u) }}">@csrf<button class="btn btn-sm user-action user-action-reactivate">Reactivate</button></form>
          @endif
          @if($u->locked_until)
            <form method="POST" action="{{ route('admin.users.unlock',$u) }}">@csrf<button class="btn btn-sm user-action user-action-unlock">Unlock</button></form>
          @endif
          <form method="POST" action="{{ route('admin.users.reset',$u) }}">@csrf<button class="btn btn-sm user-action user-action-reset">Send password reset</button></form>
          @if(!$u->is_email_verified)
            <form method="POST" action="{{ route('admin.users.verification',$u) }}">@csrf<button class="btn btn-sm user-action user-action-verify">Resend verification</button></form>
          @endif
        </div>
      </div>
    </div>
  @empty
    <div class="text-center text-secondary py-5">No users found.</div>
  @endforelse
</div>

<div class="mt-3">{{ $users->links('pagination::bootstrap-5') }}</div>
@endsection
