<?php

use Illuminate\Support\Facades\Schedule;

// Reminders, thank-you and recall e-mails wait in ntf_email_log until due.
// Production cron:  * * * * * cd /path/to/orachart && php artisan schedule:run >> /dev/null 2>&1
// Local dev:        php artisan schedule:work
Schedule::command('orachart:send-emails')->everyMinute()->withoutOverlapping();
Schedule::command('orachart:installments-daily')->dailyAt('08:00')->withoutOverlapping();
