<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::create([
            'buffet_price' => 32.00,
            'waste_fee' => 6.00,
            'session_minutes' => 120,
            'last_call_minutes' => 20,
            'tax_rate' => 0.0875,
        ]);
    }
}
