<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an SCB Monitor marks a File Search Request as Not Found, they now pick
 * the kind of "not found": MISSING (file genuinely cannot be located) or
 * PENDING (search not yet concluded / file expected to turn up). Stored here so
 * the FSR History and downstream reports can distinguish the two.
 */
return new class extends Migration {
    public function up()
    {
        if (! Schema::connection('sqlsrv')->hasTable('file_search_requests')) {
            return;
        }

        if (! Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'not_found_type')) {
            Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
                $table->string('not_found_type', 20)->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'not_found_type')) {
            Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
                $table->dropColumn('not_found_type');
            });
        }
    }
};
