@extends('layouts.app')
@section('title', 'Digital Odontogram')
@section('page_title', 'Digital Odontogram')
@section('page_subtitle', $canEdit ? 'Click a tooth to record or review findings (FDI notation).' : 'Read-only view. Only dentists and admins can record findings.')
@section('content')
<div class="card mb-3"><div class="card-body row g-3 align-items-end">
  <div class="col-md-6"><label class="form-label">Patient</label>
    <select id="patientSelect" class="form-select"><option value="">Select a patient…</option>
      @foreach ($patients as $p)<option value="{{ $p->patient_id }}" @selected($selected === $p->patient_id)>{{ $p->last_name }}, {{ $p->first_name }} — {{ $p->patient_code }}</option>@endforeach</select></div>
  <div class="col-md-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="showPrimary"><label class="form-check-label" for="showPrimary">Show primary (baby) teeth</label></div></div>
  <div class="col-md-3 text-md-end"><button class="btn btn-outline-primary" id="historyBtn" disabled>View history</button></div>
</div></div>

<div class="row g-4">
  <div class="col-xl-8"><div class="card"><div class="card-body">
    <div id="patientInfo" class="text-secondary mb-3">Select a patient to load the chart.</div>
    <div class="odonto-scroll">
      <div class="text-center small text-secondary">Upper (right ← → left)</div>
      <div class="tooth-row mb-2" id="rowUpper"></div>
      <div class="tooth-row mb-4" id="rowLower"></div>
      <div class="text-center small text-secondary mb-3">Lower</div>
      <div id="primaryWrap" class="d-none"><hr><div class="tooth-row mb-2" id="rowUpperP"></div><div class="tooth-row" id="rowLowerP"></div></div>
    </div>
    <hr>
    <div class="d-flex flex-wrap gap-3 small">
      @foreach ($conditions as $c)<span><span class="legend-dot" style="background:{{ $c->color_hex }}"></span>{{ $c->label }}</span>@endforeach
    </div>
  </div></div></div>

  <div class="col-xl-4"><div class="card"><div class="card-header bg-white fw-bold" id="toothTitle">Tooth details</div><div class="card-body">
    <div id="toothFindings" class="mb-3 text-secondary">Select a tooth.</div>
    @if ($canEdit)
    <form id="findingForm" class="d-none border-top pt-3">
      <input type="hidden" id="findingId">
      <div class="mb-2"><label class="form-label small">Condition / treatment</label>
        <select id="condSelect" class="form-select">@foreach ($conditions as $c)<option value="{{ $c->condition_code_id }}" data-applies="{{ $c->applies_to }}">{{ $c->label }} ({{ $c->category }})</option>@endforeach</select></div>
      <div class="mb-2" id="surfaceWrap"><label class="form-label small">Surface</label>
        <select id="surfaceSelect" class="form-select">@foreach (['mesial','distal','occlusal','buccal','lingual'] as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach</select></div>
      <div class="mb-2"><label class="form-label small">Notes</label><textarea id="notesInput" class="form-control" rows="2" maxlength="500"></textarea></div>
      <div class="d-flex gap-2"><button class="btn btn-primary btn-sm" id="saveFinding">Add finding</button><button type="button" class="btn btn-light btn-sm d-none" id="cancelEdit">Cancel edit</button></div>
    </form>
    @endif
  </div></div></div>
</div>

<div class="modal fade" id="historyModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Change history</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body" id="historyBody"></div></div></div></div>
@endsection
@push('scripts')
<script>window.ODONTO = {canEdit: @json((bool) $canEdit), findingsUrl: @json(url('/odontogram')), selected: {{ (int) $selected }}};</script>
<script src="{{ asset('js/odontogram.js') }}"></script>
@endpush
