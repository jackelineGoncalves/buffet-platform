<?php

namespace Database\Seeders;

use App\Models\Station;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stations = [
            ['code' => 'sushi', 'label' => 'Sushi', 'short_label' => 'SU', 'color' => '#2563eb'],
            ['code' => 'hot', 'label' => 'Hot Kitchen', 'short_label' => 'HOT', 'color' => '#dc2626'],
            ['code' => 'fry', 'label' => 'Fry', 'short_label' => 'FRY', 'color' => '#d97706'],
            ['code' => 'cold', 'label' => 'Cold', 'short_label' => 'COLD', 'color' => '#0891b2'],
            ['code' => 'bar', 'label' => 'Bar', 'short_label' => 'BAR', 'color' => '#7c3aed'],
        ];

        foreach ($stations as $station) {
            Station::create($station);
        }
    }
}
