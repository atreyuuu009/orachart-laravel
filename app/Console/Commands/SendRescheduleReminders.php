<?php

namespace App\Console\Commands;

use App\Services\ScheduleService;
use Illuminate\Console\Command;

class SendRescheduleReminders extends Command
{
    protected $signature = 'orachart:reschedule-reminders';
    protected $description = 'Send 24-hour follow-up reminders to patients who haven\'t rescheduled after a clinic closure';

    public function handle(ScheduleService $schedule): int
    {
        $count = $schedule->sendSecondReminders();
        $this->info("{$count} second reminder e-mail(s) sent to patients needing reschedule.");
        return self::SUCCESS;
    }
}
