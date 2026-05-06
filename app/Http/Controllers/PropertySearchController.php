<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PropertySearchController extends Controller
{
    /**
     * Display the Property Records search page.
     */
    public function index()
    {
        return view('property_search.index', [
            'PageTitle' => 'Property Records',
        ]);
    }

    /**
     * Server-side DataTable endpoint – returns unified records from all aggregate tables,
     * collapsed so each prop_id / file_number group appears only once.
     */
    public function data(Request $request)
    {
        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = min((int) $request->input('length', 25), 200);
        $searchValue = trim((string) $request->input('search.value', ''));

        $columns = [
            'file_number',        // 0
            'party_1',            // 1
            'party_2',            // 2
            'transaction_type',   // 3
            'land_use',           // 4
            'registration',       // 5
            'transaction_date',   // 6
            'location',           // 7
            'source_table',       // 8
        ];

        $orderColumnIndex = (int) $request->input('order.0.column', 6);
        $orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $orderColumn = $columns[$orderColumnIndex] ?? 'transaction_date';

        try {
            $connection = DB::connection('sqlsrv');

            $baseQuery = $this->buildCollapsedQuery($connection);

            $recordsTotal = (clone $baseQuery)->count();

            if ($searchValue !== '') {
                $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $searchValue);
                $baseQuery->where(function ($q) use ($escaped) {
                    $q->where('file_number', 'LIKE', "%{$escaped}%")
                      ->orWhere('party_1', 'LIKE', "%{$escaped}%")
                      ->orWhere('party_2', 'LIKE', "%{$escaped}%")
                      ->orWhere('transaction_type', 'LIKE', "%{$escaped}%")
                      ->orWhere('land_use', 'LIKE', "%{$escaped}%")
                      ->orWhere('location', 'LIKE', "%{$escaped}%")
                      ->orWhere('registration', 'LIKE', "%{$escaped}%")
                      ->orWhere('source_table', 'LIKE', "%{$escaped}%");
                });
            }

            $recordsFiltered = (clone $baseQuery)->count();

            $records = (clone $baseQuery)
                ->orderByRaw("{$orderColumn} {$orderDir}")
                ->offset($start)
                ->limit($length)
                ->get();

            $data = $records->map(function ($row) {
                return [
                    'file_number'       => $row->file_number ?: 'N/A',
                    'party_1'           => $row->party_1 ?: 'N/A',
                    'party_2'           => $row->party_2 ?: 'N/A',
                    'transaction_type'  => $row->transaction_type ?: 'N/A',
                    'land_use'          => $row->land_use ?: 'N/A',
                    'registration'      => $row->registration ?: 'N/A',
                    'transaction_date'  => $this->formatDate($row->transaction_date),
                    'location'          => $row->location ?: 'N/A',
                    'source_table'      => $row->source_table,
                    'timeline_count'    => (int) ($row->timeline_count ?? 1),
                    'prop_id'           => $row->prop_id_raw,
                ];
            });

            return response()->json([
                'draw'            => $draw,
                'recordsTotal'    => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data'            => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('PropertySearchController::data error', ['error' => $e->getMessage()]);

            return response()->json([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
            ]);
        }
    }

    /**
     * Dashboard metrics for the Property Records page.
     */
    public function stats()
    {
        try {
            $connection = DB::connection('sqlsrv');
            $collapsed = $this->buildCollapsedQuery($connection);

            $totalRecords = (clone $collapsed)->count();
            $withTimeline = (clone $collapsed)->where('timeline_count', '>', 1)->count();

            $sourceCounts = [
                'file_history' => (clone $collapsed)->where('source_table', 'File History')->count(),
                'cofo' => (clone $collapsed)->where('source_table', 'CofO')->count(),
                'pra' => (clone $collapsed)->where('source_table', 'PRA')->count(),
                'deed_registration' => (clone $collapsed)->where('source_table', 'Deed Registration')->count(),
            ];

            $topTypes = (clone $collapsed)
                ->select('transaction_type', DB::raw('COUNT(*) as total'))
                ->whereNotNull('transaction_type')
                ->groupBy('transaction_type')
                ->orderByDesc('total')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_records' => $totalRecords,
                    'multi_timeline_records' => $withTimeline,
                    'single_timeline_records' => max(0, $totalRecords - $withTimeline),
                    'source_counts' => $sourceCounts,
                    'top_transaction_types' => $topTypes,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('PropertySearchController::stats error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load dashboard statistics.',
                'data' => [
                    'total_records' => 0,
                    'multi_timeline_records' => 0,
                    'single_timeline_records' => 0,
                    'source_counts' => [
                        'file_history' => 0,
                        'cofo' => 0,
                        'pra' => 0,
                        'deed_registration' => 0,
                    ],
                    'top_transaction_types' => [],
                ],
            ], 500);
        }
    }

    /**
     * Timeline view – shows all transactions for a given file number across all 4 tables, chronologically.
     */
    public function timeline(Request $request)
    {
        $fileNumber = trim((string) $request->query('file_number', ''));
        $propId = trim((string) $request->query('prop_id', ''));

        abort_if($fileNumber === '' && $propId === '', 404, 'A file number or property ID is required.');

        $connection = DB::connection('sqlsrv');
        $transactions = collect();

        // --- file_history_staging ---
        $fhQuery = $connection->table('file_history_staging')
            ->select([
                'id',
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                'transaction_type',
                'transaction_date',
                'party_1',
                'party_2',
                'party_3',
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
                DB::raw("serialNo AS serial_no"),
                DB::raw("pageNo AS page_no"),
                DB::raw("volumeNo AS volume_no"),
                'regNo',
                'prop_id',
                'comments',
                DB::raw("'file_history_staging' AS source_table"),
            ]);
        $this->applyFileOrPropFilter($fhQuery, $fileNumber, $propId, ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);
        $fhRows = $fhQuery->get()->map(fn($r) => $this->normalizeTimelineRow($r, 'file_history_staging'));

        // --- CofO_staging ---
        $cofoQuery = $connection->table('CofO_staging')
            ->select([
                'id',
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                'transaction_type',
                'transaction_date',
                DB::raw("NULL AS party_1"),
                DB::raw("NULL AS party_2"),
                DB::raw("NULL AS party_3"),
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
                DB::raw("serialNo AS serial_no"),
                DB::raw("pageNo AS page_no"),
                DB::raw("volumeNo AS volume_no"),
                'regNo',
                'prop_id',
                'comments',
                DB::raw("'CofO_staging' AS source_table"),
            ]);
        $this->applyFileOrPropFilter($cofoQuery, $fileNumber, $propId, ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);
        $cofoRows = $cofoQuery->get()->map(fn($r) => $this->normalizeTimelineRow($r, 'CofO_staging'));

        // --- pra ---
        $praQuery = $connection->table('pra')
            ->select([
                'id',
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                'transaction_type',
                'transaction_date',
                'party_1',
                'party_2',
                'party_3',
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
                DB::raw("serialNo AS serial_no"),
                DB::raw("pageNo AS page_no"),
                DB::raw("volumeNo AS volume_no"),
                'regNo',
                'prop_id',
                DB::raw("comments"),
                DB::raw("'pra' AS source_table"),
            ]);
        $this->applyFileOrPropFilter($praQuery, $fileNumber, $propId, ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);
        $praRows = $praQuery->get()->map(fn($r) => $this->normalizeTimelineRow($r, 'pra'));

        // --- deed_registrations (uses deeds_date as transaction_date) ---
        $deedQuery = $connection->table('deed_registrations')
            ->select([
                'id',
                DB::raw("fileno AS file_number"),
                DB::raw("instrument_type AS transaction_type"),
                DB::raw("deeds_date AS transaction_date"),
                DB::raw("grantor AS party_1"),
                DB::raw("grantee AS party_2"),
                DB::raw("NULL AS party_3"),
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
                'serial_no',
                'page_no',
                'volume_no',
                DB::raw("registration_number AS regNo"),
                'prop_id',
                DB::raw("property_description AS comments"),
                DB::raw("'deed_registrations' AS source_table"),
            ]);
        $this->applyFileOrPropFilter($deedQuery, $fileNumber, $propId, ['fileno', 'parent_fileno']);

        $deedRows = $deedQuery->get()->map(fn($r) => $this->normalizeTimelineRow($r, 'deed_registrations'));

        // Merge and sort chronologically
        $transactions = $fhRows->concat($cofoRows)->concat($praRows)->concat($deedRows);

        $transactions = $transactions->sortBy(function ($t) {
            if (!$t['sort_date']) {
                return '9999-12-31';
            }
            return $t['sort_date'];
        })->values();

        $displayFileNumber = $fileNumber ?: ($transactions->first()['file_number'] ?? $propId);

        $firstTxn = $transactions->first();
        $lastTxn = $transactions->last();

        $historyPayload = [
            'fileNumber'        => $displayFileNumber,
            'propId'            => $propId ?: ($firstTxn['prop_id'] ?? null),
            'landuse'           => $firstTxn['land_use'] ?? null,
            'location'          => $firstTxn['location'] ?? null,
            'totalTransactions' => $transactions->count(),
            'originalOwner'     => $firstTxn['primary_party'] ?? null,
            'currentOwner'      => $lastTxn['primary_party'] ?? null,
            'transactions'      => $transactions->toArray(),
        ];

        $viewData = [
            'PageTitle'      => 'Property Transaction Timeline',
            'historyPayload' => $historyPayload,
            'fileNumber'     => $displayFileNumber,
        ];

        // Support ?mode=partial for AJAX modal loading (no layout wrapper)
        if ($request->query('mode') === 'partial') {
            return view('property_search.timeline_partial', $viewData);
        }

        return view('property_search.timeline', $viewData);
    }

    /**
     * Build the UNION ALL query across 4 tables.
     */
    protected function buildUnionQuery($connection)
    {
        // Grouping key: use prop_id when available, otherwise fall back to file_number
        $groupKeyExpr = "CASE WHEN prop_id_raw IS NOT NULL AND LTRIM(RTRIM(prop_id_raw)) <> '' THEN prop_id_raw ELSE COALESCE(file_number, record_id) END";

        // file_history_staging
        $fh = $connection->table('file_history_staging')
            ->select([
                DB::raw("CAST(id AS NVARCHAR(50)) AS record_id"),
                DB::raw("CAST(prop_id AS NVARCHAR(50)) AS prop_id_raw"),
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                DB::raw("COALESCE(party_1, Assignor, Mortgagor, Grantor, Surrenderor, Lessor) AS party_1"),
                DB::raw("COALESCE(party_2, Assignee, Mortgagee, Grantee, Surrenderee, Lessee) AS party_2"),
                'transaction_type',
                'land_use',
                DB::raw("regNo AS registration"),
                DB::raw("TRY_CONVERT(DATE, transaction_date) AS transaction_date"),
                'location',
                DB::raw("'File History' AS source_table"),
            ]);

        // CofO_staging
        $cofo = $connection->table('CofO_staging')
            ->select([
                DB::raw("CAST(id AS NVARCHAR(50)) AS record_id"),
                DB::raw("CAST(prop_id AS NVARCHAR(50)) AS prop_id_raw"),
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                DB::raw("COALESCE(Assignor, Mortgagor, Grantor, Surrenderor, Lessor) AS party_1"),
                DB::raw("COALESCE(Assignee, Mortgagee, Grantee, Surrenderee, Lessee) AS party_2"),
                'transaction_type',
                'land_use',
                DB::raw("regNo AS registration"),
                DB::raw("TRY_CONVERT(DATE, transaction_date) AS transaction_date"),
                'location',
                DB::raw("'CofO' AS source_table"),
            ]);

        // pra
        $pra = $connection->table('pra')
            ->select([
                DB::raw("CAST(id AS NVARCHAR(50)) AS record_id"),
                DB::raw("CAST(prop_id AS NVARCHAR(50)) AS prop_id_raw"),
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                DB::raw("COALESCE(party_1, Assignor, Mortgagor, Grantor, Surrenderor, Lessor) AS party_1"),
                DB::raw("COALESCE(party_2, Assignee, Mortgagee, Grantee, Surrenderee, Lessee) AS party_2"),
                'transaction_type',
                'land_use',
                DB::raw("regNo AS registration"),
                DB::raw("TRY_CONVERT(DATE, transaction_date) AS transaction_date"),
                'location',
                DB::raw("'PRA' AS source_table"),
            ]);

        // deed_registrations — uses deeds_date
        $deed = $connection->table('deed_registrations')
            ->select([
                DB::raw("CAST(id AS NVARCHAR(50)) AS record_id"),
                DB::raw("CAST(prop_id AS NVARCHAR(50)) AS prop_id_raw"),
                DB::raw("fileno AS file_number"),
                DB::raw("grantor AS party_1"),
                DB::raw("grantee AS party_2"),
                DB::raw("instrument_type AS transaction_type"),
                DB::raw("NULL AS land_use"),
                DB::raw("registration_number AS registration"),
                DB::raw("TRY_CONVERT(DATE, deeds_date) AS transaction_date"),
                DB::raw("COALESCE(district, lga) AS location"),
                DB::raw("'Deed Registration' AS source_table"),
            ]);

        // Build UNION ALL, then add grouping key
        $union = $fh->unionAll($cofo)->unionAll($pra)->unionAll($deed);

        // Wrap with group_key column for partitioning
        return $connection->query()->fromSub($union, 'base')
            ->select('*')
            ->selectRaw("{$groupKeyExpr} AS group_key");
    }

    /**
     * Build collapsed (rank 1 per group key) query reused by list + stats.
     *
     * timeline_count uses correlated sub-queries against the four real tables
     * (matching by prop_id) so the badge matches the timeline modal output.
     */
    protected function buildCollapsedQuery($connection)
    {
        $unionQuery = $this->buildUnionQuery($connection);

        $rankedQuery = $connection->query()->fromSub($unionQuery, 'unified')
            ->select('*')
            ->selectRaw("ROW_NUMBER() OVER (PARTITION BY group_key ORDER BY transaction_date DESC) AS grp_rank");

        // Cross-table correlated count (mirrors OpsDashboardController pattern)
        $timelineCountExpr = "CASE WHEN ranked.prop_id_raw IS NOT NULL AND LTRIM(RTRIM(ranked.prop_id_raw)) <> '' THEN "
            . "(SELECT COUNT(*) FROM file_history_staging WHERE CAST(prop_id AS NVARCHAR(50)) = ranked.prop_id_raw) + "
            . "(SELECT COUNT(*) FROM pra WHERE CAST(prop_id AS NVARCHAR(50)) = ranked.prop_id_raw AND (is_deleted IS NULL OR is_deleted = 0)) + "
            . "(SELECT COUNT(*) FROM deed_registrations WHERE CAST(prop_id AS NVARCHAR(50)) = ranked.prop_id_raw) + "
            . "(SELECT COUNT(*) FROM CofO_staging WHERE CAST(prop_id AS NVARCHAR(50)) = ranked.prop_id_raw) "
            . "ELSE 1 END";

        $collapsedWithTimeline = $connection->query()->fromSub($rankedQuery, 'ranked')
            ->where('grp_rank', 1)
            ->select('ranked.*')
            ->selectRaw("({$timelineCountExpr}) AS timeline_count");

        // Wrap so timeline_count is a real column usable in WHERE (e.g. stats)
        return $connection->query()->fromSub($collapsedWithTimeline, 'collapsed');
    }

    /**
     * Apply file number or prop_id filter to a query.
     */
    protected function applyFileOrPropFilter($query, string $fileNumber, string $propId, array $fileColumns): void
    {
        $query->where(function ($q) use ($fileNumber, $propId, $fileColumns) {
            if ($fileNumber !== '') {
                $upper = strtoupper($fileNumber);
                foreach ($fileColumns as $col) {
                    $q->orWhereRaw("UPPER({$col}) = ?", [$upper]);
                }
            }
            if ($propId !== '') {
                $q->orWhere('prop_id', $propId);
            }
        });
    }

    /**
     * Normalize a timeline row into a consistent array shape.
     */
    protected function normalizeTimelineRow($row, string $source): array
    {
        $transactionDate = $row->transaction_date;
        $sortDate = null;
        $displayDate = null;

        if ($transactionDate) {
            try {
                $carbon = Carbon::parse($transactionDate);
                $sortDate = $carbon->toDateString();
                $displayDate = $carbon->format('F j, Y');
            } catch (\Exception $e) {
                $displayDate = $transactionDate;
            }
        }

        // Determine primary parties
        $party1 = $row->party_1 ?: ($row->assignor ?: ($row->mortgagor ?: ($row->grantor ?: ($row->surrenderor ?: ($row->lessor ?? null)))));
        $party2 = $row->party_2 ?: ($row->assignee ?: ($row->mortgagee ?: ($row->grantee ?: ($row->surrenderee ?: ($row->lessee ?? null)))));

        $parties = [];
        foreach (['assignor', 'assignee', 'mortgagor', 'mortgagee', 'grantor', 'grantee', 'surrenderor', 'surrenderee', 'lessor', 'lessee'] as $role) {
            $value = $row->{$role} ?? null;
            if ($value && trim($value) !== '') {
                $parties[ucfirst($role)] = trim($value);
            }
        }

        $regParts = explode('/', $row->regNo ?? '');
        $serialNo = $row->serial_no ?: ($regParts[0] ?? null);
        $pageNo = $row->page_no ?: ($regParts[1] ?? null);
        $volumeNo = $row->volume_no ?: ($regParts[2] ?? null);

        return [
            'id'                => $row->id,
            'file_number'       => $row->file_number,
            'transaction_type'  => $row->transaction_type ?: 'Transaction',
            'transaction_date'  => $displayDate,
            'sort_date'         => $sortDate,
            'primary_party'     => $party1 ?: ($party2 ?: 'Unknown'),
            'secondary_party'   => $party2,
            'party_1'           => $party1,
            'party_2'           => $party2,
            'party_3'           => $row->party_3 ?? null,
            'parties'           => $parties,
            'land_use'          => $row->land_use ?? null,
            'location'          => $row->location ?? null,
            'serial_no'         => $serialNo,
            'page_no'           => $pageNo,
            'volume_no'         => $volumeNo,
            'regNo'             => $row->regNo ?? null,
            'prop_id'           => $row->prop_id ?? null,
            'comments'          => $row->comments ?? null,
            'source_table'      => $source,
        ];
    }

    /**
     * Format a date value for display.
     */
    protected function formatDate($value): string
    {
        if (!$value) {
            return 'N/A';
        }

        try {
            return Carbon::parse($value)->format('M j, Y');
        } catch (\Exception $e) {
            return (string) $value;
        }
    }
}
