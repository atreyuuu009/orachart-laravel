@extends('layouts.app')
@section('title', 'Patients')
@section('page_title', 'Patient Management')
@section('page_subtitle', 'Patient directory and clinical records.')
@section('page_actions')
  @if(auth()->user()->hasRole('admin','clinic_staff'))<a class="btn btn-primary" href="{{ route('patients.create') }}">Add patient</a>@endif
@endsection
@section('content')
<form id="patient-filter-form" class="card card-body mb-3" method="GET">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label small" for="patient-search">Find a patient</label>
      <div class="input-group">
        <input id="patient-search" class="form-control" name="q" value="{{ request('q') }}" placeholder="Name, patient code or phone">
        <button class="btn btn-primary" type="submit">Search</button>
      </div>
    </div>
    <div class="col-md-2">
      <label class="form-label small" for="patient-sex">Sex</label>
      <select id="patient-sex" name="sex" class="form-select auto-submit">
        <option value="">All</option>
        @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label)
          <option value="{{ $value }}" @selected(request('sex') === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small" for="patient-age">Age group</label>
      <select id="patient-age" name="age" class="form-select auto-submit">
        <option value="">All ages</option>
        @foreach (['child' => 'Child (0–12)', 'teen' => 'Teen (13–19)', 'adult' => 'Adult (20–59)', 'senior' => 'Senior (60+)'] as $value => $label)
          <option value="{{ $value }}" @selected(request('age') === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small" for="patient-visit">Last visit</label>
      <select id="patient-visit" name="visit" class="form-select auto-submit">
        <option value="">Any</option>
        @foreach (['active' => 'Active (last 12 months)', 'lapsed' => 'Lapsed (12+ months)', 'never' => 'Never visited'] as $value => $label)
          <option value="{{ $value }}" @selected(request('visit') === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small" for="patient-alerts">Clinical alerts</label>
      <select id="patient-alerts" name="alerts" class="form-select auto-submit">
        <option value="">Any</option>
        <option value="with" @selected(request('alerts') === 'with')>Has alerts</option>
        <option value="without" @selected(request('alerts') === 'without')>No alerts</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small" for="patient-sort">Sort by</label>
      <select id="patient-sort" name="sort" class="form-select auto-submit">
        @foreach (['newest' => 'Newest patients', 'oldest' => 'Oldest patients', 'name_asc' => 'Name (A–Z)', 'name_desc' => 'Name (Z–A)', 'last_visit' => 'Last visit (recent)', 'last_visit_oldest' => 'Last visit (oldest)'] as $value => $label)
          <option value="{{ $value }}" @selected(request('sort', 'newest') === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <a class="btn btn-sm btn-primary" href="{{ route('patients.index') }}">Reset</a>
    </div>
  </div>
</form>
<script>
(() => {
  const form = document.querySelector('#patient-filter-form');
  if (!form) return;
  form.querySelectorAll('.auto-submit').forEach(control => control.addEventListener('change', () => form.requestSubmit()));
})();
</script>
<div class="card table-responsive">
  <table class="table table-hover align-middle mb-0">
    <thead><tr><th>Patient</th><th>Patient code</th><th>Clinical alerts</th><th>Contact</th><th>Last visit</th><th class="text-end">Record</th></tr></thead>
    <tbody>
    @forelse($patients as $patient)
      @php
        $profile = $patient->healthProfile;
        $checklist = $profile?->structured_checklist ?? [];
        $checklistItems = is_array($checklist) ? ($checklist['items'] ?? (array_is_list($checklist) ? $checklist : [])) : [];
        $alertLabels = ['heart_condition'=>'Heart condition','high_blood_pressure'=>'High blood pressure','diabetes'=>'Diabetes','asthma'=>'Asthma','bleeding_disorder'=>'Bleeding disorder','medication_allergy'=>'Medication allergy','pregnancy'=>'Pregnancy','smoking'=>'Smoking'];
        $recordedFlags = collect($checklistItems)->map(fn ($item) => $alertLabels[$item] ?? null)->filter();
        $allergies = trim((string) ($profile?->allergies ?? ''));
        $conditions = trim((string) ($profile?->medical_conditions ?? ''));
      @endphp
      <tr>
        <td><a class="fw-semibold text-decoration-none" href="{{ route('patients.show',$patient) }}">{{ $patient->full_name }}</a><div class="small text-secondary">{{ ucfirst($patient->sex ?? 'Not specified') }}</div></td>
        <td class="text-nowrap">{{ $patient->patient_code }}</td>
        <td class="small" style="min-width: 220px; max-width: 340px">
          @if(!$profile)
            <span class="text-secondary">Health profile not recorded</span>
          @elseif($allergies || $conditions || $recordedFlags->isNotEmpty())
            @if($allergies)<div><span class="fw-semibold text-danger">Allergies:</span> {{ \Illuminate\Support\Str::limit($allergies, 100) }}</div>@endif
            @if($conditions)<div><span class="fw-semibold">Conditions:</span> {{ \Illuminate\Support\Str::limit($conditions, 100) }}</div>@endif
            @if($recordedFlags->isNotEmpty())<div class="text-secondary">{{ $recordedFlags->join(' · ') }}</div>@endif
          @else
            <span class="text-secondary">No alerts recorded</span>
          @endif
        </td>
        <td class="text-nowrap">{{ $patient->contact_number ?: '—' }}<div class="small text-secondary">{{ $patient->email ?: '' }}</div></td>
        <td class="text-nowrap">{{ $patient->visit_records_max_visit_date ? \Illuminate\Support\Carbon::parse($patient->visit_records_max_visit_date)->format('M j, Y') : 'No visit recorded' }}</td>
        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('patients.show',$patient) }}">Open record</a></td>
      </tr>
    @empty
      <tr><td colspan="6" class="text-center text-secondary py-5">No patient records found.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>
<div class="mt-3">{{ $patients->links('pagination::bootstrap-5') }}</div>
@endsection
