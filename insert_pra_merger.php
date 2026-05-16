<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Pra\PraRecordService;
use Illuminate\Support\Facades\Auth;


try {
    $praService = app(PraRecordService::class);

    $payload = [
        'mlsFNo' => 'IND-2026-3',
        'fileno' => 'IND-2026-3',
        'temp_fileno' => null,
        'transaction_type' => 'Merger',
        'instrument_type' => 'Merger',
        'transaction_date' => now()->toDateString(),
        'reg_date' => '0/0/0',
        'regNo' => '0/0/0',
        'serialNo' => '0',
        'pageNo' => '0',
        'volumeNo' => '0',
        'system_source' => 'MLS_PLOT_WORKFLOW',
        'land_use' => 'IND',
        'plot_no' => '',
        'lgsaOrCity' => '',
        'location' => '',
        'property_description' => '',
        'Grantor' => 'NIGERIAN BOTTLING CO. LTD',
        'Grantee' => 'NIGERIAN BOTTLING COMPANY LTD',
        'party_1' => 'NIGERIAN BOTTLING CO. LTD',
        'party_2' => 'NIGERIAN BOTTLING COMPANY LTD',
        'prop_id' => '80007857',
        'parent_prop_id' => '80005511,21146',
        'tracking_id' => 'TEMP-440133',
        'comments' => "Merger commissioning for IND-2026-3 (System Generated)",
        'remarks' => "Commissioned via Merger workflow",
    ];

    $record = $praService->createRecord($payload, 2);

    echo "SUCCESS: PRA record created for IND-2026-3. Prop ID: " . ($record['prop_id'] ?? 'N/A') . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
