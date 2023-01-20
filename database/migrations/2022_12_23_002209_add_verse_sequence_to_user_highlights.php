<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $table_name = 'user_highlights';

        Schema::connection('dbp_users')->table($table_name, function (Blueprint $table) {
            $table->integer('verse_sequence')->unsigned()->nullable(false);
        });
        \DB::connection('dbp_users')->statement("UPDATE $table_name SET verse_sequence = verse_start");
        \DB::connection('dbp_users')->statement(
            "ALTER TABLE $table_name modify column verse_start char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL"
        );
        \DB::connection('dbp_users')->statement(
            "ALTER TABLE $table_name modify column verse_end char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $table_name = 'user_highlights';

        Schema::connection('dbp_users')->table($table_name, function (Blueprint $table) {
            $table->dropColumn('verse_sequence');
        });

        \DB::connection('dbp_users')->statement(
            "ALTER TABLE $table_name modify column verse_start tinyint unsigned NOT NULL"
        );

        \DB::connection('dbp_users')->statement(
            "ALTER TABLE $table_name modify column verse_end tinyint unsigned NOT NULL"
        );
    }
};
