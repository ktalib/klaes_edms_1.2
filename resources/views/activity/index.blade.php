@extends('layouts.app')

@section('title', 'Activity Logs')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Activity Logs</h1>
            <p class="text-gray-600 mt-1">View and manage user activity records</p>
        </div>
        
        <div class="flex gap-2">
            <a href="{{ route('activity-dashboard.index') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                Dashboard
            </a>
            <button onclick="exportLogs()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                Export CSV
            </button>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Filters</h3>
        
        <form id="filter-form" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4" onsubmit="return applyFilters(event)">
            <!-- Status Filter -->
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Statuses</option>
                    <option value="online" {{ request('status') === 'online' ? 'selected' : '' }}>Online</option>
                    <option value="offline" {{ request('status') === 'offline' ? 'selected' : '' }}>Offline</option>
                    <option value="idle" {{ request('status') === 'idle' ? 'selected' : '' }}>Idle</option>
                    <option value="stale" {{ request('status') === 'stale' ? 'selected' : '' }}>Stale</option>
                </select>
            </div>

            <!-- User Filter -->
            <div>
                <label for="user_search" class="block text-sm font-medium text-gray-700 mb-2">User</label>
                <input type="text" id="user_search" name="search" placeholder="Name or email..." 
                       value="{{ request('search') }}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Date From Filter -->
            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Date To Filter -->
            <div>
                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Browser Filter -->
            <div>
                <label for="browser" class="block text-sm font-medium text-gray-700 mb-2">Browser</label>
                <input type="text" id="browser" name="browser" placeholder="Filter by browser..." 
                       value="{{ request('browser') }}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Device Filter -->
            <div>
                <label for="device" class="block text-sm font-medium text-gray-700 mb-2">Device</label>
                <input type="text" id="device" name="device" placeholder="Filter by device..." 
                       value="{{ request('device') }}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Sort Filter -->
            <div>
                <label for="sort" class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                <select id="sort" name="sort" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="-login_time" {{ request('sort') === '-login_time' ? 'selected' : '' }}>Latest Login</option>
                    <option value="login_time" {{ request('sort') === 'login_time' ? 'selected' : '' }}>Oldest Login</option>
                    <option value="-duration_minutes" {{ request('sort') === '-duration_minutes' ? 'selected' : '' }}>Longest Duration</option>
                    <option value="duration_minutes" {{ request('sort') === 'duration_minutes' ? 'selected' : '' }}>Shortest Duration</option>
                </select>
            </div>

            <!-- Buttons -->
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                    Apply Filters
                </button>
                <a href="{{ route('activity-logs.index') }}" class="flex-1 px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition font-medium text-center">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Results Summary -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <p class="text-blue-900">
            Showing <strong>{{ $logs->count() }}</strong> records 
            (Page <strong>{{ $logs->currentPage() }}</strong> of <strong>{{ $logs->lastPage() }}</strong>)
            • Total: <strong>{{ $logs->total() }}</strong> records
        </p>
    </div>

    <!-- Activity Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">User</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Login Time</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Logout Time</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Duration</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Browser</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Device</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">IP Address</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Status</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <div class="font-medium">{{ $log->user?->name ?? 'Unknown' }}</div>
                                <div class="text-xs text-gray-500">{{ $log->user?->email }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $log->login_time?->format('M d, Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $log->logout_time?->format('M d, Y H:i') ?? 'Still online' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $log->duration_minutes ?? '-' }} min
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <span class="bg-gray-100 px-2 py-1 rounded text-xs">{{ $log->browser ?? 'Unknown' }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <span class="bg-gray-100 px-2 py-1 rounded text-xs">{{ $log->device ?? 'Unknown' }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ $log->ip_address ?? 'N/A' }}</code>
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
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ route('activity-logs.show', $log->id) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                                No activity records found matching your filters
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    @if($logs->lastPage() > 1)
        <div class="mt-6 flex justify-center">
            {{ $logs->links('pagination::tailwind') }}
        </div>
    @endif
</div>

<script>
function applyFilters(event) {
    event.preventDefault();
    
    // Collect filter values
    const params = new URLSearchParams();
    
    const status = document.getElementById('status').value;
    const search = document.getElementById('user_search').value;
    const dateFrom = document.getElementById('date_from').value;
    const dateTo = document.getElementById('date_to').value;
    const sort = document.getElementById('sort').value;

    if (status) params.append('status', status);
    if (search) params.append('search', search);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    if (sort) params.append('sort', sort);

    // Redirect with filters
    window.location.href = '?' + params.toString();
    return false;
}

function exportLogs() {
    // Collect current filters
    const status = document.getElementById('status').value;
    const dateFrom = document.getElementById('date_from').value;
    const dateTo = document.getElementById('date_to').value;
    
    let url = '/api/activity-logs/export?format=csv';
    if (status) url += '&status=' + status;
    if (dateFrom) url += '&date_from=' + dateFrom;
    if (dateTo) url += '&date_to=' + dateTo;
    
    window.location.href = url;
}

// Load filter values from localStorage
document.addEventListener('DOMContentLoaded', function() {
    // Optional: Persist filter state
    const form = document.getElementById('filter-form');
    form.addEventListener('change', function() {
        // Could save state to localStorage here
    });
});
</script>
@endsection
