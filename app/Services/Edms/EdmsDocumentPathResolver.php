<?php

namespace App\Services\Edms;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * EdmsDocumentPathResolver
 *
 * Single source of truth for the EDMS storage layouts and for turning a stored
 * (possibly stale) document path into something that actually exists on disk.
 *
 * Storage layout:
 *   EDMS/SCAN_UPLOAD/{registry_slug}/{file_number}/{PAPER}/{file}   <- authoritative original
 *   EDMS/PAGETYPING/{registry_slug}/{file_number}/{PAPER}/{file}    <- derived copy (page typing)
 *   EDMS/ARCHIVE_Doc_WARE/{registry_slug}/{file_number}/{PAPER}/{file} <- derived copy (archive)
 *   EDMS/BLIND_SCAN/{Registry_Name}_Raw/{file_number}/{PAPER}/{file}   <- pre-ingestion
 *
 * Page typing COPIES into PAGETYPING/ARCHIVE; it must never move the original
 * out of SCAN_UPLOAD. `scannings.document_path` always names the SCAN_UPLOAD
 * original; `pagetypings.file_path` names the typed copy.
 */
class EdmsDocumentPathResolver
{
    public const SCAN_UPLOAD_ROOT = 'EDMS/SCAN_UPLOAD';
    public const PAGETYPING_ROOT = 'EDMS/PAGETYPING';
    public const ARCHIVE_ROOT = 'EDMS/ARCHIVE_Doc_WARE';
    public const BLIND_SCAN_ROOT = 'EDMS/BLIND_SCAN';

    /**
     * Historical prefixes that have leaked into stored paths over time.
     */
    private const LEGACY_PREFIXES = [
        'storage/app/public/',
        'app/public/',
        'public/',
        'storage/',
    ];

    /**
     * Registry display name => folder segment.
     */
    public function registryMap(): array
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
     * Short forms actually stored in file_indexings.registry ("SLTR", "KANGIS",
     * "DCIV", "Survey"), mapped to the canonical display name. Without this they
     * slug to SLTR/KANGIS/DCIV, which are not the folders on disk.
     */
    private const REGISTRY_ALIASES = [
        'land' => 'Lands Registry',
        'lands' => 'Lands Registry',
        'land registry' => 'Lands Registry',
        'cadastral' => 'Cadastral Registry',
        'cadastre' => 'Cadastral Registry',
        'dciv' => 'DCIV Registry',
        'secret' => 'Secret Registry',
        'kangis' => 'KANGIS Registry',
        'sltr' => 'SLTR Registry',
        'st' => 'ST Registry',
        'sectional titling' => 'ST Registry',
        'deeds' => 'Deeds Registry',
        'survey' => 'Survey Registry',
        'sit' => 'SIT Registry',
        'physical planning' => 'Physical Planning Registry',
    ];

    /**
     * Resolve a registry value (numeric id, short form or display name) to its
     * canonical display name.
     */
    public function registryName($registry): string
    {
        if (empty($registry)) {
            return 'Lands Registry';
        }

        // Legacy convention: 1=Commercial, 2=Residential, 3=Agricultural all live under Lands.
        if (in_array((string) $registry, ['1', '2', '3'], true)) {
            return 'Lands Registry';
        }

        if (is_numeric($registry)) {
            $name = DB::connection('sqlsrv')
                ->table('registries')
                ->where('id', $registry)
                ->value('name');

            $registry = $name ?: 'Lands Registry';
        }

        $name = preg_replace('/\s+/', ' ', trim((string) $registry));
        $key = strtolower(preg_replace('/\s+registry$/i', '', $name));

        return self::REGISTRY_ALIASES[$key] ?? $name;
    }

