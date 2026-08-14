<?php

namespace App\Services\Edms;

use App\Models\FileIndexing;
use App\Models\PageTyping;
use App\Models\ScanReassignmentLog;
use App\Models\Scanning;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EdmsRegistryTransferService
 *
 * Moves a file's documents from one registry to another across every EDMS tree:
 *
 *   EDMS/SCAN_UPLOAD/{registry}/{file_number}/...      (the original)
 *   EDMS/PAGETYPING/{registry}/{file_number}/...       (typed copies)
 *   EDMS/ARCHIVE_Doc_WARE/{registry}/{file_number}/... (Doc-WARE archive)
 *
 * A registry lives in the folder path, so changing a file's registry without
 * moving its documents strands every one of them. This keeps the two in step:
 * the physical files move, and file_indexings / scannings / pagetypings are all
 * re-pointed in one transaction.
 *
 * Files are MOVED here, not copied — unlike page typing, which copies from
 * SCAN_UPLOAD into the derived trees. A registry transfer is a correction: the
 * documents did not belong under the old registry at all.
 */
class EdmsRegistryTransferService
{
    /** @var EdmsDocumentPathResolver */
    private $paths;

    /** @var Filesystem */
    private $filesystem;

    public function __construct(EdmsDocumentPathResolver $paths, Filesystem $filesystem)
    {
        $this->paths = $paths;
        $this->filesystem = $filesystem;
    }

    /**
     * Registries a file can be moved to, as [display_name => folder_slug].
     */
    public function availableRegistries(): array
    {
        return $this->paths->registryMap();
    }

    /**
     * Work out exactly what a transfer would do, touching nothing.
     *
     * @return array{
     *   file_number: string,
     *   from_registry: string,
     *   to_registry: string,
     *   from_slug: string,
     *   to_slug: string,
     *   scannings: array,
     *   pagetypings: array,
     *   counts: array,
     *   blockers: string[]
     * }
     */
    public function preview(FileIndexing $file, string $targetRegistry): array
    {
        $fromRegistry = $this->paths->registryName($file->registry);
        $toRegistry = $this->paths->registryName($targetRegistry);

        $blockers = [];
        if ($this->paths->registrySlug($fromRegistry) === $this->paths->registrySlug($toRegistry)) {
            $blockers[] = "File {$file->file_number} is already in {$toRegistry}.";
        }

        $scannings = [];
        $missing = 0;
        foreach ($this->scanningsFor($file) as $scan) {
            $context = $this->paths->contextFromScanning($scan, $file);
            $resolved = $this->paths->resolveRelative($scan->document_path, $context);

            $target = $resolved
                ? $this->retargetRegistry($resolved, $fromRegistry, $toRegistry, $file->file_number, $scan->paper_size)
                : null;

            if (!$resolved) {
                $missing++;
            }

            $scannings[] = [
                'id' => $scan->id,
                'original_filename' => $scan->original_filename,
                'stored_path' => $scan->document_path,
                'resolved_path' => $resolved,
                'target_path' => $target,
                'on_disk' => (bool) $resolved,
                'conflict' => $target ? $this->filesystem->exists($this->paths->absolute($target)) : false,
            ];
        }

        $pagetypings = [];
        foreach ($this->pageTypingsFor($file) as $pt) {
            $resolved = $this->paths->resolveRelative($pt->file_path, [
                'file_number' => $file->file_number,
                'registry' => $pt->registry ?? $file->registry,
                'paper_size' => optional($pt->scanning)->paper_size,
            ]);

            $paperSize = optional($pt->scanning)->paper_size;
            $target = $resolved
                ? $this->retargetRegistry($resolved, $fromRegistry, $toRegistry, $file->file_number, $paperSize)
                : null;

            if (!$resolved) {
                $missing++;
            }

            $pagetypings[] = [
                'id' => $pt->id,
                'page_number' => $pt->page_number,
                'definition_code' => $pt->definition_code,
                'stored_path' => $pt->file_path,
                'resolved_path' => $resolved,
                'target_path' => $target,
                'on_disk' => (bool) $resolved,
                'conflict' => $target ? $this->filesystem->exists($this->paths->absolute($target)) : false,
            ];
        }

        $conflicts = collect($scannings)->where('conflict', true)->count()
            + collect($pagetypings)->where('conflict', true)->count();

        if ($conflicts > 0) {
            $blockers[] = "{$conflicts} document(s) already exist at the destination under {$toRegistry}. "
                . 'Resolve the duplicates before moving.';
        }

        return [
            'file_indexing_id' => $file->id,
            'file_number' => $file->file_number,
            'from_registry' => $fromRegistry,
            'to_registry' => $toRegistry,
            'from_slug' => $this->paths->registrySlug($fromRegistry),
            'to_slug' => $this->paths->registrySlug($toRegistry),
            'scannings' => $scannings,
            'pagetypings' => $pagetypings,
            'counts' => [
                'scannings' => count($scannings),
                'pagetypings' => count($pagetypings),
                'missing_on_disk' => $missing,
                'conflicts' => $conflicts,
            ],
            'blockers' => $blockers,
        ];
    }

