<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Office Priority Search (OFS) flags on a File Search Request.
     *
     * A request is an OFS request when the user who raised it is a ranked officer
     * (users.rank matches config/file_request_priority.php). These requests get a
     * dedicated colour in the SCB Feedback table and float to the top of the SCB
     * Monitor's mobile File Requests list.
     */
    public function up()
    {
        if (! Schema::connection('sqlsrv')->hasTable('file_search_requests')) {
            return;
        }

        Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
            if (! Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'is_ofs')) {
                $table->boolean('is_ofs')->default(false);
            }
            // The matched rank title (e.g. "Director Deeds") for display/colour tiering.
            if (! Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'ofs_rank')) {
                $table->string('ofs_rank', 255)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
            foreach (['is_ofs', 'ofs_rank'] as $col) {
                if (Schema::connection('sqlsrv')->hasColumn('file_search_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
