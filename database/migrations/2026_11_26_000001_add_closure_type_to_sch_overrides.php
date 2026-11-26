<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('sch_overrides', 'closure_type')) {
            Schema::table('sch_overrides', function (Blueprint $table) {
                $table->enum('closure_type', ['planned', 'emergency'])
                    ->default('planned')
                    ->after('reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sch_overrides', 'closure_type')) {
            Schema::table('sch_overrides', function (Blueprint $table) {
                $table->dropColumn('closure_type');
            });
        }
    }
};
