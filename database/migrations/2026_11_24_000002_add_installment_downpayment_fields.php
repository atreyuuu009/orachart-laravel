<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pay_installment_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('pay_installment_plans', 'downpayment_percent')) {
                $table->decimal('downpayment_percent', 5, 2)->default(0)->after('interest_amount');
            }
            if (! Schema::hasColumn('pay_installment_plans', 'downpayment_amount')) {
                $table->decimal('downpayment_amount', 10, 2)->default(0)->after('downpayment_percent');
            }
            if (! Schema::hasColumn('pay_installment_plans', 'downpayment_payment_id')) {
                $table->unsignedInteger('downpayment_payment_id')->nullable()->after('downpayment_amount');
                $table->foreign('downpayment_payment_id')->references('payment_id')->on('pay_payments');
            }
        });

        DB::table('sys_settings')->updateOrInsert(
            ['setting_key' => 'installment_min_amount'],
            ['setting_value' => '10000', 'description' => 'Minimum service price eligible for installments']
        );
    }

    public function down(): void
    {
        if (! Schema::hasColumn('pay_installment_plans', 'downpayment_payment_id')) return;

        Schema::table('pay_installment_plans', function (Blueprint $table) {
            $table->dropForeign(['downpayment_payment_id']);
            $table->dropColumn(['downpayment_percent', 'downpayment_amount', 'downpayment_payment_id']);
        });
    }
};
