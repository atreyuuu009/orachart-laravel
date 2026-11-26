<?php

namespace App\Support;

use App\Models\SchSetting;
use App\Models\SysSetting;
use Illuminate\Support\Facades\Auth;

/**
 * One place to read/write clinic settings. Keys live in sch_settings (scheduling rules)
 * or sys_settings (system); keys missing from the seed fall back to DEFAULTS.
 */
class Settings
{
    /** keys stored in sch_settings; everything else is sys_settings */
    private const SCH = [
        'slot_interval_minutes', 'buffer_before_minutes', 'buffer_after_minutes', 'min_booking_notice_hours',
        'slot_lock_minutes', 'max_patients_per_day', 'emergency_release_minutes', 'round_robin_last_dentist_id',
        'arrival_notice_minutes',
    ];

    public const DEFAULTS = [
        'slot_interval_minutes' => '30', 'buffer_before_minutes' => '0', 'buffer_after_minutes' => '10',
        'min_booking_notice_hours' => '12', 'slot_lock_minutes' => '5', 'max_patients_per_day' => '17',
        'emergency_release_minutes' => '30', 'round_robin_last_dentist_id' => '0', 'arrival_notice_minutes' => '10',
        'clinic_name' => 'Guda & Soriano Dental Clinic', 'session_timeout_minutes' => '15',
        'privacy_policy_version' => '1.0', 'auto_confirm_bookings' => '1', 'enforce_service_specialty' => '0',
        'reminders_enabled' => '1', 'reminder_hours_before' => '24', 'recall_months' => '6',
        'installment_min_amount' => '10000', 'installment_max_months' => '12', 'installment_reminder_days' => '3', 'gcash_qr_path' => '', 'gcash_account_name' => '', 'gcash_account_number' => '',
    ];

    private static array $cache = [];

    public static function get(string $key): string
    {
        if (array_key_exists($key, self::$cache)) return self::$cache[$key];
        $model = in_array($key, self::SCH, true) ? SchSetting::class : SysSetting::class;
        $row = $model::find($key);
        return self::$cache[$key] = $row ? (string) $row->setting_value : (self::DEFAULTS[$key] ?? '');
    }

    public static function int(string $key): int { return (int) self::get($key); }
    public static function bool(string $key): bool { return in_array(strtolower(self::get($key)), ['1', 'true', 'yes', 'on'], true); }

    public static function set(string $key, string|int|bool $value): void
    {
        $value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        if (in_array($key, self::SCH, true)) {
            SchSetting::updateOrCreate(['setting_key' => $key], ['setting_value' => $value, 'updated_by_user_id' => Auth::id()]);
        } else {
            SysSetting::updateOrCreate(['setting_key' => $key], ['setting_value' => $value]);
        }
        self::$cache[$key] = $value;
    }
}
