<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegalSearchService
{
    private const ARRANGEMENT_TABLE = 'legal_search_timeline_arrangements';
    private array $softDeleteColumnCache = [];

    /** 
     * Main search dispatcher.
     * Searches across 4 staging tables: file_history_staging, CofO_staging, pra, deed_registrations.
     * Returns all records arranged chronologically by transaction date.
     * 
     * 



     */
    public function search(array $params): array
    {
        $fileNo = trim($params['query'] ?? '');
        $guarantorName = trim($params['guarantorName'] ?? '');
        $guaranteeName = trim($params['guaranteeName'] ?? '');
        $lga = trim($params['lga'] ?? '');
        $district = trim($params['district'] ?? '');
        $location = trim($params['location'] ?? '');
        $plotNumber = trim($params['plotNumber'] ?? '');
        $planNumber = trim($params['planNumber'] ?? '');
        $size = trim($params['size'] ?? '');
        $caveat = trim($params['caveat'] ?? '');

        $hasSearchCriteria = $fileNo !== '' || $guarantorName !== '' || $guaranteeName !== '' ||
            $lga !== '' || $district !== '' || $location !== '' ||
            $plotNumber !== '' || $planNumber !== '' || $size !== '' || $caveat !== '';

        if (!$hasSearchCriteria) {
            return $this->emptyResult();
        }

        $filters = compact('fileNo', 'guarantorName', 'guaranteeName', 'lga', 'district', 'location', 'plotNumber', 'planNumber', 'size', 'caveat');
        $conn = DB::connection('sqlsrv');

        $fileHistoryRecords = $this->searchFileHistoryStaging($conn, $filters);
        $cofoRecords = $this->searchCofoStaging($conn, $filters);
        $praRecords = $this->searchPra($conn, $filters);
        $deedRecords = $this->searchDeedRegistrations($conn, $filters);

        // --- prop_id cross-table expansion ---
        // Collect prop_ids from initial results, then pull related records from all 4 tables
        $propIds = $this->collectPropIds(array_merge($fileHistoryRecords, $cofoRecords, $praRecords, $deedRecords));
        if (!empty($propIds)) {
            $existingIds = $this->buildExistingIdMap($fileHistoryRecords, $cofoRecords, $praRecords, $deedRecords);

            $extraFH = $this->searchByPropIds($conn, 'file_history_staging', $propIds, $existingIds['file_history_staging'] ?? []);
            $extraCofO = $this->searchByPropIds($conn, 'CofO_staging', $propIds, $existingIds['CofO_staging'] ?? []);
            $extraPRA = $this->searchByPropIds($conn, 'pra', $propIds, $existingIds['pra'] ?? []);
            $extraDeed = $this->searchByPropIds($conn, 'deed_registrations', $propIds, $existingIds['deed_registrations'] ?? []);

            $fileHistoryRecords = array_merge($fileHistoryRecords, $extraFH);
            $cofoRecords = array_merge($cofoRecords, $extraCofO);
            $praRecords = array_merge($praRecords, $extraPRA);
            $deedRecords = array_merge($deedRecords, $extraDeed);
        }

        // Merge all and sort chronologically
        $all = array_merge($fileHistoryRecords, $cofoRecords, $praRecords, $deedRecords);

        usort($all, function ($a, $b) {
            $dateA = $a['sort_date'] ?? '9999-12-31';
            $dateB = $b['sort_date'] ?? '9999-12-31';
            return strcmp($dateA, $dateB);
        });

        // Apply saved arrangement order if one exists for the common prop_id
        $all = $this->applyArrangementOrder($all);

        // Look up file info from file_indexings for the primary file number
        $fileIndexingData = null;
        if (!empty($all)) {
            $primaryCandidates = array_values(array_unique(array_filter([
                $all[0]['fileno'] ?? null,
                $all[0]['file_number'] ?? null,
                $all[0]['mlsFNo'] ?? null,
                $fileNo,
            ])));

            if (!empty($primaryCandidates)) {
                $fileIndexingData = $conn->table('file_indexings')
                    ->whereNull('deleted_at')
                    ->where(function ($q) use ($primaryCandidates) {
                        foreach ($primaryCandidates as $candidate) {
                            $q->orWhere('file_number', $candidate)
                                ->orWhere('related_fileno', 'like', '%' . $candidate . '%');
                        }
                    })
                    ->select('file_title', 'district', 'lga', 'land_use_type', 'plot_number', 'tp_no', 'related_fileno', 'file_number')
                    ->first();
            }
        }

        // Compute aggregate file_size using source weighting: CofO(4) > FH(3) > PRA(2) > Deed(1)
        $fileSize = null;
        $bestSizeScore = -1;
        $sourceScores = [
            'CofO_staging' => 4,
            'file_history_staging' => 3,
            'pra' => 2,
            'deed_registrations' => 1,
        ];
        foreach ($all as $t) {
            $s = $t['size'] ?? null;
            if ($s && $s !== '-' && trim($s) !== '') {
                $src = $t['source_table'] ?? '';
                $score = $sourceScores[$src] ?? 0;
                if ($score > $bestSizeScore) {
                    $bestSizeScore = $score;
                    $fileSize = trim($s);
                }
            }
        }
        if (!$fileSize && !empty($all)) {
            // Fallback: query PRA table directly via prop_id
            $propId = $all[0]['prop_id'] ?? null;
            if ($propId) {
                $praSize = $conn->table('pra')
                    ->where('prop_id', $propId)
                    ->whereNotNull('plot_size')
                    ->where('plot_size', '!=', '')
                    ->orderByDesc('id')
                    ->value('plot_size');
                if ($praSize) {
                    $fileSize = trim($praSize);
                }
            }
        }

        return [
            'transactions' => $all,
            'file_title' => $fileIndexingData->file_title ?? null,
            'file_district' => $fileIndexingData->district ?? null,
            'file_lga' => $fileIndexingData->lga ?? null,
            'file_land_use' => $fileIndexingData->land_use_type ?? null,
            'file_plot_number' => $fileIndexingData->plot_number ?? null,
            'file_tp_no' => $fileIndexingData->tp_no ?? null,
            'file_size' => $fileSize,
            'file_related_fileno' => $fileIndexingData->related_fileno ?? null,
            'file_index_number' => $fileIndexingData->file_number ?? null,
            'file_history_count' => count($fileHistoryRecords),
            'cofo_count' => count($cofoRecords),
            'pra_count' => count($praRecords),
            'deed_count' => count($deedRecords),
            'total_count' => count($all),
        ];
    }

    private function emptyResult(): array
    {
        return [
            'transactions' => [],
            'file_title' => null,
            'file_district' => null,
            'file_lga' => null,
            'file_land_use' => null,
            'file_plot_number' => null,
            'file_tp_no' => null,
            'file_size' => null,
            'file_related_fileno' => null,
            'file_index_number' => null,
            'file_history_count' => 0,
            'cofo_count' => 0,
            'pra_count' => 0,
            'deed_count' => 0,
            'total_count' => 0,
        ];
    }

    // --- file_history_staging ----------------------------------------
    // Actual columns: NO size, NO caveat. Has plot_size, is_caveated (bit), caveated_comment

    private function searchFileHistoryStaging($conn, array $f): array
    {
        $query = $conn->table('file_history_staging')
            ->select([
                'id',
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                'mlsFNo',
                'fileno',
                'kangisFileNo',
                'NewKANGISFileno',
                'transaction_type',
                DB::raw("TRY_CONVERT(DATE, transaction_date) AS transaction_date"),
                'party_1',
                'party_2',
                'party_3',
                'party_4',
                DB::raw("Assignor AS assignor"),
                DB::raw("Assignee AS assignee"),
                DB::raw("Mortgagor AS mortgagor"),
                DB::raw("Mortgagee AS mortgagee"),
                DB::raw("Grantor AS grantor"),
                DB::raw("Grantee AS grantee"),
                DB::raw("Surrenderor AS surrenderor"),
                DB::raw("Surrenderee AS surrenderee"),
                DB::raw("Lessor AS lessor"),
                DB::raw("Lessee AS lessee"),
                'land_use',
                'location',
                'lgsaOrCity',
                'districtName',
                DB::raw("serialNo AS serial_no"),
                DB::raw("pageNo AS page_no"),
                DB::raw("volumeNo AS volume_no"),
                'regNo',
                'prop_id',
                'comments',
                DB::raw("plot_size AS size"),
                DB::raw("CASE WHEN is_caveated = 1 THEN 'Yes' ELSE 'No' END AS caveat"),
                'caveat_id',
                'caveated_comment',
                'is_caveated',
                'plot_no',
                'deeds_date',
                'deeds_time',
                'reg_date',
                'reg_time',
                'tp_no',
                DB::raw("'file_history_staging' AS source_table"),
            ]);

        $this->applyFilters($query, $f, 'file_history_staging', ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);

        $this->applySoftDeleteFilter($query, 'file_history_staging');

        return $query->get()->map(fn($r) => $this->normalizeRow($r, 'File History'))->toArray();
    }

    // --- CofO_staging ------------------------------------------------
    // Actual columns: NO size, NO caveat, NO party_1/party_2/party_3. Has is_caveated (bit), caveated_comment, np_fileno

    private function searchCofoStaging($conn, array $f): array
    {
        $query = $conn->table('CofO_staging')
            ->select([
                'id',
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                'mlsFNo',
                'fileno',
                'kangisFileNo',
                'NewKANGISFileno',
                'transaction_type',
                DB::raw("TRY_CONVERT(DATE, transaction_date) AS transaction_date"),
                'party_1',
                'party_2',
                'party_3',
                'party_4',
                DB::raw("Assignor AS assignor"),
                DB::raw("Assignee AS assignee"),
                DB::raw("Mortgagor AS mortgagor"),
                DB::raw("Mortgagee AS mortgagee"),
                DB::raw("Grantor AS grantor"),
                DB::raw("Grantee AS grantee"),
                DB::raw("Surrenderor AS surrenderor"),
                DB::raw("Surrenderee AS surrenderee"),
                DB::raw("Lessor AS lessor"),
                DB::raw("Lessee AS lessee"),
                'land_use',
                'location',
                'lgsaOrCity',
                DB::raw("serialNo AS serial_no"),
                DB::raw("pageNo AS page_no"),
                DB::raw("volumeNo AS volume_no"),
                'regNo',
                'prop_id',
                'comments',
                DB::raw("NULL AS size"),
                DB::raw("CASE WHEN is_caveated = 1 THEN 'Yes' ELSE 'No' END AS caveat"),
                'caveat_id',
                'caveated_comment',
                'is_caveated',
                'plot_no',
                DB::raw("NULL AS deeds_date"),
                DB::raw("NULL AS deeds_time"),
                DB::raw("NULL AS reg_date"),
                DB::raw("transaction_time AS reg_time"),
                DB::raw("NULL AS tp_no"),
                DB::raw("'CofO_staging' AS source_table"),
            ]);

        $this->applyFilters($query, $f, 'CofO_staging', ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);

        $this->applySoftDeleteFilter($query, 'CofO_staging');

        return $query->get()->map(fn($r) => $this->normalizeRow($r, 'CofO'))->toArray();
    }

    // --- pra ---------------------------------------------------------
    // Actual columns: NO size, NO caveat. Has plot_size, is_caveated (bit), caveated_comment

    private function searchPra($conn, array $f): array
    {
        $query = $conn->table('pra')
            ->select([
                'id',
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                'mlsFNo',
                'fileno',
                'kangisFileNo',
                'NewKANGISFileno',
                'transaction_type',
                DB::raw("TRY_CONVERT(DATE, transaction_date) AS transaction_date"),
                'party_1',
                'party_2',
                'party_3',
                'party_4',
                DB::raw("Assignor AS assignor"),
                DB::raw("Assignee AS assignee"),
                DB::raw("Mortgagor AS mortgagor"),
                DB::raw("Mortgagee AS mortgagee"),
                DB::raw("Grantor AS grantor"),
                DB::raw("Grantee AS grantee"),
                DB::raw("Surrenderor AS surrenderor"),
                DB::raw("Surrenderee AS surrenderee"),
                DB::raw("Lessor AS lessor"),
                DB::raw("Lessee AS lessee"),
                'land_use',
                DB::raw("COALESCE(property_description, location) AS location"),
                'lgsaOrCity',
                'districtName',
                DB::raw("serialNo AS serial_no"),
                DB::raw("pageNo AS page_no"),
                DB::raw("volumeNo AS volume_no"),
                'regNo',
                'prop_id',
                'comments',
                DB::raw("plot_size AS size"),
                DB::raw("CASE WHEN is_caveated = 1 THEN 'Yes' ELSE 'No' END AS caveat"),
                'caveat_id',
                'caveated_comment',
                'is_caveated',
                'plot_no',
                'deeds_date',
                'deeds_time',
                DB::raw("NULL AS reg_date"),
                DB::raw("NULL AS reg_time"),
                'tp_no',
                DB::raw("'pra' AS source_table"),
            ]);

        $this->applyFilters($query, $f, 'pra', ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);

        $this->applySoftDeleteFilter($query, 'pra');

        return $query->get()->map(fn($r) => $this->normalizeRow($r, 'PRA'))->toArray();
    }

    // --- deed_registrations ------------------------------------------
    // Actual columns: HAS size, NO caveat, NO is_caveated. Has plot_number (not plot_no), district, lga

    private function searchDeedRegistrations($conn, array $f): array
    {
        $query = $conn->table('deed_registrations')
            ->select([
                'id',
                DB::raw("fileno AS file_number"),
                DB::raw("fileno AS mlsFNo"),
                'fileno',
                DB::raw("NULL AS kangisFileNo"),
                DB::raw("NULL AS NewKANGISFileno"),
                DB::raw("instrument_type AS transaction_type"),
                DB::raw("TRY_CONVERT(DATE, deeds_date) AS transaction_date"),
                DB::raw("grantor AS party_1"),
                DB::raw("grantee AS party_2"),
                DB::raw("NULL AS party_3"),
                DB::raw("NULL AS party_4"),
                DB::raw("NULL AS assignor"),
                DB::raw("NULL AS assignee"),
                DB::raw("NULL AS mortgagor"),
                DB::raw("NULL AS mortgagee"),
                DB::raw("grantor"),
                DB::raw("grantee"),
                DB::raw("NULL AS surrenderor"),
                DB::raw("NULL AS surrenderee"),
                DB::raw("NULL AS lessor"),
                DB::raw("NULL AS lessee"),
                DB::raw("NULL AS land_use"),
                DB::raw("COALESCE(district, lga) AS location"),
                'district',
                'lga',
                'serial_no',
                'page_no',
                'volume_no',
                DB::raw("registration_number AS regNo"),
                'prop_id',
                DB::raw("property_description AS comments"),
                'size',
                DB::raw("NULL AS caveat"),
                DB::raw("NULL AS caveat_id"),
                DB::raw("NULL AS caveated_comment"),
                DB::raw("CAST(0 AS BIT) AS is_caveated"),
                DB::raw("plot_number AS plot_no"),
                'deeds_date',
                'deeds_time',
                DB::raw("NULL AS reg_date"),
                DB::raw("NULL AS reg_time"),
                DB::raw("NULL AS tp_no"),
                DB::raw("'deed_registrations' AS source_table"),
            ]);

        $this->applyFilters($query, $f, 'deed_registrations', ['fileno', 'parent_fileno']);

        $this->applySoftDeleteFilter($query, 'deed_registrations');

        return $query->get()->map(fn($r) => $this->normalizeRow($r, 'Deed Registration'))->toArray();
    }

    // --- Shared filter logic -----------------------------------------
    // Table-aware: uses correct real column names per table

    private function applyFilters($query, array $f, string $tableName, array $fileColumns): void
    {
        $isDeed = ($tableName === 'deed_registrations');
        $isCofO = ($tableName === 'CofO_staging');

        // File number filter (Exact match based on File Number Selector)
        if ($f['fileNo'] !== '') {
            $exactFileNo = $f['fileNo'];
            $query->where(function ($subQ) use ($exactFileNo, $fileColumns) {
                foreach ($fileColumns as $col) {
                    $subQ->orWhereRaw("UPPER(LTRIM(RTRIM({$col}))) = UPPER(?)", [$exactFileNo]);
                }
            });
        }

        // Guarantor / party_1 filter
        if ($f['guarantorName'] !== '') {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $f['guarantorName']);
            $query->where(function ($subQ) use ($escaped, $isDeed, $isCofO) {
                if ($isDeed) {
                    $subQ->whereRaw("UPPER(grantor) LIKE UPPER(?)", ["%{$escaped}%"]);
                } else {
                    if (!$isCofO) {
                        $subQ->orWhereRaw("UPPER(party_1) LIKE UPPER(?)", ["%{$escaped}%"]);
                    }
                    $subQ->orWhereRaw("UPPER(Assignor) LIKE UPPER(?)", ["%{$escaped}%"])
                        ->orWhereRaw("UPPER(Grantor) LIKE UPPER(?)", ["%{$escaped}%"])
                        ->orWhereRaw("UPPER(Mortgagor) LIKE UPPER(?)", ["%{$escaped}%"])
                        ->orWhereRaw("UPPER(Lessor) LIKE UPPER(?)", ["%{$escaped}%"])
                        ->orWhereRaw("UPPER(Surrenderor) LIKE UPPER(?)", ["%{$escaped}%"]);
                }
            });
        }

        // Guarantee / party_2 filter
        if ($f['guaranteeName'] !== '') {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $f['guaranteeName']);
            $query->where(function ($subQ) use ($escaped, $isDeed, $isCofO) {
                if ($isDeed) {
                    $subQ->whereRaw("UPPER(grantee) LIKE UPPER(?)", ["%{$escaped}%"]);
                } else {
                    if (!$isCofO) {
                        $subQ->orWhereRaw("UPPER(party_2) LIKE UPPER(?)", ["%{$escaped}%"]);
                    }
                    $subQ->orWhereRaw("UPPER(Assignee) LIKE UPPER(?)", ["%{$escaped}%"])
                        ->orWhereRaw("UPPER(Grantee) LIKE UPPER(?)", ["%{$escaped}%"])
                        ->orWhereRaw("UPPER(Mortgagee) LIKE UPPER(?)", ["%{$escaped}%"])
                        ->orWhereRaw("UPPER(Lessee) LIKE UPPER(?)", ["%{$escaped}%"])
                        ->orWhereRaw("UPPER(Surrenderee) LIKE UPPER(?)", ["%{$escaped}%"]);
                }
            });
        }

        // LGA filter — actual column names differ per table
        if ($f['lga'] !== '') {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $f['lga']);
            if ($isDeed) {
                $query->whereRaw("UPPER(lga) LIKE UPPER(?)", ["%{$escaped}%"]);
            } else {
                // file_history_staging, CofO_staging, pra all have lgsaOrCity
                $query->whereRaw("UPPER(lgsaOrCity) LIKE UPPER(?)", ["%{$escaped}%"]);
            }
        }

        // District filter
        if ($f['district'] !== '') {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $f['district']);
            if ($isDeed) {
                $query->whereRaw("UPPER(district) LIKE UPPER(?)", ["%{$escaped}%"]);
            } else if (!$isCofO) {
                // file_history_staging, pra have districtName
                $query->whereRaw("UPPER(districtName) LIKE UPPER(?)", ["%{$escaped}%"]);
            } else {
                // CofO_staging has no districtName — fall back to location
                $query->whereRaw("UPPER(location) LIKE UPPER(?)", ["%{$escaped}%"]);
            }
        }

        // Location filter
        if ($f['location'] !== '') {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $f['location']);
            if ($isDeed) {
                $query->where(function ($subQ) use ($escaped) {
                    $subQ->orWhereRaw("UPPER(district) LIKE UPPER(?)", ["%{$escaped}%"])
                        ->orWhereRaw("UPPER(lga) LIKE UPPER(?)", ["%{$escaped}%"]);
                });
            } else {
                $query->whereRaw("UPPER(location) LIKE UPPER(?)", ["%{$escaped}%"]);
            }
        }

        // Plot number filter — deed_registrations uses plot_number
        if ($f['plotNumber'] !== '') {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $f['plotNumber']);
            $plotCol = $isDeed ? 'plot_number' : 'plot_no';
            $query->whereRaw("UPPER(CAST({$plotCol} AS NVARCHAR(100))) LIKE UPPER(?)", ["%{$escaped}%"]);
        }

        // Size filter — file_history_staging/pra use plot_size, deed uses size, CofO has neither
        if ($f['size'] !== '') {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $f['size']);
            if ($isDeed) {
                $query->whereRaw("UPPER(CAST(size AS NVARCHAR(100))) LIKE UPPER(?)", ["%{$escaped}%"]);
            } else if (!$isCofO) {
                $query->whereRaw("UPPER(CAST(plot_size AS NVARCHAR(100))) LIKE UPPER(?)", ["%{$escaped}%"]);
            }
            // CofO_staging has no size column — skip
        }

        // Caveat filter — all 3 staging tables use is_caveated (bit), deed has nothing
        if ($f['caveat'] !== '' && !$isDeed) {
            $caveatBit = (strtolower($f['caveat']) === 'yes') ? 1 : 0;
            $query->where('is_caveated', $caveatBit);
        }
    }

    // --- Row normalizer ----------------------------------------------

    private function normalizeRow($row, string $sourceLabel): array
    {
        $transactionDate = $row->transaction_date;
        $sortDate = null;
        $displayDate = null;

        if ($transactionDate) {
            try {
                $carbon = Carbon::parse($transactionDate);
                $sortDate = $carbon->toDateString();
                $displayDate = $carbon->format('M j, Y');
            } catch (\Exception $e) {
                $displayDate = (string) $transactionDate;
            }
        }

        // Determine primary parties
        $party1 = $row->party_1 ?: ($row->assignor ?: ($row->mortgagor ?: ($row->grantor ?: ($row->surrenderor ?: ($row->lessor ?? null)))));
        $party2 = $row->party_2 ?: ($row->assignee ?: ($row->mortgagee ?: ($row->grantee ?: ($row->surrenderee ?: ($row->lessee ?? null)))));

        // Build registration string
        $serialNo = $row->serial_no;
        $pageNo = $row->page_no;
        $volumeNo = $row->volume_no;

        $registration = null;
        if ($serialNo || $pageNo || $volumeNo) {
            $registration = trim(($serialNo ?: '0') . '/' . ($pageNo ?: '0') . '/' . ($volumeNo ?: '0'));
        }

        return [
            'id' => $row->id,
            'file_number' => $row->file_number,
            'mlsFNo' => $row->mlsFNo,
            'fileno' => $row->fileno,
            'kangisFileNo' => $row->kangisFileNo,
            'NewKANGISFileno' => $row->NewKANGISFileno,
            'transaction_type' => $row->transaction_type ?: '-',
            'transaction_date' => $displayDate ?: '-',
            'sort_date' => $sortDate,
            'party_1' => $party1 ?: '-',
            'party_2' => $party2 ?: '-',
            'party_3' => $row->party_3 ?? '-',
            'party_4' => $row->party_4 ?? '-',
            'land_use' => $row->land_use ?: '-',
            'location' => $row->location ?: '-',
            'lgsaOrCity' => $row->lgsaOrCity ?? ($row->lga ?? '-'),
            'districtName' => $row->districtName ?? ($row->district ?? '-'),
            'registration' => $registration ?: '-',
            'regNo' => $row->regNo ?? '-',
            'serial_no' => $serialNo ?? '-',
            'page_no' => $pageNo ?? '-',
            'volume_no' => $volumeNo ?? '-',
            'size' => $row->size ?? '-',
            'caveat' => $row->caveat ?? '-',
            'caveat_id' => $row->caveat_id ?? null,
            'caveated_comment' => $row->caveated_comment ?? null,
            'is_caveated' => $row->is_caveated ?? 0,
            'plot_no' => $row->plot_no ?? '-',
            'comments' => $row->comments ?? '-',
            'cofo_comment' => $row->cofo_comment ?? null,
            'prop_id' => $row->prop_id ?? null,
            'deeds_date' => $row->deeds_date ?? null,
            'deeds_time' => $row->deeds_time ?? null,
            'reg_date' => $row->reg_date ?? null,
            'reg_time' => $row->reg_time ?? null,
            'tp_no' => $row->tp_no ?? null,
            'source_table' => $sourceLabel,
        ];
    }

    /**
     * Collect non-empty prop_ids from normalized result rows.
     */
    private function collectPropIds(array $rows): array
    {
        $propIds = [];
        foreach ($rows as $row) {
            $pid = $row['prop_id'] ?? null;
            if ($pid && trim((string) $pid) !== '') {
                $propIds[trim((string) $pid)] = true;
            }
        }
        // Return as explicit strings so PDO binds them as NVARCHAR.
        // PHP silently converts numeric string keys to int, which causes SQL Server
        // to cast all nvarchar prop_id column values to INT, failing on dirty data.
        return array_values(array_map('strval', array_keys($propIds)));
    }

    /**
     * Build a map of existing {source_table => [id, id, ...]} to avoid duplicates.
     */
    private function buildExistingIdMap(array ...$resultSets): array
    {
        $map = [];
        foreach ($resultSets as $rows) {
            foreach ($rows as $row) {
                $source = $row['source_table'] ?? '';
                $tableKey = match ($source) {
                    'File History' => 'file_history_staging',
                    'CofO' => 'CofO_staging',
                    'PRA' => 'pra',
                    'Deed Registration' => 'deed_registrations',
                    default => $source,
                };
                $map[$tableKey][] = $row['id'];
            }
        }
        return $map;
    }

    /**
     * Search a table by prop_id list, excluding already-fetched IDs.
     */
    private function searchByPropIds($conn, string $tableName, array $propIds, array $excludeIds): array
    {
        if (empty($propIds))
            return [];

        $selectMap = [
            'file_history_staging' => [
                'id',
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                'mlsFNo',
                'fileno',
                'kangisFileNo',
                'NewKANGISFileno',
                'transaction_type',
                DB::raw("TRY_CONVERT(DATE, transaction_date) AS transaction_date"),
                'party_1',
                'party_2',
                'party_3',
                'party_4',
                DB::raw("Assignor AS assignor"),
                DB::raw("Assignee AS assignee"),
                DB::raw("Mortgagor AS mortgagor"),
                DB::raw("Mortgagee AS mortgagee"),
                DB::raw("Grantor AS grantor"),
                DB::raw("Grantee AS grantee"),
                DB::raw("Surrenderor AS surrenderor"),
                DB::raw("Surrenderee AS surrenderee"),
                DB::raw("Lessor AS lessor"),
                DB::raw("Lessee AS lessee"),
                'land_use',
                'location',
                'lgsaOrCity',
                'districtName',
                DB::raw("serialNo AS serial_no"),
                DB::raw("pageNo AS page_no"),
                DB::raw("volumeNo AS volume_no"),
                'regNo',
                'prop_id',
                'comments',
                DB::raw("plot_size AS size"),
                DB::raw("CASE WHEN is_caveated = 1 THEN 'Yes' ELSE 'No' END AS caveat"),
                'plot_no',
                DB::raw("'file_history_staging' AS source_table"),
            ],
            'CofO_staging' => [
                'id',
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                'mlsFNo',
                'fileno',
                'kangisFileNo',
                'NewKANGISFileno',
                'transaction_type',
                DB::raw("TRY_CONVERT(DATE, transaction_date) AS transaction_date"),
                DB::raw("NULL AS party_1"),
                DB::raw("NULL AS party_2"),
                DB::raw("NULL AS party_3"),
                DB::raw("NULL AS party_4"),
                DB::raw("Assignor AS assignor"),
                DB::raw("Assignee AS assignee"),
                DB::raw("Mortgagor AS mortgagor"),
                DB::raw("Mortgagee AS mortgagee"),
                DB::raw("Grantor AS grantor"),
                DB::raw("Grantee AS grantee"),
                DB::raw("Surrenderor AS surrenderor"),
                DB::raw("Surrenderee AS surrenderee"),
                DB::raw("Lessor AS lessor"),
                DB::raw("Lessee AS lessee"),
                'land_use',
                'location',
                'lgsaOrCity',
                DB::raw("serialNo AS serial_no"),
                DB::raw("pageNo AS page_no"),
                DB::raw("volumeNo AS volume_no"),
                'regNo',
                'prop_id',
                'comments',
                DB::raw("NULL AS size"),
                DB::raw("CASE WHEN is_caveated = 1 THEN 'Yes' ELSE 'No' END AS caveat"),
                'plot_no',
                DB::raw("'CofO_staging' AS source_table"),
            ],
            'pra' => [
                'id',
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                'mlsFNo',
                'fileno',
                'kangisFileNo',
                'NewKANGISFileno',
                'transaction_type',
                DB::raw("TRY_CONVERT(DATE, transaction_date) AS transaction_date"),
                'party_1',
                'party_2',
                'party_3',
                'party_4',
                DB::raw("Assignor AS assignor"),
                DB::raw("Assignee AS assignee"),
                DB::raw("Mortgagor AS mortgagor"),
                DB::raw("Mortgagee AS mortgagee"),
                DB::raw("Grantor AS grantor"),
                DB::raw("Grantee AS grantee"),
                DB::raw("Surrenderor AS surrenderor"),
                DB::raw("Surrenderee AS surrenderee"),
                DB::raw("Lessor AS lessor"),
                DB::raw("Lessee AS lessee"),
                'land_use',
                'location',
                'lgsaOrCity',
                'districtName',
                DB::raw("serialNo AS serial_no"),
                DB::raw("pageNo AS page_no"),
                DB::raw("volumeNo AS volume_no"),
                'regNo',
                'prop_id',
                'comments',
                DB::raw("plot_size AS size"),
                DB::raw("CASE WHEN is_caveated = 1 THEN 'Yes' ELSE 'No' END AS caveat"),
                'plot_no',
                DB::raw("'pra' AS source_table"),
            ],
            'deed_registrations' => [
                'id',
                DB::raw("fileno AS file_number"),
                DB::raw("fileno AS mlsFNo"),
                'fileno',
                DB::raw("NULL AS kangisFileNo"),
                DB::raw("NULL AS NewKANGISFileno"),
                DB::raw("instrument_type AS transaction_type"),
                DB::raw("TRY_CONVERT(DATE, deeds_date) AS transaction_date"),
                DB::raw("grantor AS party_1"),
                DB::raw("grantee AS party_2"),
                DB::raw("NULL AS party_3"),
                DB::raw("NULL AS party_4"),
                DB::raw("NULL AS assignor"),
                DB::raw("NULL AS assignee"),
                DB::raw("NULL AS mortgagor"),
                DB::raw("NULL AS mortgagee"),
                DB::raw("grantor"),
                DB::raw("grantee"),
                DB::raw("NULL AS surrenderor"),
                DB::raw("NULL AS surrenderee"),
                DB::raw("NULL AS lessor"),
                DB::raw("NULL AS lessee"),
                DB::raw("NULL AS land_use"),
                DB::raw("COALESCE(district, lga) AS location"),
                'district',
                'lga',
                'serial_no',
                'page_no',
                'volume_no',
                DB::raw("registration_number AS regNo"),
                'prop_id',
                DB::raw("property_description AS comments"),
                'size',
                DB::raw("NULL AS caveat"),
                DB::raw("plot_number AS plot_no"),
                DB::raw("'deed_registrations' AS source_table"),
            ],
        ];

        $labelMap = [
            'file_history_staging' => 'File History',
            'CofO_staging' => 'CofO',
            'pra' => 'PRA',
            'deed_registrations' => 'Deed Registration',
        ];

        $select = $selectMap[$tableName] ?? null;
        if (!$select)
            return [];

        $query = $conn->table($tableName)
            ->select($select)
            ->whereIn('prop_id', array_map('strval', $propIds));

        if (!empty($excludeIds)) {
            // Cast exclude IDs to int to match the INT primary key column.
            $query->whereNotIn('id', array_map('intval', array_filter($excludeIds, 'is_numeric')));
        }

        $this->applySoftDeleteFilter($query, $tableName);

        return $query->get()->map(fn($r) => $this->normalizeRow($r, $labelMap[$tableName]))->toArray();
    }

    // ================================================================
    // Cleanup Mode operations: Match, Drop, Remove, Update
    // ================================================================

    /**
     * Valid table names for cleanup operations (whitelist).
     */
    private const VALID_TABLES = [
        'file_history_staging',
        'CofO_staging',
        'pra',
        'deed_registrations',
    ];

    /**
     * Editable columns per table (whitelist for update operations).
     */
    private const EDITABLE_COLUMNS = [
        'file_history_staging' => [
            'mlsFNo',
            'kangisFileNo',
            'NewKANGISFileno',
            'fileno',
            'transaction_type',
            'transaction_date',
            'land_use',
            'location',
            'party_1',
            'party_2',
            'party_3',
            'party_4',
            'Assignor',
            'Assignee',
            'Mortgagor',
            'Mortgagee',
            'Grantor',
            'Grantee',
            'Surrenderor',
            'Surrenderee',
            'Lessor',
            'Lessee',
            'serialNo',
            'pageNo',
            'volumeNo',
            'regNo',
            'plot_no',
            'plot_size',
            'lgsaOrCity',
            'districtName',
            'comments',
            'remarks',
        ],
        'CofO_staging' => [
            'mlsFNo',
            'kangisFileNo',
            'NewKANGISFileno',
            'fileno',
            'np_fileno',
            'transaction_type',
            'transaction_date',
            'land_use',
            'location',
            'Assignor',
            'Assignee',
            'Mortgagor',
            'Mortgagee',
            'Grantor',
            'Grantee',
            'Surrenderor',
            'Surrenderee',
            'Lessor',
            'Lessee',
            'serialNo',
            'pageNo',
            'volumeNo',
            'regNo',
            'plot_no',
            'lgsaOrCity',
            'comments',
            'remarks',
            'period',
            'period_unit',
        ],
        'pra' => [
            'mlsFNo',
            'kangisFileNo',
            'NewKANGISFileno',
            'fileno',
            'transaction_type',
            'transaction_date',
            'land_use',
            'location',
            'party_1',
            'party_2',
            'party_3',
            'Assignor',
            'Assignee',
            'Mortgagor',
            'Mortgagee',
            'Grantor',
            'Grantee',
            'Surrenderor',
            'Surrenderee',
            'Lessor',
            'Lessee',
            'Donor',
            'Donee',
            'Vendor',
            'Purchaser',
            'serialNo',
            'pageNo',
            'volumeNo',
            'regNo',
            'plot_no',
            'plot_size',
            'lgsaOrCity',
            'districtName',
            'comments',
            'remarks',
        ],
        'deed_registrations' => [
            'fileno',
            'parent_fileno',
            'instrument_type',
            'registration_number',
            'volume_no',
            'page_no',
            'serial_no',
            'deeds_date',
            'deeds_time',
            'instrument_date',
            'grantor',
            'grantee',
            'lga',
            'district',
            'plot_number',
            'size',
            'property_description',
        ],
    ];

    /**
     * Validate that table name is in the whitelist.
     */
    private function validateTable(string $table): void
    {
        if (!in_array($table, self::VALID_TABLES, true)) {
            throw new \InvalidArgumentException("Invalid table: {$table}");
        }
    }

    /**
     * Match: Assign orphan record(s) to a prop_id group.
     * Sets the prop_id on the specified record(s).
     *
     * @param string $table     The source table name
     * @param array  $ids       Record IDs to update
     * @param string $propId    The target prop_id to assign
     * @return int Number of records updated
     */
    public function matchRecords(string $table, array $ids, string $propId): int
    {
        $this->validateTable($table);

        if (empty($ids) || empty(trim($propId))) {
            return 0;
        }

        return DB::connection('sqlsrv')
            ->table($table)
            ->whereIn('id', $ids)
            ->update([
                'prop_id' => trim($propId),
                'updated_at' => now(),
            ]);
    }

    /**
     * Drop: Unlink record(s) from a prop_id group.
     * Sets prop_id = NULL so they become orphan records.
     *
     * @param string $table  The source table name
     * @param array  $ids    Record IDs to unlink
     * @return int Number of records updated
     */
    public function dropRecords(string $table, array $ids): int
    {
        $this->validateTable($table);

        if (empty($ids)) {
            return 0;
        }

        return DB::connection('sqlsrv')
            ->table($table)
            ->whereIn('id', $ids)
            ->update([
                'prop_id' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Remove: Soft-delete record(s) by setting is_deleted = 1.
     *
     * @param string $table  The source table name
     * @param array  $ids    Record IDs to soft-delete
     * @return int Number of records updated
     */
    public function removeRecords(string $table, array $ids): int
    {
        $this->validateTable($table);

        if (empty($ids)) {
            return 0;
        }

        return DB::connection('sqlsrv')
            ->table($table)
            ->whereIn('id', $ids)
            ->update([
                'is_deleted' => 1,
                'updated_at' => now(),
            ]);
    }

    /**
     * Update: Edit fields on a single record.
     * Only whitelisted columns per table are accepted.
     *
     * @param string $table  The source table name
     * @param int    $id     Record ID
     * @param array  $fields Associative array of column => value
     * @return bool
     */
    public function updateRecord(string $table, int $id, array $fields): bool
    {
        $this->validateTable($table);

        $allowedColumns = self::EDITABLE_COLUMNS[$table] ?? [];
        $safeFields = [];

        foreach ($fields as $col => $val) {
            if (in_array($col, $allowedColumns, true)) {
                $safeFields[$col] = $val;
            }
        }

        if (empty($safeFields)) {
            return false;
        }

        $safeFields['updated_at'] = now();

        return DB::connection('sqlsrv')
            ->table($table)
            ->where('id', $id)
            ->update($safeFields) > 0;
    }

    /**
     * Transfer caveat fields from one record to another record.
     * Supported tables: pra and CofO_staging.
     */
    public function transferCaveat(string $sourceTable, int $sourceId, string $targetTable, int $targetId): bool
    {
        $allowed = ['pra', 'CofO_staging'];
        if (!in_array($sourceTable, $allowed, true) || !in_array($targetTable, $allowed, true)) {
            throw new \InvalidArgumentException('Caveat transfer supports only PRA and CofO records.');
        }

        if ($sourceTable === $targetTable && $sourceId === $targetId) {
            throw new \InvalidArgumentException('Source and target records cannot be the same.');
        }

        $source = DB::connection('sqlsrv')
            ->table($sourceTable)
            ->where('id', $sourceId)
            ->first(['id', 'is_caveated', 'caveat_id', 'caveated_comment']);

        if (!$source) {
            throw new \InvalidArgumentException('Source record not found.');
        }

        $targetExists = DB::connection('sqlsrv')
            ->table($targetTable)
            ->where('id', $targetId)
            ->exists();

        if (!$targetExists) {
            throw new \InvalidArgumentException('Target record not found.');
        }

        $hasCaveat = ((int) ($source->is_caveated ?? 0) === 1)
            || !empty($source->caveat_id)
            || trim((string) ($source->caveated_comment ?? '')) !== '';

        if (!$hasCaveat) {
            throw new \InvalidArgumentException('Source record has no caveat to transfer.');
        }

        return DB::connection('sqlsrv')->transaction(function () use ($sourceTable, $sourceId, $targetTable, $targetId, $source) {
            $now = now();

            $targetUpdate = [
                'is_caveated' => 1,
                'caveat_id' => $source->caveat_id,
                'caveated_comment' => $source->caveated_comment,
                'updated_at' => $now,
            ];

            $sourceClear = [
                'is_caveated' => 0,
                'caveat_id' => null,
                'caveated_comment' => null,
                'updated_at' => $now,
            ];

            $targetAffected = DB::connection('sqlsrv')
                ->table($targetTable)
                ->where('id', $targetId)
                ->update($targetUpdate);

            $sourceAffected = DB::connection('sqlsrv')
                ->table($sourceTable)
                ->where('id', $sourceId)
                ->update($sourceClear);

            return $targetAffected > 0 && $sourceAffected > 0;
        });
    }

    /**
     * Get a single record by ID and table for editing.
     */
    public function getRecord(string $table, int $id): ?object
    {
        $this->validateTable($table);

        $query = DB::connection('sqlsrv')
            ->table($table)
            ->where('id', $id);

        $this->applySoftDeleteFilter($query, $table);

        return $query->first();
    }

    private function applySoftDeleteFilter($query, string $tableName): void
    {
        if ($this->tableHasIsDeletedColumn($tableName)) {
            $query->where(function ($q) {
                $q->where('is_deleted', 0)->orWhereNull('is_deleted');
            });
        }
    }

    private function tableHasIsDeletedColumn(string $tableName): bool
    {
        if (array_key_exists($tableName, $this->softDeleteColumnCache)) {
            return $this->softDeleteColumnCache[$tableName];
        }

        $this->softDeleteColumnCache[$tableName] = Schema::connection('sqlsrv')->hasColumn($tableName, 'is_deleted');

        return $this->softDeleteColumnCache[$tableName];
    }

    /**
     * Detect prop_id conflicts in a set of selected records.
     * Returns distinct prop_ids found across the selection.
     */
    public function detectPropIdConflicts(array $selections): array
    {
        $propIds = [];

        foreach ($selections as $sel) {
            $table = $sel['table'] ?? '';
            $ids = $sel['ids'] ?? [];

            if (!in_array($table, self::VALID_TABLES, true) || empty($ids)) {
                continue;
            }

            $records = DB::connection('sqlsrv')
                ->table($table)
                ->whereIn('id', $ids)
                ->whereNotNull('prop_id')
                ->where('prop_id', '!=', '')
                ->pluck('prop_id')
                ->toArray();

            foreach ($records as $pid) {
                $propIds[trim($pid)] = true;
            }
        }

        return array_keys($propIds);
    }

    /**
     * Persist timeline order for a prop_id.
     *
     * @param string   $propId
     * @param array    $items Each item: ['table' => string, 'id' => int, 'order' => int]
     * @param int|null $userId
     * @return int Number of rows written
     */
    public function saveTimelineArrangement(string $propId, array $items, ?int $userId = null): int
    {
        $propId = trim($propId);
        if ($propId === '' || empty($items)) {
            return 0;
        }

        $payload = [];
        $seen = [];

        foreach ($items as $item) {
            $table = (string) ($item['table'] ?? '');
            $id = (int) ($item['id'] ?? 0);
            $order = (int) ($item['order'] ?? 0);

            $this->validateTable($table);
            if ($id <= 0 || $order <= 0) {
                continue;
            }

            $key = $table . ':' . $id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $payload[] = [
                'prop_id' => $propId,
                'source_table' => $table,
                'source_id' => $id,
                'display_order' => $order,
                'arranged_by' => $userId,
                'arranged_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (empty($payload)) {
            return 0;
        }

        return DB::connection('sqlsrv')->transaction(function () use ($propId, $payload) {
            DB::connection('sqlsrv')
                ->table(self::ARRANGEMENT_TABLE)
                ->where('prop_id', $propId)
                ->delete();

            DB::connection('sqlsrv')
                ->table(self::ARRANGEMENT_TABLE)
                ->insert($payload);

            return count($payload);
        });
    }

    /**
     * Label-to-table mapping for arrangement keys.
     */
    private const SOURCE_LABEL_TO_TABLE = [
        'File History' => 'file_history_staging',
        'CofO' => 'CofO_staging',
        'PRA' => 'pra',
        'Deed Registration' => 'deed_registrations',
    ];

    /**
     * Apply saved arrangement order to merged results.
     * If a common prop_id exists and has a saved arrangement, reorder accordingly.
     * Arranged items come first (by display_order), unarranged items follow in original order.
     */
    private function applyArrangementOrder(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }

        // Find the common prop_id (use the first non-empty one)
        $propId = null;
        foreach ($rows as $row) {
            $pid = $row['prop_id'] ?? null;
            if ($pid && trim((string) $pid) !== '') {
                $propId = trim((string) $pid);
                break;
            }
        }

        if (!$propId) {
            return $rows;
        }

        $arrangement = $this->getTimelineArrangement($propId);
        if (empty($arrangement)) {
            return $rows;
        }

        // Sort: arranged items first by display_order, unarranged items keep original order
        $indexed = [];
        foreach ($rows as $idx => $row) {
            $dbTable = self::SOURCE_LABEL_TO_TABLE[$row['source_table'] ?? ''] ?? ($row['source_table'] ?? '');
            $key = $dbTable . ':' . ($row['id'] ?? '');
            $indexed[] = [
                'row' => $row,
                'idx' => $idx,
                'order' => $arrangement[$key] ?? null,
            ];
        }

        usort($indexed, function ($a, $b) {
            $hasA = $a['order'] !== null;
            $hasB = $b['order'] !== null;
            if ($hasA && $hasB)
                return $a['order'] - $b['order'];
            if ($hasA && !$hasB)
                return -1;
            if (!$hasA && $hasB)
                return 1;
            return $a['idx'] - $b['idx'];
        });

        return array_map(fn($x) => $x['row'], $indexed);
    }

    /**
     * Load saved timeline order for a prop_id.
     *
     * @return array<string,int> map key => order where key is "table:id"
     */
    public function getTimelineArrangement(string $propId): array
    {
        $propId = trim($propId);
        if ($propId === '') {
            return [];
        }

        $rows = DB::connection('sqlsrv')
            ->table(self::ARRANGEMENT_TABLE)
            ->select(['source_table', 'source_id', 'display_order'])
            ->where('prop_id', $propId)
            ->orderBy('display_order')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->source_table . ':' . $row->source_id] = (int) $row->display_order;
        }

        return $map;
    }

    /**
     * Identify file number type by pattern.
     * .
     */
    public function identifyFileNumberType($fileNo): string
    {
        if (empty($fileNo))
            return 'unknown';

        $cleanFileNo = trim($fileNo);

        if (preg_match('/^ST-(RES|COM|IND|AG)-\d{4}-\d+-\d+$/i', $cleanFileNo))
            return 'st';
        if (preg_match('/^ST-(RES|COM|IND|AG)-\d{4}-\d+$/i', $cleanFileNo))
            return 'parent';

        if (
            preg_match('/^(COM|RES|IND|AG|CON-COM|CON-RES|CON-AG|CON-IND)-\d{4}-\d+$/i', $cleanFileNo) ||
            preg_match('/^(COM|RES|IND|AG|CON-COM|CON-RES|CON-AG|CON-IND)-\d+$/i', $cleanFileNo)
        ) {
            return 'mls';
        }

        if (preg_match('/^[A-Z]{4}\s?\d{3,6}$/i', $cleanFileNo))
            return 'kangis';
        if (preg_match('/^KN\d{2,6}$/i', $cleanFileNo))
            return 'new_kangis';

        return 'unknown';
    }
}
