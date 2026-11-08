<span class="badge text-bg-{{ $a->status_color }}">{{ $a->status_label }}</span>
@if ($a->is_emergency)<span class="badge text-bg-danger">Emergency</span>@endif
@if ($a->needs_reschedule && $a->isOpen())<span class="badge text-bg-warning" title="The clinic schedule changed. Please reschedule.">Needs reschedule</span>@endif
