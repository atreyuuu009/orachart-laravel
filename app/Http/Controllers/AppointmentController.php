<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Services\BookingService;
use App\Services\Notifier;
use App\Services\SlotUnavailable;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __construct(private BookingService $booking) {}

    // ------------------------------------------------------------ lists
    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:pending,confirmed,rescheduled,cancelled,completed,no_show,needs_reschedule',
            'from' => 'nullable|date', 'to' => 'nullable|date', 'q' => 'nullable|string|max:100',
        ]);

        $q = Appointment::visibleTo($request->user())->with('patient', 'service', 'dentist.user');
        if ($s = $request->query('status')) {
            $s === 'needs_reschedule' ? $q->open()->where('needs_reschedule', 1) : $q->where('status', $s);
        }
        if ($f = $request->query('from')) $q->where('start_at', '>=', Carbon::parse($f)->startOfDay());
        if ($t = $request->query('to')) $q->where('start_at', '<=', Carbon::parse($t)->endOfDay());
        if ($term = trim((string) $request->query('q'))) {
            $q->whereHas('patient', fn ($p) => $p->where(fn ($w) => $w
                ->where('first_name', 'like', "%$term%")->orWhere('last_name', 'like', "%$term%")
                ->orWhere('patient_code', 'like', "%$term%")->orWhere('contact_number', 'like', "%$term%")));
        }

        return view('appointments.index', [
            'appointments' => $q->orderByDesc('start_at')->paginate(15)->withQueryString(),
            'dentists' => Dentist::with('user')->get(),
        ]);
    }

    public function mine(Request $request)
    {
        $patient = $request->user()->patient;
        abort_if(! $patient, 403);
        $all = $patient->appointments()->with('service', 'dentist.user')->orderByDesc('start_at')->get();

        return view('appointments.mine', [
            'upcoming' => $all->filter(fn ($a) => $a->isOpen() && $a->start_at->isFuture())->sortBy('start_at')->values(),
            'history' => $all->reject(fn ($a) => $a->isOpen() && $a->start_at->isFuture())->values(),
        ]);
    }

    // ---------------------------------------------------------- actions
    public function confirm(Request $request, Appointment $appointment)
    {
        $this->authorizeManage($request, $appointment);
        if ($appointment->status !== 'pending') return back()->withErrors(['action' => 'Only pending requests can be confirmed.']);
        $this->booking->confirm($appointment, $request->user()->user_id);
        return back()->with('status', 'Appointment confirmed. The patient was notified by e-mail.');
    }

    public function reject(Request $request, Appointment $appointment)
    {
        $this->authorizeManage($request, $appointment);
        $data = $request->validate(['reason' => 'required|string|max:500']);
        if ($appointment->status !== 'pending') return back()->withErrors(['action' => 'Only pending requests can be rejected.']);
        $this->booking->cancel($appointment, $data['reason'], true);
        return back()->with('status', 'Request rejected and the patient was notified.');
    }

    public function cancel(Request $request, Appointment $appointment)
    {
        $this->authorizeManage($request, $appointment, true);
        $data = $request->validate(['reason' => 'nullable|string|max:500']);
        if (! $appointment->isOpen()) return back()->withErrors(['action' => 'This appointment can no longer be cancelled.']);

        $isPatient = $request->user()->hasRole('patient');
        if ($isPatient && ! $appointment->start_at->isFuture()) {
            return back()->withErrors(['action' => 'Past appointments cannot be cancelled.']);
        }
        $this->booking->cancel($appointment, $data['reason'] ?? ($isPatient ? 'Cancelled by patient' : 'Cancelled by clinic'));
        return back()->with('status', 'Appointment cancelled.');
    }

    public function complete(Request $request, Appointment $appointment)
    {
        $this->authorizeManage($request, $appointment);
        if ($appointment->status !== 'confirmed') return back()->withErrors(['action' => 'Only confirmed appointments can be marked completed.']);
        if ($appointment->start_at->copy()->startOfDay()->gt(today())) {
            return back()->withErrors(['action' => 'This appointment is in the future.']);
        }
        $this->booking->complete($appointment);
        return back()->with('status', 'Appointment marked as completed.');
    }

    public function noShow(Request $request, Appointment $appointment)
    {
        $this->authorizeManage($request, $appointment);
        if ($appointment->status !== 'confirmed' || $appointment->start_at->isFuture()) {
            return back()->withErrors(['action' => 'Only past, confirmed appointments can be marked as no-show.']);
        }
        $this->booking->noShow($appointment);
        return back()->with('status', 'Marked as no-show.');
    }

    public function followUp(Request $request, Appointment $appointment)
    {
        $this->authorizeManage($request, $appointment);
        $data = $request->validate(['date' => 'required|date_format:Y-m-d|after_or_equal:today', 'time' => 'required|date_format:H:i']);
        $appointment->load('service');
        if ($appointment->status !== 'completed' || ! $appointment->service->is_multi_visit) {
            return back()->withErrors(['action' => 'Follow-up visits are for completed multi-visit treatments.']);
        }
        try {
            $next = $this->booking->followUp($appointment, Carbon::parse($data['date'].' '.$data['time']), $request->user()->user_id);
        } catch (SlotUnavailable $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }
        Notifier::forAppointment('booking_confirmed', $next);
        $this->booking->queueReminder($next);
        return back()->with('status', "Follow-up visit #{$next->visit_number} booked.");
    }

    // ------------------------------------------------------- reschedule
    public function rescheduleForm(Request $request, Appointment $appointment)
    {
        $this->authorizeManage($request, $appointment, true);
        abort_unless($appointment->isOpen(), 403, 'Only active appointments can be rescheduled.');
        return view('booking.reschedule', [
            'appointment' => $appointment->load('patient', 'service', 'dentist.user'),
            'dentists' => Dentist::with('user')->where('is_accepting_bookings', 1)->get(),
        ]);
    }

    public function reschedule(Request $request, Appointment $appointment)
    {
        $this->authorizeManage($request, $appointment, true);
        abort_unless($appointment->isOpen(), 403);
        $v = $request->validate([
            'dentist_id' => 'nullable|exists:usr_dentists,dentist_id',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'time' => 'required|date_format:H:i',
        ]);

        try {
            $new = $this->booking->reschedule(
                $appointment, Carbon::parse($v['date'].' '.$v['time']), $v['dentist_id'] ?? null,
                $request->user()->user_id, $request->session()->getId()
            );
        } catch (SlotUnavailable $e) {
            return back()->withInput()->withErrors(['time' => $e->getMessage()]);
        }

        if ($new->status === 'confirmed') {
            Notifier::forAppointment('booking_confirmed', $new);
            $this->booking->queueReminder($new);
        } else {
            Notifier::forAppointment('booking_received', $new);
        }
        return redirect()->route($request->user()->hasRole('patient') ? 'appointments.mine' : 'appointments.index')
            ->with('status', 'Appointment rescheduled.');
    }

    // ------------------------------------------------------------ guard
    private function authorizeManage(Request $request, Appointment $a, bool $patientAllowed = false): void
    {
        $u = $request->user();
        if ($u->hasRole('admin', 'clinic_staff')) return;
        if ($u->hasRole('dentist') && (int) optional($u->dentist)->dentist_id === (int) $a->assigned_dentist_id) return;
        if ($patientAllowed && $u->hasRole('patient') && (int) optional($u->patient)->patient_id === (int) $a->patient_id) return;
        abort(403);
    }
}
