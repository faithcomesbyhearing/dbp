<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $table_name = 'languages';

        Schema::connection('dbp')->table($table_name, function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table_name = 'languages';
        Schema::connection('dbp')->table($table_name, function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
