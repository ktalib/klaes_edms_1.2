@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header')
    <div class="py-12 bg-slate-50 min-h-screen">
    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Recommendation For Grant of RoFO</h1>
                    @if(!empty($isOssView))
                        <p class="oss-label" style="color:#ea1b1b">LAND ONE STOP SHOP</p>
                    @else
                        <p class="text-slate-500 text-sm mt-1">Manage applications, approvals, and controlled printing.</p>
                    @endif
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <form action="{{ route('land-recommendations.index') }}" method="GET" class="relative group flex-1 md:w-80">
                        <input type="hidden" name="type" value="{{ !empty($isOssView) ? 'OSS' : 'ROFO' }}">
                        <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                        <input type="text" 
                               name="search" 
                               value="{{ request('search') }}"
                               placeholder="Search file, applicant, or location..." 
                               class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all shadow-sm">
                    </form>
                    @if(empty($isOssView))
                        <a href="{{ route('land-recommendations.create') }}" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition-all shadow-lg shadow-blue-100 whitespace-nowrap">
                            <i data-lucide="plus-circle" class="h-5 w-5"></i>
                            <span>New Recommendation</span>
                        </a>
                    @endif
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <!-- Total Records -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="file-text" class="h-32 w-32 text-blue-600"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-blue-50 text-blue-600 rounded-2xl border border-blue-100 shadow-sm">
                            <i data-lucide="file-text" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Records</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($stats['total']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>All applications</span>
                        <span class="text-blue-500">Active Database</span>
                    </div>
                </div>

                <!-- Pending -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="clock" class="h-32 w-32 text-amber-600"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-amber-50 text-amber-600 rounded-2xl border border-amber-100 shadow-sm">
                            <i data-lucide="clock" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Pending Approval</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($stats['pending']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>Awaiting review</span>
                        <span class="text-amber-500 flex items-center gap-1">Action Required</span>
                    </div>
                </div>

                <!-- Approved / Total Ground Rent -->
                <div class="p-6 rounded-3xl shadow-sm hover:shadow-md transition-all group overflow-hidden relative text-white bg-gradient-to-br from-blue-600 to-blue-800 border-none">
                    <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="check-circle" class="h-32 w-32 text-white"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-white/20 text-white rounded-2xl border border-white/30 shadow-sm backdrop-blur-md">
                            <i data-lucide="check-circle" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-blue-100 uppercase tracking-widest">Approved Applications</p>
                            <h3 class="text-2xl font-black tracking-tight text-white">{{ number_format($stats['approved']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-white/10 flex items-center justify-between text-[10px] font-bold text-blue-100 uppercase tracking-widest">
                        <span>Total Rent Value</span>
                        <span class="px-2 py-0.5 bg-white/20 text-white rounded-lg border border-white/20">₦{{ number_format($stats['total_ground_rent']) }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs flex items-center gap-2">
                        <i data-lucide="list" class="h-4 w-4 text-blue-600"></i>
                        Application Records
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[2000px] border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                <th class="px-6 py-4 whitespace-nowrap">File Number</th>
                                <th class="px-6 py-4 whitespace-nowrap">Applicant Name</th>
                                <th class="px-6 py-4 whitespace-nowrap">Purpose Clause</th>
                                <th class="px-6 py-4 whitespace-nowrap">Location</th>
                                <th class="px-6 py-4 whitespace-nowrap text-blue-600">Applicant Address</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Plot No</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Layout Plan</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Term</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Ground Rent</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Dev. Period</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Prep. Fees</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Dev. Value</th>
                                {{-- <th class="px-6 py-4 text-right whitespace-nowrap">Dev. Charge</th> --}}
                                <th class="px-6 py-4 text-center whitespace-nowrap">Status</th>
                                <th class="px-6 py-4 whitespace-nowrap">Created By</th>
                                <th class="px-6 py-4 whitespace-nowrap">Date Generated</th>
                                <th class="px-6 py-4 whitespace-nowrap text-blue-600">Application Date</th>
                                <th class="px-6 py-4 text-right sticky right-0 bg-slate-50 border-l border-slate-200 z-10 shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse($recommendations as $rec)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-4 py-2 font-mono font-bold text-slate-900 whitespace-nowrap">{{ $rec->file_number }}</td>
                                <td class="px-4 py-2 text-slate-700 whitespace-nowrap uppercase font-bold text-blue-900">{{ $rec->applicant_name }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->purpose_of_clause }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap uppercase">{{ $rec->location }}</td>
                                <td class="px-4 py-2 text-blue-600 whitespace-nowrap font-medium italic">{{ $rec->resolved_applicant_address ?? $rec->applicant_address ?? 'N/A' }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->plot_number }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->layout_plan_no }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->term }}</td>
                                <td class="px-4 py-2 text-slate-600 text-right whitespace-nowrap">₦{{ number_format($rec->ground_rent, 2) }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->development_period }}</td>
                                <td class="px-4 py-2 text-slate-600 text-right whitespace-nowrap">₦{{ number_format($rec->preparation_fees, 2) }}</td>
                                <td class="px-4 py-2 text-slate-600 text-right whitespace-nowrap">₦{{ number_format($rec->development_value, 2) }}</td>
                                {{-- <td class="px-4 py-2 text-slate-600 text-right whitespace-nowrap">{{$rec->development_charge}}</td> --}}
                                <td class="px-4 py-2 text-center whitespace-nowrap">
                                    @if(!empty($isOssView))
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">
                                            GENERATED
                                        </span>
                                    @elseif($rec->status === \App\Models\LandRecommendation::STATUS_APPROVED)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800">
                                            APPROVED
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                            PENDING
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->creator->name ?? 'System' }}</td>
                                <td class="px-4 py-2 text-slate-500 text-xs whitespace-nowrap">
                                    {{ $rec->created_at ? $rec->created_at->format('Y-m-d h:i A') : 'N/A' }}
                                </td>
                                <td class="px-4 py-2 text-blue-600 whitespace-nowrap font-bold italic">{{ $rec->application_date ? $rec->application_date->format('Y-m-d') : ($rec->created_at ? $rec->created_at->format('Y-m-d') : 'N/A') }}</td>
                                @if(empty($isOssView))
                                    <td class="px-4 py-2 text-right sticky right-0 bg-white shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] border-l border-slate-100 z-10 whitespace-nowrap">
                                        <div x-data="{ 
                                            open: false,
                                            menuStyle: {},
                                            toggleMenu($event) {
                                                if (!this.open) {
                                                    const btn = $event.currentTarget;
                                                    const rect = btn.getBoundingClientRect();
                                                    this.menuStyle = {
                                                        position: 'fixed',
                                                        top: (rect.bottom + 4) + 'px',
                                                        left: (rect.right - 224) + 'px',
                                                        zIndex: 9999
                                                    };
                                                    // If menu would go below viewport, pop it up instead
                                                    const spaceBelow = window.innerHeight - rect.bottom;
                                                    if (spaceBelow < 280) {
                                                        this.menuStyle.top = 'auto';
                                                        this.menuStyle.bottom = (window.innerHeight - rect.top + 4) + 'px';
                                                    }
                                                }
                                                this.open = !this.open;
                                            }
                                        }" class="relative inline-block text-left">
                                            <button @click="toggleMenu($event)" @click.away="open = false" type="button" class="inline-flex items-center p-2 text-slate-500 hover:text-slate-900 rounded-lg hover:bg-slate-100 transition">
                                                <i data-lucide="more-vertical" class="h-5 w-5"></i>
                                            </button>

                                            <div x-show="open" 
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="opacity-0 scale-95"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 x-transition:leave="transition ease-in duration-75"
                                                 x-transition:leave-start="opacity-100 scale-100"
                                                 x-transition:leave-end="opacity-0 scale-95"
                                                 :style="menuStyle"
                                                 class="w-56 rounded-xl shadow-2xl bg-white ring-1 ring-black ring-opacity-5 overflow-hidden" 
                                                 style="display: none;">
                                                <div class="py-1">
                                                    <!-- Edit Action -->
                                                    @if($rec->status === \App\Models\LandRecommendation::STATUS_PENDING)
                                                        <a href="{{ route('land-recommendations.edit', $rec->id) }}" class="flex items-center px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition gap-2">
                                                            <i data-lucide="edit-3" class="h-4 w-4"></i> Edit Record
                                                        </a>
                                                    @else
                                                        <span class="flex items-center px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed gap-2 italic" title="Cannot edit approved document">
                                                            <i data-lucide="edit-3" class="h-4 w-4 text-slate-200"></i> Edit (Disabled)
                                                        </span>
                                                    @endif

                                                    <div class="border-t border-slate-100 my-1"></div>

                                                    <!-- Approval Action -->
                                                    @if($rec->status === \App\Models\LandRecommendation::STATUS_PENDING)
                                                        <button type="button" onclick="approveRecord('{{ $rec->id }}', '{{ $rec->file_number }}')" class="flex w-full items-center px-4 py-2.5 text-sm text-green-600 hover:bg-green-50 transition gap-2 font-bold">
                                                            <i data-lucide="check-circle" class="h-4 w-4"></i> Approve 
                                                        </button>
                                                    @else
                                                        <span class="flex items-center px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed gap-2 italic">
                                                            <i data-lucide="check-circle" class="h-4 w-4 text-slate-200"></i> Approved
                                                        </span>
                                                    @endif

                                                    <div class="border-t border-slate-100 my-1"></div>

                                                    <!-- Batch Print Logic -->
                                                    @if($rec->status === \App\Models\LandRecommendation::STATUS_APPROVED)
                                                        <button type="button" 
                                                                onclick="SmartPrintManager.open('{{ $rec->file_number }}', 'Recommendation For Grant', '{{ route('land-recommendations.print', $rec->id) }}')" 
                                                                class="flex w-full items-center px-4 py-2.5 text-sm text-blue-700 hover:bg-blue-50 transition gap-2 font-bold">
                                                            <i data-lucide="printer" class="h-4 w-4"></i>  Print Manager
                                                        </button>
                                                    @else
                                                        <span class="flex items-center px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed gap-2 italic">
                                                            <i data-lucide="printer" class="h-4 w-4 text-slate-200"></i> Print (Pending Approval)
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                @else
                                    <td class="px-4 py-2 text-right sticky right-0 bg-white shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] border-l border-slate-100 z-10 whitespace-nowrap overflow-visible">
                                        <div x-data="{ 
                                            open: false,
                                            menuStyle: {},
                                            toggleMenu($event) {
                                                if (!this.open) {
                                                    const btn = $event.currentTarget;
                                                    const rect = btn.getBoundingClientRect();
                                                    this.menuStyle = {
                                                        position: 'fixed',
                                                        top: (rect.bottom + 4) + 'px',
                                                        left: Math.max(8, rect.right - 280) + 'px',
                                                        zIndex: 99999
                                                    };
                                                    const spaceBelow = window.innerHeight - rect.bottom;
                                                    if (spaceBelow < 80) {
                                                        this.menuStyle.top = 'auto';
                                                        this.menuStyle.bottom = (window.innerHeight - rect.top + 4) + 'px';
                                                    }
                                                }
                                                this.open = !this.open;
                                            }
                                        }" class="relative inline-block text-left">
                                            <button @click="toggleMenu($event)" @click.away="open = false" type="button" class="inline-flex items-center p-2 text-slate-500 hover:text-slate-900 rounded-lg hover:bg-slate-100 transition">
                                                <i data-lucide="more-vertical" class="h-5 w-5"></i>
                                            </button>

                                            <template x-teleport="body">
                                                <div x-show="open"
                                                     x-transition:enter="transition ease-out duration-100"
                                                     x-transition:enter-start="opacity-0 scale-95"
                                                     x-transition:enter-end="opacity-100 scale-100"
                                                     x-transition:leave="transition ease-in duration-75"
                                                     x-transition:leave-start="opacity-100 scale-100"
                                                     x-transition:leave-end="opacity-0 scale-95"
                                                     :style="menuStyle"
                                                     class="w-auto rounded-xl shadow-2xl bg-white ring-1 ring-black ring-opacity-5 overflow-hidden"
                                                     style="display: none;">
                                                    <div class="py-1">
                                                        <button type="button"
                                                                @click="open = false"
                                                                onclick="SmartPrintManager.open('{{ $rec->file_number }}', 'OSS Recommendation For Grant', '{{ route('land-recommendations.print', $rec->id) }}')"
                                                            class="flex w-full items-center px-4 py-2.5 text-sm text-blue-700 hover:bg-blue-50 transition gap-2 font-bold whitespace-nowrap">
                                                        <i data-lucide="printer" class="h-4 w-4 flex-shrink-0"></i>
                                                        OSS Print Recommendation
                                                    </button>
                                                </div>
                                            </div>
                                            </template>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                            @empty
                            <tr>
                                <td colspan="16" class="px-8 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 mb-4">
                                            <i data-lucide="file-text" class="h-6 w-6"></i>
                                        </div>
                                        <p class="text-slate-500 font-medium">No recommendations found.</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($recommendations->hasPages())
                <div class="px-8 py-6 border-t border-slate-100">
                    {{ $recommendations->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
    @include('admin.footer')
</div>

@push('scripts')
<script>
    @if(empty($isOssView))
    function approveRecord(id, fileNumber) {
        Swal.fire({
            title: 'Approve Recommendation?',
            text: `Are you sure you want to approve ${fileNumber}? Once approved, it can be printed but not edited.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Approve it!',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch(`{{ url('land-recommendations') }}/${id}/approve`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) throw new Error(response.statusText);
                    return response.json();
                })
                .catch(error => {
                    Swal.showValidationMessage(`Request failed: ${error}`);
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire('Approved!', 'Document has been approved successfully.', 'success')
                .then(() => window.location.reload());
            }
        });
    }
    @endif
</script>
@endpush

@push('styles')
<style>
    .oss-label {
        margin-top: 3.5mm;
        font-size: 3.2mm;
        font-weight: 700;
        letter-spacing: 1.2mm;
        color: #94a3b8;
        text-transform: uppercase;
    }
</style>
@endpush
@endsection
