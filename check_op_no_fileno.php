<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Records with NO file number at all
$rows = DB::connection('sqlsrv')->table('pra')
    ->where('instrument_type', 'like', '%Occupancy Permit%')
    ->where(function($q) { $q->whereNull('mlsFNo')->orWhere('mlsFNo', ''); })
    ->where(function($q) { $q->whereNull('kangisFileNo')->orWhere('kangisFileNo', ''); })
    ->where(function($q) { $q->whereNull('NewKANGISFileno')->orWhere('NewKANGISFileno', ''); })
    ->where(function($q) { $q->whereNull('fileno')->orWhere('fileno', ''); })
    ->where(function($q) { $q->whereNull('temp_fileno')->orWhere('temp_fileno', ''); })
    ->select('id','prop_id','instrument_type','party_1','party_2','Grantor','Grantee','op_serial_number','plot_no','location','temp_fileno','created_at')
    ->orderBy('id','desc')
    ->get();

echo "OP records with NO file number at all: " . $rows->count() . "\n\n";

foreach ($rows as $r) {
    echo "ID={$r->id} | prop_id={$r->prop_id} | OP#={$r->op_serial_number} | plot={$r->plot_no} | loc={$r->location} | party1={$r->party_1} | party2={$r->party_2} | temp={$r->temp_fileno} | created={$r->created_at}\n";
}

// Records where ONLY temp_fileno exists (these were showing "No File Number" before)
echo "\n--- OP records with ONLY temp_fileno (previously displayed as 'No File Number') ---\n";
$tempOnly = DB::connection('sqlsrv')->table('pra')
    ->where('instrument_type', 'like', '%Occupancy Permit%')
    ->whereNotNull('temp_fileno')->where('temp_fileno', '!=', '')
    ->where(function($q) { $q->whereNull('mlsFNo')->orWhere('mlsFNo', ''); })
    ->where(function($q) { $q->whereNull('kangisFileNo')->orWhere('kangisFileNo', ''); })
    ->where(function($q) { $q->whereNull('NewKANGISFileno')->orWhere('NewKANGISFileno', ''); })
    ->select('id','temp_fileno','party_1','party_2')
    ->orderBy('id','desc')
    ->limit(20)
    ->get();

echo "Count: " . $tempOnly->count() . "\n";
foreach ($tempOnly as $r) {
    echo "ID={$r->id} | temp={$r->temp_fileno} | party1={$r->party_1} | party2={$r->party_2}\n";
}
