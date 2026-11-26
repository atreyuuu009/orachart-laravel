<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Run AFTER importing database/schema/orachart_schema_v1.sql:
 *     php artisan db:seed
 * Safe to re-run (idempotent).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([NtfTemplateSeeder::class, PaymentDiscountSeeder::class, DemoDataSeeder::class]);
        if (app()->environment('local')) {
            $this->call(AnalyticsDemoSeeder::class);
        }
    }
}
