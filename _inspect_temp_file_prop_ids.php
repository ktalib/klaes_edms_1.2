<?php
/**
 * KLAES Temporary File Number Property ID Inspector
 * 
 * Inspects temporary file numbers (e.g., CON-RES-1983-104(T)) against their main file numbers
 * to ensure they have matching prop_ids. Focuses on mismatches involving deed_registrations.
 * 
 * Usage: php _inspect_temp_file_prop_ids.php [--fix] [--count-only]
 * Or access via: http://localhost/klaes/_inspect_temp_file_prop_ids.php
 */

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

use Illuminate\Support\Facades\DB;

class TempFileInspector {
    protected $connection = 'sqlsrv';
    protected $mismatches = [];
    protected $stats = [];
    
    public function run() {
        echo $this->header();
        
        try {
            $results = $this->queryMismatches();
            
            if (empty($results)) {
                echo "\n✓ All temporary file numbers have matching prop_ids with their main file counterparts.\n\n";
                return true;
            }
            
            echo "\n⚠ Found " . count($results) . " mismatches:\n";
            echo str_repeat("=", 120) . "\n";
            
            $this->displayResults($results);
            $this->displayStatistics($results);
            
            return false;
        } catch (\Exception $e) {
            echo "\n❌ Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    protected function queryMismatches() {
        $query = <<<'SQL'
WITH FilePropIds AS (
    -- 1. File Indexings (FI)
    SELECT 
        UPPER(LTRIM(RTRIM(file_number))) AS fileno,
        prop_id,
        'file_indexings' AS source_table,
        id AS record_id
    FROM file_indexings
    WHERE file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''

    UNION ALL

    -- 2. File History Staging (FH)
    SELECT 
        UPPER(LTRIM(RTRIM(mlsFNo))) AS fileno,
        prop_id,
        'file_history_staging' AS source_table,
        id AS record_id
    FROM file_history_staging
    WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''

    UNION ALL

    -- 3. Property Records Application (PRA)
    SELECT 
        UPPER(LTRIM(RTRIM(mlsFNo))) AS fileno,
        prop_id,
        'pra' AS source_table,
        id AS record_id
    FROM pra
    WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''

    UNION ALL

    -- 4. Deed Registrations (DR)
    SELECT 
        UPPER(LTRIM(RTRIM(fileno))) AS fileno,
        prop_id,
        'deed_registrations' AS source_table,
        id AS record_id
    FROM deed_registrations
    WHERE fileno IS NOT NULL AND LTRIM(RTRIM(fileno)) <> ''
),
TempFiles AS (
    SELECT 
        fileno AS temp_fileno,
        RTRIM(REPLACE(fileno, '(T)', '')) AS base_fileno,
        prop_id AS temp_prop_id,
        source_table AS temp_source,
        record_id AS temp_id
    FROM FilePropIds
    WHERE fileno LIKE '%(T)'
),
MainFiles AS (
    SELECT 
        fileno AS main_fileno,
        prop_id AS main_prop_id,
        source_table AS main_source,
        record_id AS main_id
    FROM FilePropIds
    WHERE fileno NOT LIKE '%(T)'
)
SELECT DISTINCT
    t.temp_fileno AS [Temp File (T)],
    t.temp_prop_id AS [Temp Prop ID],
    t.temp_source AS [Temp Source Table],
    m.main_fileno AS [Main File],
    m.main_prop_id AS [Main Prop ID],
    m.main_source AS [Main Source Table]
FROM TempFiles t
JOIN MainFiles m ON t.base_fileno = m.main_fileno
WHERE (t.temp_prop_id <> m.main_prop_id 
       OR (t.temp_prop_id IS NULL AND m.main_prop_id IS NOT NULL)
       OR (t.temp_prop_id IS NOT NULL AND m.main_prop_id IS NULL))
  AND (t.temp_source = 'deed_registrations' OR m.main_source = 'deed_registrations')
ORDER BY t.temp_fileno, t.temp_source
SQL;

        return DB::connection($this->connection)->select(DB::raw($query));
    }
    
    protected function displayResults($results) {
        echo "\n";
        printf(
            "%-25s | %-15s | %-25s | %-25s | %-15s | %-25s\n",
            "Temp File (T)",
            "Temp Prop ID",
            "Temp Source",
            "Main File",
            "Main Prop ID",
            "Main Source"
        );
        echo str_repeat("-", 120) . "\n";
        
        foreach ($results as $row) {
            printf(
                "%-25s | %-15s | %-25s | %-25s | %-15s | %-25s\n",
                $row->{'Temp File (T)'} ?? '-',
                $row->{'Temp Prop ID'} ?? 'NULL',
                $row->{'Temp Source Table'} ?? '-',
                $row->{'Main File'} ?? '-',
                $row->{'Main Prop ID'} ?? 'NULL',
                $row->{'Main Source Table'} ?? '-'
            );
        }
        echo str_repeat("-", 120) . "\n";
    }
    
    protected function displayStatistics($results) {
        echo "\nSTATISTICS:\n";
        echo str_repeat("-", 50) . "\n";
        
        $stats = [
            'total_mismatches' => count($results),
            'deed_reg_temp' => 0,
            'deed_reg_main' => 0,
            'temp_null_main_notnull' => 0,
            'temp_notnull_main_null' => 0,
        ];
        
        foreach ($results as $row) {
            if ($row->{'Temp Source Table'} === 'deed_registrations') {
                $stats['deed_reg_temp']++;
            }
            if ($row->{'Main Source Table'} === 'deed_registrations') {
                $stats['deed_reg_main']++;
            }
            if ($row->{'Temp Prop ID'} === null && $row->{'Main Prop ID'} !== null) {
                $stats['temp_null_main_notnull']++;
            }
            if ($row->{'Temp Prop ID'} !== null && $row->{'Main Prop ID'} === null) {
                $stats['temp_notnull_main_null']++;
            }
        }
        
        printf("  Total Mismatches:              %d\n", $stats['total_mismatches']);
        printf("  Involving deed_registrations (temp): %d\n", $stats['deed_reg_temp']);
        printf("  Involving deed_registrations (main): %d\n", $stats['deed_reg_main']);
        printf("  Temp NULL, Main NOT NULL:      %d\n", $stats['temp_null_main_notnull']);
        printf("  Temp NOT NULL, Main NULL:      %d\n", $stats['temp_notnull_main_null']);
        echo str_repeat("-", 50) . "\n";
    }
    
    protected function header() {
        return <<<HEADER
╔══════════════════════════════════════════════════════════════════════╗
║   KLAES Temporary File Number Property ID Inspector                 ║
║   Checks: Temp File (T) prop_id matches Main File prop_id            ║
╚══════════════════════════════════════════════════════════════════════╝
Database: SQL Server (sqlsrv)
Timestamp: {$this->getTimestamp()}

HEADER;
    }
    
    protected function getTimestamp() {
        return date('Y-m-d H:i:s');
    }
}

// Determine execution context
$isWeb = php_sapi_name() !== 'cli';
$inspector = new TempFileInspector();

if ($isWeb) {
    header('Content-Type: text/plain; charset=utf-8');
}

$success = $inspector->run();

exit($success ? 0 : 1);
