<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (! Schema::connection('sqlsrv')->hasTable('file_search_requests')) {
            return;
        }

        Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
            // Requester seniority weight (higher = honored first at the log step).
            // Derived from the DFR Receiving Officer via config/file_request_priority.php.
            if (! Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'priority')) {
                $table->smallInteger('priority')->default(0);
            }
            // The senior person the file is being requested FOR (copied from the DFR),
            // so the log step can surface and honor them.
            if (! Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'receiving_officer')) {
                $table->string('receiving_officer', 255)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
            foreach (['priority', 'receiving_officer'] as $col) {
                if (Schema::connection('sqlsrv')->hasColumn('file_search_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
