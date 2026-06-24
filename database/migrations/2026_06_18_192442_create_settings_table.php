<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('buffet_price', 8, 2);
            $table->decimal('waste_fee', 8, 2);
            $table->unsignedInteger('session_minutes');
            $table->unsignedInteger('last_call_minutes');
            $table->decimal('tax_rate', 5, 4);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
