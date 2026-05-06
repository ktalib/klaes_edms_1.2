@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header')
    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">SLTR Recommendations</h1>
                    <p class="text-slate-500 text-sm mt-1">Manage SLTR Right of Occupancy recommendation entries.</p>
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <form action="{{ route('sltr-recommendations.index') }}" method="GET" class="relative group flex-1 md:w-80">
                        <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400"></i>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Search SLTR No, applicant, location..."
                               class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all shadow-sm">
                    </form>
                    @if(request('role') !== 'pp_director')
                    <button onclick="openCreateModal()"
                            class="flex items-center gap-2 px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-bold rounded-xl shadow-lg shadow-teal-200 transition whitespace-nowrap">
                        <i data-lucide="plus" class="h-4 w-4"></i> New Recommendation
                    </button>
                    @endif
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-teal-50 text-teal-600 rounded-2xl border border-teal-100">
                            <i data-lucide="file-text" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total</p>
                            <h3 class="text-2xl font-black text-slate-800">{{ number_format($stats['total']) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-amber-50 text-amber-600 rounded-2xl border border-amber-100">
                            <i data-lucide="clock" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Pending</p>
                            <h3 class="text-2xl font-black text-slate-800">{{ number_format($stats['pending']) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-emerald-50 text-emerald-600 rounded-2xl border border-emerald-100">
                            <i data-lucide="shield-check" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Approved</p>
                            <h3 class="text-2xl font-black text-slate-800">{{ number_format($stats['approved']) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-blue-50 text-blue-600 rounded-2xl border border-blue-100">
                            <i data-lucide="zap" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">RofO Generated</p>
                            <h3 class="text-2xl font-black text-slate-800">{{ number_format($stats['rofo_generated']) }}</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Records Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs flex items-center gap-2">
                        <i data-lucide="list" class="h-4 w-4 text-teal-600"></i>
                        SLTR Recommendation Records
                    </h3>
                    {{-- RofO Management button hidden for now --}}
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[1600px] border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                <th class="px-6 py-4 whitespace-nowrap">SLTR Number</th>
                                <th class="px-6 py-4 whitespace-nowrap">Applicant Name</th>
                                <th class="px-6 py-4 whitespace-nowrap">Location</th>
                                <th class="px-6 py-4 whitespace-nowrap">LGA</th>
                                <th class="px-6 py-4 whitespace-nowrap">Land Use</th>
                                <th class="px-6 py-4 whitespace-nowrap">Plot No</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Term</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Ground Rent</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Processing Fee</th>
                                <th class="px-6 py-4 whitespace-nowrap">App. Date</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Status</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">RofO Status</th>
                                <th class="px-6 py-4 whitespace-nowrap">Created By</th>
                                <th class="px-6 py-4 text-right sticky right-0 bg-slate-50 border-l border-slate-200 z-10 shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse($recommendations as $rec)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-4 py-2 font-mono font-bold text-slate-900 whitespace-nowrap">{{ $rec->sltr_number ?? '—' }}</td>
                                <td class="px-4 py-2 text-slate-700 whitespace-nowrap">{{ $rec->applicant_name }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->location }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->lga }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->land_use }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->plot_number }}</td>
                                <td class="px-4 py-2 text-center text-slate-600 whitespace-nowrap">{{ $rec->term }} yrs</td>
                                <td class="px-4 py-2 text-right text-slate-600 whitespace-nowrap">₦{{ number_format($rec->ground_rent, 2) }}</td>
                                <td class="px-4 py-2 text-right text-slate-600 whitespace-nowrap">₦{{ number_format($rec->processing_fee, 2) }}</td>
                                <td class="px-4 py-2 text-slate-500 text-xs whitespace-nowrap">{{ $rec->application_date?->format('Y-m-d') }}</td>
                                <td class="px-4 py-2 text-center whitespace-nowrap">
                                    @if($rec->status === \App\Models\SltrRecommendation::STATUS_APPROVED)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">APPROVED</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">PENDING</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center whitespace-nowrap">
                                    @if($rec->rofo_status === \App\Models\SltrRecommendation::ROFO_GENERATED)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">GENERATED</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">PENDING</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->creator->name ?? 'System' }}</td>
                                <td class="px-4 py-2 text-right sticky right-0 bg-white shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] border-l border-slate-100 z-10 whitespace-nowrap">
                                    <div x-data="{
                                        open: false,
                                        menuStyle: {},
                                        toggleMenu($event) {
                                            if (!this.open) {
                                                const btn = $event.currentTarget;
                                                const rect = btn.getBoundingClientRect();
                                                this.menuStyle = { position: 'fixed', top: (rect.bottom + 4) + 'px', left: (rect.right - 192) + 'px', zIndex: 9999 };
                                            }
                                            this.open = !this.open;
                                        }
                                    }" @click.outside="open = false">
                                        <button @click="toggleMenu($event)" class="p-1.5 hover:bg-slate-100 rounded-lg transition">
                                            <i data-lucide="more-horizontal" class="h-4 w-4 text-slate-500"></i>
                                        </button>
                                        <div x-show="open" x-transition :style="menuStyle"
                                             class="w-48 bg-white rounded-xl shadow-xl border border-slate-200 py-1 text-sm">
                                            <button type="button"
                                                    onclick="openEditModal({{ $rec->id }}, {{ json_encode($rec) }})"
                                                    class="flex w-full items-center px-4 py-2.5 text-slate-700 hover:bg-slate-50 transition gap-2">
                                                <i data-lucide="pencil" class="h-4 w-4"></i> Edit
                                            </button>
                                            <a href="{{ route('sltr-recommendations.print', $rec->id) }}" target="_blank"
                                               class="flex w-full items-center px-4 py-2.5 text-blue-700 hover:bg-blue-50 transition gap-2 font-medium">
                                                <i data-lucide="printer" class="h-4 w-4"></i> Print Recommendation
                                            </a>
                                            @if($rec->rofo_status === \App\Models\SltrRecommendation::ROFO_GENERATED)
                                            <a href="{{ route('sltr-recommendations.print', $rec->id) }}" target="_blank"
                                               class="flex w-full items-center px-4 py-2.5 text-teal-700 hover:bg-teal-50 transition gap-2 font-bold hidden">
                                                <i data-lucide="file-check" class="h-4 w-4"></i> Print RofO
                                            </a>
                                            @endif
                                            @if($rec->status !== \App\Models\SltrRecommendation::STATUS_APPROVED)
                                            <button type="button"
                                                    onclick="approveRecord({{ $rec->id }}, '{{ $rec->sltr_number ?? $rec->applicant_name }}')"
                                                    class="flex w-full items-center px-4 py-2.5 text-emerald-700 hover:bg-emerald-50 transition gap-2 font-bold">
                                                <i data-lucide="check-circle" class="h-4 w-4"></i> Approve
                                            </button>
                                            @endif
                                            <div class="border-t border-slate-100 my-1"></div>
                                            <button type="button"
                                                    onclick="deleteRecord({{ $rec->id }}, '{{ $rec->sltr_number ?? $rec->applicant_name }}')"
                                                    class="flex w-full items-center px-4 py-2.5 text-red-600 hover:bg-red-50 transition gap-2">
                                                <i data-lucide="trash-2" class="h-4 w-4"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="14" class="px-8 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 mb-4">
                                            <i data-lucide="file-text" class="h-6 w-6"></i>
                                        </div>
                                        <p class="text-slate-500 font-medium">No recommendations found.</p>
                                        <button onclick="openCreateModal()" class="mt-3 text-sm text-teal-600 hover:underline font-bold">+ Add the first one</button>
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

