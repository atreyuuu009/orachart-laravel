<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * E-mail via SMTP (Mailtrap in dev). Every message goes through ntf_email_log:
 *   - no $when  -> sent immediately
 *   - with $when -> stays 'queued' until `php artisan orachart:send-emails` (scheduler) picks it up
 * A mail failure is logged ('failed') and never breaks the booking/registration that triggered it.
 */
class Notifier
{
    public static function queue(string $templateKey, ?string $email, array $vars = [], ?int $userId = null, ?int $appointmentId = null, ?Carbon $when = null): ?EmailLog
    {
        if (! $email) return null;

        try {
            $tpl = EmailTemplate::where('template_key', $templateKey)->where('is_active', 1)->first();
        } catch (\Throwable $e) {
            Log::warning('Email template lookup failed: '.$e->getMessage());
            return null;
        }

        if (! $tpl) return null;

        try {
            // Create the log first so a rendering failure can still be recorded as failed.
            $log = EmailLog::create([
                'template_id' => $tpl->template_id,
                'recipient_user_id' => $userId,
                'recipient_email' => $email,
                'subject' => mb_substr($tpl->subject, 0, 255),
                'body_html' => null,
                'related_appointment_id' => $appointmentId,
                'status' => 'queued',
                'scheduled_for' => $when,
            ]);
        } catch (\Throwable $e) {
            // If the log table itself is unavailable, no failed row can be persisted.
            Log::warning('Email log creation failed: '.$e->getMessage());
            return null;
        }

        try {
            $vars += ['clinic_name' => Settings::get('clinic_name'), 'app_url' => url('/')];
            $subject = self::merge($tpl->subject, $vars, false);
            $html = view('emails.layout', ['subject' => $subject, 'body' => self::merge($tpl->body_html, $vars, true)])->render();
            $log->update([
                'subject' => mb_substr($subject, 0, 255),
                'body_html' => $html,
            ]);

            if (! $when || $when->lte(now())) self::deliver($log);
        } catch (\Throwable $e) {
            Log::warning('Email preparation failed: '.$e->getMessage());
            try {
                $log->update([
                    'status' => 'failed',
                    'error_message' => mb_substr($e->getMessage(), 0, 500),
                ]);
            } catch (\Throwable $logError) {
                Log::error('Could not update failed email log: '.$logError->getMessage());
            }
        }

        return $log;
    }

    /** Convenience: template + appointment placeholders. */
    public static function forAppointment(string $templateKey, Appointment $a, array $extra = [], ?Carbon $when = null): ?EmailLog
    {
        $a->loadMissing('patient.user', 'service', 'dentist.user');
        $p = $a->patient;
        $email = $p->email ?: ($p->user->email ?? null);
        $vars = $extra + [
            'patient_name' => $p->full_name,
            'first_name' => $p->first_name,
            'service' => $a->service->service_name,
            'dentist' => $a->dentist->name,
            'date' => $a->start_at->format('l, F j, Y'),
            'time' => $a->start_at->format('g:i A'),
            'arrive_minutes' => Settings::int('arrival_notice_minutes'),
            'reason' => '',
            'action_url' => $p->user_id ? route('appointments.mine') : url('/'),
        ];
        return self::queue($templateKey, $email, $vars, $p->user_id, $a->appointment_id, $when);
    }

    public static function deliver(EmailLog $log): void
    {
        try {
            Mail::html($log->body_html, fn ($m) => $m->to($log->recipient_email)->subject($log->subject));
            $log->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);
        } catch (\Throwable $e) {
            Log::warning('Email send failed: '.$e->getMessage());
            try {
                $log->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 500)]);
            } catch (\Throwable $logError) {
                Log::error('Could not update failed email log: '.$logError->getMessage());
            }
        }
    }

    /** Send queued reminders that are due. Returns how many were attempted. */
    public static function dispatchDue(int $limit = 100): int
    {
        $due = EmailLog::where('status', 'queued')->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())->orderBy('scheduled_for')->limit($limit)->get();

        $appointmentReminderTemplateIds = EmailTemplate::whereIn('template_key', [
            'appointment_reminder', 'treatment_reminder',
        ])->pluck('template_id')->all();

        foreach ($due as $log) {
            if ($log->related_appointment_id && in_array($log->template_id, $appointmentReminderTemplateIds)) {
                $appointment = Appointment::find($log->related_appointment_id);

                if ($appointment && $appointment->start_at->lte(now())) {
                    $log->update([
                        'status' => 'cancelled',
                        'error_message' => 'Cancelled because the appointment start time has passed.',
                    ]);
                    continue;
                }
            }

            self::deliver($log);
        }

        return $due->count();
    }

    /** Cancel not-yet-sent reminders of an appointment (cancelled / rescheduled). */
    public static function cancelQueued(int $appointmentId): void
    {
        EmailLog::where('related_appointment_id', $appointmentId)->where('status', 'queued')
            ->whereNotNull('scheduled_for')->update(['status' => 'cancelled']);
    }

    /** {placeholder} substitution; values are HTML-escaped in bodies. */
    private static function merge(string $text, array $vars, bool $escape): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function ($m) use ($vars, $escape) {
            if (! array_key_exists($m[1], $vars)) return $m[0];
            return $escape ? e((string) $vars[$m[1]]) : (string) $vars[$m[1]];
        }, $text);
    }
}
