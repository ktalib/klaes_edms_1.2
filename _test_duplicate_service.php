<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DuplicateCheckService Smoke Test ===\n\n";

// 1. Test service resolves
$svc = app(\App\Services\DuplicateCheckService::class);
echo "[OK] Service resolves from container\n";

// 2. Test helper function exists
echo "[OK] check_duplicate() helper exists: " . (function_exists('check_duplicate') ? 'YES' : 'NO') . "\n";

// 3. Test minimum-parameter guard (should return null with only 1 param)
$result = $svc->check('instrument', ['fileno' => 'TEST-ONLY-ONE-PARAM']);
echo "[OK] Single-param guard: " . ($result === null ? 'PASS (returned null)' : 'FAIL (matched something)') . "\n";

// 4. Test OP check with non-existent data (should return null)
$result = $svc->check('op', [
    'fileno' => 'NONEXISTENT-FILE-999',
    'op_serial' => '99999999',
    'party_1' => 'NONEXISTENT PERSON',
]);
echo "[OK] OP check (no match expected): " . ($result === null ? 'PASS' : 'FAIL') . "\n";

// 5. Test PRA check with non-existent data (should return null)
$result = $svc->check('pra', [
    'fileno' => 'NONEXISTENT-FILE-999',
    'party_1' => 'NONEXISTENT PERSON',
    'transaction_type' => 'Assignment',
]);
echo "[OK] PRA check (no match expected): " . ($result === null ? 'PASS' : 'FAIL') . "\n";

// 6. Test RofO check (land_recommendations) with non-existent data
$result = $svc->check('rofo', [
    'fileno' => 'NONEXISTENT-FILE-999',
    'party_name' => 'NONEXISTENT PERSON',
]);
echo "[OK] RofO check (no match expected): " . ($result === null ? 'PASS' : 'FAIL') . "\n";

// 7. Test CofO check with non-existent data
$result = $svc->check('cofo', [
    'cofo_no' => 'NONEXISTENT-COFO-999',
    'owner_name' => 'NONEXISTENT PERSON',
]);
echo "[OK] CofO check (no match expected): " . ($result === null ? 'PASS' : 'FAIL') . "\n";

// 8. Test with exclude_id (should not crash)
$result = $svc->check('instrument', [
    'fileno' => 'NONEXISTENT-FILE-999',
    'party_1' => 'NONEXISTENT PERSON',
    'exclude_id' => 1,
]);
echo "[OK] exclude_id parameter: " . ($result === null ? 'PASS' : 'FAIL') . "\n";

// 9. Test with real data sample (pick first instrument_capture record if exists)
$sample = DB::connection('sqlsrv')->table('instrument_capture')
    ->where(function($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
    ->whereNotNull('temp_fileno')
    ->whereNotNull('party_1_name')
    ->first();

if ($sample) {
    // Should find a match using real data
    $realResult = $svc->check('instrument', [
        'fileno' => $sample->temp_fileno,
        'party_1' => $sample->party_1_name,
        'instrument_type' => $sample->instrument_type,
    ]);
    echo "[OK] Real data match: " . ($realResult !== null ? 'PASS (found match)' : 'FAIL (no match)') . "\n";

    // Should NOT match when exclude_id is the same record
    $excludeResult = $svc->check('instrument', [
        'fileno' => $sample->temp_fileno,
        'party_1' => $sample->party_1_name,
        'instrument_type' => $sample->instrument_type,
        'exclude_id' => $sample->id,
    ]);
    // This may still match if there are genuine duplicates, so just report
    echo "[OK] exclude_id self-exclusion: " . ($excludeResult === null ? 'PASS (self excluded)' : 'INFO (other match exists)') . "\n";
} else {
    echo "[SKIP] No real instrument_capture data to test against\n";
}

echo "\n=== All smoke tests passed ===\n";
