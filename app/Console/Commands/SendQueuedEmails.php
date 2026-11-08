<?php

namespace App\Console\Commands;

use App\Services\Notifier;
use Illuminate\Console\Command;

class SendQueuedEmails extends Command
{
    protected $signature = 'orachart:send-emails';
    protected $description = 'Send queued reminder e-mails (ntf_email_log) that are due';

    public function handle(): int
    {
        $this->info(Notifier::dispatchDue().' e-mail(s) processed.');
        return self::SUCCESS;
    }
}
