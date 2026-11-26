@extends('layouts.app')
@section('title', 'Digital Odontogram')
@section('page_title', 'Digital Odontogram')
@section('page_subtitle', $canEdit ? 'Rotate the 3D model and click a tooth to record or review findings (FDI notation).' : 'Read-only 3D view. Only dentists can record findings.')
@section('content')
@php
  $jumpGroups = [
    'Upper right' => [18, 17, 16, 15, 14, 13, 12, 11],
    'Upper left' => [21, 22, 23, 24, 25, 26, 27, 28],
    'Lower right' => [48, 47, 46, 45, 44, 43, 42, 41],
    'Lower left' => [31, 32, 33, 34, 35, 36, 37, 38],
  ];
@endphp
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

    <div class="odonto-viewer" id="odontoViewer">
      <div id="odontoCanvas" class="odonto-canvas" aria-label="Interactive 3D model of the permanent teeth"></div>
      <div class="odonto-toolbar" role="group" aria-label="3D view controls">
        <button type="button" class="btn btn-light border" data-view="front">Front</button>
        <button type="button" class="btn btn-light border" data-view="upper">Upper arch</button>
        <button type="button" class="btn btn-light border" data-view="lower">Lower arch</button>
        <button type="button" class="btn btn-light border" data-view="left">Left</button>
        <button type="button" class="btn btn-light border" data-view="right">Right</button>
        <button type="button" class="btn btn-light border" data-view="reset"><i class="bi bi-arrow-counterclockwise"></i></button>
      </div>
      <div class="odonto-tooltip" id="odontoTooltip" role="status" aria-live="polite"></div>
      <div class="odonto-loading" id="odontoLoading">
        <div class="odonto-loading-inner">
          <div class="fw-semibold mb-2">Loading 3D teeth…</div>
          <div class="progress mx-auto" role="progressbar" aria-label="3D model loading" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="odontoProgressBar">
            <div class="progress-bar" id="odontoProgress" style="width:0%"></div>
          </div>
          <div class="small text-secondary mt-1" id="odontoProgressText">0%</div>
        </div>
      </div>
      <div class="odonto-hint"><i class="bi bi-hand-index-thumb"></i> Drag to rotate · scroll to zoom · click a tooth</div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
      <label for="toothJump" class="form-label small mb-0">Jump to tooth</label>
      <select id="toothJump" class="form-select form-select-sm w-auto">
        <option value="">Select…</option>
        @foreach ($jumpGroups as $label => $teeth)
          <optgroup label="{{ $label }}">
            @foreach ($teeth as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
          </optgroup>
        @endforeach
      </select>
      <span class="small text-secondary ms-auto" id="selectedToothLabel"></span>
    </div>

    <div id="primaryWrap" class="d-none mt-3">
      <hr>
      <div class="small text-secondary mb-2">Primary (baby) teeth — 2D reference (not part of the 3D model)</div>
      <div class="odonto-scroll">
        <div class="text-center small text-secondary">Upper (right ← → left)</div>
        <div class="tooth-row mb-2" id="rowUpperP"></div>
        <div class="tooth-row mb-3" id="rowLowerP"></div>
        <div class="text-center small text-secondary">Lower</div>
      </div>
    </div>

    <hr>
    <div class="d-flex flex-wrap gap-3 small">
      @foreach ($conditions as $c)<span><span class="legend-dot" style="background:{{ $c->color_hex }}"></span>{{ $c->label }}</span>@endforeach
    </div>
    <div class="small text-secondary mt-2">
      3D model: “Teeth” by <a href="https://sketchfab.com/dewa" target="_blank" rel="noopener">dewa</a>,
      licensed under <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">CC BY 4.0</a>.
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
<script>window.ODONTO = {canEdit: @json((bool) $canEdit), findingsUrl: @json(url('/odontogram')), modelUrl: @json(asset('models/teeth_fdi.glb')), selected: {{ (int) $selected }}};</script>
<script type="importmap">
{
  "imports": {
    "three": "{{ asset('vendor/three/three.module.js') }}",
    "three/addons/": "{{ asset('vendor/three/addons') }}/"
  }
}
</script>
<script type="module" src="{{ asset('js/odontogram.js') }}?v={{ filemtime(public_path('js/odontogram.js')) }}"></script>
@endpush
