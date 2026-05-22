<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LegalSearchService;
use Illuminate\Support\Facades\DB;

class LegalSearchController extends Controller
{
    protected LegalSearchService $searchService;

    protected string $pageTitle = 'Legal Search - Official (for filing purpose)';
    protected string $viewPrefix = 'legal_search';
    protected string $searchRouteName = 'legalsearch.search';
    protected string $watermarkText = 'FOR OFFICE USE ONLY';
    protected string $printTemplateRouteName = 'legal_search.print.official';
    protected string $printManagerDocType = '';

    public function __construct(LegalSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    protected function moduleConfig(): array
    {
        return [
            'pageTitle' => $this->pageTitle,
            'viewPrefix' => $this->viewPrefix,
            'searchRouteName' => $this->searchRouteName,
            'watermarkText' => $this->watermarkText,
            'printTemplateRouteName' => $this->printTemplateRouteName,
            'printManagerDocType' => $this->printManagerDocType,
        ];
    }

    public function index()
    {
        $PageTitle = $this->pageTitle;
        $PageDescription = '';
        $moduleConfig = $this->moduleConfig();
        $landUseOptions = DB::connection('sqlsrv')->table('land_uses')->pluck('landuse')->toArray();
        $districtOptions = DB::connection('sqlsrv')->table('districts')->pluck('name')->toArray();
        return view($this->viewPrefix . '.index', compact('PageTitle', 'PageDescription', 'moduleConfig', 'landUseOptions', 'districtOptions'));
    }

    public function search(Request $request)
    {
        $results = $this->searchService->search($request->all());

        $searchParam = null;
        $searchValue = null;

        $mappings = [
            'query' => 'File Number',
            'guarantorName' => 'Party 1',
            'guaranteeName' => 'Party 2',
            'lga' => 'LGA',
            'district' => 'District',
            'location' => 'Location',
            'plotNumber' => 'Plot Number',
            'planNumber' => 'Plan Number',
            'size' => 'Size',
            'caveat' => 'Caveat',
        ];

        foreach ($mappings as $key => $label) {
            if ($request->filled($key)) {
                $searchParam = $label;
                $searchValue = $request->input($key);
                break;
            }
        }

        if ($searchParam) {
            $totalCount = $results['total_count'] ?? 0;
            $resultStatus = $totalCount > 0 ? 'Found' : 'Not Found';

            // Collect query params for direct link
            $queryParams = $request->only(array_keys($mappings));
            // Filter out empty params
            $queryParams = array_filter($queryParams, function ($val) {
                return !empty($val);
            });
            $directLink = route('legal_search.index', $queryParams);

            \App\Models\LegalSearchLog::create([
                'user_id' => auth()->id(),
                'search_parameter' => $searchParam,
                'search_value' => $searchValue,
                'result_status' => $resultStatus,
                'results_count' => $totalCount,
                'lga' => $request->input('lga'),
                'receipt_no' => $request->input('token'), // Maybe token is sent? 
                'printed' => false,
                'direct_link' => $directLink,
            ]);
        }

        return response()->json($results);
    }

    public function report()
    {
        return view($this->viewPrefix . '.report');
    }

    public function legal_search_report()
    {
        $PageTitle = 'Legal Search Report';
        $moduleConfig = $this->moduleConfig();
        return view($this->viewPrefix . '.report', compact('PageTitle', 'moduleConfig'));
    }

    public function online()
    {
        return response()->file(base_path('docs/templates/online.html'), [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function onlineSearch(Request $request)
    {
        $results = $this->searchService->search($request->all());
        return response()->json($results);
    }

    public function printTemplateOfficial()
    {
        return response()->file(resource_path('views/legal_search/templates/OFFICIAL SEARCH REPORT.html'), [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function printTemplateOnpremise()
    {
        return response()->file(resource_path('views/legal_search/templates/PAY-PER-SEARCH.html'), [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function printTemplateOnline()
    {
        return response()->file(resource_path('views/legal_search/templates/ONLINE.html'), [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function reportTemplateData(Request $request)
    {
        $fileNo = trim((string) $request->query('file_number', ''));
        $propId = trim((string) $request->query('prop_id', ''));
        $displayFileNumber = trim((string) $request->query('display_file_number', ''));
        $displayFileTitle = trim((string) $request->query('display_file_title', ''));
        $displayDistrictLga = trim((string) $request->query('display_district_lga', ''));
        $displayLandUse = trim((string) $request->query('display_land_use', ''));
        $displaySize = trim((string) $request->query('display_size', ''));
        $displayPlotNo = trim((string) $request->query('display_plot_no', ''));
        $displayTpno = trim((string) $request->query('display_tpno', ''));

        if ($fileNo === '' && $propId === '') {
            return response()->json(['success' => false, 'message' => 'file_number or prop_id is required'], 422);
        }

        $results = $this->searchService->search(['query' => $fileNo]);
        $transactions = $results['transactions'] ?? [];

        // Build the full set of allowed prop_ids for the filter.
        // For merger files, the PRA merger row stores parent_prop_id with the source file prop_ids
        // (e.g. RES-2026-2123's PRA row has parent_prop_id="80000455,77505").
        // We must include those so all source records pass the filter and appear on the print.
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

        // When prop_id is provided, filter to only records belonging to the allowed prop_id group.
        // This ensures dropped records (prop_id = NULL) are excluded from the print template.
        if (!empty($transactions) && $propId !== '') {
            $transactions = array_values(array_filter($transactions, function ($row) use ($allowedPropIds) {
                $rowPropId = trim((string) ($row['prop_id'] ?? ''));
                return in_array($rowPropId, $allowedPropIds, true);
            }));
        }

        if (empty($transactions) && $propId !== '') {
            $fallback = DB::connection('sqlsrv')
                ->table('file_history_staging')
                ->where('prop_id', $propId)
                ->where(function ($q) {
                    $q->where('is_deleted', 0)->orWhereNull('is_deleted');
                })
                ->first();

            if ($fallback) {
                $fileNo = (string) ($fallback->fileno ?? $fallback->mlsFNo ?? '');
                $results = $this->searchService->search(['query' => $fileNo]);
                $transactions = $results['transactions'] ?? [];
            }
        }

        // Match Timeline behavior for report rows:
        // - dedupe PRA/FH duplicates using source weighting
        // - normalize equivalent instrument labels (e.g. R Of O vs Right Of Occupancy)
        // - when scores tie, keep richer row
        // - sort final rows chronologically by reg/deed/transaction date-time
        $norm = function ($value): string {
            $v = trim(strtolower((string) $value));
            $v = preg_replace('/\s+/', ' ', $v);
            $v = str_replace([',', '.'], '', $v);
            return $v;
        };

        // Rule A: source-based dedup weights — which row wins when two sources have the same record
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

            // ROFO variants
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

            // Occupancy Permit variants
            if (str_contains($raw, 'occupancy permit'))
                return 'occupancy permit';
            if (preg_replace('/\s+/', '', $raw) === 'op')
                return 'occupancy permit';

            // Transfer of Title variants (incl. "Transfer Of Title (Op)")
            if (str_contains($raw, 'transfer of title'))
                return 'transfer of title';

            // Mortgage variants → deed of mortgage
            if ($raw === 'tripartite mortgage')
                return 'deed of mortgage';
            if ($raw === 'legal mortgage')
                return 'deed of mortgage';
            if ($raw === 'equitable mortgage')
                return 'deed of mortgage';

            // Surrender/release variants → deed of surrender and release
            if ($raw === 'deed of surrender' || $raw === 'deed of release' || $raw === 'deed of surrender & release') {
                return 'deed of surrender and release';
            }

            // Power of Attorney variants → power of attorney
            // Collapses "Power Of Attorney", "Irrevocable Power Of Attorney",
            // "Deed Of Power Of Attorney", "POA", etc. to one canonical key so
            // copies of the same instrument across PRA/FH dedupe correctly.
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

        // Per LS-WEIGHTING METHOD spec, richness scoring applies ONLY to FH and PRA
        // (used to break ties when both sources hold the same logical record).
        // Five categories worth 2 points each → max total 10:
        //   Parties (any of P1/P2/P3 present), Reg Particulars, Transaction Date,
        //   Reg Time, Reg Date.
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

            // Primary: reg particulars-based key.
            // PRA stores deed execution date; File History stores registration date
            // for the same instrument — date-based keys caused them to never match.
            // Reg particulars (serial/page/volume) are source-neutral and authoritative.
            $serialNo = $cleanNumericValue($row['serial_no'] ?? null) ?: '0';
            $pageNoVal = $cleanNumericValue($row['page_no'] ?? null) ?: '0';
            $volumeNo = $cleanNumericValue($row['volume_no'] ?? null) ?: '0';
            $hasRealReg = ($serialNo !== '0' && $serialNo !== '') ||
                ($pageNoVal !== '0' && $pageNoVal !== '') ||
                ($volumeNo !== '0' && $volumeNo !== '');

            if ($hasRealReg) {
                return 'reg|' . $transactionType . '|' . $serialNo . '/' . $pageNoVal . '/' . $volumeNo;
            }

            // Fallback: party + date (for records without reg particulars, e.g. ROFO)
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

        // Rule B: Priority group (OP=10, TOT=9, RoFO=8) always first in weight order;
        // all others (incl. CofO) sorted chronologically by registration date.
        $getTransPriorityWeight = function (array $row) use ($norm, $canonicalTransactionType): int {
            $txType = $canonicalTransactionType($row['transaction_type'] ?? ($row['instrument_type'] ?? ''));
            if ($txType === 'occupancy permit')
                return 10;
            if ($txType === 'transfer of title')
                return 9;
            if ($txType === 'right of occupancy')
                return 8;
            // CofO + all other instruments share weight 5; chronological order applies within this tie group.
            return 5;
        };

        usort($transactions, function ($a, $b) use ($getTransactionTimestamp, $getTransPriorityWeight) {
            $wa = $getTransPriorityWeight($a);
            $wb = $getTransPriorityWeight($b);

            // Priority group always before secondary group
            if ($wa !== $wb)
                return $wb - $wa;

            // Within priority group: maintain weight order (already equal here,
            // so fall through to chronological for ties within the same instrument)
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
            return response()->json(['success' => false, 'message' => 'No records found'], 404);
        }

        // If the client supplied a timeline_order (built from the rendered
        // Timeline table after dedupe / Arrange), reorder $transactions to
        // match exactly so the printed report mirrors the on-screen order.
        $timelineOrderRaw = trim((string) $request->query('timeline_order', ''));
        if ($timelineOrderRaw !== '') {
            $labelToDb = [
                'PRA' => 'pra',
                'File History' => 'file_history_staging',
                'CofO' => 'CofO_staging',
                'Deed Registration' => 'deed_registrations',
            ];
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

        $fileNumber = $first['fileno'] ?: ($first['file_number'] ?: ($first['mlsFNo'] ?: '-'));
        $kangisNumber = $first['kangisFileNo'] ?? null;
        if ($kangisNumber === '-')
            $kangisNumber = null;
        $relatedMls = null;

        // Get file_title, plot_number, tp_no from file_indexings table
        $fileTitle = '-';
        $fiPlotNo = null;
        $fiTpNo = null;
        $fiLandUse = null;
        if ($fileNumber && $fileNumber !== '-') {
            $fileNumberCandidates = array_values(array_unique(array_filter([$fileNumber, $fileNo])));
            $fi = DB::connection('sqlsrv')->table('file_indexings')
                ->whereNull('deleted_at')
                ->where(function ($q) use ($fileNumberCandidates) {
                    foreach ($fileNumberCandidates as $candidate) {
                        $q->orWhere('file_number', $candidate)
                            ->orWhere('related_fileno', 'like', '%' . $candidate . '%');
                    }
                })
                ->select('file_title', 'plot_number', 'tp_no', 'land_use_type', 'related_fileno')
                ->first();
            if ($fi) {
                if ($fi->file_title)
                    $fileTitle = $tc($fi->file_title);
                $fiPlotNo = $fi->plot_number ?: null;
                $fiTpNo = $fi->tp_no ?: null;
                $fiLandUse = $fi->land_use_type ?: null;

                $relatedMls = $parseRelatedFileno($fi->related_fileno ?? null);
            }
        }

        // Display rule: for KANGIS-numbered files, show "KANGIS (related MLSF)"
        $fileNumberDisplay = $fileNumber;
        if ($kangisNumber) {
            $fileNumberDisplay = $kangisNumber;
            if ($relatedMls && strcasecmp(trim($relatedMls), trim($kangisNumber)) !== 0) {
                $fileNumberDisplay .= ' (' . $relatedMls . ')';
            }
        } elseif ($relatedMls && $isKangis($fileNo)) {
            $fileNumberDisplay = $fileNo . ' (' . $relatedMls . ')';
        }
        // Fallback to party_2 if file_indexings has no title
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
        // Plot No: prefer file_indexings, fallback to transaction data
        $plotNo = $fiPlotNo ?: ($first['plot_no'] ?: '-');

        // Size: use source weighting: CofO(4) > FH(3) > PRA(2) > Deed(1)
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

        // Land Use: prefer file_indexings, fallback to transaction data
        $landUse = $tc($fiLandUse ?: ($first['land_use'] ?: '-'));

        // Pick the longest/most descriptive location across all transactions
        $bestLocation = '-';
        foreach ($transactions as $t) {
            $loc = $t['location'] ?? '';
            if ($loc && $loc !== '-' && mb_strlen($loc) > mb_strlen($bestLocation === '-' ? '' : $bestLocation)) {
                $bestLocation = $loc;
            }
        }
        $plotDescription = $tc($bestLocation);

        // TPNO: prefer file_indexings, fallback to transaction data
        $tpno = $fiTpNo ?: ($first['tp_no'] ?: '-');

        $rows = [];
        foreach ($transactions as $idx => $t) {
            // Reg Date: prefer deeds_date, fallback to reg_date, then transaction_date
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

            // Reg Time: prefer deeds_time, fallback to reg_time, format to 12-hour with AM/PM
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

            // Registration Particulars: build from serial/page/volume, fallback to 0/0/0
            $serialNo = $t['serial_no'] ?? null;
            $pageNoVal = $t['page_no'] ?? null;
            $volumeNo = $t['volume_no'] ?? null;
            $cleanReg = fn($v) => ($v && $v !== '-') ? preg_replace('/\.0$/', '', trim($v)) : '0';
            $hasAnyReg = ($serialNo && $serialNo !== '-') || ($pageNoVal && $pageNoVal !== '-') || ($volumeNo && $volumeNo !== '-');
            $regNoDisplay = $hasAnyReg
                ? $cleanReg($serialNo) . '/' . $cleanReg($pageNoVal) . '/' . $cleanReg($volumeNo)
                : '0/0/0';

            $rows[] = [
                'sn' => $idx + 1,
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
            ];
        }

        // Determine if any record is under caveat (DB flag)
        $caveatedRecord = collect($transactions)->first(fn($t) => $t['is_caveated']);
        $caveatId = $caveatedRecord ? ($caveatedRecord['caveat_id'] ?? null) : null;

        // Resolve caveat_number: the staging tables store the numeric caveats.id,
        // so look it up. Fall back to searching by file number if ID is missing.
        $caveatNumber = null;
        if ($caveatId) {
            $caveatNumber = DB::connection('sqlsrv')->table('caveats')
                ->where('id', $caveatId)
                ->value('caveat_number');
        }
        if (!$caveatNumber) {
            // Try finding an active caveat by any file number column or prop_id
            $caveatNumber = DB::connection('sqlsrv')->table('caveats')
                ->where('status', 'active')
                ->where(function ($q) use ($fileNumber, $fileNo, $propId) {
                    $variants = array_unique(array_filter([$fileNumber, $fileNo]));
                    foreach ($variants as $i => $fn) {
                        $method = $i === 0 ? 'where' : 'orWhere';
                        $q->{$method}(function ($sub) use ($fn) {
                            $sub->where('file_number_mlsf', $fn)
                                ->orWhere('file_number_kangis', $fn)
                                ->orWhere('file_number_new_kangis', $fn);
                        });
                    }
                    if ($propId) {
                        $q->orWhere('prop_id', $propId);
                    }
                })
                ->orderByDesc('id')
                ->value('caveat_number');
        }

        // Mortgage-based caveat: Deed Of Mortgage without a later Deed Of Surrender And Release
        $mortgageCaveat = false;
        $parseDate = fn($d) => $d && $d !== '-' ? (rescue(fn() => \Carbon\Carbon::parse($d), null, false)) : null;

        // Find the latest Deed Of Mortgage date (including Tripartite/Legal/Equitable mortgage aliases)
        $latestMortgage = collect($transactions)
            ->filter(fn($t) => stripos($t['transaction_type'] ?? '', 'deed of mortgage') !== false
                || stripos($t['transaction_type'] ?? '', 'tripartite mortgage') !== false
                || stripos($t['transaction_type'] ?? '', 'legal mortgage') !== false
                || stripos($t['transaction_type'] ?? '', 'equitable mortgage') !== false)
            ->map(fn($t) => $parseDate($t['reg_date'] ?? null))
            ->filter()
            ->max();

        if ($latestMortgage) {
            // Find the latest Deed Of Surrender And Release date
            $latestRelease = collect($transactions)
                ->filter(fn($t) => stripos($t['transaction_type'] ?? '', 'deed of surrender and release') !== false)
                ->map(fn($t) => $parseDate($t['reg_date'] ?? null))
                ->filter()
                ->max();

            // If no release exists, or the latest release is before the latest mortgage
            if (!$latestRelease || $latestRelease->lt($latestMortgage)) {
                $mortgageCaveat = true;
            }
        }

        $isCaveated = (bool) $caveatedRecord || $mortgageCaveat;

        // Check if any record has a Certificate of Occupancy
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
        $generatedBy = $user ? trim($user->first_name . ' ' . $user->last_name) : 'system';

        // Build remarks
        $remarksTimestamp = 'These details are as at ' . $now->format('l, F j, Y g:i A');
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

        // Comments from staging - try file_number first, then prop_id
        $comments = DB::connection('sqlsrv')->table('ls_comment_staging')
            ->where(function ($q) use ($fileNumber, $fileNo, $propId) {
                $q->where('file_number', $fileNumber);
                if ($fileNo && $fileNo !== $fileNumber) {
                    $q->orWhere('file_number', $fileNo);
                }
                if ($propId) {
                    $q->orWhere('prop_id', $propId);
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

        $generatedByText = 'Generated by ' . $generatedBy . ' at ' . $now->format('g:i A & d/m/Y');

        // Short hash QR data for verification
        $qrData = substr(hash_hmac('sha256', $fileNumber . $now->timestamp, config('app.key')), 0, 9);

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

        return response()->json([
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
                'has_cofo' => $hasCofo,
                'ground_rent' => $groundRentText,
                'no_cofo_comment' => $noCofoComment,
                'encumbrance_comment' => $encumbranceComment,
                'litigation_comment' => $litigationComment,
                'generated_by' => $generatedByText,
                'full_name' => $generatedBy,
                'qr_data' => $qrData,
            ],
        ]);
    }

    // ================================================================
    // Cleanup Mode endpoints
    // ================================================================

    /**
     * Match: Assign orphan records to a prop_id group.
     */
    public function match(Request $request)
    {
        $request->validate([
            'table' => 'required|string',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'prop_id' => 'required|string|max:20',
        ]);

        try {
            $count = $this->searchService->matchRecords(
                $request->input('table'),
                $request->input('ids'),
                $request->input('prop_id')
            );

            return response()->json([
                'success' => true,
                'message' => "{$count} record(s) matched to prop_id {$request->input('prop_id')}.",
                'data' => ['affected' => $count],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Drop: Unlink records from a prop_id group (set prop_id = NULL).
     */
    public function drop(Request $request)
    {
        $request->validate([
            'table' => 'required|string',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        try {
            $count = $this->searchService->dropRecords(
                $request->input('table'),
                $request->input('ids')
            );

            return response()->json([
                'success' => true,
                'message' => "{$count} record(s) dropped from prop_id group.",
                'data' => ['affected' => $count],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove: Soft-delete records (set is_deleted = 1).
     */
    public function remove(Request $request)
    {
        $request->validate([
            'table' => 'required|string',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        try {
            $count = $this->searchService->removeRecords(
                $request->input('table'),
                $request->input('ids')
            );

            return response()->json([
                'success' => true,
                'message' => "{$count} record(s) removed.",
                'data' => ['affected' => $count],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Update: Edit fields on a single record.
     */
    public function update(Request $request)
    {
        $request->validate([
            'table' => 'required|string',
            'id' => 'required|integer',
            'fields' => 'required|array|min:1',
        ]);

        try {
            $updated = $this->searchService->updateRecord(
                $request->input('table'),
                $request->input('id'),
                $request->input('fields')
            );

            return response()->json([
                'success' => $updated,
                'message' => $updated ? 'Record updated successfully.' : 'No changes made.',
                'data' => [],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Transfer caveat from a source record to a target record (PRA/CofO only).
     */
    public function transferCaveat(Request $request)
    {
        $request->validate([
            'source_table' => 'required|string|in:pra,CofO_staging',
            'source_id' => 'required|integer|min:1',
            'target_table' => 'required|string|in:pra,CofO_staging',
            'target_id' => 'required|integer|min:1',
        ]);

        try {
            $ok = $this->searchService->transferCaveat(
                $request->input('source_table'),
                (int) $request->input('source_id'),
                $request->input('target_table'),
                (int) $request->input('target_id')
            );

            return response()->json([
                'success' => $ok,
                'message' => $ok ? 'Caveat transferred successfully.' : 'No changes made.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to transfer caveat.'], 500);
        }
    }

    /**
     * Get a single record for editing.
     */
    public function getRecord(Request $request)
    {
        $request->validate([
            'table' => 'required|string',
            'id' => 'required|integer',
        ]);

        try {
            $record = $this->searchService->getRecord(
                $request->input('table'),
                $request->input('id')
            );

            if (!$record) {
                return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Record loaded.',
                'data' => $record,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Detect prop_id conflicts across selected records.
     */
    public function detectConflicts(Request $request)
    {
        $request->validate([
            'selections' => 'required|array|min:1',
            'selections.*.table' => 'required|string',
            'selections.*.ids' => 'required|array|min:1',
            'selections.*.ids.*' => 'integer',
        ]);

        $propIds = $this->searchService->detectPropIdConflicts(
            $request->input('selections')
        );

        return response()->json([
            'success' => true,
            'has_conflict' => count($propIds) > 1,
            'prop_ids' => $propIds,
        ]);
    }

    /**
     * Save timeline arrangement order for a prop_id.
     */
    public function saveArrangement(Request $request)
    {
        $request->validate([
            'prop_id' => 'required|string|max:20',
            'items' => 'required|array|min:1',
            'items.*.table' => 'required|string',
            'items.*.id' => 'required|integer',
            'items.*.order' => 'required|integer|min:1',
        ]);

        try {
            $count = $this->searchService->saveTimelineArrangement(
                $request->input('prop_id'),
                $request->input('items'),
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Arrangement saved successfully.',
                'data' => ['affected' => $count],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get timeline arrangement order for a prop_id.
     */
    public function getArrangement(Request $request)
    {
        $request->validate([
            'prop_id' => 'required|string|max:20',
        ]);

        $arrangement = $this->searchService->getTimelineArrangement(
            $request->input('prop_id')
        );

        return response()->json([
            'success' => true,
            'data' => ['arrangement' => $arrangement],
        ]);
    }

    // ================================================================
    // Comments / Remarks Staging
    // ================================================================

    public function getComments(Request $request)
    {
        $fileNumber = trim($request->input('file_number', ''));
        $propId = trim($request->input('prop_id', ''));

        if (!$fileNumber && !$propId) {
            return response()->json(['success' => false, 'message' => 'File number or prop_id required.']);
        }

        $query = DB::connection('sqlsrv')->table('ls_comment_staging');
        if ($fileNumber) {
            $query->where('file_number', $fileNumber);
        } else {
            $query->where('prop_id', $propId);
        }

        $records = $query->orderByDesc('updated_at')->get();

        $data = [];
        foreach ($records as $r) {
            $data[$r->comment_type ?? 'ground_rent'] = [
                'id' => $r->id,
                'amount' => $r->amount,
                'comment' => $r->comment,
                'comment_type' => $r->comment_type ?? 'ground_rent',
            ];
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function saveComment(Request $request)
    {
        $request->validate([
            'file_number' => 'required|string|max:100',
            'comment_type' => 'required|string|in:ground_rent,no_cofo,encumbrance,litigation',
            'amount' => 'nullable|numeric|min:0',
            'comment' => 'nullable|string|max:2000',
        ]);

        $fileNumber = trim($request->input('file_number'));
        $propId = trim($request->input('prop_id', ''));
        $commentType = $request->input('comment_type');
        $amount = $request->input('amount');
        $comment = trim($request->input('comment', ''));

        $existing = DB::connection('sqlsrv')->table('ls_comment_staging')
            ->where('file_number', $fileNumber)
            ->where('comment_type', $commentType)
            ->first();

        if ($existing) {
            DB::connection('sqlsrv')->table('ls_comment_staging')
                ->where('id', $existing->id)
                ->update([
                    'amount' => $amount,
                    'comment' => $comment,
                    'prop_id' => $propId ?: $existing->prop_id,
                    'updated_at' => now(),
                ]);
        } else {
            DB::connection('sqlsrv')->table('ls_comment_staging')->insert([
                'file_number' => $fileNumber,
                'prop_id' => $propId,
                'comment_type' => $commentType,
                'amount' => $amount,
                'comment' => $comment,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Comment saved.']);
    }

    /**
     * Save comment specifically for a CofO record.
     */
    public function saveCofoComment(Request $request)
    {
        $request->validate([
            'cofo_id' => 'required|integer|min:1',
            'cofo_comment' => 'nullable|string|max:2000',
        ]);

        $cofoId = (int) $request->input('cofo_id');
        $cofoComment = trim((string) $request->input('cofo_comment', ''));

        $conn = DB::connection('sqlsrv');
        $schema = $conn->getSchemaBuilder();

        if (!$schema->hasColumn('CofO_staging', 'cofo_comment')) {
            return response()->json([
                'success' => false,
                'message' => 'Column cofo_comment does not exist on CofO table.',
            ], 500);
        }

        $updateData = [
            'cofo_comment' => $cofoComment,
        ];

        if ($schema->hasColumn('CofO_staging', 'updated_at')) {
            $updateData['updated_at'] = now();
        }

        $affected = $conn->table('CofO_staging')
            ->where('id', $cofoId)
            ->update($updateData);

        if ($affected < 1) {
            return response()->json([
                'success' => false,
                'message' => 'CofO record not found or no changes made.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'CofO comment updated successfully.',
        ]);
    }

    /**
     * Create a new PRA or CofO record from the legal search interface.
     * PRA uses PropertyRecordController::store, while CofO writes directly to CofO_staging.
     */
    public function createRecord(Request $request)
    {
        $targetTable = trim((string) $request->input('target_table', 'pra'));

        if ($targetTable === 'pra') {
            $request->merge(['record_mode' => 'property']);
            $propertyRecordController = app(PropertyRecordController::class);
            return $propertyRecordController->store($request);
        }

        if ($targetTable === 'CofO_staging') {
            $request->validate([
                'mlsFNo' => 'nullable|string|max:255',
                'kangisFileNo' => 'nullable|string|max:255',
                'NewKANGISFileno' => 'nullable|string|max:255',
                'fileno' => 'nullable|string|max:255',
                'transactionType' => 'nullable|string|max:255',
                'transactionDate' => 'nullable|date',
                'serialNo' => 'nullable|string|max:50',
                'pageNo' => 'nullable|string|max:50',
                'volumeNo' => 'nullable|string|max:50',
                'regNo' => 'nullable|string|max:100',
                'location' => 'nullable|string|max:255',
                'plot_no' => 'nullable|string|max:100',
                'lgsaOrCity' => 'nullable|string|max:255',
                'land_use' => 'nullable|string|max:255',
                'comments' => 'nullable|string|max:2000',
            ]);

            $insertData = [
                'mlsFNo' => $request->input('mlsFNo'),
                'kangisFileNo' => $request->input('kangisFileNo'),
                'NewKANGISFileno' => $request->input('NewKANGISFileno'),
                'fileno' => $request->input('fileno') ?: $request->input('mlsFNo') ?: $request->input('kangisFileNo'),
                'transaction_type' => $request->input('transactionType') ?: $request->input('instrumentType'),
                'transaction_date' => $request->input('transactionDate'),
                'serialNo' => $request->input('serialNo'),
                'pageNo' => $request->input('pageNo'),
                'volumeNo' => $request->input('volumeNo'),
                'regNo' => $request->input('regNo'),
                'party_1' => $request->input('firstParty') ?: $request->input('Assignor') ?: $request->input('Grantor'),
                'party_2' => $request->input('secondParty') ?: $request->input('Assignee') ?: $request->input('Grantee'),
                'party_3' => $request->input('thirdParty'),
                'party_4' => $request->input('fourthParty') ?: $request->input('party_4'),
                'Assignor' => $request->input('Assignor'),
                'Assignee' => $request->input('Assignee'),
                'Mortgagor' => $request->input('Mortgagor'),
                'Mortgagee' => $request->input('Mortgagee'),
                'Grantor' => $request->input('Grantor'),
                'Grantee' => $request->input('Grantee'),
                'Surrenderor' => $request->input('Surrenderor'),
                'Surrenderee' => $request->input('Surrenderee'),
                'Lessor' => $request->input('Lessor'),
                'Lessee' => $request->input('Lessee'),
                'land_use' => $request->input('land_use'),
                'location' => $request->input('location'),
                'plot_no' => $request->input('plot_no'),
                'lgsaOrCity' => $request->input('lgsaOrCity'),
                'comments' => $request->input('comments'),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $hasSignal = !empty(array_filter([
                $insertData['mlsFNo'],
                $insertData['kangisFileNo'],
                $insertData['NewKANGISFileno'],
                $insertData['fileno'],
                $insertData['transaction_type'],
                $insertData['party_1'],
                $insertData['party_2'],
                $insertData['regNo'],
            ], fn($v) => trim((string) $v) !== ''));

            if (!$hasSignal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please provide at least a file number, transaction type, parties, or registration particulars.',
                ], 422);
            }

            DB::connection('sqlsrv')->table('CofO_staging')->insert($insertData);

            return response()->json([
                'success' => true,
                'message' => 'CofO record created successfully.',
                'data' => [],
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid target table.'], 422);
    }
}
