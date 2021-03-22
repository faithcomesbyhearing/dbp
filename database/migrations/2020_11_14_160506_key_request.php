<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class KeyRequest extends Migration
{
    /*
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $schema = Schema::connection('dbp_users');
        if ($schema->hasTable('user_keys')) {
            $schema->table('user_keys', function (Blueprint $table) {
                $table
                    ->integer('user_id')
                    ->unsigned()
                    ->nullable()
                    ->change();
            });
        }
        if (!$schema->hasTable('user_key_requests')) {
            $schema->create('user_key_requests', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('key_id')->nullable();
                $table
                    ->foreign('key_id', 'FK_user_keys_user_key_requests')
                    ->references('id')
                    ->on(config('database.connections.dbp_users.database') . '.user_keys')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
                $table->string('name');
                $table->string('email');
                $table->text('description')->nullable();
                $table->text('questions')->nullable();
                $table->string('temporary_key', 64)->unique();
                $table->text('notes')->nullable();
                $table->integer('state')->default(1);
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table
                    ->timestamp('updated_at')
                    ->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
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
        $schema = Schema::connection('dbp_users');
        if ($schema->hasTable('user_keys')) {
            Schema::disableForeignKeyConstraints();
            $schema->table('user_keys', function (Blueprint $table) {
                $table
                    ->integer('user_id')
                    ->unsigned()
                    ->nullable(false)
                    ->change();
            });
            Schema::enableForeignKeyConstraints();
        }
        if ($schema->hasTable('user_key_requests')) {
            $schema->drop('user_key_requests');
        }
    }
}
