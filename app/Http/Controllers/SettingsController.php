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
use Illuminate\Support\Facades\Storage;

/** Admin > Clinic Settings (hours, date overrides, booking rules, dentists, emergency windows). */
class SettingsController extends Controller
{
    public function show()
    {
        $keys = ['slot_interval_minutes', 'buffer_before_minutes', 'buffer_after_minutes', 'min_booking_notice_hours',
            'slot_lock_minutes', 'max_patients_per_day', 'emergency_release_minutes', 'arrival_notice_minutes',
            'reminder_hours_before', 'session_timeout_minutes', 'installment_min_amount', 'installment_max_months', 'installment_reminder_days'];
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
        $v = $request->validate([
            'default_open' => 'required|date_format:H:i',
            'default_close' => 'required|date_format:H:i|after:default_open',
        ], [
            'default_close.after' => 'Closing time must be later than opening time.',
        ]);

        DB::transaction(function () use ($v) {
            foreach (range(0, 6) as $d) {
                ClinicHour::updateOrCreate(['day_of_week' => $d], [
                    'is_closed' => 0, 'open_time' => $v['default_open'], 'close_time' => $v['default_close'],
                ]);
            }
        });
        Audit::log('update', 'sch_clinic_hours', null, 'Default clinic working hours updated for every day');
        return back()->with('status', 'Default clinic hours saved for every day. Patient appointment availability uses these hours for all upcoming dates.')->with('tab', 'hours');
    }

    public function previewOverride(Request $request, ScheduleService $schedule)
    {
        $v = $request->validate([
            'override_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'is_closed' => 'nullable|boolean',
            'start_time' => 'nullable|required_unless:is_closed,1|date_format:H:i',
            'end_time' => 'nullable|required_unless:is_closed,1|date_format:H:i|after:start_time',
            'dentist_id' => 'nullable|exists:usr_dentists,dentist_id',
            'closure_type' => 'nullable|in:planned,emergency',
        ]);

        $closed = $request->boolean('is_closed');
        $dentistId = $v['dentist_id'] ?? null;
        $day = Carbon::parse($v['override_date']);
        $openTime = $closed ? null : ($v['start_time'] ?? null);
        $closeTime = $closed ? null : ($v['end_time'] ?? null);

        $affected = $schedule->getConflicts($dentistId, $day, $closed, $openTime, $closeTime);
        $confirmedCount = $affected->where('status', 'confirmed')->count();
        $pendingCount = $affected->where('status', 'pending')->count();
        $isEmergency = ($v['closure_type'] ?? '') === 'emergency' || $day->isToday() || $day->diffInHours(now()) <= 24;

        return response()->json([
            'count' => $affected->count(),
            'confirmed_count' => $confirmedCount,
            'pending_count' => $pendingCount,
            'is_emergency' => $isEmergency,
            'message' => "This will affect {$affected->count()} appointments ({$confirmedCount} confirmed, {$pendingCount} pending). Continue?",
            'appointments' => $affected->map(fn ($a) => [
                'id' => $a->appointment_id,
                'patient_name' => $a->patient->full_name,
                'contact_number' => $a->patient->contact_number,
                'email' => $a->patient->email ?: ($a->patient->user->email ?? 'N/A'),
                'service_name' => $a->service->service_name,
                'dentist_name' => $a->dentist->name,
                'time' => $a->start_at->format('g:i A').' – '.$a->end_at->format('g:i A'),
                'status' => $a->status,
                'status_label' => ucfirst($a->status),
            ])->values(),
        ]);
    }

