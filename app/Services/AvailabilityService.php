<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClinicHour;
use App\Models\Dentist;
use App\Models\DentistSchedule;
use App\Models\EmergencyBlock;
use App\Models\ScheduleOverride;
use App\Models\Service;
use App\Models\SlotLock;
use App\Models\TimeOff;
use App\Support\Settings;
use App\Support\SlotCalculator as Calc;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Works out which start times can be booked for a service on a date.
 *
 * Window priority (highest first):
 *   clinic override (sch_overrides, dentist_id NULL)  >  sch_clinic_hours
 *   dentist override (sch_overrides)  >  sch_dentist_schedules (only if the dentist has any)  >  clinic hours
 *   final window = clinic window ∩ dentist window, minus time-offs, appointments and other people's slot locks.
 *
 * Slot states: open | taken | notice | full | emergency   (past slots are not listed)
 */
class AvailabilityService
{
    public function clinicWindows(Carbon $day): array
    {
        $ov = ScheduleOverride::whereNull('dentist_id')->where('override_date', $day->toDateString())->first();
        if ($ov) {
            return ($ov->is_closed || ! $ov->start_time || ! $ov->end_time) ? [] : [[Calc::parse($ov->start_time), Calc::parse($ov->end_time)]];
        }
        $h = ClinicHour::where('day_of_week', $day->dayOfWeek)->first();
        if (! $h || $h->is_closed || ! $h->open_time || ! $h->close_time) return [];
        return [[Calc::parse($h->open_time), Calc::parse($h->close_time)]];
    }

    /** Dentists who may take this service (accepting bookings, active, specialty if enforced). */
    public function eligibleDentists(Service $svc, ?int $dentistId = null): Collection
    {
        $enforce = Settings::bool('enforce_service_specialty') && $svc->required_specialty_id;
        return Dentist::with('user', 'specialties')
            ->where('is_accepting_bookings', 1)
            ->whereHas('user', fn ($q) => $q->where('is_active', 1))
            ->when($dentistId, fn ($q) => $q->where('dentist_id', $dentistId))
            ->orderBy('dentist_id')->get()
            ->filter(fn ($d) => ! $enforce || $d->specialties->contains('specialty_id', $svc->required_specialty_id))
            ->values();
    }

