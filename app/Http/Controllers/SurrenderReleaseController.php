<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use App\Services\TimelineWeightingService;
use Carbon\Carbon;

class SurrenderReleaseController extends Controller
{
    protected TimelineWeightingService $timelineService;

    public function __construct(TimelineWeightingService $timelineService)
    {
        $this->timelineService = $timelineService;
    }

    /**
     * Display the surrender & release table view.
     */
    public function index()
    {
        $today = Carbon::today();
        
        $baseFilters = function($q) {
            $q->where(function($sub) {
                $sub->whereIn('instrument_type', [
                    'Deed of Surrender and Release',
                    'Deed of Surrender & Release',
                    'Deed of Surrender, and Release',
                    'Surrender and Release',
                    'Surrender & Release'
                ])
                ->orWhereRaw("LOWER(REPLACE(REPLACE(instrument_type, '&', 'and'), '  ', ' ')) IN (?, ?, ?)", [
                    'deed of surrender and release',
                    'deed of surrender & release',
                    'deed of surrender, and release',
                ]);
            })
            ->where(function ($q2) { 
                $q2->whereNull('is_deleted')->orWhere('is_deleted', 0); 
            });
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

        return view('surrender_release.index', compact('dailyRecords', 'totalRecords', 'icTotal', 'praTotal', 'fhsTotal'));
    }

    /**
     * Get data for DataTables.
     */
    public function getData()
    {
        $baseWhere = function($q) {
            $q->whereIn('instrument_type', [
                'Deed of Surrender and Release',
                'Deed of Surrender & Release',
                'Deed of Surrender, and Release',
                'Surrender and Release',
                'Surrender & Release'
            ])
            ->orWhereRaw("LOWER(REPLACE(REPLACE(instrument_type, '&', 'and'), '  ', ' ')) IN (?, ?, ?)", [
                'deed of surrender and release',
                'deed of surrender & release',
                'deed of surrender, and release',
            ]);
        };

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
            ->where($baseWhere)
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); });

        $praQuery = DB::connection('sqlsrv')->table('pra')
            ->select([
                DB::raw("'pra_' + CAST(id AS NVARCHAR(50)) as id"),
                'prop_id',
                DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, fileno) as file_number"),
                DB::raw("CAST(ISNULL(regNo, '') AS NVARCHAR(MAX)) as registration_particulars"),
                'instrument_type',
                DB::raw("COALESCE(Surrenderor, Grantor) as party_1"),
                DB::raw("COALESCE(Surrenderee, Grantee) as party_2"),
                DB::raw("NULL as party_3"),
                DB::raw("NULL as party_4"),
                'location',
                'created_at as date_captured',
                DB::raw("'Property Records' as source_table")
            ])
            ->where($baseWhere)
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); });

        $fhsQuery = DB::connection('sqlsrv')->table('file_history_staging')
            ->select([
                DB::raw("'fhs_' + CAST(id AS NVARCHAR(50)) as id"),
                'prop_id',
                DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, fileno, temp_fileno) as file_number"),
                DB::raw("CAST(ISNULL(regNo, '') AS NVARCHAR(MAX)) as registration_particulars"),
                'instrument_type',
                DB::raw("COALESCE(Surrenderor, Grantor) as party_1"),
                DB::raw("COALESCE(Surrenderee, Grantee) as party_2"),
                'party_3',
                'party_4',
                'location',
                'created_at as date_captured',
                DB::raw("'File History Staging' as source_table")
            ])
            ->where($baseWhere)
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
            ->addColumn('associated_mortgages', function ($row) {
                $propId = trim((string) ($row->prop_id ?? ''));
                $fileNo = $row->file_number ?? '';
                return $this->getAssociatedMortgages($fileNo, $propId);
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

    /**
     * Get associated mortgages for a given file number or property ID.
     */
    protected function getAssociatedMortgages(?string $fileNumber, ?string $propId): array
    {
        if (empty($fileNumber) && empty($propId)) {
            return [];
        }

        $connection = DB::connection('sqlsrv');

        // 1. Check instrument_capture
        $icQuery = $connection->table('instrument_capture')
            ->select([
                'id',
                'prop_id',
                DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno) as file_number"),
                'registration_number as registration_particulars',
                'instrument_type',
                'party_1_name as party_1',
                'party_2_name as party_2',
                'party_3_name as party_3',
                'property_location as location',
                'created_at as date_captured',
                DB::raw("'Instrument Capture' as source")
            ])
            ->where(function($q) {
                $q->whereIn('instrument_type', [
                    'Deed of Mortgage',
                    'Tripartite Mortgage',
                    'Mortgage',
                    'Deed of Tripartite Mortgage',
                    'Legal Mortgage',
                    'Equitable Mortgage'
                ])
                ->orWhereRaw("LOWER(instrument_type) LIKE '%mortgage%'");
            })
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); });

        // 2. Check pra
        $praQuery = $connection->table('pra')
            ->select([
                'id',
                'prop_id',
                DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, fileno) as file_number"),
                DB::raw("CAST(ISNULL(regNo, '') AS NVARCHAR(MAX)) as registration_particulars"),
                'instrument_type',
                DB::raw("COALESCE(Mortgagor, Grantor) as party_1"),
                DB::raw("COALESCE(Mortgagee, Grantee) as party_2"),
                DB::raw("NULL as party_3"),
                'location',
                'created_at as date_captured',
                DB::raw("'Property Records' as source")
            ])
            ->where(function($q) {
                $q->whereIn('instrument_type', [
                    'Deed of Mortgage',
                    'Tripartite Mortgage',
                    'Mortgage',
                    'Deed of Tripartite Mortgage',
                    'Legal Mortgage',
                    'Equitable Mortgage'
                ])
                ->orWhereRaw("LOWER(instrument_type) LIKE '%mortgage%'");
            })
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); });

        // 3. Check file_history_staging
        $fhsQuery = $connection->table('file_history_staging')
            ->select([
                'id',
                'prop_id',
                DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, fileno, temp_fileno) as file_number"),
                DB::raw("CAST(ISNULL(regNo, '') AS NVARCHAR(MAX)) as registration_particulars"),
                'instrument_type',
                DB::raw("COALESCE(Mortgagor, Grantor) as party_1"),
                DB::raw("COALESCE(Mortgagee, Grantee) as party_2"),
                'party_3',
                'location',
                'created_at as date_captured',
                DB::raw("'File History Staging' as source")
            ])
            ->where(function($q) {
                $q->whereIn('instrument_type', [
                    'Deed of Mortgage',
                    'Tripartite Mortgage',
                    'Mortgage',
                    'Deed of Tripartite Mortgage',
                    'Legal Mortgage',
                    'Equitable Mortgage'
                ])
                ->orWhereRaw("LOWER(instrument_type) LIKE '%mortgage%'");
            })
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); });

        // Filter by prop_id or fileNumber using specific table columns to prevent "column not found" errors
        
        // Instrument Capture specific filter
        $icQuery->where(function($q) use ($fileNumber, $propId) {
            $hasCond = false;
            if (!empty($propId)) {
                $q->where('prop_id', $propId);
                $hasCond = true;
            }
            if (!empty($fileNumber) && $fileNumber !== '—') {
                if ($hasCond) {
                    $q->orWhere('mlsFNo', $fileNumber)
                      ->orWhere('kangisFileNo', $fileNumber)
                      ->orWhere('NewKANGISFileno', $fileNumber)
                      ->orWhere('temp_fileno', $fileNumber);
                } else {
                    $q->where(function($sub) use ($fileNumber) {
                        $sub->where('mlsFNo', $fileNumber)
                            ->orWhere('kangisFileNo', $fileNumber)
                            ->orWhere('NewKANGISFileno', $fileNumber)
                            ->orWhere('temp_fileno', $fileNumber);
                    });
                }
            }
        });

        // PRA specific filter
        $praQuery->where(function($q) use ($fileNumber, $propId) {
            $hasCond = false;
            if (!empty($propId)) {
                $q->where('prop_id', $propId);
                $hasCond = true;
            }
            if (!empty($fileNumber) && $fileNumber !== '—') {
                if ($hasCond) {
                    $q->orWhere('mlsFNo', $fileNumber)
                      ->orWhere('kangisFileNo', $fileNumber)
                      ->orWhere('NewKANGISFileno', $fileNumber)
                      ->orWhere('fileno', $fileNumber);
                } else {
                    $q->where(function($sub) use ($fileNumber) {
                        $sub->where('mlsFNo', $fileNumber)
                            ->orWhere('kangisFileNo', $fileNumber)
                            ->orWhere('NewKANGISFileno', $fileNumber)
                            ->orWhere('fileno', $fileNumber);
                    });
                }
            }
        });

        // File History Staging specific filter
        $fhsQuery->where(function($q) use ($fileNumber, $propId) {
            $hasCond = false;
            if (!empty($propId)) {
                $q->where('prop_id', $propId);
                $hasCond = true;
            }
            if (!empty($fileNumber) && $fileNumber !== '—') {
                if ($hasCond) {
                    $q->orWhere('mlsFNo', $fileNumber)
                      ->orWhere('kangisFileNo', $fileNumber)
                      ->orWhere('NewKANGISFileno', $fileNumber)
                      ->orWhere('fileno', $fileNumber)
                      ->orWhere('temp_fileno', $fileNumber);
                } else {
                    $q->where(function($sub) use ($fileNumber) {
                        $sub->where('mlsFNo', $fileNumber)
                            ->orWhere('kangisFileNo', $fileNumber)
                            ->orWhere('NewKANGISFileno', $fileNumber)
                            ->orWhere('fileno', $fileNumber)
                            ->orWhere('temp_fileno', $fileNumber);
                    });
                }
            }
        });

        $unionQuery = $icQuery->unionAll($praQuery)->unionAll($fhsQuery);
        return $unionQuery->get()->toArray();
    }
}
