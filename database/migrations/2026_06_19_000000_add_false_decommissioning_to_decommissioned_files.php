<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        $exists = DB::connection('sqlsrv')->selectOne(
            "SELECT 1 AS found FROM sys.columns WHERE object_id = OBJECT_ID('decommissioned_files') AND name = ?",
            ['false_decommissioning']
        );

        if (!$exists) {
            // 1 = recorded via a Title Status update from File Indexing (the file is NOT
            // actually decommissioned); 0 = a real decommissioning.
            DB::connection('sqlsrv')->statement(
                "ALTER TABLE decommissioned_files ADD false_decommissioning TINYINT NOT NULL DEFAULT 0"
            );
        }
    }

    public function down(): void
    {
        $exists = DB::connection('sqlsrv')->selectOne(
            "SELECT 1 AS found FROM sys.columns WHERE object_id = OBJECT_ID('decommissioned_files') AND name = ?",
            ['false_decommissioning']
        );

        if ($exists) {
            // Drop the default constraint first (SQL Server names it implicitly)
            DB::connection('sqlsrv')->statement(
                "DECLARE @df NVARCHAR(255);
                 SELECT @df = dc.name FROM sys.default_constraints dc
                 JOIN sys.columns c ON c.default_object_id = dc.object_id
                 WHERE dc.parent_object_id = OBJECT_ID('decommissioned_files') AND c.name = 'false_decommissioning';
                 IF @df IS NOT NULL EXEC('ALTER TABLE decommissioned_files DROP CONSTRAINT ' + @df);
                 ALTER TABLE decommissioned_files DROP COLUMN false_decommissioning"
            );
        }
    }
};
