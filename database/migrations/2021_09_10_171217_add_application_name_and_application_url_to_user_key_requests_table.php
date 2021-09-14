<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApplicationNameAndApplicationUrlToUserKeyRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $schema = Schema::connection('dbp_users');
        $schema->table('user_key_requests', function (Blueprint $table) {
            $table->string('application_name');
            $table->text('application_url');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $schema = Schema::connection('dbp_users');
        $schema->table('user_key_requests', function (Blueprint $table) {
            $table->dropColumn('application_name');
            $table->dropColumn('application_url');
        });
    }
}
