@extends('layouts.app')
@section('title', $patient->full_name.' - Patient')
@section('page_title', $patient->full_name)
@section('page_subtitle', $patient->patient_code.' · '.($patient->birth_date?->format('M j, Y') ?? 'Birth date unavailable').' · '.($patient->age ?? '—').' years old')
@section('page_actions')
  @if($canEditIdentity)<a class="btn btn-outline-primary" href="{{ auth()->user()->hasRole('patient') ? route('patients.own.edit') : route('patients.edit',$patient) }}">Edit identity</a>@endif
  @if(auth()->user()->hasRole('admin','dentist'))<a class="btn btn-outline-secondary" href="{{ route('odontogram',['patient'=>$patient->patient_id]) }}">Odontogram</a>@endif
@endsection
@section('content')
@php
  $me = auth()->user();
  $hp = $patient->healthProfile;
  $checklist = ['heart_condition'=>'Heart condition','high_blood_pressure'=>'High blood pressure','diabetes'=>'Diabetes','asthma'=>'Asthma','bleeding_disorder'=>'Bleeding disorder','medication_allergy'=>'Medication allergy','pregnancy'=>'Pregnancy','smoking'=>'Smoking','other'=>'Other'];
  $storedChecklist = $hp?->structured_checklist ?? [];
  $selectedChecklist = array_is_list($storedChecklist) ? $storedChecklist : ($storedChecklist['items'] ?? []);
  $otherDetails = array_is_list($storedChecklist) ? '' : ($storedChecklist['other_details'] ?? '');
  $selectedChecklist = old('checklist', $selectedChecklist);
  $otherDetails = old('checklist_other', $otherDetails);
  $checklistLabels = collect($selectedChecklist)->map(fn ($key) => $checklist[$key] ?? $key)->filter()->values();
  $hasAlerts = filled($hp?->allergies) || filled($hp?->medical_conditions) || filled($hp?->current_medications) || $checklistLabels->isNotEmpty();
  $lastXray = $records->pluck('last_xray_date')->filter()->sortDesc()->first();
  $lastVisit = $records->sortByDesc('visit_date')->first()?->visit_date;
@endphp
<div class="row g-3">
@if($me->hasRole('clinic_staff','dentist') && $outstandingPayments->isNotEmpty())<div class="col-12"><section class="card record-alert border-danger-subtle"><div class="card-body py-3"><div class="d-flex justify-content-between align-items-center"><h2 class="h6 mb-0 text-danger-emphasis"><i class="bi bi-cash-coin me-2"></i>Outstanding payment balances</h2><a href="{{ route('payments.index',['outstanding'=>1]) }}" class="small">View all</a></div><div class="table-responsive mt-2"><table class="table table-sm mb-0"><thead><tr><th>Bill</th><th>Date</th><th>Original total</th><th>Balance</th><th>Status</th></tr></thead><tbody>@foreach($outstandingPayments as $payment)<tr><td><a href="{{ route('payments.show',$payment) }}">#{{ $payment->payment_id }}</a></td><td>{{ $payment->payment_date?->format('M j, Y') }}</td><td>₱{{ number_format($payment->total_amount,2) }}</td><td class="fw-semibold text-danger">₱{{ number_format($payment->balance_amount,2) }}</td><td>{{ str_replace('_',' ',$payment->status) }}</td></tr>@endforeach</tbody></table></div></div></section></div>@endif

@if($hasAlerts)<div class="col-12"><section class="card record-alert border-danger-subtle"><div class="card-body py-3"><div class="d-flex align-items-start gap-3"><span class="text-danger fs-4 lh-1"><i class="bi bi-exclamation-triangle-fill"></i></span><div class="flex-grow-1"><div class="fw-bold text-danger-emphasis mb-2">Clinical alerts</div><div class="row g-2 small">
@if(filled($hp?->allergies))<div class="col-md-6"><span class="fw-semibold">Allergies:</span> {{ $hp->allergies }}</div>@endif
@if(filled($hp?->medical_conditions))<div class="col-md-6"><span class="fw-semibold">Medical conditions:</span> {{ $hp->medical_conditions }}</div>@endif
@if(filled($hp?->current_medications))<div class="col-md-6"><span class="fw-semibold">Current medications:</span> {{ $hp->current_medications }}</div>@endif
@if($checklistLabels->isNotEmpty())<div class="col-md-6"><span class="fw-semibold">Checklist:</span> {{ $checklistLabels->join(', ') }}@if(in_array('other',$selectedChecklist,true)&&$otherDetails) — {{ $otherDetails }}@endif</div>@endif
</div></div></div></div></section></div>@endif

