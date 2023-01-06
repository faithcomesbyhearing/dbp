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
        $table_name = 'bible_verses';

        Schema::connection('dbp')->table($table_name, function (Blueprint $table) {
            $table->integer('verse_sequence')->unsigned()->nullable(false);
        });
        \DB::connection('dbp')->statement("UPDATE $table_name SET verse_sequence = verse_start");
        \DB::connection('dbp')->statement("ALTER TABLE $table_name DROP KEY unique_text_reference");
        \DB::connection('dbp')->statement(
            "ALTER TABLE $table_name modify column verse_start char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL"
        );
        \DB::connection('dbp')->statement(
            "ALTER TABLE $table_name ADD UNIQUE KEY unique_text_reference (hash_id, book_id, chapter, verse_start)"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $table_name = 'bible_verses';

        Schema::connection('dbp')->table($table_name, function (Blueprint $table) {
            $table->dropColumn('verse_sequence');
        });

        \DB::connection('dbp')->statement("ALTER TABLE $table_name DROP KEY unique_text_reference");
        \DB::connection('dbp')->statement(
            "ALTER TABLE $table_name modify column verse_start tinyint unsigned NOT NULL"
        );
        \DB::connection('dbp')->statement(
            "ALTER TABLE $table_name ADD UNIQUE KEY unique_text_reference (hash_id, book_id, chapter, verse_start)"
        );
    }
};
