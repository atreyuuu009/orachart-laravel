@extends('layouts.app')
@section('title', 'Clinic Settings')
@section('page_title', 'Clinic Settings')
@section('page_subtitle', 'Working hours, closures, booking rules, dentists and emergency windows.')
@section('content')
@php
  $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  $tab = session('tab', old('_tab', 'hours'));
  $tabs = ['hours' => 'Weekly hours', 'overrides' => 'Date overrides', 'rules' => 'Booking rules', 'gcash' => 'GCash QR', 'dentists' => 'Dentists', 'emergency' => 'Emergency windows'];
@endphp
<ul class="nav nav-tabs mb-3" role="tablist">
  @foreach ($tabs as $k => $l)<li class="nav-item"><button class="nav-link {{ $tab === $k ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#t-{{ $k }}" type="button">{{ $l }}</button></li>@endforeach
</ul>
<div class="tab-content">

<div class="tab-pane fade {{ $tab === 'hours' ? 'show active' : '' }}" id="t-hours">
  <form method="POST" action="{{ route('admin.settings.hours') }}" class="card">@csrf<div class="card-body">
    @php
      $firstHour = $hours->first();
      $sameHoursEveryDay = $hours->count() === 7 && $hours->every(fn ($h) => ! $h->is_closed && $h->open_time === $firstHour->open_time && $h->close_time === $firstHour->close_time);
      $defaultOpen = old('default_open', $sameHoursEveryDay ? substr($firstHour->open_time, 0, 5) : '08:00');
      $defaultClose = old('default_close', $sameHoursEveryDay ? substr($firstHour->close_time, 0, 5) : '19:00');
    @endphp
    <p class="text-secondary">Set one default schedule for every day. It applies to patient appointment availability for all upcoming dates. Use a date override for holidays or special days.</p>
    <div class="row g-3 align-items-end">
      <div class="col-sm-4"><label class="form-label fw-semibold" for="defaultOpen">Default working hours</label><input id="defaultOpen" type="time" class="form-control" name="default_open" value="{{ $defaultOpen }}" required></div>
      <div class="col-sm-4"><label class="form-label" for="defaultClose">Until</label><input id="defaultClose" type="time" class="form-control" name="default_close" value="{{ $defaultClose }}" required></div>
      <div class="col-sm-4 text-secondary small">{{ $sameHoursEveryDay ? 'Currently applied every day.' : 'Saving applies these hours Sunday through Saturday.' }}</div>
    </div>
  </div><div class="card-footer bg-white text-end"><button class="btn btn-primary">Save hours</button></div></form>
</div>

