<?php

namespace App\Http\Controllers;

use App\Models\ClinicHour;
use App\Models\Dentist;
use App\Models\EmergencyBlock;
use App\Models\ScheduleOverride;
use App\Models\Specialty;
use App\Services\ScheduleService;
use App\Support\Audit;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin > Clinic Settings (hours, date overrides, booking rules, dentists, emergency windows). */
class SettingsController extends Controller
{
    public function show()
    {
        $keys = ['slot_interval_minutes', 'buffer_before_minutes', 'buffer_after_minutes', 'min_booking_notice_hours',
            'slot_lock_minutes', 'max_patients_per_day', 'emergency_release_minutes', 'arrival_notice_minutes',
            'reminder_hours_before', 'session_timeout_minutes'];
        $rules = collect($keys)->mapWithKeys(fn ($k) => [$k => Settings::get($k)])->all();

        return view('admin.settings', [
            'hours' => ClinicHour::orderBy('day_of_week')->get()->keyBy('day_of_week'),
            'overrides' => ScheduleOverride::whereNull('dentist_id')->where('override_date', '>=', today()->toDateString())->orderBy('override_date')->get(),
            'rules' => $rules,
            'flags' => [
                'auto_confirm_bookings' => Settings::bool('auto_confirm_bookings'),
                'enforce_service_specialty' => Settings::bool('enforce_service_specialty'),
                'reminders_enabled' => Settings::bool('reminders_enabled'),
            ],
            'dentists' => Dentist::with('user', 'specialties')->get(),
            'specialties' => Specialty::orderBy('specialty_name')->get(),
            'blocks' => EmergencyBlock::orderBy('day_of_week')->orderBy('start_time')->get(),
            'dentistNames' => Dentist::with('user')->get()->mapWithKeys(fn ($d) => [$d->dentist_id => $d->name]),
        ]);
    }

    public function saveHours(Request $request)
    {
        foreach (range(0, 6) as $d) {
            $closed = $request->boolean("hours.$d.closed");
            $open = $request->input("hours.$d.open"); $close = $request->input("hours.$d.close");
            if (! $closed && (! preg_match('/^\d{2}:\d{2}/', (string) $open) || ! preg_match('/^\d{2}:\d{2}/', (string) $close) || $open >= $close)) {
                return back()->withInput()->withErrors(['hours' => 'Open days need an opening time earlier than the closing time.']);
            }
            ClinicHour::updateOrCreate(['day_of_week' => $d], [
                'is_closed' => $closed ? 1 : 0, 'open_time' => $closed ? null : $open, 'close_time' => $closed ? null : $close,
            ]);
        }
        Audit::log('update', 'sch_clinic_hours', null, 'Weekly clinic hours updated');
        return back()->with('status', 'Clinic hours saved. They apply to new bookings; existing appointments are not changed.')->with('tab', 'hours');
    }

    public function storeOverride(Request $request, ScheduleService $schedule)
    {
        $v = $request->validate([
            'override_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'is_closed' => 'nullable|boolean',
            'start_time' => 'nullable|required_unless:is_closed,1|date_format:H:i',
            'end_time' => 'nullable|required_unless:is_closed,1|date_format:H:i|after:start_time',
            'reason' => 'nullable|string|max:255',
        ]);
        $closed = $request->boolean('is_closed');

        ScheduleOverride::updateOrCreate(['dentist_id' => null, 'override_date' => $v['override_date']], [
            'is_closed' => $closed ? 1 : 0,
            'start_time' => $closed ? null : $v['start_time'], 'end_time' => $closed ? null : $v['end_time'],
            'reason' => $v['reason'] ?? null, 'created_by_user_id' => $request->user()->user_id,
        ]);
        $flagged = $schedule->flagOutsideHours(Carbon::parse($v['override_date']), $closed ? null : $v['start_time'], $closed ? null : $v['end_time']);
        Audit::log('create', 'sch_overrides', null, 'Date override for '.$v['override_date']);

        return back()->with('tab', 'overrides')->with('status', 'Date override saved.'.($flagged ? " {$flagged} appointment(s) were flagged for rescheduling and the patients were e-mailed." : ''));
    }

    public function destroyOverride(ScheduleOverride $override)
    {
        $override->delete();
        Audit::log('delete', 'sch_overrides', $override->override_id, 'Date override removed');
        return back()->with('tab', 'overrides')->with('status', 'Date override removed.');
    }

    public function saveRules(Request $request)
    {
        $v = $request->validate([
            'slot_interval_minutes' => 'required|integer|min:5|max:120',
            'buffer_before_minutes' => 'required|integer|min:0|max:120',
            'buffer_after_minutes' => 'required|integer|min:0|max:120',
            'min_booking_notice_hours' => 'required|integer|min:0|max:168',
            'slot_lock_minutes' => 'required|integer|min:1|max:30',
            'max_patients_per_day' => 'required|integer|min:1|max:200',
            'emergency_release_minutes' => 'required|integer|min:0|max:240',
            'arrival_notice_minutes' => 'required|integer|min:0|max:120',
            'reminder_hours_before' => 'required|integer|min:1|max:168',
            'session_timeout_minutes' => 'required|integer|min:5|max:120',
        ]);
        foreach ($v as $k => $val) Settings::set($k, $val);
        foreach (['auto_confirm_bookings', 'enforce_service_specialty', 'reminders_enabled'] as $flag) {
            Settings::set($flag, $request->boolean($flag));
        }
        Audit::log('update', 'sch_settings', null, 'Booking rules updated');
        return back()->with('tab', 'rules')->with('status', 'Booking rules saved.');
    }

    public function saveDentists(Request $request)
    {
        DB::transaction(function () use ($request) {
            foreach (Dentist::all() as $d) {
                $d->update(['is_accepting_bookings' => $request->boolean("accepting.{$d->dentist_id}") ? 1 : 0]);
                $ids = collect($request->input("specialties.{$d->dentist_id}", []))->map(fn ($i) => (int) $i)->all();
                $d->specialties()->sync(Specialty::whereIn('specialty_id', $ids)->pluck('specialty_id')->all());
            }
        });
        Audit::log('update', 'usr_dentists', null, 'Dentist specialties / availability updated');
        return back()->with('tab', 'dentists')->with('status', 'Dentist settings saved.');
    }

    public function storeEmergency(Request $request)
    {
        $v = $request->validate([
            'dentist_id' => 'nullable|exists:usr_dentists,dentist_id',
            'day_of_week' => 'required|integer|between:0,6',
            'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i|after:start_time',
        ]);
        EmergencyBlock::create($v + ['is_active' => 1]);
        Audit::log('create', 'sch_emergency_blocks', null, 'Emergency window added');
        return back()->with('tab', 'emergency')->with('status', 'Emergency window added.');
    }

    public function destroyEmergency(EmergencyBlock $block)
    {
        $block->delete();
        Audit::log('delete', 'sch_emergency_blocks', $block->emergency_block_id, 'Emergency window removed');
        return back()->with('tab', 'emergency')->with('status', 'Emergency window removed.');
    }
}
