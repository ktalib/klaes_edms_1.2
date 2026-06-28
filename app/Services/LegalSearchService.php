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

        // Fetch SME allowed file numbers to prevent prop_id conversions/collisions
        $allowedSmeFileNos = $this->getSmeAllowedFileNos($fileNo, $conn);
        $filters['allowedSmeFileNos'] = $allowedSmeFileNos;

        $fileHistoryRecords = $this->searchFileHistoryStaging($conn, $filters);
        $cofoRecords = $this->searchCofoStaging($conn, $filters);
        $praRecords = $this->searchPra($conn, $filters);
        $deedRecords = $this->searchDeedRegistrations($conn, $filters);

        // Fetch active prop_id and parent_prop_id from active indexes if available (bypass for SME searches)
        $activePropIds = [];
        if ($fileNo !== '' && empty($allowedSmeFileNos)) {
            $activeIndexing = $conn->table('file_indexings')
                ->where('file_number', $fileNo)
                ->whereNull('deleted_at')
                ->first(['prop_id', 'parent_prop_id']);
            if ($activeIndexing) {
                if ($activeIndexing->prop_id) {
                    $activePropIds[] = (string) $activeIndexing->prop_id;
                }
                if ($activeIndexing->parent_prop_id) {
                    $activePropIds = array_merge($activePropIds, array_map('trim', explode(',', $activeIndexing->parent_prop_id)));
                }
            }
            
            $activeFileNo = $conn->table('fileNumber')
                ->where('mlsfNo', $fileNo)
                ->first(['parent_prop_id']);
            if ($activeFileNo && $activeFileNo->parent_prop_id) {
                $activePropIds = array_merge($activePropIds, array_map('trim', explode(',', $activeFileNo->parent_prop_id)));
            }

            // PRA merger records store parent_prop_id (source file prop_ids) directly on the row.
            // Extract these so the search expands to include the source files' records.
            foreach ($praRecords as $praRow) {
                $ppid = trim((string) ($praRow['parent_prop_id'] ?? ''));
                if ($ppid !== '') {
                    foreach (array_map('trim', explode(',', $ppid)) as $pid) {
                        if ($pid !== '') {
                            $activePropIds[] = $pid;
                        }
                    }
                }
            }
        }

        // --- prop_id cross-table expansion (bypass for SME searches) ---
        // Collect prop_ids from initial results, then pull related records from all 4 tables
        $propIds = [];
        if (empty($allowedSmeFileNos)) {
            $propIds = $this->collectPropIds(array_merge($fileHistoryRecords, $cofoRecords, $praRecords, $deedRecords));
            foreach ($activePropIds as $pid) {
                if (trim($pid) !== '') {
                    $propIds[] = trim($pid);
                }
            }
            $propIds = array_values(array_unique($propIds));
        }

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

        // Guard against cross-property contamination.
        // related_file_number recertification links and SME (related_fileno) expansion can pull
        // in transaction records that belong to a DIFFERENT property (different prop_id) — e.g.
        // a KANGIS recertification row tying RES-2026-2270 (prop_id A) to MLKN 3020 (prop_id B).
        // Establish the prop_id(s) that genuinely belong to the searched file number, then drop
        // any transaction row whose prop_id differs. Merger parents (parent_prop_id) are kept.
        if ($fileNo !== '') {
            $matchesSearchedFile = function (array $row) use ($fileNo): bool {
                foreach (['fileno', 'file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno'] as $col) {
                    $v = trim((string) ($row[$col] ?? ''));
                    if ($v !== '' && strcasecmp($v, $fileNo) === 0) {
                        return true;
                    }
                }
                return false;
            };
            
            // Helper to check if file numbers are truly distinct (not just prefix matches)
            $isDistinctFile = function (string $rowFileNo) use ($fileNo): bool {
                $rowFileNo = trim(strtoupper($rowFileNo));
                $searchFileNo = trim(strtoupper($fileNo));
                
                // Exact match is not distinct
                if ($rowFileNo === $searchFileNo) {
                    return false;
                }
                
                // Check if rowFileNo contains searchFileNo as a substring but is a completely different file
                // Example: searching "RES-1992-2508" should exclude "CON-RES-2018-487"
                // They share "RES" but have different years and numbers
                if (strpos($rowFileNo, $searchFileNo) === false && strpos($searchFileNo, $rowFileNo) === false) {
                    return true; // Completely different strings
                }
                
                // Extract the core pattern (PREFIX-YEAR-NUMBER)
                $extractCore = function($fn) {
                    // Match patterns like COM-2025-123 or CON-RES-2018-487 or RES-1992-2508
                    if (preg_match('/((?:CON-)?(?:RES|COM|IND|AG))-(\d{4})-(\d+)/', $fn, $m)) {
                        return $m[1] . '-' . $m[2] . '-' . $m[3];
                    }
                    return $fn;
                };
                
                $rowCore = $extractCore($rowFileNo);
                $searchCore = $extractCore($searchFileNo);
                
                // If cores are different, they're distinct files
                return $rowCore !== $searchCore;
            };

            $searchedPropIds = [];
            foreach ($all as $row) {
                if (($row['source_table'] ?? '') === 'Related Fileno') {
                    continue; // synthetic recert rows don't define the property
                }
                if ($matchesSearchedFile($row)) {
                    $pid = trim((string) ($row['prop_id'] ?? ''));
                    if ($pid !== '') {
                        $searchedPropIds[$pid] = true;
                    }
                    $ppid = trim((string) ($row['parent_prop_id'] ?? ''));
                    foreach (array_filter(array_map('trim', explode(',', $ppid))) as $pp) {
                        $searchedPropIds[$pp] = true;
                    }
                }
            }

            // Only filter when the searched file resolves to a definite prop_id; otherwise leave
            // file-number / orphan-only results untouched.
            if (!empty($searchedPropIds)) {
                $all = array_values(array_filter($all, function ($row) use ($searchedPropIds, $matchesSearchedFile, $isDistinctFile, $fileNo) {
                    // Always keep synthetic recertification rows (contextual markers).
                    if (($row['source_table'] ?? '') === 'Related Fileno') {
                        return true;
                    }
                    
                    // Get the row's file number
                    $rowFileNo = '';
                    foreach (['fileno', 'file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno'] as $col) {
                        $v = trim((string) ($row[$col] ?? ''));
                        if ($v !== '') {
                            $rowFileNo = $v;
                            break;
                        }
                    }
                    
                    $pid = trim((string) ($row['prop_id'] ?? ''));
                    if ($pid !== '' && isset($searchedPropIds[$pid])) {
                        return true;
                    }
                    
                    $ppid = trim((string) ($row['parent_prop_id'] ?? ''));
                    foreach (array_filter(array_map('trim', explode(',', $ppid))) as $pp) {
                        if ($pp !== '' && isset($searchedPropIds[$pp])) {
                            return true;
                        }
                    }
                    
                    // If this row's file number is distinctly different from the searched file, exclude it
                    if ($rowFileNo !== '' && $isDistinctFile($rowFileNo)) {
                        return false;
                    }
                    
                    // Keep orphan rows (no prop_id) only if they directly match the searched file number.
                    if ($pid === '' && $matchesSearchedFile($row)) {
                        return true;
                    }
                    return false;
                }));
            }
        }

        // Fetch matching related_file_number entries (orange Recertification rows)
        // and append them as synthetic timeline rows.
        $relatedRecertRows = $this->fetchRelatedRecertificationRows($conn, $fileNo, $all);
        if (!empty($relatedRecertRows)) {
            $all = array_merge($all, $relatedRecertRows);
        }

        // If searched by a subdivided unit (standard or Sectional Titling), only keep its own records and the mother's records,
        // and explicitly exclude other unit records.
        $motherFileNo = null;
        if ($this->isSubdividedUnit($fileNo, $motherFileNo)) {
            $all = array_filter($all, function ($row) use ($fileNo, $motherFileNo) {
                // Get the row's file number
                $rowFileNo = trim($row['fileno'] ?? ($row['file_number'] ?? ($row['mlsFNo'] ?? '')));
                if ($rowFileNo === '') {
                    return true; // Keep if no file number (e.g. orphan record with same prop_id)
                }
                
                // Keep if matches the searched unit or the mother file number
                if (strcasecmp($rowFileNo, $fileNo) === 0 || strcasecmp($rowFileNo, $motherFileNo) === 0) {
                    return true;
                }
                
                // If it's a subdivided unit file number pattern but not the searched one, exclude it!
                if ($this->isSubdividedUnit($rowFileNo)) {
                    return false; // Exclude other units!
                }
                
                return true;
            });
            $all = array_values($all);
        }

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
                $fileIndexingDataList = $conn->table('file_indexings')
                    ->whereNull('deleted_at')
                    ->where(function ($q) use ($primaryCandidates) {
                        foreach ($primaryCandidates as $candidate) {
                            $q->orWhere('file_number', $candidate)
                                ->orWhere('related_fileno', 'like', '%' . $candidate . '%');
                        }
                    })
                    ->select('file_title', 'district', 'lga', 'land_use_type', 'plot_number', 'tp_no', 'related_fileno', 'file_number')
                    ->get();

                $fileIndexingData = null;
                
                // Priority 1: Exact match on file_number
                foreach ($fileIndexingDataList as $row) {
                    foreach ($primaryCandidates as $candidate) {
                        if (strcasecmp((string)$row->file_number, (string)$candidate) === 0) {
                            $fileIndexingData = $row;
                            break 2;
                        }
                    }
                }
                
                // Priority 2: Fallback to the first match (e.g. matched via related_fileno)
                if (!$fileIndexingData && $fileIndexingDataList->isNotEmpty()) {
                    $fileIndexingData = $fileIndexingDataList->first();
                }
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

        // DCIV investigation flag — a searched file tied to a DCIV (on either side
        // of master_dciv_links) is marked Under Investigation.
        $investigation = $this->resolveDcivInvestigation([$fileNo], $all);

        return [
            'transactions' => $all,
            'under_investigation' => $investigation !== null,
            'investigation_note' => $investigation['note'] ?? null,
            'investigation_reason' => $investigation['reason'] ?? null,
            'investigation_dciv_file_number' => $investigation['dciv_file_number'] ?? null,
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

    /**
     * Pull rows from related_file_number that match the searched file_number
     * OR any prop_id that's already in the result set, and turn each into a
     * synthetic timeline entry tagged as 'Related Fileno' / Recertification.
     */
    private function fetchRelatedRecertificationRows($conn, string $fileNo, array $existingRows): array
    {
        if (!Schema::connection($conn->getName())->hasTable('related_file_number')) {
            return [];
        }

        // Collect prop_ids already in the result set
        $propIds = [];
        foreach ($existingRows as $r) {
            $pid = trim((string) ($r['prop_id'] ?? ''));
            if ($pid !== '') {
                $propIds[$pid] = true;
            }
        }
        $propIds = array_keys($propIds);

        if ($fileNo === '' && empty($propIds)) {
            return [];
        }

        $query = $conn->table('related_file_number AS rfn')
            ->leftJoin('file_indexings AS fi_rel', function ($j) {
                $j->on('fi_rel.file_number', '=', 'rfn.related_fileno')
                  ->whereNull('fi_rel.deleted_at');
            })
            ->where(function ($q) use ($fileNo, $propIds) {
                if ($fileNo !== '') {
                    $q->orWhere('rfn.file_number', $fileNo)
                      ->orWhere('rfn.related_fileno', $fileNo);
                }
                if (!empty($propIds)) {
                    $q->orWhereIn('rfn.prop_id', $propIds);
                }
            })
            ->select(
                'rfn.*',
                'fi_rel.file_title    AS related_file_title',
                'fi_rel.current_holder AS related_current_holder'
            );

        $rows = $query->orderBy('rfn.id')->get();

        $out = [];
        foreach ($rows as $row) {
            $createdAt = $row->created_at ?? null;
            $sortDate = null;
            $displayDate = '-';
            if ($createdAt) {
                try {
                    $c = Carbon::parse($createdAt);
                    $sortDate = $c->toDateString();
                    $displayDate = $c->format('M j, Y');
                } catch (\Exception $e) { /* ignore */ }
            }

            $out[] = [
                'id'                => $row->id,
                'file_number'       => $row->related_fileno, // orange-highlighted column
                'mlsFNo'            => $row->related_fileno,
                'fileno'            => $row->related_fileno,
                'kangisFileNo'      => null,
                'NewKANGISFileno'   => null,
                'transaction_type'  => $row->transaction_type ?: 'Recertification',
                'transaction_date'  => $displayDate,
                'sort_date'         => $sortDate,
                'party_1'           => $row->related_file_title
                                        ?: $row->related_current_holder
                                        ?: '-',
                'party_2'           => $row->party_2 ?: '-',
                'party_3'           => '-',
                'party_4'           => '-',
                'land_use'          => '-',
                'location'          => $row->location ?: '-',
                'lgsaOrCity'        => '-',
                'districtName'      => '-',
                'registration'      => '-',
                'regNo'             => '-',
                'serial_no'         => '-',
                'page_no'           => '-',
                'volume_no'         => '-',
                'size'              => '-',
                'caveat'            => '-',
                'caveat_id'         => null,
                'caveated_comment'  => null,
                'is_caveated'       => 0,
                'plot_no'           => '-',
                'comments'          => $row->comment ?: '-',
                'cofo_comment'      => null,
                'prop_id'           => $row->prop_id,
                'parent_prop_id'    => null,
                'deeds_date'        => null,
                'deeds_time'        => null,
                'reg_date'          => null,
                'reg_time'          => null,
                'tp_no'             => null,
                'source_table'      => 'Related Fileno',
                // Carry through the parent file_number for context (the file
                // that LISTED this related fileno).
                'parent_file_number' => $row->file_number,
            ];
        }

        return $out;
    }

    private function emptyResult(): array
    {
        return [
            'transactions' => [],
            'under_investigation' => false,
            'investigation_note' => null,
            'investigation_reason' => null,
            'investigation_dciv_file_number' => null,
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
                'title_status',
                'title_status_remark',
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
                'title_status',
                'title_status_remark',
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
                'parent_prop_id',
                'comments',
                'title_status',
                'title_status_remark',
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
                'title_status',
                'title_status_remark',
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

        // Certificate of Occupancy instruments are mirrored into CofO_staging
        // (the CofO tab). Exclude them here so the same CofO does not also show
        // up under Deeds Registration as a duplicate.
        $this->excludeCofoFromDeedRegistrations($query);

        return $query->get()->map(fn($r) => $this->normalizeRow($r, 'Deed Registration'))->toArray();
    }

    /**
     * Drop Certificate of Occupancy rows from a deed_registrations query.
     * CofOs live in CofO_staging now; keeping them here would duplicate the
     * record across the Deeds Registration and CofO tabs. Null instrument
     * types are kept (they are not CofO).
     */
    private function excludeCofoFromDeedRegistrations($query): void
    {
        $query->where(function ($q) {
            $q->whereNull('instrument_type')
              ->orWhere('instrument_type', 'NOT LIKE', '%Certificate of Occupancy%');
        });
    }

    // --- Shared filter logic -----------------------------------------
    // Table-aware: uses correct real column names per table

    private function applyFilters($query, array $f, string $tableName, array $fileColumns): void
    {
        $isDeed = ($tableName === 'deed_registrations');
        $isCofO = ($tableName === 'CofO_staging');

        // File number filter (Exact match based on File Number Selector, support subdivision parent/unit search)
        if ($f['fileNo'] !== '') {
            $searchedFileNo = $f['fileNo'];
            
            if (!empty($f['allowedSmeFileNos'])) {
                $searchFileNos = $f['allowedSmeFileNos'];
            } else {
                $searchFileNos = [$searchedFileNo];
                $motherFileNo = null;
                if ($this->isSubdividedUnit($searchedFileNo, $motherFileNo)) {
                    $searchFileNos[] = $motherFileNo;
                }
            }
            
            $query->where(function ($subQ) use ($searchFileNos, $fileColumns) {
                foreach ($fileColumns as $col) {
                    foreach ($searchFileNos as $fn) {
                        $subQ->orWhereRaw("UPPER(LTRIM(RTRIM({$col}))) = UPPER(?)", [$fn]);
                    }
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

        // When the file has been decommissioned via Title Status Update
        // (Withdrawal/Cancellation/Revocation/etc.), surface the status remark
        // as the row comment so it appears in the Legal Search timeline.
        $titleStatus       = $row->title_status ?? 0;
        $titleStatusRemark = $row->title_status_remark ?? null;
        $comments          = $row->comments ?? '-';
        if ((int) $titleStatus === 1 && trim((string) $titleStatusRemark) !== '') {
            $comments = $titleStatusRemark;
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
            'comments' => $comments,
            'title_status' => (int) $titleStatus,
            'title_status_remark' => $titleStatusRemark,
            'cofo_comment' => $row->cofo_comment ?? null,
            'prop_id' => $row->prop_id ?? null,
            'parent_prop_id' => $row->parent_prop_id ?? null,
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
                'title_status',
                'title_status_remark',
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
                'title_status',
                'title_status_remark',
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
                'parent_prop_id',
                'comments',
                'title_status',
                'title_status_remark',
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
                'title_status',
                'title_status_remark',
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

        // CofOs are surfaced from CofO_staging; never from deed_registrations,
        // to avoid the same record appearing in both the Deeds Registration and
        // CofO tabs.
        if ($tableName === 'deed_registrations') {
            $this->excludeCofoFromDeedRegistrations($query);
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

    /**
     * Helper: Check if a file number is a subdivided unit, and return its mother file number.
     * Excludes ST (Sectional Titling) files completely per project rules.
     */
    public function isSubdividedUnit($fileNo, &$motherFileNo = null): bool
    {
        $cleanFileNo = trim($fileNo);
        if (empty($cleanFileNo)) {
            return false;
        }

        // ST (Sectional Titling) is a separate system; exclude it completely
        if (str_starts_with(strtoupper($cleanFileNo), 'ST-')) {
            return false;
        }
        
        $parts = explode('-', $cleanFileNo);
        $count = count($parts);
        
        // Standard subdivided unit: COM-2025-4-001 (4 parts)
        if ($count === 4) {
            // Region-prefixed MLS file numbers like CON-COM-2026-302 have the 4-digit year at index 2 (third part).
            // Subdivided units without region prefix (COM-2025-4-001) have the 4-digit year at index 1 (second part).
            // If the third part is a 4-digit year, it is a standard MLS file number, NOT a subdivided unit.
            if (isset($parts[2]) && strlen($parts[2]) === 4 && is_numeric($parts[2])) {
                return false;
            }
            array_pop($parts);
            $motherFileNo = implode('-', $parts);
            return true;
        }
        
        // Standard subdivided unit: COM-4-001 (3 parts, second part is not a 4-digit year)
        if ($count === 3 && strlen($parts[1]) !== 4) {
            array_pop($parts);
            $motherFileNo = implode('-', $parts);
            return true;
        }
        
        return false;
    }

    /**
     * Build the print-report data payload for a file/prop_id.
     *
     * This is the shared engine behind both the staff Legal Search print
     * (LegalSearchController::reportTemplateData) and the PHS Portal search slip
     * (PhsSlipController). It dedupes FH/PRA duplicates, canonicalizes instrument
     * types, applies the OP>TOT>RoFO priority + chronological sort, detects
     * caveats/mortgage encumbrances, folds in staging comments, and honors a
     * client-supplied timeline_order and display_* overrides.
     *
     * @param array $q keys: file_number, prop_id, display_file_number, display_file_title,
     *                 display_district_lga, display_land_use, display_size, display_plot_no,
     *                 display_tpno, timeline_order
     * @return array{status:int, payload:array}
     */
    public function buildPrintReport(array $q): array
    {
        $fileNo = trim((string) ($q['file_number'] ?? ''));
        $searchedFileNo = $fileNo; // Preserve the user's searched file number — fallback may overwrite $fileNo later.
        $propId = trim((string) ($q['prop_id'] ?? ''));
        $displayFileNumber = trim((string) ($q['display_file_number'] ?? ''));
        $displayFileTitle = trim((string) ($q['display_file_title'] ?? ''));
        $displayDistrictLga = trim((string) ($q['display_district_lga'] ?? ''));
        $displayLandUse = trim((string) ($q['display_land_use'] ?? ''));
        $displaySize = trim((string) ($q['display_size'] ?? ''));
        $displayPlotNo = trim((string) ($q['display_plot_no'] ?? ''));
        $displayTpno = trim((string) ($q['display_tpno'] ?? ''));

        if ($fileNo === '' && $propId === '') {
            return ['status' => 422, 'payload' => ['success' => false, 'message' => 'file_number or prop_id is required']];
        }

        $timelineOrderRaw = trim((string) ($q['timeline_order'] ?? ''));
        $timelineOrderKeys = [];
        if ($timelineOrderRaw !== '') {
            foreach (array_filter(array_map('trim', explode(',', $timelineOrderRaw))) as $tok) {
                $timelineOrderKeys[$tok] = true;
            }
        }

        $results = $this->search(['query' => $fileNo]);
        $transactions = $results['transactions'] ?? [];

        $allowedPropIds = $propId !== '' ? [$propId] : [];
        foreach ($transactions as $tx) {
            $ppid = trim((string) ($tx['parent_prop_id'] ?? ''));
            if ($ppid !== '') {
                foreach (array_map('trim', explode(',', $ppid)) as $pid) {
                    if ($pid !== '') {
                        $allowedPropIds[] = $pid;
                    }
                }
            }
        }
        $allowedPropIds = array_values(array_unique($allowedPropIds));

        $labelToDb = [
            'PRA' => 'pra',
            'File History' => 'file_history_staging',
            'CofO' => 'CofO_staging',
            'Deed Registration' => 'deed_registrations',
        ];

        if (!empty($transactions) && $propId !== '') {
            $transactions = array_values(array_filter($transactions, function ($row) use ($allowedPropIds, $timelineOrderKeys, $labelToDb) {
                $rowPropId = trim((string) ($row['prop_id'] ?? ''));
                if (in_array($rowPropId, $allowedPropIds, true)) {
                    return true;
                }
                if (!empty($timelineOrderKeys)) {
                    $label = (string) ($row['source_table'] ?? '');
                    $db = $labelToDb[$label] ?? $label;
                    $key = $db . ':' . (string) ($row['id'] ?? '');
                    return isset($timelineOrderKeys[$key]);
                }
                return false;
            }));
        }

        if (empty($transactions) && $propId !== '') {
            $fallback = DB::connection('sqlsrv')
                ->table('file_history_staging')
                ->where('prop_id', $propId)
                ->where(function ($qq) {
                    $qq->where('is_deleted', 0)->orWhereNull('is_deleted');
                })
                ->first();

            if ($fallback) {
                $fileNo = (string) ($fallback->fileno ?? $fallback->mlsFNo ?? '');
                $results = $this->search(['query' => $fileNo]);
                $transactions = $results['transactions'] ?? [];
            }
        }

        $norm = function ($value): string {
            $v = trim(strtolower((string) $value));
            $v = preg_replace('/\s+/', ' ', $v);
            $v = str_replace([',', '.'], '', $v);
            return $v;
        };

        $sourceBaseScore = function (array $row): float {
            $source = trim((string) ($row['source_table'] ?? ''));
            if ($source === 'PRA')
                return 5.0;
            if ($source === 'Deed Registration')
                return 5.0;
            if ($source === 'CofO')
                return 5.0;
            if ($source === 'File History')
                return 2.5;
            return 1.0;
        };

        $canonicalTransactionType = function ($value) use ($norm): string {
            $raw = $norm($value);
            if ($raw === '')
                return '';

            if (str_contains($raw, 'right of occupancy') || str_contains($raw, 'right of occupanc')) {
                return 'right of occupancy';
            }
            if (preg_match('/^r\s*of\s*o$/i', $raw)) {
                return 'right of occupancy';
            }
            $compact = preg_replace('/\s+/', '', $raw);
            if (preg_match('/^r[o0]f[o0]$/', $compact) || preg_match('/^r[o0]f[o0]occupanc(y)?$/', $compact)) {
                return 'right of occupancy';
            }
            if ($raw === 'customary right of occupancy' || $raw === 'statutory right of occupancy') {
                return 'right of occupancy';
            }

            if (str_contains($raw, 'occupancy permit'))
                return 'occupancy permit';
            if (preg_replace('/\s+/', '', $raw) === 'op')
                return 'occupancy permit';

            if (str_contains($raw, 'transfer of title'))
                return 'transfer of title';

            if ($raw === 'tripartite mortgage')
                return 'deed of mortgage';
            if ($raw === 'legal mortgage')
                return 'deed of mortgage';
            if ($raw === 'equitable mortgage')
                return 'deed of mortgage';

            if ($raw === 'deed of surrender' || $raw === 'deed of release' || $raw === 'deed of surrender & release') {
                return 'deed of surrender and release';
            }

            if (str_contains($raw, 'power of attorney'))
                return 'power of attorney';
            if ($compact === 'poa' || $compact === 'ipoa')
                return 'power of attorney';

            return $raw;
        };

        $cleanNumericValue = function ($value): string {
            if ($value === null)
                return '';
            $s = trim((string) $value);
            if ($s === '')
                return '';
            return preg_replace('/\.0$/', '', $s);
        };

        $hasText = function ($value): bool {
            $s = trim(strtolower((string) ($value ?? '')));
            return $s !== '' && $s !== '-' && $s !== '--' && $s !== 'n/a';
        };

        $hasRegValue = function ($value) use ($cleanNumericValue, $hasText): bool {
            $s = $cleanNumericValue($value);
            return $hasText($s) && $s !== '0';
        };

        $richnessScore = function (array $row) use ($hasText, $hasRegValue): float {
            $source = trim((string) ($row['source_table'] ?? ''));
            if ($source !== 'File History' && $source !== 'PRA') {
                return 0.0;
            }

            $score = 0.0;
            $party1 = $row['party_1'] ?? '';
            $party2 = $row['party_2'] ?? '';
            $party3 = $row['party_3'] ?? '';
            $date = $row['transaction_date'] ?? ($row['deeds_date'] ?? ($row['reg_date'] ?? ''));
            $regDate = $row['reg_date'] ?? ($row['deeds_date'] ?? '');
            $regTime = $row['reg_time'] ?? ($row['deeds_time'] ?? ($row['transaction_time'] ?? ''));
            $serialNo = $row['serial_no'] ?? ($row['serialNo'] ?? '');
            $pageNoVal = $row['page_no'] ?? ($row['pageNo'] ?? '');
            $volumeNo = $row['volume_no'] ?? ($row['volumeNo'] ?? '');

            if ($hasText($party1) || $hasText($party2) || $hasText($party3))
                $score += 2;
            if ($hasRegValue($serialNo) || $hasRegValue($pageNoVal) || $hasRegValue($volumeNo))
                $score += 2;
            if ($hasText($date))
                $score += 2;
            if ($hasText($regTime))
                $score += 2;
            if ($hasText($regDate))
                $score += 2;

            return $score;
        };

        $totalScore = function (array $row) use ($sourceBaseScore): float {
            return $sourceBaseScore($row);
        };

        $recordKey = function (array $row) use ($norm, $canonicalTransactionType, $cleanNumericValue): ?string {
            $source = trim((string) ($row['source_table'] ?? ''));
            if (!in_array($source, ['File History', 'PRA'], true)) {
                return null;
            }

            $transactionType = $canonicalTransactionType($row['transaction_type'] ?? ($row['instrument_type'] ?? ''));
            if ($transactionType === '') {
                return null;
            }

            $serialNo = $cleanNumericValue($row['serial_no'] ?? null) ?: '0';
            $pageNoVal = $cleanNumericValue($row['page_no'] ?? null) ?: '0';
            $volumeNo = $cleanNumericValue($row['volume_no'] ?? null) ?: '0';
            $hasRealReg = ($serialNo !== '0' && $serialNo !== '') ||
                ($pageNoVal !== '0' && $pageNoVal !== '') ||
                ($volumeNo !== '0' && $volumeNo !== '');

            if ($hasRealReg) {
                return 'reg|' . $transactionType . '|' . $serialNo . '/' . $pageNoVal . '/' . $volumeNo;
            }

            $party1 = $norm($row['party_1'] ?? '');
            $party2 = $norm($row['party_2'] ?? '');
            $party3 = $norm($row['party_3'] ?? '');
            $party4 = $norm($row['party_4'] ?? '');
            $date = $norm($row['transaction_date'] ?? ($row['deeds_date'] ?? ($row['reg_date'] ?? '')));

            $keyDate = $transactionType === 'right of occupancy' ? '' : $date;

            $hasSignal = $transactionType !== '' || $party1 !== '' || $party2 !== '' || $keyDate !== '';
            if (!$hasSignal) {
                return null;
            }

            return implode('|', [$transactionType, $party1, $party2, $party3, $party4, $keyDate]);
        };

        if (!empty($transactions)) {
            $deduped = [];
            $keyToIndex = [];

            foreach ($transactions as $row) {
                $key = $recordKey($row);
                if ($key === null) {
                    $deduped[] = $row;
                    continue;
                }

                if (!array_key_exists($key, $keyToIndex)) {
                    $keyToIndex[$key] = count($deduped);
                    $deduped[] = $row;
                    continue;
                }

                $idx = $keyToIndex[$key];
                $rowRichness = $richnessScore($row);
                $existingRichness = $richnessScore($deduped[$idx]);

                if ($rowRichness > $existingRichness) {
                    $deduped[$idx] = $row;
                    continue;
                }

                if ($rowRichness === $existingRichness && $totalScore($row) > $totalScore($deduped[$idx])) {
                    $deduped[$idx] = $row;
                }
            }

            $transactions = $deduped;
        }

        $parseTimelineTimeValue = function ($value): array {
            $result = ['h' => 0, 'm' => 0, 's' => 0];
            if ($value === null || $value === '' || $value === '-')
                return $result;

            $text = trim((string) $value);
            if ($text === '')
                return $result;

            if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([AP]M)?$/i', $text, $m)) {
                $hour = (int) $m[1];
                $minute = (int) $m[2];
                $second = isset($m[3]) ? (int) $m[3] : 0;
                $ampm = strtoupper((string) ($m[4] ?? ''));

                if ($ampm === 'PM' && $hour < 12)
                    $hour += 12;
                if ($ampm === 'AM' && $hour === 12)
                    $hour = 0;

                return ['h' => $hour, 'm' => $minute, 's' => $second];
            }

            try {
                $parsed = \Carbon\Carbon::parse($text);
                return ['h' => (int) $parsed->format('H'), 'm' => (int) $parsed->format('i'), 's' => (int) $parsed->format('s')];
            } catch (\Throwable $e) {
                return $result;
            }
        };

        $parseTimelineDateValue = function ($value, $timeValue = null) use ($parseTimelineTimeValue): ?int {
            if ($value === null || $value === '' || $value === '-')
                return null;

            $text = trim((string) $value);
            if ($text === '')
                return null;

            $time = $parseTimelineTimeValue($timeValue);

            if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $text, $m)) {
                $day = (int) $m[1];
                $month = (int) $m[2];
                $year = (int) $m[3];
                $dt = \Carbon\Carbon::create($year, $month, $day, $time['h'], $time['m'], $time['s']);
                return $dt ? $dt->timestamp : null;
            }

            try {
                $dt = \Carbon\Carbon::parse($text);
                if ($timeValue) {
                    $dt->setTime($time['h'], $time['m'], $time['s']);
                }
                return $dt->timestamp;
            } catch (\Throwable $e) {
                return null;
            }
        };

        $getTransactionTimestamp = function (array $item) use ($parseTimelineDateValue): ?int {
            $candidates = [
                ['date' => $item['reg_date'] ?? null, 'time' => $item['reg_time'] ?? null],
                ['date' => $item['deeds_date'] ?? null, 'time' => $item['deeds_time'] ?? null],
                ['date' => $item['transaction_date'] ?? null, 'time' => $item['transaction_time'] ?? ($item['time'] ?? null)],
                ['date' => $item['cofo_date'] ?? null, 'time' => $item['time'] ?? null],
                ['date' => $item['certificateDate'] ?? null, 'time' => $item['time'] ?? null],
                ['date' => $item['approval_date'] ?? null, 'time' => $item['time'] ?? null],
                ['date' => $item['date'] ?? null, 'time' => $item['time'] ?? null],
            ];

            foreach ($candidates as $candidate) {
                $ts = $parseTimelineDateValue($candidate['date'], $candidate['time']);
                if ($ts !== null)
                    return $ts;
            }
            return null;
        };

        $getTransPriorityWeight = function (array $row) use ($norm, $canonicalTransactionType, $getTransactionTimestamp): int {
            $currentYear = (int) date('Y');
            
            // Check if file number has current year
            $fileNo = trim((string) ($row['file_number'] ?? ($row['fileno'] ?? ($row['mlsFNo'] ?? ''))));
            if (preg_match('/\b(?:19|20)\d{2}\b/', $fileNo, $m)) {
                if ((int) $m[0] === $currentYear) {
                    return 0;
                }
            }
            
            // Check if transaction date has current year
            $ts = $getTransactionTimestamp($row);
            if ($ts !== null) {
                if ((int) date('Y', $ts) === $currentYear) {
                    return 0;
                }
            }

            $txType = $canonicalTransactionType($row['transaction_type'] ?? ($row['instrument_type'] ?? ''));
            if ($txType === 'occupancy permit')
                return 10;
            if ($txType === 'transfer of title')
                return 9;
            if ($txType === 'right of occupancy')
                return 8;
            return 5;
        };

        usort($transactions, function ($a, $b) use ($getTransactionTimestamp, $getTransPriorityWeight) {
            $wa = $getTransPriorityWeight($a);
            $wb = $getTransPriorityWeight($b);

            if ($wa !== $wb)
                return $wb - $wa;

            $ta = $getTransactionTimestamp($a);
            $tb = $getTransactionTimestamp($b);

            if ($ta === null && $tb === null) {
                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            }
            if ($ta === null)
                return 1;
            if ($tb === null)
                return -1;
            if ($ta !== $tb)
                return $ta <=> $tb;

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });
        $transactions = array_values($transactions);

        if (empty($transactions)) {
            return ['status' => 404, 'payload' => ['success' => false, 'message' => 'No records found']];
        }

        if ($timelineOrderRaw !== '') {
            $orderTokens = array_values(array_filter(array_map('trim', explode(',', $timelineOrderRaw))));
            $orderIndex = [];
            foreach ($orderTokens as $i => $tok) {
                $orderIndex[$tok] = $i;
            }
            $rank = function (array $row) use ($orderIndex, $labelToDb) {
                $label = (string) ($row['source_table'] ?? '');
                $db = $labelToDb[$label] ?? $label;
                $key = $db . ':' . (string) ($row['id'] ?? '');
                return $orderIndex[$key] ?? PHP_INT_MAX;
            };
            usort($transactions, function ($a, $b) use ($rank) {
                $ra = $rank($a);
                $rb = $rank($b);
                if ($ra === $rb) {
                    return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
                }
                return $ra <=> $rb;
            });
            $transactions = array_values($transactions);
        }

        $first = $transactions[0];

        $tc = fn($v) => $v && $v !== '-' ? mb_convert_case(mb_strtolower($v), MB_CASE_TITLE, 'UTF-8') : '-';

        $isKangis = function ($value) {
            $v = trim((string) $value);
            return $v !== '' && preg_match('/^[A-Z]{4}\s?\d{3,6}$/i', $v);
        };

        $parseRelatedFileno = function ($raw) {
            $text = trim((string) $raw);
            if ($text === '')
                return null;

            $text = trim($text, "[]");
            $parts = preg_split('/[,;|]/', $text) ?: [];
            $parts = array_values(array_filter(array_map(function ($p) {
                $p = trim($p);
                $p = trim($p, "'\" ");
                return $p;
            }, $parts)));

            if (empty($parts))
                return null;

            foreach ($parts as $p) {
                if (
                    preg_match('/^(CON-(COM|RES|AG|IND)|COM|RES|IND|AG)-\d{2,4}-\d+$/i', $p)
                    || preg_match('/^(CON-(COM|RES|AG|IND)|COM|RES|IND|AG)-\d+$/i', $p)
                ) {
                    return $p;
                }
            }

            return $parts[0];
        };

        $fileNumber = $searchedFileNo ?: ($first['fileno'] ?: ($first['file_number'] ?: ($first['mlsFNo'] ?: '-')));
        $kangisNumber = $first['kangisFileNo'] ?? null;
        if ($kangisNumber === '-')
            $kangisNumber = null;
        $relatedMls = null;

        $fileTitle = '-';
        $fiPlotNo = null;
        $fiTpNo = null;
        $fiLandUse = null;
        if ($fileNumber && $fileNumber !== '-') {
            $fileNumberCandidates = array_values(array_unique(array_filter([$fileNumber, $fileNo])));
            $fiList = DB::connection('sqlsrv')->table('file_indexings')
                ->whereNull('deleted_at')
                ->where(function ($qq) use ($fileNumberCandidates) {
                    foreach ($fileNumberCandidates as $candidate) {
                        $qq->orWhere('file_number', $candidate)
                            ->orWhere('related_fileno', 'like', '%' . $candidate . '%');
                    }
                })
                ->select('file_title', 'plot_number', 'tp_no', 'land_use_type', 'related_fileno', 'file_number')
                ->get();
                
            $fi = null;
            // Priority 1: Exact match on file_number
            foreach ($fiList as $row) {
                foreach ($fileNumberCandidates as $candidate) {
                    if (strcasecmp((string)$row->file_number, (string)$candidate) === 0) {
                        $fi = $row;
                        break 2;
                    }
                }
            }
            // Priority 2: Fallback to first match
            if (!$fi && $fiList->isNotEmpty()) {
                $fi = $fiList->first();
            }
            if ($fi) {
                if ($fi->file_title)
                    $fileTitle = $tc($fi->file_title);
                $fiPlotNo = $fi->plot_number ?: null;
                $fiTpNo = $fi->tp_no ?: null;
                $fiLandUse = $fi->land_use_type ?: null;

                $relatedMls = $parseRelatedFileno($fi->related_fileno ?? null);
            }
        }

        $fileNumberDisplay = $fileNumber;
        if ($isKangis($searchedFileNo)) {
            if ($relatedMls && strcasecmp(trim($relatedMls), trim($searchedFileNo)) !== 0) {
                $fileNumberDisplay .= ' (' . $relatedMls . ')';
            }
        } elseif ($searchedFileNo === '') {
            if ($kangisNumber) {
                $fileNumberDisplay = $kangisNumber;
                if ($relatedMls && strcasecmp(trim($relatedMls), trim($kangisNumber)) !== 0) {
                    $fileNumberDisplay .= ' (' . $relatedMls . ')';
                }
            } elseif ($relatedMls && $isKangis($fileNo)) {
                $fileNumberDisplay = $fileNo . ' (' . $relatedMls . ')';
            }
        }
        if ($fileTitle === '-') {
            $fileTitle = $tc($first['party_2'] ?: ($first['party_1'] ?: '-'));
        }
        $district = ($first['districtName'] ?? null) ?: null;
        $lga = ($first['lgsaOrCity'] ?? null) ?: null;
        if ($district === '-')
            $district = null;
        if ($lga === '-')
            $lga = null;
        $districtLga = $tc(implode(', ', array_filter([$district, $lga])) ?: ($first['location'] ?: '-'));
        $plotNo = $fiPlotNo ?: ($first['plot_no'] ?: '-');

        $size = '-';
        $bestSizeScore = -1;
        $sizeSourceScores = [
            'CofO_staging' => 4,
            'file_history_staging' => 3,
            'pra' => 2,
            'deed_registrations' => 1,
        ];
        foreach ($transactions as $t) {
            $s = $t['size'] ?? null;
            if ($s && $s !== '-' && trim($s) !== '') {
                $src = $t['source_table'] ?? '';
                $score = $sizeSourceScores[$src] ?? 0;
                if ($score > $bestSizeScore) {
                    $bestSizeScore = $score;
                    $size = trim($s);
                }
            }
        }
        if (($size === '-' || $size === '') && $propId !== '') {
            $praSize = DB::connection('sqlsrv')->table('pra')
                ->where('prop_id', $propId)
                ->whereNotNull('plot_size')
                ->where('plot_size', '!=', '')
                ->orderByDesc('id')
                ->value('plot_size');
            if ($praSize) {
                $size = $praSize;
            }
        }

        $landUse = $tc($fiLandUse ?: ($first['land_use'] ?: '-'));

        $bestLocation = '-';
        foreach ($transactions as $t) {
            $loc = $t['location'] ?? '';
            if ($loc && $loc !== '-' && mb_strlen($loc) > mb_strlen($bestLocation === '-' ? '' : $bestLocation)) {
                $bestLocation = $loc;
            }
        }
        $plotDescription = $tc($bestLocation);

        $tpno = $fiTpNo ?: ($first['tp_no'] ?: '-');

        $rows = [];
        foreach ($transactions as $idx => $t) {
            $regDate = $t['deeds_date'] ?? null;
            if (!$regDate)
                $regDate = $t['reg_date'] ?? null;
            if ($regDate) {
                try {
                    $regDate = \Carbon\Carbon::parse($regDate)->format('M j, Y');
                } catch (\Exception $e) { /* keep as-is */
                }
            }
            if (!$regDate)
                $regDate = $t['transaction_date'] ?: '-';

            $regTime = $t['deeds_time'] ?? null;
            if (!$regTime)
                $regTime = $t['reg_time'] ?? null;
            if ($regTime && $regTime !== '-') {
                try {
                    $regTime = \Carbon\Carbon::parse($regTime)->format('g:i A');
                } catch (\Exception $e) { /* keep as-is */
                }
            }
            $regTime = $regTime ?: '-';

            $serialNo = $t['serial_no'] ?? null;
            $pageNoVal = $t['page_no'] ?? null;
            $volumeNo = $t['volume_no'] ?? null;
            $cleanReg = fn($v) => ($v && $v !== '-') ? preg_replace('/\.0$/', '', trim($v)) : '0';
            $hasAnyReg = ($serialNo && $serialNo !== '-') || ($pageNoVal && $pageNoVal !== '-') || ($volumeNo && $volumeNo !== '-');
            $regNoDisplay = $hasAnyReg
                ? $cleanReg($serialNo) . '/' . $cleanReg($pageNoVal) . '/' . $cleanReg($volumeNo)
                : '0/0/0';

            $rowFileNo = (string) ($t['fileno'] ?: ($t['file_number'] ?: ($t['mlsFNo'] ?: '-')));
            $rows[] = [
                'sn' => $idx + 1,
                'file_no' => $rowFileNo,
                'grantor' => $tc($t['party_1'] ?: '-'),
                'grantee' => $tc($t['party_2'] ?: '-'),
                'party_3' => $tc($t['party_3'] ?: '-'),
                'party_4' => $tc($t['party_4'] ?: '-'),
                'instrument_type' => $tc($t['transaction_type'] ?: '-'),
                'reg_time' => $regTime,
                'reg_date' => $regDate,
                'reg_no' => $regNoDisplay,
                'size' => $t['size'] ?: '-',
                'caveat' => $t['caveat'] ?: '-',
                'comments' => $t['is_caveated'] ? $tc($t['caveated_comment'] ?: ($t['comments'] ?: '-')) : $tc($t['comments'] ?: '-'),
                // Extra metadata (ignored by the print slip) so consumers that render
                // the same LS-weighed timeline on-screen — e.g. the PHS portal —
                // can show source badges and location identically to the slip.
                'source_table' => $labelToDb[$t['source_table'] ?? ''] ?? ($t['source_table'] ?? ''),
                'location' => $t['location'] ?? '',
            ];
        }

        $caveatedRecord = collect($transactions)->first(fn($t) => $t['is_caveated']);
        $caveatId = $caveatedRecord ? ($caveatedRecord['caveat_id'] ?? null) : null;

        $caveatNumber = null;
        if ($caveatId) {
            $caveatNumber = DB::connection('sqlsrv')->table('caveats')
                ->where('id', $caveatId)
                ->value('caveat_number');
        }
        if (!$caveatNumber) {
            $caveatNumber = DB::connection('sqlsrv')->table('caveats')
                ->where('status', 'active')
                ->where(function ($qq) use ($fileNumber, $fileNo, $propId) {
                    $variants = array_unique(array_filter([$fileNumber, $fileNo]));
                    foreach ($variants as $i => $fn) {
                        $method = $i === 0 ? 'where' : 'orWhere';
                        $qq->{$method}(function ($sub) use ($fn) {
                            $sub->where('file_number_mlsf', $fn)
                                ->orWhere('file_number_kangis', $fn)
                                ->orWhere('file_number_new_kangis', $fn);
                        });
                    }
                    if ($propId) {
                        $qq->orWhere('prop_id', $propId);
                    }
                })
                ->orderByDesc('id')
                ->value('caveat_number');
        }

        $mortgageCaveat = false;
        $parseDate = fn($d) => $d && $d !== '-' ? (rescue(fn() => \Carbon\Carbon::parse($d), null, false)) : null;

        $latestMortgage = collect($transactions)
            ->filter(fn($t) => stripos($t['transaction_type'] ?? '', 'deed of mortgage') !== false
                || stripos($t['transaction_type'] ?? '', 'tripartite mortgage') !== false
                || stripos($t['transaction_type'] ?? '', 'legal mortgage') !== false
                || stripos($t['transaction_type'] ?? '', 'equitable mortgage') !== false)
            ->map(fn($t) => $parseDate($t['reg_date'] ?? null))
            ->filter()
            ->max();

        if ($latestMortgage) {
            $latestRelease = collect($transactions)
                ->filter(fn($t) => stripos($t['transaction_type'] ?? '', 'deed of surrender and release') !== false)
                ->map(fn($t) => $parseDate($t['reg_date'] ?? null))
                ->filter()
                ->max();

            if (!$latestRelease || $latestRelease->lt($latestMortgage)) {
                $mortgageCaveat = true;
            }
        }

        $isCaveated = (bool) $caveatedRecord || $mortgageCaveat;

        $hasCofo = collect($transactions)->contains(function ($t) {
            $type = strtolower($t['transaction_type'] ?? '');
            return str_contains($type, 'certificate of occupancy')
                || str_contains($type, 'c of o')
                || str_contains($type, 'cofo')
                || str_contains($type, 'c.of.o')
                || str_contains($type, 'c/o/o');
        });

        $now = now();
        $user = auth()->user();
        $generatedBy = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? $user->name ?? '')) : 'system';
        if (trim($generatedBy) === '') {
            $generatedBy = 'system';
        }

        $remarksTimestamp = 'These details are as at ' . $now->format('l, F j, Y g:i A');
        $investigation = $this->resolveDcivInvestigation([$fileNumber, $fileNo], $transactions);

        if ($caveatedRecord && $mortgageCaveat) {
            $caveatNote = 'This Property is Under an Active Mortgage and Caveat' . ($caveatNumber ? " (See, {$caveatNumber})" : '') . '!!!';
        } elseif ($caveatedRecord) {
            $caveatNote = 'N.B. This Property is Under an Active Caveat' . ($caveatNumber ? " (See, {$caveatNumber})" : '') . '!!!';
        } elseif ($mortgageCaveat) {
            $caveatNote = 'This Property is Under an Active Mortgage !!!';
        } elseif (!$hasCofo) {
            $caveatNote = 'Based on our available records, the subject title is currently at the Letter of Grant stage, hence Certificate of Occupancy is yet to be issued. However the title is free from encumbrances.';
        } else {
            $caveatNote = 'Based on our available records, the title is free from encumbrances.';
        }

        if ($investigation !== null) {
            // If the property has no additional caveat/mortgage, only show the investigation notice.
            // Do NOT show "free from encumbrances" alongside an active investigation.
            if ($caveatedRecord || $mortgageCaveat) {
                $caveatNote = 'This Property is under Investigation. ' . $caveatNote;
            } else {
                $caveatNote = 'This Property is under Investigation.';
            }
        }

        $comments = DB::connection('sqlsrv')->table('ls_comment_staging')
            ->where(function ($qq) use ($fileNumber, $fileNo, $propId) {
                $qq->where('file_number', $fileNumber);
                if ($fileNo && $fileNo !== $fileNumber) {
                    $qq->orWhere('file_number', $fileNo);
                }
                if ($propId) {
                    $qq->orWhere('prop_id', $propId);
                }
            })
            ->get()
            ->keyBy('comment_type');

        $groundRentText = null;
        $groundRent = $comments->get('ground_rent');
        if ($groundRent) {
            if ($groundRent->amount) {
                $groundRentText = 'Ground Rent Including Land Use Charge Not Paid (Amounting to ₦' . number_format($groundRent->amount, 2) . ')';
                if ($groundRent->comment) {
                    $groundRentText .= ' — ' . $groundRent->comment;
                }
            } elseif ($groundRent->comment) {
                $groundRentText = $groundRent->comment;
            }
        }

        $noCofoComment = null;
        $noCofo = $comments->get('no_cofo');
        if ($noCofo && $noCofo->comment) {
            $noCofoComment = $noCofo->comment;
        }

        $encumbranceComment = null;
        $encumbrance = $comments->get('encumbrance');
        if ($encumbrance && $encumbrance->comment) {
            $encumbranceComment = $encumbrance->comment;
        }

        $litigationComment = null;
        $litigation = $comments->get('litigation');
        if ($litigation && $litigation->comment) {
            $litigationComment = $litigation->comment;
        }

        // DCIV investigation flag: when the searched file — or any file number
        // resolved onto the timeline rows — is tied to a DCIV in
        // master_dciv_links (on EITHER side), the property is under investigation.
        // NOTE: The DCIV "Under Investigation" note is injected into each matching
        // row's Comments cell inside search() (called above), so it already flows
        // through into the report rows — no separate handling is needed here.

        $generatedByText = 'Generated by ' . $generatedBy . ' at ' . $now->format('g:i A & d/m/Y');

        $qrData = substr(hash_hmac('sha256', $fileNumber . $now->timestamp, config('app.key')), 0, 11);

        if ($displayFileNumber !== '')
            $fileNumberDisplay = $displayFileNumber;
        if ($displayFileTitle !== '')
            $fileTitle = $tc($displayFileTitle);
        if ($displayDistrictLga !== '')
            $districtLga = $tc($displayDistrictLga);
        if ($displayLandUse !== '')
            $landUse = $tc($displayLandUse);
        if ($displaySize !== '')
            $size = $displaySize;
        if ($displayPlotNo !== '')
            $plotNo = $displayPlotNo;
        if ($displayTpno !== '')
            $tpno = $displayTpno;

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'data' => [
                    'date_line' => 'Date: ' . $now->format('F j, Y'),
                    'file_number' => $fileNumberDisplay,
                    'file_title' => $fileTitle,
                    'district_lga' => $districtLga,
                    'land_use' => $landUse,
                    'plot_no' => $plotNo,
                    'size' => $size,
                    'plot_description' => $plotDescription,
                    'tpno' => $tpno,
                    'rows' => $rows,
                    'remarks' => $remarksTimestamp,
                    'caveat_note' => $caveatNote,
                    'is_caveated' => $isCaveated,
                    'under_investigation' => $investigation !== null,
                    'has_cofo' => $hasCofo,
                    'ground_rent' => $groundRentText,
                    'no_cofo_comment' => $noCofoComment,
                    'encumbrance_comment' => $encumbranceComment,
                    'litigation_comment' => $litigationComment,
                    'generated_by' => $generatedByText,
                    'generated_date' => $now->format('F j, Y'),
                    'full_name' => $generatedBy,
                    'qr_data' => $qrData,
                ],
            ],
        ];
    }

    /**
     * Determine whether a file is under DCIV investigation, and why.
     *
     * A property is "Under Investigation" when the searched file — or any of the
     * file-number variants resolved onto its timeline rows — appears in
     * master_dciv_links on EITHER side: as the DCIV parent (dciv_file_number)
     * or as a linked child (related_file_number). The note is accompanied by the
     * DCIV's reason (dciv_reason) and parent file number when available.
     *
     * @param array $fileNos      Primary candidate file numbers (searched + resolved)
     * @param array $transactions Result rows whose file-number variants are also candidates
     * @return array{note:string, reason:?string, dciv_file_number:?string}|null
     */
    private function resolveDcivInvestigation(array $fileNos, array $transactions = []): ?array
    {
        $seen = [];
        $candidates = [];
        $add = function ($value) use (&$seen, &$candidates) {
            $v = trim((string) $value);
            if ($v === '' || $v === '-') {
                return;
            }
            $u = strtoupper($v);
            if (isset($seen[$u])) {
                return;
            }
            $seen[$u] = true;
            $candidates[] = $u;
        };

        foreach ($fileNos as $fn) {
            $add($fn);
        }
        foreach ($transactions as $row) {
            foreach (['fileno', 'file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno'] as $col) {
                $add($row[$col] ?? null);
            }
        }

        if (empty($candidates)) {
            return null;
        }

        if (!Schema::connection('sqlsrv')->hasTable('master_dciv_links')) {
            return null;
        }

        $matches = DB::connection('sqlsrv')->table('master_dciv_links')
            ->where(function ($q) use ($candidates) {
                $q->whereIn(DB::raw('UPPER(LTRIM(RTRIM(dciv_file_number)))'), $candidates)
                    ->orWhereIn(DB::raw('UPPER(LTRIM(RTRIM(related_file_number)))'), $candidates);
            })
            ->orderByDesc('id')
            ->get(['dciv_file_number', 'related_file_number', 'dciv_reason']);

        if ($matches->isEmpty()) {
            return null;
        }

        // Prefer a match that actually carries a reason.
        $best = $matches->first(fn($m) => trim((string) ($m->dciv_reason ?? '')) !== '') ?? $matches->first();

        // Capture the searched-side file numbers that are actually under a DCIV,
        // so callers can flag only the matching timeline rows (not the whole group).
        $candidateSet = array_flip($candidates);
        $fileNumbers = [];
        foreach ($matches as $m) {
            foreach (['dciv_file_number', 'related_file_number'] as $col) {
                $u = strtoupper(trim((string) ($m->$col ?? '')));
                if ($u !== '' && isset($candidateSet[$u])) {
                    $fileNumbers[$u] = true;
                }
            }
        }

        return [
            'note' => 'Under Investigation',
            'reason' => trim((string) ($best->dciv_reason ?? '')) ?: null,
            'dciv_file_number' => trim((string) ($best->dciv_file_number ?? '')) ?: null,
            'file_numbers' => array_keys($fileNumbers),
        ];
    }

    /**
     * Build the "Under Investigation" comment text (note + reason).
     * This is used for the transaction rows' Comments column.
     */
    private function investigationCommentText(array $investigation): string
    {
        $text = $investigation['note'] ?? 'Under Investigation';
        $reason = trim((string) ($investigation['reason'] ?? ''));
        if ($reason !== '') {
            $text .= ' — ' . $reason;
        }
        return $text;
    }

    /**
     * Whether a result/report row's file number is one of the investigated files.
     *
     * @param array $row         Row carrying file-number variants
     * @param array $fileNumbers UPPERCASE investigated file numbers
     * @param array $columns     Which row keys hold file numbers
     */
    private function rowIsUnderInvestigation(array $row, array $fileNumbers, array $columns): bool
    {
        if (empty($fileNumbers)) {
            return false;
        }
        $set = array_flip($fileNumbers);
        foreach ($columns as $col) {
            $u = strtoupper(trim((string) ($row[$col] ?? '')));
            if ($u !== '' && isset($set[$u])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retrieve explicitly related file numbers for Subdivision, Merger, and Extension (SME)
     * using the related_fileno identifier array from file_indexings.
     * Bypasses ST (Sectional Titling) files completely.
     */
    public function getSmeAllowedFileNos(string $fileNo, $conn): array
    {
        $fileNo = trim($fileNo);
        if ($fileNo === '') {
            return [];
        }

        // ST is a separate module; ignore ST prefix
        if (str_starts_with(strtoupper($fileNo), 'ST-')) {
            return [];
        }

        $normalizeForQuery = function (string $value): string {
            $value = strtoupper(trim($value));
            $value = preg_replace('/[\/=_]+/', '-', $value);
            $value = preg_replace('/\s+/', '', $value);
            return $value;
        };

        $normalizedFileNo = $normalizeForQuery($fileNo);
        if ($normalizedFileNo === '') {
            return [];
        }

        $allowed = [$fileNo];
        $isSme = false;

        $parseRelatedFilenos = function (?string $raw) {
            $raw = trim((string) $raw);
            if ($raw === '') {
                return [];
            }

            $trimmed = trim($raw, "[]\n\r\t\0\x0B ");
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded) && !empty($decoded)) {
                return array_values(array_filter(array_map(function ($item) {
                    return trim((string) $item);
                }, $decoded)));
            }

            $parts = preg_split('/[,;|]+/', $trimmed);
            $parts = array_values(array_filter(array_map(function ($part) {
                return trim(trim((string) $part), "'\" ");
            }, $parts)));

            if (!empty($parts)) {
                return $parts;
            }

            return [trim($trimmed, "'\" ")];
        };

        // 1. Check active indexing
        $active = $conn->table('file_indexings')
            ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(ISNULL(file_number, ''))), '/', '-'), '=', '-'), '_', '-')) = ?", [$normalizedFileNo])
            ->whereNull('deleted_at')
            ->first(['related_fileno']);

        if ($active && !empty($active->related_fileno)) {
            $decoded = $parseRelatedFilenos($active->related_fileno);
            if (!empty($decoded)) {
                $isSme = true;
                foreach ($decoded as $fn) {
                    if ($fn !== '') {
                        $allowed[] = trim($fn);
                    }
                }
            }
        }

        // 2. If the file is a child or decommissioned parent of an SME group,
        //    find the active parent record and include its related file numbers.
        if (!$isSme) {
            $activeParent = $conn->table('file_indexings')
                ->whereNull('deleted_at')
                ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(ISNULL(related_fileno, ''))), '/', '-'), '=', '-'), '_', '-')) LIKE ?", ['%' . $normalizedFileNo . '%'])
                ->first(['file_number', 'related_fileno']);
            
            if ($activeParent && !str_starts_with(strtoupper($activeParent->file_number), 'ST-')) {
                $decoded = $parseRelatedFilenos($activeParent->related_fileno);
                if (!empty($decoded)) {
                    $isSme = true;
                    $allowed[] = trim($activeParent->file_number);
                    foreach ($decoded as $fn) {
                        if ($fn !== '') {
                            $allowed[] = trim($fn);
                        }
                    }
                }
            }
        }

        if ($isSme) {
            return array_values(array_unique($allowed));
        }

        return [];
    }
}
