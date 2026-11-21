@extends('layouts.app')
@section('title', 'Appointments')
@php $dentistView = auth()->user()->hasRole('dentist'); @endphp
@section('page_title', $dentistView ? 'My Appointments' : 'Manage Appointments')
@section('page_subtitle', 'Review and manage patient appointments.')
@section('page_actions')
  <a class="btn btn-outline-primary" href="{{ route('calendar') }}"><i class="bi bi-calendar3 me-1"></i>Appointment Calendar</a>
  @if (auth()->user()->hasRole('admin', 'clinic_staff'))<a class="btn btn-primary" href="{{ route('booking.staff') }}">Create Appointment</a>@endif
@endsection
@section('content')
@if ($isNeedsReschedule)
  <div class="alert alert-warning shadow-sm border-warning mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h5 class="alert-heading mb-1 text-dark fw-bold">⚠️ Needs Reschedule Queue ({{ $needsRescheduleCount }})</h5>
        <div class="small text-secondary">
          Appointments affected by clinic closures or schedule adjustments. Priority order:
          <strong>1)</strong> Patients already on the way (same-day), 
          <strong>2)</strong> Emergencies, 
          <strong>3)</strong> Multi-visit treatments (e.g. braces), 
          <strong>4)</strong> Everyone else by date.
        </div>
      </div>
      <span class="badge text-bg-warning fs-6">{{ $needsRescheduleCount }} Pending</span>
    </div>
  </div>

  @if (! empty($emergencyCallList) && $emergencyCallList->isNotEmpty())
    <div class="card border-danger shadow-sm mb-4">
      <div class="card-header bg-danger text-white fw-bold d-flex justify-content-between align-items-center">
        <span>📞 Priority Call List (Same-Day &amp; Emergencies)</span>
        <span class="badge bg-white text-danger">{{ $emergencyCallList->count() }} Patient(s)</span>
      </div>
      <div class="card-body p-0">
        <div class="p-2 bg-light small text-secondary border-bottom">
          Because SMS is not supported, patients with same-day visits or emergencies must be telephoned immediately while notification e-mails go out.
        </div>
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
                      📞 {{ $ec->patient->contact_number }}
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
                      Quick Reschedule…
                    </button>
                    <label class="ms-2 small"><input type="checkbox" class="form-check-input me-1"> Called</label>
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

<form class="card card-body mb-3" method="GET"><div class="row g-2 align-items-end">
  <div class="col-md-3"><label class="form-label small">Search patient</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Name, code or phone"></div>
  <div class="col-md-2"><label class="form-label small">Dentist</label><select name="dentist" class="form-select"><option value="">All dentists</option>@foreach ($dentists as $dentist)<option value="{{ $dentist->dentist_id }}" @selected(request('dentist') == $dentist->dentist_id)>{{ $dentist->name }}</option>@endforeach</select></div>
  <div class="col-md-2"><label class="form-label small">Procedure</label><select name="procedure" class="form-select"><option value="">All procedures</option>@foreach ($services as $service)<option value="{{ $service->service_id }}" @selected(request('procedure') == $service->service_id)>{{ $service->service_name }}</option>@endforeach</select></div>
  <div class="col-md-2"><label class="form-label small">Status</label><select name="status" class="form-select"><option value="">All</option>
    @foreach (['pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No-show', 'rescheduled' => 'Rescheduled', 'needs_reschedule' => 'Needs reschedule'] as $v => $l)
      <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach</select></div>
  <div class="col-md-2"><label class="form-label small">View period</label><select name="period" class="form-select">@foreach (['day'=>'Day','week'=>'Week','month'=>'Month','year'=>'Year'] as $key=>$label)<option value="{{ $key }}" @selected(request('period', 'day') === $key)>{{ $label }}</option>@endforeach</select></div>
  <div class="col-md-2"><label class="form-label small">Date</label><input type="date" name="date" value="{{ request('date', today()->toDateString()) }}" class="form-control"></div>
  <div class="col-md-2"><label class="form-label small">Sort by date</label><select name="sort" class="form-select"><option value="desc" @selected(request('sort', 'desc') === 'desc')>Descending</option><option value="asc" @selected(request('sort') === 'asc')>Ascending</option></select></div>
  <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill">Apply</button><a class="btn btn-light" href="{{ route('appointments.index') }}">Reset</a></div>
</div></form>
<div class="card">@include('appointments._table', ['rows' => $appointments, 'actions' => true, 'empty' => 'No appointments match your filters.'])</div>
<div class="mt-3">{{ $appointments->links('pagination::bootstrap-5') }}</div>
@include('appointments._modals')
@endsection