<div class="col-lg-7"><section class="card h-100">
  <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center"><span><i class="bi bi-person-vcard me-2"></i>Identity and contact</span><span class="small fw-normal text-secondary">{{ $patient->patient_code }}</span></div>
  <div class="card-body">
    <div class="record-section">
      <h3 class="record-section-title">Personal details</h3>
      <dl class="row record-list mb-0 small">
        @foreach(['First name'=>$patient->first_name,'Middle name'=>$patient->middle_name,'Last name'=>$patient->last_name,'Date of birth'=>$patient->birth_date?->format('M j, Y'),'Age'=>$patient->age,'Sex'=>$patient->sex ? ucfirst($patient->sex) : null] as $label=>$value)
        <dt class="col-sm-5">{{ $label }}</dt><dd class="col-sm-7">{{ filled($value) ? $value : '—' }}</dd>
        @endforeach
      </dl>
    </div>
    <div class="record-section">
      <h3 class="record-section-title">Contact details</h3>
      <dl class="row record-list mb-0 small">
        <dt class="col-sm-5">Contact number</dt><dd class="col-sm-7">{{ $patient->contact_number ?: '—' }}</dd>
        <dt class="col-sm-5">Email</dt><dd class="col-sm-7">{{ $patient->email ?: '—' }}</dd>
        <dt class="col-sm-5">Address</dt><dd class="col-sm-7">{!! nl2br(e($patient->address ?: '—')) !!}</dd>
      </dl>
    </div>
    <div class="record-section">
      <h3 class="record-section-title">Emergency contact</h3>
      <dl class="row record-list mb-0 small">
        <dt class="col-sm-5">Name</dt><dd class="col-sm-7">{{ $patient->emergency_contact_name ?: '—' }}</dd>
        <dt class="col-sm-5">Relationship</dt><dd class="col-sm-7">{{ $patient->emergency_contact_relationship ?: '—' }}</dd>
        <dt class="col-sm-5">Contact number</dt><dd class="col-sm-7">{{ $patient->emergency_contact_number ?: '—' }}</dd>
      </dl>
    </div>
    <div class="record-section">
      <h3 class="record-section-title">Guardian</h3>
      <dl class="row record-list mb-0 small">
        <dt class="col-sm-5">Guardian name</dt><dd class="col-sm-7">{{ $patient->guardian_name ?: '—' }}</dd>
        <dt class="col-sm-5">Relationship</dt><dd class="col-sm-7">{{ $patient->guardian_relationship ?: '—' }}</dd>
      </dl>
    </div>
    <div class="record-section">
      <h3 class="record-section-title">Account</h3>
      <dl class="row record-list mb-0 small">
        <dt class="col-sm-5">Linked account</dt><dd class="col-sm-7">{{ $patient->user?->email ?? 'None' }}</dd>
      </dl>
    </div>
  </div>
</section></div>

<div class="col-lg-5"><section class="card h-100">
  <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-start gap-2"><span class="text-nowrap"><i class="bi bi-heart-pulse me-2"></i>Health profile</span>@if($hp?->last_reviewed_at)<span class="small fw-normal text-secondary text-end">Reviewed {{ $hp->last_reviewed_at->format('M j, Y') }}<br>by {{ $hp->updatedBy?->full_name ?? 'Unknown' }}</span>@endif</div>
  <div class="card-body">
