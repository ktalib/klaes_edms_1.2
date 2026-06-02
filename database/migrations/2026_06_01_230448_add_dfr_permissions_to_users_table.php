<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)->table('users', function (Blueprint $table) {
            // Comma-separated DFR sub-permissions: view_requests, make_request, approve_request
            $table->string('dfr_permissions')->nullable()->after('user_actions');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('users', function (Blueprint $table) {
            $table->dropColumn('dfr_permissions');
        });
    }
};
