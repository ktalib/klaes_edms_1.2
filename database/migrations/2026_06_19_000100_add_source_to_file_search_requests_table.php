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
        if (Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'source')) {
            return;
        }

        Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
            // Where the FSR originated: QUICK_SEARCH (default) or DFR (raised alongside
            // a Digital File Request so the SCB also searches the file).
            $table->string('source', 20)->nullable()->default('QUICK_SEARCH');
        });
    }

    public function down()
    {
        if (Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'source')) {
            Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }
    }
};
