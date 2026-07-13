<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('users', 'deputy_redirect_notes')) {
                $table->dropColumn('deputy_redirect_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('users', 'deputy_redirect_notes')) {
                $table->string('deputy_redirect_notes', 1000)->nullable()->after('deputy_user_id');
            }
        });
    }
};
