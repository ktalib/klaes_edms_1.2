<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'has_temp_file')) {
                $table->boolean('has_temp_file')->default(false)->after('has_rofo');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'temp_file_no')) {
                $table->string('temp_file_no', 255)->nullable()->after('has_temp_file');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            $cols = [];
            if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'has_temp_file')) {
                $cols[] = 'has_temp_file';
            }
            if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'temp_file_no')) {
                $cols[] = 'temp_file_no';
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
