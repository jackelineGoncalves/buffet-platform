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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dining_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('guests');
            $table->decimal('buffet_total', 8, 2);
            $table->decimal('extras_total', 8, 2);
            $table->decimal('waste_total', 8, 2);
            $table->decimal('tax', 8, 2);
            $table->decimal('total', 8, 2);
            $table->timestamp('paid_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
