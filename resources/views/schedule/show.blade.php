@extends('layouts.app')
@section('title', 'Hours & Days Off')
@section('page_title', 'Working Hours & Days Off')
@section('page_subtitle', $dentist->name)
@section('content')
@php $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; @endphp
@if ($dentists->count() > 1)
  <div class="mb-3 d-flex flex-wrap gap-2">@foreach ($dentists as $d)
    <a class="btn btn-sm {{ $d->dentist_id === $dentist->dentist_id ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('schedule', $d) }}">{{ $d->name }}</a>@endforeach</div>
@endif
<div class="row g-4">
  <div class="col-lg-7"><form class="card" method="POST" action="{{ route('schedule.hours', $dentist) }}">@csrf
    <div class="card-header bg-white fw-bold">Weekly schedule</div>
    <div class="card-body">
      <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="custom" id="custom" value="1" @checked(old('custom', $custom))>
        <label class="form-check-label" for="custom">Use a custom weekly schedule <span class="text-secondary">(otherwise follows clinic hours)</span></label></div>
      <div id="hoursGrid">
        <div class="row g-2 mb-1 small text-secondary"><div class="col-4">Day</div><div class="col-4">Starts</div><div class="col-4">Ends</div></div>
        @foreach ($days as $i => $name)
          @php
            $r = $rows[$i] ?? null;
            $c = $clinicHours[$i] ?? null;
            $defaultStart = $custom ? '' : ($c && ! $c->is_closed ? substr($c->open_time, 0, 5) : '');
            $defaultEnd = $custom ? '' : ($c && ! $c->is_closed ? substr($c->close_time, 0, 5) : '');
          @endphp
          <div class="row g-2 align-items-center mb-2">
            <div class="col-4 fw-medium">{{ $name }}</div>
            <div class="col-4"><input type="time" aria-label="{{ $name }} start time" class="form-control form-control-sm" name="days[{{ $i }}][start]" value="{{ old("days.$i.start", $r ? substr($r->start_time, 0, 5) : $defaultStart) }}"></div>
            <div class="col-4"><input type="time" aria-label="{{ $name }} end time" class="form-control form-control-sm" name="days[{{ $i }}][end]" value="{{ old("days.$i.end", $r ? substr($r->end_time, 0, 5) : $defaultEnd) }}"></div>
          </div>
        @endforeach
      </div>
      <div class="form-text">Leave both times blank for a day off. If custom scheduling is off, the clinic hours are shown. Hours outside the clinic's opening hours are ignored when booking.</div>
    </div>
    <div class="card-footer bg-white text-end"><button class="btn btn-primary">Save schedule</button></div>
  </form></div>

  <div class="col-lg-5">
    <form class="card mb-3" method="POST" action="{{ route('schedule.timeoff.store', $dentist) }}">@csrf
      <div class="card-header bg-white fw-bold">Add day off</div>
      <div class="card-body row g-2">
        <div class="col-12"><label class="form-label small">Date</label><input type="date" name="date" class="form-control" required></div>
        <div class="col-6"><label class="form-label small">From</label><input type="time" name="start_time" class="form-control" required></div>
        <div class="col-6"><label class="form-label small">Until</label><input type="time" name="end_time" class="form-control" required></div>
        <div class="col-12"><label class="form-label small">Reason (optional)</label><input name="reason" class="form-control" maxlength="255"></div>
        <div class="col-12 form-text">Booked patients in this period are flagged "Needs reschedule" and notified by e-mail.</div>
      </div>
      <div class="card-footer bg-white text-end"><button class="btn btn-primary">Save day off</button></div>
    </form>
    <div class="card"><div class="card-header bg-white fw-bold">Upcoming days off</div>
      <ul class="list-group list-group-flush">
        @forelse ($timeOffs as $t)
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div><strong>{{ $t->start_at->format('M j, g:i A') }}</strong> → {{ $t->end_at->format('M j, g:i A') }}<div class="small text-secondary">{{ $t->reason }}</div></div>
            <form method="POST" action="{{ route('schedule.timeoff.destroy', $t) }}" onsubmit="return confirm('Remove this day off?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form>
          </li>
        @empty
          <li class="list-group-item text-secondary text-center">No upcoming days off.</li>
        @endforelse
      </ul></div>
  </div>
</div>
@endsection
