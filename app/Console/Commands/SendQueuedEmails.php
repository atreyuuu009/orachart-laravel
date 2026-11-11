<?php

namespace App\Console\Commands;

use App\Services\Notifier;
use App\Services\ScheduleService;
use Illuminate\Console\Command;

class SendQueuedEmails extends Command
{
    protected $signature = 'orachart:send-emails';
    protected $description = 'Send queued reminder e-mails (ntf_email_log) that are due and 24h reschedule follow-ups';

    public function handle(ScheduleService $schedule): int
    {
        $due = Notifier::dispatchDue();
        $reminders = $schedule->sendSecondReminders();
        $this->info("{$due} queued e-mail(s) processed. {$reminders} 24h reschedule follow-up reminder(s) sent.");
        return self::SUCCESS;
    }
}
