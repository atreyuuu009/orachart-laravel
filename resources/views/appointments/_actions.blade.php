@php $pv = $patientView ?? false; @endphp
<div class="dropdown">
  <button class="btn apt-btn-kebab" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Appointment actions">
    <i class="bi bi-three-dots"></i>
  </button>
  <ul class="dropdown-menu dropdown-menu-end apt-dropdown-menu">
    <li>
      <button type="button" class="dropdown-item" data-apt-details="{{ $a->appointment_id }}">View details</button>
    </li>
    @if (! $pv && $a->patient->contact_number)
      <li><a class="dropdown-item" href="tel:{{ $a->patient->contact_number }}">Call patient</a></li>
    @endif
    @if (! $pv && $a->status === 'pending')
      <li><form method="POST" action="{{ route('appointments.confirm', $a) }}">@csrf<button class="dropdown-item">Confirm</button></form></li>
      <li><button type="button" class="dropdown-item" data-reason-action="{{ route('appointments.reject', $a) }}" data-reason-title="Reject request" data-reason-required="1">Reject…</button></li>
    @endif
    @if ($a->isOpen())
      <li><a class="dropdown-item" href="{{ route('appointments.reschedule', $a) }}">Reschedule</a></li>
      @if (! $pv && $a->needs_reschedule)
        <li>
          <button type="button" class="dropdown-item quick-reschedule-btn"
            data-id="{{ $a->appointment_id }}"
            data-slots-url="{{ route('appointments.suggested_slots', $a) }}"
            data-reschedule-url="{{ route('appointments.reschedule.store', $a) }}"
            data-custom-url="{{ route('appointments.reschedule', $a) }}"
            data-cancel-url="{{ route('appointments.cancel', $a) }}"
            data-patient="{{ $a->patient->full_name }}"
            data-service="{{ $a->service->service_name }}"
            data-dentist="{{ $a->dentist->name }}">Quick Reschedule…</button>
        </li>
      @endif
      @if (! $pv && $a->status === 'confirmed' && $a->start_at->copy()->startOfDay()->lte(today()))
        <li><form method="POST" action="{{ route('appointments.complete', $a) }}">@csrf<button class="dropdown-item">Mark completed</button></form></li>
        @if ($a->start_at->isPast())
          <li><form method="POST" action="{{ route('appointments.noshow', $a) }}">@csrf<button class="dropdown-item">Mark no-show</button></form></li>
        @endif
      @endif
      <li><hr class="dropdown-divider"></li>
      @if (! $pv && $a->needs_reschedule)
        <li>
          <form method="POST" action="{{ route('appointments.cancel', $a) }}" onsubmit="return confirm('Cancel this appointment due to clinic closure?');">
            @csrf
            <input type="hidden" name="reason" value="Cancelled due to clinic closure">
            <button class="dropdown-item text-danger">Cancel (closure)</button>
          </form>
        </li>
      @else
        <li><button type="button" class="dropdown-item text-danger" data-reason-action="{{ route('appointments.cancel', $a) }}" data-reason-title="Cancel appointment">Cancel…</button></li>
      @endif
    @endif
    @if (! $pv && $a->status === 'completed' && $a->service->is_multi_visit)
      <li><button type="button" class="dropdown-item" data-followup-action="{{ route('appointments.followup', $a) }}">Book follow-up visit…</button></li>
    @endif
  </ul>
</div>
