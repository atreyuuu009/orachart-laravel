<?php

namespace App\Http\Controllers;

use App\Models\ClinicHour;
use App\Models\Dentist;
use App\Models\DentistSchedule;
use App\Models\TimeOff;
use App\Services\ScheduleService;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Weekly working hours + time off for a dentist (dentists edit their own, admin edits any). */
class ScheduleController extends Controller
{
    public function show(Request $request, ?Dentist $dentist = null)
    {
        $u = $request->user();
        if ($u->hasRole('dentist')) {
            $own = $u->dentist;
            abort_if(! $own, 403);
            abort_if($dentist && $dentist->dentist_id !== $own->dentist_id, 403);
            $dentist = $own; $all = collect();
        } else {
            $all = Dentist::with('user')->get();
            $dentist ??= $all->first();
            abort_if(! $dentist, 404, 'No dentists found.');
        }
        $dentist->load('user');

        $rows = DentistSchedule::where('dentist_id', $dentist->dentist_id)->where('is_active', 1)->get()->keyBy('day_of_week');
        return view('schedule.show', [
            'dentist' => $dentist, 'dentists' => $all, 'rows' => $rows, 'custom' => $rows->isNotEmpty(),
            'clinicHours' => ClinicHour::orderBy('day_of_week')->get()->keyBy('day_of_week'),
            'timeOffs' => TimeOff::where('dentist_id', $dentist->dentist_id)->where('end_at', '>=', now())->orderBy('start_at')->get(),
        ]);
    }

    public function saveHours(Request $request, Dentist $dentist)
    {
        $this->authorizeDentist($request, $dentist);

        if (! $request->boolean('custom')) {
            DentistSchedule::where('dentist_id', $dentist->dentist_id)->delete();
            Audit::log('update', 'sch_dentist_schedules', $dentist->dentist_id, 'Dentist now follows clinic hours');
            return back()->with('status', 'This dentist now follows the clinic opening hours.');
        }

        $rows = [];
        foreach (range(0, 6) as $d) {
            $r = $request->input("days.$d", []);
            if (empty($r['enabled'])) continue;
            $start = $r['start'] ?? ''; $end = $r['end'] ?? '';
            if (! preg_match('/^\d{2}:\d{2}$/', $start) || ! preg_match('/^\d{2}:\d{2}$/', $end) || $start >= $end) {
                return back()->withInput()->withErrors(['hours' => 'Every working day needs a start time earlier than its end time.']);
            }
            $rows[] = ['dentist_id' => $dentist->dentist_id, 'day_of_week' => $d, 'start_time' => $start, 'end_time' => $end, 'is_active' => 1];
        }
        if (! $rows) return back()->withErrors(['hours' => 'Select at least one working day, or switch back to the clinic hours.']);

        DB::transaction(function () use ($dentist, $rows) {
            DentistSchedule::where('dentist_id', $dentist->dentist_id)->delete();
            DentistSchedule::insert($rows);
        });
        Audit::log('update', 'sch_dentist_schedules', $dentist->dentist_id, 'Weekly schedule updated');
        return back()->with('status', 'Weekly schedule saved. Existing appointments were not changed.');
    }

    public function storeTimeOff(Request $request, Dentist $dentist, ScheduleService $schedule)
    {
        $this->authorizeDentist($request, $dentist);
        $v = $request->validate([
            'start_at' => 'required|date', 'end_at' => 'required|date|after:start_at', 'reason' => 'nullable|string|max:255',
        ]);
        $t = TimeOff::create([
            'dentist_id' => $dentist->dentist_id, 'start_at' => Carbon::parse($v['start_at']), 'end_at' => Carbon::parse($v['end_at']),
            'reason' => $v['reason'] ?? null, 'created_by_user_id' => $request->user()->user_id,
        ]);
        $flagged = $schedule->flagConflicts($dentist->dentist_id, Carbon::parse($v['start_at']), Carbon::parse($v['end_at']));
        Audit::log('create', 'sch_time_offs', $t->time_off_id, 'Time off added');

        return back()->with('status', 'Time off saved.'.($flagged ? " {$flagged} appointment(s) were flagged for rescheduling and the patients were e-mailed." : ''));
    }

    public function destroyTimeOff(Request $request, TimeOff $timeOff)
    {
        $this->authorizeDentist($request, Dentist::findOrFail($timeOff->dentist_id));
        $timeOff->delete();
        Audit::log('delete', 'sch_time_offs', $timeOff->time_off_id, 'Time off removed');
        return back()->with('status', 'Time off removed.');
    }

    private function authorizeDentist(Request $request, Dentist $dentist): void
    {
        $u = $request->user();
        if ($u->hasRole('admin')) return;
        abort_unless($u->hasRole('dentist') && (int) optional($u->dentist)->dentist_id === (int) $dentist->dentist_id, 403);
    }
}
