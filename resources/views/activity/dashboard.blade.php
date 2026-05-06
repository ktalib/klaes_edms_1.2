@extends('layouts.app')

@section('title', 'Activity Log Dashboard')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Activity Log Dashboard</h1>
            <p class="text-gray-600 mt-1">Real-time user activity monitoring</p>
        </div>
        
        <!-- Date Range Selector -->
        <div class="flex gap-2">
            @foreach([7, 14, 30] as $dayOption)
                <a href="?days={{ $dayOption }}" 
                   class="px-4 py-2 rounded-lg transition 
                   {{ $days == $dayOption ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300' }}">
                    {{ $dayOption }} Days
                </a>
            @endforeach
        </div>
    </div>

    <!-- Key Metrics Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Online Users -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Online Users</p>
                    <p class="text-3xl font-bold text-green-600 mt-1" id="online-count">
                        {{ $stats['online_count'] }}
                    </p>
                </div>
                <div class="bg-green-100 rounded-full p-3">
                    <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v4h8v-4zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Total Sessions Today -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Total Sessions</p>
                    <p class="text-3xl font-bold text-blue-600 mt-1">{{ $stats['total_sessions'] }}</p>
                </div>
                <div class="bg-blue-100 rounded-full p-3">
                    <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 1 1 0 000 2H3a1 1 0 00-1 1v10a1 1 0 001 1h14a1 1 0 001-1V6a1 1 0 00-1-1h-3a1 1 0 000-2 2 2 0 00-2-2H6a2 2 0 00-2 2zm5 4a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Average Duration -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Avg Duration</p>
                    <p class="text-3xl font-bold text-purple-600 mt-1">{{ $stats['avg_duration'] }}m</p>
                </div>
                <div class="bg-purple-100 rounded-full p-3">
                    <svg class="w-6 h-6 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Unique Users -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Unique Users</p>
                    <p class="text-3xl font-bold text-orange-600 mt-1">{{ $stats['unique_users'] }}</p>
                </div>
                <div class="bg-orange-100 rounded-full p-3">
                    <svg class="w-6 h-6 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM9 11a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM17 16a1 1 0 10-2 0v2a1 1 0 102 0v-2z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Activity Timeline Chart -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Activity Timeline</h3>
            <canvas id="timeline-chart" height="300"></canvas>
        </div>

        <!-- Browser Distribution Chart -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Browser Distribution</h3>
            <canvas id="browser-chart" height="300"></canvas>
        </div>
    </div>

    <!-- Statistics Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Browser Stats Table -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Browsers</h3>
            <div class="space-y-2">
                @forelse($browserStats as $stat)
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">{{ $stat->browser ?? 'Unknown' }}</span>
                        <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                            {{ $stat->count }}
                        </span>
                    </div>
                @empty
                    <p class="text-gray-500">No data available</p>
                @endforelse
            </div>
        </div>

        <!-- Platform Stats Table -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Platforms</h3>
            <div class="space-y-2">
                @forelse($platformStats as $stat)
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">{{ $stat->platform ?? 'Unknown' }}</span>
                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                            {{ $stat->count }}
                        </span>
                    </div>
                @empty
                    <p class="text-gray-500">No data available</p>
                @endforelse
            </div>
        </div>

        <!-- Device Stats Table -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Devices</h3>
            <div class="space-y-2">
                @forelse($deviceStats as $stat)
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">{{ $stat->device ?? 'Unknown' }}</span>
                        <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-medium">
                            {{ $stat->count }}
                        </span>
                    </div>
                @empty
                    <p class="text-gray-500">No data available</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
            <a href="{{ route('activity-logs.index') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                View All →
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">User</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Login Time</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Duration</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Browser</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($recentActivity as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <div class="font-medium">{{ $log->user?->name ?? 'Unknown' }}</div>
                                <div class="text-xs text-gray-500">{{ $log->user?->email }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $log->login_time?->format('M d, Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $log->duration_minutes ?? '-' }} min
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $log->browser ?? 'Unknown' }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @php
                                    $statusColors = [
                                        'Online' => 'bg-green-100 text-green-800',
                                        'Offline' => 'bg-gray-100 text-gray-800',
                                        'Idle' => 'bg-yellow-100 text-yellow-800',
                                        'Stale' => 'bg-red-100 text-red-800',
                                    ];
                                @endphp
                                <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusColors[$log->status] ?? 'bg-gray-100' }}">
                                    {{ $log->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                No activity records found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<!-- Activity Dashboard JS -->
<script src="{{ asset('js/activity-log-dashboard.js') }}"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dashboardData = {
        timeline: @json($chartData),
        browserStats: @json($browserStats->mapWithKeys(fn($s) => [$s->browser => $s->count])->toArray()),
        platformStats: @json($platformStats->mapWithKeys(fn($s) => [$s->platform => $s->count])->toArray()),
        deviceStats: @json($deviceStats->mapWithKeys(fn($s) => [$s->device => $s->count])->toArray()),
    };

    // Timeline Chart
    const timelineCtx = document.getElementById('timeline-chart');
    if (timelineCtx) {
        new Chart(timelineCtx, {
            type: 'line',
            data: {
                labels: dashboardData.timeline.labels,
                datasets: [{
                    label: 'Sessions',
                    data: dashboardData.timeline.data,
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 5,
                    pointBackgroundColor: '#3B82F6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                        }
                    }
                }
            }
        });
    }

    // Browser Chart
    const browserCtx = document.getElementById('browser-chart');
    if (browserCtx) {
        new Chart(browserCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(dashboardData.browserStats),
                datasets: [{
                    data: Object.values(dashboardData.browserStats),
                    backgroundColor: [
                        '#3B82F6',
                        '#10B981',
                        '#F59E0B',
                        '#EF4444',
                        '#8B5CF6',
                        '#EC4899',
                        '#14B8A6',
                        '#F97316',
                        '#06B6D4',
                        '#6366F1',
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12,
                            }
                        }
                    }
                }
            }
        });
    }

    // Real-time updates
    setInterval(function() {
        updateOnlineCount();
        updateDashboardStats();
    }, 60000); // Update every 60 seconds

    function updateOnlineCount() {
        fetch('/api/activity/online-count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('online-count').textContent = data.data.online_count;
                }
            })
            .catch(error => console.error('Error updating online count:', error));
    }

    function updateDashboardStats() {
        const days = new URLSearchParams(window.location.search).get('days') || 7;
        fetch('/api/activity/dashboard-stats?days=' + days)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update stats display if needed
                    console.log('Stats updated:', data.data);
                }
            })
            .catch(error => console.error('Error updating stats:', error));
    }
});
</script>
@endsection
