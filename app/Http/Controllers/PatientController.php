<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\HealthProfile;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PatientAttachment;
use App\Models\Prescription;
use App\Models\VisitRecord;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function own(Request $request)
    {
        $patient = $request->user()->patient;
        abort_if(! $patient, 404, 'No patient record is linked to this account.');
        return $this->show($request, $patient);
    }

    public function editOwn(Request $request)
    {
        $patient = $request->user()->patient;
        abort_if(! $patient, 404, 'No patient record is linked to this account.');
        return view('patients.form', ['patient' => $patient, 'mode' => 'edit', 'ownProfile' => true]);
    }

    public function updateOwn(Request $request)
    {
        $patient = $request->user()->patient;
        abort_if(! $patient, 404, 'No patient record is linked to this account.');
        $v = $this->identityData($request, $patient);
        $old = $patient->only(array_keys($v));
        $patient->update($v);
        Audit::log('update', 'pat_patients', $patient->patient_id, 'Patient updated own identity/contact information', $old, $v);
        return redirect()->route('patients.own')->with('status', 'Your patient information has been updated.');
    }

    public function index(Request $request)
    {
        $this->clinicAccess($request);
        $query = Patient::query()
            ->with(['user', 'healthProfile'])
            ->withCount('appointments')
            ->withMax('visitRecords', 'visit_date');
        if ($request->user()->hasRole('dentist')) {
            $dentistId = $request->user()->dentist?->dentist_id ?? 0;
            $query->whereHas('appointments', fn ($a) => $a->where('assigned_dentist_id', $dentistId));
        }
        $search = trim((string) $request->query('q'));
        if ($search !== '') {
            // Match each word independently so full names work across separate columns.
            $terms = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($terms as $term) {
                $query->where(fn ($w) => $w->where('first_name', 'like', "%$term%")
                    ->orWhere('middle_name', 'like', "%$term%")
                    ->orWhere('last_name', 'like', "%$term%")
                    ->orWhere('patient_code', 'like', "%$term%")
                    ->orWhere('contact_number', 'like', "%$term%"));
            }
        }
        return view('patients.index', ['patients' => $query->orderByDesc('created_at')->orderByDesc('patient_id')->paginate(20)->withQueryString()]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->hasRole('admin', 'clinic_staff'), 403);
        return view('patients.form', ['patient' => new Patient(), 'mode' => 'create']);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasRole('admin', 'clinic_staff'), 403);
        $v = $this->identityData($request);
        $patient = DB::transaction(function () use ($v, $request) {
            $patient = Patient::create($v + ['patient_code' => Patient::nextCode(), 'created_by_user_id' => $request->user()->user_id]);
            HealthProfile::create(['patient_id' => $patient->patient_id, 'blood_type' => 'unknown']);
            return $patient;
        });
        Audit::log('create', 'pat_patients', $patient->patient_id, 'Patient record created');
        return redirect()->route('patients.show', $patient)->with('status', 'Patient record created.');
    }

    public function show(Request $request, Patient $patient)
    {
        $this->authorizePatient($request, $patient);
        $user = $request->user();
        $patient->load(['user', 'healthProfile.updatedBy', 'attachments.visitRecord']);
        $consents = collect();
        if (! $user->hasRole('clinic_staff')) {
            $patient->load([
                'consents' => fn ($q) => $q->orderByDesc('accepted_at'),
                'user.consents' => fn ($q) => $q->orderByDesc('accepted_at'),
            ]);
            $consents = $patient->consents->merge($patient->user?->consents ?? [])
                ->unique('consent_id')->sortByDesc('accepted_at')->values();
        }
        $records = collect();
        if (! $user->hasRole('clinic_staff')) {
            $records = $patient->visitRecords()->with(['dentist.user', 'appointment', 'prescriptions', 'labRequests'])->get();
            if ($user->hasRole('dentist')) $records = $records->where('dentist_id', $user->dentist?->dentist_id)->values();
        }
        return view('patients.show', [
            'patient' => $patient, 'records' => $records, 'consents' => $consents,
            'outstandingPayments' => $user->hasRole('clinic_staff', 'dentist') ? Payment::where('patient_id', $patient->patient_id)->where('balance_amount', '>', 0)->orderByDesc('payment_date')->get() : collect(),
            'appointments' => $user->hasRole('admin', 'clinic_staff') ? $patient->appointments()->with('dentist.user', 'service')->orderByDesc('start_at')->limit(20)->get() : collect(),
            'canEditIdentity' => $user->hasRole('admin', 'clinic_staff', 'dentist', 'patient'),
            'canEditHealth' => $user->hasRole('clinic_staff', 'dentist'),
            'canWriteVisits' => $user->hasRole('dentist') && (bool) $user->dentist,
            'canUpload' => $user->hasRole('admin', 'clinic_staff', 'dentist'),
            'canReadAttachments' => $user->hasRole('admin', 'clinic_staff', 'dentist', 'patient'),
            'canEditVisits' => $user->hasRole('dentist') && (bool) $user->dentist,
            'canRecordConsent' => $user->hasRole('clinic_staff'),
            'consentPolicyVersion' => Settings::get('privacy_policy_version'),
            'appointmentsForVisit' => $user->hasRole('dentist') ? $patient->appointments()->where('assigned_dentist_id', $user->dentist?->dentist_id)->with('service')->orderByDesc('start_at')->limit(30)->get() : collect(),
        ]);
    }

    public function edit(Request $request, Patient $patient)
    {
        $this->authorizePatient($request, $patient);
        abort_unless($request->user()->hasRole('admin', 'clinic_staff', 'dentist'), 403);
        $patient->loadMissing('healthProfile');
        return view('patients.form', ['patient' => $patient, 'mode' => 'edit', 'ownProfile' => false]);
    }

    public function update(Request $request, Patient $patient)
    {
        $this->authorizePatient($request, $patient);
        abort_unless($request->user()->hasRole('admin', 'clinic_staff', 'dentist'), 403);
        $v = $this->identityData($request, $patient);
        $old = $patient->only(array_keys($v));
        $patient->update($v);
        Audit::log('update', 'pat_patients', $patient->patient_id, 'Patient identity/contact updated', $old, $v);
        return redirect()->route('patients.show', $patient)->with('status', 'Patient information updated.');
    }

    public function health(Request $request, Patient $patient)
    {
        $this->authorizePatient($request, $patient);
        abort_unless($request->user()->hasRole('admin', 'clinic_staff', 'dentist'), 403);
        $v = $request->validate([
            'blood_type' => ['required', Rule::in(['A+','A-','B+','B-','AB+','AB-','O+','O-','unknown'])],
            'allergies' => 'nullable|string|max:20000', 'medical_conditions' => 'nullable|string|max:20000',
            'current_medications' => 'nullable|string|max:20000', 'oral_history' => 'nullable|string|max:20000',
            'checklist' => 'nullable|array', 'checklist.*' => ['string', Rule::in(['heart_condition','high_blood_pressure','diabetes','asthma','bleeding_disorder','medication_allergy','pregnancy','smoking','other'])],
            'checklist_other' => [Rule::requiredIf(fn () => in_array('other', (array) $request->input('checklist', []), true)), 'nullable', 'string', 'max:1000'],
        ]);
        $checklist = $v['checklist'] ?? [];
        $profile = HealthProfile::firstOrNew(['patient_id' => $patient->patient_id]);
        $profile->fill([
            'blood_type' => $v['blood_type'], 'allergies' => $v['allergies'] ?? null,
            'medical_conditions' => $v['medical_conditions'] ?? null, 'current_medications' => $v['current_medications'] ?? null,
            'oral_history' => $v['oral_history'] ?? null,
            'structured_checklist' => [
                'items' => $checklist,
                'other_details' => in_array('other', $checklist, true) ? trim($v['checklist_other']) : null,
            ],
            'last_reviewed_at' => now(), 'updated_by_user_id' => $request->user()->user_id,
        ])->save();
        Audit::log('update', 'pat_health_profiles', $profile->health_profile_id, 'Patient health profile reviewed');
        return back()->with('status', 'Health profile saved and review date updated.');
    }

    public function storeConsent(Request $request, Patient $patient)
    {
        $this->authorizePatient($request, $patient);
        abort_unless($request->user()->hasRole('clinic_staff'), 403);
        $v = $request->validate([
            'policy_version' => 'required|string|max:20',
            'is_accepted' => ['required', Rule::in(['0', '1', 0, 1])],
            'accepted_at' => 'nullable|date|before_or_equal:today',
        ]);
        $consent = $patient->consents()->create([
            'user_id' => $patient->user_id,
            'consent_type' => 'data_privacy',
            'policy_version' => $v['policy_version'],
            'is_accepted' => (int) $v['is_accepted'],
            'accepted_at' => $v['accepted_at'] ?? now(),
        ]);
        Audit::log('create', 'usr_consents', $consent->consent_id, 'Clinic staff recorded patient data privacy consent');
        return back()->with('status', 'Consent record saved.');
    }

    public function visit(Request $request, Patient $patient)
    {
        $this->authorizePatient($request, $patient);
        $this->dentistPatient($request, $patient);
        $v = $request->validate([
            'appointment_id' => ['nullable', Rule::exists('apt_appointments', 'appointment_id')->where('patient_id', $patient->patient_id)->where('assigned_dentist_id', $request->user()->dentist->dentist_id)],
            'visit_date' => 'required|date|before_or_equal:today', 'chief_complaint' => 'nullable|string|max:10000',
            'diagnosis' => 'nullable|string|max:10000', 'clinical_notes' => 'nullable|string|max:20000',
            'blood_pressure' => 'nullable|string|max:20', 'pulse' => 'nullable|integer|min:1|max:300', 'last_xray_date' => 'nullable|date|before_or_equal:today',
            'medication_name' => 'nullable|string|max:150', 'dosage' => 'nullable|string|max:100', 'frequency' => 'nullable|string|max:100',
            'duration' => 'nullable|string|max:100', 'instructions' => 'nullable|string|max:500',
            'scan_type' => 'nullable|string|max:100', 'lab_reason' => 'nullable|string|max:500',
        ]);
        $record = DB::transaction(function () use ($v, $patient, $request) {
            $record = VisitRecord::create([
                'patient_id' => $patient->patient_id, 'appointment_id' => $v['appointment_id'] ?? null,
                'dentist_id' => $request->user()->dentist->dentist_id, 'visit_date' => $v['visit_date'],
                'chief_complaint' => $v['chief_complaint'] ?? null, 'diagnosis' => $v['diagnosis'] ?? null,
                'clinical_notes' => $v['clinical_notes'] ?? null, 'blood_pressure' => $v['blood_pressure'] ?? null,
                'pulse' => $v['pulse'] ?? null, 'last_xray_date' => $v['last_xray_date'] ?? null,
            ]);
            if (! empty($v['medication_name'])) Prescription::create([
                'visit_record_id' => $record->visit_record_id, 'patient_id' => $patient->patient_id, 'dentist_id' => $record->dentist_id,
                'medication_name' => $v['medication_name'], 'dosage' => $v['dosage'] ?? null, 'frequency' => $v['frequency'] ?? null,
                'duration' => $v['duration'] ?? null, 'instructions' => $v['instructions'] ?? null,
            ]);
            if (! empty($v['scan_type'])) LabRequest::create([
                'patient_id' => $patient->patient_id, 'visit_record_id' => $record->visit_record_id,
                'dentist_id' => $record->dentist_id, 'scan_type' => $v['scan_type'], 'reason' => $v['lab_reason'] ?? null,
            ]);
            return $record;
        });
        Audit::log('create', 'pat_visit_records', $record->visit_record_id, 'Clinical visit record created');
        return back()->with('status', 'Visit record saved.');
    }

    public function updateVisit(Request $request, Patient $patient, VisitRecord $visitRecord)
    {
        $this->authorizePatient($request, $patient);
        $this->dentistPatient($request, $patient);
        abort_unless((int) $visitRecord->patient_id === (int) $patient->patient_id, 404);
        abort_unless((int) $visitRecord->dentist_id === (int) $request->user()->dentist->dentist_id, 403);

        $v = $request->validate([
            'visit_date' => 'required|date|before_or_equal:today', 'chief_complaint' => 'nullable|string|max:10000',
            'diagnosis' => 'nullable|string|max:10000', 'clinical_notes' => 'nullable|string|max:20000',
            'blood_pressure' => 'nullable|string|max:20', 'pulse' => 'nullable|integer|min:1|max:300',
            'last_xray_date' => 'nullable|date|before_or_equal:today',
            'medication_name' => 'nullable|string|max:150', 'dosage' => 'nullable|string|max:100', 'frequency' => 'nullable|string|max:100',
            'duration' => 'nullable|string|max:100', 'instructions' => 'nullable|string|max:500',
            'scan_type' => 'nullable|string|max:100', 'lab_reason' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($v, $visitRecord, $patient, $request) {
            $visitRecord->update([
                'visit_date' => $v['visit_date'], 'chief_complaint' => $v['chief_complaint'] ?? null,
                'diagnosis' => $v['diagnosis'] ?? null, 'clinical_notes' => $v['clinical_notes'] ?? null,
                'blood_pressure' => $v['blood_pressure'] ?? null, 'pulse' => $v['pulse'] ?? null,
                'last_xray_date' => $v['last_xray_date'] ?? null,
            ]);

            $prescription = $visitRecord->prescriptions()->first();
            if (! empty($v['medication_name'])) {
                $attributes = [
                    'patient_id' => $patient->patient_id, 'dentist_id' => $request->user()->dentist->dentist_id,
                    'medication_name' => $v['medication_name'], 'dosage' => $v['dosage'] ?? null,
                    'frequency' => $v['frequency'] ?? null, 'duration' => $v['duration'] ?? null,
                    'instructions' => $v['instructions'] ?? null,
                ];
                $prescription ? $prescription->update($attributes) : $visitRecord->prescriptions()->create($attributes);
            }

            $labRequest = $visitRecord->labRequests()->first();
            if (! empty($v['scan_type'])) {
                $attributes = [
                    'patient_id' => $patient->patient_id, 'dentist_id' => $request->user()->dentist->dentist_id,
                    'scan_type' => $v['scan_type'], 'reason' => $v['lab_reason'] ?? null,
                ];
                $labRequest ? $labRequest->update($attributes) : $visitRecord->labRequests()->create($attributes);
            }
        });

        Audit::log('update', 'pat_visit_records', $visitRecord->visit_record_id, 'Clinical visit record updated');
        return back()->with('status', 'Visit record updated.');
    }

    public function upload(Request $request, Patient $patient)
    {
        $this->authorizePatient($request, $patient);
        abort_unless($request->user()->hasRole('admin', 'clinic_staff', 'dentist'), 403);
        if ($request->user()->hasRole('dentist')) $this->dentistPatient($request, $patient);
        $v = $request->validate([
            'attachment' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
            'attachment_type' => ['required', Rule::in(['xray','lab_result','prescription','medical_certificate','other'])],
            'visit_record_id' => ['nullable', Rule::exists('pat_visit_records', 'visit_record_id')->where('patient_id', $patient->patient_id)],
        ]);
        $file = $v['attachment'];
        $path = $file->store("patient-records/{$patient->patient_id}", 'local');
        $attachment = PatientAttachment::create([
            'patient_id' => $patient->patient_id, 'visit_record_id' => $v['visit_record_id'] ?? null,
            'attachment_type' => $v['attachment_type'], 'original_file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'stored_file_path' => $path, 'mime_type' => $file->getMimeType(), 'file_size_bytes' => $file->getSize(),
            'uploaded_by_user_id' => $request->user()->user_id,
        ]);
        Audit::log('create', 'pat_attachments', $attachment->attachment_id, 'Patient attachment uploaded');
        $message = 'Uploaded "'.$attachment->original_file_name.'" successfully.';
        if ($request->user()->hasRole('admin', 'clinic_staff')) {
            $message .= ' You can view and download it from the attachments list below.';
        }
        return back()->with('status', $message);
    }

    public function download(Request $request, PatientAttachment $attachment)
    {
        $patient = Patient::withTrashed()->findOrFail($attachment->patient_id);
        return $this->streamAttachment($request, $patient, $attachment);
    }

    public function downloadClinic(Request $request, Patient $patient, PatientAttachment $attachment)
    {
        abort_unless((int) $patient->patient_id === (int) $attachment->patient_id, 404);
        return $this->streamAttachment($request, $patient, $attachment);
    }

    public function viewAttachment(Request $request, Patient $patient, PatientAttachment $attachment)
    {
        abort_unless((int) $patient->patient_id === (int) $attachment->patient_id, 404);
        abort_unless($request->user()->hasRole('admin', 'clinic_staff', 'dentist'), 403);
        return $this->streamAttachment($request, $patient, $attachment, 'inline');
    }

    private function streamAttachment(Request $request, Patient $patient, PatientAttachment $attachment, string $disposition = 'attachment')
    {
        abort_if($attachment->deleted_at, 404);
        abort_unless($request->user()->hasRole('admin', 'clinic_staff', 'dentist', 'patient'), 403);
        $this->authorizePatient($request, $patient);
        if ($request->user()->hasRole('dentist')) $this->dentistPatient($request, $patient);
        abort_unless(Storage::disk('local')->exists($attachment->stored_file_path), 404);
        return Storage::disk('local')->response(
            $attachment->stored_file_path,
            $attachment->original_file_name,
            ['Content-Type' => $attachment->mime_type, 'X-Content-Type-Options' => 'nosniff'],
            $disposition
        );
    }

    private function identityData(Request $request, ?Patient $patient = null): array
    {
        return $request->validate([
            'first_name' => 'required|string|max:100', 'middle_name' => 'nullable|string|max:100', 'last_name' => 'required|string|max:100',
            'birth_date' => 'required|date|before_or_equal:today', 'sex' => ['nullable', Rule::in(['male','female','other'])],
            'contact_number' => 'required|string|max:20', 'email' => 'nullable|email|max:255', 'address' => 'nullable|string|max:500',
            'emergency_contact_name' => 'nullable|string|max:150', 'emergency_contact_number' => 'nullable|string|max:20',
            'emergency_contact_relationship' => 'nullable|string|max:100', 'guardian_name' => 'nullable|string|max:150',
            'guardian_relationship' => 'nullable|string|max:100',
        ]);
    }

    private function clinicAccess(Request $request): void { abort_unless($request->user()->hasRole('admin', 'clinic_staff', 'dentist'), 403); }

    private function authorizePatient(Request $request, Patient $patient): void
    {
        $user = $request->user();
        if ($user->hasRole('patient')) abort_unless((int) $user->patient?->patient_id === (int) $patient->patient_id, 403);
        elseif ($user->hasRole('dentist')) $this->dentistPatient($request, $patient);
        else $this->clinicAccess($request);
    }

    private function dentistPatient(Request $request, Patient $patient): void
    {
        abort_unless($request->user()->hasRole('dentist') && $patient->appointments()->where('assigned_dentist_id', $request->user()->dentist?->dentist_id ?? 0)->exists(), 403);
    }
}
