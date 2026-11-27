<?php
namespace App\Console\Commands;
use App\Services\InstallmentService;
use Illuminate\Console\Command;
class InstallmentDaily extends Command
{
    protected $signature='orachart:installments-daily';
    protected $description='Apply overdue status after two grace days, charge configured fees after seven days, and send installment reminders';
    public function handle(InstallmentService $service): int { $service->markOverdue(); $service->sendDueReminders(); $this->info('Installment statuses and reminders processed.'); return self::SUCCESS; }
}
