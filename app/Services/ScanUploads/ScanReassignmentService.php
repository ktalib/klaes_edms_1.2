<?php

namespace App\Services\ScanUploads;

use App\Models\FileIndexing;
use App\Models\PageTyping;
use App\Models\ScanReassignmentLog;
use App\Models\Scanning;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ScanReassignmentService
 *
 * Handles reassignment of misplaced scanned documents to correct file numbers.
 * Supports both indexed files (SCAN_UPLOAD) and non-indexed files (BLIND_SCAN).
 */
class ScanReassignmentService
{
    private const BLIND_SCAN_ROOT = 'EDMS/BLIND_SCAN';
    private const SCAN_UPLOAD_ROOT = 'EDMS/SCAN_UPLOAD';

    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Registry mapping from display names to folder names
     */
    private function getRegistryMap(): array
    {
        return [
            'Lands Registry' => 'Lands_Registry',
            'Cadastral Registry' => 'Cadastral_Registry',
            'DCIV Registry' => 'DCIV_Registry',
            'Secret Registry' => 'Secret_Registry',
            'KANGIS Registry' => 'KANGIS_Registry',
            'SLTR Registry' => 'SLTR_Registry',
            'ST Registry' => 'ST_Registry',
            'Deeds Registry' => 'Deeds_Registry',
        ];
    }

    /**
     * Resolve where a file should be moved to based on target file number
     *
     * @param  string  $targetFileNumber
     * @param  string|null  $sourceRegistry  Registry name/ID of the source scan (used for blind_scan subfolder)
     * @return array{
     *   type: 'scan_upload' | 'blind_scan',
     *   absolute_path: string,
     *   relative_path: string,
     *   file_indexing_id?: int|null,
     *   registry?: string,
     *   folder_exists: bool,
     *   existing_scan_count: int
     * }
     */
    public function resolveTargetPath(string $targetFileNumber, ?string $sourceRegistry = null): array
    {
        $targetFileNumber = $this->normalizeFileNumber($targetFileNumber);

        // 1. Check if file_number exists in file_indexings
        $fileIndexing = FileIndexing::on('sqlsrv')
            ->where('file_number', $targetFileNumber)
            ->first();

        if ($fileIndexing) {
            $registryName = $this->resolveRegistryName($fileIndexing->registry);
            $registryMap = $this->getRegistryMap();
            $registryFolder = $registryMap[$registryName] ?? 'Lands_Registry';

            $absolutePath = file_storage_path('app/public/' . self::SCAN_UPLOAD_ROOT . '/' . $registryFolder . '/' . $targetFileNumber);
            $relativeBase = self::SCAN_UPLOAD_ROOT . '/' . $registryFolder . '/' . $targetFileNumber;
            $folderExists = $this->filesystem->isDirectory($absolutePath);
            $scanCount = Scanning::on('sqlsrv')
                ->where('file_indexing_id', $fileIndexing->id)
                ->count();

            return [
                'type' => 'scan_upload',
                'absolute_path' => $absolutePath,
                'relative_path' => $relativeBase,
                'file_indexing_id' => $fileIndexing->id,
                'registry' => $registryName,
                'folder_exists' => $folderExists,
                'existing_scan_count' => $scanCount,
            ];
        }

        // 2. Check if folder exists physically in any registry under SCAN_UPLOAD
        $scanUploadRoot = file_storage_path('app/public/' . self::SCAN_UPLOAD_ROOT);
        if ($this->filesystem->isDirectory($scanUploadRoot)) {
            foreach ($this->getRegistryMap() as $registryFolder) {
                $candidatePath = $scanUploadRoot . DIRECTORY_SEPARATOR . $registryFolder . DIRECTORY_SEPARATOR . $targetFileNumber;
                if ($this->filesystem->isDirectory($candidatePath)) {
                    $scanCount = Scanning::on('sqlsrv')
                        ->where('document_path', 'like', str_replace('\\', '/', $targetFileNumber) . '%')
                        ->count();

                    return [
                        'type' => 'scan_upload',
                        'absolute_path' => $candidatePath,
                        'relative_path' => self::SCAN_UPLOAD_ROOT . '/' . $registryFolder . '/' . $targetFileNumber,
                        'file_indexing_id' => null,
                        'registry' => array_search($registryFolder, $this->getRegistryMap()),
                        'folder_exists' => true,
                        'existing_scan_count' => $scanCount,
                    ];
                }
            }
        }

        // 3. Fall through: will create folder under BLIND_SCAN with registry subfolder
        $registryRawFolder = $this->getBlindScanRegistryFolder($sourceRegistry);
        $blindPath = self::BLIND_SCAN_ROOT . '/' . $registryRawFolder . '/' . $targetFileNumber;
        $absolutePath = file_storage_path('app/public/' . $blindPath);
        $folderExists = $this->filesystem->isDirectory($absolutePath);

        return [
            'type' => 'blind_scan',
            'absolute_path' => $absolutePath,
            'relative_path' => $blindPath,
            'file_indexing_id' => null,
            'registry' => null,
            'folder_exists' => $folderExists,
            'existing_scan_count' => 0,
        ];
    }