<!-- Create / Edit Modal -->
<div id="rec-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div id="rec-modal-backdrop" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden border border-slate-100 z-10">

        <!-- Header -->
        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
            <div>
                <h3 class="text-xl font-bold text-slate-900" id="modal-title">New Recommendation</h3>
                <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-semibold">SLTR Recommendation Details</p>
            </div>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 p-2 hover:bg-slate-50 rounded-xl transition">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>

        <!-- Scrollable Body -->
        <form id="rec-form" class="flex-1 overflow-y-auto">
            <input type="hidden" id="rec-id" value="">
            <div class="px-8 py-8 space-y-8">

                <!-- Section 1: Reference & Applicant -->
                <div class="space-y-4">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-2xl bg-teal-600 flex items-center justify-center text-white shadow-lg shadow-teal-200">
                            <i data-lucide="file-text" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest">Reference & Applicant</h4>
                            <p class="text-[10px] text-slate-500 font-bold uppercase">File number and applicant identity</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">File Number</label>
                            <div class="flex items-center gap-2">
                                <input type="text" id="f-sltr_number-display" readonly
                                    placeholder="Click 'Select File Number' to choose"
                                    class="flex-1 px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-medium cursor-pointer focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition"
                                    onclick="sltrOpenFileSelector()">
                                <input type="hidden" id="f-sltr_number">
                                <button type="button" onclick="sltrOpenFileSelector()"
                                    class="inline-flex items-center gap-1.5 px-4 py-3 bg-teal-600 hover:bg-teal-700 text-white text-sm font-bold rounded-xl shadow-sm whitespace-nowrap transition">
                                    <i data-lucide="file-search" class="h-4 w-4"></i> Select File Number
                                </button>
                                <button type="button" id="f-sltr-clear-btn" onclick="sltrClearFileNumber()"
                                    class="hidden p-2 text-red-400 hover:text-red-600 transition" title="Clear">
                                    <i data-lucide="x-circle" class="h-5 w-5"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Applicant Name <span class="text-red-500">*</span></label>
                            <input type="text" id="f-applicant_name"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium"
                                placeholder="e.g. Musa Yakubu">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Application Date</label>
                            <input type="date" id="f-application_date"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Applicant Address Builder -->
                <div class="pt-6 border-t border-slate-100 space-y-4">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-2xl bg-blue-600 flex items-center justify-center text-white shadow-lg shadow-blue-200">
                            <i data-lucide="home" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest">Applicant Address</h4>
                            <p class="text-[10px] text-slate-500 font-bold uppercase">Build the full correspondence address</p>
                        </div>
                    </div>
                    <div class="space-y-3 p-4 bg-slate-50/50 rounded-2xl border border-slate-100">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">House No</label>
                                <input type="text" id="addr-house-no"
                                    class="addr-component w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-teal-500 transition"
                                    placeholder="e.g. 12">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Street</label>
                                <select id="addr-street"
                                    class="addr-component w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-teal-500 transition">
                                    <option value="">Select Street</option>
                                    @foreach($streetOptions as $street)
                                        <option value="{{ $street->name }}">{{ $street->name }}</option>
                                    @endforeach
                                </select>
                                <input type="text" id="addr-street-other"
                                    class="hidden addr-component w-full mt-2 px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-teal-500 transition"
                                    placeholder="Specify street...">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">District</label>
                                <select id="addr-district"
                                    class="addr-component w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-teal-500 transition">
                                    <option value="">Select District</option>
                                    @foreach($districts as $district)
                                        <option value="{{ $district->name }}">{{ $district->name }}</option>
                                    @endforeach
                                    <option value="Other">Other</option>
                                </select>
                                <input type="text" id="addr-district-other"
                                    class="hidden addr-component w-full mt-2 px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-teal-500 transition"
                                    placeholder="Specify district...">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">LGA</label>
                                <select id="addr-lga"
                                    class="addr-component w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-teal-500 transition">
                                    <option value="">Select LGA</option>
                                    @foreach($lgas as $lga)
                                        <option value="{{ $lga->LGAName }}">{{ $lga->LGAName }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">State</label>
                            <select id="addr-state"
                                class="addr-component w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-teal-500 transition">
                                <option value="">Select State</option>
                                @foreach($states as $state)
                                    <option value="{{ $state->StateName }}" {{ $state->StateName == 'Kano' ? 'selected' : '' }}>{{ $state->StateName }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <input type="hidden" id="f-applicant_address">
                    <div class="p-3 bg-white rounded-xl border border-dashed border-slate-200 text-xs text-slate-500">
                        <span class="font-bold text-slate-400 uppercase text-[10px] block mb-1">Full Address Preview:</span>
                        <span id="addr-preview" class="italic">No address built yet...</span>
                    </div>
                </div>

                <!-- Section 3: Property / Location Builder -->
                <div class="pt-6 border-t border-slate-100 space-y-4">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-2xl bg-indigo-600 flex items-center justify-center text-white shadow-lg shadow-indigo-200">
                            <i data-lucide="map-pin" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest">Property / Location</h4>
                            <p class="text-[10px] text-slate-500 font-bold uppercase">Location of the subject plot</p>
                        </div>
                    </div>
                    <div class="space-y-3 p-4 bg-slate-50/50 rounded-2xl border border-slate-100">
                        <div class="grid grid-cols-3 gap-3">

                                <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Plot Number</label>
                                <input type="text"  
                                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-indigo-500 transition"
                                     value="Piece of land">
                            </div>


                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">District / Area</label>
                                <select id="prop-district"
                                    class="prop-component w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-indigo-500 transition">
                                    <option value="">Select District</option>
                                    @foreach($districts as $district)
                                        <option value="{{ $district->name }}">{{ $district->name }}</option>
                                    @endforeach
                                    <option value="Other">Other</option>
                                </select>
                                <input type="text" id="prop-district-other"
                                    class="hidden prop-component w-full mt-2 px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-indigo-500 transition"
                                    placeholder="Specify district...">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">LGA <span class="text-red-500">*</span></label>
                                <select id="prop-lga"
                                    class="prop-component w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-indigo-500 transition">
                                    <option value="">Select LGA</option>
                                    @foreach($lgas as $lga)
                                        <option value="{{ $lga->LGAName }}">{{ $lga->LGAName }}</option>
                                    @endforeach
                                </select>
                            </div>
                        
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">State</label>
                            <select id="prop-state"
                                class="prop-component w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-indigo-500 transition">
                                <option value="">Select State</option>
                                @foreach($states as $state)
                                    <option value="{{ $state->StateName }}" {{ $state->StateName == 'Kano' ? 'selected' : '' }}>{{ $state->StateName }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <input type="hidden" id="f-location">
                    <input type="hidden" id="f-lga">
                    <div class="p-3 bg-white rounded-xl border border-dashed border-slate-200 text-xs text-slate-500">
                        <span class="font-bold text-slate-400 uppercase text-[10px] block mb-1">Full Location Preview:</span>
                        <span id="prop-preview" class="italic">No location built yet...</span>
                    </div>
                </div>

                <!-- Section 4: Grant Terms -->
                <div class="pt-6 border-t border-slate-100 space-y-4">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-2xl bg-amber-500 flex items-center justify-center text-white shadow-lg shadow-amber-200">
                            <i data-lucide="scroll-text" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest">Grant Terms</h4>
                            <p class="text-[10px] text-slate-500 font-bold uppercase">Land use, tenure, fees</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Land Use</label>
                            <select id="f-land_use_id"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium"
                                onchange="syncLandUseText()">
                                <option value="">-- Select Land Use --</option>
                                @foreach($landUseOptions as $opt)
                                    <option value="{{ $opt->id }}" data-text="{{ $opt->landuse }}">{{ $opt->landuse }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" id="f-land_use">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Purpose Clause</label>
                            <select id="f-purpose_of_clause"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium">
                                <option value="">-- Select Purpose --</option>
                                @foreach($purposeOptions as $opt)
                                    <option value="{{ $opt->name }}">{{ $opt->name }}</option>
                                @endforeach
                            </select>
                        </div> 
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Term (Years)</label>
                            <input type="text" id="f-term" value="99"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Ground Rent (₦)</label>
                            <input type="number" step="0.01" id="f-ground_rent"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium"
                                placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Processing Fee (₦)</label>
                            <input type="number" step="0.01" id="f-processing_fee"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium"
                                placeholder="0.00">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Notes</label>
                            <textarea id="f-notes" rows="2"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium resize-none"
                                placeholder="Optional notes..."></textarea>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer -->
            <div class="px-8 py-5 bg-slate-50 border-t border-slate-200 flex justify-end gap-3 rounded-b-3xl shrink-0">
                <button type="button" onclick="closeModal()" class="px-6 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-200 rounded-xl transition">Cancel</button>
                <button type="submit" id="submit-btn" class="px-8 py-2.5 text-sm font-bold text-white bg-teal-600 hover:bg-teal-700 rounded-xl shadow-lg shadow-teal-200 transition flex items-center gap-2">
                    <i data-lucide="save" class="h-4 w-4"></i> Save Recommendation
                </button>
            </div>
        </form>
    </div>
</div>

@include('components.global-fileno-modal')

@push('scripts')
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script>
// ── SLTR File Number Selector ────────────────────────────────────────
function sltrOpenFileSelector() {
    if (typeof window.GlobalFileNoModal === 'undefined' || typeof window.GlobalFileNoModal.open !== 'function') {
        alert('File Number Selector is not available on this page.');
        return;
    }
    window.GlobalFileNoModal.open({
        callback: function(fileData) {
            var fileNo = (fileData && fileData.fileNumber ? fileData.fileNumber : '').toString().trim().replace(/-+$/, '');
            if (!fileNo) return;
            document.getElementById('f-sltr_number').value = fileNo;
            document.getElementById('f-sltr_number-display').value = fileNo;
            document.getElementById('f-sltr-clear-btn').classList.remove('hidden');

            // Backfill from fileNumber table record (file_name only)
            var r = fileData.record;
            if (r && r.file_name) {
                var nameField = document.getElementById('f-applicant_name');
                if (nameField && !nameField.value) nameField.value = r.file_name;
            }

            // Fetch richer details from file_indexings
            $.ajax({
                url: '/api/file-indexings/lookup-by-number',
                method: 'GET',
                data: { file_number: fileNo },
                dataType: 'json',
                timeout: 7000
            }).done(function(response) {
                if (!response || !response.success || !response.data) return;
                var d = response.data;

                var nameField = document.getElementById('f-applicant_name');
                if (nameField && !nameField.value && d.file_name) nameField.value = d.file_name;

                // Backfill visible prop dropdowns and rebuild preview
                var propLga = document.getElementById('prop-lga');
                if (propLga && !propLga.value && d.lga) {
                    setSelect(propLga, d.lga);
                }
                var propDistrict = document.getElementById('prop-district');
                if (propDistrict && !propDistrict.value && d.district) {
                    setSelect(propDistrict, d.district);
                    var propDistrictOther = document.getElementById('prop-district-other');
                    if (propDistrictOther) propDistrictOther.classList.toggle('hidden', propDistrict.value !== 'Other');
                }
                if ((d.lga || d.district) && typeof assembleProp === 'function') assembleProp();

                var landUseField = document.getElementById('f-land_use_id');
                if (landUseField && !landUseField.value && d.land_use_type) {
                    for (var i = 0; i < landUseField.options.length; i++) {
                        var optText = landUseField.options[i].getAttribute('data-text') || landUseField.options[i].text;
                        if (optText.toUpperCase() === d.land_use_type.toUpperCase()) {
                            landUseField.selectedIndex = i;
                            syncLandUseText();
                            break;
                        }
                    }
                }
            });

            window.GlobalFileNoModal.close();
        }
    });
}
function sltrClearFileNumber() {
    document.getElementById('f-sltr_number').value = '';
    document.getElementById('f-sltr_number-display').value = '';
    document.getElementById('f-sltr-clear-btn').classList.add('hidden');
}
</script>
<script>
// ── Address Builder: Applicant ──────────────────────────────────────
function assembleAddr() {
    const houseNo  = document.getElementById('addr-house-no').value.trim();
    const streetSel = document.getElementById('addr-street').value;
    const streetOth = document.getElementById('addr-street-other').value.trim();
    const street   = streetSel === 'Other' ? streetOth : streetSel;
    const distSel  = document.getElementById('addr-district').value;
    const distOth  = document.getElementById('addr-district-other').value.trim();
    const district = distSel === 'Other' ? distOth : distSel;
    const lga      = document.getElementById('addr-lga').value;
    const state    = document.getElementById('addr-state').value;

    const parts = [houseNo, street, district, lga, state].filter(Boolean);
    const assembled = parts.join(', ');
    document.getElementById('f-applicant_address').value = assembled;
    document.getElementById('addr-preview').textContent = assembled || 'No address built yet...';
}

document.querySelectorAll('.addr-component').forEach(el => el.addEventListener('change', assembleAddr));
document.querySelectorAll('.addr-component').forEach(el => el.addEventListener('input', assembleAddr));

document.getElementById('addr-street').addEventListener('change', function () {
    const other = document.getElementById('addr-street-other');
    other.classList.toggle('hidden', this.value !== 'Other');
    assembleAddr();
});
document.getElementById('addr-district').addEventListener('change', function () {
    const other = document.getElementById('addr-district-other');
    other.classList.toggle('hidden', this.value !== 'Other');
    assembleAddr();
});

// ── Property / Location Builder ─────────────────────────────────────
function assembleProp() {
    const distSel  = document.getElementById('prop-district').value;
    const distOth  = document.getElementById('prop-district-other').value.trim();
    const district = distSel === 'Other' ? distOth : distSel;
    const lga      = document.getElementById('prop-lga').value;
    const state    = document.getElementById('prop-state').value;

    const locationParts = [district, lga, state].filter(Boolean);
    const assembled = locationParts.join(', ');
    document.getElementById('f-location').value = assembled;
    document.getElementById('f-lga').value = lga;
    document.getElementById('prop-preview').textContent = assembled || 'No location built yet...';
}

document.querySelectorAll('.prop-component').forEach(el => el.addEventListener('change', assembleProp));
document.querySelectorAll('.prop-component').forEach(el => el.addEventListener('input', assembleProp));

document.getElementById('prop-district').addEventListener('change', function () {
    const other = document.getElementById('prop-district-other');
    other.classList.toggle('hidden', this.value !== 'Other');
    assembleProp();
});

// ── Helpers ─────────────────────────────────────────────────────────
function setSelect(el, val) {
    if (!el) return;
    // Try direct match first
    for (const opt of el.options) {
        if (opt.value === val) { el.value = val; return; }
    }
    // Fall back to "Other" with manual input
    for (const opt of el.options) {
        if (opt.value === 'Other') {
            el.value = 'Other';
            // find sibling other-input (next sibling or by convention)
            const otherId = el.id + '-other';
            const other = document.getElementById(otherId);
            if (other) { other.value = val; other.classList.remove('hidden'); }
            return;
        }
    }
}

function resetAddrBuilder() {
    ['addr-house-no','addr-street-other','addr-district-other'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    ['addr-street','addr-district','addr-lga','addr-state'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.selectedIndex = 0;
    });
    // Re-select Kano as default state
    const addrState = document.getElementById('addr-state');
    if (addrState) for (const opt of addrState.options) { if (opt.value === 'Kano') { addrState.value = 'Kano'; break; } }
    ['addr-street-other','addr-district-other'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.add('hidden');
    });
    document.getElementById('f-applicant_address').value = '';
    document.getElementById('addr-preview').textContent = 'No address built yet...';
}

function resetPropBuilder() {
    ['prop-district-other'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.value = ''; el.classList.add('hidden'); }
    });
    ['prop-district','prop-lga','prop-state'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.selectedIndex = 0;
    });
    const propState = document.getElementById('prop-state');
    if (propState) for (const opt of propState.options) { if (opt.value === 'Kano') { propState.value = 'Kano'; break; } }
    document.getElementById('f-location').value = '';
    document.getElementById('f-lga').value = '';
    document.getElementById('prop-preview').textContent = 'No location built yet...';
}