    /**
     * Move a file's documents (and its records) to another registry.
     *
     * @param  bool  $dryRun  when true, nothing is written — the preview is returned as-is
     *
     * @throws \InvalidArgumentException when the transfer is blocked
     */
    public function transfer(FileIndexing $file, string $targetRegistry, ?string $reason = null, bool $dryRun = false, ?int $actorId = null): array
    {
        $preview = $this->preview($file, $targetRegistry);

        if (!empty($preview['blockers'])) {
            throw new \InvalidArgumentException(implode(' ', $preview['blockers']));
        }

        if ($dryRun) {
            return $preview + ['dry_run' => true, 'moved' => ['files' => 0, 'scannings' => 0, 'pagetypings' => 0]];
        }

        $toRegistry = $preview['to_registry'];
        $fromRegistry = $preview['from_registry'];
        $actorId = $this->resolveActorId($file, $actorId);

        // The filesystem is not transactional: if the DB rolls back after files
        // have moved, the records point at paths that no longer hold anything.
        // Every move and creation is journalled so it can be undone by hand.
        $journal = ['moves' => [], 'created' => []];

        try {
            return DB::connection('sqlsrv')->transaction(function () use ($file, $preview, $fromRegistry, $toRegistry, $reason, $actorId, &$journal) {
                return $this->applyTransfer($file, $preview, $fromRegistry, $toRegistry, $reason, $actorId, $journal);
            });
        } catch (\Throwable $e) {
            $this->undo($journal);

            throw $e;
        }
    }

    /**
     * The body of a transfer. Runs inside the DB transaction; every filesystem
     * change it makes is recorded in $journal so a failure can be unwound.
     */
    private function applyTransfer(FileIndexing $file, array $preview, string $fromRegistry, string $toRegistry, ?string $reason, int $actorId, array &$journal): array
    {
        $filesMoved = 0;
        $sourceFolders = [];

        // --- SCAN_UPLOAD originals -------------------------------------
        $scannings = $this->scanningsFor($file)->keyBy('id');
        foreach ($preview['scannings'] as $row) {
            $scan = $scannings->get($row['id']);
            if (!$scan) {
                continue;
            }

            if ($row['target_path']) {
                if ($this->moveFile($row['resolved_path'], $row['target_path'], $sourceFolders, $journal)) {
                    $filesMoved++;
                }
                $scan->document_path = $row['target_path'];
            }

            $scan->registry = $toRegistry;
            $scan->save();

            $this->logTransfer($scan, $file, $fromRegistry, $toRegistry, $row, $reason, $actorId);
        }

        // --- PAGETYPING + Doc-WARE archive copies -----------------------
        $pageTypings = $this->pageTypingsFor($file)->keyBy('id');
        foreach ($preview['pagetypings'] as $row) {
            $pt = $pageTypings->get($row['id']);
            if (!$pt) {
                continue;
            }

            if ($row['target_path']) {
                if ($this->moveFile($row['resolved_path'], $row['target_path'], $sourceFolders, $journal)) {
                    $filesMoved++;
                }
                $pt->file_path = $row['target_path'];

                // Keep the Doc-WARE archive in step: move the old archive copy
                // across, or mint one from the typed page if it never had one.
                $fileName = basename(str_replace('\\', '/', $row['target_path']));
                $paperSize = optional($pt->scanning)->paper_size;

                $newArchive = $this->paths->archivePath($toRegistry, $file->file_number, $paperSize, $fileName);
                $oldArchive = $this->paths->archivePath($fromRegistry, $file->file_number, $paperSize, $fileName);

                if ($oldArchive !== $newArchive && $this->filesystem->exists($this->paths->absolute($oldArchive))) {
                    if ($this->moveFile($oldArchive, $newArchive, $sourceFolders, $journal)) {
                        $filesMoved++;
                    }
                } elseif (!$this->filesystem->exists($this->paths->absolute($newArchive))) {
                    if ($this->paths->copyWithin($row['target_path'], $newArchive)) {
                        $journal['created'][] = $newArchive;
                        $filesMoved++;
                    }
                }
            }

            $pt->registry = $toRegistry;
            $pt->save();
        }

        // --- The indexing record itself ---------------------------------
        $file->registry = $toRegistry;
        $file->save();

        foreach (array_keys($sourceFolders) as $folder) {
            $this->cleanupIfEmpty($folder);
        }

        Log::channel('daily')->info('EDMS registry transfer completed', [
            'file_indexing_id' => $file->id,
            'file_number' => $file->file_number,
            'from_registry' => $fromRegistry,
            'to_registry' => $toRegistry,
            'files_moved' => $filesMoved,
            'scannings' => $preview['counts']['scannings'],
            'pagetypings' => $preview['counts']['pagetypings'],
            'reason' => $reason,
            'moved_by' => $actorId,
        ]);

        return $preview + [
            'dry_run' => false,
            'moved' => [
                'files' => $filesMoved,
                'scannings' => $preview['counts']['scannings'],
                'pagetypings' => $preview['counts']['pagetypings'],
            ],
        ];
    }

