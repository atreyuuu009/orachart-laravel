<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ClinicHour;
use App\Models\ScheduleOverride;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        try {
            $month = Carbon::createFromFormat('Y-m-d', $request->query('month', now()->format('Y-m')).'-01')->startOfDay();
        } catch (\Throwable $e) {
            $month = now()->startOfMonth();
        }
        $selected = $request->query('date');
        try { $selected = $selected ? Carbon::parse($selected)->startOfDay() : null; } catch (\Throwable $e) { $selected = null; }
        if (! $selected || ! $selected->isSameMonth($month)) {
            $selected = now()->isSameMonth($month) ? now()->startOfDay() : $month->copy();
        }

        $gridStart = $month->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $byDay = Appointment::visibleTo($request->user())->with('patient', 'service', 'dentist.user')
            ->whereIn('status', ['pending', 'confirmed', 'completed'])
            ->whereBetween('start_at', [$gridStart, $gridEnd])->orderBy('start_at')->get()
            ->groupBy(fn ($a) => $a->start_at->toDateString());

        $closedDow = ClinicHour::where('is_closed', 1)->pluck('day_of_week')->all();
        $closedDates = ScheduleOverride::whereNull('dentist_id')->where('is_closed', 1)
            ->whereBetween('override_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->get()->map(fn ($o) => $o->override_date->toDateString())->all();

        return view('calendar', [
            'month' => $month, 'selected' => $selected, 'gridStart' => $gridStart, 'gridEnd' => $gridEnd,
            'byDay' => $byDay, 'closedDow' => $closedDow, 'closedDates' => $closedDates,
        ]);
    }
}
