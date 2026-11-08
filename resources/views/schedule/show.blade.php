@extends('layouts.app')
@section('title', 'Hours & Time Off')
@section('page_title', 'Working Hours & Time Off')
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
        @foreach ($days as $i => $name)
          @php $r = $rows[$i] ?? null; $c = $clinicHours[$i] ?? null; @endphp
          <div class="row g-2 align-items-center mb-2">
            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="days[{{ $i }}][enabled]" value="1" id="d{{ $i }}" @checked(old("days.$i.enabled", $r !== null))><label class="form-check-label" for="d{{ $i }}">{{ $name }}</label></div></div>
            <div class="col-4"><input type="time" class="form-control form-control-sm" name="days[{{ $i }}][start]" value="{{ old("days.$i.start", $r ? substr($r->start_time, 0, 5) : ($c && ! $c->is_closed ? substr($c->open_time, 0, 5) : '09:00')) }}"></div>
            <div class="col-4"><input type="time" class="form-control form-control-sm" name="days[{{ $i }}][end]" value="{{ old("days.$i.end", $r ? substr($r->end_time, 0, 5) : ($c && ! $c->is_closed ? substr($c->close_time, 0, 5) : '18:00')) }}"></div>
          </div>
        @endforeach
      </div>
      <div class="form-text">Hours outside the clinic's opening hours are ignored when booking.</div>
    </div>
    <div class="card-footer bg-white text-end"><button class="btn btn-primary">Save schedule</button></div>
  </form></div>

  <div class="col-lg-5">
    <form class="card mb-3" method="POST" action="{{ route('schedule.timeoff.store', $dentist) }}">@csrf
      <div class="card-header bg-white fw-bold">Add time off</div>
      <div class="card-body row g-2">
        <div class="col-6"><label class="form-label small">From</label><input type="datetime-local" name="start_at" class="form-control" required></div>
        <div class="col-6"><label class="form-label small">Until</label><input type="datetime-local" name="end_at" class="form-control" required></div>
        <div class="col-12"><label class="form-label small">Reason (optional)</label><input name="reason" class="form-control" maxlength="255"></div>
        <div class="col-12 form-text">Booked patients in this period are flagged "Needs reschedule" and notified by e-mail.</div>
      </div>
      <div class="card-footer bg-white text-end"><button class="btn btn-primary">Save time off</button></div>
    </form>
    <div class="card"><div class="card-header bg-white fw-bold">Upcoming time off</div>
      <ul class="list-group list-group-flush">
        @forelse ($timeOffs as $t)
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div><strong>{{ $t->start_at->format('M j, g:i A') }}</strong> → {{ $t->end_at->format('M j, g:i A') }}<div class="small text-secondary">{{ $t->reason }}</div></div>
            <form method="POST" action="{{ route('schedule.timeoff.destroy', $t) }}" onsubmit="return confirm('Remove this time off?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form>
          </li>
        @empty
          <li class="list-group-item text-secondary text-center">No upcoming time off.</li>
        @endforelse
      </ul></div>
  </div>
</div>
@endsection
