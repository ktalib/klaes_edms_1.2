<?php

namespace App\Http\Controllers;

use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * ActivityLogAnalyticsController
 *
 * Provides advanced analytics and insights into user activity patterns,
 * trends, session statistics, and device analytics.
 */
class ActivityLogAnalyticsController extends Controller
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Get activity trends over time
     *
     * GET /api/analytics/trends?days=30&granularity=daily
     *
     * Query Parameters:
     *   - days: int (default 30, max 365)
     *   - granularity: string (hourly|daily|weekly|monthly, default daily)
     *
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "labels": ["Nov 01", "Nov 02", ...],
     *     "sessions": [45, 52, 38, ...],
     *     "unique_users": [15, 18, 12, ...],
     *     "avg_duration": [42.5, 48.2, 39.1, ...]
     *   }
     * }
     */
    public function getActivityTrends(Request $request)
    {
        try {
            // Authorization
            if (!Auth::user()->isAdmin() && !Auth::user()->can('view-analytics')) {
                return $this->respondForbidden('Unauthorized');
            }

            $days = min((int)$request->input('days', 30), 365);
            $granularity = $request->input('granularity', 'daily');

            $startDate = Carbon::now()->subDays($days);

            // Build query based on granularity
            $query = UserActivityLog::where('login_time', '>=', $startDate);

            $selectClause = match($granularity) {
                'hourly' => "DATEPART(day, login_time) as period, COUNT(*) as session_count, COUNT(DISTINCT user_id) as unique_users, AVG(CAST(duration_minutes as FLOAT)) as avg_duration",
                'weekly' => "DATEPART(week, login_time) as period, COUNT(*) as session_count, COUNT(DISTINCT user_id) as unique_users, AVG(CAST(duration_minutes as FLOAT)) as avg_duration",
                'monthly' => "DATEPART(month, login_time) as period, COUNT(*) as session_count, COUNT(DISTINCT user_id) as unique_users, AVG(CAST(duration_minutes as FLOAT)) as avg_duration",
                default => "CAST(login_time as DATE) as period, COUNT(*) as session_count, COUNT(DISTINCT user_id) as unique_users, AVG(CAST(duration_minutes as FLOAT)) as avg_duration",
            };

            $groupByClause = match($granularity) {
                'hourly' => 'DATEPART(day, login_time)',
                'weekly' => 'DATEPART(week, login_time)',
                'monthly' => 'DATEPART(month, login_time)',
                default => 'CAST(login_time as DATE)',
            };

            $trends = DB::connection('sqlsrv')
                ->table('user_activity_logs')
                ->where('login_time', '>=', $startDate)
                ->selectRaw($selectClause)
                ->groupByRaw($groupByClause)
                ->orderByRaw('period ASC')
                ->get();

            $labels = [];
            $sessions = [];
            $uniqueUsers = [];
            $avgDurations = [];

            foreach ($trends as $trend) {
                $labels[] = $this->formatPeriodLabel($trend->period, $granularity);
                $sessions[] = (int)$trend->session_count;
                $uniqueUsers[] = (int)$trend->unique_users;
                $avgDurations[] = round($trend->avg_duration ?? 0, 2);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => $labels,
                    'sessions' => $sessions,
                    'unique_users' => $uniqueUsers,
                    'avg_duration' => $avgDurations,
                    'granularity' => $granularity,
                    'period_days' => $days,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching activity trends: {$e->getMessage()}");
            return $this->respondError('Failed to fetch activity trends');
        }
    }

    /**
     * Get top active users
     *
     * GET /api/analytics/top-users?days=30&limit=10
     *
     * Query Parameters:
     *   - days: int (default 30)
     *   - limit: int (default 10, max 50)
     *
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "users": [
     *       {
     *         "user_id": 1,
     *         "name": "Musa Ali",
     *         "email": "john@example.com",
     *         "sessions": 45,
     *         "total_duration": 1800,
     *         "avg_duration": 40,
     *         "last_login": "2025-11-10 14:30:00"
     *       }
     *     ]
     *   }
     * }
     */
    public function getTopUsers(Request $request)
    {
        try {
            if (!Auth::user()->isAdmin() && !Auth::user()->can('view-analytics')) {
                return $this->respondForbidden('Unauthorized');
            }

            $days = (int)$request->input('days', 30);
            $limit = min((int)$request->input('limit', 10), 50);

            $startDate = Carbon::now()->subDays($days);

            $topUsers = DB::connection('sqlsrv')
                ->table('user_activity_logs as logs')
                ->leftJoin('users', 'logs.user_id', '=', 'users.id')
                ->where('logs.login_time', '>=', $startDate)
                ->selectRaw('
                    logs.user_id,
                    users.name,
                    users.email,
                    COUNT(*) as sessions,
                    SUM(CAST(logs.duration_minutes as INT)) as total_duration,
                    AVG(CAST(logs.duration_minutes as FLOAT)) as avg_duration,
                    MAX(logs.login_time) as last_login
                ')
                ->groupBy('logs.user_id', 'users.name', 'users.email')
                ->orderByRaw('sessions DESC')
                ->limit($limit)
                ->get();

            $users = $topUsers->map(function ($user) {
                return [
                    'user_id' => (int)$user->user_id,
                    'name' => $user->name ?? 'Unknown',
                    'email' => $user->email ?? 'N/A',
                    'sessions' => (int)$user->sessions,
                    'total_duration' => (int)($user->total_duration ?? 0),
                    'avg_duration' => round($user->avg_duration ?? 0, 2),
                    'last_login' => $user->last_login ? Carbon::parse($user->last_login)->format('Y-m-d H:i:s') : 'N/A',
                ];
            })->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'users' => $users,
                    'count' => count($users),
                    'period_days' => $days,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching top users: {$e->getMessage()}");
            return $this->respondError('Failed to fetch top users');
        }
    }

    /**
     * Get session statistics by user
     *
     * GET /api/analytics/session-stats?days=30
     *
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "total_sessions": 1250,
     *     "avg_sessions_per_user": 8.3,
     *     "total_duration_hours": 850.5,
     *     "avg_duration_per_session": 40.8,
     *     "min_duration": 5,
     *     "max_duration": 480,
     *     "median_duration": 35
     *   }
     * }
     */
    public function getUserSessionStats(Request $request)
    {
        try {
            if (!Auth::user()->isAdmin() && !Auth::user()->can('view-analytics')) {
                return $this->respondForbidden('Unauthorized');
            }

            $days = (int)$request->input('days', 30);
            $startDate = Carbon::now()->subDays($days);

            // Total sessions
            $totalSessions = UserActivityLog::where('login_time', '>=', $startDate)->count();

            // Unique users
            $uniqueUsers = UserActivityLog::where('login_time', '>=', $startDate)
                ->distinct('user_id')
                ->count('user_id');

            // Duration statistics
            $durationStats = DB::connection('sqlsrv')
                ->selectOne(
                    "SELECT 
                        AVG(CAST(duration_minutes as FLOAT)) as avg_duration,
                        MIN(duration_minutes) as min_duration,
                        MAX(duration_minutes) as max_duration,
                        SUM(CAST(duration_minutes as BIGINT)) / 60.0 as total_duration_hours
                    FROM user_activity_logs 
                    WHERE login_time >= ? AND duration_minutes IS NOT NULL",
                    [$startDate]
                );

            // Median duration
            $median = DB::connection('sqlsrv')
                ->selectOne(
                    "SELECT PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY duration_minutes) 
                     OVER () as median_duration
                    FROM user_activity_logs 
                    WHERE login_time >= ? AND duration_minutes IS NOT NULL",
                    [$startDate]
                );

            return response()->json([
                'success' => true,
                'data' => [
                    'total_sessions' => $totalSessions,
                    'unique_users' => $uniqueUsers,
                    'avg_sessions_per_user' => $uniqueUsers > 0 ? round($totalSessions / $uniqueUsers, 2) : 0,
                    'avg_duration_per_session' => round($durationStats->avg_duration ?? 0, 2),
                    'total_duration_hours' => round($durationStats->total_duration_hours ?? 0, 2),
                    'min_duration' => (int)($durationStats->min_duration ?? 0),
                    'max_duration' => (int)($durationStats->max_duration ?? 0),
                    'median_duration' => round($median->median_duration ?? 0, 2),
                    'period_days' => $days,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching session stats: {$e->getMessage()}");
            return $this->respondError('Failed to fetch session statistics');
        }
    }

    /**
     * Get device and browser analytics
     *
     * GET /api/analytics/device-analytics?days=30
     *
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "browsers": { "Chrome": 750, "Firefox": 200, ... },
     *     "platforms": { "Windows": 600, "Linux": 250, ... },
     *     "devices": { "Desktop": 850, "Mobile": 100, ... }
     *   }
     * }
     */
    public function getDeviceAnalytics(Request $request)
    {
        try {
            if (!Auth::user()->isAdmin() && !Auth::user()->can('view-analytics')) {
                return $this->respondForbidden('Unauthorized');
            }

            $days = (int)$request->input('days', 30);
            $startDate = Carbon::now()->subDays($days);

            // Browser analytics
            $browsers = DB::connection('sqlsrv')
                ->table('user_activity_logs')
                ->where('login_time', '>=', $startDate)
                ->where('browser', '!=', null)
                ->groupBy('browser')
                ->selectRaw('browser, COUNT(*) as count')
                ->orderByRaw('count DESC')
                ->limit(15)
                ->pluck('count', 'browser');

            // Platform analytics
            $platforms = DB::connection('sqlsrv')
                ->table('user_activity_logs')
                ->where('login_time', '>=', $startDate)
                ->where('platform', '!=', null)
                ->groupBy('platform')
                ->selectRaw('platform, COUNT(*) as count')
                ->orderByRaw('count DESC')
                ->limit(15)
                ->pluck('count', 'platform');

            // Device analytics
            $devices = DB::connection('sqlsrv')
                ->table('user_activity_logs')
                ->where('login_time', '>=', $startDate)
                ->where('device', '!=', null)
                ->groupBy('device')
                ->selectRaw('device, COUNT(*) as count')
                ->orderByRaw('count DESC')
                ->limit(15)
                ->pluck('count', 'device');

            return response()->json([
                'success' => true,
                'data' => [
                    'browsers' => $browsers->toArray(),
                    'platforms' => $platforms->toArray(),
                    'devices' => $devices->toArray(),
                    'total_records' => $browsers->sum() + $platforms->sum() + $devices->sum(),
                    'period_days' => $days,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching device analytics: {$e->getMessage()}");
            return $this->respondError('Failed to fetch device analytics');
        }
    }

    /**
     * Get peak activity hours (heatmap data)
     *
     * GET /api/analytics/peak-hours?days=30
     *
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "hours": [0, 1, 2, ..., 23],
     *     "activity": [15, 12, 8, ..., 45],
     *     "peak_hour": 14,
     *     "peak_count": 150
     *   }
     * }
     */
    public function getPeakHours(Request $request)
    {
        try {
            if (!Auth::user()->isAdmin() && !Auth::user()->can('view-analytics')) {
                return $this->respondForbidden('Unauthorized');
            }

            $days = (int)$request->input('days', 30);
            $startDate = Carbon::now()->subDays($days);

            $hourlyActivity = DB::connection('sqlsrv')
                ->table('user_activity_logs')
                ->where('login_time', '>=', $startDate)
                ->selectRaw('DATEPART(hour, login_time) as hour, COUNT(*) as count')
                ->groupByRaw('DATEPART(hour, login_time)')
                ->orderByRaw('hour ASC')
                ->get();

            // Create array with all 24 hours
            $hours = array_fill(0, 24, 0);
            $peakHour = 0;
            $peakCount = 0;

            foreach ($hourlyActivity as $activity) {
                $hours[$activity->hour] = (int)$activity->count;
                if ((int)$activity->count > $peakCount) {
                    $peakCount = (int)$activity->count;
                    $peakHour = $activity->hour;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'hours' => array_keys($hours),
                    'activity' => array_values($hours),
                    'peak_hour' => $peakHour,
                    'peak_count' => $peakCount,
                    'average_activity' => round(array_sum($hours) / 24, 2),
                    'period_days' => $days,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching peak hours: {$e->getMessage()}");
            return $this->respondError('Failed to fetch peak hours data');
        }
    }

    /**
     * Format period label based on granularity
     */
    private function formatPeriodLabel($period, $granularity): string
    {
        return match($granularity) {
            'hourly' => "Day {$period}",
            'weekly' => "Week {$period}",
            'monthly' => "Month {$period}",
            default => Carbon::parse($period)->format('M d'),
        };
    }

    /**
     * Helper: Respond with error
     */
    private function respondError(string $message, int $code = 500): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], $code);
    }

    /**
     * Helper: Respond with forbidden
     */
    private function respondForbidden(string $message = 'Forbidden'): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 403);
    }
}