// ── Scalar fields (not address-builder) ────────────────────────────
const SCALAR_FIELDS = ['applicant_name','application_date','plot_number','purpose_of_clause','term','ground_rent','processing_fee','notes'];
function syncLandUseText() {
    const sel = document.getElementById('f-land_use_id');
    const hid = document.getElementById('f-land_use');
    if (!sel || !hid) return;
    const opt = sel.options[sel.selectedIndex];
    hid.value = (opt && opt.value) ? (opt.getAttribute('data-text') || opt.text) : '';
}

function openCreateModal() {
    document.getElementById('rec-id').value = '';
    document.getElementById('modal-title').textContent = 'New Recommendation';
    SCALAR_FIELDS.forEach(f => {
        const el = document.getElementById('f-' + f);
        if (el) el.value = f === 'term' ? 40 : '';
    });
    // Reset file number selector
    document.getElementById('f-sltr_number').value = '';
    document.getElementById('f-sltr_number-display').value = '';
    document.getElementById('f-sltr-clear-btn').classList.add('hidden');
    // Reset land use
    const luSel = document.getElementById('f-land_use_id');
    if (luSel) luSel.selectedIndex = 0;
    const luHid = document.getElementById('f-land_use');
    if (luHid) luHid.value = '';
    resetAddrBuilder();
    resetPropBuilder();
    document.getElementById('rec-modal').classList.remove('hidden');
}