@if($canEditHealth)
<form method="POST" action="{{ route('patients.health',$patient) }}">@csrf @method('PUT')
  <div class="record-section">
    <h3 class="record-section-title">Medical alerts</h3>
    <div class="row g-2">
      <div class="col-12"><label class="form-label small">Blood type</label><select class="form-select form-select-sm" name="blood_type">@foreach(['unknown','A+','A-','B+','B-','AB+','AB-','O+','O-'] as $blood)<option @selected(old('blood_type',$hp?->blood_type ?? 'unknown')===$blood) value="{{ $blood }}">{{ strtoupper($blood)==='UNKNOWN'?'Unknown':$blood }}</option>@endforeach</select></div>
      @foreach(['allergies'=>'Allergies','medical_conditions'=>'Medical conditions','current_medications'=>'Current medications'] as $field=>$label)<div class="col-12"><label class="form-label small">{{ $label }}</label><textarea class="form-control form-control-sm" rows="2" name="{{ $field }}">{{ old($field,$hp?->$field) }}</textarea></div>@endforeach
    </div>
  </div>
  <div class="record-section">
    <h3 class="record-section-title">Dental history</h3>
    <label class="form-label small">Oral / dental history</label><textarea class="form-control form-control-sm" rows="2" name="oral_history">{{ old('oral_history',$hp?->oral_history) }}</textarea>
  </div>
  <div class="record-section">
    <h3 class="record-section-title">Screening checklist</h3>
    <div class="d-flex flex-wrap gap-3">@foreach($checklist as $key=>$label)<label class="form-check small"><input id="checklist-{{ $key }}" class="form-check-input" type="checkbox" name="checklist[]" value="{{ $key }}" @checked(in_array($key,$selectedChecklist,true))>{{ $label }}</label>@endforeach</div>
    <div id="checklist-other-details" class="mt-2 {{ in_array('other',$selectedChecklist,true)?'':'d-none' }}"><label for="checklist-other-text" class="form-label small">Please specify other condition</label><input id="checklist-other-text" type="text" name="checklist_other" class="form-control form-control-sm" maxlength="1000" value="{{ $otherDetails }}" placeholder="Describe the condition"></div>
  </div>
  <button class="btn btn-sm btn-primary mt-3">Save health profile</button>
</form>
@else
  <div class="record-section">
    <h3 class="record-section-title">Medical alerts</h3>
    <dl class="row record-list mb-0 small">
      <dt class="col-sm-6">Blood type</dt><dd class="col-sm-6">{{ $hp?->blood_type && $hp->blood_type !== 'unknown' ? $hp->blood_type : 'Unknown' }}</dd>
      <dt class="col-sm-6">Allergies</dt><dd class="col-sm-6">{!! nl2br(e($hp?->allergies ?: '—')) !!}</dd>
      <dt class="col-sm-6">Medical conditions</dt><dd class="col-sm-6">{!! nl2br(e($hp?->medical_conditions ?: '—')) !!}</dd>
      <dt class="col-sm-6">Current medications</dt><dd class="col-sm-6">{!! nl2br(e($hp?->current_medications ?: '—')) !!}</dd>
    </dl>
  </div>
  <div class="record-section">
    <h3 class="record-section-title">Dental history</h3>
    <dl class="row record-list mb-0 small"><dt class="col-sm-6">Oral / dental history</dt><dd class="col-sm-6">{!! nl2br(e($hp?->oral_history ?: '—')) !!}</dd></dl>
  </div>
  <div class="record-section">
    <h3 class="record-section-title">Screening checklist</h3>
    @forelse($checklistLabels as $label)<span class="badge text-bg-light border me-1 mb-1">{{ $label }}</span>@empty<span class="small text-secondary">None marked</span>@endforelse
    @if(in_array('other',$selectedChecklist,true)&&$otherDetails)<div class="small mt-1"><strong>Other:</strong> {{ $otherDetails }}</div>@endif
  </div>
@endif
  </div>
</section></div>

@if($me->hasRole('clinic_staff'))
<div class="col-lg-7"><section class="card h-100">
  <div class="card-header bg-white fw-bold"><i class="bi bi-shield-check me-2"></i>Data privacy consent</div>
  <div class="card-body">
    @if($canRecordConsent)
    <p class="small text-secondary">Record the patient's data privacy consent after it has been discussed and accepted.</p>
    <form method="POST" action="{{ route('patients.consents.store',$patient) }}" class="row g-2 align-items-end">@csrf
      <div class="col-md-4"><label class="form-label small">Policy version</label><input name="policy_version" class="form-control" maxlength="20" value="{{ $consentPolicyVersion }}" required></div>
      <div class="col-md-4"><label class="form-label small">Consent status</label><select name="is_accepted" class="form-select"><option value="1">Accepted</option><option value="0">Declined</option></select></div>
      <div class="col-md-3"><label class="form-label small">Date recorded</label><input type="date" name="accepted_at" class="form-control" value="{{ today()->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}"></div>
      <div class="col-md-1"><button class="btn btn-primary">Save</button></div>
    </form>
    @endif
  </div>
