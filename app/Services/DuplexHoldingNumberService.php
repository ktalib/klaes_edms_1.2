<?php

namespace App\Services;

use App\Models\DuplexParcelUpdate;
use App\Models\DuplexParcelUpdateFile;
use Illuminate\Support\Facades\DB;

/**
 * Allocates the two identifiers a duplex runs on.
 *
 *   Duplex ID       DPX-2026-0007
 *   Holding number  DPX-2026-0007-H03
 *
 * The holding namespace is deliberately UNLIKE any registry file number and unlike
 * the "(T)" temporary convention. A "(T)" number is a real file with its own
 * indexing row, and FileLocationResolver / FileIndexingPropagationService treat it
 * as one; a holding number must never be picked up by any of that. It exists only
 * in duplex_parcel_update_files, is never written to fileNumber / file_indexings /
 * mls_file_no, and is retired when the duplex commits.
 */
class DuplexHoldingNumberService
{
    public const PREFIX = 'DPX';

    /** Next free DPX-<year>-<serial> for this year. */
    public function allocateDuplexId(?int $year = null): string
    {
        $year = $year ?: (int) date('Y');
        $like = self::PREFIX . '-' . $year . '-%';

        // Serial is the max already issued this year + 1. Duplex IDs are never
        // re-used, even when a duplex is deleted, so the counter only moves forward.
        $max = 0;
        $existing = DuplexParcelUpdate::where('duplex_id', 'LIKE', $like)
            ->pluck('duplex_id');

        foreach ($existing as $id) {
            if (preg_match('/-(\d+)$/', (string) $id, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return sprintf('%s-%d-%04d', self::PREFIX, $year, $max + 1);
    }

    /**
     * Next holding number for a duplex: DPX-2026-0007-H03.
     *
     * Numbered per duplex and monotonic, so the Land screen can show the chain in
     * the order it was produced regardless of which stage emitted it.
     */
    public function allocateHoldingNumber(DuplexParcelUpdate $duplex): string
    {
        $max = 0;

        $existing = DuplexParcelUpdateFile::where('duplex_parcel_update_id', $duplex->id)
            ->whereNotNull('holding_no')
            ->pluck('holding_no');

        foreach ($existing as $no) {
            if (preg_match('/-H(\d+)$/i', (string) $no, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return sprintf('%s-H%02d', $duplex->duplex_id, $max + 1);
    }

    /** Allocate several at once, in order. */
    public function allocateHoldingNumbers(DuplexParcelUpdate $duplex, int $count): array
    {
        $numbers = [];
        $max = 0;

        $existing = DuplexParcelUpdateFile::where('duplex_parcel_update_id', $duplex->id)
            ->whereNotNull('holding_no')
            ->pluck('holding_no');

        foreach ($existing as $no) {
            if (preg_match('/-H(\d+)$/i', (string) $no, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        for ($i = 1; $i <= $count; $i++) {
            $numbers[] = sprintf('%s-H%02d', $duplex->duplex_id, $max + $i);
        }

        return $numbers;
    }

    public function isHoldingNumber(?string $value): bool
    {
        return $value !== null
            && (bool) preg_match('/^' . self::PREFIX . '-\d{4}-\d+-H\d+$/i', trim($value));
    }

    /**
     * Guard: a holding number must never appear in the live registry. Called before
     * commit so a coding slip surfaces as a refusal instead of a poisoned index.
     */
    public function assertNotInRegistry(string $holdingNo): void
    {
        $conn = DB::connection('sqlsrv');

        $hit = $conn->table('fileNumber')->where('mlsfNo', $holdingNo)->exists()
            || $conn->table('file_indexings')->where('file_number', $holdingNo)->exists()
            || $conn->table('mls_file_no')->where('full_file_number', $holdingNo)->exists();

        if ($hit) {
            throw new \RuntimeException(
                "Holding number {$holdingNo} was found in the live registry. Holding numbers "
                . 'must never be written to fileNumber / file_indexings / mls_file_no.'
            );
        }
    }
}
