<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== Batches sub_prefix=129 ===\n";
$batches = DB::connection('sqlsrv')->table('sltr_print_label_batches')
    ->where('sub_prefix','129')->get();
foreach($batches as $b){
    echo "id={$b->id} batch_number={$b->batch_number} sub_prefix={$b->sub_prefix} full_label={$b->full_label} status={$b->status} generated_count={$b->generated_count}\n";
}

$ids = collect($batches)->pluck('id')->all();
echo "\n=== Batch items for those batches ===\n";
if($ids){
    $items = DB::connection('sqlsrv')->table('sltr_print_label_batch_items')->whereIn('batch_id',$ids)->get();
    echo "total items=".count($items)."\n";
    foreach($items->take(15) as $it){
        echo "batch_id={$it->batch_id} file_number={$it->file_number} is_printed={$it->is_printed}\n";
    }
}

echo "\n=== Rack label A15 ===\n";
$labels = DB::connection('sqlsrv')->table('sltr_rack_shelf_labels')
    ->where(function($q){ $q->whereRaw("UPPER(LTRIM(RTRIM(full_label))) = 'A15'")->orWhere('rack','A15'); })
    ->get();
foreach($labels as $l){
    echo "id={$l->id} rack={$l->rack} shelf={$l->shelf} full_label={$l->full_label} counter={$l->counter} status={$l->status} assigned={$l->assigned}\n";
}
