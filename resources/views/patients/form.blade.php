@extends('layouts.app')
@section('title', $mode === 'create' ? 'Add Patient' : 'Edit Patient')
@section('page_title', $mode === 'create' ? 'Add Patient' : 'Edit Patient')
@section('page_subtitle', 'General information, contact information, and health profile')
@section('content')
@php
  $ownProfile = $ownProfile ?? false;
  $canEditHealthProfile = $mode === 'edit' && auth()->user()->hasRole('admin', 'clinic_staff', 'dentist');
  $healthProfile = $patient->healthProfile;
  $healthChecklist = ['heart_condition'=>'Heart condition','high_blood_pressure'=>'High blood pressure','diabetes'=>'Diabetes','asthma'=>'Asthma','bleeding_disorder'=>'Bleeding disorder','medication_allergy'=>'Medication allergy','pregnancy'=>'Pregnancy','smoking'=>'Smoking','other'=>'Other'];
  $storedChecklist = $healthProfile?->structured_checklist ?? [];
  $selectedChecklist = is_array($storedChecklist) ? ($storedChecklist['items'] ?? (array_is_list($storedChecklist) ? $storedChecklist : [])) : [];
  $otherDetails = is_array($storedChecklist) && ! array_is_list($storedChecklist) ? ($storedChecklist['other_details'] ?? '') : '';
@endphp

<form method="POST" action="{{ $mode === 'create' ? route('patients.store') : ($ownProfile ? route('patients.own.update') : route('patients.update',$patient)) }}" class="card card-body">
  @csrf @if($mode === 'edit') @method('PUT') @endif
  <h2 class="h6 mb-3">General information</h2>
  <div class="row g-3">
    @foreach([['first_name','First name',true],['middle_name','Middle name',false],['last_name','Last name',true]] as [$key,$label,$required])
      <div class="col-md-4"><label class="form-label" for="{{ $key }}">{{ $label }}</label><input id="{{ $key }}" name="{{ $key }}" class="form-control" maxlength="100" value="{{ old($key,$patient->$key) }}" @required($required)></div>
    @endforeach
    <div class="col-md-4"><label class="form-label" for="birth_date">Birth date</label><input id="birth_date" type="date" name="birth_date" class="form-control" value="{{ old('birth_date',$patient->birth_date?->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required></div>
    <div class="col-md-4"><label class="form-label" for="sex">Sex</label><select id="sex" name="sex" class="form-select"><option value="">Not specified</option>@foreach(['female'=>'Female','male'=>'Male','other'=>'Other'] as $value=>$label)<option value="{{ $value }}" @selected(old('sex',$patient->sex)===$value)>{{ $label }}</option>@endforeach</select></div>
    @if($mode === 'edit')<div class="col-md-4"><label class="form-label">Patient code</label><input class="form-control" value="{{ $patient->patient_code }}" readonly></div>@endif

    <div class="col-12"><hr class="my-1"><h2 class="h6 mb-0">Contact information</h2></div>
    <div class="col-md-4"><label class="form-label" for="contact_number">Contact number</label><input id="contact_number" name="contact_number" class="form-control" maxlength="20" value="{{ old('contact_number',$patient->contact_number) }}" required></div>
    <div class="col-md-6"><label class="form-label" for="email">Email</label><input id="email" type="email" name="email" class="form-control" maxlength="255" value="{{ old('email',$patient->email) }}"></div>
    <div class="col-12"><label class="form-label" for="address">Address</label><textarea id="address" name="address" class="form-control" rows="2" maxlength="500">{{ old('address',$patient->address) }}</textarea></div>

    <div class="col-12"><h3 class="h6 mb-0">Emergency contact</h3></div>
    <div class="col-md-4"><label class="form-label" for="emergency_contact_name">Name</label><input id="emergency_contact_name" name="emergency_contact_name" class="form-control" value="{{ old('emergency_contact_name',$patient->emergency_contact_name) }}" maxlength="150"></div>
    <div class="col-md-4"><label class="form-label" for="emergency_contact_relationship">Relationship</label><input id="emergency_contact_relationship" name="emergency_contact_relationship" class="form-control" value="{{ old('emergency_contact_relationship',$patient->emergency_contact_relationship) }}" maxlength="100"></div>
    <div class="col-md-4"><label class="form-label" for="emergency_contact_number">Contact number</label><input id="emergency_contact_number" name="emergency_contact_number" class="form-control" value="{{ old('emergency_contact_number',$patient->emergency_contact_number) }}" maxlength="20"></div>

    <div class="col-12"><h3 class="h6 mb-0">Guardian <span class="fw-normal text-secondary">(if applicable)</span></h3></div>
    <div class="col-md-6"><label class="form-label" for="guardian_name">Guardian name</label><input id="guardian_name" name="guardian_name" class="form-control" value="{{ old('guardian_name',$patient->guardian_name) }}" maxlength="150"></div>
    <div class="col-md-6"><label class="form-label" for="guardian_relationship">Guardian relationship</label><input id="guardian_relationship" name="guardian_relationship" class="form-control" value="{{ old('guardian_relationship',$patient->guardian_relationship) }}" maxlength="100"></div>
  </div>
  <div class="d-flex gap-2 mt-4"><button class="btn btn-primary">{{ $mode === 'create' ? 'Create patient' : 'Save general and contact information' }}</button><a class="btn btn-light" href="{{ $ownProfile ? route('patients.own') : ($mode === 'edit' ? route('patients.show',$patient) : route('patients.index')) }}">Cancel</a></div>
