{{-- rows, actions(bool), empty(string), patientView(bool optional) --}}
@php
  $patientView = $patientView ?? false;
  $collection = $rows instanceof \Illuminate\Pagination\AbstractPaginator ? $rows->getCollection() : collect($rows);
  $grouped = $collection->groupBy(fn ($a) => $a->start_at->toDateString());
@endphp

<div class="apt-list-card">
  @forelse ($grouped as $dateKey => $dayRows)
    @php $dayDate = \Carbon\Carbon::parse($dateKey); @endphp
    <div class="apt-date-header">
      <span>{{ $dayDate->format($dayDate->year === now()->year ? 'l, M j' : 'l, M j, Y') }}</span>
      <span class="apt-date-count">{{ $dayRows->count() }} {{ \Illuminate\Support\Str::plural('appointment', $dayRows->count()) }}</span>
    </div>

    @foreach ($dayRows as $a)
      @php
        $isNeedsResched = $a->needs_reschedule && $a->isOpen();
        $latestEmail = $isNeedsResched && ! $patientView ? $a->latestRescheduleEmail() : null;
      @endphp
      <div class="apt-row {{ $patientView ? 'apt-row-patient' : '' }} {{ $isNeedsResched ? 'apt-row-needs-reschedule' : '' }} {{ $a->is_emergency ? 'apt-row-emergency' : '' }}">
        <div class="apt-col-time">
          <div class="apt-time-main">{{ $a->start_at->format('g:i A') }}</div>
          <div class="apt-time-sub">to {{ $a->start_at->copy()->addMinutes($a->service->duration_minutes)->format('g:i A') }}</div>
        </div>

        @unless ($patientView)
          <div class="apt-col-patient">
            <div class="apt-patient-name">{{ $a->patient->full_name }}</div>
            <div class="apt-patient-sub">
              <span>{{ $a->patient->contact_number ?: $a->patient->patient_code }}</span>
              @if ($isNeedsResched)
                @if ($latestEmail && $latestEmail->status === 'sent')
                  <i class="bi bi-envelope-check text-success" title="Email sent {{ $latestEmail->sent_at?->format('M j, Y g:i A') }}"></i>
                @elseif ($latestEmail && $latestEmail->status === 'queued')
                  <i class="bi bi-envelope text-warning" title="Email queued"></i>
                @elseif ($latestEmail && $latestEmail->status === 'failed')
                  <i class="bi bi-envelope-x text-danger" title="Email failed: {{ $latestEmail->error_message }}"></i>
                @else
                  <i class="bi bi-envelope-slash text-secondary" title="No email sent yet"></i>
                @endif
              @endif
            </div>
          </div>
        @endunless

        <div class="apt-col-procedure">
          <div class="apt-proc-name">
            {{ $a->service->service_name }}
            @if ($a->visit_number)<span class="text-secondary small fw-normal">· Visit #{{ $a->visit_number }}</span>@endif
          </div>
          <div class="apt-proc-sub">{{ $a->dentist->name }}</div>
        </div>

        <div class="apt-col-status">
          <div class="apt-status-group">
            <span class="apt-badge apt-badge-{{ $a->status }}">{{ $a->status_label }}</span>
            @if ($a->is_emergency)
              <span class="apt-badge apt-badge-emergency">Emergency</span>
            @endif
            @if ($isNeedsResched)
              <span class="apt-badge apt-badge-needs-reschedule" title="The clinic schedule changed. Please reschedule.">Needs reschedule</span>
            @endif
          </div>
        </div>

        @if ($actions)
          <div class="apt-col-actions">
            <div class="apt-actions-group">
              @if ($isNeedsResched)
                @if ($patientView)
                  <a href="{{ route('appointments.reschedule', $a) }}" class="btn btn-sm apt-btn-reschedule">Reschedule</a>
                @else
                  <button type="button" class="btn btn-sm apt-btn-reschedule quick-reschedule-btn"
                    data-id="{{ $a->appointment_id }}"
                    data-slots-url="{{ route('appointments.suggested_slots', $a) }}"
                    data-reschedule-url="{{ route('appointments.reschedule.store', $a) }}"
                    data-custom-url="{{ route('appointments.reschedule', $a) }}"
                    data-cancel-url="{{ route('appointments.cancel', $a) }}"
                    data-patient="{{ $a->patient->full_name }}"
                    data-service="{{ $a->service->service_name }}"
                    data-dentist="{{ $a->dentist->name }}">
                    Reschedule
                  </button>
                @endif
              @endif
              @include('appointments._actions', ['a' => $a, 'patientView' => $patientView])
            </div>
          </div>
        @endif

        <template id="apt-tpl-{{ $a->appointment_id }}">
          <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="apt-badge apt-badge-{{ $a->status }}">{{ $a->status_label }}</span>
            @if ($a->is_emergency)<span class="apt-badge apt-badge-emergency">Emergency</span>@endif
            @if ($isNeedsResched)<span class="apt-badge apt-badge-needs-reschedule">Needs reschedule</span>@endif
          </div>
          <dl class="row mb-0 small">
            <dt class="col-sm-4 text-secondary">Date &amp; Time</dt>
            <dd class="col-sm-8 fw-semibold">{{ $a->start_at->format('l, M j, Y') }}<br>{{ $a->start_at->format('g:i A') }} – {{ $a->start_at->copy()->addMinutes($a->service->duration_minutes)->format('g:i A') }}</dd>

            @unless ($patientView)
              <dt class="col-sm-4 text-secondary">Patient</dt>
              <dd class="col-sm-8">
                <div class="fw-semibold">{{ $a->patient->full_name }}</div>
                <div class="text-secondary">{{ $a->patient->patient_code }} @if ($a->patient->contact_number)· {{ $a->patient->contact_number }}@endif</div>
              </dd>
            @endunless

            <dt class="col-sm-4 text-secondary">Procedure</dt>
            <dd class="col-sm-8">{{ $a->service->service_name }}@if ($a->visit_number) (Visit #{{ $a->visit_number }})@endif</dd>

            <dt class="col-sm-4 text-secondary">Dentist</dt>
            <dd class="col-sm-8">{{ $a->dentist->name }}</dd>

            @if ($isNeedsResched && ! $patientView)
              <dt class="col-sm-4 text-secondary">Priority</dt>
              <dd class="col-sm-8"><span class="badge text-bg-{{ $a->priority_badge_color }}">{{ $a->priority_order_label }}</span></dd>

              <dt class="col-sm-4 text-secondary">Notification</dt>
              <dd class="col-sm-8">
                @if ($latestEmail && $latestEmail->status === 'sent')
                  <span class="badge text-bg-success">Email Sent</span> <span class="text-secondary">{{ $latestEmail->sent_at?->format('M j, Y g:i A') }}</span>
                @elseif ($latestEmail && $latestEmail->status === 'queued')
                  <span class="badge text-bg-warning">Email Queued</span>
                @elseif ($latestEmail && $latestEmail->status === 'failed')
                  <span class="badge text-bg-danger">Email Failed</span> <span class="text-danger">{{ $latestEmail->error_message }}</span>
                @else
                  <span class="badge text-bg-secondary">No Email</span>
                @endif
                @if ($a->hasSecondReminder())
                  <div class="mt-1"><span class="badge text-bg-info">2nd Email Sent (Call Pending)</span></div>
                @endif
              </dd>
            @endif

            @if ($a->status === 'cancelled' && ($a->rejection_reason || $a->cancel_reason))
              <dt class="col-sm-4 text-secondary">Reason</dt>
              <dd class="col-sm-8 text-danger">{{ $a->rejection_reason ?: $a->cancel_reason }}</dd>
            @endif
          </dl>
        </template>
      </div>
    @endforeach
  @empty
    <div class="text-center text-secondary py-5">{{ $empty }}</div>
  @endforelse
</div>
