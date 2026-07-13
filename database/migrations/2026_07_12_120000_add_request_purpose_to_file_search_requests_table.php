<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capture Request Purpose (+ its default turnaround, denormalised as id + name like
 * file_tracker.request_purpose_*) and Expected Return Date on the File Search Request
 * itself, raised from Quick Search / mobile File Search. Carried through to
 * file_tracker.request_purpose_id / deadline once the file is logged, so front desk
 * never has to re-enter them.
 */
return new class extends Migration {
    public function up()
    {
        if (! Schema::connection('sqlsrv')->hasTable('file_search_requests')) {
            return;
        }

        if (! Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'request_purpose_id')) {
            Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('request_purpose_id')->nullable();
            });
        }
        if (! Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'request_purpose_name')) {
            Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
                $table->string('request_purpose_name', 255)->nullable();
            });
        }
        if (! Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'expected_return_date')) {
            Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
                $table->date('expected_return_date')->nullable();
            });
        }
    }

    public function down()
    {
        foreach (['request_purpose_id', 'request_purpose_name', 'expected_return_date'] as $col) {
            if (Schema::connection('sqlsrv')->hasColumn('file_search_requests', $col)) {
                Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
