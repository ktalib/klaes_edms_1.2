<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use Illuminate\Support\Facades\DB;

try {
    $icQuery = DB::connection('sqlsrv')->table('instrument_capture')
        ->select([
            'id',
            DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno) as file_number"),
            DB::raw("registration_number as registration_particulars"),
            'instrument_type',
            'party_1_name as party_1',
            'party_2_name as party_2',
            'party_3_name as party_3',
            'party_4_name as party_4',
            'property_location as location',
            'created_at as date_captured',
            DB::raw("'Instrument Capture' as source_table")
        ])
        ->where('instrument_type', 'Deed of Mortgage');

    $praQuery = DB::connection('sqlsrv')->table('pra')
        ->select([
            'id',
            DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, fileno) as file_number"),
            DB::raw("regNo + ' VOL: ' + COALESCE(volumeNo, '') + ' PG: ' + COALESCE(pageNo, '') as registration_particulars"),
            'instrument_type',
            DB::raw("COALESCE(Mortgagor, Grantor) as party_1"),
            DB::raw("COALESCE(Mortgagee, Grantee) as party_2"),
            DB::raw("NULL as party_3"),
            DB::raw("NULL as party_4"),
            'location',
            'created_at as date_captured',
            DB::raw("'Property Records' as source_table")
        ])
        ->where('instrument_type', 'Deed of Mortgage');

    $unionQuery = $icQuery->unionAll($praQuery);
    $unionSql = $unionQuery->toSql();
    $unionBindings = $unionQuery->getBindings();

    $query = DB::connection('sqlsrv')
        ->table(DB::raw("({$unionSql}) as combined"))
        ->setBindings($unionBindings)
        ->select('*');

    echo "Testing wrapped count execution...\n";
    $count = $query->count();
    echo "Success! Total count: " . $count . "\n";

    echo "Testing wrapped data fetch...\n";
    $results = $query->limit(5)->get();
    echo "Success! Found " . count($results) . " records.\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
