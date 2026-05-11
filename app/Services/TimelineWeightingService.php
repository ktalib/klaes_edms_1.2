<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TimelineWeightingService
{
    /**
     * Fetch all raw timeline records for a property.
     */
    public function getRawRecords(?string $fileNumber, ?string $propId): array
    {
        if (!$fileNumber && !$propId) {
            return [];
        }

        $connection = DB::connection('sqlsrv');
        $allRecords = [];

        // 1. file_history_staging
        $fhQuery = $connection->table('file_history_staging');
        $this->applyFilters($fhQuery, $fileNumber, $propId, ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);
        $fhRows = $fhQuery->get()->map(fn($r) => $this->normalizeRow($r, 'file_history_staging'))->toArray();
        $allRecords = array_merge($allRecords, $fhRows);

        // 2. CofO_staging
        $cofoQuery = $connection->table('CofO_staging');
        $this->applyFilters($cofoQuery, $fileNumber, $propId, ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);
        $cofoRows = $cofoQuery->get()->map(fn($r) => $this->normalizeRow($r, 'CofO_staging'))->toArray();
        $allRecords = array_merge($allRecords, $cofoRows);

        // 3. pra
        $praQuery = $connection->table('pra');
        $this->applyFilters($praQuery, $fileNumber, $propId, ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);
        $praRows = $praQuery->get()->map(fn($r) => $this->normalizeRow($r, 'pra'))->toArray();
        $allRecords = array_merge($allRecords, $praRows);

        // 4. deed_registrations
        $deedQuery = $connection->table('deed_registrations');
        $this->applyFilters($deedQuery, $fileNumber, $propId, ['fileno', 'parent_fileno']);
        $deedRows = $deedQuery->get()->map(fn($r) => $this->normalizeRow($r, 'deed_registrations'))->toArray();
        $allRecords = array_merge($allRecords, $deedRows);

        return $allRecords;
    }

    /**
     * Calculate the count of weighted (preferred/unique) records.
     */
    public function getWeightedCount(array $records): int
    {
        if (empty($records)) {
            return 0;
        }

        $weightedRecords = $this->getWeightedRecords($records);
        return count($weightedRecords);
    }

    /**
     * Filter records to only include those that are "weighted" (preferred or unique).
     */
    public function getWeightedRecords(array $records): array
    {
        return $this->getWeightingAnalysis($records)['weighted'];
    }

    /**
     * Perform full weighting analysis, separating records into weighted and omitted (duplicates).
     */
    public function getWeightingAnalysis(array $records): array
    {
        if (empty($records)) {
            return ['weighted' => [], 'omitted' => []];
        }

        $weightedMap = []; // key -> row
        $omitted = [];

        foreach ($records as $row) {
            $key = $this->getRecordKey($row);
            
            // Rows that can't be keyed for dedup are always "unique" (weighted)
            if ($key === null) {
                $row['_ptl_weighting_status'] = 'unique';
                $weightedMap[spl_object_hash((object)$row)] = $row;
                continue;
            }

            if (!array_key_exists($key, $weightedMap)) {
                $row['_ptl_weighting_status'] = 'preferred'; // Tentative
                $weightedMap[$key] = $row;
                continue;
            }

            $existing = $weightedMap[$key];

            $rowRichness = $this->calculateRichnessScore($row);
            $existingRichness = $this->calculateRichnessScore($existing);

            $isNewBetter = false;
            if ($rowRichness > $existingRichness) {
                $isNewBetter = true;
            } elseif ($rowRichness === $existingRichness) {
                if ($this->getSourceBaseScore($row) > $this->getSourceBaseScore($existing)) {
                    $isNewBetter = true;
                }
            }

            if ($isNewBetter) {
                // The previous winner is now an omitted record
                $existing['_ptl_weighting_status'] = 'duplicate';
                $omitted[] = $existing;
                
                $row['_ptl_weighting_status'] = 'preferred';
                $weightedMap[$key] = $row;
            } else {
                $row['_ptl_weighting_status'] = 'duplicate';
                $omitted[] = $row;
            }
        }

        // Final pass to distinguish 'unique' vs 'preferred'
        // If a key in weightedMap has no corresponding records in omitted with the same key, it's unique.
        // Actually, let's keep it simple: if it's in weightedMap, it's a winner.
        // If we want to be exact:
        $finalWeighted = [];
        $omittedKeys = array_map(fn($r) => $this->getRecordKey($r), $omitted);
        foreach ($weightedMap as $key => $row) {
            if ($row['_ptl_weighting_status'] === 'unique') {
                $finalWeighted[] = $row;
                continue;
            }
            if (!in_array($key, $omittedKeys)) {
                $row['_ptl_weighting_status'] = 'unique';
            }
            $finalWeighted[] = $row;
        }

        return [
            'weighted' => $finalWeighted,
            'omitted'  => $omitted,
        ];
    }

    protected function applyFilters($query, ?string $fileNumber, ?string $propId, array $fileColumns): void
    {
        $query->where(function ($q) use ($fileNumber, $propId, $fileColumns) {
            if ($fileNumber) {
                $upper = strtoupper($fileNumber);
                foreach ($fileColumns as $col) {
                    $q->orWhereRaw("UPPER({$col}) = ?", [$upper]);
                }
            }
            if ($propId) {
                $q->orWhere('prop_id', $propId);
            }
        });
        
        // Exclude deleted if column exists
        // (Note: Schema check is omitted for performance here, 
        // but adding it if we want to be safe like the controllers)
    }

    public function normalizeRow($row, string $source): array
    {
        $rowArr = (array) $row;
        $rowArr['source_table'] = $source;
        
        // Ensure consistent keys for weighting logic
        $rowArr['transaction_type'] = $rowArr['transaction_type'] ?? ($rowArr['instrument_type'] ?? '');
        $rowArr['transaction_date'] = $rowArr['transaction_date'] ?? ($rowArr['deeds_date'] ?? ($rowArr['reg_date'] ?? null));
        
        // Resolve file number
        $rowArr['file_number'] = $rowArr['file_number'] ?? ($rowArr['mlsFNo'] ?? ($rowArr['fileno'] ?? ($rowArr['kangisFileNo'] ?? ($rowArr['NewKANGISFileno'] ?? ($rowArr['temp_fileno'] ?? '')))));

        // Resolve location
        $rowArr['location'] = $rowArr['location'] ?? ($rowArr['district'] ?? ($rowArr['lga'] ?? ($rowArr['lgsaOrCity'] ?? null)));

        // Party Mapping (Handle both CamelCase and lowercase)
        $rowArr['party_1'] = $rowArr['party_1'] ?? ($rowArr['Grantor'] ?? ($rowArr['grantor'] ?? ($rowArr['Assignor'] ?? ($rowArr['assignor'] ?? ($rowArr['Mortgagor'] ?? ($rowArr['mortgagor'] ?? ''))))));
        $rowArr['party_2'] = $rowArr['party_2'] ?? ($rowArr['Grantee'] ?? ($rowArr['grantee'] ?? ($rowArr['Assignee'] ?? ($rowArr['assignee'] ?? ($rowArr['Mortgagee'] ?? ($rowArr['mortgagee'] ?? ''))))));
        
        // Ensure lowercase aliases exist for normalizeTimelineRow compatibility
        $roles = ['Assignor', 'Assignee', 'Mortgagor', 'Mortgagee', 'Grantor', 'Grantee', 'Surrenderor', 'Surrenderee', 'Lessor', 'Lessee'];
        foreach ($roles as $role) {
            $lower = strtolower($role);
            if (!isset($rowArr[$lower]) && isset($rowArr[$role])) {
                $rowArr[$lower] = $rowArr[$role];
            }
        }

        // registration_number is used in deed_registrations
        $regNo = $rowArr['regNo'] ?? ($rowArr['registration_number'] ?? ($rowArr['registration'] ?? ''));
        
        $s = $rowArr['serialNo'] ?? ($rowArr['serial_no'] ?? '');
        $p = $rowArr['pageNo'] ?? ($rowArr['page_no'] ?? '');
        $v = $rowArr['volumeNo'] ?? ($rowArr['volume_no'] ?? '');

        // If regNo has slashes, prioritize its parts for consistency (often richer/canonical)
        if (str_contains($regNo, '/')) {
            $parts = explode('/', $regNo);
            if (count($parts) >= 1 && trim($parts[0]) !== '' && trim($parts[0]) !== '0') $s = trim($parts[0]);
            if (count($parts) >= 2 && trim($parts[1]) !== '' && trim($parts[1]) !== '0') $p = trim($parts[1]);
            if (count($parts) >= 3 && trim($parts[2]) !== '' && trim($parts[2]) !== '0') $v = trim($parts[2]);
        }

        $rowArr['serial_no'] = $s;
        $rowArr['page_no'] = $p;
        $rowArr['volume_no'] = $v;
        $rowArr['regNo'] = $regNo;
        
        return $rowArr;
    }

    protected function getRecordKey(array $row): ?string
    {
        $source = $row['source_table'];
        $dedupableSources = ['file_history_staging', 'pra', 'CofO_staging', 'deed_registrations'];
        if (!in_array($source, $dedupableSources)) {
            return null;
        }

        $transType = $this->getCanonicalInstrumentType($row['transaction_type'] ?? '');
        if (!$transType) {
            return null;
        }

        $serialNo = $this->cleanNumericValue($row['serial_no'] ?? '') ?: '0';
        $pageNo   = $this->cleanNumericValue($row['page_no'] ?? '') ?: '0';
        $volumeNo = $this->cleanNumericValue($row['volume_no'] ?? '') ?: '0';
        
        $hasRealReg = ($serialNo !== '0' && $serialNo !== '' && $serialNo !== '-') ||
                       ($pageNo !== '0' && $pageNo !== '' && $pageNo !== '-') ||
                       ($volumeNo !== '0' && $volumeNo !== '' && $volumeNo !== '-');

        if ($hasRealReg) {
            return 'reg|' . $transType . '|' . $serialNo . '/' . $pageNo . '/' . $volumeNo;
        }

        // Fallback key: party + date
        $party1 = $this->normalizeString($row['party_1'] ?? '');
        $party2 = $this->normalizeString($row['party_2'] ?? '');
        
        // Match JS: ignore date for Right of Occupancy
        $date = ($transType === 'right of occupancy') ? '' : $this->normalizeString($row['transaction_date'] ?? ($row['deeds_date'] ?? ''));

        if (!$party1 && !$party2 && !$date) {
            return null;
        }

        return implode('|', [$transType, $party1, $party2, $date]);
    }

    protected function calculateRichnessScore(array $item): float
    {
        $score = 0.0;
        $hasText = fn($v) => !empty($v) && trim((string)$v) !== '-' && trim((string)$v) !== '0' && trim((string)$v) !== 'N/A';
        
        // Primary parties (critical for identity)
        if ($hasText($item['party_1'] ?? null)) $score += 2;
        if ($hasText($item['party_2'] ?? null)) $score += 2;
        
        // Registration particulars (critical for legal identity)
        $serial = $item['serial_no'] ?? '';
        $page = $item['page_no'] ?? '';
        $volume = $item['volume_no'] ?? '';
        if ($hasText($serial)) $score += 1.5;
        if ($hasText($page)) $score += 1.5;
        if ($hasText($volume)) $score += 1.5;
        
        // Dates (critical for chronological accuracy)
        if ($hasText($item['transaction_date'] ?? null) || $hasText($item['deeds_date'] ?? null)) $score += 3;
        if ($hasText($item['reg_date'] ?? null)) $score += 1;
        if ($hasText($item['reg_time'] ?? null) || $hasText($item['deeds_time'] ?? null)) $score += 0.5;

        // Contextual richness
        if ($hasText($item['location'] ?? null) || $hasText($item['district'] ?? null)) $score += 1;
        if ($hasText($item['property_description'] ?? null) || $hasText($item['comments'] ?? null)) $score += 1;
        if ($hasText($item['land_use'] ?? null)) $score += 0.5;

        return $score;
    }

    protected function getSourceBaseScore(array $row): float
    {
        $source = $row['source_table'];
        $transType = $this->getCanonicalInstrumentType($row['transaction_type'] ?? '');

        if ($transType === 'occupancy permit') return 10.0;
        if ($transType === 'transfer of title') return 9.5;
        if ($transType === 'right of occupancy') return 9.0;
        if ($source === 'CofO_staging' || $transType === 'certificate of occupancy') return 8.0;
        if ($source === 'pra' || $source === 'deed_registrations') return 5.0;
        if ($source === 'file_history_staging') return 2.5;
        
        return 1.0;
    }

    protected function getCanonicalInstrumentType(string $value): string
    {
        $raw = $this->normalizeString($value);
        if (!$raw || $raw === '-') return '';

        // Right of Occupancy
        if (str_contains($raw, 'right of occupancy') || str_contains($raw, 'right of occupanc')) return 'right of occupancy';
        if (preg_match('/^r\s*of\s*o$/i', $raw)) return 'right of occupancy';
        $compact = preg_replace('/[^a-z0-9]/', '', $raw);
        if (preg_match('/^r[o0]f[o0]$/', $compact) || str_starts_with($compact, 'r0f0occupanc') || str_starts_with($compact, 'rofoccupanc')) return 'right of occupancy';
        if ($raw === 'statutory right of occupancy' || $raw === 'customary right of occupancy') return 'right of occupancy';

        // CofO
        if (str_contains($raw, 'certificate of occupancy') || str_contains($raw, 'cert of occupancy')) return 'certificate of occupancy';
        if (preg_match('/^c\s*of\s*o$/i', $raw) || $compact === 'cofo' || $compact === 'c0f0') return 'certificate of occupancy';

        // OP
        if (str_contains($raw, 'occupancy permit')) return 'occupancy permit';
        if ($raw === 'op' || $compact === 'op') return 'occupancy permit';

        // Transfer
        if (str_contains($raw, 'transfer of title')) return 'transfer of title';

        // Mortgage
        if (in_array($raw, ['mortgage', 'deed of mortgage', 'tripartite mortgage', 'legal mortgage', 'equitable mortgage'])) return 'deed of mortgage';
        
        // Assignment
        if (str_contains($raw, 'assignment') && !str_contains($raw, 'sub-assignment') && !str_contains($raw, 're-assignment') && !str_contains($raw, 'reassignment')) return 'deed of assignment';

        // Surrender
        if (in_array($raw, ['deed of surrender', 'deed of release', 'deed of surrender and release', 'deed of surrender & release'])) return 'deed of surrender and release';

        // POA
        if (str_contains($raw, 'power of attorney') || $raw === 'poa' || $raw === 'ipoa' || $compact === 'poa' || $compact === 'ipoa') return 'power of attorney';

        return $raw;
    }

    protected function normalizeString(string $value): string
    {
        $v = trim(strtolower($value));
        $v = preg_replace('/\s+/', ' ', $v);
        $v = str_replace([',', '.'], '', $v);
        
        // Strip common titles for better party matching
        $titles = ['alhaii', 'alhaji', 'alh', 'hajiya', 'haj', 'malam', 'mallam', 'mal', 'dr', 'prof', 'mr', 'mrs', 'chief', 'architect', 'arc', 'engr', 'engineer'];
        foreach ($titles as $title) {
            $v = preg_replace('/^' . $title . '\s+/i', '', $v);
            $v = preg_replace('/\s+' . $title . '\s+/i', ' ', $v);
        }

        // Standardize common name variants
        $v = preg_replace('/\b(muhammad|mohammad|muhammed|mohd)\b/i', 'mohammed', $v);
        
        return trim($v);
    }

    protected function cleanNumericValue($value): string
    {
        if ($value === null) return '';
        $s = trim((string) $value);
        if ($s === '' || $s === 'null' || $s === 'n/a') return '';
        return preg_replace('/\.0$/', '', $s);
    }
}