    public function storeOverride(Request $request, ScheduleService $schedule)
    {
        $v = $request->validate([
            'override_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'is_closed' => 'nullable|boolean',
            'start_time' => 'nullable|required_unless:is_closed,1|date_format:H:i',
            'end_time' => 'nullable|required_unless:is_closed,1|date_format:H:i|after:start_time',
            'reason' => 'required|string|max:255',
            'closure_type' => 'required|in:planned,emergency',
        ], [
            'reason.required' => 'A reason for the closure is required (it is included in patient notification e-mails and audit logs).',
            'closure_type.required' => 'Please select whether this is a planned or emergency closure.',
        ]);

        $closed = $request->boolean('is_closed');
        $overrideDate = Carbon::parse($v['override_date']);
        $isEmergency = $v['closure_type'] === 'emergency' || $overrideDate->isToday();

        $override = ScheduleOverride::updateOrCreate(['dentist_id' => null, 'override_date' => $v['override_date']], [
            'is_closed' => $closed ? 1 : 0,
            'start_time' => $closed ? null : $v['start_time'],
            'end_time' => $closed ? null : $v['end_time'],
            'reason' => $v['reason'],
            'closure_type' => $v['closure_type'],
            'created_by_user_id' => $request->user()->user_id,
        ]);

        $affected = $schedule->applyOverride($override, $request->user());
        $count = $affected->count();

        // If it's an emergency closure or same-day, flash the call list for immediate action
        if ($isEmergency && $count > 0) {
            $callList = $affected->map(fn ($a) => [
                'id' => $a->appointment_id,
                'patient_name' => $a->patient->full_name,
                'contact_number' => $a->patient->contact_number,
                'email' => $a->patient->email ?: ($a->patient->user->email ?? 'N/A'),
                'service_name' => $a->service->service_name,
                'dentist_name' => $a->dentist->name,
                'time' => $a->start_at->format('g:i A').' – '.$a->end_at->format('g:i A'),
                'status' => ucfirst($a->status),
            ])->values()->all();

            session()->flash('call_list', $callList);
            session()->flash('call_list_date', $overrideDate->format('M j, Y'));
            session()->flash('call_list_reason', $v['reason']);
        }

        $msg = "Date override saved for {$overrideDate->format('M j, Y')}.";
        if ($count > 0) {
            $msg .= " {$count} appointment(s) were flagged for rescheduling, queued reminders were cancelled, and patients were e-mailed.";
            if ($isEmergency) {
                $msg .= ' Because this is an emergency closure, a Call List has been generated below so staff can call patients immediately.';
            }
        }

        return back()->with('tab', 'overrides')->with('status', $msg);
    }

    public function callList(ScheduleOverride $override, ScheduleService $schedule)
    {
        $day = Carbon::parse($override->override_date);
        $closed = (bool) $override->is_closed;
        $openTime = $override->start_time ? substr($override->start_time, 0, 5) : null;
        $closeTime = $override->end_time ? substr($override->end_time, 0, 5) : null;

        // Fetch affected or flagged appointments on this date
        $affected = Appointment::whereBetween('start_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->where(fn ($q) => $q->open()->orWhere('needs_reschedule', 1))
            ->with(['patient.user', 'service', 'dentist.user'])
            ->orderBy('start_at')
            ->get();

        return response()->json([
            'date' => $override->override_date->format('M j, Y'),
            'reason' => $override->reason,
            'closure_type' => $override->closure_type ?? 'planned',
            'appointments' => $affected->map(fn ($a) => [
                'id' => $a->appointment_id,
                'patient_name' => $a->patient->full_name,
                'contact_number' => $a->patient->contact_number,
                'service_name' => $a->service->service_name,
                'dentist_name' => $a->dentist->name,
                'time' => $a->start_at->format('g:i A').' – '.$a->end_at->format('g:i A'),
                'status' => ucfirst($a->status),
                'needs_reschedule' => $a->needs_reschedule,
            ]),
        ]);
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
            'installment_min_amount' => 'required|numeric|min:0|max:10000000',
            'installment_max_months' => 'required|integer|min:2|max:60',
            'installment_reminder_days' => 'required|integer|min:1|max:30',
        ]);
        foreach ($v as $k => $val) Settings::set($k, $val);
        foreach (['auto_confirm_bookings', 'enforce_service_specialty', 'reminders_enabled'] as $flag) {
            Settings::set($flag, $request->boolean($flag));
        }
        Audit::log('update', 'sch_settings', null, 'Booking rules updated');
        return back()->with('tab', 'rules')->with('status', 'Booking rules saved.');
    }

    public function saveGcashQr(Request $request)
    {
        $v = $request->validate(['gcash_qr' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120','gcash_account_name'=>'nullable|string|max:100','gcash_account_number'=>'nullable|string|max:40']);
        $old = Settings::get('gcash_qr_path');
        if ($request->hasFile('gcash_qr')) {
            $path = $v['gcash_qr']->store('clinic-settings', 'public');
            Settings::set('gcash_qr_path', $path);
            if ($old) Storage::disk('public')->delete($old);
        }
        Settings::set('gcash_account_name', $v['gcash_account_name'] ?? '');
        Settings::set('gcash_account_number', $v['gcash_account_number'] ?? '');
        Audit::log('update', 'sys_settings', 'gcash_qr_path', 'Clinic GCash payment details updated');
        return back()->with('tab', 'gcash')->with('status', 'GCash payment details updated.');
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
