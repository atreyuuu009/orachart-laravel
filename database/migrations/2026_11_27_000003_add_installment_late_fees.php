<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pay_installment_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('pay_installment_payments', 'late_fee_amount')) {
                $table->decimal('late_fee_amount', 10, 2)->default(0)->after('amount_paid');
            }
            if (! Schema::hasColumn('pay_installment_payments', 'late_fee_applied_at')) {
                $table->dateTime('late_fee_applied_at')->nullable()->after('late_fee_amount');
            }
        });

        foreach ([
            'installment_late_fee_type' => ['fixed', 'Late fee type: fixed pesos or percent'],
            'installment_late_fee_amount' => ['0', 'Installment late fee amount or percent'],
        ] as $key => [$value, $description]) {
            DB::table('sys_settings')->updateOrInsert(['setting_key' => $key], ['setting_value' => $value, 'description' => $description]);
        }
    }

    public function down(): void
    {
        Schema::table('pay_installment_payments', function (Blueprint $table) {
            $columns = array_values(array_filter(['late_fee_amount', 'late_fee_applied_at'], fn ($column) => Schema::hasColumn('pay_installment_payments', $column)));
            if ($columns) $table->dropColumn($columns);
        });
        DB::table('sys_settings')->whereIn('setting_key', ['installment_late_fee_type', 'installment_late_fee_amount'])->delete();
    }
};