<div class="tab-pane fade {{ $tab === 'overrides' ? 'show active' : '' }}" id="t-overrides">
  @if (session('call_list'))
    <div class="alert alert-danger shadow-sm border-danger mb-4">
      <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <h5 class="alert-heading mb-0 text-danger fw-bold">
          📞 Urgent Patient Call List — Same-Day / Emergency Closure ({{ session('call_list_date') }})
        </h5>
        <span class="badge bg-danger">Action Required</span>
      </div>
      <p class="mb-2 small">
        Reason: <strong>{{ session('call_list_reason') }}</strong>.<br>
        <strong>Important:</strong> Because SMS is not supported, patients with same-day or short-notice closures must be called by phone immediately to notify them of the closure and coordinate rescheduling.
      </p>
      <div class="table-responsive bg-white rounded border">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Patient</th>
              <th>Contact Number</th>
              <th>Scheduled Time</th>
              <th>Procedure</th>
              <th>Dentist</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @foreach (session('call_list') as $call)
              <tr>
                <td class="fw-semibold">{{ $call['patient_name'] }}</td>
                <td>
                  <a href="tel:{{ $call['contact_number'] }}" class="btn btn-sm btn-outline-success fw-bold py-0">
                    📞 {{ $call['contact_number'] }}
                  </a>
                </td>
                <td>{{ $call['time'] }}</td>
                <td>{{ $call['service_name'] }}</td>
                <td>{{ $call['dentist_name'] }}</td>
                <td><span class="badge text-bg-warning">{{ $call['status'] }}</span></td>
                <td>
                  <label class="form-check-label small"><input type="checkbox" class="form-check-input me-1"> Called</label>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  <div class="row g-4">
    <div class="col-lg-5">
      <form method="POST" action="{{ route('admin.settings.overrides.store') }}" id="overrideForm" class="card">
        @csrf
        <input type="hidden" name="confirmed" id="overrideConfirmed" value="0">
        <div class="card-body row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Closure Date</label>
            <input type="date" name="override_date" id="ovDate" min="{{ today()->toDateString() }}" class="form-control" value="{{ old('override_date', today()->toDateString()) }}" required>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Closure Type</label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="closure_type" id="typePlanned" value="planned" @checked(old('closure_type', 'planned') === 'planned')>
                <label class="form-check-label" for="typePlanned">
                  <strong>Planned closure</strong>
                  <div class="small text-secondary">Advance notice, regular holiday, renovations.</div>
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="closure_type" id="typeEmergency" value="emergency" @checked(old('closure_type') === 'emergency')>
                <label class="form-check-label" for="typeEmergency">
                  <strong class="text-danger">Emergency closure</strong>
                  <div class="small text-secondary">Same-day or within hours. Generates patient call list.</div>
                </label>
              </div>
            </div>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_closed" value="1" id="ovClosed" @checked(old('is_closed', 1))>
              <label class="form-check-label fw-semibold" for="ovClosed">Clinic closed all day</label>
            </div>
          </div>
          <div class="col-6 ov-hours-wrap {{ old('is_closed', 1) ? 'd-none' : '' }}">
            <label class="form-label">Opens</label>
            <input type="time" name="start_time" id="ovStartTime" class="form-control" value="{{ old('start_time') }}">
          </div>
          <div class="col-6 ov-hours-wrap {{ old('is_closed', 1) ? 'd-none' : '' }}">
            <label class="form-label">Closes</label>
            <input type="time" name="end_time" id="ovEndTime" class="form-control" value="{{ old('end_time') }}">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Reason for closure <span class="text-danger">*</span></label>
            <input name="reason" id="ovReason" class="form-control" maxlength="255" placeholder="e.g. Typhoon / Power Outage / Dental Staff Emergency / Holiday" value="{{ old('reason') }}" required>
            <div class="form-text">Reason is required. It is included in patient notification e-mails and audit logs.</div>
          </div>
        </div>
        <div class="card-footer bg-white text-end">
          <button type="submit" id="saveOverrideBtn" class="btn btn-primary">Save closure</button>
        </div>
      </form>
    </div>

    <div class="col-lg-7">
      <div class="card">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
          <span>Upcoming date overrides</span>
        </div>
        <ul class="list-group list-group-flush">
          @forelse ($overrides as $o)
            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <strong>{{ $o->override_date->format('D, M j, Y') }}</strong>
                @if (($o->closure_type ?? 'planned') === 'emergency')
                  <span class="badge text-bg-danger ms-1">Emergency</span>
                @else
                  <span class="badge text-bg-secondary ms-1">Planned</span>
                @endif
                <div class="small text-secondary">
                  {{ $o->is_closed ? 'Closed all day' : substr($o->start_time, 0, 5).' – '.substr($o->end_time, 0, 5) }}
                  {{ $o->reason ? '· '.$o->reason : '' }}
                </div>
              </div>
              <div class="d-flex gap-2 align-items-center">
                <button type="button" class="btn btn-sm btn-outline-secondary view-call-list-btn" data-url="{{ route('admin.settings.overrides.calllist', $o) }}">
                  📞 Call List
                </button>
                <form method="POST" action="{{ route('admin.settings.overrides.destroy', $o) }}" onsubmit="return confirm('Remove this date override?');">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger">Remove</button>
                </form>
              </div>
            </li>
          @empty
            <li class="list-group-item text-secondary text-center py-4">No upcoming overrides.</li>
          @endforelse
        </ul>
      </div>
    </div>
  </div>
</div>

{{-- Confirmation Modal before saving closure --}}
<div class="modal fade" id="closureConfirmModal" tabindex="-1" aria-labelledby="closureConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning bg-opacity-25 border-warning">
        <h5 class="modal-title fw-bold text-dark" id="closureConfirmModalLabel">Confirm Clinic Closure</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning mb-3">
          <div class="fw-bold fs-6" id="closureConfirmMessage"></div>
          <div class="small mt-1 text-secondary">
            Date slots will disappear from online booking immediately, mid-booking holds will be deleted, queued reminders cancelled, and apology e-mails sent to all affected patients with free reschedule links.
          </div>
        </div>

        <div id="closureEmergencyNotice" class="alert alert-danger py-2 d-none">
          <strong>🚨 Emergency Closure Notice:</strong> Because SMS is not supported, an e-mail alone is not enough for same-day emergencies. A <strong>Patient Call List</strong> with telephone numbers will be generated upon saving so staff can call patients right away.
        </div>

        <div class="fw-semibold mb-2">Affected Patients &amp; Scheduled Appointments:</div>
        <div class="table-responsive border rounded" style="max-height: 280px; overflow-y: auto;">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead class="table-light sticky-top">
              <tr>
                <th>Time</th>
                <th>Patient</th>
                <th>Contact</th>
                <th>Procedure</th>
                <th>Dentist</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="closureConfirmTableBody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmSaveClosureBtn" class="btn btn-danger fw-bold">Continue &amp; Save Closure</button>
      </div>
    </div>
  </div>
