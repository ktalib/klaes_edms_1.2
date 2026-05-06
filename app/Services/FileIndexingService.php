<?php

namespace App\Services;

use App\Models\FileIndexing;
use App\Models\MlsFileNo;
use Illuminate\Support\Facades\Log;

class FileIndexingService
{
    /**
     * Create a file_indexings record from an MLS file number
     *
     * @param MlsFileNo $mlsFile
     * @return FileIndexing
     */
    public function createFromMlsFileNumber(MlsFileNo $mlsFile, ?string $relatedFileNo = null): FileIndexing
    {
        $data = [
            'tracking_id' => $mlsFile->tracking_id,
            'file_number' => $mlsFile->full_file_number,
            'file_title' => $mlsFile->file_name,
            'land_use_type' => $this->extractLandUseType($mlsFile->land_use),
            'plot_number' => $mlsFile->plot_no,
            'tp_no' => $mlsFile->tp_no,
            'location' => $mlsFile->location,
            'lga' => $mlsFile->lga,
            'created_by' => $mlsFile->created_by,
            'current_holder' => $mlsFile->file_name,
            'original_holder' => $mlsFile->file_name,
            'workflow_status' => 'indexed',
            'is_updated' => false,
            'is_deleted' => false,
        ];

        if ($relatedFileNo) {
            $data['related_fileno'] = json_encode([$relatedFileNo]);
        }

        $fileIndexing = FileIndexing::create($data);

        Log::info('File automatically indexed', [
            'mls_file_id' => $mlsFile->id,
            'file_indexing_id' => $fileIndexing->id,
            'file_number' => $mlsFile->full_file_number
        ]);

        return $fileIndexing;
    }

    /**
     * Create a file_indexings record from raw data array
     *
     * @param array $data
     * @return FileIndexing
     */
    public function createFromFileNumberData(array $data): FileIndexing
    {
        $indexingData = [
            'tracking_id' => $data['tracking_id'] ?? null,
            'file_number' => $data['file_number'],
            'file_title' => $data['file_title'] ?? null,
            'land_use_type' => isset($data['land_use']) ? $this->extractLandUseType($data['land_use']) : null,
            'plot_number' => $data['plot_number'] ?? null,
            'tp_no' => $data['tp_no'] ?? null,
            'location' => $data['location'] ?? null,
            'lga' => $data['lga'] ?? null,
            'created_by' => $data['created_by'] ?? 'System',
            'current_holder' => $data['file_title'] ?? null,
            'original_holder' => $data['file_title'] ?? null,
            'workflow_status' => 'indexed',
            'is_updated' => false,
            'is_deleted' => false,
        ];

        $fileIndexing = FileIndexing::create($indexingData);

        Log::info('File indexed from data array', [
            'file_indexing_id' => $fileIndexing->id,
            'file_number' => $indexingData['file_number']
        ]);

        return $fileIndexing;
    }

    /**
     * Map MLS land use codes to file_indexings land_use_type
     *
     * @param string $landUse
     * @return string
     */
    private function extractLandUseType(string $landUse): string
    {
        // Remove CON- prefix if present (conversion files)
        $baseLandUse = str_replace('CON-', '', $landUse);

        // Remove -RC suffix if present (recertification files)
        $baseLandUse = str_replace('-RC', '', $baseLandUse);

        // Map to full land use type names
        $mapping = [
            'RES' => 'Residential',
            'COM' => 'Commercial',
            'IND' => 'Industrial',
            'AG' => 'Agricultural',
        ];

        return $mapping[$baseLandUse] ?? $landUse; // Return original if not found
    }

    /**
     * Check if a file number already exists in file_indexings
     *
     * @param string $fileNumber
     * @return bool
     */
    public function fileNumberExists(string $fileNumber): bool
    {
        return FileIndexing::where('file_number', $fileNumber)->exists();
    }

    /**
     * Get or create file indexing record
     * Useful to avoid duplicates
     *
     * @param string $fileNumber
     * @param array $data
     * @return FileIndexing
     */
    public function getOrCreate(string $fileNumber, array $data): FileIndexing
    {
        $existing = FileIndexing::where('file_number', $fileNumber)->first();

        if ($existing) {
            Log::info('File indexing already exists', [
                'file_number' => $fileNumber,
                'file_indexing_id' => $existing->id
            ]);
            return $existing;
        }

        return $this->createFromFileNumberData(array_merge($data, ['file_number' => $fileNumber]));
    }
}
