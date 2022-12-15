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
        DB::connection('dbp')
            ->statement('UPDATE bible_fileset_tags SET admin_only = 1 WHERE name ="stock_no"');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::connection('dbp')
            ->statement('UPDATE bible_fileset_tags SET admin_only = 0 WHERE name ="stock_no"');
    }
};
