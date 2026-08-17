<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <!-- Total Page Typed Files -->
    <div class="card bg-blue-50 border-blue-200">
        <div class="p-4 pb-2">
            <h3 class="text-sm font-medium text-blue-700">Total Page Typed Files</h3>
        </div>
        <div class="p-4 pt-0">
            <div class="text-2xl font-bold text-blue-900">{{ number_format($stats['total_archived'] ?? 0) }}</div>
            <p class="text-xs text-blue-600 mt-1">Completed page typed files</p>
        </div>
    </div>

    <!-- Recently Added -->
    <div class="card bg-green-50 border-green-200">
        <div class="p-4 pb-2">
            <h3 class="text-sm font-medium text-green-700">Recently Added</h3>
        </div>
        <div class="p-4 pt-0">
            <div class="text-2xl font-bold text-green-900">{{ number_format($stats['recently_added'] ?? 0) }}</div>
            <p class="text-xs text-green-600 mt-1">Added in the last 30 days</p>
        </div>
    </div>

    <!-- Total Pages -->
    <div class="card bg-yellow-50 border-yellow-200">
        <div class="p-4 pb-2">
            <h3 class="text-sm font-medium text-yellow-700">Total Pages</h3>
        </div>
        <div class="p-4 pt-0">
            <div class="text-2xl font-bold text-yellow-900">{{ number_format($stats['total_pages'] ?? 0) }}</div>
            <p class="text-xs text-yellow-600 mt-1">Digitally classified pages</p>
        </div>
    </div>

    <!-- Storage Used -->
    <div class="card bg-purple-50 border-purple-200">
        <div class="p-4 pb-2">
            <h3 class="text-sm font-medium text-purple-700">Storage Used</h3>
        </div>
        <div class="p-4 pt-0">
            <div class="text-2xl font-bold text-purple-900">{{ $stats['storage_used'] }}</div>
            <p class="text-xs text-purple-600 mt-1">Of archived documents</p>
        </div>
    </div>
</div>

{{-- Move a file's documents (scans, typed pages and Doc-WARE archive copies)
     to another registry, or into one of the EDMS master folders. Both open with
     no file preselected — the operator searches for one inside the dialog. --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <button type="button"
            class="card bg-indigo-50 border-indigo-200 text-left w-full hover:bg-indigo-100 hover:border-indigo-300 transition-colors cursor-pointer"
            onclick="EdmsRegistryTransfer.open(null, null, () => window.location.reload())">
        <div class="p-4 flex items-center gap-4">
            <div class="p-3 bg-indigo-100 rounded-lg flex-shrink-0">
                <i data-lucide="folder-symlink" class="h-6 w-6 text-indigo-600"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-semibold text-indigo-900">Move File to Another Registry</h3>
                <p class="text-xs text-indigo-600 mt-1">
                    Relocates a file's scans, typed pages and Doc-WARE archive copies into a different
                    registry folder, and re-points its records to match.
                </p>
            </div>
            <div class="flex items-center gap-1 text-indigo-700 text-sm font-semibold whitespace-nowrap">
                Open
                <i data-lucide="chevron-right" class="h-4 w-4"></i>
            </div>
        </div>
    </button>

    <button type="button"
            class="card bg-violet-50 border-violet-200 text-left w-full hover:bg-violet-100 hover:border-violet-300 transition-colors cursor-pointer"
            onclick="EdmsFileType.open(null, null, () => window.location.reload())">
        <div class="p-4 flex items-center gap-4">
            <div class="p-3 bg-violet-100 rounded-lg flex-shrink-0">
                <i data-lucide="folder-tree" class="h-6 w-6 text-violet-600"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-semibold text-violet-900">File into a Master Folder</h3>
                <p class="text-xs text-violet-600 mt-1">
                    Files an archived document set into Regular, Merger, Subdivision, Extension,
                    Temporary File or Change of Purpose — with its cover shown so you can read
                    the instruction on it.
                </p>
            </div>
            <div class="flex items-center gap-1 text-violet-700 text-sm font-semibold whitespace-nowrap">
                Open
                <i data-lucide="chevron-right" class="h-4 w-4"></i>
            </div>
        </div>
    </button>
</div>