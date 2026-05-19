@extends('layouts.app')

@section('page-title')
    Surrender & Release Records
@endsection
@section('content')
<div class="flex-1 overflow-auto bg-slate-50">
    @include('admin.header', ['PageTitle' => 'Surrender & Release Records', 'PageDescription' => 'Comprehensive list of Deed of Surrender and Release records from PRA, Instrument Capture, and File History Staging.'])
    
    <div class="p-6 space-y-6">
        {{-- ① Statistics Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">

            {{-- Daily Combined --}}
            <div class="premium-card group border-l-4 border-l-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Captured Today</p>
                        <h3 class="text-2xl font-black text-orange-700 mt-0.5">{{ number_format($dailyRecords) }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center group-hover:bg-orange-500 transition-colors duration-300">
                        <i data-lucide="calendar" class="w-5 h-5 text-orange-600 group-hover:text-white transition-colors"></i>
                    </div>
                </div>
            </div>

            {{-- Instrument Capture --}}
            <div class="premium-card group border-l-4 border-l-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Instrument Capture</p>
                        <h3 class="text-2xl font-black text-purple-700 mt-0.5">{{ number_format($icTotal) }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center group-hover:bg-purple-500 transition-colors duration-300">
                        <i data-lucide="scan-line" class="w-5 h-5 text-purple-600 group-hover:text-white transition-colors"></i>
                    </div>
                </div>
            </div>

            {{-- Property Records (PRA) --}}
            <div class="premium-card group border-l-4 border-l-teal-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Property Records (PRA)</p>
                        <h3 class="text-2xl font-black text-teal-700 mt-0.5">{{ number_format($praTotal) }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-teal-50 flex items-center justify-center group-hover:bg-teal-500 transition-colors duration-300">
                        <i data-lucide="database" class="w-5 h-5 text-teal-600 group-hover:text-white transition-colors"></i>
                    </div>
                </div>
            </div>

            {{-- File History Staging --}}
            <div class="premium-card group border-l-4 border-l-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">File History Staging</p>
                        <h3 class="text-2xl font-black text-blue-700 mt-0.5">{{ number_format($fhsTotal) }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center group-hover:bg-blue-500 transition-colors duration-300">
                        <i data-lucide="archive" class="w-5 h-5 text-blue-600 group-hover:text-white transition-colors"></i>
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
                    Surrender & Release Records
                </h2>
                <div class="flex items-center gap-2">
                    <button id="refresh_btn" class="p-2 hover:bg-slate-100 rounded-lg transition text-slate-400 hover:text-indigo-600" title="Refresh Table">
                        <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <div class="p-6">
                <div class="overflow-x-auto w-full">
                    <table id="surrender_release_table" class="w-full text-sm min-w-[1200px]">
                        <thead>
                            <tr class="text-left text-xs font-bold text-slate-500 uppercase tracking-wider bg-slate-50">
                                <th class="px-4 py-4 rounded-tl-xl">S/N</th>
                                <th class="px-4 py-4">File Number</th>
                                <th class="px-4 py-4">Registration Particulars</th>
                                <th class="px-4 py-4">Associated Mortgage</th>
                                <th class="px-4 py-4">Party 1</th>
                                <th class="px-4 py-4">Party 2</th>
                                <th class="px-4 py-4">Party 3</th>
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

    {{-- Mortgage Details Modal --}}
    <div id="mortgage_modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" aria-hidden="true" onclick="closeMortgagesModal()"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full border border-slate-100">
                <div class="bg-gradient-to-r from-amber-500 to-orange-600 px-6 py-5 flex items-center justify-between">
                    <h3 class="text-lg font-black text-white flex items-center gap-2" id="modal-title">
                        <i class="fas fa-file-invoice-dollar text-xl"></i>
                        Associated Mortgage Details
                    </h3>
                    <button type="button" class="text-white/80 hover:text-white transition cursor-pointer" onclick="closeMortgagesModal()">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <div class="px-6 py-6 space-y-4 max-h-[70vh] overflow-y-auto bg-slate-50" id="mortgage_modal_body">
                    {{-- Dynamically populated --}}
                </div>

                <div class="bg-white px-6 py-4 border-t border-slate-100 flex justify-end">
                    <button type="button" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-xl text-sm transition shadow-md hover:shadow-lg cursor-pointer" onclick="closeMortgagesModal()">
                        Close
                    </button>
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
    #surrender_release_table_wrapper .dataTables_length select {
        border-radius: 12px;
        padding: 6px 32px 6px 12px;
        border: 1px solid #e2e8f0;
        font-weight: 600;
        color: #475569;
    }
    #surrender_release_table_wrapper .dataTables_filter input {
        border-radius: 12px;
        padding: 8px 16px;
        border: 1px solid #e2e8f0;
        width: 300px;
        transition: all 0.2s;
    }
    #surrender_release_table_wrapper .dataTables_filter input:focus {
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
    .source-ic { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
    .source-pra { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    .source-fhs { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }

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
    const table = $('#surrender_release_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('surrender-release.data') }}",
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
                className: 'whitespace-nowrap',
                render: function(data, type, row) {
                    const fileNo = data || '—';
                    const propId = row.prop_id || '';
                    const count = row.timeline_count || 1;
                    
                    let html = `<div class="flex flex-col justify-center items-start py-1 whitespace-nowrap">
                                    <span class="file-no-link whitespace-nowrap" style="white-space: nowrap;">${fileNo}</span>`;
                    
                    if (fileNo !== '—' || propId) {
                        html += `<button type="button" class="timeline-badge" 
                                            onclick="openPropertyTimeline('${propId}', '${fileNo}')"
                                            title="View full property timeline">
                                        <i class="fas fa-history mr-1 opacity-70"></i> Timeline (${count})
                                    </button>`;
                    }
                    
                    html += `</div>`;
                    return html;
                }
            },
            { data: 'registration_particulars', name: 'registration_particulars', defaultContent: '0/0/0' },
            {
                data: 'associated_mortgages',
                name: 'associated_mortgages',
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    const mortgages = data || [];
                    if (mortgages.length === 0) {
                        return `<span class="text-slate-400 text-xs italic">No Mortgage</span>`;
                    }
                    
                    return `<div class="py-1">
                                <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 transition duration-150 uppercase tracking-wide cursor-pointer mortgage-trigger shadow-sm hover:shadow" title="View Mortgage Details">
                                    <i class="fas fa-file-invoice-dollar text-[11px] text-amber-600"></i> Mortgage (${mortgages.length})
                                </button>
                            </div>`;
                }
            },
            { data: 'party_1', name: 'party_1', defaultContent: '—' },
            { data: 'party_2', name: 'party_2', defaultContent: '—' },
            { data: 'party_3', name: 'party_3', defaultContent: '—' },
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
        order: [[8, 'desc']],
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

    // Delegate click event on Mortgage button triggers
    $('#surrender_release_table').on('click', '.mortgage-trigger', function(e) {
        e.preventDefault();
        const tr = $(this).closest('tr');
        const rowData = table.row(tr).data();
        if (rowData && rowData.associated_mortgages) {
            openMortgagesModal(rowData.associated_mortgages, rowData.file_number || '—');
        }
    });

    $('#refresh_btn').on('click', function() {
        table.ajax.reload();
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
});

// Global functions for open/close Mortgage details modal
function openMortgagesModal(mortgages, fileNo) {
    const body = $('#mortgage_modal_body');
    body.empty();
    
    $('#modal-title').html(`<i class="fas fa-file-invoice-dollar text-xl"></i> Mortgages for ${fileNo}`);

    mortgages.forEach(function(m) {
        const mType = m.instrument_type || 'Deed of Mortgage';
        const reg = m.registration_particulars && m.registration_particulars.trim() ? m.registration_particulars : 'Unregistered';
        const mortgagor = m.party_1 || '—';
        const mortgagee = m.party_2 || '—';
        const party3Html = m.party_3 && m.party_3.trim() ? `
        <div class="pt-2">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Party 3</p>
            <p class="text-sm font-bold text-slate-800 mt-0.5">${m.party_3}</p>
        </div>` : '';
        
        let dateVal = '—';
        if (m.date_captured) {
            try {
                dateVal = m.date_captured.substring(0, 16).replace('T', ' ');
            } catch (e) {
                dateVal = m.date_captured;
            }
        }
        
        const cardHtml = `
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-4 hover:border-amber-300 transition-all duration-300">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold bg-amber-50 text-amber-800 border border-amber-200 uppercase tracking-wide">
                    <i class="fas fa-file-invoice-dollar text-amber-600 text-[10px]"></i> ${mType}
                </span>
                <span class="px-2.5 py-1 text-xs font-mono font-bold bg-slate-100 text-slate-700 rounded-lg border border-slate-200">
                    ${reg}
                </span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Mortgagor (Party 1)</p>
                    <p class="text-sm font-bold text-slate-800 mt-0.5">${mortgagor}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Mortgagee (Party 2)</p>
                    <p class="text-sm font-bold text-slate-800 mt-0.5">${mortgagee}</p>
                </div>
            </div>

            ${party3Html}

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-3 border-t border-slate-100 text-xs text-slate-500">
                <div class="flex items-center gap-1.5">
                    <i class="fas fa-database text-slate-400 text-[10px]"></i>
                    <span>Source: <strong class="text-slate-700">${m.source || '—'}</strong></span>
                </div>
                <div class="flex items-center gap-1.5 md:justify-end">
                    <i class="fas fa-calendar-alt text-slate-400 text-[10px]"></i>
                    <span>Date Captured: <strong class="text-slate-700">${dateVal}</strong></span>
                </div>
            </div>
        </div>`;
        
        body.append(cardHtml);
    });

    $('#mortgage_modal').removeClass('hidden');
    $('body').addClass('overflow-hidden');
}

function closeMortgagesModal() {
    $('#mortgage_modal').addClass('hidden');
    $('body').removeClass('overflow-hidden');
}

window.openMortgagesModal = openMortgagesModal;
window.closeMortgagesModal = closeMortgagesModal;
</script>
@endpush