    /**
     * Folder segment for a registry, e.g. "Lands_Registry".
     */
    public function registrySlug($registry): string
    {
        $name = $this->registryName($registry);
        $map = $this->registryMap();

        if (isset($map[$name])) {
            return $map[$name];
        }

        // registries.name carries stray whitespace ("Land  Registry", "Physical
        // Planning Registry "), which would otherwise slug to "Land__Registry" /
        // "Physical_Planning_Registry_" and miss the real folder on disk.
        $name = preg_replace('/\s+/', ' ', trim($name));

        // registries.name for id 1 is "Land  Registry"; the folder on disk has
        // always been Lands_Registry.
        if (strcasecmp($name, 'Land Registry') === 0) {
            return 'Lands_Registry';
        }

        if (isset($map[$name])) {
            return $map[$name];
        }

        return trim(preg_replace('/[^A-Za-z0-9_\-]/', '_', $name), '_');
    }

    /**
     * BLIND_SCAN registry subfolder, e.g. "Lands_Registry_Raw".
     */
    public function blindScanRegistryFolder($registry): string
    {
        return str_replace(' ', '_', $this->registryName($registry)) . '_Raw';
    }

    private function paperSize($paperSize): string
    {
        return !empty($paperSize) ? strtoupper((string) $paperSize) : 'A4';
    }

    public function scanUploadPath($registry, string $fileNumber, $paperSize, string $fileName): string
    {
        return self::SCAN_UPLOAD_ROOT . '/' . $this->registrySlug($registry) . '/' . $fileNumber
            . '/' . $this->paperSize($paperSize) . '/' . $fileName;
    }

    public function scanUploadFolder($registry, string $fileNumber, $paperSize = null): string
    {
        $path = self::SCAN_UPLOAD_ROOT . '/' . $this->registrySlug($registry) . '/' . $fileNumber;

        return $paperSize ? $path . '/' . $this->paperSize($paperSize) : $path;
    }

    public function pageTypingPath($registry, string $fileNumber, $paperSize, string $fileName): string
    {
        return self::PAGETYPING_ROOT . '/' . $this->registrySlug($registry) . '/' . $fileNumber
            . '/' . $this->paperSize($paperSize) . '/' . $fileName;
    }

    public function archivePath($registry, string $fileNumber, $paperSize, string $fileName): string
    {
        return self::ARCHIVE_ROOT . '/' . $this->registrySlug($registry) . '/' . $fileNumber
            . '/' . $this->paperSize($paperSize) . '/' . $fileName;
    }

    public function blindScanPath($registry, string $fileNumber, $paperSize, string $fileName): string
    {
        return self::BLIND_SCAN_ROOT . '/' . $this->blindScanRegistryFolder($registry) . '/' . $fileNumber
            . '/' . $this->paperSize($paperSize) . '/' . $fileName;
    }

    /**
     * Absolute filesystem path for a public-disk relative path.
     */
    public function absolute(string $relativePath): string
    {
        return file_storage_path('app/public/' . ltrim(str_replace('\\', '/', $relativePath), '/'));
    }

    /**
     * Convert an absolute filesystem path back to a public-disk relative path.
     */
    public function toRelative(string $absolutePath): string
    {
        $base = str_replace('\\', '/', file_storage_path('app/public/'));
        $normalized = str_replace('\\', '/', $absolutePath);

        if (stripos($normalized, $base) === 0) {
            $normalized = substr($normalized, strlen($base));
        }

        return trim($normalized, '/');
    }

    /**
     * Strip leading slashes, normalise separators and remove legacy path prefixes.
     */
    public function normalize(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        foreach (self::LEGACY_PREFIXES as $prefix) {
            if (stripos($normalized, $prefix) === 0) {
                $normalized = substr($normalized, strlen($prefix));
                break;
            }
        }

        return ltrim($normalized, '/') ?: null;
    }

