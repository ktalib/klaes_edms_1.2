<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        $checks = [
            'kangis_file_type'  => "ALTER TABLE file_indexings ADD kangis_file_type VARCHAR(20) NULL",
            'mls_file_no'       => "ALTER TABLE file_indexings ADD mls_file_no VARCHAR(100) NULL",
            'kangis_file_no'    => "ALTER TABLE file_indexings ADD kangis_file_no VARCHAR(100) NULL",
            'new_kangis_file_no'=> "ALTER TABLE file_indexings ADD new_kangis_file_no VARCHAR(100) NULL",
            'kn_grouping_id'    => "ALTER TABLE file_indexings ADD kn_grouping_id BIGINT NULL",
        ];

        foreach ($checks as $column => $sql) {
            $exists = DB::connection('sqlsrv')->selectOne(
                "SELECT 1 AS found FROM sys.columns WHERE object_id = OBJECT_ID('file_indexings') AND name = ?",
                [$column]
            );
            if (!$exists) {
                DB::connection('sqlsrv')->statement($sql);
            }
        }
    }

    public function down(): void
    {
        $columns = ['kangis_file_type', 'mls_file_no', 'kangis_file_no', 'new_kangis_file_no', 'kn_grouping_id'];
        foreach ($columns as $column) {
            $exists = DB::connection('sqlsrv')->selectOne(
                "SELECT 1 AS found FROM sys.columns WHERE object_id = OBJECT_ID('file_indexings') AND name = ?",
                [$column]
            );
            if ($exists) {
                DB::connection('sqlsrv')->statement("ALTER TABLE file_indexings DROP COLUMN {$column}");
            }
        }
    }
};
