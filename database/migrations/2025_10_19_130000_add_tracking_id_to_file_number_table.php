<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('fileNumber', 'tracking_id')) {
            Schema::connection('sqlsrv')->table('fileNumber', function (Blueprint $table) {
                $table->string('tracking_id', 50)->nullable()->after('location');
                $table->index('tracking_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'tracking_id')) {
            Schema::connection('sqlsrv')->table('fileNumber', function (Blueprint $table) {
                $table->dropColumn('tracking_id');
            });
        }
    }
};
