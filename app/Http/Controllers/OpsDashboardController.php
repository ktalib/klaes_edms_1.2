<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use App\Support\DateFormatter;
use App\Services\TimelineWeightingService;

class OpsDashboardController extends Controller
{
    protected TimelineWeightingService $timelineService;

    public function __construct(TimelineWeightingService $timelineService)
    {
        $this->timelineService = $timelineService;
    }

    public function index()
    {
        return view('ops_dashboard.index');
    }

    /**
     * Summary stat cards — returned as JSON for AJAX load.
     */
    public function stats(Request $request)
    {
        $from = $request->input('from');
        $to   = $request->input('to');

        $icBase = DB::connection('sqlsrv')
            ->table('instrument_capture')
            ->where('instrument_type', 'Occupancy Permit (OP)')
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); });

        if ($from) $icBase->whereDate('created_at', '>=', $from);
        if ($to)   $icBase->whereDate('created_at', '<=', $to);

        $icCount = (clone $icBase)->count();

        $directAllocation = (clone $icBase)->where('op_type', 'OP Direct Allocation')->count();
        $resettlement      = (clone $icBase)->where('op_type', 'OP Resettlement')->count();

        $linkedToMls = DB::connection('sqlsrv')
            ->table('mls_file_no')
            ->whereNotNull('source_instrument_capture_id')
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('created_at', '<=', $to))
            ->count();

        $changeOfName = DB::connection('sqlsrv')
            ->table('pra')
            ->where('instrument_type', 'Occupancy Permit (OP)')
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })
            ->whereIn('prop_id', function ($sub) {
                $sub->select('prop_id')
                    ->from('pra')
                    ->where('system_source', 'OSSOPCHANGEOFNAME')
                    ->whereNotNull('prop_id');
            })
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('created_at', '<=', $to))
            ->count();

        $opApplications = $changeOfName;

        $praRecords = DB::connection('sqlsrv')
            ->table('pra')
            ->whereIn('instrument_type', ['Occupancy Permit (OP)', 'Transfer of Title (OP)'])
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('created_at', '<=', $to))
            ->count();

        $totalOps = $icCount + $praRecords;

        return response()->json([
            'total_ops'         => $totalOps,
            'direct_allocation' => $directAllocation,
            'resettlement'      => $resettlement,
            'op_applications'   => $opApplications,
            'linked_to_mls'     => $linkedToMls,
            'change_of_name'    => $changeOfName,
            'pra_records'       => $praRecords,
        ]);
    }

    /**
     * Land-use distribution chart data.
     */
    public function chartLandUse(Request $request)
    {
        $from = $request->input('from');
        $to   = $request->input('to');

        $rows = DB::connection('sqlsrv')
            ->table('instrument_capture')
            ->select(
                DB::raw("LTRIM(RTRIM(land_use)) as label"),
                DB::raw('COUNT(*) as total')
            )
            ->where('instrument_type', 'Occupancy Permit (OP)')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->whereNotNull('land_use')
            ->whereRaw("LTRIM(RTRIM(land_use)) != ''")
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('created_at', '<=', $to))
            ->groupBy('land_use')
            ->orderByDesc('total')
            ->get();

        return response()->json($rows);
    }

    /**
     * OP-type breakdown chart data.
     */
    public function chartOpType(Request $request)
    {
        $from = $request->input('from');
        $to   = $request->input('to');

        $rows = DB::connection('sqlsrv')
            ->table('instrument_capture')
            ->select(DB::raw('ISNULL(op_type, \'Unknown\') as label'), DB::raw('COUNT(*) as total'))
            ->where('instrument_type', 'Occupancy Permit (OP)')
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('created_at', '<=', $to))
            ->groupBy('op_type')
            ->orderByDesc('total')
            ->get();

        return response()->json($rows);
    }

    /**
     * DataTable — All OPs tab.
     */
    public function tableAllOps(Request $request)
    {
        $from = $request->input('from');
        $to   = $request->input('to');

        // ── Part 1: instrument_capture ────────────────────────────────────────
        $captureQ = DB::connection('sqlsrv')
            ->table('instrument_capture as ic')
            ->leftJoin('mls_file_no as mls', 'mls.source_instrument_capture_id', '=', 'ic.id')
            ->leftJoin('fileNumber as fn', function ($join) {
                $join->on('fn.mlsfNo', '=', 'mls.full_file_number')
                    ->orOn('fn.mlsfNo', '=', 'ic.mlsFNo');
            })
            ->select([
                DB::raw("ISNULL(CAST(fn.id AS NVARCHAR(50)), '')            as id"),
                DB::raw("ISNULL(ic.temp_fileno, '')                        as temp_fileno"),
                DB::raw("ISNULL(ic.op_type, '')                             as op_type"),
                DB::raw("ISNULL(CAST(ic.op_serial_number AS NVARCHAR(50)),'') as op_serial_number"),
                DB::raw("ISNULL(ic.registration_number, '')                 as registration_number"),
                DB::raw("ISNULL(mls.full_file_number, '')                   as mls_file_number"),
                DB::raw("ISNULL(ic.party_1_name, '')                        as grantor"),
                DB::raw("ISNULL(ic.party_2_name, '')                        as allottee"),
                DB::raw("''                                                 as new_party_name"),
                DB::raw("ISNULL(ic.property_description, ISNULL(ic.property_location, '')) as property_description"),
                'ic.created_at',
                DB::raw("ISNULL(CAST(ic.prop_id AS NVARCHAR(50)), '')       as prop_id"),
                DB::raw("ISNULL(CAST(ic.id AS NVARCHAR(50)), '')            as source_capture_id"),
                DB::raw("''                                                 as pra_id"),
                DB::raw("'0' as is_change_of_name"),
            ])
            ->where('ic.instrument_type', 'Occupancy Permit (OP)')
            ->where(function ($q) { $q->whereNull('ic.is_deleted')->orWhere('ic.is_deleted', 0); });

        // ── Part 2: OP Change of Name from mls_file_no -> source_pra_id -> pra ─
        $filenoQ = DB::connection('sqlsrv')
            ->table('mls_file_no as mfn')
            ->leftJoin('fileNumber as fn', 'fn.tracking_id', '=', 'mfn.tracking_id')
            ->join('pra as p', function ($join) {
                $join->on('p.id', '=', 'mfn.source_pra_id')
                     ->whereIn('p.instrument_type', ['Occupancy Permit (OP)', 'Transfer of Title (OP)']);
            })
            ->leftJoin('instrument_capture as src_ic', 'src_ic.id', '=', 'p.instrument_capture_id')
            ->select([
                DB::raw("ISNULL(CAST(fn.id AS NVARCHAR(50)), CAST(p.id AS NVARCHAR(50))) as id"),
                DB::raw("ISNULL(p.temp_fileno, ISNULL(src_ic.temp_fileno, '')) as temp_fileno"),
                DB::raw("ISNULL(p.op_type, ISNULL(mfn.source, '')) as op_type"),
                DB::raw("ISNULL(CAST(p.op_serial_number AS NVARCHAR(50)), '') as op_serial_number"),
                DB::raw("ISNULL(CAST(p.regNo AS NVARCHAR(50)), '') as registration_number"),
                DB::raw("ISNULL(fn.mlsfNo, ISNULL(mfn.full_file_number, '')) as mls_file_number"),
                DB::raw("ISNULL(src_ic.party_2_name, ISNULL(p.grantee, ISNULL(p.grantor, ISNULL(fn.FileName, '')))) as grantor"),
                DB::raw("ISNULL(fn.FileName, ISNULL(p.grantee, ISNULL(mfn.customer_type, ''))) as allottee"),
                DB::raw("ISNULL(fn.FileName, '') as new_party_name"),
                DB::raw("ISNULL(p.property_description, ISNULL(p.location, ISNULL(fn.location, ''))) as property_description"),
                DB::raw("ISNULL(fn.created_at, mfn.created_at) as created_at"),
                DB::raw("ISNULL(CAST(p.prop_id AS NVARCHAR(50)), ISNULL(CAST(src_ic.prop_id AS NVARCHAR(50)), '')) as prop_id"),
                DB::raw("ISNULL(CAST(p.instrument_capture_id AS NVARCHAR(50)), '') as source_capture_id"),
                DB::raw("ISNULL(CAST(p.id AS NVARCHAR(50)), '') as pra_id"),
                DB::raw("'1' as is_change_of_name"),
            ])
            ->where('mfn.sub_source', 'OP Change of Name')
            ->whereNotNull('mfn.source_pra_id')
            ->where(function ($q) { $q->whereNull('fn.is_deleted')->orWhere('fn.is_deleted', 0); });

        // ── Part 3: standalone PRA records (not in Part 1 or Part 2) ──────
        $praQ = DB::connection('sqlsrv')
            ->table('pra as p2')
            ->select([
                DB::raw("CAST(p2.id AS NVARCHAR(50))                        as id"),
                DB::raw("ISNULL(p2.temp_fileno, '')                        as temp_fileno"),
                DB::raw("ISNULL(p2.op_type, '')                             as op_type"),
                DB::raw("ISNULL(CAST(p2.op_serial_number AS NVARCHAR(50)),'') as op_serial_number"),
                DB::raw("ISNULL(CAST(p2.regNo AS NVARCHAR(50)), '')         as registration_number"),
                DB::raw("ISNULL(p2.mlsFNo, '')                              as mls_file_number"),
                DB::raw("ISNULL(p2.grantor, '')                             as grantor"),
                DB::raw("ISNULL(p2.grantee, '')                             as allottee"),
                DB::raw("''                                                 as new_party_name"),
                DB::raw("ISNULL(p2.property_description, ISNULL(p2.location, '')) as property_description"),
                'p2.created_at',
                DB::raw("ISNULL(CAST(p2.prop_id AS NVARCHAR(50)), '')       as prop_id"),
                DB::raw("ISNULL(CAST(p2.instrument_capture_id AS NVARCHAR(50)), '') as source_capture_id"),
                DB::raw("CAST(p2.id AS NVARCHAR(50))                        as pra_id"),
                DB::raw("'0' as is_change_of_name"),
            ])
            ->whereIn('p2.instrument_type', ['Occupancy Permit (OP)', 'Transfer of Title (OP)'])
            ->where(function ($q) { $q->whereNull('p2.is_deleted')->orWhere('p2.is_deleted', 0); })
            ->whereNull('p2.instrument_capture_id')
            ->where(function ($q) {
                $q->whereNull('p2.system_source')
                  ->orWhere('p2.system_source', '!=', 'OSSOPCHANGEOFNAME');
            })
            ->whereNotIn('p2.id', function ($sub) {
                $sub->select('source_pra_id')
                    ->from('mls_file_no')
                    ->where('sub_source', 'OP Change of Name')
                    ->whereNotNull('source_pra_id');
            });

        // Apply date filters before building the union
        if ($from) {
            $captureQ->whereDate('ic.created_at', '>=', $from);
            $filenoQ->whereDate('mfn.created_at', '>=', $from);
            $praQ->whereDate('p2.created_at', '>=', $from);
        }
        if ($to) {
            $captureQ->whereDate('ic.created_at', '<=', $to);
            $filenoQ->whereDate('mfn.created_at', '<=', $to);
            $praQ->whereDate('p2.created_at', '<=', $to);
        }

        // Build union and wrap as subquery
        $captureQ->unionAll($filenoQ)->unionAll($praQ);

        // Wrap in a subquery that collapses by prop_id (latest row wins)
        $unionSql = $captureQ->toSql();
        $unionBindings = $captureQ->getBindings();

        $rankedSql = "SELECT *, "
            . "ROW_NUMBER() OVER (PARTITION BY CASE WHEN prop_id IS NOT NULL AND prop_id <> '' THEN prop_id ELSE CAST(NEWID() AS NVARCHAR(50)) END ORDER BY created_at DESC) as _rn "
            . "FROM ({$unionSql}) as u";

        // timeline_count: cross-table count matching PropertySearchController's 4 tables
        $timelineCountExpr = "CASE WHEN ranked.prop_id IS NOT NULL AND ranked.prop_id <> '' THEN "
            . "(SELECT COUNT(*) FROM file_history_staging WHERE CAST(prop_id AS NVARCHAR(50)) = ranked.prop_id) + "
            . "(SELECT COUNT(*) FROM pra WHERE CAST(prop_id AS NVARCHAR(50)) = ranked.prop_id AND (is_deleted IS NULL OR is_deleted = 0)) + "
            . "(SELECT COUNT(*) FROM deed_registrations WHERE CAST(prop_id AS NVARCHAR(50)) = ranked.prop_id) + "
            . "(SELECT COUNT(*) FROM CofO_staging WHERE CAST(prop_id AS NVARCHAR(50)) = ranked.prop_id) "
            . "ELSE 1 END";

        $query = DB::connection('sqlsrv')
            ->table(DB::raw("({$rankedSql}) as ranked"))
            ->setBindings($unionBindings)
            ->where('_rn', 1)
            ->select([
                'id', 'temp_fileno', 'op_type', 'op_serial_number',
                'registration_number', 'mls_file_number', 'grantor',
                'allottee', 'new_party_name', 'property_description',
                'created_at', 'prop_id', 'source_capture_id', 'pra_id',
                DB::raw("CAST(is_change_of_name AS NVARCHAR(1)) as is_change_of_name"),
                DB::raw("({$timelineCountExpr}) as timeline_count"),
            ]);

        return DataTables::of($query)
            ->filterColumn('temp_fileno',     fn($q, $kw) => $q->where('temp_fileno',     'like', "%$kw%"))
            ->filterColumn('mls_file_number', fn($q, $kw) => $q->where('mls_file_number', 'like', "%$kw%"))
            ->filterColumn('grantor',         fn($q, $kw) => $q->where('grantor',         'like', "%$kw%"))
            ->filterColumn('allottee',        fn($q, $kw) => $q->where('allottee',        'like', "%$kw%"))
            ->editColumn('created_at', fn($row) => DateFormatter::toDisplay($row->created_at) ?? '—')
            ->editColumn('timeline_count', function ($row) {
                $propId = trim((string) ($row->prop_id ?? ''));
                $fileNo = trim((string) ($row->mls_file_number ?? ($row->temp_fileno ?? '')));
                
                $rawRecords = $this->timelineService->getRawRecords($fileNo, $propId);
                return max(1, $this->timelineService->getWeightedCount($rawRecords));
            })
            ->rawColumns([])
            ->make(true);
    }

    /**
     * DataTable — PRA Records tab.
     */
    public function tablePra(Request $request)
    {
        $from = $request->input('from');
        $to   = $request->input('to');

        $query = DB::connection('sqlsrv')
            ->table('pra as p')
            ->select([
                'p.id',
                'p.temp_fileno',
                DB::raw('ISNULL(p.mlsFNo, \'\') as mls_file_number'),
                DB::raw('ISNULL(p.transaction_type, \'\') as transaction_type'),
                DB::raw('ISNULL(p.grantor, \'\') as grantor'),
                DB::raw('ISNULL(p.grantee, \'\') as grantee'),
                DB::raw('ISNULL(p.lgsaOrCity, \'\') as lga'),
                DB::raw('ISNULL(p.op_type, \'\') as op_type'),
                DB::raw('ISNULL(p.op_serial_number, \'\') as op_serial_number'),
                'p.created_at',
                DB::raw("ISNULL(CAST(p.prop_id AS NVARCHAR(50)), '') as prop_id"),
                DB::raw("ISNULL(CAST(p.instrument_capture_id AS NVARCHAR(50)), '') as source_capture_id"),
                DB::raw("CAST(p.id AS NVARCHAR(50)) as pra_id"),
            ])
            ->whereIn('p.instrument_type', ['Occupancy Permit (OP)', 'Transfer of Title (OP)'])
            ->where(function ($q) { $q->whereNull('p.is_deleted')->orWhere('p.is_deleted', 0); })
            ->when($from, fn($q) => $q->whereDate('p.created_at', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('p.created_at', '<=', $to));

        return DataTables::of($query)
            ->editColumn('created_at', fn($row) => DateFormatter::toDisplay($row->created_at) ?? '—')
            ->make(true);
    }

    /**
     * DataTable — Change of Name tab.
     */
    public function tableChangeOfName(Request $request)
    {
        $from = $request->input('from');
        $to   = $request->input('to');

        // Subquery to pull data from the linked Transfer of Title (Change of Name) record
        $tSub = fn(string $expr) => "(SELECT TOP 1 {$expr} FROM pra t WHERE t.prop_id = p.prop_id AND t.system_source = 'OSSOPCHANGEOFNAME' AND t.instrument_type = 'Transfer of Title (OP)' ORDER BY t.id DESC)";

        // One row per Change of Name: the original OP enriched with Transfer of Title data
        $inner = DB::connection('sqlsrv')
            ->table('pra as p')
            ->leftJoin('instrument_capture as ic', 'ic.id', '=', 'p.instrument_capture_id')
            ->select([
                'p.id',
                DB::raw("ISNULL(p.temp_fileno, ISNULL(ic.temp_fileno, '')) as temp_fileno"),
                DB::raw("ISNULL({$tSub('ISNULL(t.mlsFNo, t.fileno)')}, ISNULL(p.mlsFNo, ISNULL(p.fileno, ''))) as mls_file_number"),
                DB::raw("ISNULL(p.Grantor, ISNULL(p.party_1, '')) as party_1"),
                DB::raw("ISNULL({$tSub('ISNULL(t.Grantee, t.party_2)')}, ISNULL(p.Grantee, ISNULL(p.party_2, ''))) as party_2"),
                DB::raw("ISNULL(p.op_type, ISNULL(ic.op_type, '')) as op_type"),
                DB::raw("ISNULL(CAST(p.op_serial_number AS NVARCHAR(50)), ISNULL(CAST(ic.op_serial_number AS NVARCHAR(50)), '')) as op_serial_number"),
                DB::raw("ISNULL(p.land_use, '') as land_use"),
                DB::raw('p.created_at as created_at'),
                DB::raw("ISNULL(CAST(p.prop_id AS NVARCHAR(50)), ISNULL(CAST(ic.prop_id AS NVARCHAR(50)), '')) as prop_id"),
                DB::raw("ISNULL(CAST(p.instrument_capture_id AS NVARCHAR(50)), '') as source_capture_id"),
                DB::raw("CAST(p.id AS NVARCHAR(50)) as pra_id"),
                DB::raw("ISNULL({$tSub('ISNULL(t.Grantee, t.party_2)')}, ISNULL(p.Grantee, ISNULL(p.party_2, ''))) as new_party_name"),
                DB::raw("'1' as is_change_of_name"),
            ])
            ->where('p.instrument_type', 'Occupancy Permit (OP)')
            ->where(function ($q) { $q->whereNull('p.is_deleted')->orWhere('p.is_deleted', 0); })
            ->whereIn('p.prop_id', function ($sub) {
                $sub->select('prop_id')
                    ->from('pra')
                    ->where('system_source', 'OSSOPCHANGEOFNAME')
                    ->whereNotNull('prop_id');
            })
            ->when($from, fn($q) => $q->whereDate('p.created_at', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('p.created_at', '<=', $to));

        // Wrap in subquery so column names are unambiguous for ORDER BY
        $query = DB::connection('sqlsrv')
            ->table(DB::raw("({$inner->toSql()}) as sub"))
            ->mergeBindings($inner)
            ->select('*');

        return DataTables::of($query)
            ->editColumn('created_at', fn($row) => $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('d M Y') : '—')
            ->make(true);
    }

    /**
     * DataTable — Deeds Instrument Capture tab.
     */
    public function tableDeeds(Request $request)
    {
        $from = $request->input('from');
        $to   = $request->input('to');

        $inner = DB::connection('sqlsrv')
            ->table('instrument_capture as ic')
            ->leftJoin('deed_registrations as dr', 'dr.prop_id', '=', 'ic.prop_id')
            ->select([
                'ic.id',
                'ic.temp_fileno',
                DB::raw('ISNULL(ic.instrument_type, \'\') as instrument_type'),
                DB::raw('ISNULL(dr.registration_number, \'\') as reg_number'),
                DB::raw('ISNULL(dr.deeds_date, \'\') as deeds_date'),
                DB::raw('ISNULL(ic.party_1_name, \'\') as grantor'),
                DB::raw('ISNULL(ic.party_2_name, \'\') as grantee'),
                DB::raw('ic.created_at as created_at'),
                DB::raw("ISNULL(CAST(ic.prop_id AS NVARCHAR(50)), '') as prop_id"),
                DB::raw("ISNULL(CAST(ic.id AS NVARCHAR(50)), '') as source_capture_id"),
                DB::raw("'' as pra_id"),
            ])
            ->where('ic.instrument_type', 'Occupancy Permit (OP)')
            ->where(function ($q) { $q->whereNull('ic.is_deleted')->orWhere('ic.is_deleted', 0); })
            ->whereNotNull('dr.id')
            ->when($from, fn($q) => $q->whereDate('ic.created_at', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('ic.created_at', '<=', $to));

        // Wrap in subquery so column names are unambiguous for ORDER BY
        $query = DB::connection('sqlsrv')
            ->table(DB::raw("({$inner->toSql()}) as sub"))
            ->mergeBindings($inner)
            ->select('*');

        return DataTables::of($query)
            ->editColumn('created_at', fn($row) => $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('d M Y') : '—')
            ->make(true);
    }
}
