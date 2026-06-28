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
        Schema::connection('sqlsrv')->table('legal_search_online_feedback', function (Blueprint $table) {
            $table->unsignedBigInteger('online_ls_user_id')->nullable()->after('user_id');
            $table->index('online_ls_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('legal_search_online_feedback', function (Blueprint $table) {
            $table->dropColumn('online_ls_user_id');
        });
    }
};
