<?php
/**
 * Seed 4 Legal Search tables with 10 test transactions each.
 * Run: php seed_legal_search_test.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$conn = DB::connection('sqlsrv');

// ── 1. Clear all 4 tables ────────────────────────────────────────
$conn->table('file_history_staging')->delete();
$conn->table('CofO_staging')->delete();
$conn->table('pra')->delete();
$conn->table('deed_registrations')->delete();

echo "Cleared all 4 tables.\n";

// ── Shared data ──────────────────────────────────────────────────
$names = ['Musa Ibrahim', 'Amina Yusuf', 'Sani Abdullahi', 'Zainab Abubakar', 'Yakubu Garba',
          'Hauwa Musa', 'Bashir Suleiman', 'Hadiza Bello', 'Usman Danladi', 'Fatima Shehu',
          'Kano State Government', 'Aisha Mohammed', 'Idris Kabiru', 'Safiya Adamu', 'Nasiru Haruna'];

$lgas = ['Nassarawa', 'Tarauni', 'Fagge', 'Gwale', 'Kano Municipal', 'Dala', 'Ungogo', 'Kumbotso'];
$districts = ['Nassarawa', 'Sharada', 'Bompai', 'Hotoro', 'Dorayi', 'Gyadi-Gyadi', 'Zoo Road', 'Kabuga'];
$landUses = ['Residential', 'Commercial', 'Industrial', 'Agricultural'];
$txnTypes = ['Occupancy Permit (OP)', 'Transfer of Title (OP)', 'Right of Occupancy (RofO)',
             'Deed of Assignment', 'Mortgage', 'Lease', 'Power of Attorney', 'Surrender'];

$propIds = [];
for ($i = 1; $i <= 5; $i++) {
    $propIds[] = 900000 + $i;  // integer prop_ids like actual system uses
}

// ── 2. file_history_staging (10 rows) ────────────────────────────
$fileHistoryRows = [];
for ($i = 1; $i <= 10; $i++) {
    $fileNo = 'COM-2026-' . $i;
    $serial = rand(100, 999);
    $page = rand(1, 50);
    $volume = rand(1, 20);
    $fileHistoryRows[] = [
        'mlsFNo'           => $fileNo,
        'fileno'           => $fileNo,
        'kangisFileNo'     => 'KNML ' . str_pad(rand(1000, 99999), 5, '0', STR_PAD_LEFT),
        'NewKANGISFileno'  => 'KN' . str_pad(rand(100, 9999), 4, '0', STR_PAD_LEFT),
        'transaction_type' => $txnTypes[array_rand($txnTypes)],
        'transaction_date' => '2025-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT),
        'party_1'          => $names[array_rand($names)],
        'party_2'          => $names[array_rand($names)],
        'land_use'         => $landUses[array_rand($landUses)],
        'location'         => 'Plot ' . rand(1, 500) . ', ' . $districts[array_rand($districts)] . ' Layout, ' . $lgas[array_rand($lgas)],
        'lgsaOrCity'       => $lgas[array_rand($lgas)],
        'districtName'     => $districts[array_rand($districts)],
        'serialNo'         => $serial,
        'pageNo'           => $page,
        'volumeNo'         => $volume,
        'regNo'            => $serial . '/' . $page . '/' . $volume,
        'plot_no'          => (string) rand(1, 200),
        'plot_size'        => rand(200, 5000) . ' sqm',
        'prop_id'          => $propIds[($i - 1) % count($propIds)],
        'comments'         => 'Test record ' . $i . ' for file history staging',
        'deeds_date'       => '2025-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT),
        'deeds_time'       => str_pad(rand(8, 16), 2, '0', STR_PAD_LEFT) . ':' . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT),
        'reg_date'         => '2025-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT),
        'reg_time'         => str_pad(rand(8, 16), 2, '0', STR_PAD_LEFT) . ':' . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT),
        'is_caveated'      => $i <= 2 ? 1 : 0,
        'is_deleted'       => 0,
    ];
}
$conn->table('file_history_staging')->insert($fileHistoryRows);
echo "Inserted 10 rows into file_history_staging.\n";

// ── 3. CofO_staging (10 rows) ────────────────────────────────────
$cofoRows = [];
for ($i = 1; $i <= 10; $i++) {
    $fileNo = 'RES-2025-' . $i;
    $serial = rand(100, 999);
    $page = rand(1, 50);
    $volume = rand(1, 20);
    $cofoRows[] = [
        'mlsFNo'           => $fileNo,
        'fileno'           => $fileNo,
        'kangisFileNo'     => 'KNML ' . str_pad(rand(1000, 99999), 5, '0', STR_PAD_LEFT),
        'NewKANGISFileno'  => 'KN' . str_pad(rand(100, 9999), 4, '0', STR_PAD_LEFT),
        'transaction_type' => $txnTypes[array_rand($txnTypes)],
        'transaction_date' => '2024-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT),
        'Grantor'          => $names[array_rand($names)],
        'Grantee'          => $names[array_rand($names)],
        'land_use'         => $landUses[array_rand($landUses)],
        'location'         => 'Plot ' . rand(1, 500) . ', ' . $districts[array_rand($districts)] . ' Layout, ' . $lgas[array_rand($lgas)],
        'lgsaOrCity'       => $lgas[array_rand($lgas)],
        'serialNo'         => $serial,
        'pageNo'           => $page,
        'volumeNo'         => $volume,
        'regNo'            => $serial . '/' . $page . '/' . $volume,
        'plot_no'          => (string) rand(1, 200),
        'prop_id'          => $propIds[($i - 1) % count($propIds)],
        'comments'         => 'Test record ' . $i . ' for CofO staging',
        'transaction_time' => str_pad(rand(8, 16), 2, '0', STR_PAD_LEFT) . ':' . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT),
        'is_caveated'      => $i <= 2 ? 1 : 0,
        'is_deleted'       => 0,
    ];
}
$conn->table('CofO_staging')->insert($cofoRows);
echo "Inserted 10 rows into CofO_staging.\n";

// ── 4. pra (10 rows) ─────────────────────────────────────────────
$praRows = [];
for ($i = 1; $i <= 10; $i++) {
    $fileNo = 'IND-2025-' . $i;
    $serial = rand(100, 999);
    $page = rand(1, 50);
    $volume = rand(1, 20);
    $praRows[] = [
        'mlsFNo'           => $fileNo,
        'fileno'           => $fileNo,
        'kangisFileNo'     => 'KNML ' . str_pad(rand(1000, 99999), 5, '0', STR_PAD_LEFT),
        'NewKANGISFileno'  => 'KN' . str_pad(rand(100, 9999), 4, '0', STR_PAD_LEFT),
        'transaction_type' => $txnTypes[array_rand($txnTypes)],
        'transaction_date' => '2025-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT),
        'party_1'          => $names[array_rand($names)],
        'party_2'          => $names[array_rand($names)],
        'land_use'         => $landUses[array_rand($landUses)],
        'location'         => 'Plot ' . rand(1, 500) . ', ' . $districts[array_rand($districts)] . ' Area, ' . $lgas[array_rand($lgas)],
        'lgsaOrCity'       => $lgas[array_rand($lgas)],
        'districtName'     => $districts[array_rand($districts)],
        'serialNo'         => $serial,
        'pageNo'           => $page,
        'volumeNo'         => $volume,
        'regNo'            => $serial . '/' . $page . '/' . $volume,
        'plot_no'          => (string) rand(1, 200),
        'plot_size'        => rand(200, 5000) . ' sqm',
        'prop_id'          => $propIds[($i - 1) % count($propIds)],
        'comments'         => 'Test record ' . $i . ' for PRA',
        'deeds_date'       => '2025-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT),
        'deeds_time'       => str_pad(rand(8, 16), 2, '0', STR_PAD_LEFT) . ':' . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT),
        'is_caveated'      => $i <= 2 ? 1 : 0,
        'is_deleted'       => 0,
    ];
}
$conn->table('pra')->insert($praRows);
echo "Inserted 10 rows into pra.\n";

// ── 5. deed_registrations (10 rows) ──────────────────────────────
$deedRows = [];
$instruments = ['Deed of Assignment', 'Mortgage Deed', 'Deed of Lease', 'Power of Attorney',
                'Deed of Surrender', 'Deed of Gift', 'Deed of Sublease', 'Deed of Conveyance'];
for ($i = 1; $i <= 10; $i++) {
    $fileNo = 'COM-2026-' . rand(1, 10);
    $serial = rand(100, 999);
    $page = rand(1, 50);
    $volume = rand(1, 20);
    $deedRows[] = [
        'fileno'               => $fileNo,
        'instrument_type'      => $instruments[array_rand($instruments)],
        'deeds_date'           => '2025-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT),
        'deeds_time'           => str_pad(rand(8, 16), 2, '0', STR_PAD_LEFT) . ':' . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT),
        'grantor'              => $names[array_rand($names)],
        'grantee'              => $names[array_rand($names)],
        'district'             => $districts[array_rand($districts)],
        'lga'                  => $lgas[array_rand($lgas)],
        'serial_no'            => $serial,
        'page_no'              => $page,
        'volume_no'            => $volume,
        'registration_number'  => $serial . '/' . $page . '/' . $volume,
        'plot_number'          => (string) rand(1, 200),
        'size'                 => rand(200, 5000) . ' sqm',
        'prop_id'              => $propIds[($i - 1) % count($propIds)],
        'property_description' => 'Plot ' . rand(1, 500) . ' at ' . $districts[array_rand($districts)] . ', ' . $lgas[array_rand($lgas)],
        'is_deleted'           => 0,
    ];
}
$conn->table('deed_registrations')->insert($deedRows);
echo "Inserted 10 rows into deed_registrations.\n";

echo "\nDone! 40 test records created across 4 tables.\n";