function openEditModal(id, data) {
    document.getElementById('rec-id').value = id;
    document.getElementById('modal-title').textContent = 'Edit Recommendation';

    SCALAR_FIELDS.forEach(f => {
        const el = document.getElementById('f-' + f);
        if (!el) return;
        let v = data[f] ?? '';
        if (f === 'application_date' && v) v = v.substring(0, 10);
        el.value = v;
    });

    // Restore file number selector
    const storedFileNo = data.sltr_number ?? '';
    document.getElementById('f-sltr_number').value = storedFileNo;
    document.getElementById('f-sltr_number-display').value = storedFileNo;
    if (storedFileNo) document.getElementById('f-sltr-clear-btn').classList.remove('hidden');
    else document.getElementById('f-sltr-clear-btn').classList.add('hidden');

    // For edit, we set the hidden assembled fields directly and show them in previews.
    // The builder sub-fields will show "Other" fallback with the stored value.
    resetAddrBuilder();
    resetPropBuilder();

    // Restore applicant address as "Other" district/street pattern if not found in dropdowns
    const storedAddr = data.applicant_address ?? '';
    if (storedAddr) {
        // Show stored value in "other" street field as a best-effort restore
        const addrStreetOther = document.getElementById('addr-street-other');
        const addrStreetSel   = document.getElementById('addr-street');
        addrStreetSel.value = 'Other';
        addrStreetOther.value = storedAddr;
        addrStreetOther.classList.remove('hidden');
        document.getElementById('f-applicant_address').value = storedAddr;
        document.getElementById('addr-preview').textContent = storedAddr;
    }

    const storedLoc = data.location ?? '';
    const storedLga = data.lga ?? '';
    if (storedLoc || storedLga) {
        const propDistOther = document.getElementById('prop-district-other');
        document.getElementById('prop-district').value = 'Other';
        propDistOther.value = storedLoc;
        propDistOther.classList.remove('hidden');
        setSelect(document.getElementById('prop-lga'), storedLga);
        document.getElementById('f-location').value = storedLoc;
        document.getElementById('f-lga').value = storedLga;
        document.getElementById('prop-preview').textContent = [storedLoc, storedLga].filter(Boolean).join(', ');
    }

    // Restore land use: find option by data-text match
    const storedLandUse = data.land_use ?? '';
    const luSel = document.getElementById('f-land_use_id');
    const luHid = document.getElementById('f-land_use');
    if (luSel && luHid) {
        luSel.selectedIndex = 0;
        luHid.value = '';
        if (storedLandUse) {
            for (let i = 0; i < luSel.options.length; i++) {
                const optText = luSel.options[i].getAttribute('data-text') || luSel.options[i].text;
                if (optText.toUpperCase() === storedLandUse.toUpperCase()) {
                    luSel.selectedIndex = i;
                    luHid.value = storedLandUse;
                    break;
                }
            }
        }
    }

    document.getElementById('rec-modal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('rec-modal').classList.add('hidden');
}

document.getElementById('rec-modal-backdrop').addEventListener('click', closeModal);

document.getElementById('rec-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const id = document.getElementById('rec-id').value;
    const url = id ? `/sltr-recommendations/${id}` : '/sltr-recommendations';
    const method = id ? 'PUT' : 'POST';
    const body = {};

    SCALAR_FIELDS.forEach(f => {
        const el = document.getElementById('f-' + f);
        if (!el) return;
        const v = el.value;
        if (['term','ground_rent','processing_fee'].includes(f)) {
            body[f] = v === '' ? null : (f === 'term' ? parseInt(v, 10) : parseFloat(v));
        } else {
            body[f] = v;
        }
    });

    // Add file number and assembled address builder values
    body.sltr_number       = document.getElementById('f-sltr_number').value;
    body.applicant_address = document.getElementById('f-applicant_address').value;
    body.location          = document.getElementById('f-location').value;
    body.lga               = document.getElementById('f-lga').value;
    body.land_use          = document.getElementById('f-land_use').value;

    const btn = document.getElementById('submit-btn');
    btn.disabled = true;

    try {
        const res = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) {
            closeModal();
            window.location.reload();
        } else {
            alert(data.message || 'Error saving recommendation.');
        }
    } catch (err) {
        alert('Network error. Please try again.');
    } finally {
        btn.disabled = false;
    }
});

async function approveRecord(id, label) {
    if (!confirm(`Approve recommendation: ${label}?`)) return;
    const res = await fetch(`/sltr-recommendations/${id}/approve`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    });
    const data = await res.json();
    if (data.success) { window.location.reload(); }
    else { alert(data.message || 'Error approving.'); }
}

async function deleteRecord(id, label) {
    if (!confirm(`Delete recommendation: ${label}? This cannot be undone.`)) return;
    const res = await fetch(`/sltr-recommendations/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    });
    const data = await res.json();
    if (data.success) { window.location.reload(); }
    else { alert(data.message || 'Error deleting.'); }
}
</script>
@endpush
@endsection
