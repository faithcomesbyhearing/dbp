<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLanguageCodesV2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!\Schema::connection('dbp')->hasTable('language_codes_v2')) {
            \Schema::create('language_codes_v2', function (Blueprint $table) {
                $table->string('id', 3);
                $table->string('language_ISO_639_3_id');
                $table->string('family_id');
                $table->string('name');
                $table->string('english_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('language_codes_v2');
    }
}
