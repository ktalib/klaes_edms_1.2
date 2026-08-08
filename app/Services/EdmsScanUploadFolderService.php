<?php

namespace App\Services;

use App\Models\FileIndexing;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The file's scan folder under storage/app/public/EDMS/SCAN_UPLOAD.
 *
 * Layout, which the scanning and page-typing modules already rely on:
 *
 *      EDMS/SCAN_UPLOAD/{Registry_Slug}/{FILE NUMBER}/
 *
 * Created as early as possible so the folder is waiting before anyone scans into
 * it — at commissioning (MLS and ST) and again at indexing for files that were
 * never commissioned through KLAES. Previously it only appeared when the first
 * page was uploaded (ScanningController), or from a copy of this logic inside
 * MlsFileNoController that hardcoded Lands_Registry.
 *
 * The registry slug MUST match what the readers compute, or scans get written to
 * one folder and looked for in another — PageTypingController::getRegistrySlug()
 * is the map this mirrors, and the paper-size subfolder (A4/A3) is deliberately
 * NOT created here: the uploader makes it, and an empty A4 folder would imply a
 * scan session that never happened.
 */
class EdmsScanUploadFolderService
{
    public const BASE_PATH = 'EDMS/SCAN_UPLOAD';

    /** Registry name => folder slug. Mirrors PageTypingController::getRegistrySlug(). */
    private const REGISTRY_SLUGS = [
        'Lands Registry'     => 'Lands_Registry',
        'Cadastral Registry' => 'Cadastral_Registry',
        'DCIV Registry'      => 'DCIV_Registry',
        'Secret Registry'    => 'Secret_Registry',
        'KANGIS Registry'    => 'KANGIS_Registry',
        'SLTR Registry'      => 'SLTR_Registry',
        'ST Registry'        => 'ST_Registry',
        'Deeds Registry'     => 'Deeds_Registry',
    ];

    /**
     * Create the file's scan folder if it is not already there.
     *
     * Best-effort: the file is already indexed by the time this runs, so a storage
     * failure is logged and reported, never thrown.
     *
     * @return array{created:bool, existed:bool, path:?string, registry:?string, reason:string}
     */
    public function ensureForIndexing(FileIndexing $record): array
    {
        return $this->ensure(
            (string) ($record->file_number ?: $record->temp_file_no ?: ''),
            $this->registryNameFor($record),
            ['file_indexing_id' => $record->id]
        );
    }

    /**
     * Create the scan folder for a bare file number + registry.
     *
     * Used by the commissioning flows (MLS and ST), where the file has no
     * file_indexings row yet — the folder is made the moment the number is issued,
     * so scanning can begin before the file is ever indexed.
     *
     * @param  array<string,mixed>  $logContext extra fields for the log lines
     * @return array{created:bool, existed:bool, path:?string, registry:?string, reason:string}
     */
    public function ensure(string $fileNumber, ?string $registryName = null, array $logContext = []): array
    {
        $fileNumber = trim($fileNumber);

        if ($fileNumber === '') {
            return $this->outcome(false, false, null, null, 'no_file_number');
        }

        $folderName = $this->folderName($fileNumber);
        if ($folderName === '') {
            return $this->outcome(false, false, null, null, 'unusable_file_number');
        }

        $slug = $this->registrySlug($registryName);
        $path = self::BASE_PATH . '/' . $slug . '/' . $folderName;

        try {
            if (Storage::disk('public')->exists($path)) {
                return $this->outcome(false, true, $path, $slug, 'already_exists');
            }

            Storage::disk('public')->makeDirectory($path);

            Log::info('EdmsScanUploadFolderService - created scan folder', array_merge($logContext, [
                'file_number' => $fileNumber,
                'path'        => $path,
            ]));

            return $this->outcome(true, false, $path, $slug, 'created');
        } catch (\Throwable $e) {
            Log::warning('EdmsScanUploadFolderService - could not create scan folder', array_merge($logContext, [
                'file_number' => $fileNumber,
                'path'        => $path,
                'error'       => $e->getMessage(),
            ]));

            return $this->outcome(false, false, $path, $slug, 'error');
        }
    }

    /**
     * The registry this file's scans belong under.
     *
     * general_registry carries the readable name ("Lands Registry", "KANGIS
     * Registry"); the older `registry` column mostly holds land-use ids (1/2/3)
     * or short codes, so it is only a fallback — and normalizeRegistry() maps the
     * numeric ids onto Lands Registry the way the rest of the app does.
     */
    private function registryNameFor(FileIndexing $record): string
    {
        foreach ([$record->general_registry ?? null, $record->registry ?? null] as $candidate) {
            $value = $this->collapseSpaces((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return 'Lands Registry';
    }

    /**
     * Folder slug for a registry name. Unknown registries (SIT, Survey, Physical
     * Planning …) fall through to the same character-scrub the readers apply, so
     * a registry that is not in the map still resolves to one consistent folder.
     */
    public function registrySlug(?string $registry): string
    {
        $normalized = $this->normalizeRegistry($registry);

        return self::REGISTRY_SLUGS[$normalized]
            ?? preg_replace('/[^A-Za-z0-9_\-]/', '_', $normalized);
    }

    /** Mirrors PageTypingController::normalizeRegistry(). */
    private function normalizeRegistry(?string $registry): string
    {
        $registry = $this->collapseSpaces((string) $registry);

        if ($registry === '') {
            return 'Lands Registry';
        }

        // 1/2/3 are land-use ids on the legacy `registry` column, not registries.
        if (in_array($registry, ['1', '2', '3'], true)) {
            return 'Lands Registry';
        }

        // "Land Registry" / "Land  Registry" are typo variants of the real name;
        // without this they would each mint their own folder.
        if (preg_match('/^Lands?\s+Registry$/i', $registry)) {
            return 'Lands Registry';
        }

        return $registry;
    }

    /**
     * Filesystem-safe folder name for a file number, matching the sanitiser the
     * commissioning path already uses (MlsFileNoController). A normal land number
     * (RES-2024-10, AG-2026-7(T)) passes through untouched; only characters
     * Windows forbids in a path are folded to "-".
     */
    public function folderName(string $fileNumber): string
    {
        $safe = preg_replace('/[\\\\\/\:\*\?"<>\|]+/', '-', trim($fileNumber));

        return trim((string) $safe);
    }

    /** @return array{created:bool, existed:bool, path:?string, registry:?string, reason:string} */
    private function outcome(bool $created, bool $existed, ?string $path, ?string $registry, string $reason): array
    {
        return [
            'created'  => $created,
            'existed'  => $existed,
            'path'     => $path,
            'registry' => $registry,
            'reason'   => $reason,
        ];
    }

    private function collapseSpaces(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
