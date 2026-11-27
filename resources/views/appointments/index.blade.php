@extends('layouts.app')
@section('title', 'Appointments')
@php
  $dentistView = auth()->user()->hasRole('dentist');
  $activeStatus = (string) request('status', '');
  $activeFilterCount = collect([
    request('dentist'),
    request('procedure'),
    request('from'),
    request('to'),
    (request()->filled('date') && request('date') !== today()->toDateString()) ? request('date') : null,
    $filterPeriod === 'year' ? 'year' : null,
  ])->filter()->count();
@endphp
@section('page_title', $dentistView ? 'My Appointments' : 'Appointments')
@section('page_subtitle', 'Review and manage patient appointments.')
@section('page_actions')
  <div class="d-flex align-items-center gap-2">
    <a class="btn btn-outline-secondary bg-white" href="{{ route('calendar') }}">Calendar view</a>
    @if (auth()->user()->hasRole('admin', 'clinic_staff'))
      <a class="btn btn-primary" href="{{ route('booking.staff') }}">New appointment</a>
    @endif
  </div>
@endsection

@section('content')
@if ($isNeedsReschedule)
  <div class="alert alert-warning shadow-sm border-warning mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h6 class="alert-heading mb-1 text-dark fw-bold">Needs Reschedule Queue ({{ $needsRescheduleCount }})</h6>
        <div class="small text-secondary">
          Priority order: <strong>1)</strong> Same-day, <strong>2)</strong> Emergencies, <strong>3)</strong> Multi-visit treatments, <strong>4)</strong> Everyone else by date.
        </div>
      </div>
      <span class="badge text-bg-warning">{{ $needsRescheduleCount }} Pending</span>
    </div>
  </div>

  @if (! empty($emergencyCallList) && $emergencyCallList->isNotEmpty())
    <div class="card border-danger shadow-sm mb-3">
      <div class="card-header bg-danger text-white fw-bold d-flex justify-content-between align-items-center py-2">
        <span>Priority Call List (Same-Day &amp; Emergencies)</span>
        <span class="badge bg-white text-danger">{{ $emergencyCallList->count() }} Patient(s)</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Patient</th>
                <th>Contact</th>
                <th>Scheduled Time</th>
                <th>Procedure</th>
                <th>Dentist</th>
                <th>Priority</th>
                <th class="text-end">Staff Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($emergencyCallList as $ec)
                <tr>
                  <td class="fw-semibold">{{ $ec->patient->full_name }}</td>
                  <td>
                    <a href="tel:{{ $ec->patient->contact_number }}" class="btn btn-sm btn-outline-success fw-bold py-0">
                      {{ $ec->patient->contact_number }}
                    </a>
                  </td>
                  <td class="text-nowrap">{{ $ec->start_at->format('M j, g:i A') }}</td>
                  <td>{{ $ec->service->service_name }}</td>
                  <td>{{ $ec->dentist->name }}</td>
                  <td><span class="badge text-bg-{{ $ec->priority_badge_color }}">{{ $ec->priority_order_label }}</span></td>
                  <td class="text-end">
                    <button type="button" class="btn btn-primary btn-sm py-0 px-2 quick-reschedule-btn"
                      data-id="{{ $ec->appointment_id }}"
                      data-slots-url="{{ route('appointments.suggested_slots', $ec) }}"
                      data-reschedule-url="{{ route('appointments.reschedule.store', $ec) }}"
                      data-custom-url="{{ route('appointments.reschedule', $ec) }}"
                      data-cancel-url="{{ route('appointments.cancel', $ec) }}"
                      data-patient="{{ $ec->patient->full_name }}"
                      data-service="{{ $ec->service->service_name }}"
                      data-dentist="{{ $ec->dentist->name }}">
                      Reschedule
                    </button>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif
@endif

