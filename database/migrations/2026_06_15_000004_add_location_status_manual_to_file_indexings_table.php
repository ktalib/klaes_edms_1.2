<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Marks a file_indexings row whose tracking_status was set MANUALLY from the
     * File Quick Search interface. When set, the FileLocationResolver treats the
     * stored status as the source of truth (manual override) instead of
     * re-deriving it from trackers/ranges.
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'location_status_manual')) {
                $table->dateTime('location_status_manual')->nullable()->after('file_tracker_id');
            }
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'location_status_manual')) {
                $table->dropColumn('location_status_manual');
            }
        });
    }
};
