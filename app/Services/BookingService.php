<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Service;
use App\Models\SlotLock;
use App\Models\TreatmentPlan;
use App\Support\Audit;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(private AvailabilityService $availability) {}

    /** Re-run availability for one exact start time; returns the open slot or throws. */
    public function resolveSlot(Service $svc, Carbon $start, ?int $dentistId, bool $emergency, ?int $exclude = null, ?string $session = null): array
    {
        $r = $this->availability->slots($svc, $start->copy()->startOfDay(), $dentistId, $emergency, $exclude, $session);
        foreach ($r['slots'] as $s) {
            if ($s['time'] === $start->format('H:i')) {
                if ($s['state'] === 'open') return $s;
                break;
            }
        }
        throw new SlotUnavailable('That time is no longer available. Please choose another slot.');
    }

    /** Temporary hold (apt_slot_locks) while the patient fills in the form. One hold per browser session. */
    public function hold(string $session, Service $svc, Carbon $start, ?int $dentistId, bool $emergency, ?int $exclude = null): SlotLock
    {
        SlotLock::where('expires_at', '<', now())->delete();
        SlotLock::where('session_id', $session)->delete();

        $slot = $this->resolveSlot($svc, $start, $dentistId, $emergency, $exclude, $session);
        $dur = (int) $svc->duration_minutes ?: 30;
        try {
            return SlotLock::create([
                'dentist_id' => $slot['dentist_id'],
                'start_at' => $start,
                'end_at' => $start->copy()->addMinutes($dur + Settings::int('buffer_after_minutes')),
                'session_id' => $session,
                'expires_at' => now()->addMinutes(Settings::int('slot_lock_minutes') ?: 5),
            ]);
        } catch (QueryException $e) {
            throw new SlotUnavailable('Someone else just took that slot. Please choose another.');
        }
    }

    /**
     * Create an appointment. Concurrency: bookings for the same dentists are serialised with
     * SELECT ... FOR UPDATE on usr_dentists, then availability is re-checked inside the transaction.
     *
     * @param array{patient_id:int,service_id:int,dentist_id?:?int,start:Carbon,emergency?:bool,notes?:?string,status?:string,
     *              booked_by?:?int,confirmed_by?:?int,session?:?string,exclude?:?int,rescheduled_from?:?int,
     *              treatment_plan_id?:?int,visit_number?:?int} $d
     */
    public function book(array $d): Appointment
    {
        $svc = Service::findOrFail($d['service_id']);
        $dentistId = $d['dentist_id'] ?? null;
        $emergency = (bool) ($d['emergency'] ?? false);

        return DB::transaction(function () use ($d, $svc, $dentistId, $emergency) {
            $pool = $this->availability->eligibleDentists($svc, $dentistId);
            if ($pool->isNotEmpty()) {
                Dentist::whereIn('dentist_id', $pool->pluck('dentist_id'))->orderBy('dentist_id')->lockForUpdate()->get();
            }
            $slot = $this->resolveSlot($svc, $d['start'], $dentistId, $emergency, $d['exclude'] ?? null, $d['session'] ?? null);
            $dur = (int) $svc->duration_minutes ?: 30;

            $a = Appointment::create([
                'patient_id' => $d['patient_id'],
                'service_id' => $svc->service_id,
                'requested_dentist_id' => $dentistId,
                'assigned_dentist_id' => $slot['dentist_id'],
                'treatment_plan_id' => $d['treatment_plan_id'] ?? null,
                'visit_number' => $d['visit_number'] ?? null,
                'start_at' => $d['start'],
                'end_at' => $d['start']->copy()->addMinutes($dur + Settings::int('buffer_after_minutes')),
                'status' => $d['status'] ?? 'pending',
                'is_emergency' => $emergency,
                'rescheduled_from_appointment_id' => $d['rescheduled_from'] ?? null,
                'patient_notes' => isset($d['notes']) ? mb_substr($d['notes'], 0, 500) : null,
                'booked_by_user_id' => $d['booked_by'] ?? null,
                'confirmed_by_user_id' => ($d['status'] ?? 'pending') === 'confirmed' ? ($d['confirmed_by'] ?? null) : null,
            ]);

            if (! $dentistId) Settings::set('round_robin_last_dentist_id', $slot['dentist_id']);
            if (! empty($d['session'])) SlotLock::where('session_id', $d['session'])->delete();
            Audit::log('create', 'apt_appointments', $a->appointment_id, "Appointment booked for {$a->start_at}");
            return $a;
        });
    }

    /** Move to a new time: the old row becomes 'rescheduled' (frees the slot), a new linked row is created. */
    public function reschedule(Appointment $old, Carbon $start, ?int $dentistId, ?int $byUserId, ?string $session = null): Appointment
    {
        return DB::transaction(function () use ($old, $start, $dentistId, $byUserId, $session) {
            $new = $this->book([
                'patient_id' => $old->patient_id, 'service_id' => $old->service_id, 'dentist_id' => $dentistId,
                'start' => $start, 'emergency' => $old->is_emergency, 'notes' => $old->patient_notes,
                'status' => $old->status === 'pending' ? 'pending' : 'confirmed',
                'booked_by' => $byUserId, 'confirmed_by' => $byUserId, 'session' => $session, 'exclude' => $old->appointment_id,
                'rescheduled_from' => $old->appointment_id,
                'treatment_plan_id' => $old->treatment_plan_id, 'visit_number' => $old->visit_number,
            ]);
            $old->update(['status' => 'rescheduled', 'needs_reschedule' => 0]);
            Notifier::cancelQueued($old->appointment_id);
            Audit::log('update', 'apt_appointments', $old->appointment_id, "Rescheduled to appointment #{$new->appointment_id}");
            return $new;
        });
    }

    public function confirm(Appointment $a, int $userId): void
    {
        $a->update(['status' => 'confirmed', 'confirmed_by_user_id' => $userId]);
        Audit::log('update', 'apt_appointments', $a->appointment_id, 'Appointment confirmed');
        Notifier::forAppointment('booking_confirmed', $a);
        $this->queueReminder($a);
    }

    /** Reject a pending request (stored as cancelled + rejection_reason) or cancel a booking. */
    public function cancel(Appointment $a, string $reason, bool $isRejection = false): void
    {
        $a->update(['status' => 'cancelled', $isRejection ? 'rejection_reason' : 'cancel_reason' => mb_substr($reason, 0, 500), 'needs_reschedule' => 0]);
        Notifier::cancelQueued($a->appointment_id);
        Audit::log('update', 'apt_appointments', $a->appointment_id, $isRejection ? 'Appointment rejected' : 'Appointment cancelled');
        Notifier::forAppointment($isRejection ? 'booking_rejected' : 'appointment_cancelled', $a, ['reason' => $reason]);
    }

    public function complete(Appointment $a): void
    {
        $a->update(['status' => 'completed', 'needs_reschedule' => 0]);
        Audit::log('update', 'apt_appointments', $a->appointment_id, 'Appointment completed');
        if (Settings::bool('reminders_enabled')) {
            Notifier::forAppointment('post_treatment_thank_you', $a, [], $a->end_at->copy()->addHours(2));
            Notifier::forAppointment('recall_reminder', $a, [], $a->end_at->copy()->addMonths(max(1, Settings::int('recall_months'))));
        }
    }

    public function noShow(Appointment $a): void
    {
        $a->update(['status' => 'no_show']);
        Notifier::cancelQueued($a->appointment_id);
        Audit::log('update', 'apt_appointments', $a->appointment_id, 'Marked no-show');
    }

    /** Linked follow-up visit for multi-visit treatments (creates the treatment plan on first use). */
    public function followUp(Appointment $prev, Carbon $start, int $byUserId): Appointment
    {
        return DB::transaction(function () use ($prev, $start, $byUserId) {
            $plan = $prev->treatmentPlan;
            if (! $plan) {
                $plan = TreatmentPlan::create([
                    'patient_id' => $prev->patient_id, 'service_id' => $prev->service_id,
                    'assigned_dentist_id' => $prev->assigned_dentist_id, 'planned_visits' => 2,
                ]);
                $prev->update(['treatment_plan_id' => $plan->treatment_plan_id, 'visit_number' => 1]);
            }
            $next = Appointment::where('treatment_plan_id', $plan->treatment_plan_id)->max('visit_number') + 1;
            $plan->update(['planned_visits' => max($plan->planned_visits, $next)]);

            $a = $this->book([
                'patient_id' => $prev->patient_id, 'service_id' => $prev->service_id, 'dentist_id' => $prev->assigned_dentist_id,
                'start' => $start, 'status' => 'confirmed', 'booked_by' => $byUserId, 'confirmed_by' => $byUserId,
                'treatment_plan_id' => $plan->treatment_plan_id, 'visit_number' => $next,
            ]);
            return $a;
        });
    }

    public function queueReminder(Appointment $a): void
    {
        if (! Settings::bool('reminders_enabled')) return;
        $when = $a->start_at->copy()->subHours(max(1, Settings::int('reminder_hours_before')));
        if ($when->lte(now())) return;
        Notifier::forAppointment(($a->visit_number ?? 1) > 1 ? 'treatment_reminder' : 'appointment_reminder', $a, [], $when);
    }
}
