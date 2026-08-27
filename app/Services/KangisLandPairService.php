<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Pairs a land file number with its KANGIS number (and the reverse), so a printed
 * sheet can name both in one line: "RES-1991-772 (KNML 9213)".
 *
 * A KANGIS file is an alias of a land file, not a file of its own: it lives in a
 * separate file_indexings row (registry = 'KANGIS') whose related_fileno JSON
 * back-links to the land file it was recertified from. Whichever of the two numbers
 * is quoted on a document, the reader expects to see the pair.
 */
class KangisLandPairService
{
    /** Legacy KANGIS (MLKN/KNML/KNGP, optionally unit-suffixed) or new-KANGIS (KN…). */
    public function isKangisFormat(?string $fileNo): bool
    {
        $fileNo = trim((string) $fileNo);

        return $fileNo !== ''
            && (bool) preg_match('/^((MLKN|KNML|KNGP)\s?\d{1,6}([-_]\d{1,3})?|KN[\s-]?\d{2,6})$/i', $fileNo);
    }

    /**
     * "land file no (kangis file no)" when the pairing is known, in either direction;
     * otherwise the number exactly as given. Never throws — a display helper.
     */
    public function format(?string $fileNo): string
    {
        $fileNo = trim((string) $fileNo);
        if ($fileNo === '') {
            return '';
        }

        try {
            $conn = DB::connection('sqlsrv');

            if ($this->isKangisFormat($fileNo)) {
                $land = $this->landFileForKangis($conn, $fileNo);

                return $land !== null ? "{$land} ({$fileNo})" : $fileNo;
            }

            $kangis = $this->kangisFileForLand($conn, $fileNo);

            return $kangis !== null ? "{$fileNo} ({$kangis})" : $fileNo;
        } catch (\Throwable $e) {
            return $fileNo;
        }
    }

    /**
     * Same, for a stored related_fileno — a JSON array on newer rows, a comma list on
     * older ones, a bare number otherwise. Each number is paired, then joined for print.
     */
    public function formatList($raw): string
    {
        $numbers = $this->parseRelatedFileno($raw);
        if (empty($numbers)) {
            return '';
        }

        return implode(', ', array_map(fn ($n) => $this->format($n), $numbers));
    }

    /**
     * The land file a KANGIS number is an alias of: the KANGIS row's own related_fileno
     * back-link first, then a KANGIS Recertification link at either endpoint.
     */
    private function landFileForKangis($conn, string $kangisNo): ?string
    {
        $key = strtoupper(preg_replace('/\s+/', '', $kangisNo));
        $keyNoZero = preg_replace('/^([A-Z]+)0*(\d+)$/', '$1$2', $key);

        $pickLand = function ($candidates) use ($kangisNo): ?string {
            foreach ($candidates as $cand) {
                $cand = trim((string) $cand);
                if ($cand !== '' && $cand !== '-' && !$this->isKangisFormat($cand)
                    && strcasecmp($cand, $kangisNo) !== 0) {
                    return $cand;
                }
            }
            return null;
        };

        try {
            $own = $conn->table('file_indexings')
                ->whereRaw("UPPER(REPLACE(LTRIM(RTRIM(ISNULL(file_number,''))),' ','')) IN (?, ?)", [$key, $keyNoZero])
                ->whereNull('deleted_at')
                ->value('related_fileno');
            $land = $pickLand($this->parseRelatedFileno($own));
            if ($land !== null) {
                return $land;
            }
        } catch (\Throwable $e) { /* fail-open */ }

        try {
            $links = $conn->table('related_file_number')
                ->where('transaction_type', 'like', '%Recertification%')
                ->where(function ($q) use ($key, $keyNoZero) {
                    $q->whereRaw("UPPER(REPLACE(LTRIM(RTRIM(ISNULL(file_number,''))),' ','')) IN (?, ?)", [$key, $keyNoZero])
                      ->orWhereRaw("UPPER(REPLACE(LTRIM(RTRIM(ISNULL(related_fileno,''))),' ','')) IN (?, ?)", [$key, $keyNoZero]);
                })
                ->get(['file_number', 'related_fileno']);
            foreach ($links as $l) {
                $land = $pickLand([$l->file_number ?? null, $l->related_fileno ?? null]);
                if ($land !== null) {
                    return $land;
                }
            }
        } catch (\Throwable $e) { /* fail-open */ }

        return null;
    }

    /**
     * The KANGIS number of a land file: the reverse lookup — which KANGIS-registry
     * indexing row lists this land file in its related_fileno? Legacy KANGIS is
     * preferred over new-KANGIS, as the land file's first recertification number.
     */
    private function kangisFileForLand($conn, string $landFileNo): ?string
    {
        $variants = $this->fileNumberVariants($landFileNo);
        $legacy = null;
        $newk = null;
        $take = function ($value) use (&$legacy, &$newk): void {
            $v = trim((string) $value);
            if ($v === '' || $v === '-' || !$this->isKangisFormat($v)) {
                return;
            }
            if (preg_match('/^KN[\s-]?\d/i', $v)) {
                $newk = $newk ?? $v;
            } else {
                $legacy = $legacy ?? $v;
            }
        };

        // The quoted token is matched so "RES-1991-772" cannot partial-match "RES-1991-7720".
        try {
            $rows = $conn->table('file_indexings')
                ->where(function ($q) use ($variants) {
                    foreach ($variants as $v) {
                        $q->orWhere('related_fileno', 'like', '%"' . $v . '"%');
                    }
                })
                ->whereNull('deleted_at')
                ->get(['file_number', 'kangis_fileno_resolved', 'kangis_file_no', 'new_kangis_file_no']);
            foreach ($rows as $r) {
                $take($r->kangis_fileno_resolved ?? null);
                $take($r->kangis_file_no ?? null);
                $take($r->file_number ?? null);
                $take($r->new_kangis_file_no ?? null);
            }
        } catch (\Throwable $e) { /* fail-open */ }

        if ($legacy === null && $newk === null) {
            try {
                $links = $conn->table('related_file_number')
                    ->where('transaction_type', 'like', '%Recertification%')
                    ->where(function ($q) use ($variants) {
                        foreach ($variants as $v) {
                            $q->orWhere('file_number', $v)->orWhere('related_fileno', $v);
                        }
                    })
                    ->get(['file_number', 'related_fileno']);
                foreach ($links as $l) {
                    $take($l->file_number ?? null);
                    $take($l->related_fileno ?? null);
                }
            } catch (\Throwable $e) { /* fail-open */ }
        }

        return $legacy ?? $newk;
    }

    /** related_fileno is a JSON array on newer rows and a comma list on older ones. */
    private function parseRelatedFileno($raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $decoded), fn ($v) => $v !== ''));
            }
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($v) => $v !== ''));
    }

    /** A temporary file is stored both as "X" and "X(T)"; match either. */
    private function fileNumberVariants(string $fileNo): array
    {
        $variants = [$fileNo => $fileNo];
        $base = trim((string) preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $fileNo));
        if ($base !== '') {
            $variants[$base] = $base;
            $variants[$base . '(T)'] = $base . '(T)';
        }

        return array_values($variants);
    }
}
