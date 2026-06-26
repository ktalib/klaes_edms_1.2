<?php

use App\Models\MasterDcivLink;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reverse-direction backfill for master_dciv_links.
 *
 * The three prior backfills (dciv_link, file_indexing_links, related_fileno JSON)
 * only ever sourced rows where the DCIV/LPCC file was the *parent* — i.e. a DCIV
 * file was indexed and carried its related files. They never captured the reverse:
 * an ordinary file (Land / SLTR / ST) that was indexed with a DCIV listed as its
 * related file number. Those pairs live in the ordinary file's file_indexing_links
 * / related_fileno and were therefore absent from master_dciv_links.
 *
 * This mirrors FileIndexingController::syncMasterDcivLinks (direction 2), which now
 * handles the same case at indexing time for new records. Additive and idempotent
 * (per dciv/related pair existence check); reuses MasterDcivLink::typeColumns.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('master_dciv_links')
            || ! Schema::connection($this->connection)->hasTable('file_indexings')) {
            return;
        }

        $conn = DB::connection($this->connection);

        // Source A: file_indexing_links rows whose LINK file number is a DCIV/LPCC,
        // attached to an ordinary (non-DCIV/LPCC) parent file.
        if (Schema::connection($this->connection)->hasTable('file_indexing_links')) {
            $conn->table('file_indexing_links as l')
                ->join('file_indexings as fi', 'fi.id', '=', 'l.file_indexing_id')
                ->whereNull('l.deleted_at')
                ->whereNotNull('l.file_number')
                ->whereRaw('LEN(LTRIM(RTRIM(l.file_number))) > 0')
                ->where(function ($q) {
                    $q->where('l.file_number', 'like', 'DCIV%')
                      ->orWhere('l.file_number', 'like', 'LPCC%');
                })
                ->whereNotNull('fi.file_number')
                ->whereRaw('LEN(LTRIM(RTRIM(fi.file_number))) > 0')
                ->where('fi.file_number', 'not like', 'DCIV%')
                ->where('fi.file_number', 'not like', 'LPCC%')
                ->where(fn ($q) => $q->where('fi.is_deleted', 0)->orWhereNull('fi.is_deleted'))
                ->select(
                    'l.file_number as dciv_no',
                    'fi.file_number as related_no',
                    'fi.file_title as related_title',
                    'fi.dciv_reason as dciv_reason'
                )
                ->orderBy('l.id')
                ->chunk(500, function ($rows) use ($conn) {
                    foreach ($rows as $row) {
                        $this->insertPair($conn, $row->dciv_no, $row->related_no, $row->related_title, $row->dciv_reason);
                    }
                });
        }

        // Source B: ordinary files whose related_fileno JSON lists a DCIV/LPCC number.
        $conn->table('file_indexings')
            ->select('file_number', 'file_title', 'related_fileno', 'dciv_reason')
            ->whereNotNull('file_number')
            ->whereRaw('LEN(LTRIM(RTRIM(file_number))) > 0')
            ->where('file_number', 'not like', 'DCIV%')
            ->where('file_number', 'not like', 'LPCC%')
            ->whereNotNull('related_fileno')
            ->whereRaw('LEN(LTRIM(RTRIM(related_fileno))) > 0')
            ->where(fn ($q) => $q->where('is_deleted', 0)->orWhereNull('is_deleted'))
            ->orderBy('id')
            ->chunk(300, function ($rows) use ($conn) {
                foreach ($rows as $row) {
                    $parent = trim((string) $row->file_number);
                    if ($parent === '') {
                        continue;
                    }

                    foreach ($this->parseRelated($row->related_fileno) as $rel) {
                        $rel = trim($rel);
                        $upper = strtoupper($rel);
                        if ($rel === '' || strcasecmp($rel, $parent) === 0) {
                            continue;
                        }
                        if (! str_starts_with($upper, 'DCIV') && ! str_starts_with($upper, 'LPCC')) {
                            continue; // only reverse links (DCIV as the related entry)
                        }

                        $this->insertPair($conn, $rel, $parent, $row->file_title, $row->dciv_reason);
                    }
                }
            });
    }

    public function down(): void
    {
        // Non-reversible data backfill; rows are intentionally left in place.
    }

    /**
     * Insert a single (dciv -> related) pair if it does not already exist.
     */
    private function insertPair($conn, ?string $dcivNo, ?string $relatedNo, ?string $relatedTitle, ?string $dcivReason): void
    {
        $dcivNo = trim((string) $dcivNo);
        $relatedNo = trim((string) $relatedNo);
        if ($dcivNo === '' || $relatedNo === '' || strcasecmp($dcivNo, $relatedNo) === 0) {
            return;
        }

        $exists = $conn->table('master_dciv_links')
            ->whereRaw('UPPER(LTRIM(RTRIM(dciv_file_number))) = ?', [strtoupper($dcivNo)])
            ->whereRaw('UPPER(LTRIM(RTRIM(related_file_number))) = ?', [strtoupper($relatedNo)])
            ->exists();

        if ($exists) {
            return;
        }

        // Resolve a related-file title from file_indexings when the source lacks one.
        if (empty($relatedTitle)) {
            $relatedTitle = $conn->table('file_indexings')
                ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper($relatedNo)])
                ->orderByDesc('id')
                ->value('file_title');
        }

        // Pull dciv_file_no metadata (id / reason / created_by) for the DCIV when present.
        $dcivFileNo = $conn->table('dciv_file_no')
            ->whereRaw('UPPER(LTRIM(RTRIM(full_file_number))) = ?', [strtoupper($dcivNo)])
            ->where(fn ($q) => $q->where('is_deleted', 0)->orWhereNull('is_deleted'))
            ->orderByDesc('id')
            ->first();

        $createdBy = $dcivFileNo->created_by ?? null;
        $createdBy = is_numeric($createdBy) ? (int) $createdBy : null;

        MasterDcivLink::create(array_merge([
            'dciv_file_no_id'     => $dcivFileNo->id ?? null,
            'dciv_file_number'    => $dcivNo,
            'dciv_reason'         => $dcivFileNo->dciv_reason ?? $dcivReason,
            'related_file_number' => $relatedNo,
            'related_file_title'  => $relatedTitle,
            'created_by'          => $createdBy,
        ], MasterDcivLink::typeColumns($relatedNo)));
    }

    /**
     * Parse related_fileno, normally a JSON array but sometimes a comma list.
     *
     * @return string[]
     */
    private function parseRelated($raw): array
    {
        $raw = (string) $raw;
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return array_map(
                fn ($v) => is_array($v) ? (string) ($v['file_number'] ?? '') : (string) $v,
                $decoded
            );
        }

        return array_filter(array_map('trim', explode(',', $raw)), fn ($v) => $v !== '');
    }
};