</div>

{{-- Existing Override Call List Modal --}}
<div class="modal fade" id="existingCallListModal" tabindex="-1" aria-labelledby="existingCallListModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="existingCallListModalLabel">Patient Call List</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info py-2 small mb-3" id="existingCallListInfo"></div>
        <div class="table-responsive border rounded" style="max-height: 320px; overflow-y: auto;">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light sticky-top">
              <tr>
                <th>Patient</th>
                <th>Contact Number</th>
                <th>Time</th>
                <th>Procedure</th>
                <th>Dentist</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="existingCallListBody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="tab-pane fade {{ $tab === 'rules' ? 'show active' : '' }}" id="t-rules">
  <form method="POST" action="{{ route('admin.settings.rules') }}" class="card">@csrf<div class="card-body"><div class="row g-3">
    @foreach ([
      ['slot_interval_minutes', 'Slot interval (minutes)', 5, 120], ['buffer_before_minutes', 'Buffer before treatment (min)', 0, 120],
      ['buffer_after_minutes', 'Buffer after treatment (min)', 0, 120], ['min_booking_notice_hours', 'Minimum booking notice (hours)', 0, 168],
      ['slot_lock_minutes', 'Slot hold while booking (min)', 1, 30], ['max_patients_per_day', 'Max patients per day (clinic)', 1, 200],
      ['emergency_release_minutes', 'Release unused emergency slots (min before)', 0, 240], ['arrival_notice_minutes', 'Ask patients to arrive early (min)', 0, 120],
      ['reminder_hours_before', 'Reminder e-mail (hours before)', 1, 168], ['session_timeout_minutes', 'Inactivity logout (minutes)', 5, 120],
      ['installment_min_amount', 'Installment minimum (₱)', 0, 10000000], ['installment_max_months', 'Installment maximum months', 2, 60], ['installment_reminder_days', 'Installment reminder lead days', 1, 30],
    ] as [$k, $label, $min, $max])
      <div class="col-md-6 col-lg-4"><label class="form-label">{{ $label }}</label><input type="number" class="form-control" name="{{ $k }}" min="{{ $min }}" max="{{ $max }}" value="{{ old($k, $rules[$k]) }}" required></div>
    @endforeach
    <div class="col-12"><hr></div>
    @foreach (['auto_confirm_bookings' => 'Auto-confirm patient bookings (otherwise staff must confirm each request)',
               'reminders_enabled' => 'Send reminder, thank-you and recall e-mails',
               'enforce_service_specialty' => 'Only offer a procedure to dentists with its required specialty'] as $k => $label)
      <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="{{ $k }}" value="1" id="f{{ $k }}" @checked($flags[$k])><label class="form-check-label" for="f{{ $k }}">{{ $label }}</label></div></div>
    @endforeach
  </div></div><div class="card-footer bg-white text-end"><button class="btn btn-primary">Save rules</button></div></form>
</div>

<div class="tab-pane fade {{ $tab === 'gcash' ? 'show active' : '' }}" id="t-gcash"><div class="card"><div class="card-header bg-white fw-bold">GCash payment QR</div><div class="card-body">
  <p class="text-secondary">Patients use this clinic QR to make installment payments. Updating the image replaces the one shown in their payment instructions.</p>
  @if (\App\Support\Settings::get('gcash_qr_path'))<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url(\App\Support\Settings::get('gcash_qr_path')) }}" alt="Clinic GCash QR" style="max-width:240px" class="d-block mb-3">@endif
  <form method="POST" enctype="multipart/form-data" action="{{ route('admin.settings.gcash') }}">@csrf<div class="row g-2"><div class="col-md-4"><label class="form-label">GCash account name</label><input class="form-control" name="gcash_account_name" maxlength="100" value="{{ \App\Support\Settings::get('gcash_account_name') }}"></div><div class="col-md-4"><label class="form-label">GCash account number</label><input class="form-control" name="gcash_account_number" maxlength="40" value="{{ \App\Support\Settings::get('gcash_account_number') }}"></div><div class="col-md-4"><label class="form-label">Replace QR image</label><input class="form-control" type="file" name="gcash_qr" accept="image/jpeg,image/png,image/webp"></div><div class="col-12"><button class="btn btn-primary">Save GCash details</button></div></div></form>
</div></div></div>

