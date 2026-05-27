<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class DashboardController extends Controller
{
    protected $connection = 'sqlsrv';

    /* ─────────────────────────────────────────────────────────────────────
     | COMBINED – single request loads all top-level stats
     | GET /api/dashboard/all-stats
     ───────────────────────────────────────────────────────────────────── */
    public function getAllStats()
    {
        $ttl = 120; // 2-minute cache

        try {
            $stats = Cache::remember('dashboard.all_stats', $ttl, function () {
                $db = DB::connection($this->connection);
                $now = Carbon::now();
                $prev30 = $now->copy()->subDays(30);
                $prev60 = $now->copy()->subDays(60);

                /* --- helpers --- */
                $trend = function ($current, $previous) {
                    if ($previous <= 0) return 0;
                    return round((($current - $previous) / $previous) * 100, 1);
                };

                $countWithTrend = function ($table, $where = []) use ($db, $now, $prev30, $prev60, $trend) {
                    $q = $db->table($table);
                    foreach ($where as $col => $val) {
                        is_array($val) ? $q->whereIn($col, $val) : $q->where($col, $val);
                    }
                    $total   = (clone $q)->count();
                    $curr30  = (clone $q)->where('created_at', '>=', $prev30)->count();
                    $prev30c = (clone $q)->whereBetween('created_at', [$prev60, $prev30])->count();
                    return [
                        'count'    => number_format($total),
                        'raw'      => $total,
                        'trend'    => $trend($curr30, $prev30c),
                    ];
                };

                /* ── Indexed files ────────────────────────────────────── */
                $indexedFiles = $countWithTrend('file_indexings');

                /* ── Blind scans ─────────────────────────────────────── */
                $blindScans = $countWithTrend('blind_scannings');

                /* ── Scan uploads ────────────────────────────────────── */
                $scanUploads = $countWithTrend('scannings');

                /* ── Total applications (mother + sub) ────────────────── */
                $motherTotal  = $db->table('mother_applications')->count();
                $subTotal     = $db->table('subapplications')->count();
                $appTotal     = $motherTotal + $subTotal;

                $motherCurr   = $db->table('mother_applications')->where('created_at', '>=', $prev30)->count();
                $subCurr      = $db->table('subapplications')->where('created_at', '>=', $prev30)->count();
                $motherPrev   = $db->table('mother_applications')->whereBetween('created_at', [$prev60, $prev30])->count();
                $subPrev      = $db->table('subapplications')->whereBetween('created_at', [$prev60, $prev30])->count();
                $appTrend     = $trend($motherCurr + $subCurr, $motherPrev + $subPrev);

                $totalApplications = [
                    'count' => number_format($appTotal),
                    'raw'   => $appTotal,
                    'trend' => $appTrend,
                ];

                /* ── Pending approvals ────────────────────────────────── */
                $pendingMother = $db->table('mother_applications')
                    ->where(function ($q) {
                        $q->whereNull('application_status')
                          ->orWhereIn('application_status', ['pending', 'Pending', 'PENDING']);
                    })->count();
                $pendingSub = $db->table('subapplications')
                    ->where(function ($q) {
                        $q->whereNull('application_status')
                          ->orWhereIn('application_status', ['pending', 'Pending', 'PENDING']);
                    })->count();

                $pendingApprovals = [
                    'count' => number_format($pendingMother + $pendingSub),
                    'raw'   => $pendingMother + $pendingSub,
                    'trend' => 0,
                ];

                /* ── PRA records ──────────────────────────────────────── */
                $praStats = $countWithTrend('pra');

                /* ── IC (instrument_capture) records ─────────────────── */
                $icTotal   = $db->table('instrument_capture')
                    ->where(function ($q) {
                        $q->where('is_deleted', 0)->orWhereNull('is_deleted');
                    })->count();
                $icCurr    = $db->table('instrument_capture')
                    ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
                    ->where('created_at', '>=', $prev30)->count();
                $icPrev    = $db->table('instrument_capture')
                    ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
                    ->whereBetween('created_at', [$prev60, $prev30])->count();

                $icStats = [
                    'count' => number_format($icTotal),
                    'raw'   => $icTotal,
                    'trend' => $trend($icCurr, $icPrev),
                ];

                /* ── FH (file_history_staging) records ───────────────── */
                $fhTotal = $db->table('file_history_staging')->count();
                $fhCurr  = $db->table('file_history_staging')->where('created_at', '>=', $prev30)->count();
                $fhPrev  = $db->table('file_history_staging')->whereBetween('created_at', [$prev60, $prev30])->count();

                $fhStats = [
                    'count' => number_format($fhTotal),
                    'raw'   => $fhTotal,
                    'trend' => $trend($fhCurr, $fhPrev),
                ];

                /* ── MLS Files Commissioned ───────────────────────────── */
                $mlsTotal  = $db->table('mls_file_no')->whereNotNull('commissioning_date')->count();
                $mlsCurr   = $db->table('mls_file_no')->whereNotNull('commissioning_date')
                    ->where('created_at', '>=', $prev30)->count();
                $mlsPrev   = $db->table('mls_file_no')->whereNotNull('commissioning_date')
                    ->whereBetween('created_at', [$prev60, $prev30])->count();

                $mlsCommissioned = [
                    'count' => number_format($mlsTotal),
                    'raw'   => $mlsTotal,
                    'trend' => $trend($mlsCurr, $mlsPrev),
                ];

                /* ── ST Primary Applications (Sectional_Title_File_Numbers) ── */
                $stPrimaryTotal = $db->table('Sectional_Title_File_Numbers')->count();
                $stPrimaryCurr  = $db->table('Sectional_Title_File_Numbers')
                    ->where('created_at', '>=', $prev30)->count();
                $stPrimaryPrev  = $db->table('Sectional_Title_File_Numbers')
                    ->whereBetween('created_at', [$prev60, $prev30])->count();

                $stPrimaryApps = [
                    'count' => number_format($stPrimaryTotal),
                    'raw'   => $stPrimaryTotal,
                    'trend' => $trend($stPrimaryCurr, $stPrimaryPrev),
                ];

                /* ── ST PuAs (Sectional Titling sub-unit applications) ────── */
                $stPuasTotal = $db->table('subapplications')->count();
                $stPuasCurr  = $db->table('subapplications')
                    ->where('created_at', '>=', $prev30)->count();
                $stPuasPrev  = $db->table('subapplications')
                    ->whereBetween('created_at', [$prev60, $prev30])->count();

                $stPuas = [
                    'count' => number_format($stPuasTotal),
                    'raw'   => $stPuasTotal,
                    'trend' => $trend($stPuasCurr, $stPuasPrev),
                ];

                /* ── Information Products ────────────────────────────────── */
                // CofO – from CofO_staging (all historical + new records)
                $cofOTotal = $db->table('CofO_staging')
                    ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })
                    ->count();

                // Right of Occupancy – land_recommendations + sltr_recommendations
                $rofoTotal = $db->table('land_recommendations')->count()
                           + $db->table('sltr_recommendations')->count();

                // Letter of Administration – no dedicated table yet
                $loaTotal = 0;

                // Occupancy Permit – instrument_capture (OP type, not deleted)
                $opTotal = $db->table('instrument_capture')
                    ->where('instrument_type', 'Occupancy Permit (OP)')
                    ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
                    ->count();

                // Site Plan / Parcel Plan – recommended_site_plans
                $sitePlanTotal = $db->table('recommended_site_plans')->count();

                // Sectional Title Deeds – eRegistry (all commissioned)
                $stDeedsTotal = $db->table('eRegistry')->count();

                $infoProducts = [
                    'cofo'            => ['count' => number_format($cofOTotal),      'raw' => $cofOTotal],
                    'rofo'            => ['count' => number_format($rofoTotal),       'raw' => $rofoTotal],
                    'loa'             => ['count' => number_format($loaTotal),        'raw' => $loaTotal],
                    'occupancy_permit' => ['count' => number_format($opTotal),        'raw' => $opTotal],
                    'site_plan'       => ['count' => number_format($sitePlanTotal),   'raw' => $sitePlanTotal],
                    'st_deeds'        => ['count' => number_format($stDeedsTotal),    'raw' => $stDeedsTotal],
                ];

                /* ── Approved / Rejected applications ────────────────── */
                $approvedMother = $db->table('mother_applications')
                    ->whereIn('application_status', ['approved', 'Approved', 'APPROVED'])->count();
                $approvedSub = $db->table('subapplications')
                    ->whereIn('application_status', ['approved', 'Approved', 'APPROVED'])->count();
                $approvedTotal = $approvedMother + $approvedSub;

                $rejectedMother = $db->table('mother_applications')
                    ->whereIn('application_status', ['rejected', 'Rejected', 'REJECTED'])->count();
                $rejectedSub = $db->table('subapplications')
                    ->whereIn('application_status', ['rejected', 'Rejected', 'REJECTED'])->count();
                $rejectedTotal = $rejectedMother + $rejectedSub;

                /* ── Recent applications (latest 5 from mother_applications) ── */
                $recentApps = $db->table('mother_applications')
                    ->select([
                        'id', 'applicationID',
                        'first_name', 'surname', 'corporate_name', 'applicant_type',
                        'application_type', 'application_status',
                        'created_at', 'fileno', 'applied_file_number',
                    ])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($row) {
                        // Resolve applicant display name
                        $applicant = trim(($row->first_name ?? '') . ' ' . ($row->surname ?? ''));
                        if (empty(trim($applicant)) && !empty($row->corporate_name)) {
                            $applicant = $row->corporate_name;
                        }
                        if (empty(trim($applicant))) {
                            $applicant = '—';
                        }

                        // Resolve file number
                        $fileNo = $row->applied_file_number ?? $row->fileno ?? '—';

                        // Resolve display application ID
                        $appId = $row->applicationID
                            ? $row->applicationID
                            : 'APP-' . str_pad($row->id ?? 0, 6, '0', STR_PAD_LEFT);

                        return [
                            'id'          => $row->id ?? null,
                            'app_id'      => $appId,
                            'applicant'   => $applicant,
                            'type'        => $row->application_type ?? '—',
                            'status'      => $row->application_status ?? 'pending',
                            'file_number' => $fileNo,
                            'date'        => $row->created_at
                                ? Carbon::parse($row->created_at)->format('Y-m-d')
                                : '—',
                        ];
                    });

                /* ── Weekly application trend (last 7 days) ──────────── */
                $weeklyData = [];
                for ($i = 6; $i >= 0; $i--) {
                    $day     = $now->copy()->subDays($i);
                    $dayStr  = $day->format('D');
                    $dayDate = $day->toDateString();

                    $dayCount = $db->table('mother_applications')
                        ->whereDate('created_at', $dayDate)->count()
                        + $db->table('subapplications')
                        ->whereDate('created_at', $dayDate)->count();

                    $weeklyData[] = ['day' => $dayStr, 'count' => $dayCount, 'date' => $dayDate];
                }

                /* ── Application type breakdown ──────────────────────── */
                $appTypes = $db->table('mother_applications')
                    ->select('application_type', DB::raw('COUNT(*) as cnt'))
                    ->whereNotNull('application_type')
                    ->groupBy('application_type')
                    ->orderByDesc('cnt')
                    ->limit(5)
                    ->get()
                    ->map(fn($r) => ['type' => $r->application_type, 'count' => (int) $r->cnt]);

                return [
                    'indexed_files'      => $indexedFiles,
                    'blind_scans'        => $blindScans,
                    'scan_uploads'       => $scanUploads,
                    'total_applications' => $totalApplications,
                    'pending_approvals'  => $pendingApprovals,
                    'pra_records'        => $praStats,
                    'ic_records'         => $icStats,
                    'fh_records'         => $fhStats,
                    'mls_commissioned'   => $mlsCommissioned,
                    'st_primary_apps'    => $stPrimaryApps,
                    'st_puas'            => $stPuas,
                    'info_products'      => $infoProducts,
                    'approved_apps'      => $approvedTotal,
                    'rejected_apps'      => $rejectedTotal,
                    'recent_apps'        => $recentApps,
                    'weekly_trend'       => $weeklyData,
                    'app_type_breakdown' => $appTypes,
                    'generated_at'       => $now->toDateTimeString(),
                ];
            });

            return response()->json(['success' => true, 'data' => $stats]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
            ], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────────────────
     | INDIVIDUAL endpoints (kept for backward compatibility)
     ───────────────────────────────────────────────────────────────────── */

    public function getTotalApplications()
    {
        try {
            $motherCount = DB::connection($this->connection)->table('mother_applications')->count();
            $subCount    = DB::connection($this->connection)->table('subapplications')->count();
            $total       = $motherCount + $subCount;

            $now     = Carbon::now();
            $curr    = DB::connection($this->connection)->table('mother_applications')->where('created_at', '>=', $now->copy()->subDays(30))->count()
                     + DB::connection($this->connection)->table('subapplications')->where('created_at', '>=', $now->copy()->subDays(30))->count();
            $prev    = DB::connection($this->connection)->table('mother_applications')->whereBetween('created_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])->count()
                     + DB::connection($this->connection)->table('subapplications')->whereBetween('created_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])->count();

            $trend = $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : 0;

            return response()->json(['count' => number_format($total), 'trend' => $trend, 'raw_count' => $total]);
        } catch (\Exception $e) {
            return response()->json(['count' => 'Error', 'trend' => '0', 'error' => $e->getMessage()], 500);
        }
    }

    public function getPendingApprovals()
    {
        try {
            $pending = DB::connection($this->connection)->table('mother_applications')
                ->where(function ($q) { $q->whereNull('application_status')->orWhereIn('application_status', ['pending', 'Pending', 'PENDING']); })->count()
                + DB::connection($this->connection)->table('subapplications')
                ->where(function ($q) { $q->whereNull('application_status')->orWhereIn('application_status', ['pending', 'Pending', 'PENDING']); })->count();

            return response()->json(['count' => number_format($pending), 'trend' => '0', 'raw_count' => $pending]);
        } catch (\Exception $e) {
            return response()->json(['count' => 'Error', 'trend' => '0', 'error' => $e->getMessage()], 500);
        }
    }

    public function getIndexedFiles()
    {
        try {
            $count = DB::connection($this->connection)->table('file_indexings')->count();
            $now   = Carbon::now();
            $curr  = DB::connection($this->connection)->table('file_indexings')->where('created_at', '>=', $now->copy()->subDays(30))->count();
            $prev  = DB::connection($this->connection)->table('file_indexings')->whereBetween('created_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])->count();
            $trend = $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : 0;

            return response()->json(['count' => number_format($count), 'trend' => $trend, 'raw_count' => $count]);
        } catch (\Exception $e) {
            return response()->json(['count' => 'Error', 'trend' => '0', 'error' => $e->getMessage()], 500);
        }
    }

    public function getBlindScans()
    {
        try {
            $count = DB::connection($this->connection)->table('blind_scannings')->count();
            $now   = Carbon::now();
            $curr  = DB::connection($this->connection)->table('blind_scannings')->where('created_at', '>=', $now->copy()->subDays(30))->count();
            $prev  = DB::connection($this->connection)->table('blind_scannings')->whereBetween('created_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])->count();
            $trend = $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : 0;

            return response()->json(['count' => number_format($count), 'trend' => $trend, 'raw_count' => $count]);
        } catch (\Exception $e) {
            return response()->json(['count' => 'Error', 'trend' => '0', 'error' => $e->getMessage()], 500);
        }
    }

    public function getScanUploads()
    {
        try {
            $count = DB::connection($this->connection)->table('scannings')->count();
            $now   = Carbon::now();
            $curr  = DB::connection($this->connection)->table('scannings')->where('created_at', '>=', $now->copy()->subDays(30))->count();
            $prev  = DB::connection($this->connection)->table('scannings')->whereBetween('created_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])->count();
            $trend = $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : 0;

            return response()->json(['count' => number_format($count), 'trend' => $trend, 'raw_count' => $count]);
        } catch (\Exception $e) {
            return response()->json(['count' => 'Error', 'trend' => '0', 'error' => $e->getMessage()], 500);
        }
    }

    /* ── New individual endpoints ──────────────────────────────────────── */

    public function getPraRecords()
    {
        try {
            $count = DB::connection($this->connection)->table('pra')->count();
            return response()->json(['count' => number_format($count), 'trend' => 0, 'raw_count' => $count]);
        } catch (Throwable $e) {
            return response()->json(['count' => 'Error', 'trend' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    public function getIcRecords()
    {
        try {
            $count = DB::connection($this->connection)->table('instrument_capture')
                ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
                ->count();
            return response()->json(['count' => number_format($count), 'trend' => 0, 'raw_count' => $count]);
        } catch (Throwable $e) {
            return response()->json(['count' => 'Error', 'trend' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    public function getFhRecords()
    {
        try {
            $count = DB::connection($this->connection)->table('file_history_staging')->count();
            return response()->json(['count' => number_format($count), 'trend' => 0, 'raw_count' => $count]);
        } catch (Throwable $e) {
            return response()->json(['count' => 'Error', 'trend' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    public function getMlsCommissioned()
    {
        try {
            $count = DB::connection($this->connection)->table('mls_file_no')
                ->whereNotNull('commissioning_date')
                ->count();
            return response()->json(['count' => number_format($count), 'trend' => 0, 'raw_count' => $count]);
        } catch (Throwable $e) {
            return response()->json(['count' => 'Error', 'trend' => 0, 'error' => $e->getMessage()], 500);
        }
    }
}
