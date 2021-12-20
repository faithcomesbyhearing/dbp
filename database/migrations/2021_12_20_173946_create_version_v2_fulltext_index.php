<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVersionV2FulltextIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (\Schema::connection('dbp')->hasTable('version')) {
            DB::connection('dbp')
                ->statement('ALTER TABLE version ADD FULLTEXT ft_index_version_name_english_name(name, english_name)');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ft_index_version_name_english_name');
    }
}
