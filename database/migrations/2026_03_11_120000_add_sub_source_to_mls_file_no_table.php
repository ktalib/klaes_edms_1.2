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
        if (!Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'sub_source')) {
            Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $table) {
                $table->string('sub_source', 100)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'sub_source')) {
            Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $table) {
                $table->dropColumn('sub_source');
            });
        }
    }
};
