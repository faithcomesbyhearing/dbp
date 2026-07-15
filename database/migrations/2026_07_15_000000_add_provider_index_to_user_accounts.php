<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProviderIndexToUserAccounts extends Migration
{
    /**
     * Run the migrations.
     *
     * The social login lookups (UsersController::loginWithSocialProvider and
     * AccountValidator) filter user_accounts by provider_user_id + provider_id,
     * but the table only has indexes leading with user_id or project_id, so
     * every lookup was a full table scan.
     *
     * @return void
     */
    public function up()
    {
        // Deploys apply this index with plain SQL; skip when it already exists so
        // running migrations stays safe in environments prepared either way.
        $schema = Schema::connection('dbp_users');
        if (
            $schema->hasIndex(
                'user_accounts',
                'user_accounts_provider_user_id_provider_id_index'
            )
        ) {
            return;
        }
        $schema->table('user_accounts', function (Blueprint $table) {
            $table->index(
                ['provider_user_id', 'provider_id'],
                'user_accounts_provider_user_id_provider_id_index'
            );
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
        if (
            !$schema->hasIndex(
                'user_accounts',
                'user_accounts_provider_user_id_provider_id_index'
            )
        ) {
            return;
        }
        $schema->table('user_accounts', function (Blueprint $table) {
            $table->dropIndex(
                'user_accounts_provider_user_id_provider_id_index'
            );
        });
    }
}
