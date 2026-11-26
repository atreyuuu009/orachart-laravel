@extends('layouts.app')
@section('title', 'Patients')
@php
  $activeFilterCount = collect([
    request('sex'),
    request('age'),
    $activeSort !== 'newest' ? $activeSort : null,
  ])->filter()->count();
@endphp
@section('page_title', 'Patient Management')
@section('page_subtitle', 'Patient directory and clinical records.')
@section('page_actions')
  @if(auth()->user()->hasRole('admin','clinic_staff'))
    <a class="btn btn-primary" href="{{ route('patients.create') }}">Add patient</a>
  @endif
@endsection

@section('content')
<form id="patient-filter-form" method="GET" action="{{ route('patients.index') }}">
  @if ($activeAlerts !== '')
    <input type="hidden" name="alerts" value="{{ $activeAlerts }}">
  @endif
  @if ($activeVisit !== '')
    <input type="hidden" name="visit" id="patientFilterVisit" value="{{ $activeVisit }}">
  @else
    <input type="hidden" name="visit" id="patientFilterVisit" value="">
  @endif
  <input type="hidden" name="sort" id="patientFilterSort" value="{{ $activeSort }}">

  <div class="apt-toolbar">
    <div class="apt-search-wrap">
      <i class="bi bi-search"></i>
      <input type="search" id="patient-search" name="q" value="{{ request('q') }}" class="form-control apt-search-input" placeholder="Search name, patient code or phone" aria-label="Find a patient">
    </div>

    <div class="apt-period-seg" role="group" aria-label="Last visit filter">
      @foreach (['' => 'All', 'active' => 'Active', 'lapsed' => 'Lapsed', 'never' => 'Never visited'] as $vKey => $vLabel)
        <button type="button" class="apt-period-btn {{ $activeVisit === $vKey ? 'active' : '' }}" data-visit="{{ $vKey }}">{{ $vLabel }}</button>
      @endforeach
    </div>

    <button class="btn apt-filter-btn" type="button" data-bs-toggle="collapse" data-bs-target="#patientFiltersPanel" aria-expanded="false" aria-controls="patientFiltersPanel">
      <i class="bi bi-sliders"></i>
      <span>Filters</span>
      <span class="apt-filter-count">{{ $activeFilterCount }}</span>
    </button>
  </div>

  <div class="collapse mb-3" id="patientFiltersPanel">
    <div class="card card-body border shadow-sm">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label small" for="patient-sex">Sex</label>
          <select id="patient-sex" name="sex" class="form-select auto-submit">
            <option value="">All</option>
            @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label)
              <option value="{{ $value }}" @selected(request('sex') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small" for="patient-age">Age group</label>
          <select id="patient-age" name="age" class="form-select auto-submit">
            <option value="">All ages</option>
            @foreach (['child' => 'Child (0–12)', 'teen' => 'Teen (13–19)', 'adult' => 'Adult (20–59)', 'senior' => 'Senior (60+)'] as $value => $label)
              <option value="{{ $value }}" @selected(request('age') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small" for="patient-sort">Sort by</label>
          <select id="patient-sort" class="form-select">
            @foreach (['newest' => 'Newest patients', 'oldest' => 'Oldest patients', 'name_asc' => 'Name (A–Z)', 'name_desc' => 'Name (Z–A)', 'last_visit' => 'Last visit (recent)', 'last_visit_oldest' => 'Last visit (oldest)'] as $value => $label)
              <option value="{{ $value }}" @selected($activeSort === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill">Apply</button>
          <a class="btn btn-light" href="{{ route('patients.index') }}">Reset</a>
        </div>
      </div>
    </div>
  </div>
</form>

@php
  $alertTabs = [
    '' => ['label' => 'All', 'count' => $alertCounts['all'], 'warning' => false],
    'with' => ['label' => 'Has alerts', 'count' => $alertCounts['with'], 'warning' => true],
    'without' => ['label' => 'No alerts', 'count' => $alertCounts['without'], 'warning' => false],
  ];
@endphp

<div class="apt-status-bar">
  <div class="apt-status-tabs">
    @foreach ($alertTabs as $tabKey => $tab)
      @php
        $tabUrl = $tabKey === ''
          ? route('patients.index', request()->except('alerts', 'page'))
          : route('patients.index', array_merge(request()->except('page'), ['alerts' => $tabKey]));
      @endphp
      <a href="{{ $tabUrl }}" class="apt-status-tab {{ $activeAlerts === $tabKey ? 'active' : '' }}">
        <span>{{ $tab['label'] }}</span>
        <span class="apt-tab-count {{ $tab['warning'] && $tab['count'] > 0 ? 'apt-tab-count-warning' : '' }}">{{ $tab['count'] }}</span>
      </a>
    @endforeach
  </div>
  <a href="{{ route('patients.index', array_merge(request()->except('page'), ['sort' => $activeSort === 'oldest' ? 'newest' : 'oldest'])) }}"
     class="apt-sort-btn"
     title="Toggle newest / oldest patients"
     aria-label="Toggle sort order">
    <i class="bi bi-arrow-down-up"></i>
  </a>
</div>

<script>
(() => {
  const form = document.querySelector('#patient-filter-form');
  if (!form) return;

  form.querySelectorAll('.auto-submit').forEach(control => {
    control.addEventListener('change', () => form.requestSubmit());
  });

  form.querySelectorAll('[data-visit]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('patientFilterVisit').value = btn.dataset.visit;
      form.requestSubmit();
    });
  });

  const sortSelect = document.getElementById('patient-sort');
  const sortHidden = document.getElementById('patientFilterSort');
  if (sortSelect && sortHidden) {
    sortSelect.addEventListener('change', () => {
      sortHidden.value = sortSelect.value;
      form.requestSubmit();
    });
  }
})();
</script>

