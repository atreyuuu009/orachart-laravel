@extends('layouts.app')
@section('title', 'Hours & Days Off')
@section('page_title', 'Working Hours & Days Off')
@section('page_subtitle', $dentist->name)
@section('content')
@php $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; @endphp

@if ($dentists->count() > 1)
  <div class="mb-3">
    <div class="apt-period-seg flex-wrap" style="height: auto;" role="navigation" aria-label="Select dentist">
      @foreach ($dentists as $d)
        <a class="apt-period-btn text-decoration-none {{ $d->dentist_id === $dentist->dentist_id ? 'active' : '' }}" href="{{ route('schedule', $d) }}">{{ $d->name }}</a>
      @endforeach
    </div>
  </div>
@endif

<div class="row g-4">
  <div class="col-lg-7">
    <form class="apt-list-card" method="POST" action="{{ route('schedule.hours', $dentist) }}">
      @csrf
      <div class="apt-date-header">
        <span>Weekly schedule</span>
        <span class="apt-badge {{ old('custom', $custom) ? 'apt-badge-confirmed' : 'apt-badge-rescheduled' }}">
          {{ old('custom', $custom) ? 'Custom schedule' : 'Following clinic hours' }}
        </span>
      </div>

      <div class="px-3 py-2 border-bottom bg-light bg-opacity-50 d-flex align-items-center justify-content-between">
        <div class="form-check form-switch mb-0">
          <input class="form-check-input" type="checkbox" role="switch" name="custom" id="custom" value="1" @checked(old('custom', $custom))>
          <label class="form-check-label fw-medium small" for="custom">
            Use a custom weekly schedule <span class="text-secondary fw-normal">(otherwise follows clinic hours)</span>
          </label>
        </div>
      </div>

      <div id="hoursGrid">
        @foreach ($days as $i => $name)
          @php
            $r = $rows[$i] ?? null;
            $c = $clinicHours[$i] ?? null;
            $defaultStart = $custom ? '' : ($c && ! $c->is_closed ? substr($c->open_time, 0, 5) : '');
            $defaultEnd = $custom ? '' : ($c && ! $c->is_closed ? substr($c->close_time, 0, 5) : '');
            $valStart = old("days.$i.start", $r ? substr($r->start_time, 0, 5) : $defaultStart);
            $valEnd = old("days.$i.end", $r ? substr($r->end_time, 0, 5) : $defaultEnd);
            $isOff = ($valStart === '' && $valEnd === '');
          @endphp
          <div class="sch-day-row {{ $isOff ? 'sch-day-off' : '' }}" data-day-row>
            <div class="sch-day-label">
              <div class="fw-semibold text-dark small">{{ $name }}</div>
              <span class="apt-badge mt-1 {{ $isOff ? 'apt-badge-rescheduled' : 'apt-badge-completed' }}" data-day-badge>
                {{ $isOff ? 'Day off' : 'Open' }}
              </span>
            </div>
            <div>
              <input type="time" aria-label="{{ $name }} start time" class="form-control form-control-sm" name="days[{{ $i }}][start]" value="{{ $valStart }}" data-day-time>
            </div>
            <div>
              <input type="time" aria-label="{{ $name }} end time" class="form-control form-control-sm" name="days[{{ $i }}][end]" value="{{ $valEnd }}" data-day-time>
            </div>
            <div class="text-end">
              <button type="button" class="sch-clear-btn" data-day-clear title="Clear times (set as day off)">Clear</button>
            </div>
          </div>
        @endforeach
      </div>

      <div class="sch-card-footer">
        <div class="small text-secondary" style="max-width: 420px;">
          Leave both times blank for a day off. Hours outside the clinic's opening hours are ignored when booking.
        </div>
        <button class="btn btn-primary text-nowrap">Save schedule</button>
      </div>
    </form>
  </div>

  <div class="col-lg-5 d-flex flex-column gap-3">
    <form class="apt-list-card" method="POST" action="{{ route('schedule.timeoff.store', $dentist) }}">
      @csrf
      <div class="apt-date-header">
        <span>Add day off</span>
        <i class="bi bi-calendar-x text-secondary"></i>
      </div>
      <div class="p-3 row g-2">
        <div class="col-12">
          <label class="form-label small">Date</label>
          <input type="date" name="date" class="form-control" required>
        </div>
        <div class="col-6">
          <label class="form-label small">From</label>
          <input type="time" name="start_time" class="form-control" required>
        </div>
        <div class="col-6">
          <label class="form-label small">Until</label>
          <input type="time" name="end_time" class="form-control" required>
        </div>
        <div class="col-12">
          <label class="form-label small">Reason (optional)</label>
          <input name="reason" class="form-control" maxlength="255" placeholder="e.g., Seminar, Personal leave">
        </div>
        <div class="col-12">
          <div class="small text-secondary mt-1">
            <i class="bi bi-info-circle me-1"></i>Booked patients in this period are flagged "Needs reschedule" and notified by e-mail.
          </div>
        </div>
      </div>
      <div class="sch-card-footer justify-content-end">
        <button class="btn btn-primary">Save day off</button>
      </div>
    </form>

    <div class="apt-list-card">
      <div class="apt-date-header">
        <span>Upcoming days off</span>
        <span class="apt-date-count">{{ $timeOffs->count() }} {{ \Illuminate\Support\Str::plural('entry', $timeOffs->count()) }}</span>
      </div>
      @forelse ($timeOffs as $t)
        <div class="d-flex justify-content-between align-items-center gap-2 px-3 py-2 border-bottom">
          <div>
            <div class="fw-semibold small text-dark">{{ $t->start_at->format('M j, g:i A') }} → {{ $t->end_at->format('M j, g:i A') }}</div>
            @if ($t->reason)
              <div class="pat-sub">{{ $t->reason }}</div>
            @endif
          </div>
          <form method="POST" action="{{ route('schedule.timeoff.destroy', $t) }}" onsubmit="return confirm('Remove this day off?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm user-action user-action-deactivate" style="border:0; border-radius:999px; padding:.25rem .65rem; font-weight:600; color:#a9435e; background:#ffe8ee;">Remove</button>
          </form>
        </div>
      @empty
        <div class="text-secondary text-center py-4 small">No upcoming days off.</div>
      @endforelse
    </div>
  </div>
</div>

<script>
(() => {
  const syncRow = (row) => {
    const inputs = row.querySelectorAll('[data-day-time]');
    const badge = row.querySelector('[data-day-badge]');
    const isOff = Array.from(inputs).every(inp => !inp.value);
    row.classList.toggle('sch-day-off', isOff);
    if (badge) {
      badge.textContent = isOff ? 'Day off' : 'Open';
      badge.className = 'apt-badge mt-1 ' + (isOff ? 'apt-badge-rescheduled' : 'apt-badge-completed');
    }
  };

  document.querySelectorAll('[data-day-row]').forEach(row => {
    row.querySelectorAll('[data-day-time]').forEach(inp => {
      inp.addEventListener('change', () => syncRow(row));
    });
    const clearBtn = row.querySelector('[data-day-clear]');
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        row.querySelectorAll('[data-day-time]').forEach(inp => { inp.value = ''; });
        syncRow(row);
      });
    }
  });
})();
</script>
@endsection
