@extends('layouts.app')
@section('title', 'Patients')
@section('page_title', 'Patient Management')
@section('page_subtitle', 'Patient directory and clinical records.')
@section('page_actions')
  @if(auth()->user()->hasRole('admin','clinic_staff'))<a class="btn btn-primary" href="{{ route('patients.create') }}">Add patient</a>@endif
@endsection
@section('content')
<form class="card card-body mb-3" method="GET">
  <div class="row g-2">
    <div class="col-md-8"><label class="form-label small" for="patient-search">Find a patient</label><input id="patient-search" class="form-control" name="q" value="{{ request('q') }}" placeholder="Name, patient code or phone"></div>
    <div class="col-md-4 d-flex align-items-end gap-2"><button class="btn btn-primary">Search</button><a class="btn btn-light" href="{{ route('patients.index') }}">Reset</a></div>
  </div>
</form>
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