<div class="apt-list-card">
  <div class="apt-date-header">
    <span>Patient directory</span>
    <span class="apt-date-count">{{ $patients->total() }} {{ \Illuminate\Support\Str::plural('patient', $patients->total()) }}</span>
  </div>

  @forelse($patients as $patient)
    @php
      $profile = $patient->healthProfile;
      $checklist = $profile?->structured_checklist ?? [];
      $checklistItems = is_array($checklist) ? ($checklist['items'] ?? (array_is_list($checklist) ? $checklist : [])) : [];
      $alertLabels = ['heart_condition'=>'Heart condition','high_blood_pressure'=>'High blood pressure','diabetes'=>'Diabetes','asthma'=>'Asthma','bleeding_disorder'=>'Bleeding disorder','medication_allergy'=>'Medication allergy','pregnancy'=>'Pregnancy','smoking'=>'Smoking'];
      $recordedFlags = collect($checklistItems)->map(fn ($item) => $alertLabels[$item] ?? null)->filter();
      $allergies = trim((string) ($profile?->allergies ?? ''));
      $conditions = trim((string) ($profile?->medical_conditions ?? ''));
      $hasAlert = $profile && ($allergies !== '' || $conditions !== '' || $recordedFlags->isNotEmpty());
    @endphp
    <div class="pat-row {{ $hasAlert ? 'pat-row-alert' : '' }}">
      <div class="pat-col-main">
        <a class="pat-name" href="{{ route('patients.show', $patient) }}">{{ $patient->full_name }}</a>
        <div class="pat-sub">{{ $patient->patient_code }} · {{ ucfirst($patient->sex ?? 'Not specified') }}</div>
      </div>

      <div class="pat-col-alerts small">
        @if(!$profile)
          <span class="pat-muted">Health profile not recorded</span>
        @elseif($hasAlert)
          @if($allergies)<div><span class="fw-semibold text-danger">Allergies:</span> {{ \Illuminate\Support\Str::limit($allergies, 90) }}</div>@endif
          @if($conditions)<div><span class="fw-semibold">Conditions:</span> {{ \Illuminate\Support\Str::limit($conditions, 90) }}</div>@endif
          @if($recordedFlags->isNotEmpty())<div class="text-secondary">{{ $recordedFlags->join(' · ') }}</div>@endif
        @else
          <span class="pat-muted">No alerts recorded</span>
        @endif
      </div>

      <div class="pat-col-contact">
        <div class="pat-contact-main">{{ $patient->contact_number ?: '—' }}</div>
        @if($patient->email)
          <div class="pat-sub">{{ $patient->email }}</div>
        @endif
      </div>

      <div class="pat-col-visit">
        <div class="pat-visit-main">
          {{ $patient->visit_records_max_visit_date ? \Illuminate\Support\Carbon::parse($patient->visit_records_max_visit_date)->format('M j, Y') : 'No visit recorded' }}
        </div>
        @if($patient->appointments_count)
          <div class="pat-sub">{{ $patient->appointments_count }} {{ \Illuminate\Support\Str::plural('appointment', $patient->appointments_count) }}</div>
        @endif
      </div>

      <div class="pat-col-action text-end">
        <a class="btn btn-sm btn-outline-primary" href="{{ route('patients.show', $patient) }}">Open record</a>
      </div>
    </div>
  @empty
    <div class="text-center text-secondary py-5">No patient records found.</div>
  @endforelse
</div>

<div class="mt-3">{{ $patients->links('pagination::bootstrap-5') }}</div>
@endsection
