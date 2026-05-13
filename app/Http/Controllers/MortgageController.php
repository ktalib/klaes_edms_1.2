<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use App\Services\TimelineWeightingService;
use Carbon\Carbon;

class MortgageController extends Controller
{
    protected TimelineWeightingService $timelineService;

    public function __construct(TimelineWeightingService $timelineService)
    {
        $this->timelineService = $timelineService;
    }

    /**
     * Display the mortgage table view.
     */
    public function index()
    {
        $today = Carbon::today();
        
        $baseFilters = function($q) {
            $q->where('instrument_type', 'Deed of Mortgage')
              ->where(function ($q2) { $q2->whereNull('is_deleted')->orWhere('is_deleted', 0); });
        };

        // Source-specific totals
        $icTotal = DB::connection('sqlsrv')->table('instrument_capture')->where($baseFilters)->count();
        $praTotal = DB::connection('sqlsrv')->table('pra')->where($baseFilters)->count();
        $fhsTotal = DB::connection('sqlsrv')->table('file_history_staging')->where($baseFilters)->count();

        // Daily Records (Combined)
        $dailyRecords = DB::connection('sqlsrv')->table('instrument_capture')->where($baseFilters)->whereDate('created_at', $today)->count() +
            DB::connection('sqlsrv')->table('pra')->where($baseFilters)->whereDate('created_at', $today)->count() +
            DB::connection('sqlsrv')->table('file_history_staging')->where($baseFilters)->whereDate('created_at', $today)->count();

        $totalRecords = $icTotal + $praTotal + $fhsTotal;

        return view('mortgages.index', compact('dailyRecords', 'totalRecords', 'icTotal', 'praTotal', 'fhsTotal'));
    }

    /**
     * Get data for DataTables.
     */
    public function getData()
    {
        $icQuery = DB::connection('sqlsrv')->table('instrument_capture')
            ->select([
                DB::raw("'ic_' + CAST(id AS NVARCHAR(50)) as id"),
                'prop_id',
                DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno) as file_number"),
                DB::raw("registration_number as registration_particulars"),
                'instrument_type',
                'party_1_name as party_1',
                'party_2_name as party_2',
                'party_3_name as party_3',
                'party_4_name as party_4',
                'property_location as location',
                'created_at as date_captured',
                DB::raw("'Instrument Capture' as source_table")
            ])
            ->where('instrument_type', 'Deed of Mortgage')
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); });

        $praQuery = DB::connection('sqlsrv')->table('pra')
            ->select([
                DB::raw("'pra_' + CAST(id AS NVARCHAR(50)) as id"),
                'prop_id',
                DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, fileno) as file_number"),
                DB::raw("CAST(ISNULL(regNo, '') AS NVARCHAR(MAX)) as registration_particulars"),
                'instrument_type',
                DB::raw("COALESCE(Mortgagor, Grantor) as party_1"),
                DB::raw("COALESCE(Mortgagee, Grantee) as party_2"),
                DB::raw("NULL as party_3"),
                DB::raw("NULL as party_4"),
                'location',
                'created_at as date_captured',
                DB::raw("'Property Records' as source_table")
            ])
            ->where('instrument_type', 'Deed of Mortgage')
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); });

        $fhsQuery = DB::connection('sqlsrv')->table('file_history_staging')
            ->select([
                DB::raw("'fhs_' + CAST(id AS NVARCHAR(50)) as id"),
                'prop_id',
                DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, fileno, temp_fileno) as file_number"),
                DB::raw("CAST(ISNULL(regNo, '') AS NVARCHAR(MAX)) as registration_particulars"),
                'instrument_type',
                DB::raw("COALESCE(Mortgagor, Grantor) as party_1"),
                DB::raw("COALESCE(Mortgagee, Grantee) as party_2"),
                'party_3',
                'party_4',
                'location',
                'created_at as date_captured',
                DB::raw("'File History Staging' as source_table")
            ])
            ->where('instrument_type', 'Deed of Mortgage')
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); });

        $unionQuery = $icQuery->unionAll($praQuery)->unionAll($fhsQuery);
        $unionSql = $unionQuery->toSql();
        $unionBindings = $unionQuery->getBindings();

        $query = DB::connection('sqlsrv')
            ->table(DB::raw("({$unionSql}) as combined"))
            ->setBindings($unionBindings)
            ->select('*');

        return DataTables::of($query)
            ->filterColumn('file_number', function($q, $kw) {
                $q->where('file_number', 'like', "%$kw%");
            })
            ->filterColumn('registration_particulars', function($q, $kw) {
                $q->where('registration_particulars', 'like', "%$kw%");
            })
            ->addColumn('timeline_count', function ($row) {
                $propId = trim((string) ($row->prop_id ?? ''));
                $fileNo = $row->file_number ?? '';
                
                if (!$propId && !$fileNo) return 1;

                $rawRecords = $this->timelineService->getRawRecords($fileNo, $propId);
                return max(1, $this->timelineService->getWeightedCount($rawRecords));
            })
            ->editColumn('date_captured', function ($row) {
                try {
                    return $row->date_captured ? Carbon::parse($row->date_captured)->format('Y-m-d H:i') : '—';
                } catch (\Exception $e) {
                    return $row->date_captured ?? '—';
                }
            })
            ->make(true);
    }
}
