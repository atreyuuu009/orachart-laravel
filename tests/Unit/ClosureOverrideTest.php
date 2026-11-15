<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Dentist;
use App\Models\EmailLog;
use App\Models\Patient;
use App\Models\ScheduleOverride;
use App\Models\Service;
use App\Models\SlotLock;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\ScheduleService;
use App\Support\Settings;
use Carbon\Carbon;
use Tests\TestCase;

class ClosureOverrideTest extends TestCase
{
    public function test_get_conflicts_only_returns_pending_and_confirmed_appointments(): void
    {
        $dentist = Dentist::first();
        $patient = Patient::first();
        $service = Service::first();
        $date = Carbon::tomorrow()->setTime(10, 0);

        // Create appointments with various statuses
        $confirmed = Appointment::create([
            'patient_id' => $patient->patient_id,
            'service_id' => $service->service_id,
            'assigned_dentist_id' => $dentist->dentist_id,
            'start_at' => $date,
            'end_at' => $date->copy()->addMinutes(30),
            'status' => 'confirmed',
        ]);

        $pending = Appointment::create([
            'patient_id' => $patient->patient_id,
            'service_id' => $service->service_id,
            'assigned_dentist_id' => $dentist->dentist_id,
            'start_at' => $date->copy()->addHours(1),
            'end_at' => $date->copy()->addHours(1)->addMinutes(30),
            'status' => 'pending',
        ]);

        $completed = Appointment::create([
            'patient_id' => $patient->patient_id,
            'service_id' => $service->service_id,
            'assigned_dentist_id' => $dentist->dentist_id,
            'start_at' => $date->copy()->addHours(2),
            'end_at' => $date->copy()->addHours(2)->addMinutes(30),
            'status' => 'completed',
        ]);

        $cancelled = Appointment::create([
            'patient_id' => $patient->patient_id,
            'service_id' => $service->service_id,
            'assigned_dentist_id' => $dentist->dentist_id,
            'start_at' => $date->copy()->addHours(3),
            'end_at' => $date->copy()->addHours(3)->addMinutes(30),
            'status' => 'cancelled',
        ]);

        $service_sched = new ScheduleService();
        $conflicts = $service_sched->getConflicts(null, $date, true);

        $this->assertTrue($conflicts->contains('appointment_id', $confirmed->appointment_id));
        $this->assertTrue($conflicts->contains('appointment_id', $pending->appointment_id));
        $this->assertFalse($conflicts->contains('appointment_id', $completed->appointment_id));
        $this->assertFalse($conflicts->contains('appointment_id', $cancelled->appointment_id));

        // Clean up
        $confirmed->forceDelete();
        $pending->forceDelete();
        $completed->forceDelete();
        $cancelled->forceDelete();
    }