    /**
     * Reassign a single scan to a new file number
     *
     * @param  Scanning  $scan
     * @param  string  $targetFileNumber
     * @param  string|null  $reason
     * @return array
     *
     * @throws \Throwable
     */
    public function reassign(Scanning $scan, string $targetFileNumber, ?string $reason = null): array
    {
        $targetFileNumber = $this->normalizeFileNumber($targetFileNumber);

        // Validation: target file number cannot be the same
        $originalFileNumber = $scan->fileIndexing?->file_number;
        if ($originalFileNumber && $originalFileNumber === $targetFileNumber) {
            throw new \InvalidArgumentException(
                "Target file number cannot be the same as current file number ({$targetFileNumber})"
            );
        }

        // Check for PageTyping constraints: if any page typing exists, block reassignment
        if ($scan->pagetypings()->exists()) {
            throw new \InvalidArgumentException(
                "Cannot reassign scan — page typing is in progress. Delete page typing records first."
            );
        }

        return DB::connection('sqlsrv')->transaction(function () use ($scan, $targetFileNumber, $reason, $originalFileNumber) {
            // Resolve target destination (pass source registry for blind_scan subfolder)
            $sourceRegistry = $scan->registry;
            $targetInfo = $this->resolveTargetPath($targetFileNumber, $sourceRegistry);

            // Get current file info
            $currentPath = file_storage_path('app/public/' . $scan->document_path);
            $paperSize = $scan->paper_size ?? 'A4';

            // Verify source file exists before attempting move
            if (!$this->filesystem->exists($currentPath)) {
                throw new \RuntimeException(
                    "Source file not found on disk: {$scan->document_path}. The file may have been deleted or moved outside the system."
                );
            }

            // Ensure destination directory exists with proper paper size subdirectory
            $basePath = $targetInfo['absolute_path'];
            $paperSizePath = $basePath . DIRECTORY_SEPARATOR . $paperSize;
            $this->filesystem->ensureDirectoryExists($paperSizePath, 0775, true);

            // Generate new filename (using the same pattern as BlindScanIngestionService)
            $originalFileName = $scan->original_filename ?? 'document.pdf';
            $newFileName = $this->buildTargetFilename($originalFileName);
            $newAbsolutePath = $paperSizePath . DIRECTORY_SEPARATOR . $newFileName;

            // Move physical file
            if (!$this->filesystem->move($currentPath, $newAbsolutePath)) {
                throw new FileNotFoundException(
                    "Unable to move file from {$currentPath} to {$newAbsolutePath}"
                );
            }

            // Build new relative document path
            $newDocumentPath = $this->convertToPublicPath($newAbsolutePath);

            // Store old values for audit log
            $oldFileIndexingId = $scan->file_indexing_id;
            $oldDocumentPath = $scan->document_path;
            $oldRegistry = $scan->registry;

            // Update scanning record
            if ($targetInfo['file_indexing_id']) {
                $scan->file_indexing_id = $targetInfo['file_indexing_id'];
            }
            $scan->document_path = $newDocumentPath;
            if ($targetInfo['registry']) {
                $scan->registry = $targetInfo['registry'];
            }
            $scan->save();

            // Refresh definition/definition_code if applicable
            if ($targetInfo['file_indexing_id']) {
                $this->refreshDefinitionFields($scan, $targetInfo['file_indexing_id']);
            }

            // Log the reassignment
            $this->logReassignment(
                $scan,
                $originalFileNumber ?? 'Unknown',
                $targetFileNumber,
                $oldFileIndexingId,
                $targetInfo['file_indexing_id'],
                $oldDocumentPath,
                $newDocumentPath,
                $reason
            );

            // Clean up empty directories at source
            $this->cleanupIfEmpty(dirname($currentPath));

            return [
                'scanning_id' => $scan->id,
                'from' => [
                    'file_number' => $originalFileNumber ?? 'Unknown',
                    'file_indexing_id' => $oldFileIndexingId,
                    'path' => $oldDocumentPath,
                    'registry' => $oldRegistry,
                ],
                'to' => [
                    'file_number' => $targetFileNumber,
                    'file_indexing_id' => $targetInfo['file_indexing_id'],
                    'path' => $newDocumentPath,
                    'registry' => $targetInfo['registry'],
                    'destination_type' => $targetInfo['type'],
                ],
                'scan' => $scan->fresh(['fileIndexing', 'uploader']),
            ];
        });
    }