<div class="tab-pane fade {{ $tab === 'dentists' ? 'show active' : '' }}" id="t-dentists">
  <form method="POST" action="{{ route('admin.settings.dentists') }}" class="card">@csrf<div class="card-body">
    @foreach ($dentists as $d)
      <div class="border rounded-3 p-3 mb-3">
        <div class="d-flex justify-content-between flex-wrap gap-2"><strong>{{ $d->name }}</strong>
          <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="accepting[{{ $d->dentist_id }}]" value="1" id="acc{{ $d->dentist_id }}" @checked($d->is_accepting_bookings)><label class="form-check-label" for="acc{{ $d->dentist_id }}">Accepting online bookings</label></div></div>
        <div class="mt-2 d-flex flex-wrap gap-3">
          @foreach ($specialties as $s)
            <div class="form-check"><input class="form-check-input" type="checkbox" name="specialties[{{ $d->dentist_id }}][]" value="{{ $s->specialty_id }}" id="sp{{ $d->dentist_id }}-{{ $s->specialty_id }}" @checked($d->specialties->contains('specialty_id', $s->specialty_id))><label class="form-check-label" for="sp{{ $d->dentist_id }}-{{ $s->specialty_id }}">{{ $s->specialty_name }}</label></div>
          @endforeach
        </div>
      </div>
    @endforeach
    <div class="form-text">Weekly working hours for each dentist are under <a href="{{ route('schedule') }}">Dentist Schedules</a>.</div>
  </div><div class="card-footer bg-white text-end"><button class="btn btn-primary">Save dentists</button></div></form>
</div>