</section></div>
@else
<div class="col-lg-7"><section class="card h-100">
  <div class="card-header bg-white fw-bold"><i class="bi bi-shield-check me-2"></i>Data privacy consent</div>
  <div class="card-body">
    @forelse($consents as $consent)
    <div class="d-flex justify-content-between align-items-start gap-2 border-bottom py-2 small">
      <div>{{ ucfirst(str_replace('_',' ',$consent->consent_type)) }} <span class="badge text-bg-{{ $consent->is_accepted ? 'success' : 'secondary' }}">{{ $consent->is_accepted ? 'Accepted' : 'Declined' }}</span></div>
      <div class="text-secondary text-end">Policy {{ $consent->policy_version }}<br>{{ \Illuminate\Support\Carbon::parse($consent->accepted_at)->format('M j, Y') }}</div>
    </div>
    @empty
    <p class="small text-secondary mb-0">None recorded.</p>
    @endforelse
  </div>
</section></div>
@endif

<div class="col-lg-5"><section class="card h-100">
  <div class="card-header bg-white fw-bold"><i class="bi bi-folder2-open me-2"></i>Dental records</div>
  <div class="card-body">
    <dl class="row record-list mb-0 small">
      <dt class="col-6">Last X-ray</dt><dd class="col-6">{{ $lastXray?->format('M j, Y') ?? 'No date recorded' }}</dd>
      <dt class="col-6">Attachments</dt><dd class="col-6">{{ $patient->attachments->count() }} {{ $patient->attachments->count() === 1 ? 'file' : 'files' }}</dd>
      @if($records->isNotEmpty())
      <dt class="col-6">Last visit</dt><dd class="col-6">{{ $lastVisit?->format('M j, Y') ?? '—' }}</dd>
      <dt class="col-6">Visit records</dt><dd class="col-6">{{ $records->count() }}</dd>
      @endif
    </dl>
  </div>
</section></div>

