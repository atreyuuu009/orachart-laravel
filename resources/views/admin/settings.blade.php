@extends('layouts.app')
@section('title', 'Clinic Settings')
@section('page_title', 'Clinic Settings')
@section('page_subtitle', 'Working hours, closures, booking rules, dentists and emergency windows.')
@section('content')
@php
  $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  $tab = session('tab', old('_tab', 'hours'));
  $tabs = ['hours' => 'Weekly hours', 'overrides' => 'Date overrides', 'rules' => 'Booking rules', 'dentists' => 'Dentists', 'emergency' => 'Emergency windows'];
@endphp
<ul class="nav nav-tabs mb-3" role="tablist">
  @foreach ($tabs as $k => $l)<li class="nav-item"><button class="nav-link {{ $tab === $k ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#t-{{ $k }}" type="button">{{ $l }}</button></li>@endforeach
</ul>
<div class="tab-content">

<div class="tab-pane fade {{ $tab === 'hours' ? 'show active' : '' }}" id="t-hours">
  <form method="POST" action="{{ route('admin.settings.hours') }}" class="card">@csrf<div class="card-body">
    <p class="text-secondary">Default opening hours for every week. Use a date override for holidays or special days.</p>
    @foreach ($days as $i => $name)
      @php $h = $hours[$i] ?? null; $closed = old("hours.$i.closed", $h ? $h->is_closed : ($i === 0)); @endphp
      <div class="row g-2 align-items-center mb-2">
        <div class="col-md-3 fw-semibold">{{ $name }}</div>
        <div class="col-5 col-md-3"><input type="time" class="form-control" name="hours[{{ $i }}][open]" value="{{ old("hours.$i.open", $h && $h->open_time ? substr($h->open_time, 0, 5) : '09:00') }}"></div>
        <div class="col-5 col-md-3"><input type="time" class="form-control" name="hours[{{ $i }}][close]" value="{{ old("hours.$i.close", $h && $h->close_time ? substr($h->close_time, 0, 5) : '18:00') }}"></div>
        <div class="col-2 col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="hours[{{ $i }}][closed]" value="1" id="cl{{ $i }}" @checked($closed)><label class="form-check-label" for="cl{{ $i }}">Closed</label></div></div>
      </div>
    @endforeach
  </div><div class="card-footer bg-white text-end"><button class="btn btn-primary">Save hours</button></div></form>
</div>

<div class="tab-pane fade {{ $tab === 'overrides' ? 'show active' : '' }}" id="t-overrides"><div class="row g-4">
  <div class="col-lg-5"><form method="POST" action="{{ route('admin.settings.overrides.store') }}" class="card">@csrf<div class="card-body row g-2">
    <div class="col-12"><label class="form-label">Date</label><input type="date" name="override_date" min="{{ today()->toDateString() }}" class="form-control" required></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_closed" value="1" id="ovClosed"><label class="form-check-label" for="ovClosed">Clinic closed all day</label></div></div>
    <div class="col-6"><label class="form-label">Opens</label><input type="time" name="start_time" class="form-control"></div>
    <div class="col-6"><label class="form-label">Closes</label><input type="time" name="end_time" class="form-control"></div>
    <div class="col-12"><label class="form-label">Reason</label><input name="reason" class="form-control" maxlength="255" placeholder="e.g. Holiday"></div>
    <div class="col-12 form-text">Booked patients affected by the change are flagged and e-mailed.</div>
  </div><div class="card-footer bg-white text-end"><button class="btn btn-primary">Save override</button></div></form></div>
  <div class="col-lg-7"><div class="card"><div class="card-header bg-white fw-bold">Upcoming overrides</div><ul class="list-group list-group-flush">
    @forelse ($overrides as $o)
      <li class="list-group-item d-flex justify-content-between align-items-center"><div><strong>{{ $o->override_date->format('D, M j, Y') }}</strong>
        <div class="small text-secondary">{{ $o->is_closed ? 'Closed' : substr($o->start_time, 0, 5).' – '.substr($o->end_time, 0, 5) }} {{ $o->reason ? '· '.$o->reason : '' }}</div></div>
        <form method="POST" action="{{ route('admin.settings.overrides.destroy', $o) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form></li>
    @empty<li class="list-group-item text-secondary text-center">No upcoming overrides.</li>@endforelse
  </ul></div></div>
</div></div>

<div class="tab-pane fade {{ $tab === 'rules' ? 'show active' : '' }}" id="t-rules">
  <form method="POST" action="{{ route('admin.settings.rules') }}" class="card">@csrf<div class="card-body"><div class="row g-3">
    @foreach ([
      ['slot_interval_minutes', 'Slot interval (minutes)', 5, 120], ['buffer_before_minutes', 'Buffer before treatment (min)', 0, 120],
      ['buffer_after_minutes', 'Buffer after treatment (min)', 0, 120], ['min_booking_notice_hours', 'Minimum booking notice (hours)', 0, 168],
      ['slot_lock_minutes', 'Slot hold while booking (min)', 1, 30], ['max_patients_per_day', 'Max patients per day (clinic)', 1, 200],
      ['emergency_release_minutes', 'Release unused emergency slots (min before)', 0, 240], ['arrival_notice_minutes', 'Ask patients to arrive early (min)', 0, 120],
      ['reminder_hours_before', 'Reminder e-mail (hours before)', 1, 168], ['session_timeout_minutes', 'Inactivity logout (minutes)', 5, 120],
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
