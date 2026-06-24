<?php

use App\Models\MasterDcivLink;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Third backfill source for master_dciv_links.
 *
 * Beyond `dciv_link` (commissioning store) and `file_indexing_links` (indexing
 * module link store), DCIV/LPCC file_indexings rows also record their related
 * files inline in the `related_fileno` JSON column. Most of these overlap with
 * file_indexing_links, but some related numbers live only in the JSON and were
 * therefore still absent from master_dciv_links.
 *
 * The column is JSON (e.g. ["RES-1986-1280","RES-1992-6391"]) so this backfill
 * runs in PHP. It is additive and idempotent (per-pair existence check) and
 * reuses MasterDcivLink::typeColumns for type classification.
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

        $conn->table('file_indexings')
            ->select('id', 'file_number', 'related_fileno', 'dciv_reason', 'created_by')
            ->where(function ($q) {
                $q->where('file_number', 'like', 'DCIV%')
                  ->orWhere('file_number', 'like', 'LPCC%');
            })
            ->whereNotNull('related_fileno')
            ->whereRaw('LEN(LTRIM(RTRIM(related_fileno))) > 0')
            ->where(fn ($q) => $q->where('is_deleted', 0)->orWhereNull('is_deleted'))
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($conn) {
                foreach ($rows as $row) {
                    $dciv = trim((string) $row->file_number);
                    if ($dciv === '') {
                        continue;
                    }

                    foreach ($this->parseRelated($row->related_fileno) as $relatedNo) {
                        $relatedNo = trim($relatedNo);
                        if ($relatedNo === '' || strcasecmp($relatedNo, $dciv) === 0) {
                            continue; // skip blanks and self-references
                        }

                        $exists = $conn->table('master_dciv_links')
                            ->whereRaw('UPPER(LTRIM(RTRIM(dciv_file_number))) = ?', [strtoupper($dciv)])
                            ->whereRaw('UPPER(LTRIM(RTRIM(related_file_number))) = ?', [strtoupper($relatedNo)])
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        // Resolve a dciv_file_no row (id / reason / created_by) when one exists.
                        $dcivFileNo = $conn->table('dciv_file_no')
                            ->whereRaw('UPPER(LTRIM(RTRIM(full_file_number))) = ?', [strtoupper($dciv)])
                            ->where(fn ($q) => $q->where('is_deleted', 0)->orWhereNull('is_deleted'))
                            ->orderByDesc('id')
                            ->first();

                        // Resolve a related-file title from file_indexings.
                        $title = $conn->table('file_indexings')
                            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper($relatedNo)])
                            ->orderByDesc('id')
                            ->value('file_title');

                        $createdBy = $dcivFileNo->created_by ?? null;
                        $createdBy = is_numeric($createdBy) ? (int) $createdBy : null;

                        MasterDcivLink::create(array_merge([
                            'dciv_file_no_id'    => $dcivFileNo->id ?? null,
                            'dciv_file_number'   => $dciv,
                            'dciv_reason'        => $dcivFileNo->dciv_reason ?? $row->dciv_reason ?? null,
                            'related_file_number' => $relatedNo,
                            'related_file_title' => $title,
                            'created_by'         => $createdBy,
                        ], MasterDcivLink::typeColumns($relatedNo)));
                    }
                }
            });
    }

    public function down(): void
    {
        // Non-reversible data backfill; rows are intentionally left in place.
    }

    /**
     * Parse the related_fileno column, which is normally a JSON array of file
     * numbers but may also appear as a comma-separated string in legacy rows.
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
