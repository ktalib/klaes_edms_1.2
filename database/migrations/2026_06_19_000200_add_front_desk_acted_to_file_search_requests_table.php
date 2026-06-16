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
            // Set when the Front Desk acts on the SCB's response (logs the file or refers).
            // While null + SCB has responded → the request sits in the SCB Feedback queue.
            if (! Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'front_desk_acted_at')) {
                $table->dateTime('front_desk_acted_at')->nullable();
            }
            if (! Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'front_desk_acted_by')) {
                $table->unsignedBigInteger('front_desk_acted_by')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
            foreach (['front_desk_acted_at', 'front_desk_acted_by'] as $col) {
                if (Schema::connection('sqlsrv')->hasColumn('file_search_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
