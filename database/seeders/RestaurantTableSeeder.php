<?php

namespace Database\Seeders;

use App\Models\RestaurantTable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RestaurantTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tables = [
            ['code' => 'T1', 'seats' => 4],
            ['code' => 'T2', 'seats' => 2],
            ['code' => 'T3', 'seats' => 6],
        ];

        foreach ($tables as $table) {
            RestaurantTable::create($table);
        }
    }
}
