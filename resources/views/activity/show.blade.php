@extends('layouts.app')

@section('title', 'Activity Log Details')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Activity Log Details</h1>
            <p class="text-gray-600 mt-1">
                <a href="{{ route('activity-logs.index') }}" class="text-blue-600 hover:text-blue-800">← Back to Logs</a>
            </p>
        </div>

        <div class="flex gap-2">
            @can('delete-activity-logs')
                <form method="POST" action="{{ route('activity-logs.destroy', $log->id) }}" style="display: inline;" 
                      onsubmit="return confirm('Are you sure? This action cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                        Delete
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <!-- User Information Card -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">User Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <p class="text-sm text-gray-600">Name</p>
                <p class="text-lg font-medium text-gray-900">{{ $log->user?->name ?? 'Unknown' }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Email</p>
                <p class="text-lg font-medium text-gray-900">{{ $log->user?->email ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-600">User ID</p>
                <p class="text-lg font-medium text-gray-900">#{{ $log->user_id }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Status</p>
                @php
                    $statusColors = [
                        'Online' => 'bg-green-100 text-green-800',
                        'Offline' => 'bg-gray-100 text-gray-800',
                        'Idle' => 'bg-yellow-100 text-yellow-800',
                        'Stale' => 'bg-red-100 text-red-800',
                    ];
                @endphp
                <span class="inline-block px-4 py-2 rounded-full text-sm font-medium {{ $statusColors[$log->status] ?? 'bg-gray-100' }}">
                    {{ $log->status }}
                </span>
            </div>
        </div>
    </div>

    <!-- Session Information Card -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Session Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <p class="text-sm text-gray-600">Login Time</p>
                <p class="text-lg font-medium text-gray-900">
                    {{ $log->login_time?->format('F d, Y \a\t H:i:s') ?? 'N/A' }}
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Logout Time</p>
                <p class="text-lg font-medium text-gray-900">
                    {{ $log->logout_time?->format('F d, Y \a\t H:i:s') ?? 'Still logged in' }}
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Duration</p>
                <p class="text-lg font-medium text-gray-900">
                    {{ $log->duration_minutes ?? 'N/A' }} minutes
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Last Seen</p>
                <p class="text-lg font-medium text-gray-900">
                    {{ $log->last_seen_at?->format('F d, Y \a\t H:i:s') ?? 'N/A' }}
                </p>
            </div>
        </div>
    </div>

    <!-- Device Information Card -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Device Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <p class="text-sm text-gray-600">IP Address</p>
                <p class="text-lg font-mono text-gray-900 bg-gray-100 px-3 py-2 rounded">
                    {{ $log->ip_address ?? 'Unknown' }}
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Browser</p>
                <p class="text-lg font-medium text-gray-900">{{ $log->browser ?? 'Unknown' }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Device</p>
                <p class="text-lg font-medium text-gray-900">{{ $log->device ?? 'Unknown' }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Platform</p>
                <p class="text-lg font-medium text-gray-900">{{ $log->platform ?? 'Unknown' }}</p>
            </div>
            @if($log->user_agent)
                <div class="md:col-span-2">
                    <p class="text-sm text-gray-600">User Agent</p>
                    <p class="text-sm font-mono text-gray-900 bg-gray-100 px-3 py-2 rounded break-words">
                        {{ $log->user_agent }}
                    </p>
                </div>
            @endif
        </div>
    </div>

    <!-- File & Environment Card -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Related Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <p class="text-sm text-gray-600">Related File Number</p>
                <p class="text-lg font-mono text-gray-900 bg-gray-100 px-3 py-2 rounded">
                    {{ $log->related_file_number ?? 'None' }}
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Environment</p>
                <p class="text-lg">
                    @if($log->test_control === 'TEST')
                        <span class="px-4 py-2 rounded-full text-sm font-medium bg-orange-100 text-orange-800">
                            TEST
                        </span>
                    @else
                        <span class="px-4 py-2 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                            PRODUCTION
                        </span>
                    @endif
                </p>
            </div>
        </div>
    </div>

    <!-- Metadata Card -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Metadata</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-600">
            <div>
                <p class="font-medium">Record ID</p>
                <p class="text-gray-900">#{{ $log->id }}</p>
            </div>
            <div>
                <p class="font-medium">Created At</p>
                <p class="text-gray-900">{{ $log->created_at?->format('F d, Y H:i:s') ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="font-medium">Updated At</p>
                <p class="text-gray-900">{{ $log->updated_at?->format('F d, Y H:i:s') ?? 'N/A' }}</p>
            </div>
            @if($log->indexed_at)
                <div>
                    <p class="font-medium">Indexed At</p>
                    <p class="text-gray-900">{{ $log->indexed_at?->format('F d, Y H:i:s') }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
