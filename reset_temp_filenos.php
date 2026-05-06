<?php
/**
 * Reset ALL temp file numbers
 * - Clears temp_fileno column in pra, pic, instrument_capture
 * - Deletes all rows from temp_fileno_sequence
 * - Reseeds identity to 0 so next insert starts at 1
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('sqlsrv');

echo "=== Resetting ALL Temp File Numbers ===" . PHP_EOL . PHP_EOL;

DB::connection('sqlsrv')->beginTransaction();

try {
    // 1. Clear temp_fileno in pra
    $praUpdated = $db->table('pra')
        ->where('temp_fileno', 'like', 'TEMP-%')
        ->update(['temp_fileno' => null]);
    echo "pra: cleared {$praUpdated} TEMP- records" . PHP_EOL;

    // 2. Clear temp_fileno in pic
    $picUpdated = $db->table('pic')
        ->where('temp_fileno', 'like', 'TEMP-%')
        ->update(['temp_fileno' => null]);
    echo "pic: cleared {$picUpdated} TEMP- records" . PHP_EOL;

    // 3. Clear temp_fileno in instrument_capture
    $icUpdated = $db->table('instrument_capture')
        ->where('temp_fileno', 'like', 'TEMP-%')
        ->update(['temp_fileno' => null]);
    echo "instrument_capture: cleared {$icUpdated} TEMP- records" . PHP_EOL;

    // 4. Delete all rows from temp_fileno_sequence
    $seqDeleted = $db->table('temp_fileno_sequence')->delete();
    echo "temp_fileno_sequence: deleted {$seqDeleted} rows" . PHP_EOL;

    // 5. Reseed identity to 0 (next insert will be id=1 → TEMP-00001)
    $db->statement("DBCC CHECKIDENT ('temp_fileno_sequence', RESEED, 0)");
    echo "temp_fileno_sequence: identity reseeded to 0 (next id = 1)" . PHP_EOL;

    DB::connection('sqlsrv')->commit();

    echo PHP_EOL . "=== Reset Complete ===" . PHP_EOL;
    echo "Next temp file number will be: TEMP-00001" . PHP_EOL;

} catch (\Exception $e) {
    DB::connection('sqlsrv')->rollBack();
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo "All changes rolled back." . PHP_EOL;
}
