<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'source_pra_id')) {
            Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $table) {
                $table->unsignedBigInteger('source_pra_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'source_pra_id')) {
            Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $table) {
                $table->dropColumn('source_pra_id');
            });
        }
    }
};