    public function test_apply_override_deletes_locks_flags_appointments_cancels_queued_emails_and_audits(): void
    {
        $dentist = Dentist::first();
        $patient = Patient::first();
        $service = Service::first();
        $admin = User::where('role_id', 1)->first();
        $date = Carbon::tomorrow()->setTime(14, 0);

        // 1. Create active slot lock on that date
        $lock = SlotLock::create([
            'dentist_id' => $dentist->dentist_id,
            'start_at' => $date,
            'end_at' => $date->copy()->addMinutes(40),
            'session_id' => 'test-session-closure',
            'expires_at' => now()->addMinutes(5),
        ]);

        // 2. Create appointment
        $appt = Appointment::create([
            'patient_id' => $patient->patient_id,
            'service_id' => $service->service_id,
            'assigned_dentist_id' => $dentist->dentist_id,
            'start_at' => $date,
            'end_at' => $date->copy()->addMinutes(30),
            'status' => 'confirmed',
            'needs_reschedule' => 0,
        ]);

        // 3. Create queued reminder email for this appointment
        $email = EmailLog::create([
            'recipient_email' => 'test@patient.com',
            'subject' => 'Appointment Reminder',
            'related_appointment_id' => $appt->appointment_id,
            'status' => 'queued',
            'scheduled_for' => $date->copy()->subHours(24),
        ]);

        // 4. Create ScheduleOverride
        $override = ScheduleOverride::create([
            'override_date' => $date->toDateString(),
            'is_closed' => 1,
            'reason' => 'Severe Weather Closure',
            'closure_type' => 'emergency',
            'created_by_user_id' => $admin->user_id,
        ]);

        // Apply closure
        $schedule = new ScheduleService();
        $affected = $schedule->applyOverride($override, $admin);

        // Verifications:
        // A. Slot lock was deleted
        $this->assertNull(SlotLock::find($lock->slot_lock_id));

        // B. Appointment flagged with needs_reschedule = 1
        $appt->refresh();
        $this->assertEquals(1, $appt->needs_reschedule);

        // C. Queued reminder e-mail was cancelled
        $email->refresh();
        $this->assertEquals('cancelled', $email->status);
        $this->assertStringContainsString('Cancelled due to clinic schedule closure', (string) $email->error_message);

        // D. schedule_changed notification was recorded
        $notification = EmailLog::where('related_appointment_id', $appt->appointment_id)
            ->where('subject', 'like', '%rescheduling%')
            ->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Severe Weather Closure', (string) $notification->body_html);
        $this->assertStringContainsString('No Penalty', (string) $notification->body_html);

        // E. Audit log created
        $audit = AuditLog::where('table_affected', 'sch_overrides')
            ->where('record_id', (string) $override->override_id)
            ->latest('audit_log_id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertStringContainsString('Severe Weather Closure', $audit->description);
        $this->assertStringContainsString('1 appointment(s) affected', $audit->description);

        // Clean up
        EmailLog::where('related_appointment_id', $appt->appointment_id)->delete();
        $appt->forceDelete();
        $override->delete();
    }

    public function test_closure_reschedule_bypasses_12h_notice_and_daily_cap(): void
    {
        $dentist = Dentist::first();
        $patient = Patient::first();
        $service = Service::first();

        // Target slot is 2 hours from now (within 12h notice window)
        $shortNoticeDate = now()->addHours(2);
        // Ensure within working hours: 10:00 AM today or tomorrow
        $targetDate = now()->hour >= 16 ? Carbon::tomorrow()->setTime(10, 0) : now()->addHours(2);

        // Normal booking without emergency or closure reschedule: notice restriction applies if < min_booking_notice_hours
        $availability = new AvailabilityService();

        // Create an appointment flagged with needs_reschedule = 1
        $flaggedAppt = Appointment::create([
            'patient_id' => $patient->patient_id,
            'service_id' => $service->service_id,
            'assigned_dentist_id' => $dentist->dentist_id,
            'start_at' => Carbon::tomorrow()->setTime(11, 0),
            'end_at' => Carbon::tomorrow()->setTime(11, 30),
            'status' => 'confirmed',
            'needs_reschedule' => 1,
        ]);

        // When passing exclude = $flaggedAppt->appointment_id to slots():
        $res = $availability->slots(
            $service,
            $targetDate,
            $dentist->dentist_id,
            emergency: false,
            excludeAppointmentId: $flaggedAppt->appointment_id
        );

        // Slots should not be marked 'notice' or 'full' due to bypass
        $hasNotice = collect($res['slots'])->contains(fn ($s) => $s['state'] === 'notice');
        $this->assertFalse($hasNotice, 'Notice restriction should be bypassed for closure reschedule');

        $flaggedAppt->forceDelete();
    }

    public function test_preview_override_returns_confirmation_structure(): void
    {
        $admin = User::where('role_id', 1)->first();
        $dentist = Dentist::first();
        $patient = Patient::first();
        $service = Service::first();
        $date = Carbon::tomorrow()->setTime(10, 0);

        $appt1 = Appointment::create([
            'patient_id' => $patient->patient_id,
            'service_id' => $service->service_id,
            'assigned_dentist_id' => $dentist->dentist_id,
            'start_at' => $date,
            'end_at' => $date->copy()->addMinutes(30),
            'status' => 'confirmed',
        ]);
        $appt2 = Appointment::create([
            'patient_id' => $patient->patient_id,
            'service_id' => $service->service_id,
            'assigned_dentist_id' => $dentist->dentist_id,
            'start_at' => $date->copy()->addHours(1),
            'end_at' => $date->copy()->addHours(1)->addMinutes(30),
            'status' => 'pending',
        ]);

        $res = $this->actingAs($admin)->postJson(route('admin.settings.overrides.preview'), [
            'override_date' => $date->toDateString(),
            'is_closed' => 1,
            'closure_type' => 'planned',
        ]);

        $res->assertStatus(200);
        $res->assertJsonFragment([
            'count' => 2,
            'confirmed_count' => 1,
            'pending_count' => 1,
            'message' => 'This will affect 2 appointments (1 confirmed, 1 pending). Continue?',
        ]);
        $res->assertJsonStructure([
            'count', 'confirmed_count', 'pending_count', 'is_emergency', 'message',
            'appointments' => [
                '*' => ['id', 'patient_name', 'contact_number', 'service_name', 'dentist_name', 'time', 'status', 'status_label']
            ]
        ]);

        $appt1->forceDelete();
        $appt2->forceDelete();
    }

    public function test_store_override_requires_reason_and_closure_type(): void
    {
        $admin = User::where('role_id', 1)->first();
        $date = Carbon::tomorrow()->toDateString();

        // Missing reason and closure_type
        $res = $this->actingAs($admin)->post(route('admin.settings.overrides.store'), [
            'override_date' => $date,
            'is_closed' => 1,
        ]);
        $res->assertSessionHasErrors(['reason', 'closure_type']);

        // With emergency closure: sets call_list in session if appointments affected
        $dentist = Dentist::first();
        $patient = Patient::first();
        $service = Service::first();

        $appt = Appointment::create([
            'patient_id' => $patient->patient_id,
            'service_id' => $service->service_id,
            'assigned_dentist_id' => $dentist->dentist_id,
            'start_at' => Carbon::tomorrow()->setTime(9, 30),
            'end_at' => Carbon::tomorrow()->setTime(10, 0),
            'status' => 'confirmed',
        ]);

        $resSuccess = $this->actingAs($admin)->post(route('admin.settings.overrides.store'), [
            'override_date' => $date,
            'is_closed' => 1,
            'reason' => 'Emergency power transformer repair',
            'closure_type' => 'emergency',
        ]);

        $resSuccess->assertRedirect();
        $resSuccess->assertSessionHas('call_list');
        $resSuccess->assertSessionHas('call_list_date');
        $resSuccess->assertSessionHas('call_list_reason', 'Emergency power transformer repair');

        // Cleanup
        EmailLog::where('related_appointment_id', $appt->appointment_id)->delete();
        $appt->forceDelete();
        ScheduleOverride::where('override_date', $date)->delete();
    }

    public function test_priority_order_in_needs_reschedule_queue(): void
    {
        $admin = User::where('role_id', 1)->first();
        $dentist = Dentist::first();
        $patient = Patient::first();
        $standardSvc = Service::where('is_multi_visit', 0)->first();
        $multiVisitSvc = Service::where('is_multi_visit', 1)->first() ?? $standardSvc;

        // 1. Standard appointment 3 days from now
        $aStandard = Appointment::create([
            'patient_id' => $patient->patient_id,
            'service_id' => $standardSvc->service_id,
            'assigned_dentist_id' => $dentist->dentist_id,
            'start_at' => Carbon::now()->addDays(3)->setTime(14, 0),
            'end_at' => Carbon::now()->addDays(3)->setTime(14, 30),
            'status' => 'confirmed',
            'needs_reschedule' => 1,
            'is_emergency' => 0,
        ]);

        // 2. Multi-visit treatment (priority 3)
        $treatmentPlan = \App\Models\TreatmentPlan::create([
            'patient_id' => $patient->patient_id,
            'service_id' => $standardSvc->service_id,
            'planned_visits' => 4,
        ]);

        $aMulti = Appointment::create([
            'patient_id' => $patient->patient_id,
            'service_id' => $standardSvc->service_id,
            'assigned_dentist_id' => $dentist->dentist_id,
            'start_at' => Carbon::now()->addDays(2)->setTime(10, 0),
            'end_at' => Carbon::now()->addDays(2)->setTime(10, 30),
            'status' => 'confirmed',
            'needs_reschedule' => 1,
            'is_emergency' => 0,
            'treatment_plan_id' => $treatmentPlan->treatment_plan_id,
        ]);

        // 3. Emergency tomorrow (priority 2)
        $aEmergency = Appointment::create([
            'patient_id' => $patient->patient_id,
            'service_id' => $standardSvc->service_id,
            'assigned_dentist_id' => $dentist->dentist_id,
            'start_at' => Carbon::tomorrow()->setTime(15, 0),
            'end_at' => Carbon::tomorrow()->setTime(15, 30),
            'status' => 'confirmed',
            'needs_reschedule' => 1,
            'is_emergency' => 1,
        ]);

        // 4. Same-day appointment (priority 1)
        $aSameDay = Appointment::create([
            'patient_id' => $patient->patient_id,
            'service_id' => $standardSvc->service_id,
            'assigned_dentist_id' => $dentist->dentist_id,
            'start_at' => Carbon::today()->setTime(16, 0),
            'end_at' => Carbon::today()->setTime(16, 30),
            'status' => 'confirmed',
            'needs_reschedule' => 1,
            'is_emergency' => 0,
        ]);

        $res = $this->actingAs($admin)->get(route('appointments.index', ['status' => 'needs_reschedule']));
        $res->assertStatus(200);

        $items = $res->viewData('appointments')->items();
        $ids = collect($items)->pluck('appointment_id')->all();

        $idxSameDay = array_search($aSameDay->appointment_id, $ids, true);
        $idxEmergency = array_search($aEmergency->appointment_id, $ids, true);
        $idxMulti = array_search($aMulti->appointment_id, $ids, true);
        $idxStandard = array_search($aStandard->appointment_id, $ids, true);

        $this->assertNotFalse($idxSameDay);
        $this->assertNotFalse($idxEmergency);
        $this->assertNotFalse($idxMulti);
        $this->assertNotFalse($idxStandard);

        // Verify priority ordering: SameDay < Emergency < Multi < Standard
        $this->assertLessThan($idxEmergency, $idxSameDay, 'Same day should precede emergency');
        $this->assertLessThan($idxMulti, $idxEmergency, 'Emergency should precede multi-visit');
        $this->assertLessThan($idxStandard, $idxMulti, 'Multi-visit should precede standard upcoming');

        // Cleanup
        $aStandard->forceDelete();
        $aMulti->forceDelete();
        $treatmentPlan->delete();
        $aEmergency->forceDelete();
        $aSameDay->forceDelete();
    }

    public function test_suggest_slots_returns_three_available_slots(): void
    {
        $dentist = Dentist::first();
        $patient = Patient::first();
        $service = Service::first();

        $appt = Appointment::create([
            'patient_id' => $patient->patient_id,
            'service_id' => $service->service_id,
            'assigned_dentist_id' => $dentist->dentist_id,
            'start_at' => Carbon::tomorrow()->setTime(10, 0),
            'end_at' => Carbon::tomorrow()->setTime(10, 30),
            'status' => 'confirmed',
            'needs_reschedule' => 1,
        ]);

        $schedule = new ScheduleService();
        $slots = $schedule->suggestSlots($appt, 3);

        $this->assertIsArray($slots);
        $this->assertNotEmpty($slots);
        $this->assertLessThanOrEqual(3, count($slots));
        $this->assertArrayHasKey('date', $slots[0]);
        $this->assertArrayHasKey('time', $slots[0]);
        $this->assertArrayHasKey('dentist_name', $slots[0]);

        // Cleanup
        $appt->forceDelete();
    }

    public function test_second_reminder_sent_after_24_hours_without_auto_cancelling(): void
    {
        $dentist = Dentist::first();
        $patient = Patient::first();
        $service = Service::first();

        $appt = Appointment::create([
            'patient_id' => $patient->patient_id,
            'service_id' => $service->service_id,
            'assigned_dentist_id' => $dentist->dentist_id,
            'start_at' => Carbon::now()->addDays(4)->setTime(10, 0),
            'end_at' => Carbon::now()->addDays(4)->setTime(10, 30),
            'status' => 'confirmed',
            'needs_reschedule' => 1,
        ]);

        // Initial notification sent 25 hours ago
        $initialEmail = EmailLog::create([
            'recipient_email' => 'patient@test.com',
            'subject' => 'Clinic Schedule Update: Your appointment needs rescheduling',
            'related_appointment_id' => $appt->appointment_id,
            'status' => 'sent',
            'created_at' => now()->subHours(25),
            'sent_at' => now()->subHours(25),
        ]);

        $schedule = new ScheduleService();
        $sentCount = $schedule->sendSecondReminders();

        $this->assertGreaterThanOrEqual(1, $sentCount);

        // Second reminder log exists
        $secondEmail = EmailLog::where('related_appointment_id', $appt->appointment_id)
            ->where('subject', 'like', '%Second Reminder%')
            ->first();
        $this->assertNotNull($secondEmail);

        // Appointment MUST still be open with needs_reschedule = 1 (NOT auto-cancelled!)
        $appt->refresh();
        $this->assertEquals(1, $appt->needs_reschedule);
        $this->assertEquals('confirmed', $appt->status);

        // Running again should not duplicate
        $dupeCount = $schedule->sendSecondReminders();
        $this->assertEquals(0, $dupeCount);

        // Cleanup
        $initialEmail->delete();
        $secondEmail->delete();
        $appt->forceDelete();
    }
}
