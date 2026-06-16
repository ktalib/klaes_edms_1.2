<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Adds the Quick Search & File Location snapshot columns. These cache the
     * resolved location for each indexed file so the Quick Search result and
     * reporting stay fast; the resolver always recomputes live and writes
     * through to these columns.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'tracking_status')) {
                $table->string('tracking_status', 50)->nullable()->after('general_registry');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'current_location')) {
                $table->string('current_location', 255)->nullable()->after('tracking_status');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'file_tracker_id')) {
                $table->unsignedBigInteger('file_tracker_id')->nullable()->after('current_location');
                $table->index('file_tracker_id', 'file_indexings_file_tracker_id_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'file_tracker_id')) {
                $table->dropIndex('file_indexings_file_tracker_id_idx');
                $table->dropColumn('file_tracker_id');
            }
            if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'current_location')) {
                $table->dropColumn('current_location');
            }
            if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'tracking_status')) {
                $table->dropColumn('tracking_status');
            }
        });
    }
};
