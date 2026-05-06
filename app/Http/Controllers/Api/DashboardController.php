<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    protected $connection = 'sqlsrv';

    /**
     * Get total applications count (mother_applications + subapplications)
     */
    public function getTotalApplications()
    {
        try {
            // Count from mother_applications
            $motherAppsCount = DB::connection($this->connection)
                ->table('mother_applications')
                ->count();

            // Count from subapplications
            $subAppsCount = DB::connection($this->connection)
                ->table('subapplications')
                ->count();

            $totalCount = $motherAppsCount + $subAppsCount;

            // Calculate trend (last 30 days vs previous 30 days)
            $currentMonth = DB::connection($this->connection)
                ->table('mother_applications')
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->count() + 
                DB::connection($this->connection)
                ->table('subapplications')
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->count();

            $previousMonth = DB::connection($this->connection)
                ->table('mother_applications')
                ->whereBetween('created_at', [Carbon::now()->subDays(60), Carbon::now()->subDays(30)])
                ->count() + 
                DB::connection($this->connection)
                ->table('subapplications')
                ->whereBetween('created_at', [Carbon::now()->subDays(60), Carbon::now()->subDays(30)])
                ->count();

            $trend = $previousMonth > 0 ? round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1) : 0;

            return response()->json([
                'count' => number_format($totalCount),
                'trend' => $trend,
                'raw_count' => $totalCount
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'count' => 'Error',
                'trend' => '0',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending approvals count
     */
    public function getPendingApprovals()
    {
        try {
            // Count from mother_applications where planning_recommendation_status is null/pending and application_status is null/pending
            $motherPending = DB::connection($this->connection)
                ->table('mother_applications')
                ->where(function ($query) {
                    $query->whereNull('planning_recommendation_status')
                          ->orWhere('planning_recommendation_status', 'pending');
                })
                ->where(function ($query) {
                    $query->whereNull('application_status')
                          ->orWhere('application_status', 'pending');
                })
                ->count();

            // Count from subapplications with same conditions
            $subPending = DB::connection($this->connection)
                ->table('subapplications')
                ->where(function ($query) {
                    $query->whereNull('planning_recommendation_status')
                          ->orWhere('planning_recommendation_status', 'pending');
                })
                ->where(function ($query) {
                    $query->whereNull('application_status')
                          ->orWhere('application_status', 'pending');
                })
                ->count();

            $totalPending = $motherPending + $subPending;

            // Calculate trend
            $currentMonth = DB::connection($this->connection)
                ->table('mother_applications')
                ->where(function ($query) {
                    $query->whereNull('planning_recommendation_status')
                          ->orWhere('planning_recommendation_status', 'pending');
                })
                ->where(function ($query) {
                    $query->whereNull('application_status')
                          ->orWhere('application_status', 'pending');
                })
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->count() + 
                DB::connection($this->connection)
                ->table('subapplications')
                ->where(function ($query) {
                    $query->whereNull('planning_recommendation_status')
                          ->orWhere('planning_recommendation_status', 'pending');
                })
                ->where(function ($query) {
                    $query->whereNull('application_status')
                          ->orWhere('application_status', 'pending');
                })
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->count();

            return response()->json([
                'count' => number_format($totalPending),
                'trend' => '0', // Simplified for now
                'raw_count' => $totalPending
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'count' => 'Error',
                'trend' => '0',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get indexed files count
     */
    public function getIndexedFiles()
    {
        try {
            $count = DB::connection($this->connection)
                ->table('file_indexings')
                ->count();

            // Calculate trend
            $currentMonth = DB::connection($this->connection)
                ->table('file_indexings')
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->count();

            $previousMonth = DB::connection($this->connection)
                ->table('file_indexings')
                ->whereBetween('created_at', [Carbon::now()->subDays(60), Carbon::now()->subDays(30)])
                ->count();

            $trend = $previousMonth > 0 ? round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1) : 0;

            return response()->json([
                'count' => number_format($count),
                'trend' => $trend,
                'raw_count' => $count
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'count' => 'Error',
                'trend' => '0',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get blind scans count
     */
    public function getBlindScans()
    {
        try {
            $count = DB::connection($this->connection)
                ->table('blind_scannings')
                ->count();

            // Calculate trend
            $currentMonth = DB::connection($this->connection)
                ->table('blind_scannings')
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->count();

            $previousMonth = DB::connection($this->connection)
                ->table('blind_scannings')
                ->whereBetween('created_at', [Carbon::now()->subDays(60), Carbon::now()->subDays(30)])
                ->count();

            $trend = $previousMonth > 0 ? round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1) : 0;

            return response()->json([
                'count' => number_format($count),
                'trend' => $trend,
                'raw_count' => $count
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'count' => 'Error',
                'trend' => '0',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get scan uploads count
     */
    public function getScanUploads()
    {
        try {
            $count = DB::connection($this->connection)
                ->table('scannings')
                ->count();

            // Calculate trend
            $currentMonth = DB::connection($this->connection)
                ->table('scannings')
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->count();

            $previousMonth = DB::connection($this->connection)
                ->table('scannings')
                ->whereBetween('created_at', [Carbon::now()->subDays(60), Carbon::now()->subDays(30)])
                ->count();

            $trend = $previousMonth > 0 ? round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1) : 0;

            return response()->json([
                'count' => number_format($count),
                'trend' => $trend,
                'raw_count' => $count
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'count' => 'Error',
                'trend' => '0',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}