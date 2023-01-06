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
        $table_name = 'bible_files';

        Schema::connection('dbp')->table($table_name, function (Blueprint $table) {
            $table->integer('verse_sequence')->unsigned()->nullable();
        });
        \DB::connection('dbp')->statement("UPDATE $table_name SET verse_sequence = verse_start");
        \DB::connection('dbp')->statement(
            "ALTER TABLE $table_name modify column verse_start char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $table_name = 'bible_files';

        Schema::connection('dbp')->table($table_name, function (Blueprint $table) {
            $table->dropColumn('verse_sequence');
        });

        \DB::connection('dbp')->statement(
            "ALTER TABLE $table_name modify column verse_start tinyint unsigned DEFAULT NULL"
        );
    }
};
