<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up()
    {
        // The column is applied directly on live databases, so guard the migration
        // to keep it re-runnable.
        if (Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'old_file_number')) {
            return;
        }

        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            // Parent/previous file number captured for application types that derive
            // from an existing file: Plot Subdivision, Plot Merger, Change of Purpose.
            $table->string('old_file_number', 100)->nullable()->after('file_number');
        });
    }

    public function down()
    {
        if (!Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'old_file_number')) {
            return;
        }

        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->dropColumn('old_file_number');
        });
    }
};
