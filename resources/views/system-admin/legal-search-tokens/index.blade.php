@extends('layouts.app')

@section('page-title')
    {{ __('Transaction Token Control (TTC)') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto bg-slate-50/60">
        @include('admin.header', [
            'PageTitle' => 'Transaction Token Control (TTC)',
            'PageDescription' => 'Generate and manage legal search tokens for property verification.'
        ])

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
                {{-- Page Header --}}
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                    <div>
                        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Transaction Token Control</h1>
                        <p class="text-slate-500 text-sm mt-1">Manage search tokens and tracking.</p>
                    </div>
                    <button type="button"
                        onclick="openTokenModal()"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 transition-all shadow-sm hover:shadow-md active:scale-95">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        Generate Token
                    </button>
                </div>

                {{-- Statistics Cards --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                        <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                            <i data-lucide="key" class="h-28 w-28 text-indigo-600"></i>
                        </div>
                        <div class="flex items-center gap-4 relative z-10">
                            <div class="p-3 bg-indigo-50 text-indigo-600 rounded-2xl border border-indigo-100 shadow-sm">
                                <i data-lucide="key" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Tokens</p>
                                <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ $tokens->total() }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                        <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                            <i data-lucide="check-circle" class="h-28 w-28 text-emerald-600"></i>
                        </div>
                        <div class="flex items-center gap-4 relative z-10">
                            <div class="p-3 bg-emerald-50 text-emerald-600 rounded-2xl border border-emerald-100 shadow-sm">
                                <i data-lucide="check-circle" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Used Tokens</p>
                                <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ \App\Models\LegalSearchToken::where('is_used', true)->count() }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                        <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                            <i data-lucide="alert-circle" class="h-28 w-28 text-amber-600"></i>
                        </div>
                        <div class="flex items-center gap-4 relative z-10">
                            <div class="p-3 bg-amber-50 text-amber-600 rounded-2xl border border-amber-100 shadow-sm">
                                <i data-lucide="alert-circle" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Unused Tokens</p>
                                <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ \App\Models\LegalSearchToken::where('is_used', false)->count() }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Records Table --}}
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center gap-2">
                        <i data-lucide="list" class="h-4 w-4 text-indigo-600"></i>
                        <h3 class="font-bold text-slate-800">Generated Tokens</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                    <th class="px-6 py-4">S/N</th>
                                    <th class="px-6 py-4">Token</th>
                                    <th class="px-6 py-4">File Number</th>
                                    <th class="px-6 py-4">Applicant</th>
                                    <th class="px-6 py-4">Transaction Purpose</th>
                                    <th class="px-6 py-4">Receipt</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                    <th class="px-6 py-4 text-center">Created At</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                                @forelse($tokens as $token)
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-6 py-4 text-xs font-bold text-slate-400">
                                            {{ ($tokens->currentPage() - 1) * $tokens->perPage() + $loop->iteration }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2 cursor-pointer group" onclick="toggleToken(this, '{{ $token->token }}')">
                                                <span class="token-display font-mono font-bold text-indigo-600/40 tracking-widest">************</span>
                                                <i data-lucide="eye" class="h-3 w-3 text-slate-300 group-hover:text-indigo-400 transition-colors"></i>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700 border border-blue-100">
                                                {{ $token->file_number }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-slate-900">{{ $token->applicant_name }}</div>
                                            <div class="text-[11px] text-slate-400 truncate max-w-[200px]" title="{{ $token->property_location }}">
                                                {{ $token->property_location ?: 'No location provided' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            @if($token->payment_reason)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-indigo-50 border border-indigo-100 text-[10px] font-bold text-indigo-600">{{ $token->payment_reason }}</span>
                                            @else
                                                <span class="text-[11px] text-slate-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-xs font-bold text-slate-700">{{ $token->receipt_number }}</div>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            @if($token->is_used)
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[9px] font-black bg-emerald-50 text-emerald-700 border border-emerald-100 uppercase tracking-widest">
                                                    Used
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[9px] font-black bg-amber-50 text-amber-700 border border-amber-100 uppercase tracking-widest">
                                                    Unused
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="text-xs text-slate-600 font-medium">{{ $token->created_at->format('d M, Y') }}</div>
                                            <div class="text-[10px] text-slate-400">{{ $token->created_at->format('h:i A') }}</div>
                                            <div class="mt-1 text-[9px] font-bold text-indigo-400 uppercase tracking-tighter">By: {{ $token->creator->name ?? 'System' }}</div>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            @if(!$token->is_used)
                                                <button onclick="deleteToken({{ $token->id }})" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" title="Delete Token">
                                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                            <div class="flex flex-col items-center justify-center gap-2">
                                                <i data-lucide="search" class="h-8 w-8 opacity-20"></i>
                                                <p>No tokens generated yet.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($tokens->hasPages())
                        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100">
                            {{ $tokens->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @include('admin.footer')
    </div>

    {{-- Generate Token Modal --}}
    <div id="token-modal" class="fixed inset-0 z-[999] hidden items-center justify-center p-4 sm:p-6">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeTokenModal()"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden animate-in fade-in zoom-in duration-300">
            <div class="bg-indigo-600 px-6 py-5 flex items-center justify-between">
                <div class="flex items-center gap-3 text-white">
                    <div class="p-2 bg-white/20 rounded-xl">
                        <i data-lucide="key" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold">Transaction Token Control (TTC)</h3>
                        <p class="text-indigo-100 text-[11px] font-medium uppercase tracking-wider">New verification credential</p>
                    </div>
                </div>
                <button onclick="closeTokenModal()" class="text-white/70 hover:text-white transition-colors">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>

            <form id="token-form" class="p-6 space-y-5">
                @csrf
                <div class="space-y-4">
                    {{-- File Selection --}}
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">File Selection</label>
                        <div class="flex items-center gap-3">
                            <div class="flex-1">
                                <input type="text" id="file_number" name="file_number" readonly required
                                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:outline-none placeholder:font-normal"
                                    placeholder="Select a file number...">
                            </div>
                            <button type="button" onclick="openFileSelector()"
                                class="px-4 py-3 bg-white border border-slate-200 text-slate-600 rounded-xl hover:bg-slate-50 transition-all shadow-sm flex items-center gap-2 font-bold text-sm">
                                <i data-lucide="search" class="h-4 w-4"></i>
                                Browse
                            </button>
                        </div>
                    </div>

                    {{-- Prefilled Data (Read-only) --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Applicant Name</label>
                            <input type="text" id="applicant_name" name="applicant_name" readonly required
                                class="w-full px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl text-sm font-medium text-slate-500 cursor-not-allowed outline-none"
                                placeholder="Auto-filled from selection...">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Property Location</label>
                            <input type="text" id="property_location_display" readonly
                                class="w-full px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl text-sm font-medium text-slate-500 cursor-not-allowed outline-none"
                                placeholder="Auto-filled from selection...">
                            <input type="hidden" id="property_location" name="property_location">
                        </div>
                    </div>

                    {{-- Payment Details Group --}}
                    <div class="p-5 bg-slate-50/50 rounded-2xl border border-slate-100 space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="credit-card" class="h-4 w-4 text-indigo-600"></i>
                            <span class="text-xs font-black text-slate-600 uppercase tracking-widest">Payment Information</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input type="hidden" id="amount_paid" name="amount_paid" value="0">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Receipt No.</label>
                                <input type="text" id="receipt_number" name="receipt_number" required
                                    class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none"
                                    placeholder="RPT/...">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Date Paid</label>
                                <input type="date" id="date_paid" name="date_paid" value="{{ date('Y-m-d') }}" required
                                    class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Reason for Payment</label>
                            <select id="payment_reason" name="payment_reason" required
                                class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none">
                                <option value="" disabled selected>Select a reason...</option>
                                @foreach($paymentReasons as $reason)
                                    <option value="{{ $reason->name }}">{{ $reason->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Metadata (Read-only) --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 opacity-70">
                        <div>
                            <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Generated By</label>
                            <input type="text" readonly value="{{ Auth::user()->name }}" 
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-lg text-[11px] font-bold text-slate-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Time</label>
                            <input type="text" readonly value="{{ date('h:i A') }}" 
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-lg text-[11px] font-bold text-slate-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Date</label>
                            <input type="text" readonly value="{{ date('d M, Y') }}" 
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-lg text-[11px] font-bold text-slate-500 outline-none">
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button type="button" onclick="closeTokenModal()"
                        class="px-6 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-8 py-2.5 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 transition-all shadow-md hover:shadow-lg active:scale-95 flex items-center gap-2">
                        <i data-lucide="check" class="h-4 w-4"></i>
                        Generate Token
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Global File Number Selector Modal --}}
    @include('components.global-fileno-modal')
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
@endsection

@push('scripts')
<script>
    function openTokenModal() {
        $('#token-modal').removeClass('hidden').addClass('flex');
    }

    function closeTokenModal() {
        $('#token-modal').addClass('hidden').removeClass('flex');
        $('#token-form')[0].reset();
    }

    function toggleToken(element, token) {
        const display = $(element).find('.token-display');
        const icon = $(element).find('i');
        const isMasked = display.text().includes('*');

        if (isMasked) {
            display.text(token).removeClass('text-indigo-600/40').addClass('text-indigo-600');
            icon.attr('data-lucide', 'eye-off');
        } else {
            display.text('************').removeClass('text-indigo-600').addClass('text-indigo-600/40');
            icon.attr('data-lucide', 'eye');
        }
        
        // Refresh Lucide icons
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function openFileSelector() {
        GlobalFileNoModal.open({
            callback: function(data) {
                console.log('File Selected:', data);
                $('#file_number').val(data.fileNumber);
                
                // Extract applicant name and location from record if available
                if (data.record) {
                    const record = data.record;
                    const applicant = record.file_title || record.file_name || record.FileName || '';
                    const location = record.location || record.Location || record.ma_location || '';
                    const lga = record.lga || record.LGA || record.fi_lga || '';
                    const locStr = `${location}${lga ? ', ' + lga : ''}`;
                    $('#applicant_name').val(applicant);
                    $('#property_location').val(locStr);
                    $('#property_location_display').val(locStr);
                }
            }
        });
    }

    $('#token-form').on('submit', function(e) {
        e.preventDefault();
        
        const btn = $(this).find('button[type="submit"]');
        const originalText = btn.html();
        btn.prop('disabled', true).html('<i class="animate-spin h-4 w-4 border-2 border-white/30 border-t-white rounded-full"></i> Processing...');

        $.ajax({
            url: "{{ route('legal-search-tokens.store') }}",
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Token Generated!',
                        html: `<div class="text-slate-600 mb-2">A new legal search token has been successfully created.</div>
                               <div class="text-sm font-black text-indigo-600 tracking-[0.5em]">************</div>`,
                        icon: 'success',
                        confirmButtonText: 'Great!',
                        confirmButtonColor: '#4f46e5'
                    }).then(() => {
                        window.location.reload();
                    });
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON ? xhr.responseJSON.message : 'Something went wrong';
                Swal.fire('Error', message, 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    function deleteToken(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/legal-search-tokens/${id}`,
                    method: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success').then(() => {
                                window.location.reload();
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Error', xhr.responseJSON.message, 'error');
                    }
                });
            }
        });
    }
</script>
@endpush
