<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'dining_sessions_one_active_per_table';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ($this->supportsPartialIndexes()) {
            DB::statement(
                'create unique index '.self::INDEX_NAME.
                " on dining_sessions (restaurant_table_id) where status != 'closed'"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->supportsPartialIndexes()) {
            DB::statement('drop index '.self::INDEX_NAME);
        }
    }

    private function supportsPartialIndexes(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['sqlite', 'pgsql'], true);
    }
};
