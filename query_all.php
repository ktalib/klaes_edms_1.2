<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$tables = ['pra', 'instrument_capture', 'file_history_staging', 'CofO_staging', 'PropID_Master', 'deed_registrations'];
$search = 'RES-2000-4828';

foreach ($tables as $table) {
    try {
        $cols = Illuminate\Support\Facades\Schema::connection('sqlsrv')->getColumnListing($table);
        $query = Illuminate\Support\Facades\DB::connection('sqlsrv')->table($table);
        
        $hasMatches = false;
        $query->where(function ($q) use ($cols, $search) {
            foreach ($cols as $col) {
                if (in_array(strtolower($col), ['mlsfno', 'fileno', 'temp_fileno', 'kangisfileno', 'primary_file_number'])) {
                    $q->orWhere($col, $search);
                }
            }
        });
        
        $data = $query->get();
        if ($data->count() > 0) {
            echo strtoupper($table) . ":\n";
            echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
        }
    } catch (\Exception $e) {}
}
