{{-- rows, actions(bool), empty(string), patientView(bool optional) --}}
@php $patientView = $patientView ?? false; @endphp
<div class="table-responsive">
  <table class="table table-hover mb-0">
    <thead><tr>
      @unless ($patientView)<th>Patient</th>@endunless
      <th>Procedure</th><th>Dentist</th><th>Date &amp; time</th><th>Status</th>@if ($actions)<th class="text-end">Actions</th>@endif
    </tr></thead>
    <tbody>
    @forelse ($rows as $a)
      <tr>
        @unless ($patientView)<td><strong>{{ $a->patient->full_name }}</strong><div class="small text-secondary">{{ $a->patient->patient_code }} · {{ $a->patient->contact_number }}</div></td>@endunless
        <td>{{ $a->service->service_name }}@if ($a->visit_number)<div class="small text-secondary">Visit #{{ $a->visit_number }}</div>@endif</td>
        <td>{{ $a->dentist->name }}</td>
        <td class="text-nowrap">{{ $a->start_at->format('M j, Y') }}<div class="small text-secondary">{{ $a->start_at->format('g:i A') }} – {{ $a->start_at->copy()->addMinutes($a->service->duration_minutes)->format('g:i A') }}</div></td>
        <td>
          @include('partials.status', ['a' => $a])
          @if ($a->needs_reschedule && $a->isOpen())
            @if ($patientView)
              <div class="alert alert-warning py-1 px-2 mt-2 mb-0 small border-warning">
                <strong>Clinic closed on this date. Please reschedule.</strong>
                <div class="mt-1 d-flex flex-wrap gap-1 align-items-center">
                  <a href="{{ route('appointments.reschedule', $a) }}" class="btn btn-warning btn-sm py-0 px-2 fw-semibold">Reschedule</a>
                  <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" data-reason-action="{{ route('appointments.cancel', $a) }}" data-reason-title="Cancel appointment" data-reason-placeholder="Reason for cancellation (no penalty)">Cancel (No penalty)</button>
                </div>
              </div>
            @else
              <div class="closure-action-panel mt-2 p-2 p-md-3 small">
                <div class="d-flex flex-wrap gap-1 align-items-center mb-1">
                  <span class="badge text-bg-{{ $a->priority_badge_color }}">{{ $a->priority_order_label }}</span>
                  @php $latestEmail = $a->latestRescheduleEmail(); @endphp
                  @if ($latestEmail)
                    @if ($latestEmail->status === 'sent')
                      <span class="badge text-bg-success" title="Sent at {{ $latestEmail->sent_at?->format('M j, Y g:i A') }}">Email Sent</span>
                    @elseif ($latestEmail->status === 'queued')
                      <span class="badge text-bg-warning">Email Queued</span>
                    @elseif ($latestEmail->status === 'failed')
                      <span class="badge text-bg-danger" title="{{ $latestEmail->error_message }}">Email Failed</span>
                    @endif
                  @else
                    <span class="badge text-bg-secondary">No Email</span>
                  @endif

                  @if ($a->hasSecondReminder())
                    <span class="badge text-bg-info">2nd Email Sent (Call Pending)</span>
                  @endif
                </div>

                <div class="d-flex flex-wrap gap-2 align-items-center mt-2 closure-actions">
                  <a href="tel:{{ $a->patient->contact_number }}" class="btn btn-outline-success btn-sm px-3">📞 Call</a>
                  <button type="button" class="btn btn-primary btn-sm px-3 quick-reschedule-btn"
                    data-id="{{ $a->appointment_id }}"
                    data-slots-url="{{ route('appointments.suggested_slots', $a) }}"
                    data-reschedule-url="{{ route('appointments.reschedule.store', $a) }}"
                    data-custom-url="{{ route('appointments.reschedule', $a) }}"
                    data-cancel-url="{{ route('appointments.cancel', $a) }}"
                    data-patient="{{ $a->patient->full_name }}"
                    data-service="{{ $a->service->service_name }}"
                    data-dentist="{{ $a->dentist->name }}">
                    Quick reschedule
                  </button>
                  <div class="dropdown">
                    <button class="btn btn-light btn-sm border dropdown-toggle px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">More actions</button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                      <li>
                        <form method="POST" action="{{ route('appointments.cancel', $a) }}" onsubmit="return confirm('Cancel this appointment due to clinic closure?');">
                    @csrf
                    <input type="hidden" name="reason" value="Cancelled due to clinic closure">
                    <button class="dropdown-item text-danger" type="submit">Cancel due to closure</button>
                        </form>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            @endif
          @endif
          @if ($a->status === 'cancelled' && ($a->rejection_reason || $a->cancel_reason))<div class="small text-secondary mt-1">{{ $a->rejection_reason ?: $a->cancel_reason }}</div>@endif
        </td>
        @if ($actions)<td class="text-end">@include('appointments._actions', ['a' => $a, 'patientView' => $patientView])</td>@endif
      </tr>
    @empty
      <tr><td colspan="6" class="text-center text-secondary py-4">{{ $empty }}</td></tr>
    @endforelse
    </tbody>
  </table>
</div>