    /**
     * Build the ordered list of candidate locations for a document.
     *
     * @param  array  $context  ['registry' =>, 'file_number' =>, 'paper_size' =>, 'file_name' =>]
     * @return string[]
     */
    public function candidates(?string $storedPath, array $context = []): array
    {
        $candidates = [];

        $normalized = $this->normalize($storedPath);
        if ($normalized) {
            $candidates[] = $normalized;
        }

        $fileNumber = $context['file_number'] ?? null;
        $registry = $context['registry'] ?? null;
        $paperSize = $context['paper_size'] ?? null;

        // Filename: prefer an explicit one, otherwise reuse the stored path's basename.
        $fileName = $context['file_name'] ?? ($normalized ? basename($normalized) : null);

        if ($fileNumber && $fileName) {
            $fileNumber = (string) $fileNumber;

            $candidates[] = $this->scanUploadPath($registry, $fileNumber, $paperSize, $fileName);
            $candidates[] = $this->pageTypingPath($registry, $fileNumber, $paperSize, $fileName);
            $candidates[] = $this->archivePath($registry, $fileNumber, $paperSize, $fileName);

            // Legacy archive layout written by PageTypingQCController (no slug, no paper size).
            $candidates[] = self::ARCHIVE_ROOT . '/' . $fileNumber . '/' . $fileName;

            $candidates[] = $this->blindScanPath($registry, $fileNumber, $paperSize, $fileName);

            // Legacy layouts without the paper-size segment.
            $slug = $this->registrySlug($registry);
            $candidates[] = self::SCAN_UPLOAD_ROOT . '/' . $slug . '/' . $fileNumber . '/' . $fileName;
            $candidates[] = self::PAGETYPING_ROOT . '/' . $slug . '/' . $fileNumber . '/' . $fileName;

            // Legacy layouts without the registry segment.
            $candidates[] = self::SCAN_UPLOAD_ROOT . '/' . $fileNumber . '/' . $this->paperSize($paperSize) . '/' . $fileName;
            $candidates[] = self::SCAN_UPLOAD_ROOT . '/' . $fileNumber . '/' . $fileName;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * First candidate location that exists on the public disk, or null.
     */
    public function resolveRelative(?string $storedPath, array $context = []): ?string
    {
        foreach ($this->candidates($storedPath, $context) as $candidate) {
            if (Storage::disk('public')->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Public URL for the first candidate that exists, or null when nothing is on disk.
     */
    public function resolveUrl(?string $storedPath, array $context = []): ?string
    {
        $relative = $this->resolveRelative($storedPath, $context);
        if ($relative) {
            return Storage::disk('public')->url($relative);
        }

        // Last-ditch: some very old records point straight into public/.
        $raw = ltrim(str_replace('\\', '/', (string) $storedPath), '/');
        if ($raw !== '' && file_exists(public_path($raw))) {
            return asset($raw);
        }

        return null;
    }

    /**
     * Build the resolver context from a Scanning-like record.
     *
     * @param  object|null  $scanning  Expects ->paper_size, ->registry, ->original_filename and ->fileIndexing
     */
    public function contextFromScanning($scanning, $fileIndexing = null): array
    {
        if (!$scanning) {
            return [];
        }

        $fileIndexing = $fileIndexing ?: ($scanning->fileIndexing ?? null);

        return [
            'file_number' => $fileIndexing->file_number ?? null,
            'registry' => $scanning->registry ?? ($fileIndexing->registry ?? null),
            'paper_size' => $scanning->paper_size ?? null,
            'file_name' => $scanning->original_filename ?? null,
        ];
    }

    /**
     * Copy a file on the public disk, creating the destination folder.
     * Never moves, never deletes the source.
     *
     * @return bool true when the destination holds the file afterwards
     */
    public function copyWithin(string $sourceRelative, string $destinationRelative, bool $overwrite = false): bool
    {
        $source = $this->absolute($sourceRelative);
        $destination = $this->absolute($destinationRelative);

        if (!file_exists($source)) {
            return false;
        }

        if ($source === $destination) {
            return true;
        }

        if (file_exists($destination) && !$overwrite) {
            return true;
        }

        Storage::disk('public')->makeDirectory(dirname($destinationRelative));

        return copy($source, $destination);
    }
}
