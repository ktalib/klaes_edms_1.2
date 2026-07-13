<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stamp the Request Purpose (denormalised id + name, same convention as
 * origin_office_code/origin_office_name) onto file_tracker so the create form,
 * File Tracker Log listing and printed File Tracking Sheet can all show why the
 * file was requested without joining request_purposes on every read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('file_tracker', 'request_purpose_id')) {
                $table->unsignedBigInteger('request_purpose_id')->nullable()->after('destination');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('file_tracker', 'request_purpose_name')) {
                $table->string('request_purpose_name', 255)->nullable()->after('request_purpose_id');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('file_tracker', 'request_purpose_name')) {
                $table->dropColumn('request_purpose_name');
            }
            if (Schema::connection('sqlsrv')->hasColumn('file_tracker', 'request_purpose_id')) {
                $table->dropColumn('request_purpose_id');
            }
        });
    }
};
