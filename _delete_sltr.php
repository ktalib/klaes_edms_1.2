<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$conn = DB::connection('sqlsrv');

// Target: sub_prefix 129, 6-digit batch (full_label A16) = batch id 181
$batch = $conn->table('sltr_print_label_batches')
    ->where('sub_prefix','129')
    ->where('full_label','A16')
    ->where('status','!=','printed')
    ->where('status','!=','completed')
    ->first();

if(!$batch){
    echo "No matching 6-digit 129 batch found (already deleted?).\n";
} else {
    $batchId = $batch->id;
    $items = $conn->table('sltr_print_label_batch_items')->where('batch_id',$batchId)->get();
    echo "Found batch id={$batchId} full_label={$batch->full_label} items=".count($items)."\n";

    // Backup
    file_put_contents(__DIR__.'/_sltr_backup_batch.json', json_encode([
        'batch' => $batch,
        'items' => $items,
    ], JSON_PRETTY_PRINT));

    // file_indexing ids assigned by this batch
    $fiIds = collect($items)->pluck('file_indexing_id')->filter()->unique()->values()->all();

    $conn->transaction(function() use ($conn,$batchId,$fiIds){
        $delItems = $conn->table('sltr_print_label_batch_items')->where('batch_id',$batchId)->delete();
        echo "Deleted {$delItems} batch items.\n";

        // Reset shelf_location only where it still points to A16 (set by this batch)
        if($fiIds){
            $reset = 0;
            foreach(array_chunk($fiIds,100) as $chunk){
                $reset += $conn->table('file_indexings')
                    ->whereIn('id',$chunk)
                    ->where('shelf_location','A16')
                    ->update(['shelf_location'=>null,'updated_at'=>now()]);
            }
            echo "Reset shelf_location on {$reset} file_indexings.\n";
        }

        $delBatch = $conn->table('sltr_print_label_batches')->where('id',$batchId)->delete();
        echo "Deleted {$delBatch} batch row.\n";
    });
}

// Delete rack label A15
$label = $conn->table('sltr_rack_shelf_labels')
    ->whereRaw("UPPER(LTRIM(RTRIM(full_label))) = 'A15'")->first();
if(!$label){
    echo "No rack label A15 found (already deleted?).\n";
} else {
    file_put_contents(__DIR__.'/_sltr_backup_label.json', json_encode($label, JSON_PRETTY_PRINT));
    $del = $conn->table('sltr_rack_shelf_labels')->where('id',$label->id)->delete();
    echo "Deleted rack label A15 (id={$label->id}, counter was {$label->counter}).\n";
}

echo "\nDONE.\n";
