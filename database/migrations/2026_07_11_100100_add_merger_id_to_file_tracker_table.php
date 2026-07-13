<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stamp the file_merger group id onto file_tracker so a tracked file that belongs to a
 * parcel-update group can be resolved to its whole lineage (all related file numbers)
 * when building the combined File Movement History. Denormalised for quick lookup; the
 * read path can still rebuild the group from file_merger if this is unset.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('file_tracker', 'merger_id')) {
            return;
        }

        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            $table->string('merger_id', 64)->nullable()->after('tracking_id');
            $table->index('merger_id');
        });
    }

    public function down(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('file_tracker', 'merger_id')) {
            Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
                $table->dropIndex(['merger_id']);
                $table->dropColumn('merger_id');
            });
        }
    }
};