    /**
     * @return array{closed:bool,message:?string,slots:array<int,array{time:string,label:string,state:string,dentist_id:?int}>}
     */
    public function slots(Service $svc, Carbon $date, ?int $dentistId = null, bool $emergency = false, ?int $excludeAppointmentId = null, ?string $sessionId = null, bool $isClosureReschedule = false): array
    {
        $day = $date->copy()->startOfDay();
        $dayEnd = $day->copy()->endOfDay();
        $dow = $day->dayOfWeek;

        if (! $isClosureReschedule && $excludeAppointmentId) {
            $ex = Appointment::find($excludeAppointmentId);
            if ($ex && $ex->needs_reschedule) {
                $isClosureReschedule = true;
            }
        }
        $bypassNoticeAndCap = $emergency || $isClosureReschedule;

        $clinic = $this->clinicWindows($day);
        if (! $clinic) return ['closed' => true, 'message' => 'The clinic is closed on this date.', 'slots' => []];

        $pool = $this->eligibleDentists($svc, $dentistId);
        if ($pool->isEmpty()) return ['closed' => false, 'message' => 'No dentist is available for this procedure.', 'slots' => []];

        $step = Settings::int('slot_interval_minutes') ?: 30;
        $before = Settings::int('buffer_before_minutes');
        $after = Settings::int('buffer_after_minutes');
        $dur = (int) $svc->duration_minutes ?: 30;
        $ids = $pool->pluck('dentist_id')->all();
        $mins = fn (Carbon $t) => max(0, min(1440, intdiv($t->getTimestamp() - $day->getTimestamp(), 60)));

        $apps = Appointment::whereIn('assigned_dentist_id', $ids)->whereIn('status', Appointment::BLOCKING)
            ->whereBetween('start_at', [$day, $dayEnd])
            ->when($excludeAppointmentId, fn ($q) => $q->where('appointment_id', '!=', $excludeAppointmentId))
            ->get(['assigned_dentist_id', 'start_at', 'end_at'])->groupBy('assigned_dentist_id');
        $offs = TimeOff::whereIn('dentist_id', $ids)->where('start_at', '<', $dayEnd)->where('end_at', '>', $day)->get()->groupBy('dentist_id');
        $locks = SlotLock::whereIn('dentist_id', $ids)->where('expires_at', '>', now())->whereBetween('start_at', [$day, $dayEnd])
            ->when($sessionId, fn ($q) => $q->where('session_id', '!=', $sessionId))->get()->groupBy('dentist_id');
        $overrides = ScheduleOverride::whereIn('dentist_id', $ids)->where('override_date', $day->toDateString())->get()->keyBy('dentist_id');
        $schedules = DentistSchedule::whereIn('dentist_id', $ids)->where('is_active', 1)->get()->groupBy('dentist_id');
        $blocks = EmergencyBlock::where('is_active', 1)->where('day_of_week', $dow)->get();

        // clinic-wide load for the day (round robin + daily capacity)
        $loads = Appointment::whereIn('status', Appointment::BLOCKING)->whereBetween('start_at', [$day, $dayEnd])
            ->when($excludeAppointmentId, fn ($q) => $q->where('appointment_id', '!=', $excludeAppointmentId))
            ->selectRaw('assigned_dentist_id, COUNT(*) AS c')->groupBy('assigned_dentist_id')->pluck('c', 'assigned_dentist_id')
            ->map(fn ($c) => (int) $c);
        $dayTotal = $loads->sum();
        $maxPerDay = Settings::int('max_patients_per_day');

        // per-dentist free start times
        $free = [];
        foreach ($pool as $d) {
            $id = $d->dentist_id;
            if (isset($overrides[$id])) {
                $o = $overrides[$id];
                $w = ($o->is_closed || ! $o->start_time || ! $o->end_time) ? [] : [[Calc::parse($o->start_time), Calc::parse($o->end_time)]];
            } elseif (isset($schedules[$id])) {
                $w = $schedules[$id]->where('day_of_week', $dow)
                    ->map(fn ($r) => [Calc::parse($r->start_time), Calc::parse($r->end_time)])->values()->all();
            } else {
                $w = $clinic;
            }
            $w = Calc::intersect($w, $clinic);

            $busy = [];
            foreach ($apps[$id] ?? [] as $a) $busy[] = [$mins($a->start_at) - $before, $mins($a->end_at)];
            foreach ($offs[$id] ?? [] as $o) $busy[] = [$mins($o->start_at), $mins($o->end_at)];
            foreach ($locks[$id] ?? [] as $l) $busy[] = [$mins($l->start_at) - $before, $mins($l->end_at)];

            $free[$id] = array_flip(Calc::startTimes($w, $step, $dur, $before, $after, $busy));
        }

        $noticeAt = now()->addHours(Settings::int('min_booking_notice_hours'));
        $release = Settings::int('emergency_release_minutes');
        $out = [];

        foreach (Calc::startTimes($clinic, $step, $dur, 0, 0, []) as $t) {
            $startAt = $day->copy()->addMinutes($t);
            if ($startAt->lte(now())) continue;                         // already passed

            $row = ['time' => Calc::fmt($t), 'label' => Calc::label($t), 'state' => 'taken', 'dentist_id' => null];
            $avail = array_values(array_filter($ids, fn ($id) => isset($free[$id][$t])));

            if ($avail) {
                if (! $bypassNoticeAndCap && $startAt->lt($noticeAt)) {
                    $row['state'] = 'notice';
                } elseif (! $bypassNoticeAndCap && $maxPerDay > 0 && $dayTotal >= $maxPerDay) {
                    $row['state'] = 'full';
                } else {
                    $cands = $avail;
                    if (! $emergency && ! $isClosureReschedule) {
                        $cands = array_values(array_filter($avail, fn ($id) => ! $this->reserved($blocks, $id, $t, $startAt, $release)));
                    }
                    if (! $cands) {
                        $row['state'] = 'emergency';
                    } else {
                        $row['state'] = 'open';
                        $row['dentist_id'] = $this->pick($cands, $loads, $pool);
                    }
                }
            }
            $out[] = $row;
        }

        return ['closed' => false, 'message' => null, 'slots' => $out];
    }

    /** Reserved emergency window for this dentist? Released shortly before the slot if still unused. */
    private function reserved(Collection $blocks, int $dentistId, int $t, Carbon $startAt, int $releaseMinutes): bool
    {
        foreach ($blocks as $b) {
            if ($b->dentist_id !== null && (int) $b->dentist_id !== $dentistId) continue;
            if ($t >= Calc::parse($b->start_time) && $t < Calc::parse($b->end_time)) {
                return now()->lt($startAt->copy()->subMinutes($releaseMinutes));
            }
        }
        return false;
    }

    /** "No preference": fewest appointments that day wins; ties rotate after the last dentist assigned. */
    private function pick(array $candidates, Collection $loads, Collection $pool): int
    {
        if (count($candidates) === 1) return (int) $candidates[0];
        $all = $pool->pluck('dentist_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
        $n = count($all);
        $lastIdx = array_search(Settings::int('round_robin_last_dentist_id'), $all, true);
        $lastIdx = $lastIdx === false ? -1 : $lastIdx;
        $rank = fn ($id) => (array_search((int) $id, $all, true) - $lastIdx - 1 + $n) % $n;
        usort($candidates, function ($a, $b) use ($loads, $rank) {
            $la = $loads[$a] ?? 0; $lb = $loads[$b] ?? 0;
            return $la !== $lb ? $la <=> $lb : $rank($a) <=> $rank($b);
        });
        return (int) $candidates[0];
    }
}
