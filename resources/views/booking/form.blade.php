@extends('layouts.app')
@section('title', 'Book appointment')
@section('page_title', $mode === 'staff' ? 'Create Appointment' : 'Book an Appointment')
@section('page_subtitle', $mode === 'staff' ? 'Schedule an appointment for a registered patient or walk-in.' : 'Schedule your dental appointment.')
@section('content')
<form method="POST" action="{{ $mode === 'staff' ? route('booking.staff.store') : route('booking.store') }}" id="bookingForm" class="card"><div class="card-body p-4">@csrf

  @if ($mode === 'staff')
    <h5 class="mb-3">Patient</h5>
    <div class="btn-group mb-3" role="group">
      <input type="radio" class="btn-check" name="patient_mode" id="pmExisting" value="existing" @checked(old('patient_mode', 'existing') === 'existing')>
      <label class="btn btn-outline-primary" for="pmExisting">Registered patient</label>
      <input type="radio" class="btn-check" name="patient_mode" id="pmNew" value="new" @checked(old('patient_mode') === 'new')>
      <label class="btn btn-outline-primary" for="pmNew">New / walk-in patient</label>
    </div>
    <div id="existingBox" class="mb-4">
      <select name="patient_id" class="form-select booking-compact-field">
        <option value="">Select a patient…</option>
        @foreach ($patients as $p)<option value="{{ $p->patient_id }}" @selected((string) old('patient_id') === (string) $p->patient_id)>{{ $p->last_name }}, {{ $p->first_name }} — {{ $p->patient_code }} — {{ $p->contact_number }}</option>@endforeach
      </select>
    </div>
    <div id="newBox" class="row g-3 mb-4 d-none">
      <div class="col-md-4"><label class="form-label">First name</label><input name="first_name" class="form-control" value="{{ old('first_name') }}"></div>
      <div class="col-md-4"><label class="form-label">Middle name</label><input name="middle_name" class="form-control" value="{{ old('middle_name') }}"></div>
      <div class="col-md-4"><label class="form-label">Last name</label><input name="last_name" class="form-control" value="{{ old('last_name') }}"></div>
      <div class="col-md-4"><label class="form-label">Contact number</label><input name="contact_number" type="tel" class="form-control" value="{{ old('contact_number') }}" placeholder="09XXXXXXXXX"></div>
      <div class="col-md-4"><label class="form-label">Date of birth</label><input name="birth_date" type="date" class="form-control" value="{{ old('birth_date') }}"></div>
      <div class="col-md-4"><label class="form-label">Sex</label><select name="sex" class="form-select"><option value="">—</option>@foreach (['male', 'female', 'other'] as $s)<option value="{{ $s }}" @selected(old('sex') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
      <div class="col-md-6"><label class="form-label">Email (optional, for confirmations)</label><input name="email" type="email" class="form-control" value="{{ old('email') }}"></div>
    </div>
  @else
    <div class="alert alert-light border">Booking as <strong>{{ $patient->full_name }}</strong> ({{ $patient->patient_code }}). Bookings need at least {{ $noticeHours }} hours' notice unless marked as an emergency.</div>
  @endif

  <h5 class="mb-3">Procedure</h5>
  <input type="hidden" name="service_id" id="serviceId" value="{{ old('service_id') }}">
  <button type="button" class="btn btn-outline-primary booking-compact-field text-start mb-1" data-bs-toggle="modal" data-bs-target="#procModal" id="procBtn">Select a procedure…</button>
  <div class="small text-secondary mb-4" id="procMeta"></div>

  <h5 class="mb-3">Schedule</h5>
  @include('booking._picker', ['dentists' => $dentists])

  <div class="mt-4"><label class="form-label">Reason / additional notes</label>
    <textarea name="notes" class="form-control" rows="3" maxlength="500" id="notes" placeholder="Describe your concern or symptoms…">{{ old('notes') }}</textarea>
    <div class="small text-danger d-none" id="urgentHint">Swelling or bleeding can be urgent. Consider ticking the emergency box above.</div></div>

  @if ($mode === 'patient')
    <div class="border rounded-3 p-3 mt-4">
      <strong>Health &amp; medication review</strong>
      <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="health_changed" id="hChanged" value="1" @checked(old('health_changed'))><label class="form-check-label" for="hChanged">My health condition or medication has changed</label></div>
      <div id="healthBox" class="d-none mt-2 row g-2">
        @php $hp = $patient->healthProfile; @endphp
        <div class="col-md-4"><label class="form-label small">Allergies</label><textarea name="allergies" class="form-control" rows="2">{{ old('allergies', $hp->allergies ?? '') }}</textarea></div>
        <div class="col-md-4"><label class="form-label small">Medical conditions</label><textarea name="medical_conditions" class="form-control" rows="2">{{ old('medical_conditions', $hp->medical_conditions ?? '') }}</textarea></div>
        <div class="col-md-4"><label class="form-label small">Current medications</label><textarea name="current_medications" class="form-control" rows="2">{{ old('current_medications', $hp->current_medications ?? '') }}</textarea></div>
      </div>
      <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="health_reviewed" id="hRev" value="1" required><label class="form-check-label" for="hRev">I reviewed my health information and confirm it is up to date.</label></div>
    </div>
  @endif

  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="{{ route('dashboard') }}">Cancel</a>
    <button class="btn btn-primary px-4" id="submitBtn">{{ $mode === 'staff' ? 'Create appointment' : 'Request appointment' }}</button>
  </div>
</div></form>

<div class="modal fade" id="procModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><div><h5 class="modal-title">What procedure do you need?</h5><small class="text-secondary">The appointment length is set by the procedure.</small></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="search" id="procSearch" class="form-control mb-3" placeholder="Search procedures…">
    @foreach ($categories as $c)
      <div class="proc-group mb-3"><div class="fw-bold text-primary mb-2">{{ $c->category_name }}</div>
        @foreach ($c->services as $s)
          <label class="proc-option mb-2" data-name="{{ strtolower($s->service_name) }}">
            <input type="radio" name="proc_pick" value="{{ $s->service_id }}" data-name="{{ $s->service_name }}" data-duration="{{ $s->duration_label }}" data-price="{{ $s->price_label }}">
            <span class="flex-fill"><strong>{{ $s->service_name }}</strong><br><small class="text-secondary">About {{ $s->duration_label }} · {{ $s->price_label }}</small></span>
          </label>
        @endforeach
      </div>
    @endforeach
  </div>
  <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="procOk" data-bs-dismiss="modal">Select</button></div>
</div></div></div>
@endsection
@push('scripts')
<script>window.BOOKING = {slotsUrl: @json(route('booking.slots')), holdUrl: @json(route('booking.hold')), exclude: null, mode: @json($mode)};</script>
<script src="{{ asset('js/booking.js') }}"></script>
@endpush
