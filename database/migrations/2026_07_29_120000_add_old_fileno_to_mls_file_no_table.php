<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Re-Issuance of FileNo: keeps the old (duplicated) file number the new
     * number was issued to replace.
     */
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'old_fileno')) {
            Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $table) {
                $table->string('old_fileno', 100)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'old_fileno')) {
            Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $table) {
                $table->dropColumn('old_fileno');
            });
        }
    }
};
