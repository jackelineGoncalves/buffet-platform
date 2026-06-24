<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'admin',
        ]);

        $this->call(StationSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(TagSeeder::class);
        $this->call(AllergenSeeder::class);
        $this->call(SettingSeeder::class);
    }
}
