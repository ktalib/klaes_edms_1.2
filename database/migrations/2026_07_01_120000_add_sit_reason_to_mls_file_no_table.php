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
        if (!Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'sit_reason')) {
            Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $table) {
                $table->text('sit_reason')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'sit_reason')) {
            Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $table) {
                $table->dropColumn('sit_reason');
            });
        }
    }
};