</form>

@if($canEditHealthProfile)
  <details class="card card-body mt-3">
    <summary class="h6 mb-0">Edit health profile</summary>
    <p class="small text-secondary mt-2 mb-3">Medical and dental information is saved separately from general and contact information.</p>
    <form method="POST" action="{{ route('patients.health',$patient) }}">
    @csrf @method('PUT')
    <div class="row g-3">
      <div class="col-12"><h3 class="h6 mb-0">Medical information</h3></div>
      <div class="col-md-4"><label class="form-label" for="blood_type">Blood type</label><select id="blood_type" class="form-select" name="blood_type">@foreach(['unknown','A+','A-','B+','B-','AB+','AB-','O+','O-'] as $blood)<option value="{{ $blood }}" @selected(old('blood_type',$healthProfile?->blood_type ?? 'unknown')===$blood)>{{ strtoupper($blood)==='UNKNOWN'?'Unknown':$blood }}</option>@endforeach</select></div>
      @foreach(['allergies'=>'Allergies','medical_conditions'=>'Medical conditions','current_medications'=>'Current medications'] as $field=>$label)
        <div class="col-12"><label class="form-label" for="{{ $field }}">{{ $label }}</label><textarea id="{{ $field }}" class="form-control" rows="2" name="{{ $field }}">{{ old($field,$healthProfile?->$field) }}</textarea></div>
      @endforeach
      <div class="col-12"><div class="form-label">Medical checklist</div><div class="d-flex flex-wrap gap-3">@foreach($healthChecklist as $key=>$label)<label class="form-check small"><input class="form-check-input" type="checkbox" name="checklist[]" value="{{ $key }}" @checked(in_array($key,old('checklist',$selectedChecklist),true))>{{ $label }}</label>@endforeach</div>
        <label class="form-label mt-2" for="checklist_other">Other condition details</label><input id="checklist_other" type="text" name="checklist_other" class="form-control" maxlength="1000" value="{{ old('checklist_other',$otherDetails) }}" placeholder="Describe other condition">
      </div>
      <div class="col-12"><h3 class="h6 mb-0">Dental information</h3><label class="form-label" for="oral_history">Oral / dental history</label><textarea id="oral_history" class="form-control" rows="2" name="oral_history">{{ old('oral_history',$healthProfile?->oral_history) }}</textarea></div>
    </div>
      <div class="mt-3"><button class="btn btn-primary">Save health profile</button></div>
    </form>
  </details>
@endif
@endsection
