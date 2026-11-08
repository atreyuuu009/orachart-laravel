@php $pv = $patientView ?? false; @endphp
<div class="dropdown">
  <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">Actions</button>
  <ul class="dropdown-menu dropdown-menu-end">
    @if (! $pv && $a->status === 'pending')
      <li><form method="POST" action="{{ route('appointments.confirm', $a) }}">@csrf<button class="dropdown-item">Confirm</button></form></li>
      <li><button class="dropdown-item" data-reason-action="{{ route('appointments.reject', $a) }}" data-reason-title="Reject request" data-reason-required="1">Reject…</button></li>
    @endif
    @if ($a->isOpen())
      <li><a class="dropdown-item" href="{{ route('appointments.reschedule', $a) }}">Reschedule</a></li>
      @if (! $pv && $a->status === 'confirmed' && $a->start_at->copy()->startOfDay()->lte(today()))
        <li><form method="POST" action="{{ route('appointments.complete', $a) }}">@csrf<button class="dropdown-item">Mark completed</button></form></li>
        @if ($a->start_at->isPast())<li><form method="POST" action="{{ route('appointments.noshow', $a) }}">@csrf<button class="dropdown-item">Mark no-show</button></form></li>@endif
      @endif
      <li><hr class="dropdown-divider"></li>
      <li><button class="dropdown-item text-danger" data-reason-action="{{ route('appointments.cancel', $a) }}" data-reason-title="Cancel appointment">Cancel…</button></li>
    @endif
    @if (! $pv && $a->status === 'completed' && $a->service->is_multi_visit)
      <li><button class="dropdown-item" data-followup-action="{{ route('appointments.followup', $a) }}">Book follow-up visit…</button></li>
    @endif
    @if (! $a->isOpen() && ! ($a->status === 'completed' && $a->service->is_multi_visit && ! $pv))<li><span class="dropdown-item-text text-secondary small">No actions</span></li>@endif
  </ul>
</div>
