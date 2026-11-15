<?php

namespace Database\Seeders;

use App\Models\Discount;
use Illuminate\Database\Seeder;

class PaymentDiscountSeeder extends Seeder
{
    public function run(): void
    {
        // Keep the preset for historical references, but hide it from new bills.
        Discount::where('discount_name', 'RCT Restoration Promo')->update(['is_active' => 0]);
    }
}
