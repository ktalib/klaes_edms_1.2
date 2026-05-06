@extends('layouts.app')

@section('page-title')
    {{ __('KANGIS Indexed Files') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto bg-slate-50/60">
        @include('admin.header')

        <div class="p-6 max-w-7xl mx-auto space-y-6">
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold text-amber-600 uppercase tracking-widest">KANGIS</p>
                        <h1 class="text-2xl md:text-3xl font-bold text-slate-900 mt-1">Indexed Files</h1>
                        <p class="text-sm text-slate-500 mt-2">
                            Search and review KANGIS indexed file records.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl border border-blue-200 shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-blue-600 uppercase tracking-widest">Total Files</p>
                            <h3 class="text-3xl md:text-4xl font-bold text-blue-900 mt-2" id="total-kangis-files">--</h3>
                            <p class="text-sm text-blue-700 mt-2">KANGIS Indexed Files</p>
                        </div>
                        <div class="bg-blue-200 rounded-full p-3">
                            <i data-lucide="file-text" class="h-8 w-8 text-blue-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-2xl border border-green-200 shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-green-600 uppercase tracking-widest">Today's Indexing</p>
                            <h3 class="text-3xl md:text-4xl font-bold text-green-900 mt-2" id="daily-kangis-files">--</h3>
                            <p class="text-sm text-green-700 mt-2">Files Indexed Today</p>
                        </div>
                        <div class="bg-green-200 rounded-full p-3">
                            <i data-lucide="calendar-today" class="h-8 w-8 text-green-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            @include('components.indexed-files-table', ['config' => [
                'registry' => 'KANGIS',
                'tableVariant' => 'kangis',
                'hiddenColumns' => ['general_registry', 'registry', 'registry_batch_no', 'sys_batch_no', 'batch_no', 'corresponding_fileno'],
                'columnLabels' => [
                    'file_number' => 'Kangis FileNo',
                    'kangis_fileno_placeholder' => 'Placeholder',
                    'related_file_no' => 'Mls FileNo',
                    'file_title' => 'File Title',
                ],
                'hideActions' => false,
                'trackingUrlTemplate' => route('fileindexing.kangis-tracking-sheet') . '?files=__ID__',
            ]])
        </div>

        @include('admin.footer')
    </div>

    <script>
        // Load KANGIS indexed files stats
        document.addEventListener('DOMContentLoaded', function() {
            const statsUrl = new URL("{{ route('indexed-files.api.stats') }}", window.location.origin);
            statsUrl.searchParams.set('registry', 'KANGIS');

            fetch(statsUrl.toString())
                .then(response => response.json())
                .then(data => {
                    const totalElement = document.getElementById('total-kangis-files');
                    const dailyElement = document.getElementById('daily-kangis-files');
                    
                    if (data.data) {
                        if (totalElement && data.data.total_indexed) {
                            totalElement.textContent = data.data.total_indexed.toLocaleString();
                        }
                        
                        if (dailyElement && data.data.indexed_today) {
                            dailyElement.textContent = data.data.indexed_today.toLocaleString();
                        }
                    }
                })
                .catch(error => console.error('Error loading stats:', error));
        });
    </script>
@endsection
