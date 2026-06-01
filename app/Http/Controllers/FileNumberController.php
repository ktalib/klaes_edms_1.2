<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Yajra\DataTables\Facades\DataTables;
use App\Models\FileNumber;
use App\Models\LandUse;
use App\Models\StreetName;
use PhpOffice\PhpSpreadsheet\IOFactory;

class FileNumberController extends Controller
{
    /**
     * Cache of tracking IDs generated during the current request to avoid duplicates
     * before database persistence.
     *
     * @var array<string, bool>
     */
    private array $generatedTrackingIds = [];

    /**
     * Display the MLS File number generation page
     */
    public function index()
    {
        // Single query replaces 3 separate COUNT round-trips.
        // Results are cached for 10 minutes; cache is busted when a new file number
        // is commissioned (see store/generate methods which call Cache::forget).
        $stats = Cache::remember('mls_fileno_page_stats', 600, function () {
            $today = now()->toDateString();   // YYYY-MM-DD
            $month = now()->month;
            $year = now()->year;

            $row = DB::connection('sqlsrv')->selectOne(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN CAST(created_at AS DATE) = ? THEN 1 ELSE 0 END) AS today_count,
                    SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 ELSE 0 END) AS month_count
                 FROM fileNumber
                 WHERE mlsfNo IS NOT NULL
                   AND LTRIM(RTRIM(mlsfNo)) != ''
                   AND SOURCE IN ('MLS_Commissioned','MLS_Commissioned_Batch')
                   AND type = 'MlsFileNO'
                   AND (is_deleted IS NULL OR is_deleted = 0)
                   AND NOT EXISTS (
                       SELECT 1 FROM mls_file_no ms
                       WHERE ms.full_file_number = fileNumber.mlsfNo
                         AND (ms.sub_source = 'OP Change of Name' OR ms.source IN ('OP Resettlement', 'OP Direct Allocation'))
                   )",
                [$today, $month, $year]
            );

            return [
                'total' => (int) ($row->total ?? 0),
                'today' => (int) ($row->today_count ?? 0),
                'month' => (int) ($row->month_count ?? 0),
            ];
        });

        $totalCount = $stats['total'];
        $todayCount = $stats['today'];
        $monthCount = $stats['month'];

        // Fetch LGAs for dropdown
        $lgas = DB::connection('sqlsrv')->table('lgas')->select('name')->orderBy('name')->get();

        // Fetch states and street names for instrument capture modal reuse
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        // Fetch Districts for dropdown
        $districts = DB::connection('sqlsrv')->table('districts')->select('name')->where('is_active', 1)->orderBy('name')->get();
        if ($districts->isEmpty()) {
            $districts = DB::connection('sqlsrv')->table('districts')->select('name')->orderBy('name')->get();
        }

        // Fetch Land Uses
        $landUses = LandUse::all();

        // Fetch all Prefixes with land_use_id for frontend logic
        $allPrefixes = \App\Models\Prefix::select('id', 'prefix', 'land_use_id')->get();

        // Fetch unallocated entries from the allocation list
        $unallocatedEntries = \App\Models\AllocationListEntry::where('is_allocated', 0)
            ->orderBy('first_name')
            ->get();

        return view('generate_fileno.mlsfno', compact('totalCount', 'todayCount', 'monthCount', 'lgas', 'states', 'districts', 'streetNames', 'landUses', 'allPrefixes', 'unallocatedEntries'));
    }

    /**
     * Get statistics for the dashboard
     */
    public function getStats()
    {
        $capturedTypes = ['Captured', 'Migrated', 'indexing', 'Indexing', 'INDEXING', 'KANGIS GIS'];
        $capturedSources = ['Captured', 'Migrated', 'indexing', 'Indexing', 'INDEXING', 'KANGIS GIS'];

        // Helper query builder
        $queryBuilder = function () use ($capturedTypes, $capturedSources) {
            return DB::connection('sqlsrv')
                ->table('fileNumber')
                ->whereNotNull('mlsfNo')
                ->where('mlsfNo', '!=', '')
                ->whereIn('SOURCE', ['MLS_Commissioned', 'MLS_Commissioned_Batch'])
                ->where('type', 'MlsFileNO')
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                });
        };

        $totalCount = (clone $queryBuilder())->count();
        $todayCount = (clone $queryBuilder())->whereDate('created_at', now()->today())->count();
        $monthCount = (clone $queryBuilder())->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $totalCount,
                'today' => $todayCount,
                'month' => $monthCount
            ]
        ]);
    }

    /**
     * Display the Capture Existing File page (Global View – all file numbers)
     */
    public function captureIndex()
    {
        $notDeleted = "(is_deleted IS NULL OR is_deleted = 0)";

        $stats = DB::connection('sqlsrv')->selectOne("
            SELECT
                COUNT(*)                                                                             AS total,
                SUM(CASE WHEN mlsfNo          IS NOT NULL AND LTRIM(RTRIM(mlsfNo))          != '' THEN 1 ELSE 0 END) AS mls_count,
                SUM(CASE WHEN kangisFileNo    IS NOT NULL AND LTRIM(RTRIM(kangisFileNo))    != '' THEN 1 ELSE 0 END) AS kangis_count,
                SUM(CASE WHEN NewKANGISFileNo IS NOT NULL AND LTRIM(RTRIM(NewKANGISFileNo)) != '' THEN 1 ELSE 0 END) AS new_kangis_count,
                SUM(CASE WHEN st_file_no      IS NOT NULL AND LTRIM(RTRIM(st_file_no))      != '' THEN 1 ELSE 0 END) AS st_count
            FROM fileNumber
            WHERE {$notDeleted}
        ");

        $totalCount = (int) ($stats->total ?? 0);
        $mlsfCount = (int) ($stats->mls_count ?? 0);
        $kangisCount = (int) ($stats->kangis_count ?? 0);
        $newKangisCount = (int) ($stats->new_kangis_count ?? 0);
        $stCount = (int) ($stats->st_count ?? 0);

        // Generate a fresh tracking ID for this session
        $trackingId = $this->generateTrackingId();

        return view('generate_fileno.capture_existing', compact(
            'totalCount',
            'mlsfCount',
            'kangisCount',
            'newKangisCount',
            'stCount',
            'trackingId'
        ));
    }



    public function getData(Request $request)
    {
        try {
            $draw = $request->input('draw');
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 20);
            $search = $request->input('search.value', '');
            $source = $request->input('source', 'New');

            // ── Source WHERE fragment (no bindings needed — string literals only) ──
            if ($source === 'New') {
                $sourceWhere = "fn.SOURCE IN ('MLS_Commissioned','MLS_Commissioned_Batch')
                                AND fn.type = 'MlsFileNO'
                                AND NOT EXISTS (
                                    SELECT 1 FROM mls_file_no ms
                                    WHERE ms.full_file_number = fn.mlsfNo
                                      AND ms.sub_source = 'OP Change of Name'
                                )";
            } elseif ($source === 'All') {
                // Global view – no type/source filter, show everything
                $sourceWhere = "1=1";
            } else {
                $sourceWhere = "(   fn.type   IN ('Captured','Migrated','indexing','Indexing','INDEXING','KANGIS GIS')
                                 OR fn.SOURCE IN ('Captured','Migrated','indexing','Indexing','INDEXING','KANGIS GIS')
                                 OR (fn.st_file_no IS NOT NULL AND LTRIM(RTRIM(fn.st_file_no)) != ''))";
            }

            // ── Search WHERE fragment + bindings ──
            $searchSql = '';
            $searchBindings = [];
            if (!empty($search)) {
                $pct = "%{$search}%";
                $searchSql = "AND (   fn.kangisFileNo    LIKE ?
                                       OR fn.NewKANGISFileNo LIKE ?
                                       OR fn.FileName        LIKE ?
                                       OR fn.mlsfNo          LIKE ?
                                       OR fn.st_file_no      LIKE ?
                                       OR fn.tracking_id     LIKE ?
                                       OR fn.lga             LIKE ?
                                       OR fn.location        LIKE ?
                                       OR fn.plot_no         LIKE ?
                                       OR fn.tp_no           LIKE ?)";
                $searchBindings = [$pct, $pct, $pct, $pct, $pct, $pct, $pct, $pct, $pct, $pct];
            }

            // ── Fast total count (cached 5 min) ──
            // Uses a single OUTER APPLY for batch_no only — no other lookups needed.
            $recordsTotal = Cache::remember("file_numbers_total_v2_{$source}", 300, function () use ($sourceWhere) {
                $row = DB::connection('sqlsrv')->selectOne(
                    "SELECT COUNT(DISTINCT COALESCE(NULLIF(mls.batch_no,''), CAST(fn.id AS VARCHAR(20)))) AS cnt
                     FROM fileNumber fn
                     OUTER APPLY (
                         SELECT TOP 1 batch_no FROM mls_file_no
                         WHERE full_file_number = fn.mlsfNo ORDER BY id DESC
                     ) AS mls
                     WHERE (fn.is_deleted IS NULL OR fn.is_deleted = 0)
                       AND {$sourceWhere}"
                );
                return (int) ($row->cnt ?? 0);
            });

            // ── Add temporary-file count to totals (source=New only) ──
            // Temporary files only exist in mls_file_no, so we count them separately.
            $tempCount = 0;
            if ($source === 'New') {
                $tempRow = DB::connection('sqlsrv')->selectOne(
                    "SELECT COUNT(*) AS cnt FROM mls_file_no
                     WHERE file_option = 'temporary'
                       AND (is_deleted IS NULL OR is_deleted = 0)
                       AND NOT EXISTS (
                           SELECT 1 FROM fileNumber fn2
                           WHERE fn2.mlsfNo = mls_file_no.full_file_number
                             AND (fn2.is_deleted IS NULL OR fn2.is_deleted = 0)
                       )"
                );
                $tempCount = (int) ($tempRow->cnt ?? 0);
                $recordsTotal += $tempCount;
            }

            // ── Fast filtered count ──
            // Short-circuit when no search: filtered = total (saves a whole SQL round-trip).
            if (empty($search)) {
                $filteredRecords = $recordsTotal;
            } else {
                $row = DB::connection('sqlsrv')->selectOne(
                    "SELECT COUNT(DISTINCT COALESCE(NULLIF(mls.batch_no,''), CAST(fn.id AS VARCHAR(20)))) AS cnt
                     FROM fileNumber fn
                     OUTER APPLY (
                         SELECT TOP 1 batch_no FROM mls_file_no
                         WHERE full_file_number = fn.mlsfNo ORDER BY id DESC
                     ) AS mls
                     WHERE (fn.is_deleted IS NULL OR fn.is_deleted = 0)
                       AND {$sourceWhere}
                       {$searchSql}",
                    $searchBindings
                );
                $filteredRecords = (int) ($row->cnt ?? 0);

                // Also count matching temporary files in filtered count
                if ($source === 'New') {
                    $pct = "%{$search}%";
                    $tempFiltered = DB::connection('sqlsrv')->selectOne(
                        "SELECT COUNT(*) AS cnt FROM mls_file_no
                         WHERE file_option = 'temporary'
                           AND (is_deleted IS NULL OR is_deleted = 0)
                           AND NOT EXISTS (
                               SELECT 1 FROM fileNumber fn2
                               WHERE fn2.mlsfNo = mls_file_no.full_file_number
                                 AND (fn2.is_deleted IS NULL OR fn2.is_deleted = 0)
                           )
                           AND (
                               full_file_number LIKE ?
                               OR file_name     LIKE ?
                               OR tracking_id   LIKE ?
                               OR location      LIKE ?
                               OR lga           LIKE ?
                               OR plot_no       LIKE ?
                               OR tp_no         LIKE ?
                           )",
                        [$pct, $pct, $pct, $pct, $pct, $pct, $pct]
                    );
                    $filteredRecords += (int) ($tempFiltered->cnt ?? 0);
                }
            }

            // ── Main data query (two-phase) ──
            //
            // PHASE 1 – Paginate cheaply.
            // Only the mls_file_no OUTER APPLY is needed here (for batch_no grouping).
            // We intentionally exclude the expensive pra / instrument_capture /
            // file_commissioning_sheets lookups so they don't fire for every row
            // in the full result set (~1,540+).  Window functions still need to see
            // all matching rows to compute correct PARTITION values, but the row
            // data carried through is minimal.
            $phaseSql = "
                SELECT id, mlsfNo, derived_batch_no, batch_count, batch_first_file
                FROM (
                    SELECT
                        fn.id,
                        fn.mlsfNo,
                        COALESCE(NULLIF(mls.batch_no,''), CAST(fn.id AS VARCHAR(20))) AS batch_key,
                        mls.batch_no AS derived_batch_no,
                        ROW_NUMBER() OVER (
                            PARTITION BY COALESCE(NULLIF(mls.batch_no,''), CAST(fn.id AS VARCHAR(20)))
                            ORDER BY fn.id DESC
                        ) AS group_rn,
                        COUNT(*) OVER (
                            PARTITION BY COALESCE(NULLIF(mls.batch_no,''), CAST(fn.id AS VARCHAR(20)))
                        ) AS batch_count,
                        FIRST_VALUE(fn.mlsfNo) OVER (
                            PARTITION BY COALESCE(NULLIF(mls.batch_no,''), CAST(fn.id AS VARCHAR(20)))
                            ORDER BY fn.id ASC
                            ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING
                        ) AS batch_first_file
                    FROM fileNumber fn
                    OUTER APPLY (
                        SELECT TOP 1 m.batch_no
                        FROM mls_file_no m
                        WHERE m.full_file_number = fn.mlsfNo
                        ORDER BY m.id DESC
                    ) AS mls
                    WHERE (fn.is_deleted IS NULL OR fn.is_deleted = 0)
                      AND {$sourceWhere}
                      {$searchSql}
                ) AS windowed
                WHERE group_rn = 1
                ORDER BY id DESC
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
            ";

            $pagedRows = DB::connection('sqlsrv')->select(
                $phaseSql,
                array_merge($searchBindings, [$start, $length])
            );

            // Build lookup maps from phase-1 results
            $batchMeta = [];   // id => [batch_no, batch_count, batch_first_file]
            $pageIds = [];
            foreach ($pagedRows as $r) {
                $pageIds[] = (int) $r->id;
                $batchMeta[$r->id] = [
                    'batch_no' => $r->derived_batch_no,
                    'batch_count' => (int) $r->batch_count,
                    'batch_first_file' => $r->batch_first_file,
                ];
            }

            // Nothing in fileNumber – but we may still have temporary files to show
            if (empty($pageIds)) {
                $tempFormattedData2 = collect();
                if ($source === 'New') {
                    $tmpSrchSql = '';
                    $tmpSrchBindings = [];
                    if (!empty($search)) {
                        $pct = "%{$search}%";
                        $tmpSrchSql = "AND (m.full_file_number LIKE ? OR m.file_name LIKE ? OR m.tracking_id LIKE ?
                            OR m.location LIKE ? OR m.lga LIKE ? OR m.plot_no LIKE ? OR m.tp_no LIKE ?)";
                        $tmpSrchBindings = [$pct, $pct, $pct, $pct, $pct, $pct, $pct];
                    }
                    $tmpRows = DB::connection('sqlsrv')->select(
                        "SELECT m.id, m.full_file_number, m.file_name, m.land_use, m.customer_type,
                                m.plot_no, m.tp_no, m.location, m.lga, m.tracking_id,
                                m.created_by, m.commissioning_date, m.created_at, m.source,
                                m.batch_no, p.name AS purpose_name
                         FROM mls_file_no m
                         LEFT JOIN purposes p ON p.id = m.purpose_id
                         WHERE m.file_option = 'temporary'
                           AND (m.is_deleted IS NULL OR m.is_deleted = 0)
                           AND NOT EXISTS (
                               SELECT 1 FROM fileNumber fn2
                               WHERE fn2.mlsfNo = m.full_file_number
                                 AND (fn2.is_deleted IS NULL OR fn2.is_deleted = 0)
                           )
                           {$tmpSrchSql}
                         ORDER BY m.id DESC",
                        $tmpSrchBindings
                    );
                    $tempFormattedData2 = collect($tmpRows)->map(function ($row) {
                        $fileNo = trim($row->full_file_number ?? '');
                        $id = (int) $row->id;
                        $actionHtml = '<div class="relative action-dropdown"><button type="button" class="p-1 rounded-full hover:bg-slate-100 transition-colors"><i data-lucide="more-horizontal" class="w-5 h-5 text-slate-500"></i></button><div class="action-dropdown-menu"><div class="py-1"><button onclick="editRecord(' . $id . ')" class="w-full text-left px-4 py-2.5 text-sm flex items-center space-x-3 text-slate-700 hover:bg-slate-50"><i data-lucide="pencil" class="w-4 h-4 text-slate-500"></i><span class="font-medium">Edit Record</span></button></div></div></div>';
                        return [
                            'id' => $id, 'primaryFileNo' => $fileNo ?: 'N/A', 'relatedFileNo' => 'N/A',
                            'mlsfNo' => $fileNo ?: 'N/A', 'kangisFileNo' => 'N/A', 'NewKANGISFileNo' => 'N/A', 'stFileNo' => 'N/A',
                            'FileName' => trim($row->file_name ?? '') ?: 'N/A',
                            'land_use' => trim($row->land_use ?? '') ?: 'N/A',
                            'customer_type' => trim($row->customer_type ?? '') ?: 'N/A',
                            'purpose_name' => trim($row->purpose_name ?? '') ?: 'N/A',
                            'plot_no' => trim($row->plot_no ?? '') ?: 'N/A', 'tp_no' => trim($row->tp_no ?? '') ?: 'N/A',
                            'location' => trim($row->location ?? '') ?: 'N/A', 'lga' => trim($row->lga ?? '') ?: 'N/A',
                            'tracking_id' => trim($row->tracking_id ?? '') ?: 'N/A', 'type' => 'Temporary',
                            'created_by' => trim($row->created_by ?? '') ?: 'System',
                            'source' => trim($row->source ?? '') ?: 'Temporary File',
                            'source_instrument_capture_id' => null, 'source_pra_id' => null, 'source_prop_id' => null,
                            'source_temp_fileno' => 'N/A', 'batch_no' => trim($row->batch_no ?? ''), 'batch_count' => 1,
                            'batch_first_file' => $fileNo,
                            'commissioning_date' => $row->commissioning_date ? date('Y-m-d', strtotime($row->commissioning_date)) : 'N/A',
                            'has_commissioning_sheet' => false,
                            'created_at' => $row->created_at ? date('Y-m-d H:i:s', strtotime($row->created_at)) : 'N/A',
                            'action' => $actionHtml,
                        ];
                    });
                }
                return response()->json([
                    'draw' => intval($draw),
                    'recordsTotal' => $recordsTotal,
                    'recordsFiltered' => $filteredRecords,
                    'data' => $tempFormattedData2->values(),
                ]);
            }

            // PHASE 2 – Enrich only the page-size rows (20 max).
            // All expensive OUTER APPLYs (pra, instrument_capture,
            // file_commissioning_sheets, dciv_link, file_indexing_links)
            // now fire at most 20 times per request.
            $inList = implode(',', $pageIds);   // safe: all are cast to int above
            $enrichSql = "
                SELECT
                    fn.id, fn.kangisFileNo, fn.mlsfNo, fn.NewKANGISFileNo, fn.FileName,
                    fn.st_file_no, fn.plot_no, fn.tp_no, fn.location, fn.tracking_id,
                    fn.type, fn.created_at, fn.SOURCE,
                    mls.batch_no                              AS derived_batch_no,
                    mls.land_use                              AS derived_land_use,
                    mls.customer_type                         AS derived_customer_type,
                    mls.commissioning_date                    AS derived_commissioning_date,
                    mls.source                                AS derived_source,
                    mls.source_instrument_capture_id          AS derived_source_instrument_capture_id,
                    COALESCE(fn.lga,         mls.lga)         AS derived_lga,
                    COALESCE(mls.created_by, fn.created_by)   AS derived_created_by,
                    pur.name                                  AS purpose_name,
                    pra.id                                    AS derived_source_pra_id,
                    COALESCE(ic.prop_id,     pra.prop_id)     AS derived_source_prop_id,
                    COALESCE(ic.temp_fileno, pra.temp_fileno) AS derived_source_temp_fileno,
                    CASE WHEN cs.file_number IS NOT NULL THEN 1 ELSE 0 END AS has_commissioning_sheet,
                    dciv_rel.dciv_related,
                    fil_rel.fil_related
                FROM fileNumber fn
                OUTER APPLY (
                    SELECT TOP 1
                        m.batch_no, m.land_use, m.customer_type, m.commissioning_date,
                        m.source, m.source_instrument_capture_id, m.lga, m.created_by, m.purpose_id
                    FROM mls_file_no m
                    WHERE m.full_file_number = fn.mlsfNo
                    ORDER BY m.id DESC
                ) AS mls
                OUTER APPLY (
                    SELECT TOP 1 p.name FROM purposes p WHERE p.id = mls.purpose_id
                ) AS pur
                OUTER APPLY (
                    SELECT TOP 1 p.id, p.prop_id, p.temp_fileno
                    FROM pra p WHERE p.mlsFNo = fn.mlsfNo
                    ORDER BY p.id DESC
                ) AS pra
                OUTER APPLY (
                    SELECT TOP 1 ic.prop_id, ic.temp_fileno
                    FROM instrument_capture ic
                    WHERE ic.id = mls.source_instrument_capture_id
                ) AS ic
                OUTER APPLY (
                    SELECT STUFF((
                        SELECT ', ' + dl.related_file_number
                        FROM dciv_link dl
                        WHERE dl.main_file_number IN (fn.mlsfNo, fn.kangisFileNo, fn.NewKANGISFileNo, fn.st_file_no)
                          AND dl.related_file_number IS NOT NULL
                          AND LTRIM(RTRIM(dl.related_file_number)) != ''
                        FOR XML PATH(''), TYPE
                    ).value('.', 'NVARCHAR(MAX)'), 1, 2, '') AS dciv_related
                ) AS dciv_rel
                OUTER APPLY (
                    SELECT STUFF((
                        SELECT ', ' + fil.file_number
                        FROM file_indexing_links fil
                        WHERE fil.file_indexing_id IN (
                            SELECT fi.id FROM file_indexings fi
                            WHERE fi.file_number IN (fn.mlsfNo, fn.kangisFileNo, fn.NewKANGISFileNo, fn.st_file_no)
                        )
                          AND fil.file_number IS NOT NULL
                          AND LTRIM(RTRIM(fil.file_number)) != ''
                        FOR XML PATH(''), TYPE
                    ).value('.', 'NVARCHAR(MAX)'), 1, 2, '') AS fil_related
                ) AS fil_rel
                LEFT JOIN (SELECT DISTINCT file_number FROM file_commissioning_sheets) cs
                    ON cs.file_number = fn.mlsfNo
                WHERE fn.id IN ({$inList})
            ";

            // Re-sort to match the order from phase 1  
            $enrichedMap = [];
            foreach (DB::connection('sqlsrv')->select($enrichSql) as $r) {
                $enrichedMap[$r->id] = $r;
            }

            // Merge phase-2 enrichment + phase-1 batch meta, preserving phase-1 order
            $data = [];
            foreach ($pageIds as $id) {
                if (!isset($enrichedMap[$id]))
                    continue;
                $row = $enrichedMap[$id];
                // Overlay correct batch aggregates from phase 1
                $row->derived_batch_no = $batchMeta[$id]['batch_no'] ?? '';
                $row->batch_count = $batchMeta[$id]['batch_count'] ?? 1;
                $row->batch_first_file = $batchMeta[$id]['batch_first_file'] ?? $row->mlsfNo;
                $data[] = $row;
            }

            // ── Format response ──
            $formattedData = collect($data)->map(function ($row) {
                // Build primaryFileNo: first non-empty of mlsfNo, kangisFileNo, NewKANGISFileNo, st_file_no
                $primaryFileNo = collect([
                    $row->mlsfNo ?? '',
                    $row->kangisFileNo ?? '',
                    $row->NewKANGISFileNo ?? '',
                    $row->st_file_no ?? '',
                ])->map(fn($v) => trim($v))->first(fn($v) => $v !== '') ?: 'N/A';

                // Build relatedFileNo: merge from dciv_link and file_indexing_links
                $relatedParts = array_filter([
                    $row->dciv_related ?? '',
                    $row->fil_related ?? '',
                ]);
                $allRelated = [];
                foreach ($relatedParts as $part) {
                    foreach (explode(',', $part) as $r) {
                        $r = trim($r);
                        if ($r !== '' && !in_array($r, $allRelated)) {
                            $allRelated[] = $r;
                        }
                    }
                }
                $relatedFileNo = implode(', ', $allRelated) ?: 'N/A';

                return [
                    'id' => $row->id,
                    'primaryFileNo' => $primaryFileNo,
                    'relatedFileNo' => $relatedFileNo,
                    'mlsfNo' => trim($row->mlsfNo ?? '') ?: 'N/A',
                    'kangisFileNo' => trim($row->kangisFileNo ?? '') ?: 'N/A',
                    'NewKANGISFileNo' => trim($row->NewKANGISFileNo ?? '') ?: 'N/A',
                    'stFileNo' => trim($row->st_file_no ?? '') ?: 'N/A',
                    'FileName' => trim($row->FileName ?? '') ?: 'N/A',
                    'land_use' => trim($row->derived_land_use ?? '') ?: 'N/A',
                    'customer_type' => trim($row->derived_customer_type ?? '') ?: 'N/A',
                    'purpose_name' => trim($row->purpose_name ?? '') ?: 'N/A',
                    'plot_no' => trim($row->plot_no ?? '') ?: 'N/A',
                    'tp_no' => trim($row->tp_no ?? '') ?: 'N/A',
                    'location' => trim($row->location ?? '') ?: 'N/A',
                    'lga' => trim($row->derived_lga ?? '') ?: 'N/A',
                    'tracking_id' => trim($row->tracking_id ?? '') ?: 'N/A',
                    'type' => trim($row->type ?? '') ?: 'N/A',
                    'created_by' => trim($row->derived_created_by ?? '') ?: 'System',
                    'source' => trim($row->derived_source ?? '') ?: (trim($row->SOURCE ?? '') ?: 'N/A'),
                    'source_instrument_capture_id' => $row->derived_source_instrument_capture_id ? (int) $row->derived_source_instrument_capture_id : null,
                    'source_pra_id' => $row->derived_source_pra_id ? (int) $row->derived_source_pra_id : null,
                    'source_prop_id' => $row->derived_source_prop_id ? (int) $row->derived_source_prop_id : null,
                    'source_temp_fileno' => trim($row->derived_source_temp_fileno ?? '') ?: 'N/A',
                    'batch_no' => trim($row->derived_batch_no ?? ''),
                    'batch_count' => (int) $row->batch_count,
                    'batch_first_file' => trim($row->batch_first_file ?? ''),
                    'commissioning_date' => $row->derived_commissioning_date ? date('Y-m-d', strtotime($row->derived_commissioning_date)) : 'N/A',
                    'has_commissioning_sheet' => (bool) $row->has_commissioning_sheet,
                    'created_at' => $row->created_at ? date('Y-m-d H:i:s', strtotime($row->created_at)) : 'N/A',
                    'action' => $this->buildCaptureActionColumn($row, $row->SOURCE),
                ];
            });

            // ── Prepend temporary files from mls_file_no (source=New only) ──
            $tempFormattedData = collect();
            if ($source === 'New') {
                $tempSearchSql = '';
                $tempBindings = [];
                if (!empty($search)) {
                    $pct = "%{$search}%";
                    $tempSearchSql = "AND (
                        m.full_file_number LIKE ? OR m.file_name LIKE ? OR m.tracking_id LIKE ?
                        OR m.location LIKE ? OR m.lga LIKE ? OR m.plot_no LIKE ? OR m.tp_no LIKE ?
                    )";
                    $tempBindings = [$pct, $pct, $pct, $pct, $pct, $pct, $pct];
                }

                $tempRows = DB::connection('sqlsrv')->select(
                    "SELECT m.id, m.full_file_number, m.file_name, m.land_use, m.customer_type,
                            m.plot_no, m.tp_no, m.location, m.lga, m.tracking_id,
                            m.created_by, m.commissioning_date, m.created_at, m.source,
                            m.batch_no, p.name AS purpose_name
                     FROM mls_file_no m
                     LEFT JOIN purposes p ON p.id = m.purpose_id
                     WHERE m.file_option = 'temporary'
                       AND (m.is_deleted IS NULL OR m.is_deleted = 0)
                       AND NOT EXISTS (
                           SELECT 1 FROM fileNumber fn2
                           WHERE fn2.mlsfNo = m.full_file_number
                             AND (fn2.is_deleted IS NULL OR fn2.is_deleted = 0)
                       )
                       {$tempSearchSql}
                     ORDER BY m.id DESC",
                    $tempBindings
                );

                $tempFormattedData = collect($tempRows)->map(function ($row) {
                    $fileNo = trim($row->full_file_number ?? '');
                    // Build a fake action column for temp files (view only, no batch)
                    $id = (int) $row->id;
                    $actionHtml = '
                    <div class="relative action-dropdown">
                        <button type="button" class="p-1 rounded-full hover:bg-slate-100 transition-colors">
                            <i data-lucide="more-horizontal" class="w-5 h-5 text-slate-500"></i>
                        </button>
                        <div class="action-dropdown-menu"><div class="py-1">
                            <button onclick="editRecord(' . $id . ')" class="w-full text-left px-4 py-2.5 text-sm flex items-center space-x-3 text-slate-700 hover:bg-slate-50">
                                <i data-lucide="pencil" class="w-4 h-4 text-slate-500"></i>
                                <span class="font-medium">Edit Record</span>
                            </button>
                        </div></div>
                    </div>';

                    return [
                        'id'                          => $id,
                        'primaryFileNo'               => $fileNo ?: 'N/A',
                        'relatedFileNo'               => 'N/A',
                        'mlsfNo'                      => $fileNo ?: 'N/A',
                        'kangisFileNo'                => 'N/A',
                        'NewKANGISFileNo'             => 'N/A',
                        'stFileNo'                    => 'N/A',
                        'FileName'                    => trim($row->file_name ?? '') ?: 'N/A',
                        'land_use'                    => trim($row->land_use ?? '') ?: 'N/A',
                        'customer_type'               => trim($row->customer_type ?? '') ?: 'N/A',
                        'purpose_name'                => trim($row->purpose_name ?? '') ?: 'N/A',
                        'plot_no'                     => trim($row->plot_no ?? '') ?: 'N/A',
                        'tp_no'                       => trim($row->tp_no ?? '') ?: 'N/A',
                        'location'                    => trim($row->location ?? '') ?: 'N/A',
                        'lga'                         => trim($row->lga ?? '') ?: 'N/A',
                        'tracking_id'                 => trim($row->tracking_id ?? '') ?: 'N/A',
                        'type'                        => 'Temporary',
                        'created_by'                  => trim($row->created_by ?? '') ?: 'System',
                        'source'                      => trim($row->source ?? '') ?: 'Temporary File',
                        'source_instrument_capture_id'=> null,
                        'source_pra_id'               => null,
                        'source_prop_id'              => null,
                        'source_temp_fileno'          => 'N/A',
                        'batch_no'                    => trim($row->batch_no ?? ''),
                        'batch_count'                 => 1,
                        'batch_first_file'            => $fileNo,
                        'commissioning_date'          => $row->commissioning_date ? date('Y-m-d', strtotime($row->commissioning_date)) : 'N/A',
                        'has_commissioning_sheet'     => false,
                        'created_at'                  => $row->created_at ? date('Y-m-d H:i:s', strtotime($row->created_at)) : 'N/A',
                        'action'                      => $actionHtml,
                    ];
                });
            }

            // Merge: temp files first, then regular records
            $mergedData = $tempFormattedData->concat($formattedData)->values();

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $filteredRecords,
                'data' => $mergedData,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in FileNumberController getData: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'draw' => intval($request->input('draw')),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Error loading data: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Get data for Capture Existing Files DataTables (Global View)
     */
    public function getCaptureData(Request $request)
    {
        // Global view — show ALL file numbers
        $request->merge(['source' => 'All']);
        return $this->getData($request);
    }

    protected function buildCaptureActionColumn($row, $source = null): string
    {
        $id = (int) ($row->id ?? 0);
        $batchNo = trim($row->derived_batch_no ?? $row->batch_no ?? '');
        $hasBatchNo = !empty($batchNo);

        if ($id <= 0) {
            return '<span class="inline-flex items-center px-2 py-1 rounded-md bg-slate-100 text-xs font-semibold text-slate-500">No actions</span>';
        }

        $isSupperAdmin = Auth::user() && Auth::user()->assign_role === 'Supper Admin';

        // Use the same dropdown structure as the main table for consistency and responsiveness
        $dropdown = '
        <div class="relative action-dropdown">
            <button type="button" class="p-1 rounded-full hover:bg-slate-100 transition-colors focus:outline-none focus:ring-2 focus:ring-slate-200">
                <i data-lucide="more-horizontal" class="w-5 h-5 text-slate-500"></i>
            </button>
            
            <div class="action-dropdown-menu">
                <div class="py-1">
                    <!-- Edit -->
                    <button onclick="editRecord(' . $id . ')" 
                            class="w-full text-left px-4 py-2.5 text-sm flex items-center space-x-3 text-slate-700 hover:bg-slate-50 transition-colors">
                        <i data-lucide="pencil" class="w-4 h-4 text-slate-500"></i>
                        <span class="font-medium">Edit Record</span>
                    </button>';

        if ($isSupperAdmin) {
            $dropdown .= '
                    <!-- Delete -->
                    <button onclick="deleteRecord(' . $id . ')" 
                            class="w-full text-left px-4 py-2.5 text-sm flex items-center space-x-3 text-red-600 hover:bg-red-50 transition-colors">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                        <span class="font-medium">Delete Record</span>
                    </button>';
        }

        if ($hasBatchNo && $source !== 'Captured') {
            $dropdown .= '
                    <div class="border-t border-slate-100 my-1"></div>
                    
                    <!-- Printer Manager -->
                    <button onclick="openPrinterManager(\'' . $id . '\', \'' . $batchNo . '\')" 
                            class="w-full text-left px-4 py-2.5 text-sm text-blue-600 hover:bg-blue-50 flex items-center space-x-3"
                            title="Manage printing protocols for this batch">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        <span class="font-medium">Printer Manager</span>
                    </button>';
        }

        $dropdown .= '
                </div>
            </div>
        </div>';

        return $dropdown;
    }

    /**
     * Get the next serial number for the current year
     */
    public function getNextSerial(Request $request)
    {
        $currentYear = $request->get('year', date('Y'));

        try {
            // Get all records for the current year and extract serial numbers
            // Filter by type = 'Generated' to only consider generated file numbers
            $records = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where('mlsfNo', 'like', '%-' . $currentYear . '-%')
                ->where('SOURCE', 'MLS_Commissioned')
                ->where('type', 'MlsFileNO')
                ->whereNotNull('mlsfNo')
                ->where('mlsfNo', '!=', '')
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->get();

            $maxSerial = 0;

            foreach ($records as $record) {
                if ($record->mlsfNo) {
                    // Extract serial number from patterns like: RES-2024-0001, CON-IND-42154, etc.
                    // Look for the last number in the string that could be a serial
                    if (preg_match('/-(\d+)(?:\(T\))?(?:\s+AND\s+EXTENSION)?$/', $record->mlsfNo, $matches)) {
                        $serial = (int) $matches[1];
                        if ($serial > $maxSerial) {
                            $maxSerial = $serial;
                        }
                    }
                }
            }

            $nextSerial = $maxSerial + 1;

            return response()->json(['nextSerial' => $nextSerial]);

        } catch (\Exception $e) {
            \Log::error('Error getting next serial number: ' . $e->getMessage());
            return response()->json(['nextSerial' => 1]);
        }
    }

    /**
     * Get existing file numbers for extension dropdown
     */
    public function getExistingFileNumbers()
    {
        try {
            $fileNumbers = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->select('mlsfNo')
                ->whereNotNull('mlsfNo')
                ->where('mlsfNo', '!=', '')
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->orderBy('mlsfNo', 'desc')
                ->limit(100)
                ->get();

            return response()->json($fileNumbers);

        } catch (\Exception $e) {
            \Log::error('Error getting existing file numbers: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    /**
     * Store a new MLS File number
     */
    public function store(Request $request)
    {
        // Validate required fields
        $validator = Validator::make($request->all(), [
            'file_name' => 'required|string|max:255',
            'lga' => 'required|string|max:100',
            'tracking_id' => 'nullable|string|max:100',
            'location' => 'required|string|max:255',
            'customer_type' => 'nullable|string|max:50',
            'purpose_id' => 'nullable|integer',
            'phone_no' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'rep_phone_no' => 'nullable|string|max:50',
            'rep_address' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        try {
            $fileOption = $request->file_option;
            $mlsfNo = '';

            // Debug: Log the request data
            \Log::info('FileNumber store request data:', [
                'file_option' => $request->file_option,
                'serial_no' => $request->serial_no,
                'land_use' => $request->land_use,
                'year' => $request->year,
                'file_name' => $request->file_name,
                'tracking_id' => $request->tracking_id
            ]);

            if ($fileOption === 'extension') {
                // For extensions, use the existing file number with "AND EXTENSION"
                // Handle both dropdown (existing_file_no) and manual input (existing_file_no_manual)
                $existingFileNo = $request->existing_file_no ?: $request->existing_file_no_manual;
                $mlsfNo = $existingFileNo . ' AND EXTENSION';
            } elseif ($fileOption === 'temporary') {
                // For temporary files, use the existing file number with "(T)"
                // Handle both dropdown (existing_file_no) and manual input (existing_file_no_manual)
                $existingFileNo = $request->existing_file_no ?: $request->existing_file_no_manual;
                $mlsfNo = $existingFileNo . '(T)';
            } elseif ($fileOption === 'miscellaneous') {
                // Format: MISC-KN-0203
                $mlsfNo = 'MISC-' . $request->middle_prefix . '-' . $request->serial_no;
            } elseif ($fileOption === 'sltr') {
                // Format: SLTR-0203567
                $mlsfNo = 'SLTR-' . $request->serial_no;
            } elseif ($fileOption === 'sit') {
                // Format: SIT-2025-0203567
                $mlsfNo = 'SIT-' . $request->year . '-' . $request->serial_no;
            } else {
                // Generate new file number for normal files - no padding for serial number
                $mlsfNo = $request->land_use . '-' . $request->year . '-' . $request->serial_no;
            }

            $trackingId = $this->getUniqueTrackingId($request->input('tracking_id'));

            // Only validate for duplicates (skip validation for extension and temporary files)
            if (!str_ends_with($mlsfNo, ' AND EXTENSION') && !str_ends_with($mlsfNo, '(T)')) {
                $exists = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->where('mlsfNo', $mlsfNo)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'File number already exists: ' . $mlsfNo
                    ], 409);
                }
            }

            // Insert new record - only populate mlsfNo field, leave others null
            $id = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->insertGetId([
                    'tracking_id' => $trackingId,
                    'mlsfNo' => $mlsfNo,
                    'kangisFileNo' => null,  // Leave empty
                    'NewKANGISFileNo' => null,  // Leave empty
                    'FileName' => $validatedData['file_name'],
                    'plot_no' => $request->plot_no,
                    'tp_no' => $request->tp_no,
                    'location' => $validatedData['location'],
                    'lga' => $validatedData['lga'],
                    'customer_type' => $validatedData['customer_type'],
                    'type' => 'MlsFileNO',
                    'SOURCE' => 'MLS_Commissioned',
                    'is_deleted' => 0,
                    'created_by' => (Auth::user()->first_name . ' ' . Auth::user()->last_name) ?: Auth::user()->name ?: Auth::user()->email ?: 'System',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'phone_no' => $request->phone_no,
                    'address' => $request->address,
                    'rep_phone_no' => $request->rep_phone_no,
                    'rep_address' => $request->rep_address
                ]);

            // Save to mls_file_no as well for customer_type and other details
            DB::connection('sqlsrv')->table('mls_file_no')->insert([
                'full_file_number' => $mlsfNo,
                'file_name' => $validatedData['file_name'],
                'customer_type' => $validatedData['customer_type'] ?? 'Individual',
                'purpose_id' => $request->purpose_id,
                'tracking_id' => $trackingId,
                'land_use' => $request->land_use,
                'location' => $validatedData['location'],
                'lga' => $validatedData['lga'],
                'file_option' => $fileOption,
                'source' => $fileOption === 'temporary' ? 'Temporary File' : ($fileOption === 'extension' ? 'Extension File' : 'Direct Allocation'),
                'created_at' => now(),
                'updated_at' => now(),
                'phone_no' => $request->phone_no,
                'address' => $request->address,
                'rep_phone_no' => $request->rep_phone_no,
                'rep_address' => $request->rep_address
            ]);

            return response()->json([
                'success' => true,
                'message' => 'MLS File number generated successfully: ' . $mlsfNo,
                'data' => [
                    'id' => $id,
                    'mlsfNo' => $mlsfNo,
                    'kangisFileNo' => null,
                    'NewKANGISFileNo' => null,
                    'FileName' => $request->file_name,
                    'tracking_id' => $trackingId
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error generating MLS File number: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error generating MLS File number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a captured existing file number
     */
    public function captureStore(Request $request)
    {
        // Only validate file name and that we have a generated file number
        $validator = Validator::make($request->all(), [
            'file_name' => 'required|string|max:255',
            'lga' => 'required|string|max:100',
            'tracking_id' => 'nullable|string|max:100',
            'location' => 'required|string|max:255',
            'customer_type' => 'nullable|string|max:50',
            'purpose_id' => 'nullable|integer',
            'land_use' => 'nullable|string|max:100',
            'purpose' => 'nullable|string|max:255',
            'phone_no' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        try {
            $fileOption = $request->file_option;
            $mlsfNo = '';

            // Generate the complete file number based on file option
            if ($fileOption === 'extension') {
                // Handle both dropdown (existing_file_no) and manual input (existing_file_no_manual)
                $existingFileNo = $request->existing_file_no ?: $request->existing_file_no_manual;
                $mlsfNo = $existingFileNo . ' AND EXTENSION';
            } elseif ($fileOption === 'temporary') {
                // For temporary files, use the existing file number with "(T)"
                // Handle both dropdown (existing_file_no) and manual input (existing_file_no_manual)
                $existingFileNo = $request->existing_file_no ?: $request->existing_file_no_manual;
                $mlsfNo = $existingFileNo . '(T)';
            } elseif ($fileOption === 'miscellaneous') {
                $mlsfNo = 'MISC-' . $request->middle_prefix . '-' . $request->serial_no;
            } elseif ($fileOption === 'old_mls') {
                $mlsfNo = 'KN ' . $request->serial_no;
            } elseif ($fileOption === 'sltr') {
                $mlsfNo = 'SLTR-' . $request->serial_no;
            } elseif ($fileOption === 'sit') {
                $mlsfNo = 'SIT-' . $request->year . '-' . $request->serial_no;
            } else {
                // Normal format - no padding for serial number  
                $mlsfNo = $request->prefix . '-' . $request->year . '-' . $request->serial_no;
            }

            $trackingId = $this->getUniqueTrackingId($request->input('tracking_id'));

            // Only validate for duplicates (skip validation for extension and temporary files)
            if (!str_ends_with($mlsfNo, ' AND EXTENSION') && !str_ends_with($mlsfNo, '(T)')) {
                $exists = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->where('mlsfNo', $mlsfNo)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'File number already exists: ' . $mlsfNo
                    ], 409);
                }
            }

            // Insert new record with only mlsfNo and file name
            $id = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->insertGetId([
                    'tracking_id' => $trackingId,
                    'mlsfNo' => $mlsfNo,
                    'kangisFileNo' => null,
                    'NewKANGISFileNo' => null,
                    'FileName' => $validatedData['file_name'],
                    'plot_no' => $request->plot_no,
                    'tp_no' => $request->tp_no,
                    'location' => $validatedData['location'],
                    'lga' => $validatedData['lga'],
                    'customer_type' => $validatedData['customer_type'],
                    'phone_no' => $request->phone_no,
                    'address' => $request->address,
                    'type' => 'Captured',
                    'is_deleted' => 0,
                    'created_by' => Auth::user()->name ?? Auth::user()->email ?? 'System',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

            // Save to mls_file_no as well for customer_type and other details
            DB::connection('sqlsrv')->table('mls_file_no')->insert([
                'full_file_number' => $mlsfNo,
                'file_name' => $validatedData['file_name'],
                'customer_type' => $validatedData['customer_type'] ?? 'Individual',
                'purpose_id' => $request->purpose_id,
                'tracking_id' => $trackingId,
                'land_use' => $request->land_use ?: $request->prefix,
                'location' => $validatedData['location'],
                'lga' => $validatedData['lga'],
                'phone_no' => $request->phone_no,
                'address' => $request->address,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Existing file number captured successfully: ' . $mlsfNo,
                'data' => [
                    'id' => $id,
                    'mlsfNo' => $mlsfNo,
                    'FileName' => $validatedData['file_name'],
                    'tracking_id' => $trackingId
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error capturing existing file number: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error capturing existing file number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Migrate data from CSV file (simple and efficient)
     */
    public function migrate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'excel_file' => 'required|file|mimes:csv,txt|max:20480' // Only CSV files, 20MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please upload a valid CSV file. Max size: 20MB',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Increase memory limit and execution time for large files
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', 300); // 5 minutes

            $file = $request->file('excel_file');
            $filePath = $file->getPathname();

            \Log::info("CSV Migration started for file: " . $file->getClientOriginalName());

            $imported = 0;
            $duplicates = 0;
            $errors = 0;
            $batchSize = 100;
            $batch = [];
            $rowNumber = 0;

            // Get existing records to check for duplicates
            $existingMlsfNos = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->whereNotNull('mlsfNo')
                ->where('mlsfNo', '!=', '')
                ->pluck('mlsfNo')
                ->toArray();

            $existingKangisNos = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->whereNotNull('kangisFileNo')
                ->where('kangisFileNo', '!=', '')
                ->pluck('kangisFileNo')
                ->toArray();

            $existingNewKangisNos = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->whereNotNull('NewKANGISFileNo')
                ->where('NewKANGISFileNo', '!=', '')
                ->pluck('NewKANGISFileNo')
                ->toArray();

            // Open and read CSV file
            if (($handle = fopen($filePath, 'r')) !== FALSE) {

                // Read header row to understand column structure
                $header = fgetcsv($handle, 1000, ',');

                if (!$header) {
                    throw new \Exception('Could not read CSV header row');
                }

                \Log::info("CSV Header: " . implode(', ', $header));

                // Find column indexes (case insensitive)
                $mlsfNoIndex = -1;
                $kangisFileIndex = -1;
                $newKangisFileNoIndex = -1;
                $fileNameIndex = -1;

                foreach ($header as $index => $column) {
                    $column = strtolower(trim($column));
                    if (in_array($column, ['mlsfno', 'mls_file_no', 'mlsfileno'])) {
                        $mlsfNoIndex = $index;
                    } elseif (in_array($column, ['kangisfile', 'kangis_file', 'kangisfileno'])) {
                        $kangisFileIndex = $index;
                    } elseif (in_array($column, ['newkangisfileno', 'new_kangis_file_no', 'newkangisfile'])) {
                        $newKangisFileNoIndex = $index;
                    } elseif (in_array($column, ['filename', 'file_name', 'name'])) {
                        $fileNameIndex = $index;
                    }
                }

                \Log::info("Column mapping - mlsfNo: {$mlsfNoIndex}, kangisFile: {$kangisFileIndex}, newKangisFileNo: {$newKangisFileNoIndex}, fileName: {$fileNameIndex}");

                // Process each data row
                while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    $rowNumber++;

                    try {
                        // Skip empty rows
                        if (empty(array_filter($row))) {
                            continue;
                        }

                        // Extract data based on column indexes
                        $mlsfNo = trim($row[$mlsfNoIndex] ?? '');
                        $kangisFileNo = trim($row[$kangisFileIndex] ?? '');
                        $newKangisFileNo = trim($row[$newKangisFileNoIndex] ?? '');
                        $fileName = trim($row[$fileNameIndex] ?? '');

                        // Skip if all essential data is missing
                        if (empty($mlsfNo) && empty($kangisFileNo) && empty($newKangisFileNo)) {
                            continue;
                        }

                        // Check for duplicates
                        $isDuplicate = false;
                        if (!empty($mlsfNo) && in_array($mlsfNo, $existingMlsfNos)) {
                            $isDuplicate = true;
                        } elseif (!empty($kangisFileNo) && in_array($kangisFileNo, $existingKangisNos)) {
                            $isDuplicate = true;
                        } elseif (!empty($newKangisFileNo) && in_array($newKangisFileNo, $existingNewKangisNos)) {
                            $isDuplicate = true;
                        }

                        if ($isDuplicate) {
                            $duplicates++;
                            continue;
                        }

                        // Add to batch
                        $batch[] = [
                            'tracking_id' => $this->getUniqueTrackingId(),
                            'mlsfNo' => !empty($mlsfNo) ? $mlsfNo : null,
                            'kangisFileNo' => !empty($kangisFileNo) ? $kangisFileNo : null,
                            'NewKANGISFileNo' => !empty($newKangisFileNo) ? $newKangisFileNo : null,
                            'FileName' => !empty($fileName) ? $fileName : null,
                            'type' => 'Migrated',
                            'is_deleted' => 0,
                            'created_by' => 'Migrated',
                            'created_at' => now(),
                            'updated_at' => now()
                        ];

                        // Update existing arrays to prevent duplicates within the same import
                        if (!empty($mlsfNo))
                            $existingMlsfNos[] = $mlsfNo;
                        if (!empty($kangisFileNo))
                            $existingKangisNos[] = $kangisFileNo;
                        if (!empty($newKangisFileNo))
                            $existingNewKangisNos[] = $newKangisFileNo;

                        // Insert batch when it reaches the batch size
                        if (count($batch) >= $batchSize) {
                            DB::connection('sqlsrv')->table('fileNumber')->insert($batch);
                            $imported += count($batch);
                            $batch = [];

                            // Log progress every 1000 records
                            if ($imported % 1000 == 0) {
                                \Log::info("Migration progress: {$imported} records imported");
                            }
                        }

                    } catch (\Exception $e) {
                        \Log::error("Error importing CSV row {$rowNumber}: " . $e->getMessage());
                        $errors++;
                    }
                }

                // Insert remaining batch
                if (!empty($batch)) {
                    DB::connection('sqlsrv')->table('fileNumber')->insert($batch);
                    $imported += count($batch);
                }

                fclose($handle);

            } else {
                throw new \Exception('Could not open CSV file for reading');
            }

            // Clean up memory
            unset($existingMlsfNos, $existingKangisNos, $existingNewKangisNos, $batch);

            \Log::info("CSV Migration completed: {$imported} imported, {$duplicates} duplicates, {$errors} errors");

            return response()->json([
                'success' => true,
                'message' => "CSV migration completed successfully! Imported: {$imported}, Duplicates skipped: {$duplicates}, Errors: {$errors}",
                'data' => [
                    'imported' => $imported,
                    'duplicates' => $duplicates,
                    'errors' => $errors,
                    'total_processed' => $imported + $duplicates + $errors,
                    'rows_processed' => $rowNumber
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error during CSV migration: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error during CSV migration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a specific record
     */
    public function show($id)
    {
        try {
            $record = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->leftJoin('mls_file_no', 'fileNumber.mlsfNo', '=', 'mls_file_no.full_file_number')
                ->select([
                    'fileNumber.*',
                    // Coalesce crucial fields to ensure data availability
                    DB::raw('COALESCE(fileNumber.lga, mls_file_no.lga) as lga'),
                    DB::raw('COALESCE(fileNumber.FileName, mls_file_no.file_name) as FileName'),
                    DB::raw('COALESCE(fileNumber.plot_no, mls_file_no.plot_no) as plot_no'),
                    DB::raw('COALESCE(fileNumber.tp_no, mls_file_no.tp_no) as tp_no'),
                    DB::raw('COALESCE(fileNumber.location, mls_file_no.location) as location'),
                    DB::raw('COALESCE(fileNumber.district, mls_file_no.district) as district'),
                    'mls_file_no.customer_type',
                    'mls_file_no.purpose_id'
                ])
                ->where('fileNumber.id', $id)
                ->where(function ($q) {
                    $q->whereNull('fileNumber.is_deleted')->orWhere('fileNumber.is_deleted', 0);
                })
                ->first();

            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }

            return response()->json($record);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a record (file name, KANGIS file number, and New KANGIS file number can be updated)
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'file_name' => 'required|string|max:255',
            'kangis_file_no' => 'nullable|string|max:255',
            'new_kangis_file_no' => 'nullable|string|max:255',
            'lga' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'plot_no' => 'nullable|string|max:100',
            'tp_no' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'customer_type' => 'nullable|string|max:50',
            'purpose_id' => 'nullable|integer',
            'phone_no' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'rep_phone_no' => 'nullable|string|max:50',
            'rep_address' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $record = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where('id', $id)
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->first();

            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }

            // Prepare update data
            $updateData = [
                'FileName' => $request->file_name,
                'updated_by' => Auth::user()->name ?? Auth::user()->email ?? 'System',
                'updated_at' => now()
            ];

            // Add fields if provided
            if ($request->has('kangis_file_no'))
                $updateData['kangisFileNo'] = $request->kangis_file_no;
            if ($request->has('new_kangis_file_no'))
                $updateData['NewKANGISFileNo'] = $request->new_kangis_file_no;
            if ($request->has('lga'))
                $updateData['lga'] = $request->lga;
            if ($request->has('district'))
                $updateData['district'] = $request->district;
            if ($request->has('plot_no'))
                $updateData['plot_no'] = $request->plot_no;
            if ($request->has('tp_no'))
                $updateData['tp_no'] = $request->tp_no;
            if ($request->has('location'))
                $updateData['location'] = $request->location;

            // purpose_id and customer_type are usually in mls_file_no, but check if they're in fileNumber too
            if ($request->has('purpose_id') && \Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('fileNumber', 'purpose_id')) {
                $updateData['purpose_id'] = $request->purpose_id;
            }
            if ($request->has('customer_type') && \Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('fileNumber', 'customer_type')) {
                $updateData['customer_type'] = $request->customer_type;
            }

            // Handle phone_no dynamically
            if ($request->has('phone_no')) {
                if (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('fileNumber', 'phone_no')) {
                    $updateData['phone_no'] = $request->phone_no;
                } elseif (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('fileNumber', 'PhoneNo')) {
                    $updateData['PhoneNo'] = $request->phone_no;
                }
            }

            // Other new fields
            if ($request->has('address')) {
                $updateData['address'] = $request->address;
            }
            if ($request->has('rep_phone_no')) {
                $updateData['rep_phone_no'] = $request->rep_phone_no;
            }
            if ($request->has('rep_address')) {
                $updateData['rep_address'] = $request->rep_address;
            }

            // Update the record in fileNumber
            DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where('id', $id)
                ->update($updateData);

            // Also sync to mls_file_no if mlsfNo is present
            if ($record->mlsfNo) {
                $mlsUpdateData = [];
                if (isset($updateData['FileName']))
                    $mlsUpdateData['file_name'] = $updateData['FileName'];
                if (isset($updateData['lga']))
                    $mlsUpdateData['lga'] = $updateData['lga'];
                if (isset($updateData['district']))
                    $mlsUpdateData['district'] = $updateData['district'];
                if (isset($updateData['plot_no']))
                    $mlsUpdateData['plot_no'] = $updateData['plot_no'];
                if (isset($updateData['tp_no']))
                    $mlsUpdateData['tp_no'] = $updateData['tp_no'];
                if (isset($updateData['location']))
                    $mlsUpdateData['location'] = $updateData['location'];

                if ($request->has('purpose_id'))
                    $mlsUpdateData['purpose_id'] = $request->purpose_id;

                if ($request->has('customer_type'))
                    $mlsUpdateData['customer_type'] = $request->customer_type;

                if ($request->has('phone_no')) {
                    if (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'phone_no')) {
                        $mlsUpdateData['phone_no'] = $request->phone_no;
                    } elseif (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'PhoneNo')) {
                        $mlsUpdateData['PhoneNo'] = $request->phone_no;
                    }
                }

                if ($request->has('address')) {
                    $mlsUpdateData['address'] = $request->address;
                }
                if ($request->has('rep_phone_no')) {
                    $mlsUpdateData['rep_phone_no'] = $request->rep_phone_no;
                }
                if ($request->has('rep_address')) {
                    $mlsUpdateData['rep_address'] = $request->rep_address;
                }

                if (!empty($mlsUpdateData)) {
                    DB::connection('sqlsrv')
                        ->table('mls_file_no')
                        ->where('full_file_number', $record->mlsfNo)
                        ->update($mlsUpdateData);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Record updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified file number record and cascade delete from related tables.
     */
    public function destroy($id)
    {
        if (!Auth::user() || Auth::user()->assign_role !== 'Supper Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action. Only Supper Admin can execute a Master Delete.'
            ], 403);
        }

        $fileRecord = DB::connection('sqlsrv')->table('fileNumber')->where('id', $id)->first();
        if (!$fileRecord) {
            return response()->json([
                'success' => false,
                'message' => 'File number record not found.'
            ], 404);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $result = $this->cascadeDeleteFileRecord($id, $fileRecord);

            $this->forgetFileNumberCaches();
            $this->writeMasterDeleteAudit($id, $result);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'MLS record and all associated staging/indexing records deleted successfully from all 5 systems.',
                'details' => [
                    'fileNumber_deleted' => $result['counts']['fileNumber'],
                    'mls_file_no_deleted' => $result['counts']['mls_file_no'],
                    'entities_staging_deleted' => $result['counts']['entities_staging'],
                    'customers_staging_deleted' => $result['counts']['customers_staging'],
                    'file_indexings_deleted' => $result['counts']['file_indexings'],
                ]
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            \Log::error('Error executing master delete for MLS file ID ' . $id . ': ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk master delete for multiple MLS file records.
     * All deletes execute inside ONE transaction — any failure rolls back every record.
     */
    public function bulkDestroy(Request $request)
    {
        if (!Auth::user() || Auth::user()->assign_role !== 'Supper Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action. Only Supper Admin can execute a Master Delete.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ids'   => 'required|array|min:1|max:200',
            'ids.*' => 'required|integer|min:1',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request: ' . $validator->errors()->first(),
            ], 422);
        }

        $ids = array_values(array_unique(array_map('intval', $request->input('ids'))));

        $records = DB::connection('sqlsrv')->table('fileNumber')->whereIn('id', $ids)->get()->keyBy('id');
        $missingIds = array_values(array_diff($ids, $records->keys()->all()));

        if ($records->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No matching file records found for the provided IDs.',
                'missing_ids' => $missingIds,
            ], 404);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $totals = [
                'fileNumber' => 0, 'mls_file_no' => 0, 'entities_staging' => 0,
                'customers_staging' => 0, 'file_indexings' => 0,
            ];
            $perRecord = [];

            foreach ($records as $id => $fileRecord) {
                $result = $this->cascadeDeleteFileRecord($id, $fileRecord);
                foreach ($totals as $k => $_) {
                    $totals[$k] += $result['counts'][$k];
                }
                $this->writeMasterDeleteAudit($id, $result, true);
                $perRecord[] = [
                    'id' => $id,
                    'mlsfNo' => $result['snapshot']['mlsfNo'],
                ];
            }

            $this->forgetFileNumberCaches();
            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => count($records) . ' MLS record(s) deleted successfully across all 5 systems.',
                'deleted_count' => count($records),
                'missing_ids' => $missingIds,
                'records' => $perRecord,
                'totals' => $totals,
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            \Log::error('Error executing bulk master delete for MLS file IDs: ' . $e->getMessage(), [
                'ids' => $ids,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk delete failed and was rolled back: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Execute the 5-table cascade delete for a single fileNumber row.
     * Caller is responsible for transaction management.
     */
    private function cascadeDeleteFileRecord($id, $fileRecord): array
    {
        $mlsfNo          = trim($fileRecord->mlsfNo ?? '');
        $trackingId      = trim($fileRecord->tracking_id ?? '');
        $kangisFileNo    = trim($fileRecord->kangisFileNo ?? '');
        $newKangisFileNo = trim($fileRecord->NewKANGISFileNo ?? '');
        $stFileNo        = trim($fileRecord->st_file_no ?? '');

        // 1. Delete from mls_file_no
        $deletedMlsFileNo = 0;
        if ($mlsfNo !== '') {
            $deletedMlsFileNo += DB::connection('sqlsrv')->table('mls_file_no')
                ->where('full_file_number', $mlsfNo)->delete();
        }
        if ($trackingId !== '') {
            $deletedMlsFileNo += DB::connection('sqlsrv')->table('mls_file_no')
                ->where('tracking_id', $trackingId)->delete();
        }

        $fileNumbersForStaging = array_unique(array_filter([$mlsfNo, $kangisFileNo, $newKangisFileNo, $stFileNo]));

        // 2. entities_staging
        $deletedEntities = 0;
        if (!empty($fileNumbersForStaging)) {
            $deletedEntities = DB::connection('sqlsrv')->table('entities_staging')
                ->whereIn('file_number', $fileNumbersForStaging)->delete();
        }

        // 3. customers_staging
        $deletedCustomers = 0;
        if (!empty($fileNumbersForStaging)) {
            $deletedCustomers = DB::connection('sqlsrv')->table('customers_staging')
                ->whereIn('file_number', $fileNumbersForStaging)->delete();
        }

        // 4. file_indexings (+ children)
        $deletedIndexings = 0;
        $fileIndexingIds = [];
        if (!empty($fileNumbersForStaging)) {
            $fileIndexingIds = DB::connection('sqlsrv')->table('file_indexings')
                ->whereIn('file_number', $fileNumbersForStaging)->pluck('id')->toArray();
        }
        if ($trackingId !== '') {
            $moreIds = DB::connection('sqlsrv')->table('file_indexings')
                ->where('tracking_id', $trackingId)->pluck('id')->toArray();
            $fileIndexingIds = array_unique(array_merge($fileIndexingIds, $moreIds));
        }
        if (!empty($fileIndexingIds)) {
            DB::connection('sqlsrv')->table('scannings')
                ->whereIn('file_indexing_id', $fileIndexingIds)->delete();
            DB::connection('sqlsrv')->table('pagetypings')
                ->whereIn('file_indexing_id', $fileIndexingIds)->delete();
            DB::connection('sqlsrv')->table('print_label_batch_items')
                ->whereIn('file_indexing_id', $fileIndexingIds)->delete();
            $deletedIndexings = DB::connection('sqlsrv')->table('file_indexings')
                ->whereIn('id', $fileIndexingIds)->delete();
        }

        // 5. fileNumber
        $deletedFileNumbers = DB::connection('sqlsrv')->table('fileNumber')
            ->where('id', $id)->delete();
        if ($mlsfNo !== '') {
            $deletedFileNumbers += DB::connection('sqlsrv')->table('fileNumber')
                ->where('mlsfNo', $mlsfNo)->delete();
        }

        return [
            'snapshot' => [
                'id' => $id,
                'mlsfNo' => $mlsfNo,
                'tracking_id' => $trackingId,
                'kangisFileNo' => $kangisFileNo,
                'NewKANGISFileNo' => $newKangisFileNo,
                'st_file_no' => $stFileNo,
                'FileName' => $fileRecord->FileName ?? null,
            ],
            'counts' => [
                'fileNumber' => $deletedFileNumbers,
                'mls_file_no' => $deletedMlsFileNo,
                'entities_staging' => $deletedEntities,
                'customers_staging' => $deletedCustomers,
                'file_indexings' => $deletedIndexings,
            ],
        ];
    }

    private function forgetFileNumberCaches(): void
    {
        Cache::forget('mls_fileno_page_stats');
        Cache::forget('file_numbers_total_v2_New');
        Cache::forget('file_numbers_total_v2_All');
        Cache::forget('file_numbers_total_v2_Captured');
    }

    private function writeMasterDeleteAudit($id, array $result, bool $isBulk = false): void
    {
        try {
            $c = $result['counts'];
            $prefix = $isBulk ? 'Bulk Master Delete' : 'Master Delete';
            app(\App\Services\AuditService::class)->logAction(
                'DELETED',
                'mls_file_record',
                $id,
                $result['snapshot'],
                null,
                "{$prefix} executed for MLS File Record ID {$id}. Affected tables: fileNumber ({$c['fileNumber']} rows), mls_file_no ({$c['mls_file_no']} rows), entities_staging ({$c['entities_staging']} rows), customers_staging ({$c['customers_staging']} rows), file_indexings ({$c['file_indexings']} rows)."
            );
        } catch (\Exception $auditEx) {
            \Log::warning("AuditLog failed during MLS master delete: " . $auditEx->getMessage());
        }
    }

    /**
     * Get total count of file numbers
     */
    public function getCount()
    {
        try {
            $count = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->count();

            return response()->json(['count' => $count]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting count: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear file numbers cache
     */
    public function clearCache()
    {
        try {
            Cache::forget('file_numbers_total_count_New');
            Cache::forget('file_numbers_total_count_Captured');

            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error clearing cache: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test database connection and table structure
     */
    public function testDatabase()
    {
        try {
            // Test connection
            $connectionTest = DB::connection('sqlsrv')->getPdo();

            // Test table existence
            $tableExists = DB::connection('sqlsrv')
                ->select("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'fileNumber'");

            // Get table structure
            $columns = DB::connection('sqlsrv')
                ->select("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'fileNumber'");

            // Get record count
            $recordCount = 0;
            $sampleRecords = [];

            if ($tableExists[0]->count > 0) {
                $recordCount = DB::connection('sqlsrv')->table('fileNumber')->count();
                $sampleRecords = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->limit(5)
                    ->get()
                    ->toArray();
            }

            return response()->json([
                'success' => true,
                'connection' => 'Connected successfully',
                'table_exists' => $tableExists[0]->count > 0,
                'columns' => $columns,
                'record_count' => $recordCount,
                'sample_records' => $sampleRecords,
                'database_name' => DB::connection('sqlsrv')->getDatabaseName(),
                'server_info' => DB::connection('sqlsrv')->select('SELECT @@VERSION as version')[0]->version ?? 'Unknown'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Global API: Search file numbers (Top 10 + Search functionality)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function getUniqueTrackingId(?string $preferred = null): string
    {
        $preferred = $preferred ? strtoupper(trim($preferred)) : null;
        $attempts = 0;

        do {
            $candidate = $preferred && $attempts === 0 ? $preferred : $this->generateTrackingId();

            if (isset($this->generatedTrackingIds[$candidate])) {
                $preferred = null;
                $attempts++;
                continue;
            }

            $exists = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where('tracking_id', $candidate)
                ->exists();

            if (!$exists) {
                $this->generatedTrackingIds[$candidate] = true;
                return $candidate;
            }

            $preferred = null;
            $attempts++;
        } while ($attempts < 10);

        throw new \RuntimeException('Unable to generate a unique tracking ID after multiple attempts.');
    }

    private function generateTrackingId(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $segmentOne = '';
        $segmentTwo = '';
        $length = strlen($characters) - 1;

        for ($i = 0; $i < 8; $i++) {
            $segmentOne .= $characters[random_int(0, $length)];
        }

        for ($i = 0; $i < 5; $i++) {
            $segmentTwo .= $characters[random_int(0, $length)];
        }

        return "TRK-{$segmentOne}-{$segmentTwo}";
    }

    public function searchFileNumbers(Request $request)
    {
        try {
            \Log::info('FileNumber search API called', [
                'query' => $request->input('query', ''),
                'limit' => $request->input('limit', 10),
                'page' => $request->input('page', 1)
            ]);

            $query = $request->input('query', '');
            $limit = $request->input('limit', 10);
            $page = $request->input('page', 1);

            // Test database connection first
            try {
                \DB::connection('sqlsrv')->getPdo();
                \Log::info('SQL Server connection successful');
            } catch (\Exception $connException) {
                \Log::error('SQL Server connection failed', ['error' => $connException->getMessage()]);
                return response()->json([
                    'success' => false,
                    'error' => 'Database connection failed',
                    'message' => 'Unable to connect to SQL Server: ' . $connException->getMessage(),
                    'timestamp' => now()->toISOString()
                ], 500);
            }

            // Build the query
            $fileQuery = FileNumber::active()
                ->select([
                    'id',
                    'kangisFileNo',
                    'mlsfNo',
                    'NewKANGISFileNo',
                    'FileName',
                    'tracking_id',
                    'decommissioning_reason',
                    'created_at',
                    'updated_at'
                ])
                ->orderBy('created_at', 'desc');

            // If query provided, search across relevant fields
            if (!empty($query)) {
                $fileQuery->where(function ($q) use ($query) {
                    $q->where('kangisFileNo', 'LIKE', "%{$query}%")
                        ->orWhere('mlsfNo', 'LIKE', "%{$query}%")
                        ->orWhere('NewKANGISFileNo', 'LIKE', "%{$query}%")
                        ->orWhere('FileName', 'LIKE', "%{$query}%");
                });
            }

            // Get total count for pagination
            $totalCount = $fileQuery->count();
            \Log::info('Total file numbers found', ['count' => $totalCount]);

            // Apply pagination
            $offset = ($page - 1) * $limit;
            $results = $fileQuery->skip($offset)->take($limit)->get();
            \Log::info('Retrieved paginated results', ['count' => $results->count()]);

            // Format results for consistent API response
            $formattedResults = $results->map(function ($file) {
                return [
                    'id' => $file->id,
                    'kangis_file_no' => $file->kangisFileNo,
                    'mlsf_no' => $file->mlsfNo,
                    'new_kangis_file_no' => $file->NewKANGISFileNo,
                    'file_name' => $file->FileName,
                    'tracking_id' => $file->tracking_id,
                    'display_name' => $this->formatDisplayName($file),
                    'search_text' => $this->formatSearchText($file),
                    'status' => 'Active',
                    'decommissioning_reason' => $file->decommissioning_reason,
                    'created_at' => $file->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $file->updated_at?->format('Y-m-d H:i:s')
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedResults,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $totalCount,
                    'total_pages' => ceil($totalCount / $limit),
                    'has_more' => ($page * $limit) < $totalCount
                ],
                'query' => $query,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            \Log::error('FileNumber search API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to search file numbers',
                'message' => $e->getMessage(),
                'debug_info' => [
                    'line' => $e->getLine(),
                    'file' => basename($e->getFile())
                ],
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Global API: Get top 10 recent active file numbers
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTopFileNumbers()
    {
        try {
            \Log::info('FileNumber getTopFileNumbers API called');

            $topFiles = FileNumber::active()
                ->select([
                    'id',
                    'kangisFileNo',
                    'mlsfNo',
                    'NewKANGISFileNo',
                    'FileName',
                    'created_at',
                    'updated_at'
                ])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            \Log::info('Retrieved top file numbers', ['count' => $topFiles->count()]);

            $formattedResults = $topFiles->map(function ($file) {
                return [
                    'id' => $file->id,
                    'kangis_file_no' => $file->kangisFileNo,
                    'mlsf_no' => $file->mlsfNo,
                    'new_kangis_file_no' => $file->NewKANGISFileNo,
                    'file_name' => $file->FileName,
                    'display_name' => $this->formatDisplayName($file),
                    'search_text' => $this->formatSearchText($file),
                    'status' => 'Active',
                    'created_at' => $file->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $file->updated_at?->format('Y-m-d H:i:s')
                ];
            });

        } catch (\Exception $connException) {
            \Log::error('SQL Server connection failed in getTopFileNumbers, using mock data', ['error' => $connException->getMessage()]);

            // Return mock data when database is unavailable
            $formattedResults = collect([
                [
                    'id' => 1,
                    'kangis_file_no' => 'KLA/2024/001',
                    'mlsf_no' => 'MLSF-2024-001',
                    'new_kangis_file_no' => 'NKLA/2024/001',
                    'file_name' => 'Victoria Island Property',
                    'display_name' => 'KLA/2024/001 - Victoria Island Property',
                    'search_text' => 'KLA/2024/001 MLSF-2024-001 NKLA/2024/001 Victoria Island Property',
                    'status' => 'Active',
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now()->format('Y-m-d H:i:s')
                ],
                [
                    'id' => 2,
                    'kangis_file_no' => 'COM/2024/001',
                    'mlsf_no' => 'MLSF-COM-2024-001',
                    'new_kangis_file_no' => 'NCOM/2024/001',
                    'file_name' => 'Commercial Plaza Lagos',
                    'display_name' => 'COM/2024/001 - Commercial Plaza Lagos',
                    'search_text' => 'COM/2024/001 MLSF-COM-2024-001 NCOM/2024/001 Commercial Plaza Lagos',
                    'status' => 'Active',
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now()->format('Y-m-d H:i:s')
                ],
                [
                    'id' => 3,
                    'kangis_file_no' => 'RES/2024/001',
                    'mlsf_no' => 'MLSF-RES-2024-001',
                    'new_kangis_file_no' => 'NRES/2024/001',
                    'file_name' => 'Residential Estate Abuja',
                    'tracking_id' => 'TRK-20240101-ABCD',
                    'display_name' => 'RES/2024/001 - Residential Estate Abuja',
                    'search_text' => 'TRK-20240101-ABCD RES/2024/001 MLSF-RES-2024-001 NRES/2024/001 Residential Estate Abuja',
                    'status' => 'Active',
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now()->format('Y-m-d H:i:s')
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $formattedResults,
            'count' => $formattedResults->count(),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Global API: Get file number details by ID
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFileNumberDetails($id)
    {
        try {
            $file = FileNumber::active()
                ->select([
                    'id',
                    'kangisFileNo',
                    'mlsfNo',
                    'NewKANGISFileNo',
                    'FileName',
                    'tracking_id',
                    'decommissioning_reason',
                    'created_by',
                    'updated_by',
                    'location',
                    'SOURCE',
                    'commissioning_date',
                    'created_at',
                    'updated_at'
                ])
                ->find($id);

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'error' => 'File number not found',
                    'timestamp' => now()->toISOString()
                ], 404);
            }

            $formattedFile = [
                'id' => $file->id,
                'kangis_file_no' => $file->kangisFileNo,
                'mlsf_no' => $file->mlsfNo,
                'new_kangis_file_no' => $file->NewKANGISFileNo,
                'file_name' => $file->FileName,
                'tracking_id' => $file->tracking_id,
                'display_name' => $this->formatDisplayName($file),
                'search_text' => $this->formatSearchText($file),
                'status' => 'Active',
                'decommissioning_reason' => $file->decommissioning_reason,
                'created_by' => $file->created_by,
                'updated_by' => $file->updated_by,
                'location' => $file->location,
                'source' => $file->SOURCE,
                'commissioning_date' => $file->commissioning_date?->format('Y-m-d H:i:s'),
                'created_at' => $file->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $file->updated_at?->format('Y-m-d H:i:s')
            ];

            return response()->json([
                'success' => true,
                'data' => $formattedFile,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch file number details',
                'message' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Helper method to format display name for file number
     * 
     * @param FileNumber $file
     * @return string
     */
    private function formatDisplayName($file)
    {
        // Helper function to check if a value is valid (not null, empty, or N/A)
        $isValidValue = function ($value) {
            return $value &&
                trim($value) !== '' &&
                strtoupper(trim($value)) !== 'N/A' &&
                strtoupper(trim($value)) !== 'NULL';
        };

        $validFileNumbers = array_filter([
            $file->kangisFileNo,
            $file->mlsfNo,
            $file->NewKANGISFileNo
        ], $isValidValue);

        // Use array_values to reindex and check if array has elements
        $validFileNumbers = array_values($validFileNumbers);
        $primaryNumber = !empty($validFileNumbers) ? $validFileNumbers[0] : 'N/A';

        // Only add file name if it's valid
        $fileName = $isValidValue($file->FileName) ? ' - ' . $file->FileName : '';

        // Only add tracking ID if it's valid
        $trackingSuffix = $isValidValue($file->tracking_id) ? ' [' . $file->tracking_id . ']' : '';

        return $primaryNumber . $fileName . $trackingSuffix;
    }

    /**
     * Helper method to format search text for file number
     * 
     * @param FileNumber $file
     * @return string
     */
    private function formatSearchText($file)
    {
        // Helper function to check if a value is valid (not null, empty, or N/A)
        $isValidValue = function ($value) {
            return $value &&
                trim($value) !== '' &&
                strtoupper(trim($value)) !== 'N/A' &&
                strtoupper(trim($value)) !== 'NULL';
        };

        return implode(' ', array_filter([
            $file->tracking_id,
            $file->kangisFileNo,
            $file->mlsfNo,
            $file->NewKANGISFileNo,
            $file->FileName
        ], $isValidValue));
    }

    /**
     * Generate "Application for Conversion" document
     */
    public function generateConversionApplication(Request $request, $id)
    {
        try {
            $record = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->select([
                    'fileNumber.*',
                    DB::raw("(SELECT TOP 1 land_use FROM mls_file_no WHERE full_file_number = fileNumber.mlsfNo ORDER BY id DESC) as land_use_derived"),
                    DB::raw("(SELECT TOP 1 lga FROM mls_file_no WHERE full_file_number = fileNumber.mlsfNo ORDER BY id DESC) as lga_derived"),
                    DB::raw("(SELECT TOP 1 created_by FROM mls_file_no WHERE full_file_number = fileNumber.mlsfNo ORDER BY id DESC) as created_by_derived"),
                    'mls_file_no.batch_no'
                ])
                ->leftJoin('mls_file_no', 'fileNumber.mlsfNo', '=', 'mls_file_no.full_file_number')
                ->where('fileNumber.id', $id)
                ->first();

            if (!$record) {
                return abort(404, 'Record not found');
            }

            // Wrap in collection for template consistency
            $records = collect([$record]);

            // Capture acquisition method from request
            $acquisitionMethod = $request->query('method');
            $specifyOther = $request->query('other');

            // Check for existing Serial Number
            $existingApp = DB::connection('sqlsrv')
                ->table('conversion_applications')
                ->where('mls_file_no_id', $record->id)
                ->first();

            if ($existingApp && $existingApp->serial_no) {
                $serialNo = $existingApp->serial_no;
            } else {
                // Generate new Serial Number
                $maxSerial = DB::connection('sqlsrv')->table('conversion_applications')->max('serial_no');
                $serialNo = $maxSerial ? $maxSerial + 1 : 1;

                // Record the generation
                DB::connection('sqlsrv')
                    ->table('conversion_applications')
                    ->insert([
                        'mls_file_no_id' => $record->id,
                        'tracking_id' => $record->tracking_id,
                        'full_file_number' => $record->mlsfNo,
                        'serial_no' => $serialNo,
                        'generated_by' => (Auth::user()->first_name . ' ' . Auth::user()->last_name) ?: Auth::user()->name ?: Auth::user()->email ?: 'System',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
            }

            $record->serial_no = $serialNo;

            // Determine watermark text based on print count
            $printCount = DB::connection('sqlsrv')->table('print_logs')
                ->where('reference_number', $record->mlsfNo) // Use full file number as reference
                ->where('document_type', 'Application for Conversion')
                ->count();

            $watermarkText = ($printCount > 1) ? 'CERTIFIED TRUE COPY' : 'ORIGINAL';

            return view('generate_fileno.application_for_conversion', compact('records', 'acquisitionMethod', 'specifyOther', 'watermarkText'));

        } catch (\Exception $e) {
            \Log::error('Error generating Conversion Application: ' . $e->getMessage());
            return abort(500, 'Error generating document: ' . $e->getMessage());
        }
    }

    /**
     * Generate batch "Application for Conversion" documents
     */
    public function generateBatchConversionApplication(Request $request, $batchNo)
    {
        try {
            $records = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->select([
                    'fileNumber.*',
                    DB::raw("(SELECT TOP 1 land_use FROM mls_file_no WHERE full_file_number = fileNumber.mlsfNo ORDER BY id DESC) as land_use_derived"),
                    DB::raw("(SELECT TOP 1 lga FROM mls_file_no WHERE full_file_number = fileNumber.mlsfNo ORDER BY id DESC) as lga_derived"),
                    DB::raw("(SELECT TOP 1 created_by FROM mls_file_no WHERE full_file_number = fileNumber.mlsfNo ORDER BY id DESC) as created_by_derived"),
                    'mls_file_no.batch_no'
                ])
                ->leftJoin('mls_file_no', 'fileNumber.mlsfNo', '=', 'mls_file_no.full_file_number')
                ->where('mls_file_no.batch_no', $batchNo)
                ->where(function ($q) {
                    $q->whereNull('fileNumber.is_deleted')->orWhere('fileNumber.is_deleted', 0);
                })
                ->get();

            if ($records->isEmpty()) {
                return abort(404, 'No records found for this batch');
            }

            // Capture acquisition method from request
            $acquisitionMethod = $request->query('method');
            $specifyOther = $request->query('other');

            $generateBy = (Auth::user()->first_name . ' ' . Auth::user()->last_name) ?: Auth::user()->name ?: Auth::user()->email ?: 'System';

            // Get current max serial to increment efficiently
            $currentMax = DB::connection('sqlsrv')->table('conversion_applications')->max('serial_no') ?? 0;
            $nextSerial = $currentMax + 1;

            // 1. Pre-fetch existing conversion applications in bulk
            $fileNumberIds = $records->pluck('id')->toArray();
            $existingApps = DB::connection('sqlsrv')
                ->table('conversion_applications')
                ->whereIn('mls_file_no_id', $fileNumberIds)
                ->get()
                ->keyBy('mls_file_no_id');

            // 2. Pre-fetch print counts in bulk to avoid per-record queries if possible
            // For now, individual count is okay if limited, but bulk is better
            $mlsfNumbers = $records->pluck('mlsfNo')->toArray();
            $printCounts = DB::connection('sqlsrv')->table('print_logs')
                ->whereIn('reference_number', $mlsfNumbers)
                ->where('document_type', 'Application for Conversion')
                ->select('reference_number', DB::raw('count(*) as total'))
                ->groupBy('reference_number')
                ->get()
                ->keyBy('reference_number');

            $conversionData = [];
            foreach ($records as $record) {
                $existingApp = $existingApps->get($record->id);

                if ($existingApp && $existingApp->serial_no) {
                    $record->serial_no = $existingApp->serial_no;
                } else {
                    // Assign new serial
                    $record->serial_no = $nextSerial;

                    $conversionData[] = [
                        'mls_file_no_id' => $record->id,
                        'tracking_id' => $record->tracking_id,
                        'full_file_number' => $record->mlsfNo,
                        'serial_no' => $record->serial_no,
                        'generated_by' => $generateBy,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    $nextSerial++;
                }

                // Determine watermark text based on pre-fetched print count
                $count = isset($printCounts[$record->mlsfNo]) ? $printCounts[$record->mlsfNo]->total : 0;
                $record->watermarkText = ($count > 900) ? 'CERTIFIED TRUE COPY' : 'ORIGINAL';
            }

            // 3. Bulk insert new conversion applications
            if (!empty($conversionData)) {
                DB::connection('sqlsrv')->table('conversion_applications')->insert($conversionData);
            }

            // Determine watermark text based on print count for the batch
            $printCount = DB::connection('sqlsrv')->table('print_logs')
                ->where('reference_number', $batchNo)
                ->where('document_type', 'Application for Conversion')
                ->count();

            $watermarkText = ($printCount > 900) ? 'CERTIFIED TRUE COPY' : 'ORIGINAL';

            return view('generate_fileno.application_for_conversion', compact('records', 'acquisitionMethod', 'specifyOther', 'batchNo', 'watermarkText'));

        } catch (\Exception $e) {
            \Log::error('Error generating Batch Conversion Application: ' . $e->getMessage());
            return abort(500, 'Error generating documents: ' . $e->getMessage());
        }
    }
    /**
     * Get the current print status for a document
     */
    public function getPrintStatus(Request $request)
    {
        $reference = $request->query('reference');
        $type = $request->query('type');
        $docType = $request->query('doc_type');
        $batchNo = $request->query('batch_no');

        if (!$reference || !$type || !$docType) {
            return response()->json(['count' => 0, 'status' => 'Original', 'allowed' => true]);
        }

        // Count existing prints
        $query = DB::connection('sqlsrv')->table('print_logs')
            ->where('document_type', $docType);

        if ($type === 'Individual') {
            $query->where(function ($q) use ($reference, $type, $batchNo) {
                // Check for individual prints
                $q->where(function ($sub) use ($reference, $type) {
                    $sub->where('reference_number', $reference)
                        ->where('print_type', $type);
                });

                // Also check for batch prints if batch logic is applicable
                if ($batchNo && $batchNo !== 'null' && $batchNo !== 'undefined') {
                    $q->orWhere(function ($sub) use ($batchNo) {
                        $sub->where('reference_number', $batchNo)
                            ->where('print_type', 'Batch');
                    });
                }
            });
        } else {
            // Batch mode
            $query->where('reference_number', $reference)
                ->where('print_type', $type);
        }

        $count = $query->count();

        // Determine status and eligibility
        // Always allowed as per user request
        $allowed = true;
        // Status is Original for the first 50 prints, then Certified True Copy
        $status = $count < 50 ? 'Original' : 'Certified True Copy';

        return response()->json([
            'count' => $count,
            'status' => $status,
            'allowed' => $allowed
        ]);
    }

    /**
     * Record a print action
     */
    public function recordPrint(Request $request)
    {
        $reference = $request->input('reference');
        $type = $request->input('type');
        $docType = $request->input('doc_type');

        if (!$reference) {
            return response()->json(['success' => false, 'message' => 'Missing reference']);
        }

        try {
            if ($type === 'Batch' || $type === 'Date') {
                $query = \Illuminate\Support\Facades\DB::connection('sqlsrv')->table('mls_file_no')
                    ->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    });

                if ($type === 'Date') {
                    $query->where(function ($q) use ($reference) {
                        $q->whereDate('commissioning_date', $reference)
                            ->orWhereDate('created_at', $reference);
                    });
                } else {
                    $query->where('batch_no', $reference);
                }

                // Exclude OSS / One-Stop Shop specific records (match getBatchRecords filters)
                $query->where(function ($q) {
                    $q->where(function ($sq) {
                        $sq->where('sub_source', '!=', 'OP Change of Name')
                            ->orWhereNull('sub_source');
                    })
                        ->where(function ($sq) {
                            $sq->whereNotIn('source', ['OP Resettlement', 'OP Direct Allocation'])
                                ->orWhereNull('source');
                        });
                });

                // Only log records that haven't been printed yet
                $query->whereNotExists(function ($q) use ($docType) {
                    $q->select(DB::raw(1))
                        ->from('print_logs as pl')
                        ->whereColumn('pl.reference_number', 'mls_file_no.full_file_number')
                        ->where('pl.document_type', $docType);
                });

                $files = $query->pluck('full_file_number');

                $logs = [];
                $now = now();
                $userId = \Illuminate\Support\Facades\Auth::check() ? \Illuminate\Support\Facades\Auth::id() : null;

                foreach ($files as $fileNo) {
                    if (!empty($fileNo)) {
                        $logs[] = [
                            'reference_number' => $fileNo,
                            'document_type' => $docType,
                            'print_type' => 'Individual',
                            'status' => 'Printed',
                            'user_id' => $userId,
                            'created_at' => $now,
                            'updated_at' => $now
                        ];
                    }
                }

                if (!empty($logs)) {
                    \Illuminate\Support\Facades\DB::connection('sqlsrv')->table('print_logs')->insert($logs);
                }
            } else {
                \Illuminate\Support\Facades\DB::connection('sqlsrv')->table('print_logs')->insert([
                    'reference_number' => $reference,
                    'document_type' => $docType,
                    'print_type' => $type,
                    'status' => 'Printed', // You might want to track checking vs printing
                    'user_id' => \Illuminate\Support\Facades\Auth::check() ? \Illuminate\Support\Facades\Auth::id() : null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('Print log error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Could not log print']);
        }
    }


    /**
     * Consolidation Report – fetch records from mls_file_no for export (PDF / Excel).
     *
     * Filters:
     *   - date_from / date_to   (commissioning date range)
     *   - file_year             (the year embedded in full_file_number, e.g. 2026)
     *   - prefix                (land_use prefix, e.g. RES, COM, IND, AG …)
     */
    public function getConsolidationReport(Request $request)
    {
        try {
            $query = DB::connection('sqlsrv')
                ->table('mls_file_no')
                ->leftJoin('purposes', 'mls_file_no.purpose_id', '=', 'purposes.id')
                ->leftJoin('prefix', 'mls_file_no.land_use', '=', 'prefix.prefix')
                ->leftJoin('land_uses', 'prefix.land_use_id', '=', 'land_uses.id')
                ->where(function ($q) {
                    $q->whereNull('mls_file_no.is_deleted')->orWhere('mls_file_no.is_deleted', 0);
                })
                ->select([
                    'mls_file_no.id',
                    'mls_file_no.full_file_number',
                    'mls_file_no.file_name',
                    'mls_file_no.land_use',
                    'mls_file_no.customer_type',
                    'mls_file_no.plot_no',
                    'mls_file_no.tp_no',
                    'mls_file_no.location',
                    'mls_file_no.lga',
                    'mls_file_no.source',
                    'mls_file_no.created_by',
                    'mls_file_no.created_at',
                    'purposes.name as purpose_name',
                    'land_uses.landuse as land_use_full',
                ]);

            // Filter by commissioning date range
            if ($request->filled('date_from')) {
                $query->whereDate('mls_file_no.created_at', '>=', $request->input('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->whereDate('mls_file_no.created_at', '<=', $request->input('date_to'));
            }

            // Filter by the year in the file number (e.g. RES-2026-xxx → year = 2026)
            if ($request->filled('file_year')) {
                $year = (int) $request->input('file_year');
                $query->where('mls_file_no.full_file_number', 'LIKE', "%-{$year}-%");
            }

            // Filter by land-use prefix (Exact match)
            if ($request->filled('prefix')) {
                $prefix = trim($request->input('prefix'));
                $query->where('mls_file_no.land_use', $prefix);
            }

            $query->orderBy('mls_file_no.id', 'asc');

            $records = $query->get();

            return response()->json([
                'success' => true,
                'count' => $records->count(),
                'data' => $records,
            ]);
        } catch (\Exception $e) {
            \Log::error('Consolidation report error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching consolidation report: ' . $e->getMessage(),
            ], 500);
        }
    }

}

