<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Derives a file's rack/shelf from the shelf_rack_ranges map when the file has
 * no recorded shelf_location of its own.
 *
 * A file number is <series>-<serial> (e.g. "RES-1981-250" => series "RES-1981",
 * serial 250). The shelf is the shelf_rack_ranges row for the same registry and
 * series whose serial_from..serial_to brackets the serial.
 *
 * Accuracy, measured against the 47,409 files that DO have a recorded
 * shelf_location (see shelf-racks:verify):
 *   - registry 3 (CON-RES-*, workbook set 1): ~98% agreement
 *   - registry 1 (RES/COM/IND-*, workbook set 2): ~71% agreement; the workbook
 *     drifts by one rack (I->J, J->K, K->L, U->T, V->U) against the live data
 *   - registry 2: no workbook coverage at all, never resolves
 *
 * Because of that drift, resolutions are flagged via `shelf_is_derived` so the
 * UI can distinguish an inferred shelf from a recorded one. The registry-3 rows
 * from workbook set 2 relabel CON-RES-* as RES-*, which matches no real file
 * number, so they simply never resolve and need no special-casing here.
 */
class ShelfRackLocator
{
    private const CACHE_KEY = 'shelf_rack_ranges.lookup';
    private const CACHE_TTL = 3600;

    /** @var array<string, array<int, array{from:int,to:int,shelf:string}>>|null */
    private ?array $lookup = null;

    /**
     * Fill in shelf_location on any file lacking one, and mark how it was
     * obtained. Mutates and returns the given files.
     *
     * @param  iterable<int, object>  $files
     */
    public function applyTo(iterable $files): iterable
    {
        foreach ($files as $file) {
            $recorded = trim((string) ($file->shelf_location ?? ''));

            if ($recorded !== '') {
                $file->shelf_is_derived = false;
                continue;
            }

            $derived = $this->resolve($file->file_number ?? '', $file->registry ?? null);

            $file->shelf_location = $derived;
            $file->shelf_is_derived = $derived !== null;
        }

        return $files;
    }

    /**
     * The rack/shelf label (e.g. "A11") the workbooks place this file number on,
     * or null when the registry/series/serial isn't covered.
     */
    public function resolve(?string $fileNumber, $registry): ?string
    {
        $parsed = $this->parse($fileNumber);

        if ($parsed === null || $registry === null || trim((string) $registry) === '') {
            return null;
        }

        [$series, $serial] = $parsed;
        $key = $this->key($registry, $series);

        foreach ($this->lookup()[$key] ?? [] as $range) {
            if ($serial >= $range['from'] && $serial <= $range['to']) {
                return $range['shelf'];
            }
        }

        return null;
    }

    /**
     * "RES-1981-250" => ["RES-1981", 250]. Null when the number has no trailing
     * serial to place.
     *
     * @return array{0: string, 1: int}|null
     */
    private function parse(?string $fileNumber): ?array
    {
        $fileNumber = trim((string) $fileNumber);

        if ($fileNumber === '' || !preg_match('/^(.*)-(\d+)$/', $fileNumber, $m)) {
            return null;
        }

        return [strtoupper(trim($m[1])), (int) $m[2]];
    }

    private function key($registry, string $series): string
    {
        return trim((string) $registry) . '|' . $series;
    }

    /**
     * registry|series => ranges. The whole table is ~1,700 rows, so it is loaded
     * once and cached rather than queried per file.
     *
     * @return array<string, array<int, array{from:int,to:int,shelf:string}>>
     */
    private function lookup(): array
    {
        if ($this->lookup !== null) {
            return $this->lookup;
        }

        $this->lookup = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $lookup = [];

            $rows = DB::connection('sqlsrv')
                ->table('shelf_rack_ranges')
                ->whereNotNull('file_no')
                ->whereNotNull('serial_from')
                ->whereNotNull('serial_to')
                // Deterministic pick for the 8 ranges the workbooks place on two
                // shelves: lowest rack/shelf wins.
                ->orderBy('set_version')
                ->orderBy('rack')
                ->orderBy('shelf')
                ->select(['registry_id', 'file_no', 'serial_from', 'serial_to', 'rack_shelf'])
                ->get();

            foreach ($rows as $row) {
                $key = trim((string) $row->registry_id) . '|' . strtoupper(trim($row->file_no));

                $lookup[$key][] = [
                    'from' => (int) $row->serial_from,
                    'to' => (int) $row->serial_to,
                    'shelf' => trim($row->rack_shelf),
                ];
            }

            return $lookup;
        });

        return $this->lookup;
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