<form id="appointment-filter-form" method="GET" action="{{ route('appointments.index') }}">
  @if ($activeStatus !== '')
    <input type="hidden" name="status" value="{{ $activeStatus }}">
  @endif
  <input type="hidden" name="period" id="appointmentFilterPeriod" value="{{ $filterPeriod }}">
  <input type="hidden" name="date" id="appointmentFilterDate" value="{{ $filterDate->toDateString() }}">
  <input type="hidden" name="sort" id="appointmentFilterSort" value="{{ $sort }}">

  <div class="apt-toolbar">
    <div class="apt-search-wrap">
      <i class="bi bi-search"></i>
      <input type="search" name="q" value="{{ request('q') }}" class="form-control apt-search-input" placeholder="Search name, code or phone" aria-label="Search appointments">
    </div>

    <div class="apt-period-seg" role="group" aria-label="View period">
      @foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $pKey => $pLabel)
        <button type="button" class="apt-period-btn {{ $filterPeriod === $pKey ? 'active' : '' }}" data-period="{{ $pKey }}">{{ $pLabel }}</button>
      @endforeach
    </div>

    <button class="btn apt-filter-btn" type="button" data-bs-toggle="collapse" data-bs-target="#aptFiltersPanel" aria-expanded="false" aria-controls="aptFiltersPanel">
      <i class="bi bi-sliders"></i>
      <span>Filters</span>
      <span class="apt-filter-count">{{ $activeFilterCount }}</span>
    </button>
  </div>

  <div class="collapse mb-3" id="aptFiltersPanel">
    <div class="card card-body border shadow-sm">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label small">Dentist</label>
          <select name="dentist" class="form-select auto-submit">
            <option value="">All dentists</option>
            @foreach ($dentists as $dentist)
              <option value="{{ $dentist->dentist_id }}" @selected(request('dentist') == $dentist->dentist_id)>{{ $dentist->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small">Procedure</label>
          <select name="procedure" class="form-select auto-submit">
            <option value="">All procedures</option>
            @foreach ($services as $service)
              <option value="{{ $service->service_id }}" @selected(request('procedure') == $service->service_id)>{{ $service->service_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small">{{ ucfirst($filterPeriod) }}</label>
          @if ($filterPeriod === 'day')
            <input type="date" value="{{ $filterDate->toDateString() }}" class="form-control period-value">
          @elseif ($filterPeriod === 'week')
            <input type="week" value="{{ $filterDate->format('o-\\WW') }}" class="form-control period-value">
          @elseif ($filterPeriod === 'month')
            <input type="month" value="{{ $filterDate->format('Y-m') }}" class="form-control period-value">
          @else
            <input type="number" min="2000" max="2100" value="{{ $filterDate->format('Y') }}" class="form-control period-value" aria-label="Year">
          @endif
        </div>
        <div class="col-md-2">
          <label class="form-label small">Sort by date</label>
          <select class="form-select" id="aptSortSelect">
            <option value="desc" @selected($sort === 'desc')>Descending</option>
            <option value="asc" @selected($sort === 'asc')>Ascending</option>
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill">Apply</button>
          <a class="btn btn-light" href="{{ route('appointments.index') }}">Reset</a>
        </div>
      </div>
    </div>
  </div>
</form>

@php
  $tabs = [
    '' => ['label' => 'All', 'count' => $statusCounts['all'], 'warning' => false],
    'needs_reschedule' => ['label' => 'Needs reschedule', 'count' => $statusCounts['needs_reschedule'], 'warning' => true],
    'pending' => ['label' => 'Pending', 'count' => $statusCounts['pending'], 'warning' => false],
    'confirmed' => ['label' => 'Confirmed', 'count' => $statusCounts['confirmed'], 'warning' => false],
    'rescheduled' => ['label' => 'Rescheduled', 'count' => $statusCounts['rescheduled'], 'warning' => false],
    'completed' => ['label' => 'Completed', 'count' => $statusCounts['completed'], 'warning' => false],
    'cancelled' => ['label' => 'Cancelled', 'count' => $statusCounts['cancelled'], 'warning' => false],
    'no_show' => ['label' => 'No-show', 'count' => $statusCounts['no_show'], 'warning' => false],
  ];
@endphp

<div class="apt-status-bar">
  <div class="apt-status-tabs">
    @foreach ($tabs as $tabKey => $tab)
      @php
        $tabUrl = $tabKey === ''
          ? route('appointments.index', request()->except('status', 'page'))
          : route('appointments.index', array_merge(request()->except('page'), ['status' => $tabKey]));
      @endphp
      <a href="{{ $tabUrl }}" class="apt-status-tab {{ $activeStatus === $tabKey ? 'active' : '' }}">
        <span>{{ $tab['label'] }}</span>
        <span class="apt-tab-count {{ $tab['warning'] && $tab['count'] > 0 ? 'apt-tab-count-warning' : '' }}">{{ $tab['count'] }}</span>
      </a>
    @endforeach
  </div>
  <a href="{{ route('appointments.index', array_merge(request()->except('page'), ['sort' => $sort === 'asc' ? 'desc' : 'asc'])) }}"
     class="apt-sort-btn"
     title="Sort by date ({{ $sort === 'asc' ? 'Oldest first' : 'Newest first' }})"
     aria-label="Toggle date sort order">
    <i class="bi bi-arrow-down-up"></i>
  </a>
</div>

<script>
(() => {
  const form = document.querySelector('#appointment-filter-form');
  if (!form) return;

  form.querySelectorAll('.auto-submit').forEach(control => {
    control.addEventListener('change', () => form.requestSubmit());
  });

  form.querySelectorAll('[data-period]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('appointmentFilterPeriod').value = btn.dataset.period;
      form.requestSubmit();
    });
  });

  const sortSelect = document.getElementById('aptSortSelect');
  const sortHidden = document.getElementById('appointmentFilterSort');
  if (sortSelect && sortHidden) {
    sortSelect.addEventListener('change', () => {
      sortHidden.value = sortSelect.value;
      form.requestSubmit();
    });
  }

  const value = form.querySelector('.period-value');
  const date = form.querySelector('#appointmentFilterDate');
  if (value && date) {
    value.addEventListener('change', () => {
      if (value.type === 'week') {
        const match = value.value.match(/^(\d{4})-W(\d{2})$/);
        if (!match) return;
        const jan4 = new Date(Number(match[1]), 0, 4);
        const monday = new Date(jan4);
        monday.setDate(jan4.getDate() - ((jan4.getDay() + 6) % 7) + (Number(match[2]) - 1) * 7);
        date.value = `${monday.getFullYear()}-${String(monday.getMonth() + 1).padStart(2, '0')}-${String(monday.getDate()).padStart(2, '0')}`;
      } else if (value.type === 'month') {
        date.value = `${value.value}-01`;
      } else if (value.type === 'number') {
        date.value = `${value.value}-01-01`;
      } else {
        date.value = value.value;
      }
      form.requestSubmit();
    });
  }
})();
</script>

@include('appointments._table', ['rows' => $appointments, 'actions' => true, 'empty' => 'No appointments match your filters.'])
<div class="mt-3">{{ $appointments->links('pagination::bootstrap-5') }}</div>
@include('appointments._modals')
@endsection
