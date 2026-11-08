<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\HealthProfile;
use App\Models\Patient;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\Notifier;
use App\Services\PatientService;
use App\Services\SlotUnavailable;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function __construct(private BookingService $booking, private AvailabilityService $availability) {}

    // ------------------------------------------------------------- forms
    public function create(Request $request)
    {
        $patient = $request->user()->patient()->with('healthProfile')->first();
        abort_if(! $patient, 403, 'No patient record is linked to this account.');
        return view('booking.form', $this->formData() + ['mode' => 'patient', 'patient' => $patient]);
    }

    public function createForPatient()
    {
        $patients = Patient::orderBy('last_name')->orderBy('first_name')
            ->get(['patient_id', 'patient_code', 'first_name', 'middle_name', 'last_name', 'contact_number']);
        return view('booking.form', $this->formData() + ['mode' => 'staff', 'patients' => $patients, 'patient' => null]);
    }

    private function formData(): array
    {
        return [
            'categories' => ServiceCategory::with(['services' => fn ($q) => $q->bookable()->orderBy('service_id')])
                ->orderBy('category_id')->get()->filter(fn ($c) => $c->services->isNotEmpty()),
            'dentists' => Dentist::with('user', 'specialties')->where('is_accepting_bookings', 1)
                ->whereHas('user', fn ($q) => $q->where('is_active', 1))->get(),
            'noticeHours' => Settings::int('min_booking_notice_hours'),
            'holdMinutes' => Settings::int('slot_lock_minutes') ?: 5,
        ];
    }

    // -------------------------------------------------------------- JSON
    public function slots(Request $request)
    {
        $v = $request->validate([
            'service_id' => 'required|exists:svc_services,service_id',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'dentist_id' => 'nullable|exists:usr_dentists,dentist_id',
            'emergency' => 'nullable|boolean',
            'exclude' => 'nullable|integer',
        ]);
        $svc = Service::bookable()->findOrFail($v['service_id']);
        return response()->json($this->availability->slots(
            $svc, Carbon::parse($v['date']), $v['dentist_id'] ?? null, (bool) ($v['emergency'] ?? false),
            $v['exclude'] ?? null, $request->session()->getId()
        ));
    }

    public function hold(Request $request)
    {
        $v = $request->validate([
            'service_id' => 'required|exists:svc_services,service_id',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'dentist_id' => 'nullable|exists:usr_dentists,dentist_id',
            'emergency' => 'nullable|boolean',
            'exclude' => 'nullable|integer',
        ]);
        $svc = Service::bookable()->findOrFail($v['service_id']);
        try {
            $lock = $this->booking->hold(
                $request->session()->getId(), $svc, Carbon::parse($v['date'].' '.$v['time']),
                $v['dentist_id'] ?? null, (bool) ($v['emergency'] ?? false), $v['exclude'] ?? null
            );
        } catch (SlotUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
        return response()->json([
            'dentist_id' => $lock->dentist_id,
            'dentist_name' => Dentist::with('user')->find($lock->dentist_id)->name,
            'seconds' => (int) max(0, now()->diffInSeconds($lock->expires_at, false)),
        ]);
    }

    // ------------------------------------------------------------- store
    private function rules(): array
    {
        return [
            'service_id' => 'required|exists:svc_services,service_id',
            'dentist_id' => 'nullable|exists:usr_dentists,dentist_id',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:500',
            'emergency' => 'nullable|boolean',
            'emergency_details' => 'nullable|required_if:emergency,1|string|max:400',
        ];
    }

    public function store(Request $request)
    {
        $v = $request->validate($this->rules() + [
            'health_reviewed' => 'accepted',
            'health_changed' => 'nullable|boolean',
            'allergies' => 'nullable|string|max:2000',
            'medical_conditions' => 'nullable|string|max:2000',
            'current_medications' => 'nullable|string|max:2000',
        ], ['health_reviewed.accepted' => 'Please confirm that you reviewed your health information.']);

        $patient = $request->user()->patient;
        abort_if(! $patient, 403);

        $profile = HealthProfile::firstOrCreate(['patient_id' => $patient->patient_id], ['blood_type' => 'unknown']);
        $update = ['last_reviewed_at' => now(), 'updated_by_user_id' => $request->user()->user_id];
        if ($request->boolean('health_changed')) {
            $update += [
                'allergies' => $v['allergies'] ?? null,
                'medical_conditions' => $v['medical_conditions'] ?? null,
                'current_medications' => $v['current_medications'] ?? null,
            ];
        }

        $status = Settings::bool('auto_confirm_bookings') ? 'confirmed' : 'pending';
        $a = $this->place($request, $v, $patient->patient_id, $status);
        if (! $a instanceof Appointment) return $a;          // redirect-back response on failure

        $profile->update($update);
        return redirect()->route('appointments.mine')->with('status', $status === 'confirmed'
            ? 'Your appointment is confirmed. A confirmation e-mail is on its way.'
            : 'Your request was sent. We will e-mail you once the clinic confirms it.');
    }

    public function storeForPatient(Request $request, PatientService $patients)
    {
        $v = $request->validate($this->rules() + [
            'patient_mode' => 'required|in:existing,new',
            'patient_id' => 'required_if:patient_mode,existing|nullable|exists:pat_patients,patient_id',
            'first_name' => 'required_if:patient_mode,new|nullable|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required_if:patient_mode,new|nullable|string|max:100',
            'birth_date' => 'required_if:patient_mode,new|nullable|date|before:today',
            'sex' => 'nullable|in:male,female,other',
            'contact_number' => ['required_if:patient_mode,new', 'nullable', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'email' => 'nullable|email|max:255',
        ]);

        // For a walk-in the patient record is created in the SAME transaction as the booking,
        // so if the slot was just taken nothing is left behind.
        $patient = $v['patient_mode'] === 'existing'
            ? (int) $v['patient_id']
            : fn () => $patients->create($v, null, $request->user())->patient_id;
        $a = $this->place($request, $v, $patient, 'confirmed');
        if (! $a instanceof Appointment) return $a;

        return redirect()->route('appointments.index')->with('status', 'Appointment created and confirmed.');
    }

    /** Shared: parse the slot, book it, send the right e-mail. Returns the Appointment or a redirect. */
    private function place(Request $request, array $v, int|callable $patient, string $status)
    {
        $emergency = $request->boolean('emergency');
        $notes = trim(($emergency ? '[EMERGENCY] '.($v['emergency_details'] ?? '').' ' : '').($v['notes'] ?? ''));

        try {
            $a = DB::transaction(fn () => $this->booking->book([
                'patient_id' => is_callable($patient) ? $patient() : $patient, 'service_id' => $v['service_id'],
                'dentist_id' => $v['dentist_id'] ?? null,
                'start' => Carbon::parse($v['date'].' '.$v['time']),
                'emergency' => $emergency, 'notes' => $notes ?: null, 'status' => $status,
                'booked_by' => $request->user()->user_id, 'confirmed_by' => $status === 'confirmed' ? $request->user()->user_id : null,
                'session' => $request->session()->getId(),
            ]));
        } catch (SlotUnavailable $e) {
            return back()->withInput()->withErrors(['time' => $e->getMessage()]);
        }

        if ($status === 'confirmed') {
            Notifier::forAppointment('booking_confirmed', $a);
            $this->booking->queueReminder($a);
        } else {
            Notifier::forAppointment('booking_received', $a);
        }
        return $a;
    }
}
