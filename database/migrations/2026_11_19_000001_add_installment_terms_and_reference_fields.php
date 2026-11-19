<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $planColumns = [
            'interest_rate_percent' => fn (Blueprint $t) => $t->decimal('interest_rate_percent', 5, 2)->default(0)->after('discount_amount'),
            'interest_amount' => fn (Blueprint $t) => $t->decimal('interest_amount', 10, 2)->default(0)->after('interest_rate_percent'),
            'first_due_date' => fn (Blueprint $t) => $t->date('first_due_date')->nullable()->after('number_of_installments'),
            'terms_note' => fn (Blueprint $t) => $t->string('terms_note', 1000)->nullable()->after('first_due_date'),
            'accepted_at' => fn (Blueprint $t) => $t->dateTime('accepted_at')->nullable()->after('reviewed_at'),
        ];
        foreach ($planColumns as $name => $define) if (! Schema::hasColumn('pay_installment_plans', $name)) Schema::table('pay_installment_plans', $define);
        if (! Schema::hasColumn('pay_installment_payments', 'reference_number')) Schema::table('pay_installment_payments', fn (Blueprint $table) => $table->string('reference_number', 100)->nullable()->after('proof_uploaded_at'));
    }

    public function down(): void
    {
        Schema::table('pay_installment_payments', fn (Blueprint $table) => $table->dropColumn('reference_number'));
        Schema::table('pay_installment_plans', fn (Blueprint $table) => $table->dropColumn(['interest_rate_percent', 'interest_amount', 'first_due_date', 'terms_note', 'accepted_at']));
    }
};