@if($canUpload)<div class="col-12"><section class="card">
  <div class="card-header bg-white fw-bold"><i class="bi bi-upload me-2"></i>Upload attachment</div>
  <div class="card-body">
    <form method="POST" action="{{ route('patients.attachments.store',$patient) }}" enctype="multipart/form-data" class="row g-2 align-items-end">@csrf
      <div class="col-md-4"><label class="form-label small">File (PDF, JPG, PNG or WebP; max 10 MB)</label><input class="form-control" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>
      <div class="col-md-3"><label class="form-label small">Type</label><select name="attachment_type" class="form-select">@foreach(['xray'=>'X-ray','lab_result'=>'Lab result','prescription'=>'Prescription','medical_certificate'=>'Medical certificate','other'=>'Other'] as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
      <div class="col-md-3"><label class="form-label small">Visit (optional)</label><select name="visit_record_id" class="form-select"><option value="">Patient file</option>@foreach($records as $record)<option value="{{ $record->visit_record_id }}">{{ $record->visit_date->format('M j, Y') }} · #{{ $record->visit_record_id }}</option>@endforeach</select></div>
      <div class="col-md-2"><button class="btn btn-primary w-100">Upload</button></div>
    </form>
  </div>
</section></div>@endif

@if($canReadAttachments)<div class="col-12"><section class="card">
  <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center"><span><i class="bi bi-paperclip me-2"></i>Attachments</span><span class="small fw-normal text-secondary">{{ $patient->attachments->count() }} total</span></div>
  <div class="card-body">
    @forelse($patient->attachments as $file)
    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
      <div><span class="badge text-bg-light">{{ str_replace('_',' ',$file->attachment_type) }}</span> {{ $file->original_file_name }}<div class="small text-secondary">{{ $file->created_at?->format('M j, Y') }}</div></div>
      <div class="d-flex gap-2">@unless($me->hasRole('patient'))<a class="btn btn-sm btn-outline-primary" href="{{ route('patients.attachments.view',['patient'=>$patient->patient_id,'attachment'=>$file->attachment_id]) }}" target="_blank" rel="noopener">View</a>@endunless<a class="btn btn-sm btn-outline-secondary" href="{{ $me->hasRole('patient') ? route('patients.attachments.download',['attachment'=>$file->attachment_id]) : route('patients.attachments.download.clinic',['patient'=>$patient->patient_id,'attachment'=>$file->attachment_id]) }}">Download</a></div>
    </div>
    @empty
    <p class="small text-secondary mb-0">No attachments.</p>
    @endforelse
  </div>
</section></div>@endif

@if($canWriteVisits || $records->isNotEmpty())<div class="col-12 mt-2"><hr class="my-2"><h2 class="h5 mb-0"><i class="bi bi-journal-medical me-2"></i>Clinical history</h2></div>@endif

@if($canWriteVisits)<div class="col-12"><details class="card"><summary class="card-header bg-white fw-semibold">Add per-visit clinical record</summary><div class="card-body"><form method="POST" action="{{ route('patients.visits.store',$patient) }}">@csrf<div class="row g-3">
<div class="col-md-3"><label class="form-label">Visit date</label><input type="date" name="visit_date" class="form-control" max="{{ today()->format('Y-m-d') }}" value="{{ old('visit_date',today()->format('Y-m-d')) }}" required></div><div class="col-md-5"><label class="form-label">Linked appointment</label><select name="appointment_id" class="form-select"><option value="">None</option>@foreach($appointmentsForVisit as $a)<option value="{{ $a->appointment_id }}">{{ $a->start_at->format('M j, Y g:i A') }} · {{ $a->service?->service_name }}</option>@endforeach</select></div>
@foreach(['chief_complaint'=>'Chief complaint','diagnosis'=>'Diagnosis','clinical_notes'=>'Clinical notes'] as $field=>$label)<div class="col-md-4"><label class="form-label">{{ $label }}</label><textarea name="{{ $field }}" class="form-control" rows="3"></textarea></div>@endforeach
<div class="col-md-3"><label class="form-label">Blood pressure</label><input name="blood_pressure" class="form-control" placeholder="e.g. 120/80"></div><div class="col-md-3"><label class="form-label">Pulse (bpm)</label><input type="number" name="pulse" min="1" max="300" class="form-control"></div><div class="col-md-3"><label class="form-label">Last X-ray date</label><input type="date" name="last_xray_date" max="{{ today()->format('Y-m-d') }}" class="form-control"></div>
<div class="col-12"><h3 class="h6 mb-0">Prescription (optional)</h3></div><div class="col-md-4"><label class="form-label">Medication</label><input name="medication_name" class="form-control" maxlength="150"></div><div class="col-md-2"><label class="form-label">Dosage</label><input name="dosage" class="form-control" maxlength="100"></div><div class="col-md-2"><label class="form-label">Frequency</label><input name="frequency" class="form-control" maxlength="100"></div><div class="col-md-2"><label class="form-label">Duration</label><input name="duration" class="form-control" maxlength="100"></div><div class="col-md-12"><label class="form-label">Instructions</label><input name="instructions" class="form-control" maxlength="500"></div>
<div class="col-12"><h3 class="h6 mb-0">Lab / scan request (optional)</h3></div><div class="col-md-4"><label class="form-label">Scan / lab type</label><input name="scan_type" class="form-control" maxlength="100" placeholder="Panoramic, CBCT..."></div><div class="col-md-8"><label class="form-label">Reason</label><input name="lab_reason" class="form-control" maxlength="500"></div>
</div><button class="btn btn-primary mt-3">Save visit record</button></form></div></details></div>@endif

@foreach($records as $record)<div class="col-12"><article class="card card-body"><div class="d-flex justify-content-between"><div><h3 class="h6 mb-1">{{ $record->visit_date->format('F j, Y') }} @if($record->appointment)<span class="badge text-bg-light">Appointment #{{ $record->appointment_id }}</span>@endif</h3><div class="small text-secondary">{{ $record->dentist?->name ?? 'Dentist' }}@if($record->blood_pressure) · BP {{ $record->blood_pressure }}@endif @if($record->pulse) · Pulse {{ $record->pulse }} bpm @endif</div></div>@if($record->last_xray_date)<span class="small text-secondary">Last X-ray: {{ $record->last_xray_date->format('M j, Y') }}</span>@endif</div><div class="row mt-2 small"><div class="col-md-4"><strong>Chief complaint</strong><div>{!! nl2br(e($record->chief_complaint ?: '—')) !!}</div></div><div class="col-md-4"><strong>Diagnosis</strong><div>{!! nl2br(e($record->diagnosis ?: '—')) !!}</div></div><div class="col-md-4"><strong>Clinical notes</strong><div>{!! nl2br(e($record->clinical_notes ?: '—')) !!}</div></div></div>
@foreach($record->prescriptions as $rx)<div class="alert alert-light mt-3 mb-0 small"><strong>Prescription:</strong> {{ $rx->medication_name }} {{ $rx->dosage }} · {{ $rx->frequency }} · {{ $rx->duration }}<br>{{ $rx->instructions }}</div>@endforeach
@foreach($record->labRequests as $lab)<div class="alert alert-light mt-2 mb-0 small"><strong>Lab / scan:</strong> {{ $lab->scan_type }} · {{ ucfirst($lab->status) }} {{ $lab->reason ? '· '.$lab->reason : '' }}</div>@endforeach
@if($canEditVisits)@php $rxEdit=$record->prescriptions->first(); $labEdit=$record->labRequests->first(); @endphp<details class="mt-3"><summary class="small fw-semibold">Edit this visit record</summary><form method="POST" action="{{ route('patients.visits.update',['patient'=>$patient->patient_id,'visitRecord'=>$record->visit_record_id]) }}" class="row g-2 mt-1">@csrf @method('PUT')
<div class="col-md-3"><label class="form-label small">Visit date</label><input type="date" name="visit_date" class="form-control form-control-sm" max="{{ today()->format('Y-m-d') }}" value="{{ $record->visit_date->format('Y-m-d') }}" required></div>
@foreach(['chief_complaint'=>'Chief complaint','diagnosis'=>'Diagnosis','clinical_notes'=>'Clinical notes'] as $field=>$label)<div class="col-md-3"><label class="form-label small">{{ $label }}</label><textarea name="{{ $field }}" class="form-control form-control-sm" rows="2">{{ $record->$field }}</textarea></div>@endforeach
<div class="col-md-3"><label class="form-label small">Blood pressure</label><input name="blood_pressure" class="form-control form-control-sm" value="{{ $record->blood_pressure }}"></div><div class="col-md-3"><label class="form-label small">Pulse (bpm)</label><input type="number" name="pulse" min="1" max="300" class="form-control form-control-sm" value="{{ $record->pulse }}"></div><div class="col-md-3"><label class="form-label small">Last X-ray date</label><input type="date" name="last_xray_date" max="{{ today()->format('Y-m-d') }}" class="form-control form-control-sm" value="{{ $record->last_xray_date?->format('Y-m-d') }}"></div>
<div class="col-12"><strong class="small">Prescription</strong></div><div class="col-md-4"><label class="form-label small">Medication</label><input name="medication_name" class="form-control form-control-sm" maxlength="150" value="{{ $rxEdit?->medication_name }}"></div><div class="col-md-2"><label class="form-label small">Dosage</label><input name="dosage" class="form-control form-control-sm" maxlength="100" value="{{ $rxEdit?->dosage }}"></div><div class="col-md-2"><label class="form-label small">Frequency</label><input name="frequency" class="form-control form-control-sm" maxlength="100" value="{{ $rxEdit?->frequency }}"></div><div class="col-md-2"><label class="form-label small">Duration</label><input name="duration" class="form-control form-control-sm" maxlength="100" value="{{ $rxEdit?->duration }}"></div><div class="col-md-12"><label class="form-label small">Instructions</label><input name="instructions" class="form-control form-control-sm" maxlength="500" value="{{ $rxEdit?->instructions }}"></div>
<div class="col-12"><strong class="small">Lab / scan request</strong></div><div class="col-md-4"><label class="form-label small">Scan / lab type</label><input name="scan_type" class="form-control form-control-sm" maxlength="100" value="{{ $labEdit?->scan_type }}"></div><div class="col-md-8"><label class="form-label small">Reason</label><input name="lab_reason" class="form-control form-control-sm" maxlength="500" value="{{ $labEdit?->reason }}"></div>
<div class="col-12"><button class="btn btn-sm btn-primary">Save visit changes</button></div></form></details>@endif
</article></div>@endforeach

@if($appointments->isNotEmpty())<div class="col-12 mt-2"><hr class="my-2"><section class="card"><div class="card-header bg-white fw-bold"><i class="bi bi-calendar-check me-2"></i>Recent appointments</div><div class="card-body"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Service</th><th>Dentist</th><th>Status</th></tr></thead><tbody>@foreach($appointments as $appointment)<tr><td>{{ $appointment->start_at->format('M j, Y g:i A') }}</td><td>{{ $appointment->service?->service_name }}</td><td>{{ $appointment->dentist?->name }}</td><td>{{ ucfirst(str_replace('_',' ',$appointment->status)) }}</td></tr>@endforeach</tbody></table></div></div></section></div>@endif
</div>
@endsection
@push('scripts')
<script>
  const otherChecklist = document.getElementById('checklist-other');
  const otherDetails = document.getElementById('checklist-other-details');
  const otherText = document.getElementById('checklist-other-text');
  if (otherChecklist && otherDetails) {
    const syncOtherDetails = () => {
      otherDetails.classList.toggle('d-none', !otherChecklist.checked);
      if (otherText) otherText.required = otherChecklist.checked;
    };
    otherChecklist.addEventListener('change', syncOtherDetails);
    syncOtherDetails();
  }
</script>
@endpush
