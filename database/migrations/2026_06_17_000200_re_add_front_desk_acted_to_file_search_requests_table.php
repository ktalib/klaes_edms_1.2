<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The original 2026_06_19_000200 migration was recorded as run but did not actually
 * add the columns on some environments (SQL Server). Re-add them idempotently so the
 * SCB Feedback queue (which filters on front_desk_acted_at) works.
 */
return new class extends Migration {
    public function up()
    {
        if (! Schema::connection('sqlsrv')->hasTable('file_search_requests')) {
            return;
        }

        if (! Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'front_desk_acted_at')) {
            Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
                $table->dateTime('front_desk_acted_at')->nullable();
            });
        }

        if (! Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'front_desk_acted_by')) {
            Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('front_desk_acted_by')->nullable();
            });
        }
    }

    public function down()
    {
        foreach (['front_desk_acted_at', 'front_desk_acted_by'] as $col) {
            if (Schema::connection('sqlsrv')->hasColumn('file_search_requests', $col)) {
                Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
