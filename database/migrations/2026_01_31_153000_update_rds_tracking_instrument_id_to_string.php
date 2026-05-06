<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw SQL for SQL Server to modify the column type
        // Explicitly use sqlsrv connection
        // Drop indexes first because they depend on the column
        try {
            DB::connection('sqlsrv')->statement("DROP INDEX idx_rds_composite ON rds_tracking");
        } catch (\Exception $e) {
        } // Ignore if not exists

        try {
            DB::connection('sqlsrv')->statement("DROP INDEX rds_tracking_instrument_id_index ON rds_tracking");
        } catch (\Exception $e) {
        } // Ignore if not exists

        DB::connection('sqlsrv')->statement("ALTER TABLE rds_tracking ALTER COLUMN instrument_id NVARCHAR(255)");

        // Recreate the main index
        DB::connection('sqlsrv')->statement("CREATE INDEX rds_tracking_instrument_id_index ON rds_tracking(instrument_id)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to bigint
        // WARNING: This will fail if there are non-numeric values (like 'deed_reg_25') in the column
        // treating this as a one-way migration for now relative to the new feature

        // We attempt to delete non-numeric records before reverting to avoid crash if rollback is needed
        DB::table('rds_tracking')->where('instrument_id', 'like', 'deed_reg_%')->delete();

        DB::statement("ALTER TABLE rds_tracking ALTER COLUMN instrument_id BIGINT");
    }
};
