@extends('layouts.app')

@section('page-title')
    Mortgage Records
@endsection
@section('content')
<div class="flex-1 overflow-auto bg-slate-50">
    @include('admin.header', ['PageTitle' => 'Mortgage Records', 'PageDescription' => 'Comprehensive list of Deed of Mortgage and Tripartite Mortgage records from PRA, Instrument Capture, and File History Staging.'])
    
    <div class="p-6 space-y-6">
        {{-- ① Statistics Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">


               {{-- Daily Combined --}}
            <div class="premium-card group border-l-4 border-l-rose-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Captured Today</p>
                        <h3 class="text-2xl font-black text-rose-700 mt-0.5">{{ number_format($dailyRecords) }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center group-hover:bg-rose-500 transition-colors duration-300">
                        <i data-lucide="calendar" class="w-5 h-5 text-rose-600 group-hover:text-white transition-colors"></i>
                    </div>
                </div>
            </div>

          

            {{-- Instrument Capture --}}
            <div class="premium-card group border-l-4 border-l-indigo-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Instrument Capture</p>
                        <h3 class="text-2xl font-black text-indigo-700 mt-0.5">{{ number_format($icTotal) }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center group-hover:bg-indigo-500 transition-colors duration-300">
                        <i data-lucide="scan-line" class="w-5 h-5 text-indigo-600 group-hover:text-white transition-colors"></i>
                    </div>
                </div>
            </div>

            {{-- Property Records (PRA) --}}
            <div class="premium-card group border-l-4 border-l-emerald-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Property Records (PRA)</p>
                        <h3 class="text-2xl font-black text-emerald-700 mt-0.5">{{ number_format($praTotal) }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center group-hover:bg-emerald-500 transition-colors duration-300">
                        <i data-lucide="database" class="w-5 h-5 text-emerald-600 group-hover:text-white transition-colors"></i>
                    </div>
                </div>
            </div>

            {{-- File History Staging --}}
            <div class="premium-card group border-l-4 border-l-amber-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">File History Staging</p>
                        <h3 class="text-2xl font-black text-amber-700 mt-0.5">{{ number_format($fhsTotal) }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center group-hover:bg-amber-500 transition-colors duration-300">
                        <i data-lucide="archive" class="w-5 h-5 text-amber-600 group-hover:text-white transition-colors"></i>
                    </div>
                </div>
            </div>

   
              {{-- Total Records (All) --}}
            <div class="premium-card group border-l-4 border-l-slate-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Records</p>
                        <h3 class="text-2xl font-black text-slate-800 mt-0.5">{{ number_format($totalRecords) }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center group-hover:bg-slate-800 transition-colors duration-300">
                        <i data-lucide="layers" class="w-5 h-5 text-slate-600 group-hover:text-white transition-colors"></i>
                    </div>
                </div>
            </div>

        </div>

        {{-- ② Data Table Card --}}
        <div class="bg-white rounded-3xl border border-slate-200 shadow-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-white flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="table-2" class="w-5 h-5 text-indigo-500"></i>
                    Mortgage Records
                </h2>
                <div class="flex items-center gap-2">
                    <button id="refresh_btn" class="p-2 hover:bg-slate-100 rounded-lg transition text-slate-400 hover:text-indigo-600" title="Refresh Table">
                        <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <div class="p-6">
                <div class="table-responsive">
                    <table id="mortgage_table" class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-bold text-slate-500 uppercase tracking-wider bg-slate-50">
                                <th class="px-4 py-4 rounded-tl-xl">S/N</th>
                                <th class="px-4 py-4">File Number</th>
                                <th class="px-4 py-4">Registration Particulars</th>
                                <th class="px-4 py-4">Instrument Type</th>
                                <th class="px-4 py-4">Party 1 </th>
                                <th class="px-4 py-4">Party 2 </th>
                                <th class="px-4 py-4">Party 3</th>
                                <th class="px-4 py-4">Party 4</th>
                                <th class="px-4 py-4">Location</th>
                                <th class="px-4 py-4">Date Captured</th>
                                <th class="px-4 py-4 text-center rounded-tr-xl">Source</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('admin.footer')
</div>
@endsection

@push('styles')
<style>
    /* Premium Design System */
    .premium-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }
    .premium-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        border-color: #cbd5e1;
    }

    /* DataTable Overrides */
    #mortgage_table_wrapper .dataTables_length select {
        border-radius: 12px;
        padding: 6px 32px 6px 12px;
        border: 1px solid #e2e8f0;
        font-weight: 600;
        color: #475569;
    }
    #mortgage_table_wrapper .dataTables_filter input {
        border-radius: 12px;
        padding: 8px 16px;
        border: 1px solid #e2e8f0;
        width: 300px;
        transition: all 0.2s;
    }
    #mortgage_table_wrapper .dataTables_filter input:focus {
        border-color: #6366f1;
        outline: none;
    }
    
    table.dataTable thead th {
        background-color: #f8fafc !important;
        border-bottom: 2px solid #e2e8f0 !important;
        color: #64748b !important;
        padding: 16px 12px !important;
    }
    
    table.dataTable tbody td {
        padding: 12px 12px !important;
        border-bottom: 1px solid #f1f5f9 !important;
        color: #334155;
    }
    
    table.dataTable tbody tr:hover {
        background-color: #f8fafc !important;
    }

    .source-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 99px;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.025em;
        white-space: nowrap;
    }
    .source-ic { background: #eef2ff; color: #4f46e5; border: 1px solid #c7d2fe; }
    .source-pra { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    .source-fhs { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }

    .file-no-link {
        color: #4f46e5;
        font-weight: 700;
        text-decoration: none;
        transition: color 0.2s;
    }
    .file-no-link:hover {
        color: #312e81;
        text-decoration: underline;
    }

    /* Timeline Badge Styles */
    .timeline-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        padding: 2px 8px;
        border-radius: 9999px;
        font-size: 10px;
        font-weight: 700;
        border: 1px solid #bfdbfe;
        background-color: #eff6ff;
        color: #1e40af;
        cursor: pointer;
        transition: all 0.2s;
        margin-top: 4px;
        white-space: nowrap;
    }
    .timeline-badge:hover {
        background-color: #dbeafe;
        transform: scale(1.05);
    }

    /* Animations */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .p-6 > * {
        animation: fadeIn 0.4s ease-out forwards;
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/property-timeline-modal.js') }}"></script>
<script>
$(function() {
    const table = $('#mortgage_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('mortgages.data') }}",
        columns: [
            { 
                data: null, 
                orderable: false, 
                searchable: false,
                render: function (data, type, row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                }
            },
            { 
                data: 'file_number', 
                name: 'file_number',
                render: function(data, type, row) {
                    const fileNo = data || '—';
                    const propId = row.prop_id || '';
                    const count = row.timeline_count || 1;
                    
                    let html = `<div class="flex flex-col">
                                    <span class="file-no-link">${fileNo}</span>`;
                    
                    if (fileNo !== '—' || propId) {
                        html += `<div class="mt-1">
                                    <button type="button" class="timeline-badge" 
                                            onclick="openPropertyTimeline('${propId}', '${fileNo}')"
                                            title="View full property timeline">
                                        <i class="fas fa-history mr-1 opacity-70"></i> Timeline (${count})
                                    </button>
                                 </div>`;
                    }
                    
                    html += `</div>`;
                    return html;
                }
            },
            { data: 'registration_particulars', name: 'registration_particulars', defaultContent: '0/0/0' },
            { 
                data: 'instrument_type', 
                name: 'instrument_type',
                render: function(data) {
                    return `<span class="font-semibold text-slate-700">${data}</span>`;
                }
            },
            { data: 'party_1', name: 'party_1', defaultContent: '—' },
            { data: 'party_2', name: 'party_2', defaultContent: '—' },
            { data: 'party_3', name: 'party_3', defaultContent: '—' },
            { data: 'party_4', name: 'party_4', defaultContent: '—' },
            { data: 'location', name: 'location', defaultContent: '—' },
            { data: 'date_captured', name: 'date_captured' },
            { 
                data: 'source_table', 
                name: 'source_table',
                className: 'text-center',
                render: function(data) {
                    let cls = '';
                    if (data === 'Instrument Capture') cls = 'source-ic';
                    else if (data === 'Property Records') cls = 'source-pra';
                    else if (data === 'File History Staging') cls = 'source-fhs';
                    return `<span class="source-badge ${cls}">${data}</span>`;
                }
            }
        ],
        pageLength: 25,
        order: [[9, 'desc']],
        language: {
            search: "",
            searchPlaceholder: "Search records...",
            lengthMenu: "_MENU_ records per page",
            processing: '<div class="flex items-center justify-center p-4"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div></div>'
        },
        drawCallback: function() {
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    });

    $('#refresh_btn').on('click', function() {
        table.ajax.reload();
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
});

 

</script>
@endpush
