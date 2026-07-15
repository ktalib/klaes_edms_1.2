<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How the file request reached the registry: MANUAL (walk-in paper request
 * sheet) or SUBMITTED (request submitted through the system). Updatable from
 * the File Log Table's "Update Request Sheet" action so registry staff can
 * reclassify a tracker after the fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('file_tracker', 'file_request_type')) {
                $table->string('file_request_type', 20)->nullable()->after('request_purpose_name');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('file_tracker', 'file_request_type')) {
                $table->dropColumn('file_request_type');
            }
        });
    }
};
