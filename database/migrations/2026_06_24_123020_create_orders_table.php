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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dining_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->unsignedInteger('round');
            $table->enum('status', ['new', 'prep', 'ready', 'served'])->default('new');
            $table->timestamp('placed_at');
            $table->timestamps();

            $table->unique(['dining_session_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
