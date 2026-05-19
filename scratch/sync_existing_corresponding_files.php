<?php
/**
 * Utility script to backfill existing matched Cadastral Shadow Files (CSF)
 * into the file_indexings table, ensuring they have is_corresponding_file = 1
 * and corresponding_fileno set to the matched file number.
 */

// Boot Laravel application
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Starting synchronization of existing Cadastral Shadow Files (CSF) with file_indexings...\n\n";

try {
    // 1. Fetch all matched, active cadastral shadow files
    $matchedShadows = DB::connection('sqlsrv')
        ->table('cadastral_shadow_files')
        ->where('is_deleted', 0)
        ->get();

    echo "Found " . count($matchedShadows) . " active Cadastral Shadow File matches in cadastral_shadow_files.\n";

    $updatedCount = 0;
    $scannedCount = 0;

    foreach ($matchedShadows as $shadow) {
        $fileNumber = trim($shadow->full_number);
        if (empty($fileNumber)) {
            continue;
        }

        $scannedCount++;

        // 2. Find any matching records in file_indexings that don't have is_corresponding_file = 1 or corresponding_fileno set
        $affected = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where(function($query) use ($fileNumber) {
                $query->where('file_number', $fileNumber)
                      ->orWhere('st_fillno', $fileNumber);
            })
            ->where(function($query) use ($fileNumber) {
                $query->whereNull('is_corresponding_file')
                      ->orWhere('is_corresponding_file', '!=', 1)
                      ->orWhereNull('corresponding_fileno')
                      ->orWhere('corresponding_fileno', '!=', $fileNumber);
            })
            ->update([
                'is_corresponding_file' => 1,
                'corresponding_fileno' => $fileNumber,
            ]);

        if ($affected > 0) {
            echo "  [Updated] File: {$fileNumber} -> Marked {$affected} record(s) in file_indexings as corresponding.\n";
            $updatedCount += $affected;
        }
    }

    echo "\nSummary of Operations:\n";
    echo "- Shadow file matches scanned: {$scannedCount}\n";
    echo "- file_indexings records updated: {$updatedCount}\n";
    echo "Done successfully!\n";

} catch (\Exception $e) {
    echo "Error occurred: " . $e->getMessage() . "\n";
    exit(1);
}
