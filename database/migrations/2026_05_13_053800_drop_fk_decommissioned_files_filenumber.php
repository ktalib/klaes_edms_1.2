<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Drop the FK from decommissioned_files to fileNumber.
     * 
     * This FK created a circular dependency: the archive table referenced the
     * active table, preventing deletion of the very records being archived.
     * The constraint was dropped manually and this migration documents it.
     */
    public function up()
    {
        try {
            DB::connection('sqlsrv')->statement(
                'ALTER TABLE decommissioned_files DROP CONSTRAINT FK_decommissioned_files_fileNumber'
            );
        } catch (\Exception $e) {
            // Already dropped — safe to ignore
        }
    }

    public function down()
    {
        // Intentionally not restoring — the FK was architecturally wrong
    }
};
