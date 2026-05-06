<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('sqlsrv');

        // mls_file_no: composite index for the JOIN in OpResettlement
        // (tracking_id + full_file_number) with source_instrument_capture_id, source_pra_id INCLUDEd
        $this->createIndexIfNotExists($conn, 'mls_file_no', 'IX_mls_file_no_tracking_fullfile',
            'CREATE NONCLUSTERED INDEX IX_mls_file_no_tracking_fullfile ON mls_file_no (tracking_id, full_file_number) INCLUDE (source_instrument_capture_id, source_pra_id, source, land_use, sub_source, con_commissioned_at)');

        // fileNumber: index on mlsfNo for the LEFT JOIN and correlated subqueries
        $this->createIndexIfNotExists($conn, 'fileNumber', 'IX_fileNumber_mlsfNo',
            'CREATE NONCLUSTERED INDEX IX_fileNumber_mlsfNo ON fileNumber (mlsfNo) INCLUDE (tracking_id, FileName, plot_no, tp_no, lga, location, temp_fileno, SOURCE, commissioning_date, is_deleted, created_by, created_at)');

        // oss_applications: composite for the change-of-name page filtering
        // system_source may be text/ntext on production so it goes in INCLUDE
        $this->createIndexIfNotExists($conn, 'oss_applications', 'IX_oss_applications_systemsource_type',
            'CREATE NONCLUSTERED INDEX IX_oss_applications_systemsource_type ON oss_applications (application_type) INCLUDE (instrument_capture_id, captured_by, file_no, applicant_name, system_source)');

        // pra: covering index for the Transfer of Title lookup
        $this->createIndexIfNotExists($conn, 'pra', 'IX_pra_prop_id_instrument_type',
            'CREATE NONCLUSTERED INDEX IX_pra_prop_id_instrument_type ON pra (prop_id, instrument_type) INCLUDE (created_at, transaction_type)');

        // pra: covering index for temp_fileno aggregate
        $this->createIndexIfNotExists($conn, 'pra', 'IX_pra_prop_id_temp_fileno',
            'CREATE NONCLUSTERED INDEX IX_pra_prop_id_temp_fileno ON pra (prop_id) INCLUDE (temp_fileno) WHERE temp_fileno IS NOT NULL');
    }

    public function down(): void
    {
        $conn = DB::connection('sqlsrv');

        foreach ([
            'IX_mls_file_no_tracking_fullfile' => 'mls_file_no',
            'IX_fileNumber_mlsfNo' => 'fileNumber',
            'IX_oss_applications_systemsource_type' => 'oss_applications',
            'IX_pra_prop_id_instrument_type' => 'pra',
            'IX_pra_prop_id_temp_fileno' => 'pra',
        ] as $index => $table) {
            try {
                $conn->statement("IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = '{$index}' AND object_id = OBJECT_ID('{$table}')) DROP INDEX [{$index}] ON [{$table}]");
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    private function createIndexIfNotExists($conn, string $table, string $indexName, string $sql): void
    {
        try {
            $exists = $conn->selectOne("SELECT 1 as ex FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID(?)", [$indexName, $table]);
            if (!$exists) {
                $conn->statement($sql);
            }
        } catch (\Throwable $e) {
            // Log but don't fail — production may have column differences
            \Illuminate\Support\Facades\Log::warning("Index creation skipped: {$indexName} on {$table} — {$e->getMessage()}");
        }
    }
};
