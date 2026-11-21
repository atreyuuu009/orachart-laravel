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

/** Admin-managed weekly working hours and date-specific days off for dentists. */
class ScheduleController extends Controller
{
    public function show(Request $request, ?Dentist $dentist = null)
    {
        $this->authorizeAdmin($request);
        $all = Dentist::with('user')->get();
        $dentist ??= $all->first();
        abort_if(! $dentist, 404, 'No dentists found.');
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
        $this->authorizeAdmin($request);

        if (! $request->boolean('custom')) {
            DentistSchedule::where('dentist_id', $dentist->dentist_id)->delete();
            Audit::log('update', 'sch_dentist_schedules', $dentist->dentist_id, 'Dentist now follows clinic hours');
            return back()->with('status', 'This dentist now follows the clinic opening hours.');
        }

        $rows = [];
        foreach (range(0, 6) as $d) {
            $r = $request->input("days.$d", []);
            $start = $r['start'] ?? ''; $end = $r['end'] ?? '';
            if ($start === '' && $end === '') continue;
            if (! preg_match('/^\d{2}:\d{2}$/', $start) || ! preg_match('/^\d{2}:\d{2}$/', $end) || $start >= $end) {
                return back()->withInput()->withErrors(['hours' => 'Every working day needs a start time earlier than its end time.']);
            }
            $rows[] = ['dentist_id' => $dentist->dentist_id, 'day_of_week' => $d, 'start_time' => $start, 'end_time' => $end, 'is_active' => 1];
        }
        if (! $rows) return back()->withInput()->withErrors(['hours' => 'Enter working hours for at least one day, or switch back to clinic hours.']);
        DB::transaction(function () use ($dentist, $rows) {
            DentistSchedule::where('dentist_id', $dentist->dentist_id)->delete();
            DentistSchedule::insert($rows);
        });
        Audit::log('update', 'sch_dentist_schedules', $dentist->dentist_id, 'Weekly schedule updated');
        return back()->with('status', 'Weekly schedule saved. Existing appointments were not changed.');
    }

    public function storeTimeOff(Request $request, Dentist $dentist, ScheduleService $schedule)
    {
        $this->authorizeAdmin($request);
        $v = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'reason' => 'nullable|string|max:255',
        ]);
        $startAt = Carbon::createFromFormat('Y-m-d H:i', $v['date'].' '.$v['start_time']);
        $endAt = Carbon::createFromFormat('Y-m-d H:i', $v['date'].' '.$v['end_time']);
        $t = TimeOff::create([
            'dentist_id' => $dentist->dentist_id, 'start_at' => $startAt, 'end_at' => $endAt,
            'reason' => $v['reason'] ?? null, 'created_by_user_id' => $request->user()->user_id,
        ]);
        $flagged = $schedule->flagConflicts($dentist->dentist_id, $startAt, $endAt);
        Audit::log('create', 'sch_time_offs', $t->time_off_id, 'Day off added');

        return back()->with('status', 'Day off saved.'.($flagged ? " {$flagged} appointment(s) were flagged for rescheduling and the patients were e-mailed." : ''));
    }

    public function destroyTimeOff(Request $request, TimeOff $timeOff)
    {
        $this->authorizeAdmin($request);
        $timeOff->delete();
        Audit::log('delete', 'sch_time_offs', $timeOff->time_off_id, 'Day off removed');
        return back()->with('status', 'Day off removed.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->hasRole('admin'), 403);
    }
}