<div class="tab-pane fade {{ $tab === 'emergency' ? 'show active' : '' }}" id="t-emergency"><div class="row g-4">
  <div class="col-lg-5"><form method="POST" action="{{ route('admin.settings.emergency.store') }}" class="card">@csrf<div class="card-body row g-2">
    <div class="col-12 text-secondary small">Reserved windows are hidden from normal bookings and released shortly before they start. Emergency bookings can use them.</div>
    <div class="col-6"><label class="form-label">Day</label><select name="day_of_week" class="form-select">@foreach ($days as $i => $n)<option value="{{ $i }}">{{ $n }}</option>@endforeach</select></div>
    <div class="col-6"><label class="form-label">Dentist</label><select name="dentist_id" class="form-select"><option value="">Any dentist</option>@foreach ($dentistNames as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
    <div class="col-6"><label class="form-label">From</label><input type="time" name="start_time" class="form-control" required></div>
    <div class="col-6"><label class="form-label">To</label><input type="time" name="end_time" class="form-control" required></div>
  </div><div class="card-footer bg-white text-end"><button class="btn btn-primary">Add window</button></div></form></div>
  <div class="col-lg-7"><div class="card"><div class="card-header bg-white fw-bold">Reserved emergency windows</div><ul class="list-group list-group-flush">
    @forelse ($blocks as $b)
      <li class="list-group-item d-flex justify-content-between align-items-center"><div><strong>{{ $days[$b->day_of_week] }}</strong> {{ substr($b->start_time, 0, 5) }} – {{ substr($b->end_time, 0, 5) }}
        <span class="text-secondary small">· {{ $b->dentist_id ? ($dentistNames[$b->dentist_id] ?? 'Dentist') : 'Any dentist' }}</span></div>
        <form method="POST" action="{{ route('admin.settings.emergency.destroy', $b) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form></li>
    @empty<li class="list-group-item text-secondary text-center">No emergency windows.</li>@endforelse
  </ul></div></div>
</div></div>

</div>
@endsection

@push('scripts')
<script>
(() => {
  const ovClosed = document.getElementById('ovClosed');
  const ovHoursWraps = document.querySelectorAll('.ov-hours-wrap');
  const ovStartTime = document.getElementById('ovStartTime');
  const ovEndTime = document.getElementById('ovEndTime');
  const ovDate = document.getElementById('ovDate');
  const typeEmergency = document.getElementById('typeEmergency');
  const typePlanned = document.getElementById('typePlanned');
  const overrideForm = document.getElementById('overrideForm');
  const overrideConfirmed = document.getElementById('overrideConfirmed');
  const saveOverrideBtn = document.getElementById('saveOverrideBtn');

  // Toggle hours input
  const toggleHours = () => {
    const isClosed = ovClosed.checked;
    ovHoursWraps.forEach(el => el.classList.toggle('d-none', isClosed));
    if (ovStartTime) ovStartTime.required = !isClosed;
    if (ovEndTime) ovEndTime.required = !isClosed;
  };
  if (ovClosed) {
    ovClosed.addEventListener('change', toggleHours);
    toggleHours();
  }

  // Suggest emergency if date is today
  const checkDateEmergency = () => {
    if (!ovDate) return;
    const today = new Date().toISOString().split('T')[0];
    if (ovDate.value === today && typeEmergency) {
      typeEmergency.checked = true;
    }
  };
  if (ovDate) {
    ovDate.addEventListener('change', checkDateEmergency);
  }

  // Confirmation preview
  if (overrideForm) {
    overrideForm.addEventListener('submit', async (e) => {
      if (overrideConfirmed.value === '1') return true;

      e.preventDefault();
      if (!overrideForm.checkValidity()) {
        overrideForm.reportValidity();
        return;
      }

      saveOverrideBtn.disabled = true;
      const originalText = saveOverrideBtn.textContent;
      saveOverrideBtn.textContent = 'Checking affected bookings…';

      try {
        const formData = new FormData(overrideForm);
        const res = await fetch("{{ route('admin.settings.overrides.preview') }}", {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: formData
        });

        if (!res.ok) {
          const err = await res.json().catch(() => ({}));
          alert(err.message || 'Error checking for conflicting appointments.');
          saveOverrideBtn.disabled = false;
          saveOverrideBtn.textContent = originalText;
          return;
        }

        const data = await res.json();
        saveOverrideBtn.disabled = false;
        saveOverrideBtn.textContent = originalText;

        if (data.count === 0) {
          // No appointments affected, proceed directly
          overrideConfirmed.value = '1';
          overrideForm.submit();
          return;
        }

        // Show confirmation modal with appointment list
        document.getElementById('closureConfirmMessage').textContent = data.message;
        const emNotice = document.getElementById('closureEmergencyNotice');
        if (emNotice) {
          emNotice.classList.toggle('d-none', !data.is_emergency);
        }

        const tbody = document.getElementById('closureConfirmTableBody');
        tbody.replaceChildren();

        data.appointments.forEach(a => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td class="text-nowrap fw-semibold">${a.time}</td>
            <td><strong>${a.patient_name}</strong></td>
            <td><a href="tel:${a.contact_number}" class="text-decoration-none">📞 ${a.contact_number}</a></td>
            <td>${a.service_name}</td>
            <td>${a.dentist_name}</td>
            <td><span class="badge text-bg-${a.status === 'confirmed' ? 'primary' : 'warning'}">${a.status_label}</span></td>
          `;
          tbody.appendChild(tr);
        });

        const modalEl = document.getElementById('closureConfirmModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

        const confirmBtn = document.getElementById('confirmSaveClosureBtn');
        confirmBtn.onclick = () => {
          confirmBtn.disabled = true;
          confirmBtn.textContent = 'Applying closure…';
          overrideConfirmed.value = '1';
          overrideForm.submit();
        };

      } catch (err) {
        saveOverrideBtn.disabled = false;
        saveOverrideBtn.textContent = originalText;
        alert('Could not verify conflicts. Please try again.');
      }
    });
  }

  // Existing Override Call List Modal
  document.querySelectorAll('.view-call-list-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const url = btn.dataset.url;
      try {
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!res.ok) return alert('Could not load call list.');
        const data = await res.json();

        document.getElementById('existingCallListInfo').innerHTML =
          `<strong>${data.date}</strong> · Closure Type: <span class="badge text-bg-${data.closure_type === 'emergency' ? 'danger' : 'secondary'}">${data.closure_type}</span> · Reason: <em>${data.reason || 'None specified'}</em>`;

        const tbody = document.getElementById('existingCallListBody');
        tbody.replaceChildren();

        if (data.appointments.length === 0) {
          tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-secondary">No appointments were scheduled on this date.</td></tr>';
        } else {
          data.appointments.forEach(a => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td class="fw-semibold">${a.patient_name}</td>
              <td><a href="tel:${a.contact_number}" class="btn btn-sm btn-outline-success py-0 fw-bold">📞 ${a.contact_number}</a></td>
              <td class="text-nowrap">${a.time}</td>
              <td>${a.service_name}</td>
              <td>${a.dentist_name}</td>
              <td>
                <span class="badge text-bg-${a.status === 'Confirmed' ? 'primary' : 'warning'}">${a.status}</span>
                ${a.needs_reschedule ? '<span class="badge text-bg-danger ms-1">Needs reschedule</span>' : ''}
              </td>
            `;
            tbody.appendChild(tr);
          });
        }

        new bootstrap.Modal(document.getElementById('existingCallListModal')).show();
      } catch (e) {
        alert('Network error loading call list.');
      }
    });
  });
})();
</script>
@endpush
