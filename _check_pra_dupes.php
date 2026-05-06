<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$props = [57160,57161,57162,57163,57164];
$files = ['RES-2026-2038','RES-2026-2039','RES-2026-2040','RES-2026-2041','RES-2026-2042'];
$icIds = [711,2866,712,3988,713,5337,714,1751,715,3767];

echo "=== instrument_capture columns ===\n";
$cols = DB::connection('sqlsrv')->select("SELECT name FROM sys.columns WHERE object_id=OBJECT_ID('instrument_capture') ORDER BY column_id");
echo implode(', ', array_map(fn($c)=>$c->name,$cols))."\n\n";

echo "=== instrument_capture rows by id (the dupes) ===\n";
$rows = DB::connection('sqlsrv')->table('instrument_capture')->whereIn('id',$icIds)->orderBy('id')->get();
foreach ($rows as $r) {
    $a = (array)$r;
    echo json_encode([
        'id'=>$a['id'],'temp'=>$a['temp_fileno']??null,'mls'=>$a['mlsFNo']??null,
        'fileno'=>$a['fileno']??null,'kangis'=>$a['kangisFileNo']??null,'newk'=>$a['NewKANGISFileno']??null,
        'rel'=>$a['related_file_number']??null,'prop'=>$a['prop_id']??null,
        'op_sn'=>$a['op_serial_number']??null,'inst'=>$a['instrument_type']??null,
        'p1'=>$a['party_1_name']??$a['party_1']??null,'p2'=>$a['party_2_name']??$a['party_2']??null,
        'created'=>$a['created_at']??null,
    ])."\n";
}

echo "\n=== pra rows by prop_id ===\n";
$rows = DB::connection('sqlsrv')->table('pra')->whereIn('prop_id',$props)->orderBy('prop_id')->orderBy('id')->get();
foreach ($rows as $r) {
    $a=(array)$r;
    echo json_encode([
        'id'=>$a['id'],'temp'=>$a['temp_fileno'],'fileno'=>$a['fileno'],'mls'=>$a['mlsFNo'],
        'prop'=>$a['prop_id'],'op_sn'=>$a['op_serial_number']??null,
        'p1'=>$a['party_1']??null,'p2'=>$a['party_2']??null,
        'inst'=>$a['instrument_type']??null,'ic_id'=>$a['instrument_capture_id']??null,
        'created'=>$a['created_at']??null,
    ])."\n";
}

echo "\n=== pic rows by prop_id ===\n";
$rows = DB::connection('sqlsrv')->table('pic')->whereIn('prop_id',$props)->orderBy('prop_id')->orderBy('id')->get();
foreach ($rows as $r) {
    $a=(array)$r;
    echo json_encode([
        'id'=>$a['id'],'temp'=>$a['temp_fileno'],'fileno'=>$a['fileno'],'mls'=>$a['mlsFNo'],
        'prop'=>$a['prop_id'],'inst'=>$a['instrument_type']??null,
        'p1'=>$a['party_1']??null,'p2'=>$a['party_2']??null,
        'created'=>$a['created_at']??null,
    ])."\n";
}

echo "\n=== instrument_capture rows where any file column matches RES-2026-2038..2042 ===\n";
$rows = DB::connection('sqlsrv')->table('instrument_capture')
    ->where(function($q) use ($files){
        $q->whereIn('mlsFNo',$files)
          ->orWhereIn('fileno',$files)
          ->orWhereIn('kangisFileNo',$files)
          ->orWhereIn('NewKANGISFileno',$files)
          ->orWhereIn('related_file_number',$files);
    })->get();
foreach ($rows as $r) {
    $a=(array)$r;
    echo json_encode([
        'id'=>$a['id'],'temp'=>$a['temp_fileno']??null,
        'mls'=>$a['mlsFNo']??null,'fileno'=>$a['fileno']??null,
        'kangis'=>$a['kangisFileNo']??null,'newk'=>$a['NewKANGISFileno']??null,
        'rel'=>$a['related_file_number']??null,'prop'=>$a['prop_id']??null,
        'op_sn'=>$a['op_serial_number']??null,
    ])."\n";
}
