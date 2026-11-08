<?php

namespace App\Services;

use App\Models\Appointment;
use App\Support\Audit;
use Carbon\Carbon;

class ScheduleService
{
    /**
     * After a time-off / closure is saved, flag the open appointments it collides with
     * (needs_reschedule = 1) and e-mail those patients using the schedule_changed template.
     */
    public function flagConflicts(?int $dentistId, Carbon $from, Carbon $to): int
    {
        $hit = Appointment::open()->where('needs_reschedule', 0)
            ->where('start_at', '<', $to)->where('end_at', '>', $from)
            ->when($dentistId, fn ($q) => $q->where('assigned_dentist_id', $dentistId))
            ->with('patient.user', 'service', 'dentist.user')->get();

        foreach ($hit as $a) {
            $a->update(['needs_reschedule' => 1]);
            Notifier::forAppointment('schedule_changed', $a);
        }
        if ($hit->count()) Audit::log('update', 'apt_appointments', null, $hit->count().' appointment(s) flagged for rescheduling');
        return $hit->count();
    }

    /** A date override shortened the clinic day: flag open appointments that no longer fit. */
    public function flagOutsideHours(Carbon $day, ?string $open, ?string $close): int
    {
        $from = $day->copy()->startOfDay(); $to = $day->copy()->endOfDay();
        if (! $open || ! $close) return $this->flagConflicts(null, $from, $to);   // closed all day

        $openAt = $day->copy()->setTimeFromTimeString($open);
        $closeAt = $day->copy()->setTimeFromTimeString($close)->addMinutes(\App\Support\Settings::int('buffer_after_minutes'));
        $hit = Appointment::open()->where('needs_reschedule', 0)->whereBetween('start_at', [$from, $to])
            ->where(fn ($q) => $q->where('start_at', '<', $openAt)->orWhere('end_at', '>', $closeAt))
            ->with('patient.user', 'service', 'dentist.user')->get();
        foreach ($hit as $a) {
            $a->update(['needs_reschedule' => 1]);
            Notifier::forAppointment('schedule_changed', $a);
        }
        return $hit->count();
    }
}
