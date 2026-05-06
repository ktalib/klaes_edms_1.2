@extends('app.layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Activity Reports</h1>
        <p class="text-gray-600">Generate and manage activity log reports in various formats</p>
    </div>

    <!-- Report Generation Form -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-6">Generate New Report</h2>

        <form id="reportGenerationForm" method="POST" action="{{ route('activity.reports.generate') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Date From -->
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-2">
                        Date From
                    </label>
                    <input 
                        type="date" 
                        id="date_from" 
                        name="date_from"
                        value="{{ request('date_from', now()->subDays(30)->toDateString()) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>

                <!-- Date To -->
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-2">
                        Date To
                    </label>
                    <input 
                        type="date" 
                        id="date_to" 
                        name="date_to"
                        value="{{ request('date_to', now()->toDateString()) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>

                <!-- Format Selection -->
                <div>
                    <label for="format" class="block text-sm font-medium text-gray-700 mb-2">
                        Report Format
                    </label>
                    <select 
                        id="format" 
                        name="format"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="csv">CSV (Excel compatible)</option>
                        <option value="pdf">PDF Document</option>
                        <option value="excel">Excel Workbook</option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                        Status (Optional)
                    </label>
                    <select 
                        id="status" 
                        name="status"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">All Statuses</option>
                        <option value="ACTIVE">Active</option>
                        <option value="IDLE">Idle</option>
                        <option value="INACTIVE">Inactive</option>
                    </select>
                </div>
            </div>

            <!-- User Filter -->
            <div>
                <label for="user_id" class="block text-sm font-medium text-gray-700 mb-2">
                    User (Optional)
                </label>
                <input 
                    type="text" 
                    id="user_id" 
                    name="user_search"
                    placeholder="Search by user name or email..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
            </div>

            <!-- Submit Buttons -->
            <div class="flex gap-4 pt-4 border-t border-gray-200">
                <button 
                    type="submit" 
                    class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Generate Report
                </button>
                <button 
                    type="button" 
                    id="scheduleBtn"
                    class="px-6 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition-colors"
                >
                    Schedule Report
                </button>
                <button 
                    type="reset" 
                    class="px-6 py-2 bg-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-400 transition-colors"
                >
                    Reset
                </button>
            </div>

            <!-- Generation Status -->
            <div id="generationStatus" class="hidden pt-4">
                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-blue-700">
                        <span class="inline-block animate-spin mr-2">⟳</span>
                        Generating report, please wait...
                    </p>
                </div>
            </div>
        </form>
    </div>

    <!-- Scheduled Reports Section -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-6">Scheduled Reports</h2>

        @if(empty($scheduledReports))
            <div class="text-center py-8">
                <p class="text-gray-500">No scheduled reports configured</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100 border-b border-gray-300">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Frequency</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Format</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Recipients</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Next Run</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($scheduledReports as $report)
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded capitalize">
                                        {{ $report['frequency'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    {{ strtoupper($report['format']) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $report['recipients'] }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    {{ \Carbon\Carbon::parse($report['next_run'])->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if($report['is_active'])
                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-medium">
                                            Active
                                        </span>
                                    @else
                                        <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs font-medium">
                                            Inactive
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <button 
                                        type="button"
                                        class="text-blue-600 hover:text-blue-800 font-medium mr-3 edit-schedule"
                                        data-id="{{ $report['id'] }}"
                                    >
                                        Edit
                                    </button>
                                    <button 
                                        type="button"
                                        class="text-red-600 hover:text-red-800 font-medium delete-schedule"
                                        data-id="{{ $report['id'] }}"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Recent Reports Section -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-6">Recent Reports</h2>

        @if(empty($recentReports))
            <div class="text-center py-8">
                <p class="text-gray-500">No reports generated yet</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100 border-b border-gray-300">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Filename</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Format</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Size</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Generated</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentReports as $report)
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    {{ $report['filename'] }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-xs font-medium">
                                        {{ strtoupper($report['format']) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    {{ number_format($report['size'] / 1024, 2) }} KB
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ \Carbon\Carbon::parse($report['generated_at'])->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <a 
                                        href="{{ route('activity.reports.download', $report['id']) }}"
                                        class="text-blue-600 hover:text-blue-800 font-medium mr-3"
                                    >
                                        Download
                                    </a>
                                    <button 
                                        type="button"
                                        class="text-red-600 hover:text-red-800 font-medium delete-report"
                                        data-id="{{ $report['id'] }}"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<!-- Schedule Report Modal -->
<div id="scheduleModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Schedule Report Delivery</h3>

        <form id="scheduleForm" class="space-y-4">
            @csrf

            <!-- Frequency -->
            <div>
                <label for="schedule_frequency" class="block text-sm font-medium text-gray-700 mb-2">
                    Frequency
                </label>
                <select id="schedule_frequency" name="frequency" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="biweekly">Bi-weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>

            <!-- Recipients -->
            <div>
                <label for="schedule_recipients" class="block text-sm font-medium text-gray-700 mb-2">
                    Email Recipients (comma separated)
                </label>
                <input 
                    type="text" 
                    id="schedule_recipients" 
                    name="recipients"
                    placeholder="user1@example.com, user2@example.com"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
            </div>

            <!-- Buttons -->
            <div class="flex gap-3 pt-4 border-t border-gray-200">
                <button 
                    type="submit"
                    class="flex-1 px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Schedule
                </button>
                <button 
                    type="button"
                    id="cancelSchedule"
                    class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-400 transition-colors"
                >
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Schedule button handler
    document.getElementById('scheduleBtn').addEventListener('click', function() {
        document.getElementById('scheduleModal').classList.remove('hidden');
    });

    // Cancel schedule
    document.getElementById('cancelSchedule').addEventListener('click', function() {
        document.getElementById('scheduleModal').classList.add('hidden');
    });

    // Report generation form
    document.getElementById('reportGenerationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        document.getElementById('generationStatus').classList.remove('hidden');
        this.submit();
    });

    // Delete report handlers
    document.querySelectorAll('.delete-report').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete this report?')) {
                // Submit delete request
                fetch(`/activity-reports/${this.dataset.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                }).then(response => response.json())
                  .then(data => {
                      if (data.success) {
                          location.reload();
                      } else {
                          alert('Error deleting report');
                      }
                  });
            }
        });
    });

    // Delete schedule handlers
    document.querySelectorAll('.delete-schedule').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete this scheduled report?')) {
                fetch(`/activity-reports/schedule/${this.dataset.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                }).then(response => response.json())
                  .then(data => {
                      if (data.success) {
                          location.reload();
                      } else {
                          alert('Error deleting scheduled report');
                      }
                  });
            }
        });
    });
});
</script>
@endsection
