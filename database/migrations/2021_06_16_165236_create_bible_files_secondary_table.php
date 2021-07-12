<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBibleFilesSecondaryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!\Schema::connection('dbp')->hasTable('bible_files_secondary')) {
            \Schema::connection('dbp')->create('bible_files_secondary', function (Blueprint $table) {
                $table->string('hash_id', 16);
                $table->foreign('hash_id', 'FK_bible_filesets_bible_files_secondary')->references('hash_id')->on(config('database.connections.dbp.database').'.bible_filesets')->onUpdate('cascade')->onDelete('cascade');
                $table->string('file_name');
                $table->string('file_type');
                $table->unique(['hash_id'], 'unique_bible_file_secondary_by_reference');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
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
        Schema::dropIfExists('bible_files_secondary');
    }
}
