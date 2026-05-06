<?php
// Load Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Models\FileIndexing;
use App\Models\Scanning;
use App\Models\PageTyping;
use Illuminate\Support\Facades\DB;

header('Content-Type: text/plain');

echo "--- Pending Files Sorted by Latest Scan Date ---\n\n";

$query = FileIndexing::on('sqlsrv')
    ->whereHas('scannings')
    ->whereDoesntHave('pagetypings');

$total = $query->count();
echo "Total Pending Files: $total\n\n";

$files = $query->select('file_indexings.*')
    ->addSelect(['latest_scan_at' => Scanning::select('created_at')
        ->whereColumn('file_indexing_id', 'file_indexings.id')
        ->orderBy('created_at', 'desc')
        ->limit(1)
    ])
    ->orderBy('latest_scan_at', 'desc')
    ->limit(15)
    ->get();

foreach ($files as $index => $file) {
    echo ($index + 1) . ". File Number: " . $file->file_number . "\n";
    echo "   File Title: " . $file->file_title . "\n";
    echo "   Index Date: " . ($file->created_at ? $file->created_at->format('Y-m-d H:i:s') : 'N/A') . "\n";
    echo "   Latest Scan: " . $file->latest_scan_at . "\n";
    echo "   Scannings Count: " . $file->scannings()->count() . "\n";
    echo "-------------------------------------------\n";
}

echo "\n\n--- Checking specific files from screenshot ---\n\n";
$specificFiles = ['RES-1981-500', 'RES-1981-261', 'RES-1981-40', 'COM-2025-43'];

foreach ($specificFiles as $fileNo) {
    $file = FileIndexing::on('sqlsrv')->where('file_number', 'LIKE', '%' . $fileNo . '%')->first();
    if ($file) {
        echo "FOUND: $fileNo (Actual DB File Number: [" . $file->file_number . "])\n";
        echo "   ID: " . $file->id . "\n";
        echo "   Latest Scan: " . Scanning::where('file_indexing_id', $file->id)->orderBy('created_at', 'desc')->value('created_at') . "\n";
        echo "   Page Typings Count: " . PageTyping::where('file_indexing_id', $file->id)->count() . "\n";
    } else {
        echo "NOT FOUND: $fileNo\n";
    }
}
