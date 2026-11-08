<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/** Replaces the TODO bodies from the SQL seed with real templates. Placeholders use {name}. */
class NtfTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'verify_email' => ['Verify your OraChart account', <<<'HTML'
<p>Hi {first_name},</p><p>Thanks for registering with {clinic_name}. Please confirm your e-mail address to activate your account. This link is valid for 24 hours.</p><p style="margin:24px 0;"><a href="{action_url}" style="background:#243b8f;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;display:inline-block;">Verify my e-mail</a></p><p style="color:#6b7280;font-size:13px;">If you did not create this account, you can ignore this message.</p>
HTML],
            'reset_password' => ['Reset your OraChart password', <<<'HTML'
<p>Hi {first_name},</p><p>We received a request to reset your password. The link below is valid for {expires_minutes} minutes.</p><p style="margin:24px 0;"><a href="{action_url}" style="background:#243b8f;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;display:inline-block;">Choose a new password</a></p><p style="color:#6b7280;font-size:13px;">If you did not request this, no action is needed.</p>
HTML],
            'booking_received' => ['We received your booking request', <<<'HTML'
<p>Hi {patient_name},</p><p>We received your appointment request. The clinic will review it and e-mail you once it is confirmed.</p><table style="margin:12px 0;border-collapse:collapse;"><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Procedure</td><td><strong>{service}</strong></td></tr><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Dentist</td><td>{dentist}</td></tr><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Date</td><td>{date}</td></tr><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Time</td><td>{time}</td></tr></table>
HTML],
            'booking_confirmed' => ['Your appointment is confirmed', <<<'HTML'
<p>Hi {patient_name},</p><p>Your appointment at {clinic_name} is confirmed.</p><table style="margin:12px 0;border-collapse:collapse;"><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Procedure</td><td><strong>{service}</strong></td></tr><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Dentist</td><td>{dentist}</td></tr><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Date</td><td>{date}</td></tr><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Time</td><td>{time}</td></tr></table><p>Please arrive {arrive_minutes} minutes before your scheduled time.</p><p>Need to change plans? Log in and open My Appointments.</p>
HTML],
            'booking_rejected' => ['Your appointment request was declined', <<<'HTML'
<p>Hi {patient_name},</p><p>Unfortunately we could not accept your request for {service} on {date} at {time}.</p><p>Reason: {reason}</p><p>Please book another time that works for you.</p><p style="margin:24px 0;"><a href="{action_url}" style="background:#243b8f;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;display:inline-block;">Book again</a></p>
HTML],
            'appointment_cancelled' => ['Your appointment was cancelled', <<<'HTML'
<p>Hi {patient_name},</p><p>Your appointment for {service} on {date} at {time} was cancelled.</p><p>Reason: {reason}</p><p>You are welcome to book a new time at any point.</p><p style="margin:24px 0;"><a href="{action_url}" style="background:#243b8f;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;display:inline-block;">Book a new time</a></p>
HTML],
            'appointment_reminder' => ['Appointment reminder', <<<'HTML'
<p>Hi {patient_name},</p><p>This is a friendly reminder of your upcoming appointment.</p><table style="margin:12px 0;border-collapse:collapse;"><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Procedure</td><td><strong>{service}</strong></td></tr><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Dentist</td><td>{dentist}</td></tr><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Date</td><td>{date}</td></tr><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Time</td><td>{time}</td></tr></table><p>Please arrive {arrive_minutes} minutes early.</p><p style="margin:24px 0;"><a href="{action_url}" style="background:#243b8f;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;display:inline-block;">View my appointments</a></p>
HTML],
            'treatment_reminder' => ['Your next treatment visit', <<<'HTML'
<p>Hi {patient_name},</p><p>Your next treatment visit is coming up.</p><table style="margin:12px 0;border-collapse:collapse;"><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Procedure</td><td><strong>{service}</strong></td></tr><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Dentist</td><td>{dentist}</td></tr><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Date</td><td>{date}</td></tr><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Time</td><td>{time}</td></tr></table><p>Please arrive {arrive_minutes} minutes early.</p><p style="margin:24px 0;"><a href="{action_url}" style="background:#243b8f;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;display:inline-block;">View my appointments</a></p>
HTML],
            'post_treatment_thank_you' => ['Thank you for visiting us', <<<'HTML'
<p>Hi {patient_name},</p><p>Thank you for visiting {clinic_name}. We hope to see you again!</p><p>If you notice anything unusual after your {service}, please contact the clinic.</p>
HTML],
            'recall_reminder' => ['Time for your routine check-up', <<<'HTML'
<p>Hi {patient_name},</p><p>It has been a while since your last visit. It may be time for your routine dental cleaning and check-up.</p><p style="margin:24px 0;"><a href="{action_url}" style="background:#243b8f;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;display:inline-block;">Book my next visit</a></p>
HTML],
            'schedule_changed' => ['Your appointment needs rescheduling', <<<'HTML'
<p>Hi {patient_name},</p><p>Because of a change in the clinic schedule, we need to move your appointment:</p><table style="margin:12px 0;border-collapse:collapse;"><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Procedure</td><td><strong>{service}</strong></td></tr><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Dentist</td><td>{dentist}</td></tr><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Date</td><td>{date}</td></tr><tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Time</td><td>{time}</td></tr></table><p>We are sorry for the inconvenience. Please choose a new time.</p><p style="margin:24px 0;"><a href="{action_url}" style="background:#243b8f;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;display:inline-block;">Reschedule now</a></p>
HTML],
        ];

        foreach ($templates as $key => [$subject, $html]) {
            EmailTemplate::updateOrCreate(['template_key' => $key], ['subject' => $subject, 'body_html' => $html, 'is_active' => 1]);
        }
    }
}
