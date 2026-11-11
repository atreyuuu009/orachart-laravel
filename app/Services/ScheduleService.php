<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\ScheduleOverride;
use App\Models\SlotLock;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ScheduleService
{
    /**
     * Get all open (pending or confirmed) appointments that conflict with a proposed closure/override.
     * Completed, cancelled, no-show, and rescheduled rows are never included.
     */
    public function getConflicts(?int $dentistId, Carbon $day, bool $isClosed, ?string $openTime = null, ?string $closeTime = null): Collection
    {
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();

        $query = Appointment::open()
            ->where('needs_reschedule', 0)
            ->whereBetween('start_at', [$from, $to])
            ->when($dentistId, fn ($q) => $q->where('assigned_dentist_id', $dentistId))
            ->with(['patient.user', 'service', 'dentist.user']);

        if (! $isClosed && $openTime && $closeTime) {
            // Partial closure: only flag appointments overlapping the closed hours / outside new open hours
            $openAt = $day->copy()->setTimeFromTimeString($openTime);
            $closeAt = $day->copy()->setTimeFromTimeString($closeTime)->addMinutes(Settings::int('buffer_after_minutes'));

            $query->where(fn ($q) => $q->where('start_at', '<', $openAt)->orWhere('end_at', '>', $closeAt));
        }

        return $query->orderBy('start_at')->get();
    }

    /**
     * Apply a clinic or dentist schedule override:
     * 1. Delete active slot locks on that date/window (prevents mid-booking submissions)
     * 2. Flag conflicting open appointments (needs_reschedule = 1)
     * 3. Cancel queued reminder e-mails for flagged appointments
     * 4. E-mail patients right away with apology, reason, and reschedule/cancel links (no penalty)
     * 5. Record an audit log entry
     *
     * @return Collection<int, Appointment> The affected appointments
     */
    public function applyOverride(ScheduleOverride $override, User $operator): Collection
    {
        $day = Carbon::parse($override->override_date);
        $isClosed = (bool) $override->is_closed;
        $dentistId = $override->dentist_id;
        $openTime = $override->start_time ? substr($override->start_time, 0, 5) : null;
        $closeTime = $override->end_time ? substr($override->end_time, 0, 5) : null;
        $reason = $override->reason ?: 'Clinic schedule adjustment';
        $closureType = $override->closure_type ?: 'planned';

        // 1. Delete active slot holds (apt_slot_locks)
        $this->deleteConflictingLocks($dentistId, $day, $isClosed, $openTime, $closeTime);

        // 2. Identify and flag conflicting open appointments
        $affected = $this->getConflicts($dentistId, $day, $isClosed, $openTime, $closeTime);

        if ($affected->isNotEmpty()) {
            $affectedIds = $affected->pluck('appointment_id')->all();

            // Flag appointments
            Appointment::whereIn('appointment_id', $affectedIds)->update(['needs_reschedule' => 1]);

            // 3. Cancel queued reminder e-mails
            EmailLog::whereIn('related_appointment_id', $affectedIds)
                ->where('status', 'queued')
                ->update([
                    'status' => 'cancelled',
                    'error_message' => 'Cancelled due to clinic schedule closure / override',
                ]);

            // 4. Send schedule_changed e-mail immediately to each patient
            foreach ($affected as $a) {
                Notifier::forAppointment('schedule_changed', $a, [
                    'reason' => $reason,
                    'action_url' => route('appointments.reschedule', $a),
                    'cancel_url' => route('appointments.mine'),
                ]);
            }
        }

        // 5. Audit log
        $scope = $dentistId ? "Dentist #{$dentistId}" : 'Clinic';
        $desc = "{$scope} closure on {$override->override_date->format('Y-m-d')} ({$closureType}): {$reason}. {$affected->count()} appointment(s) affected.";
        Audit::log('create', 'sch_overrides', $override->override_id, $desc);

        return $affected;
    }

    /**
     * Delete active slot holds (apt_slot_locks) so someone mid-booking cannot submit.
     */
    public function deleteConflictingLocks(?int $dentistId, Carbon $day, bool $isClosed, ?string $openTime = null, ?string $closeTime = null): int
    {
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();

        $query = SlotLock::whereBetween('start_at', [$from, $to])
            ->when($dentistId, fn ($q) => $q->where('dentist_id', $dentistId));

        if (! $isClosed && $openTime && $closeTime) {
            $openAt = $day->copy()->setTimeFromTimeString($openTime);
            $closeAt = $day->copy()->setTimeFromTimeString($closeTime);
            $query->where(fn ($q) => $q->where('start_at', '<', $openAt)->orWhere('end_at', '>', $closeAt));
        }

        return $query->delete();
    }

    /**
     * Backward-compatible helper for dentist time-offs
     */
    public function flagConflicts(?int $dentistId, Carbon $from, Carbon $to): int
    {
        $hit = Appointment::open()->where('needs_reschedule', 0)
            ->where('start_at', '<', $to)->where('end_at', '>', $from)
            ->when($dentistId, fn ($q) => $q->where('assigned_dentist_id', $dentistId))
            ->with(['patient.user', 'service', 'dentist.user'])->get();

        if ($hit->isNotEmpty()) {
            $ids = $hit->pluck('appointment_id')->all();
            Appointment::whereIn('appointment_id', $ids)->update(['needs_reschedule' => 1]);

            // Cancel queued reminder emails
            EmailLog::whereIn('related_appointment_id', $ids)
                ->where('status', 'queued')
                ->update([
                    'status' => 'cancelled',
                    'error_message' => 'Cancelled due to dentist time-off',
                ]);

            foreach ($hit as $a) {
                Notifier::forAppointment('schedule_changed', $a, [
                    'reason' => 'Dentist schedule adjustment / time off',
                    'action_url' => route('appointments.reschedule', $a),
                    'cancel_url' => route('appointments.mine'),
                ]);
            }
            Audit::log('update', 'apt_appointments', null, $hit->count().' appointment(s) flagged for rescheduling');
        }

        return $hit->count();
    }

    /**
     * Backward-compatible helper for date overrides
     */
    public function flagOutsideHours(Carbon $day, ?string $open, ?string $close): int
    {
        $isClosed = (! $open || ! $close);
        $hit = $this->getConflicts(null, $day, $isClosed, $open, $close);

        if ($hit->isNotEmpty()) {
            $ids = $hit->pluck('appointment_id')->all();
            Appointment::whereIn('appointment_id', $ids)->update(['needs_reschedule' => 1]);

            EmailLog::whereIn('related_appointment_id', $ids)
                ->where('status', 'queued')
                ->update([
                    'status' => 'cancelled',
                    'error_message' => 'Cancelled due to clinic hours change',
                ]);

            foreach ($hit as $a) {
                Notifier::forAppointment('schedule_changed', $a, [
                    'reason' => 'Clinic operating hours update',
                    'action_url' => route('appointments.reschedule', $a),
                    'cancel_url' => route('appointments.mine'),
                ]);
            }
        }

        return $hit->count();
    }

    /**
     * Suggest the next available slots (default: 3) for an appointment needing rescheduling.
     * Looks ahead up to 14 days, prioritizing the assigned dentist, then falling back to any eligible dentist.
     */
    public function suggestSlots(Appointment $appointment, int $limit = 3): array
    {
        $appointment->loadMissing('service', 'dentist.user');
        $svc = $appointment->service;
        $dentistId = $appointment->assigned_dentist_id;
        $availability = app(AvailabilityService::class);

        $slots = [];
        $startDate = today();

        // 1. Try with the assigned dentist first
        for ($i = 0; $i < 14 && count($slots) < $limit; $i++) {
            $day = $startDate->copy()->addDays($i);
            $res = $availability->slots(
                $svc,
                $day,
                $dentistId,
                emergency: false,
                excludeAppointmentId: $appointment->appointment_id,
                sessionId: null,
                isClosureReschedule: true
            );

            if (! empty($res['closed'])) continue;

            foreach ($res['slots'] as $s) {
                if ($s['state'] === 'open') {
                    $dentist = Dentist::with('user')->find($s['dentist_id'] ?? $dentistId);
                    $slots[] = [
                        'date' => $day->toDateString(),
                        'date_label' => $day->format('D, M j, Y'),
                        'time' => $s['time'],
                        'time_label' => $s['label'],
                        'dentist_id' => $dentist?->dentist_id ?? $dentistId,
                        'dentist_name' => $dentist?->name ?? 'Assigned Dentist',
                    ];
                    if (count($slots) >= $limit) break;
                }
            }
        }

        // 2. If fewer than limit, check other eligible dentists
        if (count($slots) < $limit) {
            for ($i = 0; $i < 14 && count($slots) < $limit; $i++) {
                $day = $startDate->copy()->addDays($i);
                $res = $availability->slots(
                    $svc,
                    $day,
                    null,
                    emergency: false,
                    excludeAppointmentId: $appointment->appointment_id,
                    sessionId: null,
                    isClosureReschedule: true
                );

                if (! empty($res['closed'])) continue;

                foreach ($res['slots'] as $s) {
                    if ($s['state'] === 'open') {
                        $key = $day->toDateString().'@'.$s['time'];
                        $already = collect($slots)->contains(fn ($x) => ($x['date'].'@'.$x['time']) === $key);
                        if ($already) continue;

                        $dentist = Dentist::with('user')->find($s['dentist_id']);
                        $slots[] = [
                            'date' => $day->toDateString(),
                            'date_label' => $day->format('D, M j, Y'),
                            'time' => $s['time'],
                            'time_label' => $s['label'],
                            'dentist_id' => $dentist?->dentist_id,
                            'dentist_name' => $dentist?->name ?? 'Available Dentist',
                        ];
                        if (count($slots) >= $limit) break;
                    }
                }
            }
        }

        return array_slice($slots, 0, $limit);
    }

    /**
     * Send second reminder e-mails to patients who have not rescheduled after ~24 hours.
     * Keeps the appointment in the staff queue for a phone call (never auto-cancelled).
     */
    public function sendSecondReminders(): int
    {
        $cutoff = now()->subHours(24);

        $appointments = Appointment::open()
            ->where('needs_reschedule', 1)
            ->with(['patient.user', 'service', 'dentist.user', 'emailLogs'])
            ->get();

        $sentCount = 0;
        foreach ($appointments as $a) {
            // Find initial notification
            $initialLog = $a->emailLogs->first(function ($l) {
                $sub = strtolower($l->subject);
                return str_contains($sub, 'reschedul') || str_contains($sub, 'schedule update');
            });

            if (! $initialLog) continue;

            $initialSentAt = $initialLog->sent_at ?: $initialLog->created_at;
            if (! $initialSentAt || $initialSentAt->gt($cutoff)) {
                // Sent less than 24 hours ago
                continue;
            }

            // Check if second reminder was already sent
            $alreadyReminded = $a->emailLogs->contains(function ($l) {
                return str_contains(strtolower($l->subject), 'second reminder');
            });

            if ($alreadyReminded) continue;

            // Reason from latest override on that date if available
            $ov = ScheduleOverride::where('override_date', $a->start_at->toDateString())->latest('override_id')->first();
            $reason = $ov?->reason ?: 'Clinic schedule closure / adjustment';

            Notifier::forAppointment('schedule_changed_reminder', $a, [
                'reason' => $reason,
                'action_url' => route('appointments.reschedule', $a),
                'cancel_url' => route('appointments.mine'),
            ]);

            $sentCount++;
        }

        return $sentCount;
    }
}
