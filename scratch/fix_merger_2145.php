<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $result = DB::connection('sqlsrv')->transaction(function() {
        $service = app(\App\Services\PropertyIdAllocationService::class);
        $groupId = (string) Str::uuid();

        // 1. Generate fresh Prop IDs for the sources
        $propIdA = $service->allocateOrRetrievePropId('TEMP-1044A', 'TEMP-1044A', null, null, ['skip_lookup' => true]);
        $propIdB = $service->allocateOrRetrievePropId('TEMP-1044B', 'TEMP-1044B', null, null, ['skip_lookup' => true]);
        
        // 2. Generate fresh Prop ID for the Merged Result
        $newPropId = $service->allocateOrRetrievePropId('RES-2026-2145', 'RES-2026-2145', null, null, ['skip_lookup' => true]);

        // 3. Create Source OP A (Plot 1044A)
        $opA = DB::connection('sqlsrv')->table('pra')->insertGetId([
            'prop_id' => $propIdA,
            'temp_fileno' => 'TEMP-1044A',
            'party_1' => 'Kano State Government',
            'party_2' => 'ABDURRAUF YAKUBU',
            'Grantor' => 'Kano State Government',
            'Grantee' => 'ABDURRAUF YAKUBU',
            'instrument_type' => 'Occupancy Permit (OP)',
            'transaction_type' => 'Occupancy Permit (OP)',
            'plot_no' => '1044A',
            'land_use' => 'RESIDENTIAL',
            'system_source' => 'FIX_MANUAL',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // 4. Create Source OP B (Plot 1044B)
        $opB = DB::connection('sqlsrv')->table('pra')->insertGetId([
            'prop_id' => $propIdB,
            'temp_fileno' => 'TEMP-1044B',
            'party_1' => 'Kano State Government',
            'party_2' => 'DAHIRU DAMBU SA\'ADU & DAHIRU IDRIS',
            'Grantor' => 'Kano State Government',
            'Grantee' => 'DAHIRU DAMBU SA\'ADU & DAHIRU IDRIS',
            'instrument_type' => 'Occupancy Permit (OP)',
            'transaction_type' => 'Occupancy Permit (OP)',
            'plot_no' => '1044B',
            'land_use' => 'RESIDENTIAL',
            'system_source' => 'FIX_MANUAL',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // 5. Update the existing ToT (140761) to link to these sources
        DB::connection('sqlsrv')->table('pra')->where('id', 140761)->update([
            'prop_id' => $newPropId,
            'parent_prop_id' => $newPropId,
            'is_merger_op' => 1,
            'merger_group_id' => $groupId,
            'source_op_table' => 'pra',
            'source_op_id' => $opA, // Primary anchor
            'updated_at' => now()
        ]);

        // 6. Create "Merger Link" rows for each source so they show up in the history
        // Source A Link
        DB::connection('sqlsrv')->table('pra')->insert([
            'prop_id' => $newPropId,
            'parent_prop_id' => $propIdA,
            'is_merger_op' => 1,
            'merger_group_id' => $groupId,
            'party_1' => 'ABDURRAUF YAKUBU',
            'party_2' => 'MARYAM ABUBAKAR MATAWALLE',
            'instrument_type' => 'Merger OP',
            'plot_no' => '1044A',
            'system_source' => 'FIX_MANUAL',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Source B Link
        DB::connection('sqlsrv')->table('pra')->insert([
            'prop_id' => $newPropId,
            'parent_prop_id' => $propIdB,
            'is_merger_op' => 1,
            'merger_group_id' => $groupId,
            'party_1' => 'DAHIRU DAMBU SA\'ADU & DAHIRU IDRIS',
            'party_2' => 'MARYAM ABUBAKAR MATAWALLE',
            'instrument_type' => 'Merger OP',
            'plot_no' => '1044B',
            'system_source' => 'FIX_MANUAL',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return [
            'new_prop_id' => $newPropId,
            'group_id' => $groupId,
            'source_a' => $opA,
            'source_b' => $opB
        ];
    });

    echo "Merger fix applied successfully:\n";
    print_r($result);
} catch (\Throwable $e) {
    echo "Fix failed: " . $e->getMessage() . "\n";
}
