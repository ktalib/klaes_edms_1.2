@extends('layouts.app')

@section('page-title')
    {{ $pageTitle ?? __('Allocation List') }}
@endsection

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

{{-- ── DataTables CSS ─────────────────────────────────────────────────── --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
{{-- We keep the above for DT layout, but we'll override it with our CSS --}}
<link rel="stylesheet" href="{{ asset('css/allocation_list_entry.css') }}">

{{-- ── Global JS Config ───────────────────────────────────────────────── --}}
<script>
    window.ALE = {
        csrf : '{{ csrf_token() }}', 
        urls : {
            data       : '{{ route("allocation-list.data") }}',
            store      : '{{ route("allocation-list.store") }}',
            storeExisting: '{{ route("allocation-list.store-existing") }}',
            fileLookup : '{{ route("allocation-list.file-lookup") }}',
            update     : '{{ route("allocation-list.update", "__ID__") }}',
            destroy    : '{{ route("allocation-list.destroy", "__ID__") }}',
            fetchrowinfo: '{{ route("allocation-list.fetchrowinfo", "__ID__") }}',
            importCsv  : '{{ route("allocation-list.import.csv") }}',
            template   : '{{ route("allocation-list.template") }}',
        },
        activeFilter : ''
    };
</script>

{{-- ── Page Wrapper ────────────────────────────────────────────────────── --}}
<div class="flex-1 overflow-auto bg-gray-50">
    @include('admin.header')
    
    <div class="p-6">
        <main class="max-w-7xl mx-auto space-y-6">

            {{-- ── Stats Cards ──────────────────────────────────────────── --}}
            <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
                {{-- Total --}}
                <div class="ale-stat-card bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                        <i data-lucide="users" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-900 leading-none" id="stat-total">—</div>
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mt-1">Total Entries</div>
                    </div>
                </div>
            </div>

            {{-- ── Table Card ───────────────────────────────────────────── --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

                {{-- Header bar --}}
                <div class="px-6 py-5 border-b border-gray-100 bg-white">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">{{ $pageTitle }}</h2>
                            <p class="text-sm text-gray-500 mt-1">{{ $pageDescription }}</p>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            {{-- Export buttons container --}}
                            <div id="dt-export-buttons" class="flex gap-1"></div>

                            {{-- Import --}}
                            <input type="file" id="import-csv-input" class="hidden" accept=".csv" onchange="aleHandleImport(event)">
                            <button onclick="aleTriggerImport()" 
                                class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-all flex items-center gap-2">
                                <i data-lucide="upload-cloud" class="h-4 w-4"></i>
                                Import
                            </button>

                            {{-- Template --}}
                            <a href="{{ route('allocation-list.template') }}" 
                                class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-all flex items-center gap-2">
                                <i data-lucide="download" class="h-4 w-4"></i>
                                Template
                            </a>

                            {{-- Capture an existing allocation --}}
                            <button onclick="aleOpenAdd()"
                                class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 transition-all shadow-md flex items-center gap-2">
                                <i data-lucide="plus" class="h-4 w-4"></i>
                                Capture Allocation
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Table --}}
                <div class="p-6 overflow-x-auto ale-table-container">
                    <table id="allocationTable" class="ale-table w-full">
                        <thead>
                            <tr>
                                <th>SN</th>
                                <th>FileNo</th>
                                <th>File Title</th>
                                <th>Name</th>
                                <th>Location</th>
                                <th>Year</th>
                                <th>Captured</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
    @include('admin.footer')
</div>

{{-- ── Modal ───────────────────────────────────────────────────────────── --}}
@include('allocation_list_entry._modal')

{{-- Shared file number selector, opened by the "Select" button on the form.
     Included inside the content section so its Select2 CDN <script> loads after
     the jQuery in <head>. --}}
@include('components.global-fileno-modal')

{{-- ── Scripts ─────────────────────────────────────────────────────────── --}}
{{-- jQuery is already in layouts.app --}}
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
{{-- SweetAlert2 and Lucide are already in layouts.app; Select2 ships with the
     global file number modal include above. --}}
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script src="{{ asset('js/allocation_list_entry.js') }}"></script>

@endsection
