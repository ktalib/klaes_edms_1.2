<!-- Quick Actions Card -->
<div class="bg-white rounded-lg border border-gray-200 shadow-sm">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
            <i data-lucide="file-search" class="h-5 w-5"></i>
            Quick Actions
        </h3>
        <p class="text-sm text-gray-600 mt-1">Perform common file tracking operations</p>
    </div>
    <div class="p-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <!-- Search Files -->
            <button id="quick-search-files" class="quick-action-btn" data-action="search-files" title="Search existing file trackers">
                <div class="flex flex-col items-center gap-4">
                    <div class="p-4 bg-blue-100 rounded-xl">
                        <i data-lucide="file-search" class="h-7 w-7 text-blue-600"></i>
                    </div>
                    <div class="text-center">
                        <span class="block text-base font-medium text-gray-700">Search Files</span>
                        <span class="block text-sm text-gray-500">Find trackers</span>
                    </div>
                </div>
            </button>

            <!-- Office List -->
            <button id="quick-office-list" class="quick-action-btn" data-action="office-list" title="View all offices and departments">
                <div class="flex flex-col items-center gap-4">
                    <div class="p-4 bg-green-100 rounded-xl">
                        <i data-lucide="building" class="h-7 w-7 text-green-600"></i>
                    </div>
                    <div class="text-center">
                        <span class="block text-base font-medium text-gray-700">Office List</span>
                        <span class="block text-sm text-gray-500">View offices</span>
                    </div>
                </div>
            </button>

            <!-- Track Status -->
            <button id="quick-track-status" class="quick-action-btn" data-action="track-status" title="Check file tracking status">
                <div class="flex flex-col items-center gap-4">
                    <div class="p-4 bg-orange-100 rounded-xl">
                        <i data-lucide="activity" class="h-7 w-7 text-orange-600"></i>
                    </div>
                    <div class="text-center">
                        <span class="block text-base font-medium text-gray-700">Track Status</span>
                        <span class="block text-sm text-gray-500">Check status</span>
                    </div>
                </div>
            </button>

            <!-- Statistics -->
            <button id="quick-statistics" class="quick-action-btn" data-action="statistics" title="View tracking statistics">
                <div class="flex flex-col items-center gap-4">
                    <div class="p-4 bg-purple-100 rounded-xl">
                        <i data-lucide="bar-chart-3" class="h-7 w-7 text-purple-600"></i>
                    </div>
                    <div class="text-center">
                        <span class="block text-base font-medium text-gray-700">Statistics</span>
                        <span class="block text-sm text-gray-500">View stats</span>
                    </div>
                </div>
            </button>

            <!-- Assignment Center -->
            <button id="quick-assignment-center" class="quick-action-btn" data-action="assignment-center" title="Manage file assignments">
                <div class="flex flex-col items-center gap-4">
                    <div class="p-4 bg-indigo-100 rounded-xl">
                        <i data-lucide="user-plus" class="h-7 w-7 text-indigo-600"></i>
                    </div>
                    <div class="text-center">
                        <span class="block text-base font-medium text-gray-700">Assignment Center</span>
                        <span class="block text-sm text-gray-500">Assign & review</span>
                    </div>
                </div>
            </button>

            @if(($module ?? '') === 'new_kangis')
            <!-- Scan QR — New KANGIS department log -->
            <button id="quick-scan-qr" class="quick-action-btn border-amber-300 hover:border-amber-500 hover:bg-amber-50" data-action="scan-qr" title="Scan QR code to log file into next department">
                <div class="flex flex-col items-center gap-4">
                    <div class="p-4 bg-amber-100 rounded-xl">
                        <i data-lucide="scan-line" class="h-7 w-7 text-amber-600"></i>
                    </div>
                    <div class="text-center">
                        <span class="block text-base font-medium text-gray-700">Scan QR</span>
                        <span class="block text-sm text-amber-600">Log to next dept</span>
                    </div>
                </div>
            </button>
            @endif

            <!-- Update Movement moved to history table action menu -->
        </div>
    </div>
</div>

<!-- Quick Actions Styles -->
<style>
.quick-action-btn {
    @apply w-full p-5 border border-gray-200 rounded-xl transition-all duration-200 hover:border-blue-400 hover:shadow-lg hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2;
}

.quick-action-btn:hover .text-gray-700 {
    @apply text-blue-700;
}

.quick-action-btn:hover .text-gray-500 {
    @apply text-blue-600;
}

.quick-action-btn:active {
    @apply transform scale-95;
}
</style>