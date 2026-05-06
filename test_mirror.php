<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Run the mirror manually for ID 47315
$fileIndexing = App\Models\FileIndexing::find(47315);
if (!$fileIndexing) {
    die("File indexing 47315 not found.\n");
}

$controller = new class(app(\App\Services\PropertyIdAllocationService::class)) extends App\Http\Controllers\FileIndexingController {
    public function runMirror($f) {
        $validated = [
            'general_registry' => $f->general_registry,
            'land_use_id' => 1,
            'purpose_id' => 1,
            'customer_type' => 'Individual'
        ];
        
        try {
            $this->mirrorCommissioningModules($f, $validated, []);
            echo "Mirroring completed without exceptions.\n";
        } catch (\Throwable $e) {
            echo "Exception caught: " . $e->getMessage() . "\n";
            echo $e->getTraceAsString();
        }
    }
    
    // override Log::warning and Log::info to echo them directly
    protected function mirrorCommissioningModules(\App\Models\FileIndexing $fileIndexing, array $validated, array $relatedFileNos): void
    {
        $registryCandidate = $fileIndexing->general_registry
            ?? ($validated['general_registry'] ?? null)
            ?? $fileIndexing->registry
            ?? ($validated['registry'] ?? null);

        if (!$registryCandidate) {
            echo "No registry candidate found.\n";
            return;
        }
        
        echo "Registry candidate: {$registryCandidate}\n";

        try {
            if ($this->registryMatchesSurvey($registryCandidate)) {
                echo "Matches Survey.\n";
                $this->syncGknCommissionRecord($fileIndexing, $validated);
                return;
            }

            if ($this->registryMatchesDciv($registryCandidate)) {
                echo "Matches DCIV.\n";
                $this->syncDcivCommissionRecord($fileIndexing, $validated, $relatedFileNos);
            }
        } catch (\Throwable $exception) {
            echo "Sync Failed: {$exception->getMessage()}\n";
        }
    }
    
    protected function syncGknCommissionRecord(\App\Models\FileIndexing $fileIndexing, array $validated): void
    {
        echo "syncGknCommissionRecord called.\n";
        $fileNumber = $fileIndexing->file_number;
        if (empty($fileNumber)) {
            echo "File number is empty.\n";
            return;
        }

        if (\App\Models\GknFileNo::where('full_file_number', $fileNumber)->exists()) {
            echo "GKN File No already exists.\n";
            return;
        }

        $components = $this->extractGknComponents($fileNumber);
        $timestamp = now();

        $payload = [
            'batch_no' => null,
            'year' => $components['year'],
            'serial_number' => $components['serial'],
            'full_file_number' => $fileNumber,
            'file_name' => $fileIndexing->file_title,
            'plot_no' => $fileIndexing->plot_number,
            'tp_no' => $fileIndexing->tp_no,
            'location' => $fileIndexing->location,
            'lga' => $fileIndexing->lga,
            'tracking_id' => $fileIndexing->tracking_id,
            'created_by' => 0,
            'commissioning_date' => $timestamp,
            'commissioning_time' => $timestamp->format('H:i:s'),
            'land_use_id' => $validated['land_use_id'] ?? null,
            'purpose_id' => $validated['purpose_id'] ?? null,
            'type' => $components['type'],
            'customer_type' => $validated['customer_type'] ?? 'Individual',
        ];

        echo "Creating GknFileNo with payload: \n";
        print_r($payload);

        \App\Models\GknFileNo::create($payload);

        echo "Mirrored successfully to GKN.\n";
    }
};

$gkn = App\Models\GknFileNo::where('full_file_number', 'DCIV-2026-3')->get()->toArray();
print_r($gkn);
$dciv = App\Models\DcivFileNo::where('full_file_number', 'DCIV-2026-3')->get()->toArray();
print_r($dciv);
