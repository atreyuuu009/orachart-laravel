<?php

namespace App\Services;

use App\Models\SysSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class PatientNotifications
{
    private const SKIPPED_TEMPLATES = ['verify_email', 'reset_password', 'post_treatment_thank_you', 'installment_due'];

    public function forPatient(User $user): array
    {
        abort_unless((int) $user->role_id === 4, 403);

        $patient = $user->patient;
        if (! $patient) {
            return ['unread' => 0, 'has_red_unread' => false, 'items' => []];
        }

        $since = now()->subDays(30);
        $logs = DB::table('ntf_email_log as l')
            ->leftJoin('ntf_templates as t', 't.template_id', '=', 'l.template_id')
            ->leftJoin('apt_appointments as a', 'a.appointment_id', '=', 'l.related_appointment_id')
            ->leftJoin('svc_services as s', 's.service_id', '=', 'a.service_id')
            ->leftJoin('pat_patients as p', 'p.patient_id', '=', 'a.patient_id')
            ->whereIn('l.status', ['sent', 'failed'])
            ->whereRaw('(l.recipient_user_id = ? OR l.recipient_email = ? OR (p.patient_id = ? AND l.recipient_email = p.email))', [$user->user_id, $user->email, $patient->patient_id])
            ->whereRaw('COALESCE(l.sent_at, l.created_at) >= ?', [$since])
            ->whereNotIn('t.template_key', self::SKIPPED_TEMPLATES)
            ->select(['l.email_log_id', 'l.subject', 'l.created_at', 'l.sent_at', 't.template_key', 'a.appointment_id', 'a.patient_id', 's.service_name'])
            ->get()
            ->filter(fn ($row) => ! $row->patient_id || (int) $row->patient_id === (int) $patient->patient_id)
            ->map(fn ($row) => $this->emailItem($row));

        $completed = DB::table('apt_appointments as a')
            ->leftJoin('svc_services as s', 's.service_id', '=', 'a.service_id')
            ->where('a.patient_id', $patient->patient_id)
            ->where('a.status', 'completed')
            ->where('a.updated_at', '>=', $since)
            ->select(['a.appointment_id', 'a.updated_at as created_at', 's.service_name'])
            ->get()
            ->map(fn ($row) => [
                'id' => 'completed-'.$row->appointment_id, 'key' => 'appointment_completed',
                'title' => 'Appointment completed',
                'message' => 'You just completed your '.($row->service_name ?: 'appointment').' appointment!',
                'tone' => 'green', 'sticky' => false, 'created_at' => Carbon::parse($row->created_at),
                'url' => $this->routeUrl('appointments.mine'),
            ]);

        $due = DB::table('pay_installment_payments as ip')
            ->join('pay_installment_plans as pl', 'pl.installment_plan_id', '=', 'ip.installment_plan_id')
            ->leftJoin('svc_services as s', 's.service_id', '=', 'pl.service_id')
            ->where('pl.patient_id', $patient->patient_id)
            ->whereIn('pl.status', ['approved', 'active'])
            ->whereIn('ip.status', ['unpaid', 'rejected', 'overdue'])
            ->where(fn ($query) => $query->where('ip.status', 'rejected')->orWhereDate('ip.due_date', '<=', today()->addDays(3)))
            ->select(['ip.installment_payment_id', 'ip.due_date', 'ip.status', 'ip.rejection_reason', 'ip.late_fee_amount', 'pl.service_id', 's.service_name'])
            ->get()
            ->map(fn ($row) => [
                'id' => 'installment-'.$row->installment_payment_id, 'key' => 'payment_due',
                'title' => 'Payment due',
                'message' => $row->status === 'rejected'
                    ? 'Your proof was rejected. '.($row->rejection_reason ?: 'Please upload a new JPG or PNG proof.').' Resubmit your proof from My Installments.'
                    : 'Your payment for '.($row->service_name ?: 'your treatment').' is '.(Carbon::parse($row->due_date)->toDateString() < today()->toDateString() ? 'overdue; please contact the clinic if you need a schedule adjustment.' : (Carbon::parse($row->due_date)->isToday() ? 'due today.' : 'due by '.Carbon::parse($row->due_date)->format('M j').'.')).((float)$row->late_fee_amount > 0 ? ' Late fee included: ₱'.number_format((float)$row->late_fee_amount, 2).'.' : ''),
                'tone' => 'red', 'sticky' => true, 'created_at' => Carbon::parse($row->due_date)->startOfDay(),
                'url' => $this->paymentUrl(),
            ]);

        $seen = SysSetting::whereKey('notif_seen_'.$user->user_id)->value('setting_value');
        $seenAt = $seen ? Carbon::parse($seen) : null;
        $allItems = $logs->concat($completed)->concat($due)
            ->sortByDesc(fn ($item) => $item['sticky'] ? '1|'.($item['created_at']?->format('Y-m-d H:i:s') ?? '') : '0|'.($item['created_at']?->format('Y-m-d H:i:s') ?? ''))
            ->map(function ($item) use ($seenAt) {
                $item['unread'] = $item['sticky'] || ! $seenAt || $item['created_at']->gt($seenAt);
                $item['time'] = $item['created_at']->diffForHumans();
                $item['created_at'] = $item['created_at']->toIso8601String();
                return $item;
            })->values();
        $unread = $allItems->where('unread', true);
        $items = $allItems->take(20);

        return [
            'unread' => $unread->count(),
            'has_red_unread' => $unread->contains(fn ($item) => $item['tone'] === 'red'),
            'items' => $items->all(),
        ];
    }

    public function markSeen(User $user): void
    {
        abort_unless((int) $user->role_id === 4, 403);
        DB::table('sys_settings')->updateOrInsert(
            ['setting_key' => 'notif_seen_'.$user->user_id],
            ['setting_value' => now()->toDateTimeString(), 'description' => 'Patient notification last opened', 'updated_at' => now()]
        );
    }

    private function emailItem(object $row): array
    {
        $key = $row->template_key ?: '';
        [$title, $message, $tone] = match ($key) {
            'booking_confirmed' => ['Appointment confirmed', 'Your appointment is confirmed.', 'green'],
            'payment_verified' => ['Payment verified', 'Your payment has been verified.', 'green'],
            'installment_approved' => ['Installment approved', 'Your installment plan has been approved.', 'green'],
            'installment_overdue' => ['Installment 7 days overdue', 'Your installment is 7 days overdue. Please view your schedule or contact the clinic to discuss an adjustment.', 'red'],
            'installment_schedule_changed' => ['Installment schedule adjusted', 'The clinic adjusted your installment schedule. View your current due dates.', 'yellow'],
            'booking_received' => ['Booking received', 'We received your appointment request.', 'yellow'],
            'booking_rejected' => ['Booking declined', 'Your appointment request was declined.', 'yellow'],
            'appointment_cancelled' => ['Appointment cancelled', 'Your appointment was cancelled.', 'yellow'],
            'appointment_reminder' => ['Appointment reminder', 'You have an upcoming appointment.', 'yellow'],
            'treatment_reminder' => ['Treatment reminder', 'Your next treatment visit is coming up.', 'yellow'],
            'recall_reminder' => ['Check-up reminder', 'It may be time to book your next check-up.', 'yellow'],
            'schedule_changed', 'schedule_changed_reminder' => ['Needs rescheduling', 'Your appointment needs a new time. Please choose another slot.', 'red'],
            'payment_rejected' => ['Payment rejected', 'Your payment could not be verified. Please review your payment.', 'red'],
            'installment_rejected' => ['Installment declined', 'Your installment request was declined.', 'red'],
            default => [$row->subject ?: 'Clinic update', 'There is an update about your care.', 'yellow'],
        };
        $when = Carbon::parse($row->sent_at ?: $row->created_at);
        $url = in_array($key, ['booking_received'], true) ? $this->routeUrl('booking.create') : (in_array($key, ['payment_rejected', 'installment_overdue', 'installment_schedule_changed'], true) ? $this->paymentUrl() : $this->routeUrl('appointments.mine'));

        return ['id' => 'email-'.$row->email_log_id, 'key' => $key, 'title' => $title, 'message' => $message,
            'tone' => $tone, 'sticky' => false, 'created_at' => $when, 'url' => $url];
    }

    private function paymentUrl(): ?string
    {
        if (Route::has('installments.mine')) return route('installments.mine');
        if (Route::has('payments.index')) return route('payments.index');
        return null;
    }

    private function routeUrl(string $name): ?string
    {
        return Route::has($name) ? route($name) : null;
    }
}
