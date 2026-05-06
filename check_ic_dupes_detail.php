<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$db = DB::connection('sqlsrv');

// For each duplicate set: the SECOND (newer) record is the duplicate to soft-delete.
// We need to also clean up its deed_registration entry.

echo "=== All duplicate temp_fileno pairs in instrument_capture ===" . PHP_EOL;

$dupes = $db->select("
    SELECT temp_fileno, COUNT(*) as cnt
    FROM instrument_capture
    WHERE temp_fileno IS NOT NULL AND temp_fileno LIKE 'TEMP-%'
    AND (is_deleted = 0 OR is_deleted IS NULL)
    GROUP BY temp_fileno
    HAVING COUNT(*) > 1
    ORDER BY temp_fileno
");

foreach ($dupes as $d) {
    echo PHP_EOL . "=== {$d->temp_fileno} ({$d->cnt} records) ===" . PHP_EOL;

    $rows = $db->table('instrument_capture')
        ->where('temp_fileno', $d->temp_fileno)
        ->where(function($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
        ->select('id', 'temp_fileno', 'registration_number', 'instrument_type', 
                 'prop_id', 'op_serial_number', 'party_2_name', 'is_deed_registered', 'created_at')
        ->orderBy('id')
        ->get();

    foreach ($rows as $i => $r) {
        $label = $i === 0 ? '[KEEP]' : '[DUPLICATE - to soft-delete]';
        echo "  {$label} ic.id={$r->id} | reg={$r->registration_number} | prop_id={$r->prop_id} | op_serial={$r->op_serial_number} | p2={$r->party_2_name} | deed_reg={$r->is_deed_registered} | created={$r->created_at}" . PHP_EOL;

        // Check linked deed_registration
        if ($r->registration_number) {
            $dr = $db->table('deed_registrations')
                ->where('registration_number', $r->registration_number)
                ->where('instrument_type', $r->instrument_type)
                ->select('id', 'registration_number', 'instrument_capture_id', 'prop_id')
                ->first();
            if ($dr) {
                echo "    -> deed_registrations id={$dr->id} | ic_id={$dr->instrument_capture_id} | prop_id={$dr->prop_id}" . PHP_EOL;
            } else {
                echo "    -> no deed_registration found" . PHP_EOL;
            }
        }
    }

    // Check if PropID_Master has references
    $propIds = $rows->pluck('prop_id')->unique()->filter();
    foreach ($propIds as $pid) {
        $master = $db->table('PropID_Master')->where('prop_id', $pid)->select('prop_id', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno')->first();
        if ($master) {
            echo "  PropID_Master prop_id={$master->prop_id} | mls={$master->mlsFNo} | kangis={$master->kangisFileNo} | temp={$master->temp_fileno}" . PHP_EOL;
        }
    }
}

// Check vault state for OP
echo PHP_EOL . "=== Current OP Vault State ===" . PHP_EOL;
$vault = $db->table('instrument_number_vaults')
    ->where('instrument_type', 'Occupancy Permit (OP)')
    ->first();
if ($vault) {
    echo "  current_volume={$vault->current_volume} | current_page={$vault->current_page} | current_serial={$vault->current_serial}" . PHP_EOL;
}
