<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $result = DB::connection('sqlsrv')->transaction(function() {
        // 1. Generate a fresh Prop ID
        $service = app(\App\Services\PropertyIdAllocationService::class);
        $newPropId = $service->allocateOrRetrievePropId('RES-2026-2153', 'RES-2026-2153', null, null, ['skip_lookup' => true]);

        // 2. Create the CORRECT OP in PRA
        $correctOp = DB::connection('sqlsrv')->table('pra')->insertGetId([
            'prop_id' => $newPropId,
            'mlsFNo' => 'RES-2026-2153',
            'fileno' => 'RES-2026-2153',
            'temp_fileno' => 'TEMP-38539',
            'party_1' => 'Kano State Government',
            'party_2' => 'AMINU AHMAD',
            'Grantor' => 'Kano State Government',
            'Grantee' => 'AMINU AHMAD',
            'instrument_type' => 'Occupancy Permit (OP)',
            'transaction_type' => 'Occupancy Permit (OP)',
            'plot_no' => '2764A',
            'land_use' => 'RESIDENTIAL',
            'system_source' => 'FIX_MANUAL',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // 3. Move the existing ToT to the new Prop ID and link to the new OP
        DB::connection('sqlsrv')->table('pra')->where('id', 141018)->update([
            'prop_id' => $newPropId,
            'parent_prop_id' => $newPropId,
            'source_op_table' => 'pra',
            'source_op_id' => $correctOp,
            'updated_at' => now()
        ]);

        return ['new_prop_id' => $newPropId, 'correct_op_id' => $correctOp];
    });

    echo "Fix applied successfully:\n";
    print_r($result);
} catch (\Throwable $e) {
    echo "Fix failed: " . $e->getMessage() . "\n";
}
