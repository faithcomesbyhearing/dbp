<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add deleted_at with microsecond precision
        Schema::connection('dbp')->table('organizations', function (Blueprint $table) {
            $table->timestamp('deleted_at', 6)->nullable()->default(null);
        });

        // 2. Add is_deleted generated column, drop old unique keys, add new composite unique keys
        DB::connection('dbp')->statement(
            'ALTER TABLE `organizations`
              ADD COLUMN `is_deleted` decimal(20, 0) GENERATED ALWAYS AS (IF(deleted_at IS NULL, 0, deleted_at * 1000000)) STORED,
              DROP KEY `organizations_slug_unique`,
              DROP KEY `organizations_abbreviation_unique`,
              ADD UNIQUE KEY `unique_slug_active` (`slug`, `is_deleted`),
              ADD UNIQUE KEY `unique_abbreviation_active` (`abbreviation`, `is_deleted`)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Re-add the old unique keys, drop new composite unique keys and is_deleted column
        DB::connection('dbp')->statement(
            'ALTER TABLE `organizations`
              DROP KEY `unique_slug_active`,
              DROP KEY `unique_abbreviation_active`,
              DROP COLUMN `is_deleted`,
              ADD UNIQUE KEY `organizations_slug_unique` (`slug`),
              ADD UNIQUE KEY `organizations_abbreviation_unique` (`abbreviation`)'
        );

        // 2. Drop deleted_at column
        Schema::connection('dbp')->table('organizations', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
