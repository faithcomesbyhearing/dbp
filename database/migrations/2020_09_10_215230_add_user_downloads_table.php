<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUserDownloadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::connection('dbp_users')->hasTable('user_downloads')) {
            Schema::connection('dbp_users')->create('user_downloads', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('user_id')->unsigned();
                $table->foreign('user_id', 'FK_users_user_downloads')->references('id')->on(config('database.connections.dbp_users.database') . '.users')->onUpdate('cascade');
                $table->string('bible_id', 12);
                $table->foreign('bible_id', 'FK_bibles_user_downloads')->references('id')->on(config('database.connections.dbp.database').'.bibles')->onUpdate('cascade')->onDelete('cascade');
                $table->char('book_id', 3);
                $table->foreign('book_id', 'FK_books_bible_books')->references('id')->on(config('database.connections.dbp.database').'.books');
                $table->tinyInteger('chapter')->unsigned();
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
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
        Schema::connection('dbp_users')->dropIfExists('user_downloads');
    }
}
