<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSegmentationTypeToBibleFilesets extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('dbp')->table('bible_filesets', function (Blueprint $table) {
            $table->enum('segmentation_type', ['section', 'chapter'])
                ->nullable()
                ->default(null)
                ->after('archived');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('dbp')->table('bible_filesets', function (Blueprint $table) {
            $table->dropColumn('segmentation_type');
        });
    }
}
