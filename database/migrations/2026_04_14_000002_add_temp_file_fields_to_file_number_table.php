<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('fileNumber', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('fileNumber', 'has_temp_file')) {
                $table->boolean('has_temp_file')->default(false);
            }
            if (!Schema::connection('sqlsrv')->hasColumn('fileNumber', 'temp_file_no')) {
                $table->string('temp_file_no', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('fileNumber', function (Blueprint $table) {
            $cols = [];
            if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'has_temp_file')) {
                $cols[] = 'has_temp_file';
            }
            if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'temp_file_no')) {
                $cols[] = 'temp_file_no';
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
