<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capture the requester's office + department on a File Search Request so they can be
 * backfilled into the Create File Tracker form when the file is later logged.
 */
return new class extends Migration {
    public function up()
    {
        if (! Schema::connection('sqlsrv')->hasTable('file_search_requests')) {
            return;
        }

        if (! Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'requester_office')) {
            Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
                $table->string('requester_office', 255)->nullable();
            });
        }
        if (! Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'requester_department')) {
            Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
                $table->string('requester_department', 255)->nullable();
            });
        }
    }

    public function down()
    {
        foreach (['requester_office', 'requester_department'] as $col) {
            if (Schema::connection('sqlsrv')->hasColumn('file_search_requests', $col)) {
                Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
