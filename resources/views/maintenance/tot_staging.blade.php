@extends('layouts.app')

@section('page-title')
    {{ __('ToT Intelligence Dashboard') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'ToT Dashboard',
        'PageDescription' => 'Records qualified for automatic Transfer of Title generation'
    ])

    <div class="p-6 space-y-8 max-w-7xl mx-auto">
        <!-- Header & Actions -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900"></h1>
                <p class="text-slate-500 mt-1">Records ready for Transfer of Title generation</p>
            </div>
            <div class="flex gap-3">
                <button id="btnIgnore" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white border border-slate-200 text-slate-700 font-semibold shadow-sm hover:bg-slate-50 transition">
                    <i data-lucide="eye-off" class="h-5 w-5"></i>
                    <span>Archive Selected</span>
                </button>
                <button id="btnGenerate" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-blue-600 text-white font-semibold shadow hover:bg-blue-700 transition">
                    <i data-lucide="zap" class="h-5 w-5"></i>
                    <span>Execute ToT Generation</span>
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="h-12 w-12 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center">
                    <i data-lucide="alert-circle" class="h-6 w-6"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 font-medium uppercase tracking-wider">Ready for ToT</p>
                    <h3 class="text-2xl font-bold text-slate-800">{{ number_format(count($records)) }}</h3>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="h-12 w-12 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center">
                    <i data-lucide="file-text" class="h-6 w-6"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 font-medium uppercase tracking-wider">Source: PRA (OP)</p>
                    <h3 class="text-2xl font-bold text-slate-800">Occupancy Permit</h3>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="h-12 w-12 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center">
                    <i data-lucide="id-card" class="h-6 w-6"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 font-medium uppercase tracking-wider">Target: RofO</p>
                    <h3 class="text-2xl font-bold text-slate-800">Right of Occupancy</h3>
                </div>
            </div>
        </div>

        <!-- Data Table Section -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-slate-800">Records Ready for ToT Generation</h2>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-slate-600">Showing {{ $records->count() }} of {{ $records->total() }} records</span>
                    <div class="flex items-center gap-2">
                        <input class="w-5 h-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500" type="checkbox" id="selectAll">
                        <label class="text-xs font-bold text-slate-400 uppercase tracking-widest cursor-pointer" for="selectAll">Select All</label>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto -mx-6 px-6">
                    <table id="totStagingTable" class="w-full text-left border-collapse" style="min-width: 960px;">
                        <thead>
                            <tr class="text-slate-500 text-sm uppercase tracking-wider">
                                <th class="pb-4 font-semibold w-10 whitespace-nowrap"></th>
                                <th class="pb-4 font-semibold whitespace-nowrap">MLS File No</th>
                                <th class="pb-4 font-semibold whitespace-nowrap">OP Owner (Current)</th>
                                <th class="pb-4 font-semibold text-center whitespace-nowrap">Status</th>
                                <th class="pb-4 font-semibold whitespace-nowrap">RofO Owner (Staged)</th>
                                
                            </tr>
                        </thead>
                        <tbody class="text-slate-600 text-sm">
                            @forelse($records->items() as $record)
                            <tr class="hover:bg-slate-50 transition-colors border-b border-slate-100 group">
                                <td class="py-4 align-middle">
                                    <input class="w-5 h-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 row-checkbox" type="checkbox" value="{{ $record->id }}">
                                </td>
                                <td class="py-4 align-middle">
                                    <span class="font-mono text-xs font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full inline-block whitespace-nowrap">{{ $record->mlsFNo }}</span>
                                </td>
                                <td class="py-4 align-middle">
                                    <div class="p-3 bg-indigo-50/50 rounded-xl border border-indigo-100/50 min-w-[180px]">
                                        <span class="font-bold text-indigo-900 block truncate max-w-[240px]" title="{{ $record->op_name }}">{{ $record->op_name }}</span>
                                    </div>
                                </td>
                                <td class="py-4 text-center align-middle">
                                    <div class="inline-flex flex-col items-center gap-1">
                                        <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-black uppercase whitespace-nowrap">Ready for ToT</span>
                                        <i data-lucide="move-right" class="h-4 w-4 text-slate-300"></i>
                                    </div>
                                </td>
                                <td class="py-4 align-middle">
                                    <div class="p-3 bg-amber-50/50 rounded-xl border border-amber-100/50 min-w-[180px]">
                                        <span class="font-bold text-amber-900 block truncate max-w-[240px]" title="{{ $record->ro_name }}">{{ $record->ro_name }}</span>
                                    </div>
                                </td>
                              
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="py-20 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="h-16 w-16 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center">
                                            <i data-lucide="check-check" class="h-8 w-8"></i>
                                        </div>
                                        <h3 class="text-xl font-bold text-slate-800">All Caught Up</h3>
                                        <p class="text-slate-500">No records currently qualify for Transfer of Title generation.</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="flex justify-between items-center mt-6">
            <p class="text-sm text-slate-600">Total: {{ $records->total() }} records</p>
            <div>
                {{ $records->links() }}
            </div>
        </div>

@push('scripts')
<script>
$(document).ready(function() {
    const selectAll = document.getElementById('selectAll');
    const btnGenerate = document.getElementById('btnGenerate');
    const btnIgnore = document.getElementById('btnIgnore');

    // Select All
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            $('input.row-checkbox').prop('checked', this.checked);
        });
    }

    // Bulk Action Logic
    const executeAction = async (action, ids) => {
        const route = action === 'generate' ? '{{ route("maintenance.tot.generate") }}' : '{{ route("maintenance.tot.ignore") }}';
        const msg = action === 'generate' ? `Execute ToT generation for ${ids.length} records?` : `Archive ${ids.length} records?`;

        const confirmResult = await Swal.fire({
            title: 'Confirm Action',
            text: msg,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, proceed'
        });

        if (!confirmResult.isConfirmed) return;

        // Visual feedback
        const btn = action === 'generate' ? btnGenerate : btnIgnore;
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-2"></i> Processing...';

        try {
            const response = await fetch(route, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ ids: ids })
            });
            const result = await response.json();

            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Done',
                    text: result.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire('Error', result.message, 'error');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire('System Error', 'Failed to process the request. Please try again.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    };

    btnGenerate.addEventListener('click', () => {
        const ids = [];
        $('input.row-checkbox:checked').each(function() {
            ids.push($(this).val());
        });
        if (!ids.length) return Swal.fire('No Selection', 'Please select at least one record to generate.', 'info');
        executeAction('generate', ids);
    });

    btnIgnore.addEventListener('click', () => {
        const ids = [];
        $('input.row-checkbox:checked').each(function() {
            ids.push($(this).val());
        });
        if (!ids.length) return Swal.fire('No Selection', 'Please select at least one record to archive.', 'info');
        executeAction('ignore', ids);
    });
});
</script>
@endpush
@endsection