    /**
     * Reassign multiple scans to the same target file number
     *
     * @param  array<int>  $scanIds
     * @param  string  $targetFileNumber
     * @param  string|null  $reason
     * @return array
     */
    public function reassignBatch(array $scanIds, string $targetFileNumber, ?string $reason = null): array
    {
        $results = [
            'success' => [],
            'failed' => [],
            'total' => count($scanIds),
        ];

        foreach ($scanIds as $scanId) {
            try {
                $scan = Scanning::on('sqlsrv')->findOrFail($scanId);
                $result = $this->reassign($scan, $targetFileNumber, $reason);
                $results['success'][] = $result;
            } catch (\Throwable $e) {
                Log::warning('Scan reassignment failed for individual scan', [
                    'scanning_id' => $scanId,
                    'target_file_number' => $targetFileNumber,
                    'error' => $e->getMessage(),
                ]);
                $results['failed'][] = [
                    'scanning_id' => $scanId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get the BLIND_SCAN registry subfolder name (e.g. Lands_Registry_Raw)
     */
    private function getBlindScanRegistryFolder(?string $registry): string
    {
        $name = $this->resolveRegistryName($registry);

        return str_replace(' ', '_', $name) . '_Raw';
    }

    /**
     * Resolve registry value to display name.
     * Handles both numeric IDs (from registries table) and display name strings.
     */
    private function resolveRegistryName($registry): string
    {
        if (empty($registry)) {
            return 'Lands Registry';
        }

        // If it's a numeric ID, look up the display name
        if (is_numeric($registry)) {
            $registryRecord = DB::connection('sqlsrv')
                ->table('registries')
                ->where('id', $registry)
                ->value('name');

            return $registryRecord ?? 'Lands Registry';
        }

        // Already a display name
        return (string) $registry;
    }

    /**
     * Normalize file number (sanitize input)
     */
    private function normalizeFileNumber(string $value): string
    {
        $normalized = str_replace(['\\'], '/', trim($value));
        $normalized = preg_replace('/\s+/', '', $normalized);
        $normalized = str_replace('..', '', $normalized);

        return preg_replace('/[^A-Za-z0-9\-\/]/', '', $normalized) ?? '';
    }

    /**
     * Build a target filename with timestamp + random suffix
     */
    private function buildTargetFilename(string $originalName): string
    {
        $slug = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: 'pdf');

        return $slug . '-' . now()->timestamp . '-' . Str::random(6) . '.' . $extension;
    }

    /**
     * Convert absolute filesystem path to public-relative path
     */
    private function convertToPublicPath(string $absolutePath): string
    {
        $base = file_storage_path('app/public/');
        return str_replace('\\', '/', trim(Str::after($absolutePath, $base), '/'));
    }

    /**
     * Refresh definition and definition_code fields based on file indexing
     */
    private function refreshDefinitionFields(Scanning $scan, int $fileIndexingId): void
    {
        $fileIndexing = FileIndexing::on('sqlsrv')->find($fileIndexingId);
        if ($fileIndexing) {
            $scan->definition = $fileIndexing->file_number;
            $scan->definition_code = $fileIndexing->file_number; // Simplified; adjust if different logic applies
            $scan->save();
        }
    }

    /**
     * Log reassignment action to audit table and Laravel logs
     */
    private function logReassignment(
        Scanning $scan,
        string $fromFileNumber,
        string $toFileNumber,
        ?int $fromFileIndexingId,
        ?int $toFileIndexingId,
        string $fromPath,
        string $toPath,
        ?string $reason
    ): void {
        ScanReassignmentLog::on('sqlsrv')->create([
            'scanning_id' => $scan->id,
            'from_file_number' => $fromFileNumber,
            'to_file_number' => $toFileNumber,
            'from_file_indexing_id' => $fromFileIndexingId,
            'to_file_indexing_id' => $toFileIndexingId,
            'from_path' => $fromPath,
            'to_path' => $toPath,
            'reason' => $reason,
            'reassigned_by' => Auth::id(),
        ]);

        Log::channel('daily')->info('Scan reassigned to different file number', [
            'scanning_id' => $scan->id,
            'from_file_number' => $fromFileNumber,
            'to_file_number' => $toFileNumber,
            'from_file_indexing_id' => $fromFileIndexingId,
            'to_file_indexing_id' => $toFileIndexingId,
            'reason' => $reason,
            'reassigned_by' => Auth::id(),
            'timestamp' => now(),
        ]);
    }

    /**
     * Clean up empty directories (safety: never delete root)
     */
    private function cleanupIfEmpty(string $folder): void
    {
        $blindRoot = file_storage_path('app/public/' . self::BLIND_SCAN_ROOT);
        $scanUploadRoot = file_storage_path('app/public/' . self::SCAN_UPLOAD_ROOT);

        // Safety checks
        if (!$this->filesystem->isDirectory($folder)) {
            return;
        }

        $realPath = realpath($folder);
        $realBlindRoot = realpath($blindRoot);
        $realScanUploadRoot = realpath($scanUploadRoot);

        if ($realPath === $realBlindRoot || $realPath === $realScanUploadRoot) {
            return; // Never delete root directories
        }

        // Only delete if empty
        $files = $this->filesystem->allFiles($folder);
        if (empty($files)) {
            $this->filesystem->deleteDirectory($folder);
            Log::info("Cleaned up empty folder: {$folder}");
        }
    }
}
