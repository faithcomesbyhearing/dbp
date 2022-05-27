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
        $schema = Schema::connection('dbp');
        $schema->table('languages', function (Blueprint $table) {
            $table->string('rolv_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $schema = Schema::connection('dbp');
        $schema->table('languages', function (Blueprint $table) {
            $table->dropColumn('rolv_code');
        });
    }
};
