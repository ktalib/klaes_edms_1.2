<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = 'sqlsrv';

        // Add columns to file_indexings table
        if (!Schema::connection($connection)->hasColumn('file_indexings', 'has_temp_file')) {
            Schema::connection($connection)->table('file_indexings', function (Blueprint $table) {
                $table->tinyInteger('has_temp_file')->default(0)->after('has_rofo');
            });
        }

        if (!Schema::connection($connection)->hasColumn('file_indexings', 'temp_file_no')) {
            Schema::connection($connection)->table('file_indexings', function (Blueprint $table) {
                $table->string('temp_file_no', 100)->nullable()->after('has_temp_file');
            });
        }

        // Add columns to fileNumber table
        if (!Schema::connection($connection)->hasColumn('fileNumber', 'has_temp_file')) {
            Schema::connection($connection)->table('fileNumber', function (Blueprint $table) {
                $table->tinyInteger('has_temp_file')->default(0);
            });
        }

        if (!Schema::connection($connection)->hasColumn('fileNumber', 'temp_file_no')) {
            Schema::connection($connection)->table('fileNumber', function (Blueprint $table) {
                $table->string('temp_file_no', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        $connection = 'sqlsrv';

        Schema::connection($connection)->table('file_indexings', function (Blueprint $table) {
            $table->dropColumn(['has_temp_file', 'temp_file_no']);
        });

        Schema::connection($connection)->table('fileNumber', function (Blueprint $table) {
            $table->dropColumn(['has_temp_file', 'temp_file_no']);
        });
    }
};
