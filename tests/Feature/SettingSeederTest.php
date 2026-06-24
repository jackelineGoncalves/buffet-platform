<?php

namespace Tests\Feature;

use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_a_single_settings_row_with_defaults(): void
    {
        $this->seed(SettingSeeder::class);

        $this->assertDatabaseCount('settings', 1);
        $this->assertDatabaseHas('settings', [
            'buffet_price' => 32.00,
            'waste_fee' => 6.00,
            'session_minutes' => 120,
            'last_call_minutes' => 20,
            'tax_rate' => 0.0875,
        ]);
    }
}
