<?php
namespace App\Console\Commands;
use App\Services\InstallmentService;
use Illuminate\Console\Command;
class InstallmentDaily extends Command
{
    protected $signature='orachart:installments-daily';
    protected $description='Flag overdue installment months and send due reminders';
    public function handle(InstallmentService $service): int { $service->markOverdue(); $service->sendDueReminders(); $this->info('Installment statuses and reminders processed.'); return self::SUCCESS; }
}
