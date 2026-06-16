<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('phs_members', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('phs_members', 'phone')) {
                $table->string('phone', 50)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('phs_members', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('phs_members', 'phone')) {
                $table->dropColumn('phone');
            }
        });
    }
};