    /**
     * Put every file back where it was after a failed transfer, so the disk
     * matches the rolled-back records.
     */
    private function undo(array $journal): void
    {
        $emptied = [];

        foreach (array_reverse($journal['moves']) as [$from, $to]) {
            try {
                $target = $this->paths->absolute($from);
                $source = $this->paths->absolute($to);

                if ($this->filesystem->exists($source)) {
                    $this->filesystem->ensureDirectoryExists(dirname($target), 0775, true);
                    $this->filesystem->move($source, $target);
                    $emptied[dirname($source)] = true;
                }
            } catch (\Throwable $e) {
                Log::error('Could not undo a registry-transfer move', [
                    'from' => $from,
                    'to' => $to,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($journal['created'] as $created) {
            try {
                $absolute = $this->paths->absolute($created);
                if ($this->filesystem->exists($absolute)) {
                    $this->filesystem->delete($absolute);
                    $emptied[dirname($absolute)] = true;
                }
            } catch (\Throwable $e) {
                Log::error('Could not remove a file created during a failed registry transfer', [
                    'path' => $created,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Drop the destination folders the aborted transfer created.
        foreach (array_keys($emptied) as $folder) {
            $this->cleanupIfEmpty($folder);
        }

        if ($journal['moves'] || $journal['created']) {
            Log::warning('Registry transfer failed — filesystem changes were rolled back', [
                'moves_undone' => count($journal['moves']),
                'files_removed' => count($journal['created']),
            ]);
        }
    }

    /**
     * Who to record as the actor. Falls back to the file's creator when running
     * outside a request (console), since the audit column is NOT NULL.
     */
    private function resolveActorId(FileIndexing $file, ?int $actorId): int
    {
        $resolved = $actorId ?: Auth::id() ?: $file->created_by;

        if (!$resolved) {
            $resolved = DB::connection('sqlsrv')->table('users')->min('id');
        }

        if (!$resolved) {
            throw new \RuntimeException('Cannot record the transfer: no user could be resolved as the actor.');
        }

        return (int) $resolved;
    }

    /**
     * Move by file number instead of model — the entry point for the console command.
     *
     * @throws \InvalidArgumentException when the file number is unknown
     */
    public function transferByFileNumber(string $fileNumber, string $targetRegistry, ?string $reason = null, bool $dryRun = false, ?int $actorId = null): array
    {
        $file = FileIndexing::on('sqlsrv')->where('file_number', trim($fileNumber))->first();

        if (!$file) {
            throw new \InvalidArgumentException("No indexing record found for file number '{$fileNumber}'.");
        }

        return $this->transfer($file, $targetRegistry, $reason, $dryRun, $actorId);
    }

    /**
     * Swap the registry segment of a path, rebuilding it through the canonical
     * layout so a legacy path (missing the registry or paper-size segment) comes
     * out standardized rather than half-fixed.
     */
    private function retargetRegistry(string $relativePath, string $fromRegistry, string $toRegistry, string $fileNumber, $paperSize): string
    {
        $normalized = str_replace('\\', '/', $relativePath);
        $fileName = basename($normalized);

        if (str_starts_with($normalized, EdmsDocumentPathResolver::PAGETYPING_ROOT . '/')) {
            return $this->paths->pageTypingPath($toRegistry, $fileNumber, $paperSize, $fileName);
        }

        if (str_starts_with($normalized, EdmsDocumentPathResolver::ARCHIVE_ROOT . '/')) {
            return $this->paths->archivePath($toRegistry, $fileNumber, $paperSize, $fileName);
        }

        // SCAN_UPLOAD, BLIND_SCAN and anything legacy land in SCAN_UPLOAD, which
        // is where an original belongs.
        return $this->paths->scanUploadPath($toRegistry, $fileNumber, $paperSize, $fileName);
    }

    /**
     * Move one file on the public disk, remembering its folder for cleanup.
     */
    private function moveFile(string $sourceRelative, string $targetRelative, array &$sourceFolders, array &$journal): bool
    {
        if ($sourceRelative === $targetRelative) {
            return false;
        }

        $source = $this->paths->absolute($sourceRelative);
        $target = $this->paths->absolute($targetRelative);

        // Already moved — several records can share one file (a scan and its
        // typed page often point at the same image on legacy data).
        if (!$this->filesystem->exists($source)) {
            return false;
        }

        $this->filesystem->ensureDirectoryExists(dirname($target), 0775, true);

        if (!$this->filesystem->move($source, $target)) {
            throw new \RuntimeException("Unable to move {$sourceRelative} to {$targetRelative}.");
        }

        $journal['moves'][] = [$sourceRelative, $targetRelative];
        $sourceFolders[dirname($source)] = true;

        return true;
    }

    /**
     * @return \Illuminate\Support\Collection<Scanning>
     */
    private function scanningsFor(FileIndexing $file)
    {
        return Scanning::on('sqlsrv')->where('file_indexing_id', $file->id)->get();
    }

    /**
     * @return \Illuminate\Support\Collection<PageTyping>
     */
    private function pageTypingsFor(FileIndexing $file)
    {
        return PageTyping::on('sqlsrv')->with('scanning')->where('file_indexing_id', $file->id)->get();
    }

    /**
     * Reuse the scan reassignment audit trail: the file number is unchanged, the
     * registry (and therefore the path) is what moved.
     */
    private function logTransfer(Scanning $scan, FileIndexing $file, string $fromRegistry, string $toRegistry, array $row, ?string $reason, int $actorId): void
    {
        if (!$row['resolved_path'] && !$row['target_path']) {
            return;
        }

        ScanReassignmentLog::on('sqlsrv')->create([
            'scanning_id' => $scan->id,
            'from_file_number' => $file->file_number,
            'to_file_number' => $file->file_number,
            'from_file_indexing_id' => $file->id,
            'to_file_indexing_id' => $file->id,
            'from_path' => (string) ($row['resolved_path'] ?? $row['stored_path']),
            'to_path' => (string) $row['target_path'],
            'reason' => trim("Registry transfer: {$fromRegistry} -> {$toRegistry}. " . (string) $reason),
            'reassigned_by' => $actorId,
        ]);
    }

    /**
     * Delete a folder once its last document has left. Never touches a tree root.
     */
    private function cleanupIfEmpty(string $folder): void
    {
        if (!$this->filesystem->isDirectory($folder)) {
            return;
        }

        $roots = [
            EdmsDocumentPathResolver::SCAN_UPLOAD_ROOT,
            EdmsDocumentPathResolver::PAGETYPING_ROOT,
            EdmsDocumentPathResolver::ARCHIVE_ROOT,
            EdmsDocumentPathResolver::BLIND_SCAN_ROOT,
        ];

        $realFolder = realpath($folder);
        foreach ($roots as $root) {
            $realRoot = realpath($this->paths->absolute($root));
            if ($realRoot && $realFolder === $realRoot) {
                return;
            }
            // Never delete a registry folder either — only file-level folders below it.
            if ($realRoot && dirname($realFolder) === $realRoot) {
                return;
            }
        }

        if (empty($this->filesystem->allFiles($folder))) {
            $this->filesystem->deleteDirectory($folder);
            Log::info('Cleaned up empty folder after registry transfer', ['folder' => $folder]);

            // The file-number folder above may now be empty too.
            $this->cleanupIfEmpty(dirname($folder));
        }
    }
}
