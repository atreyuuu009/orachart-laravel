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
        <td>@include('partials.status', ['a' => $a])
          @if ($a->status === 'cancelled' && ($a->rejection_reason || $a->cancel_reason))<div class="small text-secondary mt-1">{{ $a->rejection_reason ?: $a->cancel_reason }}</div>@endif</td>
        @if ($actions)<td class="text-end">@include('appointments._actions', ['a' => $a, 'patientView' => $patientView])</td>@endif
      </tr>
    @empty
      <tr><td colspan="6" class="text-center text-secondary py-4">{{ $empty }}</td></tr>
    @endforelse
    </tbody>
  </table>
</div>
