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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dish_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->decimal('unit_price', 8, 2)->nullable();
            $table->unsignedInteger('qty');
            $table->enum('status', ['firing', 'ready', 'served'])->default('firing');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index(['status', 'station_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
