@extends('layouts.app')
@section('title', 'Calendar')
@section('page_title', auth()->user()->hasRole('dentist') ? 'My Schedule' : 'Appointment Calendar')
@section('page_subtitle', 'View scheduled appointments.')
@section('content')
@php
  $prev = $month->copy()->subMonth()->format('Y-m'); $next = $month->copy()->addMonth()->format('Y-m');
  $list = $byDay[$selected->toDateString()] ?? collect();
@endphp
<div class="row g-4">
  <div class="col-xl-8"><div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <a class="btn btn-outline-primary btn-sm" href="{{ route('calendar', ['month' => $prev]) }}">&laquo; Prev</a>
      <h2 class="h5 mb-0">{{ $month->format('F Y') }}</h2>
      <a class="btn btn-outline-primary btn-sm" href="{{ route('calendar', ['month' => $next]) }}">Next &raquo;</a>
    </div>
    <div class="cal-grid">
      @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d)<div class="cal-head">{{ $d }}</div>@endforeach
      @for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay())
        @php
          $key = $day->toDateString(); $items = $byDay[$key] ?? collect();
          $isClosed = in_array($day->dayOfWeek, $closedDow) || in_array($key, $closedDates);
        @endphp
        <a href="{{ route('calendar', ['month' => $month->format('Y-m'), 'date' => $key]) }}"
           class="cal-cell {{ $day->month !== $month->month ? 'muted' : '' }} {{ $isClosed ? 'closed' : '' }} {{ $day->isToday() ? 'today' : '' }} {{ $key === $selected->toDateString() ? 'selected' : '' }}">
          <span class="cal-num">{{ $day->day }}</span>
          @if ($items->count())<div><span class="badge text-bg-primary">{{ $items->count() }} appt</span></div>@endif
          @if ($isClosed)<div class="small text-secondary">Closed</div>@endif
        </a>
      @endfor
    </div>
  </div></div></div>
  <div class="col-xl-4"><div class="card"><div class="card-header bg-white fw-bold">{{ $selected->format('l, F j, Y') }}</div>
    <div class="list-group list-group-flush">
      @forelse ($list as $a)
        <div class="list-group-item">
          <div class="d-flex justify-content-between"><strong>{{ $a->start_at->format('g:i A') }}</strong>@include('partials.status', ['a' => $a])</div>
          <div>{{ $a->patient->full_name }}</div>
          <div class="small text-secondary">{{ $a->service->service_name }} · {{ $a->dentist->name }}</div>
        </div>
      @empty
        <div class="list-group-item text-secondary text-center py-4">No appointments on this day.</div>
      @endforelse
    </div></div></div>
</div>
@endsection
