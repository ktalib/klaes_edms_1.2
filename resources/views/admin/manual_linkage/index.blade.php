@extends('layouts.app')

@section('styles')
<style>
    .transition-all { transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
    .badge-trans { animation: fadeIn 0.3s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
    .modal-backdrop { background-color: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); }
    .child-row-enter { animation: fadeIn 0.2s ease-out; }
</style>
@endsection
 
@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle'       => 'Legacy Parcel Update',
        'PageDescription' => 'Backfill lineage for files already processed manually before Change of Purpose and Parcel Update workflows were built.'
    ])
  
    <div class="py-10 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            {{-- Page Heading --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 mt-1">Legacy Parcel Update</h1>
                    <p class="text-sm text-slate-500 mt-1">Link already-processed legacy files, decommission old sources, and restore Parcel Update history.</p>
                </div>
                <button type="button" onclick="openLinkageModal()"
                    class="inline-flex items-center gap-2 px-6 py-3.5 bg-blue-600 text-white rounded-xl text-sm font-bold shadow-md hover:bg-blue-700 transition-all transform hover:-translate-y-0.5">
                    <i data-lucide="plus" class="w-5 h-5"></i> Backfill Linkage
                </button>
            </div>

            {{-- Alerts --}}
            @if(session('success'))
                <div class="p-4 bg-green-50 border-l-4 border-green-500 rounded-xl text-green-700 shadow-sm flex items-center gap-3">
                    <i data-lucide="check-circle" class="w-5 h-5 text-green-500"></i>
                    <p class="font-semibold">{{ session('success') }}</p>
                </div>
            @endif

            @if($errors->any())
                <div class="p-4 bg-red-50 border-l-4 border-red-500 rounded-xl text-red-700 shadow-sm space-y-1">
                    <div class="flex items-center gap-3">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i>
                        <p class="font-semibold">Something went wrong:</p>
                    </div>
                    <ul class="list-disc list-inside text-sm pl-8">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{-- Linkage History Table --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="font-bold text-slate-800 text-lg">Historical Manual Linkages</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Audit log of legacy Parcel Update lineage backfilled on KLAES.</p>
                    </div>
                    <span class="bg-slate-100 text-slate-600 font-semibold px-3 py-1 rounded-full text-xs self-start sm:self-auto">
                        {{ $linkages->total() }} Total Linkages
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[1100px]">
                        <thead>
                            <tr class="bg-slate-50 text-slate-400 font-semibold text-xs border-b border-slate-200">
                                <th class="px-5 py-4 whitespace-nowrap">Workflow</th>
                                <th class="px-5 py-4 whitespace-nowrap">Old File(s) Decommissioned</th>
                                <th class="px-5 py-4 whitespace-nowrap">Linked New File</th>
                                <th class="px-5 py-4 whitespace-nowrap">Applicant / Holder</th>
                                <th class="px-5 py-4 whitespace-nowrap">Created By</th>
                                <th class="px-5 py-4 whitespace-nowrap">Date Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                            @forelse($linkages as $link)
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="px-5 py-4">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap
                                            @if($link->workflow_type === 'Subdivision') bg-indigo-50 text-indigo-700
                                            @elseif($link->workflow_type === 'Merger') bg-amber-50 text-amber-700
                                            @elseif($link->workflow_type === 'Plot Extension') bg-emerald-50 text-emerald-700
                                            @else bg-blue-50 text-blue-700 @endif">
                                            {{ $link->workflow_type }}
                                        </span>
                                        @if(!empty($link->linkage_group_id ?? null))
                                            <span class="block mt-1 text-[9px] font-mono text-slate-300 truncate max-w-[80px]" title="Group: {{ $link->linkage_group_id }}">
                                                grp:{{ substr($link->linkage_group_id, 0, 8) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-xs font-mono font-medium max-w-[180px]">
                                        @php $oldFiles = json_decode($link->old_file_numbers, true) ?: [$link->old_file_numbers]; @endphp
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($oldFiles as $oldFile)
                                                <span class="bg-slate-100 text-slate-800 px-1.5 py-0.5 rounded border border-slate-200 whitespace-nowrap">{{ $oldFile }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="font-mono font-bold text-slate-900 text-xs block">{{ $link->new_file_number }}</span>
                                        <span class="text-[10px] text-blue-600 font-bold font-mono">Prop: {{ $link->prop_id ?: '—' }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-xs">{{ $link->applicant_name ?: '—' }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center text-[10px] font-bold text-slate-600 flex-shrink-0">
                                                {{ substr($link->processed_by, 0, 2) }}
                                            </div>
                                            <span class="text-xs font-medium text-slate-800">{{ $link->processed_by }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-xs text-slate-400 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($link->created_at)->format('d/m/Y H:i') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-slate-400 italic">
                                        No legacy process linkages recorded yet. Click "Backfill Linkage" to record one.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($linkages->hasPages())
                    <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
                        {{ $linkages->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</div>

{{-- MODAL --}}
<div id="linkage-modal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
    <div class="fixed inset-0 modal-backdrop transition-opacity duration-300" onclick="closeLinkageModal()"></div>

    <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 z-10 w-full max-w-[92%] lg:max-w-6xl max-h-[92vh] overflow-hidden flex flex-col transition-all duration-300 transform scale-95 opacity-0" id="modal-content">

        {{-- Header --}}
        <div class="bg-gradient-to-r from-blue-700 to-indigo-800 px-8 py-5 flex items-center justify-between text-white flex-shrink-0">
            <div class="flex items-center gap-3">
                <i data-lucide="link" class="w-6 h-6"></i>
                <div>
                    <h2 class="text-lg font-bold">Backfill Manual Process Linkage</h2>
                    <p class="text-xs text-blue-200">Connect old source files to the already-processed destination file.</p>
                </div>
            </div>
            <button type="button" onclick="closeLinkageModal()"
                class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition-all text-white text-lg font-bold">&times;</button>
        </div>

        <div class="flex-1 overflow-y-auto p-8 bg-slate-50/30">

            {{-- STEP 1: Workflow Selection --}}
            <div id="step-select-workflow" class="space-y-6">
                <div class="text-center max-w-xl mx-auto space-y-2">
                    <h3 class="text-xl font-bold text-slate-800">Select Workflow Process Type</h3>
                    <p class="text-sm text-slate-500">Choose the legacy process that was completed before the Parcel Update workflow existed.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mx-auto pt-4">
                    <div onclick="selectWorkflow('Subdivision')"
                        class="bg-white hover:bg-indigo-50/30 border border-slate-200 hover:border-indigo-500 p-6 rounded-2xl cursor-pointer shadow-sm hover:shadow-md transition-all group flex gap-4 items-start">
                        <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-all">
                            <i data-lucide="split" class="w-6 h-6"></i>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-bold text-slate-800 text-base group-hover:text-indigo-700 transition">Subdivision Linkage</h4>
                            <p class="text-xs text-slate-400">Link one legacy parent plot to its already-created child subdivided files (all children in one step).</p>
                        </div>
                    </div>

                    <div onclick="selectWorkflow('Merger')"
                        class="bg-white hover:bg-amber-50/30 border border-slate-200 hover:border-amber-500 p-6 rounded-2xl cursor-pointer shadow-sm hover:shadow-md transition-all group flex gap-4 items-start">
                        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-all">
                            <i data-lucide="git-merge" class="w-6 h-6"></i>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-bold text-slate-800 text-base group-hover:text-amber-700 transition">Merger Linkage</h4>
                            <p class="text-xs text-slate-400">Link multiple legacy plot files to one already-created merged file.</p>
                        </div>
                    </div>

                    <div onclick="selectWorkflow('Plot Extension')"
                        class="bg-white hover:bg-emerald-50/30 border border-slate-200 hover:border-emerald-500 p-6 rounded-2xl cursor-pointer shadow-sm hover:shadow-md transition-all group flex gap-4 items-start">
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-all">
                            <i data-lucide="move-diagonal" class="w-6 h-6"></i>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-bold text-slate-800 text-base group-hover:text-emerald-700 transition">Plot Extension</h4>
                            <p class="text-xs text-slate-400">Link one legacy plot file to its already-created extended file.</p>
                        </div>
                    </div>

                    <div onclick="selectWorkflow('Change of Purpose')"
                        class="bg-white hover:bg-blue-50/30 border border-slate-200 hover:border-blue-500 p-6 rounded-2xl cursor-pointer shadow-sm hover:shadow-md transition-all group flex gap-4 items-start">
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-all">
                            <i data-lucide="refresh-cw" class="w-6 h-6"></i>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-bold text-slate-800 text-base group-hover:text-blue-700 transition">Change of Purpose</h4>
                            <p class="text-xs text-slate-400">Link one legacy file to its already-created changed-purpose file.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- STEP 2: Linkage Form --}}
            <div id="step-linkage-form" class="hidden">
                <form action="{{ route('admin.manual-linkage.store') }}" method="POST" id="linkage-form">
                    @csrf
                    <input type="hidden" name="workflow_type" id="hidden_workflow_type">

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 bg-white border border-slate-200 rounded-3xl p-6 lg:p-8">

                        {{-- LEFT: Source / Old Files --}}
                        <div class="space-y-6">
                            <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                                <span class="w-7 h-7 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-sm">1</span>
                                <h3 class="font-bold text-slate-800 text-base" id="old-side-header">Legacy / Source File Details</h3>
                            </div>

                            {{-- Merger: Merged Result File selector (shown only for Merger) --}}
                            <div id="merger-result-panel" class="hidden space-y-5">
                                <p class="text-[11px] text-slate-500">Select the already-merged destination file using the global file selector. Source plot files are selected individually on each plot card on the right.</p>
                                <div class="border border-amber-200 rounded-2xl p-4 bg-amber-50/30 space-y-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex-1 min-w-0 space-y-1">
                                            <span class="px-2 py-0.5 bg-amber-600 text-white text-[9px] font-black rounded uppercase tracking-wider">Merged Result File</span>
                                            <div id="merger-result-file-no" class="text-sm font-bold text-slate-800 font-mono mt-1">NOT SELECTED</div>
                                            <input type="hidden" name="new_file_number" id="new_file_number" value="">
                                            <div id="merger-result-title" class="text-xs text-slate-500 truncate">—</div>
                                        </div>
                                        <button type="button" onclick="openMergerResultSelector()"
                                            class="flex items-center gap-2 px-3 py-2 bg-amber-600 text-white rounded-xl text-xs font-bold hover:bg-amber-700 transition shadow-sm whitespace-nowrap flex-shrink-0">
                                            <i data-lucide="search" class="w-3.5 h-3.5"></i>
                                            Select File
                                        </button>
                                    </div>
                                    <div id="merger-result-details" class="hidden border-t border-amber-200 pt-3">
                                        <table class="w-full text-xs text-slate-600">
                                            <tr><td class="text-slate-400 pr-2 pb-1 w-20">Title:</td><td id="mr-title" class="font-semibold text-slate-800 pb-1">—</td></tr>
                                            <tr><td class="text-slate-400 pr-2 pb-1">Land Use:</td><td id="mr-land" class="text-slate-700 pb-1">—</td></tr>
                                            <tr><td class="text-slate-400 pr-2 pb-1">Location:</td><td id="mr-location" class="text-slate-600 pb-1">—</td></tr>
                                            <tr><td class="text-slate-400 pr-2">Prop ID:</td><td id="mr-propid" class="font-bold text-blue-600">—</td></tr>
                                        </table>
                                        <button type="button" onclick="clearMergerResult()" class="mt-2 text-[10px] text-slate-400 hover:text-red-500 transition">✕ Clear Selection</button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Applicant Name</label>
                                    <input type="text" name="applicant_name" placeholder="e.g. Musa Ibrahim"
                                        class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                                </div>
                            </div>

                            {{-- Subdivision: Parent File selector + Mother Plot Location --}}
                            <div id="subdivision-parent-panel" class="hidden space-y-5">
                                <p class="text-[11px] text-slate-500">Select the one parent file that was subdivided. It will be decommissioned on save.</p>
                                <div class="border border-indigo-200 rounded-2xl p-4 bg-indigo-50/30 space-y-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex-1 min-w-0 space-y-1">
                                            <span class="px-2 py-0.5 bg-indigo-600 text-white text-[9px] font-black rounded uppercase tracking-wider">Parent File <span class="text-red-300">*</span></span>
                                            <div id="sub-parent-file-no" class="text-sm font-bold text-slate-800 font-mono mt-1">NOT SELECTED</div>
                                            <input type="hidden" name="old_file_numbers[]" id="sub-parent-hidden" value="">
                                        </div>
                                        <button type="button" onclick="openSubdivisionParentSelector()"
                                            class="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition shadow-sm whitespace-nowrap flex-shrink-0">
                                            <i data-lucide="search" class="w-3.5 h-3.5"></i>
                                            Select File
                                        </button>
                                    </div>
                                    <div id="sub-parent-details" class="hidden border-t border-indigo-200 pt-3">
                                        <table class="w-full text-xs text-slate-600">
                                            <tr><td class="text-slate-400 pr-2 pb-1 w-20">Title:</td><td id="sub-parent-title" class="font-semibold text-slate-800 pb-1">—</td></tr>
                                            <tr><td class="text-slate-400 pr-2 pb-1">Land Use:</td><td id="sub-parent-land" class="text-slate-700 pb-1">—</td></tr>
                                            <tr><td class="text-slate-400 pr-2 pb-1">Location:</td><td id="sub-parent-location" class="text-slate-600 pb-1">—</td></tr>
                                            <tr><td class="text-slate-400 pr-2">Prop ID:</td><td id="sub-parent-propid" class="font-bold text-blue-600">—</td></tr>
                                        </table>
                                        <button type="button" onclick="clearSubdivisionParent()" class="mt-2 text-[10px] text-slate-400 hover:text-red-500 transition">✕ Clear</button>
                                    </div>
                                    <p class="text-[11px] text-amber-700 bg-amber-50 rounded px-2 py-1 border border-amber-200">
                                        If you cannot find the file, it has not been indexed yet — index it first.
                                    </p>
                                </div>

                                {{-- Mother Plot Location Details --}}
                                <div class="border border-slate-200 rounded-2xl p-4 bg-slate-50/40 space-y-4">
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-600">Mother Plot Location Details</span>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Plot No</label>
                                            <input type="text" name="plot_number" id="plot_number" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm" placeholder="e.g. 123">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">House No</label>
                                            <input type="text" name="house_no" id="house_no" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm" placeholder="e.g. 322">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Street Name</label>
                                            <select name="street_name" id="street_name" onchange="updateManualLocationPreview()" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
                                                <option value="">SELECT STREET</option>
                                                @foreach($streetNames as $street)
                                                    <option value="{{ $street->name }}">{{ strtoupper($street->name) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">District</label>
                                            <select name="district" id="district" onchange="updateManualLocationPreview()" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
                                                <option value="">SELECT DISTRICT</option>
                                                @foreach($districts as $district)
                                                    <option value="{{ $district->name }}">{{ strtoupper($district->name) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">LGA</label>
                                            <select name="lga" id="lga" onchange="updateManualLocationPreview()" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
                                                <option value="">SELECT LGA</option>
                                                @foreach($lgas as $lga)
                                                    <option value="{{ $lga->name }}">{{ strtoupper($lga->name) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">State</label>
                                            <select name="state" id="state" onchange="updateManualLocationPreview()" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">@foreach($states as $st)<option value="{{ $st->StateName }}" @selected($st->StateName == 'Kano')>{{ strtoupper($st->StateName) }}</option>@endforeach</select>
                                        </div>
                                        <div class="md:col-span-2">
                                            <div class="bg-white p-3 rounded-lg border border-dashed border-slate-200">
                                                <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">Location Preview</p>
                                                <p id="manual-location-preview" class="text-xs font-bold text-slate-600 italic">No location details entered yet.</p>
                                                <input type="hidden" name="location" id="location">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Source verify panel (all non-Merger workflows) --}}
                            <div id="source-verify-panel" class="space-y-6">
                                <div class="space-y-4">
                                    <label class="block text-sm font-semibold text-slate-700" id="old-input-label">Add Old File Number</label>
                                    <div class="flex gap-2">
                                        <div class="relative flex-1">
                                            <i data-lucide="file-text" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                            <input type="text" id="old-file-input" placeholder="e.g. RES-2000-158"
                                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                        </div>
                                        <button type="button" id="btn-verify-old"
                                            class="px-5 py-3 bg-slate-800 text-white rounded-xl text-sm font-semibold hover:bg-slate-700 transition whitespace-nowrap">
                                            Verify &amp; Add
                                        </button>
                                    </div>
                                    <p class="text-[11px] text-slate-400" id="old-help-text">Type the file number and click verify.</p>
                                </div>

                                <div id="verify-loader" class="hidden py-4 flex justify-center">
                                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                                </div>

                                {{-- Verify result card --}}
                                <div id="verify-card" class="hidden p-5 bg-slate-50 border border-slate-200 rounded-2xl transition-all badge-trans">
                                    <div class="flex justify-between items-start mb-3 gap-2 flex-wrap">
                                        <span class="text-xs font-semibold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded">Active Record Verified</span>
                                        <span id="v-already-linked-badge" class="hidden text-xs font-semibold bg-orange-100 text-orange-700 px-2 py-0.5 rounded border border-orange-200"></span>
                                    </div>
                                    <table class="w-full text-xs text-slate-600">
                                        <tr><td class="font-medium text-slate-400 pb-1 w-24">File Title:</td><td class="font-semibold text-slate-800 pb-1" id="v-title">-</td></tr>
                                        <tr><td class="font-medium text-slate-400 pb-1">Land Use:</td><td class="font-semibold text-slate-800 pb-1" id="v-land">-</td></tr>
                                        <tr><td class="font-medium text-slate-400 pb-1">LGA/Location:</td><td class="font-semibold text-slate-800 pb-1" id="v-location">-</td></tr>
                                        <tr><td class="font-medium text-slate-400 pb-1">Property ID:</td><td class="font-semibold text-blue-600 pb-1" id="v-propid">-</td></tr>
                                        <tr><td class="font-medium text-slate-400">Transactions:</td><td id="v-tx">-</td></tr>
                                    </table>
                                </div>

                                <div class="space-y-3">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400" id="list-label">Selected Files to Decommission</label>
                                    <div id="selected-files-list" class="flex flex-wrap gap-2 py-2">
                                        <span class="text-xs text-slate-400 italic">No legacy files added yet.</span>
                                    </div>
                                    <div id="hidden-inputs-container"></div>
                                </div>
                            </div>
                        </div>

                        {{-- RIGHT: Destination / New File Details --}}
                        <div class="space-y-6">
                            <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                                <span id="new-side-badge" class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm">2</span>
                                <h3 class="font-bold text-slate-800 text-base" id="new-side-header">Processed Destination File Details</h3>
                            </div>
                            <div id="dynamic-form-fields" class="space-y-5">
                                {{-- Injected by renderTailoredForm() --}}
                            </div>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <button type="button" onclick="goBackToStep1()"
                            class="px-6 py-3.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-sm font-bold transition flex items-center gap-2 w-full sm:w-auto justify-center">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
                        </button>
                        <button type="submit" id="btn-submit-linkage"
                            class="px-8 py-3.5 bg-blue-600 text-white rounded-xl text-sm font-bold shadow-md hover:bg-blue-700 transition flex items-center gap-2 w-full sm:w-auto justify-center">
                            <i data-lucide="check-square" class="w-4 h-4"></i> Backfill Linkage and Decommission
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@include('components.global-fileno-modal')

@section('footer-scripts')
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script>
// ─── Temp File No Auto-Generation ────────────────────────────────────────────
const TEMP_FILENO_URL = '{{ route("instruments.getNextTempFileNo") }}';

function fetchAndSetTempFileNo() {
    const input = document.getElementById('temp_file_no');
    if (!input) return;
    input.value = 'Generating...';

    fetch(TEMP_FILENO_URL)
        .then(r => r.json())
        .then(data => { input.value = data.temp_fileno || ''; })
        .catch(() => { input.value = ''; });
}

// ─── Global State ───────────────────────────────────────────────────────────
let selectedWorkflow = null;
let selectedOldFiles = [];
let selectedOldFileDetails = [];
let firstFileDetails = null;

// Subdivision multi-child state
let subdivisionChildren = [];
let subdivisionChildCounter = 0;

// Merger destination file state
let mergedFileData    = null;
let mergerResultFile  = null;

// Pre-build select option strings once (used by manualLocationBlock / merger cards)
const manualStreetOptions   = `<option value="">SELECT STREET</option>@foreach($streetNames as $street)<option value="{{ $street->name }}">{{ strtoupper($street->name) }}</option>@endforeach`;
const manualDistrictOptions = `<option value="">SELECT DISTRICT</option>@foreach($districts as $district)<option value="{{ $district->name }}">{{ strtoupper($district->name) }}</option>@endforeach`;
const manualLgaOptions      = `<option value="">SELECT LGA</option>@foreach($lgas as $lga)<option value="{{ $lga->name }}">{{ strtoupper($lga->name) }}</option>@endforeach`;
const manualStateOptions    = `@foreach($states as $state)<option value="{{ $state->StateName }}" @selected($state->StateName == 'Kano')>{{ strtoupper($state->StateName) }}</option>@endforeach`;

// ─── Modal Open / Close ──────────────────────────────────────────────────────
function openLinkageModal() {
    const modal   = document.getElementById('linkage-modal');
    const content = document.getElementById('modal-content');
    modal.classList.remove('hidden');
    setTimeout(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
    }, 50);
    goBackToStep1();
}

function closeLinkageModal() {
    const modal   = document.getElementById('linkage-modal');
    const content = document.getElementById('modal-content');
    content.classList.remove('scale-100', 'opacity-100');
    content.classList.add('scale-95', 'opacity-0');
    setTimeout(() => modal.classList.add('hidden'), 200);
}

// ─── Step Navigation ─────────────────────────────────────────────────────────
function goBackToStep1() {
    document.getElementById('step-select-workflow').classList.remove('hidden');
    document.getElementById('step-linkage-form').classList.add('hidden');
    selectedWorkflow       = null;
    selectedOldFiles       = [];
    selectedOldFileDetails = [];
    firstFileDetails       = null;
    subdivisionChildren    = [];
    subdivisionChildCounter = 0;
    mergedFileData         = null;
    mergerResultFile       = null;
    const subParentHidden = document.getElementById('sub-parent-hidden');
    if (subParentHidden) subParentHidden.value = '';
    const subParentFileNo = document.getElementById('sub-parent-file-no');
    if (subParentFileNo) subParentFileNo.textContent = 'NOT SELECTED';
    const subParentDetails = document.getElementById('sub-parent-details');
    if (subParentDetails) subParentDetails.classList.add('hidden');
    const mrFileNo = document.getElementById('merger-result-file-no');
    if (mrFileNo) mrFileNo.textContent = 'NOT SELECTED';
    const mrTitle = document.getElementById('merger-result-title');
    if (mrTitle)  mrTitle.textContent = '—';
    const mrDetails = document.getElementById('merger-result-details');
    if (mrDetails) mrDetails.classList.add('hidden');
    const mrHidden = document.getElementById('new_file_number');
    if (mrHidden)  mrHidden.value = '';
    renderBadges();
    const form = document.getElementById('linkage-form');
    if (form) form.reset();
}

function selectWorkflow(workflow) {
    selectedWorkflow = workflow;
    document.getElementById('hidden_workflow_type').value = workflow;
    document.getElementById('step-select-workflow').classList.add('hidden');
    document.getElementById('step-linkage-form').classList.remove('hidden');
    tailorLeftForm();
    renderTailoredForm();
    lucide.createIcons();
}

// ─── Tailor Left Side Labels ─────────────────────────────────────────────────
function tailorLeftForm() {
    const texts = {
        Subdivision:          { header: 'Subdivision Parent File',  label: 'Enter Parent File Number',          help: 'Verify the one parent file to be split. (Exactly 1 required).',         list: 'Parent File to Decommission' },
        Merger:               { header: 'Merged Result File',       label: '',                                  help: '',                                                                       list: '' },
        'Plot Extension':     { header: 'Legacy Plot File',         label: 'Enter Legacy Plot File Number',     help: 'Verify the original plot file. (Exactly 1 required).',                  list: 'Plot File to Decommission'   },
        'Change of Purpose':  { header: 'Legacy Source File',       label: 'Enter Legacy File Number',          help: 'Verify the file to re-purpose. (Exactly 1 required).',                 list: 'Legacy File to Re-Purpose'   },
    };
    const t = texts[selectedWorkflow] || {};
    document.getElementById('old-side-header').textContent = t.header || 'Source File';

    const isMerger       = selectedWorkflow === 'Merger';
    const isSubdivision  = selectedWorkflow === 'Subdivision';
    const sourcePanel    = document.getElementById('source-verify-panel');
    const mergerPanel    = document.getElementById('merger-result-panel');
    const subPanel       = document.getElementById('subdivision-parent-panel');
    if (sourcePanel) sourcePanel.classList.toggle('hidden', isMerger || isSubdivision);
    if (mergerPanel) mergerPanel.classList.toggle('hidden', !isMerger);
    if (subPanel)    subPanel.classList.toggle('hidden', !isSubdivision);

    if (!isMerger) {
        document.getElementById('old-input-label').textContent = t.label || 'Add File Number';
        document.getElementById('old-help-text').textContent   = t.help  || '';
        document.getElementById('list-label').textContent      = t.list  || 'Selected Files';
    }

    // Right-side header
    const newHeader = document.getElementById('new-side-header');
    const newBadge  = document.getElementById('new-side-badge');
    if (newHeader) newHeader.textContent = isMerger ? 'Source Plot Details' : 'Processed Destination File Details';
    if (newBadge) {
        newBadge.className = `w-7 h-7 rounded-full flex items-center justify-center font-bold text-sm ${isMerger ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700'}`;
    }
}

// ─── Shared Helpers for Form HTML ────────────────────────────────────────────
function approvalFieldsHTML() {
    return `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border border-slate-200 rounded-2xl p-4 bg-slate-50/40">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Approval / Ref No.</label>
                <input type="text" name="approval_reference" placeholder="e.g. KAG/MLS/2019/234"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Approval Date</label>
                <input type="date" name="approval_date"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
            </div>
        </div>
    `;
}

function manualLocationBlock(title) {
    return `
        <div class="border border-slate-200 rounded-2xl p-4 bg-slate-50/40 space-y-4">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-600">${title}</span>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Plot No</label>
                    <input type="text" name="plot_number" id="plot_number" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm" placeholder="e.g. 123">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">House No</label>
                    <input type="text" name="house_no" id="house_no" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm" placeholder="e.g. 322">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Street Name</label>
                    <select name="street_name" id="street_name" onchange="updateManualLocationPreview()" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">${manualStreetOptions}</select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">District</label>
                    <select name="district" id="district" onchange="updateManualLocationPreview()" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">${manualDistrictOptions}</select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">LGA</label>
                    <select name="lga" id="lga" onchange="updateManualLocationPreview()" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">${manualLgaOptions}</select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">State</label>
                    <select name="state" id="state" onchange="updateManualLocationPreview()" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">${manualStateOptions}</select>
                </div>
                <div class="md:col-span-3">
                    <div class="bg-white p-3 rounded-lg border border-dashed border-slate-200">
                        <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">Location Preview</p>
                        <p id="manual-location-preview" class="text-xs font-bold text-slate-600 italic">No location details entered yet.</p>
                        <input type="hidden" name="location" id="location">
                    </div>
                </div>
            </div>
        </div>
    `;
}

function updateManualLocationPreview() {
    const parts = [
        document.getElementById('plot_number')?.value ? 'Plot ' + document.getElementById('plot_number').value.trim() : '',
        document.getElementById('house_no')?.value    ? 'House ' + document.getElementById('house_no').value.trim()   : '',
        document.getElementById('street_name')?.value || '',
        document.getElementById('district')?.value    || '',
        document.getElementById('lga')?.value         || '',
        document.getElementById('state')?.value       || '',
    ].filter(Boolean);
    const loc = parts.join(', ');
    const preview = document.getElementById('manual-location-preview');
    const input   = document.getElementById('location');
    if (preview) preview.textContent = loc || 'No location details entered yet.';
    if (input)   input.value = loc;
}

// ─── SUBDIVISION: Children Table ─────────────────────────────────────────────
function addSubdivisionChild() {
    subdivisionChildCounter++;
    subdivisionChildren.push({
        index:           subdivisionChildCounter,
        new_file_number: '',
        file_title:      '',
        plot_number:     '',
        plot_size:       '',
        survey_plan_no:  '',
    });
    renderSubdivisionChildren();
}

function removeSubdivisionChild(index) {
    subdivisionChildren = subdivisionChildren.filter(c => c.index !== index);
    renderSubdivisionChildren();
}

function renderSubdivisionChildren() {
    const container = document.getElementById('subdivision-children-list');
    const empty     = document.getElementById('sub-children-empty');
    if (!container) return;

    if (subdivisionChildren.length === 0) {
        container.innerHTML = '';
        if (empty) empty.classList.remove('hidden');
        return;
    }
    if (empty) empty.classList.add('hidden');

    container.innerHTML = subdivisionChildren.map((child, i) => `
        <div class="bg-white border border-slate-200 rounded-xl p-3 child-row-enter space-y-2">
            <div class="flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold flex items-center justify-center flex-shrink-0">${i + 1}</span>
                <div class="flex-1 flex items-center gap-2 min-w-0">
                    <div class="flex-1 flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 min-w-0">
                        <i data-lucide="file-text" class="w-3.5 h-3.5 text-slate-400 flex-shrink-0"></i>
                        <div class="min-w-0">
                            <span id="sub-child-fileno-display-${child.index}" class="text-xs font-mono font-bold text-slate-800 block">${child.new_file_number || 'NOT SELECTED'}</span>
                            <span id="sub-child-title-display-${child.index}" class="text-[10px] text-slate-400 block truncate">${child.file_title || 'Click Select to choose child file'}</span>
                        </div>
                        <input type="hidden" name="children[${child.index}][new_file_number]" value="${child.new_file_number || ''}">
                    </div>
                    <button type="button" onclick="openSubdivisionChildSelector(${child.index})"
                        class="flex items-center gap-1.5 px-3 py-2 bg-indigo-600 text-white rounded-lg text-xs font-bold hover:bg-indigo-700 transition whitespace-nowrap flex-shrink-0">
                        <i data-lucide="search" class="w-3 h-3"></i> Select
                    </button>
                </div>
                <button type="button" onclick="removeSubdivisionChild(${child.index})"
                    class="w-7 h-7 rounded-full bg-red-50 hover:bg-red-100 text-red-500 flex items-center justify-center text-base font-bold flex-shrink-0 transition">&times;</button>
            </div>
            <div class="pl-8">
                <div class="w-40">
                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Plot No</label>
                    <input type="text" name="children[${child.index}][plot_number]"
                           value="${child.plot_number}" placeholder="e.g. 45A"
                           class="w-full px-2.5 py-2 rounded-lg border border-slate-200 text-xs">
                </div>
            </div>
        </div>
    `).join('');
}

// ─── MERGER: Helpers ─────────────────────────────────────────────────────────
function openFileSelectorForMergerCard(index) {
    GlobalFileNoModal.open({
        callback: function(data) {
            backfillMergerCardFromModal(index, data);
        }
    });
}

function backfillMergerCardFromModal(index, data) {
    if (!data.record) {
        Swal.fire({
            icon: 'error',
            title: 'File Not Indexed',
            html: `<strong>${data.fileNumber}</strong> was not found in the File Indexing system.<br><br>
                   The source file must be <strong>indexed first</strong> before it can be used here.`,
        });
        return;
    }
    const card = document.getElementById(`manual_merger_location_card_${index}`);
    if (!card) return;

    const fileNoInput = card.querySelector(`input[name="location_details[${index}][source_file_no]"]`);
    if (fileNoInput) fileNoInput.value = data.fileNumber;

    const fileNoDisplay = card.querySelector('.source-file-display');
    if (fileNoDisplay) fileNoDisplay.innerText = data.fileNumber;

    const record = data.record || {};
    const title  = (record.file_name || '').toUpperCase();

    const fileTitleDisplay = card.querySelector('.source-title-display');
    if (fileTitleDisplay) { fileTitleDisplay.innerText = title || '—'; fileTitleDisplay.title = title; }

    const fileTitleInput = card.querySelector(`input[name="location_details[${index}][source_file_title]"]`);
    if (fileTitleInput) fileTitleInput.value = title;

    if (record.plot_no)    { const el = card.querySelector(`input[name*="[plot_no]"]`);   if (el) el.value = record.plot_no; }
    if (record.house_no)   { const el = card.querySelector(`input[name*="[house_no]"]`);  if (el) el.value = record.house_no; }
    if (record.street_name) setManualSelectValue(card.querySelector(`select[name*="[street_name]"]`), record.street_name);
    if (record.district)    setManualSelectValue(card.querySelector(`select[name*="[district]"]`),    record.district);
    if (record.lga)         setManualSelectValue(card.querySelector(`select[name*="[lga]"]`),         record.lga);
    if (record.state)       setManualSelectValue(card.querySelector(`select[name*="[state]"]`),       record.state);

    updateManualMergerLocationPreview(index);
    if (window.lucide) window.lucide.createIcons();
}

function manualMergerLocationCardHTML(index, fileData) {
    const fileNo    = fileData?.file_number || '';
    const fileTitle = fileData?.file_title  || '';
    return `
        <div id="manual_merger_location_card_${index}" class="manual-merger-location-card border border-slate-200 rounded-2xl bg-slate-50/50 p-4 space-y-4 ${index === 1 ? '' : 'hidden'}">
            <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-100">
                <div class="flex-1 flex items-center gap-6">
                    <div class="flex flex-col gap-1">
                        <span class="px-2 py-0.5 w-fit bg-slate-600 text-white text-[9px] font-black rounded uppercase tracking-wider">Source File</span>
                        <span class="source-file-display text-sm font-bold text-slate-800 font-mono">${fileNo || 'NOT SELECTED'}</span>
                        <input type="hidden" name="location_details[${index}][source_file_no]" value="${fileNo}">
                    </div>
                    <div class="flex-1 min-w-0">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-tight mb-0.5">FILE TITLE</label>
                        <span class="source-title-display text-xs font-bold text-slate-600 block truncate" title="${fileTitle}">${fileTitle || '—'}</span>
                        <input type="hidden" name="location_details[${index}][source_file_title]" value="${fileTitle}">
                    </div>
                </div>
                <button type="button" onclick="openFileSelectorForMergerCard(${index})" class="flex items-center gap-2 px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg border border-blue-100 hover:bg-blue-100 transition text-xs font-bold shadow-sm whitespace-nowrap">
                    <i data-lucide="search" class="w-3.5 h-3.5"></i>
                    Select File
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Plot No <span class="text-red-500">*</span></label>
                    <input type="text" name="location_details[${index}][plot_no]"
                           value="${fileData?.plot_number && fileData.plot_number !== 'N/A' ? fileData.plot_number : ''}"
                           oninput="updateManualMergerLocationPreview(${index})"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">House No</label>
                    <input type="text" name="location_details[${index}][house_no]" oninput="updateManualMergerLocationPreview(${index})" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Street Name</label>
                    <select name="location_details[${index}][street_name]" onchange="updateManualMergerLocationPreview(${index})" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">${manualStreetOptions}</select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">District</label>
                    <select name="location_details[${index}][district]" onchange="updateManualMergerLocationPreview(${index})" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">${manualDistrictOptions}</select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">LGA</label>
                    <select name="location_details[${index}][lga]" onchange="updateManualMergerLocationPreview(${index})" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">${manualLgaOptions}</select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">State</label>
                    <select name="location_details[${index}][state]" onchange="updateManualMergerLocationPreview(${index})" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">${manualStateOptions}</select>
                </div>
                <div class="md:col-span-3">
                    <div class="bg-white p-4 rounded-xl border border-dashed border-slate-300">
                        <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Full Property Location Preview</p>
                        <p id="manual_merger_location_preview_${index}" class="text-sm font-bold text-slate-700 italic">No location details entered yet.</p>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function generateManualMergerSources() {
    const count            = parseInt(document.getElementById('num_plots')?.value || '0') || 0;
    const sourceContainer  = document.getElementById('manual_merger_sources_container');
    const locationContainer = document.getElementById('manual_merger_location_cards_container');
    const totalLabel       = document.getElementById('manual_total_plots_label');
    const currentLabel     = document.getElementById('manual_current_plot_label');
    if (!sourceContainer || !locationContainer) return;

    sourceContainer.innerHTML  = '';
    locationContainer.innerHTML = '';
    if (totalLabel)   totalLabel.textContent   = String(count);
    if (currentLabel) currentLabel.textContent = count > 0 ? '1' : '0';

    if (count < 1) {
        sourceContainer.innerHTML   = '<p class="col-span-full text-slate-400 text-xs italic">Set number of plots to define source sizes.</p>';
        locationContainer.innerHTML = '<div class="p-8 text-center text-slate-400 italic text-xs border border-dashed border-slate-200 rounded-xl">Define number of plots to set location details.</div>';
        return;
    }

    for (let i = 1; i <= count; i++) {
        const sizeDiv = document.createElement('div');
        sizeDiv.innerHTML = `
            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Plot ${i} Size</label>
            <input type="number" name="plot_sizes[]" class="manual-merger-source-input w-full px-3 py-2 rounded-lg border border-slate-200 text-sm" step="0.01" placeholder="0.00" oninput="calculateManualMergerTotal()">
        `;
        sourceContainer.appendChild(sizeDiv);

        const wrapper = document.createElement('div');
        wrapper.innerHTML = manualMergerLocationCardHTML(i, selectedOldFileDetails[i - 1] || null);
        locationContainer.appendChild(wrapper.firstElementChild);
        if (selectedOldFileDetails[i - 1]) {
            backfillManualMergerCard(i, selectedOldFileDetails[i - 1]);
        }
    }
    if (window.lucide) window.lucide.createIcons();
}

function navManualMergerLocation(direction) {
    const count = parseInt(document.getElementById('num_plots')?.value || '0') || 0;
    if (count < 1) return;
    const currentLabel = document.getElementById('manual_current_plot_label');
    let current = parseInt(currentLabel?.textContent || '1') || 1;
    const next  = Math.max(1, Math.min(count, current + direction));
    document.querySelectorAll('.manual-merger-location-card').forEach(card => card.classList.add('hidden'));
    document.getElementById(`manual_merger_location_card_${next}`)?.classList.remove('hidden');
    if (currentLabel) currentLabel.textContent = String(next);
}

function calculateManualMergerTotal() {
    let total = 0;
    document.querySelectorAll('.manual-merger-source-input').forEach(input => {
        const v = parseFloat(input.value);
        if (!isNaN(v)) total += v;
    });
    const totalInput = document.getElementById('plot_size');
    if (totalInput) totalInput.value = total > 0 ? `${total.toFixed(2)} Sqm` : '';
}

function backfillManualMergerCard(index, fileData) {
    const card = document.getElementById(`manual_merger_location_card_${index}`);
    if (!card || !fileData) return;
    card.querySelector('.source-file-display').textContent  = fileData.file_number || 'NOT SELECTED';
    card.querySelector('.source-title-display').textContent = fileData.file_title  || '-';
    card.querySelector('.source-title-display').title       = fileData.file_title  || '';
    const sourceInput = card.querySelector(`input[name="location_details[${index}][source_file_no]"]`);
    const titleInput  = card.querySelector(`input[name="location_details[${index}][source_file_title]"]`);
    if (sourceInput) sourceInput.value = fileData.file_number || '';
    if (titleInput)  titleInput.value  = fileData.file_title  || '';
    const plotInput = card.querySelector(`input[name="location_details[${index}][plot_no]"]`);
    if (plotInput && fileData.plot_number && fileData.plot_number !== 'N/A') plotInput.value = fileData.plot_number;
    setManualSelectValue(card.querySelector(`select[name="location_details[${index}][district]"]`), fileData.district);
    setManualSelectValue(card.querySelector(`select[name="location_details[${index}][lga]"]`),      fileData.lga);
    updateManualMergerLocationPreview(index);
}

function setManualSelectValue(select, value) {
    if (!select || !value || value === 'N/A') return;
    const n = String(value).trim().toUpperCase();
    Array.from(select.options).some(o => {
        if (String(o.value).trim().toUpperCase() === n || String(o.text).trim().toUpperCase() === n) {
            select.value = o.value;
            return true;
        }
    });
}

function updateManualMergerLocationPreview(index) {
    const card = document.getElementById(`manual_merger_location_card_${index}`);
    if (!card) return;
    const plot     = card.querySelector(`input[name="location_details[${index}][plot_no]"]`)?.value   || '';
    const house    = card.querySelector(`input[name="location_details[${index}][house_no]"]`)?.value  || '';
    const street   = card.querySelector(`select[name="location_details[${index}][street_name]"]`)?.value || '';
    const district = card.querySelector(`select[name="location_details[${index}][district]"]`)?.value || '';
    const lga      = card.querySelector(`select[name="location_details[${index}][lga]"]`)?.value      || '';
    const state    = card.querySelector(`select[name="location_details[${index}][state]"]`)?.value    || '';
    const parts    = [];
    if (plot)     parts.push(`Plot ${plot}`);
    if (house)    parts.push(`House No. ${house}`);
    if (street)   parts.push(street);
    if (district) parts.push(district);
    if (lga)      parts.push(lga);
    if (state)    parts.push(state);
    const preview = document.getElementById(`manual_merger_location_preview_${index}`);
    if (preview) preview.textContent = parts.length ? parts.join(', ') : 'No location details entered yet.';
    if (index === 1) {
        const locInput = document.getElementById('location');
        if (locInput) locInput.value = parts.join(', ');
    }
}

// ─── MERGER: Destination File Search ─────────────────────────────────────────
function searchMergedFile() {
    const input  = document.getElementById('merger-new-file-input');
    const loader = document.getElementById('merger-new-file-loader');
    const card   = document.getElementById('merger-new-file-card');
    const hidden = document.getElementById('new_file_number');
    if (!input) return;

    const rawVal = input.value.trim();
    if (!rawVal) {
        Swal.fire({ icon: 'warning', title: 'Input Required', text: 'Please enter the merged result file number.' });
        return;
    }
    const fileNo = rawVal.toUpperCase();
    input.value  = fileNo;

    if (loader) loader.classList.remove('hidden');
    if (card)   card.classList.add('hidden');

    fetch(`{{ route('admin.manual-linkage.search-old-file') }}?file_number=${encodeURIComponent(fileNo)}`)
        .then(r => r.json())
        .then(data => {
            if (loader) loader.classList.add('hidden');

            if (data.error) {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error });
                return;
            }

            // Set the real hidden field regardless of whether the file exists yet
            if (hidden) hidden.value = fileNo;
            mergedFileData = data.exists ? data : { file_number: fileNo, file_title: '(New — will be created)', land_use: '—', district: null, lga: null, location: null, prop_id: null };

            // Render summary card
            if (card) {
                const isNew = !data.exists;
                card.innerHTML = `
                    <div class="flex items-start justify-between gap-2 flex-wrap">
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded ${isNew ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700'}">
                            ${isNew ? 'New File — Will Be Created' : 'Existing File Found'}
                        </span>
                        <button type="button" onclick="clearMergedFile()" class="text-[10px] text-slate-400 hover:text-red-500 transition">✕ Clear</button>
                    </div>
                    <table class="w-full text-xs text-slate-600 mt-2">
                        <tr><td class="text-slate-400 pr-2 pb-1 w-20">File No:</td><td class="font-mono font-bold text-slate-900 pb-1">${fileNo}</td></tr>
                        ${!isNew ? `
                        <tr><td class="text-slate-400 pr-2 pb-1">Title:</td><td class="font-semibold text-slate-800 pb-1">${data.file_title}</td></tr>
                        <tr><td class="text-slate-400 pr-2 pb-1">Land Use:</td><td class="text-slate-700 pb-1">${data.land_use}</td></tr>
                        <tr><td class="text-slate-400 pr-2">Prop ID:</td><td class="font-bold text-blue-600">${data.prop_id || '—'}</td></tr>
                        ` : '<tr><td colspan="2" class="text-slate-400 italic text-[11px]">File will be registered during linkage process.</td></tr>'}
                    </table>
                `;
                card.classList.remove('hidden');
            }
        })
        .catch(() => {
            if (loader) loader.classList.add('hidden');
            Swal.fire({ icon: 'error', title: 'Request Failed', text: 'Unable to reach the verification server.' });
        });
}

function clearMergedFile() {
    mergedFileData = null;
    const input  = document.getElementById('merger-new-file-input');
    const card   = document.getElementById('merger-new-file-card');
    const hidden = document.getElementById('new_file_number');
    if (input)  input.value  = '';
    if (hidden) hidden.value = '';
    if (card)   { card.innerHTML = ''; card.classList.add('hidden'); }
}

// ─── MERGER: Result File via GlobalFileNoModal ────────────────────────────────
function openMergerResultSelector() {
    GlobalFileNoModal.open({
        callback: function(data) { backfillMergerResult(data); }
    });
}

function backfillMergerResult(data) {
    if (!data.record) {
        Swal.fire({
            icon: 'error',
            title: 'File Not Indexed',
            html: `<strong>${data.fileNumber}</strong> was not found in the File Indexing system.<br><br>
                   The merged result file must be <strong>indexed first</strong> before it can be linked here.`,
        });
        return;
    }
    mergerResultFile = data;
    const fileNo = data.fileNumber || '';
    const record = data.record    || {};
    const title  = (record.file_name || '').toUpperCase();

    const hidden = document.getElementById('new_file_number');
    if (hidden) hidden.value = fileNo;
    const fileNoEl = document.getElementById('merger-result-file-no');
    if (fileNoEl) fileNoEl.textContent = fileNo || 'NOT SELECTED';
    const titleEl = document.getElementById('merger-result-title');
    if (titleEl) titleEl.textContent = title || '—';

    document.getElementById('mr-title').textContent    = title || '—';
    document.getElementById('mr-land').textContent     = record.land_use || '—';
    document.getElementById('mr-location').textContent = [record.street_name, record.district, record.lga, record.state].filter(Boolean).join(', ') || '—';
    document.getElementById('mr-propid').textContent   = record.prop_id || '—';

    const applicantInput = document.querySelector('input[name="applicant_name"]');
    if (applicantInput) applicantInput.value = title;

    const details = document.getElementById('merger-result-details');
    if (details) details.classList.remove('hidden');
    if (window.lucide) window.lucide.createIcons();
}

function clearMergerResult() {
    mergerResultFile = null;
    const hidden = document.getElementById('new_file_number');
    if (hidden) hidden.value = '';
    const fileNoEl = document.getElementById('merger-result-file-no');
    if (fileNoEl) fileNoEl.textContent = 'NOT SELECTED';
    const titleEl = document.getElementById('merger-result-title');
    if (titleEl) titleEl.textContent = '—';
    const details = document.getElementById('merger-result-details');
    if (details) details.classList.add('hidden');
}

function calculateManualSubdivisionFee() {
    const value   = parseFloat(document.getElementById('manual_sub_land_value')?.value || '0') || 0;
    const fee     = value * 0.0025;
    const display = document.getElementById('manual_sub_fee_display');
    const hidden  = document.getElementById('manual_sub_fee_hidden');
    if (display) display.value = fee.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (hidden)  hidden.value  = fee.toFixed(2);
}

function calculateManualCopFee() {
    const size    = parseFloat(document.getElementById('manual_cop_land_size')?.value || '0') || 0;
    const fee     = size * 50;
    const display = document.getElementById('manual_cop_fee_display');
    const hidden  = document.getElementById('manual_cop_fee_hidden');
    if (display) display.value = fee.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (hidden)  hidden.value  = fee.toFixed(2);
}

// ─── Subdivision Parent File Selector ────────────────────────────────────────
function openSubdivisionParentSelector() {
    GlobalFileNoModal.open({
        callback: function(data) {
            if (!data.record) {
                Swal.fire({
                    icon: 'error',
                    title: 'File Not Indexed',
                    html: `<strong>${data.fileNumber}</strong> was not found in the File Indexing system.<br><br>
                           The parent file must be <strong>indexed first</strong> before it can be used here.`,
                });
                return;
            }
            backfillSubdivisionParent(data);
        }
    });
}

function backfillSubdivisionParent(data) {
    const fileNo  = data.fileNumber || '';
    const record  = data.record    || {};
    const title   = (record.file_name || record.file_title || '').toUpperCase();
    const landUse = record.land_use  || '—';
    const loc     = [record.street_name, record.district, record.lga, record.state].filter(Boolean).join(', ') || '—';
    const propId  = record.prop_id   || '—';

    const hidden = document.getElementById('sub-parent-hidden');
    if (hidden) hidden.value = fileNo;

    const fileNoEl = document.getElementById('sub-parent-file-no');
    if (fileNoEl) fileNoEl.textContent = fileNo;

    document.getElementById('sub-parent-title').textContent    = title;
    document.getElementById('sub-parent-land').textContent     = landUse;
    document.getElementById('sub-parent-location').textContent = loc;
    document.getElementById('sub-parent-propid').textContent   = propId;

    const detailsEl = document.getElementById('sub-parent-details');
    if (detailsEl) detailsEl.classList.remove('hidden');

    // Auto-fill mother plot location fields from indexed record
    const plotEl = document.getElementById('plot_number');
    if (plotEl && !plotEl.value && record.plot_no && record.plot_no !== 'N/A') plotEl.value = record.plot_no;
    if (record.street_name) setManualSelectValue(document.getElementById('street_name'), record.street_name);
    if (record.district)    setManualSelectValue(document.getElementById('district'),    record.district);
    if (record.lga)         setManualSelectValue(document.getElementById('lga'),         record.lga);
    if (record.state)       setManualSelectValue(document.getElementById('state'),       record.state);
    updateManualLocationPreview();

    if (window.lucide) window.lucide.createIcons();
}

function clearSubdivisionParent() {
    const hidden = document.getElementById('sub-parent-hidden');
    if (hidden) hidden.value = '';
    const fileNoEl = document.getElementById('sub-parent-file-no');
    if (fileNoEl) fileNoEl.textContent = 'NOT SELECTED';
    const detailsEl = document.getElementById('sub-parent-details');
    if (detailsEl) detailsEl.classList.add('hidden');
}

// ─── Destination File Selector (Plot Extension & Change of Purpose) ──────────
function openDestinationFileSelector(workflow) {
    GlobalFileNoModal.open({
        callback: function(data) {
            if (!data.record) {
                Swal.fire({
                    icon: 'error',
                    title: 'File Not Indexed',
                    html: `<strong>${data.fileNumber}</strong> was not found in the File Indexing system.<br><br>
                           The file must be <strong>indexed first</strong> before it can be linked here.`,
                });
                return;
            }
            backfillDestinationFile(workflow, data);
        }
    });
}

function backfillDestinationFile(workflow, data) {
    const fileNo  = data.fileNumber || '';
    const record  = data.record    || {};
    const title   = (record.file_name || record.file_title || '').toUpperCase();
    const landUse = record.land_use  || '—';
    const loc     = [record.street_name, record.district, record.lga, record.state].filter(Boolean).join(', ') || '—';
    const propId  = record.prop_id   || '—';
    const prefix  = workflow === 'Plot Extension' ? 'ext' : 'cop';

    const hidden = document.getElementById('new_file_number');
    if (hidden) hidden.value = fileNo;

    const fileNoEl = document.getElementById(`${prefix}-dest-file-no`);
    if (fileNoEl) fileNoEl.textContent = fileNo;

    const titleEl = document.getElementById(`${prefix}-dest-title`);
    if (titleEl) titleEl.textContent = title || '—';

    const landEl = document.getElementById(`${prefix}-dest-land`);
    if (landEl) landEl.textContent = landUse;

    const locEl = document.getElementById(`${prefix}-dest-location`);
    if (locEl) locEl.textContent = loc;

    const propEl = document.getElementById(`${prefix}-dest-propid`);
    if (propEl) propEl.textContent = propId;

    const detailsEl = document.getElementById(`${prefix}-dest-details`);
    if (detailsEl) detailsEl.classList.remove('hidden');

    if (window.lucide) window.lucide.createIcons();
}

function clearDestinationFile(workflow) {
    const prefix = workflow === 'Plot Extension' ? 'ext' : 'cop';
    const hidden = document.getElementById('new_file_number');
    if (hidden) hidden.value = '';
    const fileNoEl = document.getElementById(`${prefix}-dest-file-no`);
    if (fileNoEl) fileNoEl.textContent = 'NOT SELECTED';
    const detailsEl = document.getElementById(`${prefix}-dest-details`);
    if (detailsEl) detailsEl.classList.add('hidden');
}

// ─── Subdivision Child File Selector ─────────────────────────────────────────
function openSubdivisionChildSelector(childIndex) {
    GlobalFileNoModal.open({
        callback: function(data) {
            if (!data.record) {
                Swal.fire({
                    icon: 'error',
                    title: 'File Not Indexed',
                    html: `<strong>${data.fileNumber}</strong> was not found in the File Indexing system.<br><br>
                           The child file must be <strong>indexed first</strong> before it can be linked here.`,
                });
                return;
            }
            backfillSubdivisionChildFile(childIndex, data);
        }
    });
}

function backfillSubdivisionChildFile(childIndex, data) {
    const fileNo = data.fileNumber || '';
    const record = data.record    || {};
    const title  = (record.file_name || record.file_title || '').toUpperCase();

    const child = subdivisionChildren.find(c => c.index === childIndex);
    if (child) {
        child.new_file_number = fileNo;
        child.file_title      = title;
    }

    const fileNoDisplay = document.getElementById(`sub-child-fileno-display-${childIndex}`);
    if (fileNoDisplay) fileNoDisplay.textContent = fileNo || 'NOT SELECTED';

    const titleDisplay = document.getElementById(`sub-child-title-display-${childIndex}`);
    if (titleDisplay) titleDisplay.textContent = title || '—';

    const hiddenInput = document.querySelector(`input[name="children[${childIndex}][new_file_number]"]`);
    if (hiddenInput) hiddenInput.value = fileNo;

    if (window.lucide) window.lucide.createIcons();
}

// ─── Render Right-Side Form (tailored per workflow) ──────────────────────────
function renderTailoredForm() {
    const fieldsContainer = document.getElementById('dynamic-form-fields');
    fieldsContainer.innerHTML = '';
    let html = '';

    if (selectedWorkflow === 'Subdivision') {
        html = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">File Title</label>
                    <input type="text" name="file_title" id="file_title"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold" placeholder="Common holder / applicant name">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Applicant Name</label>
                    <input type="text" name="applicant_name" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm" placeholder="e.g. Musa Ibrahim">
                </div>
            </div>

            <div class="border border-indigo-200 rounded-2xl p-4 bg-indigo-50/20 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-700">Child Plot Files <span class="text-red-500">*</span></span>
                    <button type="button" onclick="addSubdivisionChild()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold hover:bg-indigo-700 transition">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add Child Plot
                    </button>
                </div>
                <p class="text-[11px] text-slate-400">Add one row per subdivided child. Each child will receive its own linkage and Prop ID.</p>
                <div id="subdivision-children-list" class="space-y-2"></div>
                <div id="sub-children-empty" class="py-4 text-center text-xs text-slate-400 italic border border-dashed border-indigo-200 rounded-xl">
                    No child plots added yet. Click "Add Child Plot" to begin.
                </div>
            </div>

        `;
    } else if (selectedWorkflow === 'Merger') {
        html = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Temp File No</label>
                    <input type="text" name="temp_file_no" id="temp_file_no" readonly
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm font-bold font-mono text-slate-700"
                        placeholder="Auto-generating...">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Number of Source Plots <span class="text-red-500">*</span></label>
                    <input type="number" name="num_plots" id="num_plots" min="2" max="50" oninput="generateManualMergerSources()"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold text-orange-600">
                </div>
            </div>

            <div class="border border-slate-200 rounded-2xl p-4 bg-slate-50/40 space-y-4">
                <span class="text-xs font-bold uppercase tracking-wider text-orange-600">Individual Plot Sizes (Sqm)</span>
                <div id="manual_merger_sources_container" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <p class="col-span-full text-slate-400 text-xs italic">Set number of plots to define source sizes.</p>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                    <label class="text-sm font-semibold text-slate-700">Total Merged Area:</label>
                    <input type="text" name="plot_size" id="plot_size" class="w-32 text-right border-none bg-transparent text-orange-700 font-bold" value="" readonly>
                </div>
            </div>

            <div class="border border-slate-200 rounded-2xl p-4 bg-slate-50/40 space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-600">
                        Old Source Plot Details &nbsp;(<span id="manual_current_plot_label">0</span> of <span id="manual_total_plots_label">0</span>)
                    </span>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="navManualMergerLocation(-1)"
                            class="p-2 rounded-xl border border-slate-200 text-slate-400 hover:text-orange-600 hover:border-orange-200 hover:bg-orange-50 transition">
                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
                        </button>
                        <button type="button" onclick="navManualMergerLocation(1)"
                            class="p-2 rounded-xl border border-slate-200 text-slate-400 hover:text-orange-600 hover:border-orange-200 hover:bg-orange-50 transition">
                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
                <div id="manual_merger_location_cards_container">
                    <div class="p-8 text-center text-slate-400 italic text-xs border border-dashed border-slate-200 rounded-xl">Set number of source plots above to define their location details.</div>
                </div>
                <input type="hidden" name="location" id="location">
            </div>
        `;
    } else if (selectedWorkflow === 'Plot Extension') {
        html = `
            <div class="border border-emerald-200 rounded-2xl p-4 bg-emerald-50/30 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex-1 min-w-0 space-y-1">
                        <span class="px-2 py-0.5 bg-emerald-600 text-white text-[9px] font-black rounded uppercase tracking-wider">Extended / New File <span class="text-red-300">*</span></span>
                        <div id="ext-dest-file-no" class="text-sm font-bold text-slate-800 font-mono mt-1">NOT SELECTED</div>
                        <input type="hidden" name="new_file_number" id="new_file_number" value="">
                    </div>
                    <button type="button" onclick="openDestinationFileSelector('Plot Extension')"
                        class="flex items-center gap-2 px-3 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 transition shadow-sm whitespace-nowrap flex-shrink-0">
                        <i data-lucide="search" class="w-3.5 h-3.5"></i>
                        Select File
                    </button>
                </div>
                <div id="ext-dest-details" class="hidden border-t border-emerald-200 pt-3">
                    <table class="w-full text-xs text-slate-600">
                        <tr><td class="text-slate-400 pr-2 pb-1 w-20">Title:</td><td id="ext-dest-title" class="font-semibold text-slate-800 pb-1">—</td></tr>
                        <tr><td class="text-slate-400 pr-2 pb-1">Land Use:</td><td id="ext-dest-land" class="text-slate-700 pb-1">—</td></tr>
                        <tr><td class="text-slate-400 pr-2 pb-1">Location:</td><td id="ext-dest-location" class="text-slate-600 pb-1">—</td></tr>
                        <tr><td class="text-slate-400 pr-2">Prop ID:</td><td id="ext-dest-propid" class="font-bold text-blue-600">—</td></tr>
                    </table>
                    <button type="button" onclick="clearDestinationFile('Plot Extension')" class="mt-2 text-[10px] text-slate-400 hover:text-red-500 transition">✕ Clear Selection</button>
                </div>
                <p class="text-[11px] text-amber-700 bg-amber-50 rounded px-2 py-1 border border-amber-200">
                    If you cannot find the file, it has not been indexed yet — index it first, then return here.
                </p>
            </div>

        `;
    } else if (selectedWorkflow === 'Change of Purpose') {
        html = `
            <div class="border border-blue-200 rounded-2xl p-4 bg-blue-50/30 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex-1 min-w-0 space-y-1">
                        <span class="px-2 py-0.5 bg-blue-600 text-white text-[9px] font-black rounded uppercase tracking-wider">Changed-Purpose / New File <span class="text-red-300">*</span></span>
                        <div id="cop-dest-file-no" class="text-sm font-bold text-slate-800 font-mono mt-1">NOT SELECTED</div>
                        <input type="hidden" name="new_file_number" id="new_file_number" value="">
                    </div>
                    <button type="button" onclick="openDestinationFileSelector('Change of Purpose')"
                        class="flex items-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold hover:bg-blue-700 transition shadow-sm whitespace-nowrap flex-shrink-0">
                        <i data-lucide="search" class="w-3.5 h-3.5"></i>
                        Select File
                    </button>
                </div>
                <div id="cop-dest-details" class="hidden border-t border-blue-200 pt-3">
                    <table class="w-full text-xs text-slate-600">
                        <tr><td class="text-slate-400 pr-2 pb-1 w-20">Title:</td><td id="cop-dest-title" class="font-semibold text-slate-800 pb-1">—</td></tr>
                        <tr><td class="text-slate-400 pr-2 pb-1">Land Use:</td><td id="cop-dest-land" class="text-slate-700 pb-1">—</td></tr>
                        <tr><td class="text-slate-400 pr-2 pb-1">Location:</td><td id="cop-dest-location" class="text-slate-600 pb-1">—</td></tr>
                        <tr><td class="text-slate-400 pr-2">Prop ID:</td><td id="cop-dest-propid" class="font-bold text-blue-600">—</td></tr>
                    </table>
                    <button type="button" onclick="clearDestinationFile('Change of Purpose')" class="mt-2 text-[10px] text-slate-400 hover:text-red-500 transition">✕ Clear Selection</button>
                </div>
                <p class="text-[11px] text-amber-700 bg-amber-50 rounded px-2 py-1 border border-amber-200">
                    If you cannot find the file, it has not been indexed yet — index it first, then return here.
                </p>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">New Purpose <span class="text-red-500">*</span></label>
                <select name="purpose" id="purpose" class="w-full px-4 py-3 rounded-xl border border-slate-200 text-sm">
                    <option value="">- Select New Purpose -</option>
                    <option value="RES">Residential</option>
                    <option value="COM">Commercial</option>
                    <option value="IND">Industrial</option>
                    <option value="AGR">Agricultural</option>
                    <option value="MIX">Mixed Use</option>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Land Size (Sqm)</label>
                    <input type="number" step="0.01" name="land_size" id="manual_cop_land_size" oninput="calculateManualCopFee()"
                        class="w-full px-4 py-3 rounded-xl border border-slate-200 text-sm" placeholder="e.g. 500.00">
                </div>
                <div class="bg-blue-50/50 p-3 rounded-xl border border-blue-100 flex flex-col justify-center">
                    <label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">Application Fee (NGN 50/sqm)</label>
                    <input type="text" id="manual_cop_fee_display" class="w-full bg-transparent border-none text-sm font-bold text-blue-700 p-0" value="0.00" readonly>
                    <input type="hidden" name="knupda_fee" id="manual_cop_fee_hidden" value="0">
                </div>
            </div>

            ${approvalFieldsHTML()}

            ${manualLocationBlock('File & Purpose Location Details')}

        `;
    }

    fieldsContainer.innerHTML = html;
    if (window.lucide) window.lucide.createIcons();

    if (selectedWorkflow === 'Merger') {
        fetchAndSetTempFileNo();
    }
}

// ─── Badge Rendering (shared helper, called by DOMContentLoaded closures) ────
let renderBadges = () => {};

// ─── DOMContentLoaded: Verify + Badge + Submit ───────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const oldFileInput   = document.getElementById('old-file-input');
    const btnVerifyOld   = document.getElementById('btn-verify-old');
    const verifyLoader   = document.getElementById('verify-loader');
    const verifyCard     = document.getElementById('verify-card');
    const selectedList   = document.getElementById('selected-files-list');
    const hiddenInputs   = document.getElementById('hidden-inputs-container');
    const linkageForm    = document.getElementById('linkage-form');

    // ── Verify Card Detail Elements ───────────────────────────────────────────
    const vTitle         = document.getElementById('v-title');
    const vLand          = document.getElementById('v-land');
    const vLocation      = document.getElementById('v-location');
    const vPropid        = document.getElementById('v-propid');
    const vTx            = document.getElementById('v-tx');
    const vAlreadyLinked = document.getElementById('v-already-linked-badge');

    // ── Badges ────────────────────────────────────────────────────────────────
    renderBadges = function () {
        if (selectedOldFiles.length === 0) {
            selectedList.innerHTML  = '<span class="text-xs text-slate-400 italic">No legacy files added yet.</span>';
            hiddenInputs.innerHTML  = '';
            return;
        }
        selectedList.innerHTML  = '';
        hiddenInputs.innerHTML  = '';

        selectedOldFiles.forEach(fileNo => {
            const badge = document.createElement('span');
            badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-800 border border-amber-200 rounded-lg text-sm font-semibold badge-trans font-mono';
            badge.innerHTML = `<span>${fileNo}</span>
                <button type="button" class="w-4 h-4 rounded-full bg-amber-200 hover:bg-amber-300 text-amber-900 flex items-center justify-center text-[10px]" data-remove="${fileNo}">&times;</button>`;
            selectedList.appendChild(badge);

            const hiddenInput = document.createElement('input');
            hiddenInput.type  = 'hidden';
            hiddenInput.name  = 'old_file_numbers[]';
            hiddenInput.value = fileNo;
            hiddenInputs.appendChild(hiddenInput);
        });

        document.querySelectorAll('[data-remove]').forEach(btn => {
            btn.removeEventListener('click', removeBadgeHandler);
            btn.addEventListener('click', removeBadgeHandler);
        });
    };

    function removeBadgeHandler() {
        const removeNo = this.getAttribute('data-remove');
        const index    = selectedOldFiles.indexOf(removeNo);
        selectedOldFiles       = selectedOldFiles.filter(f => f !== removeNo);
        if (index >= 0) selectedOldFileDetails.splice(index, 1);
        if (selectedWorkflow === 'Merger') {
            const numPlotsInput = document.getElementById('num_plots');
            if (numPlotsInput && parseInt(numPlotsInput.value || '0') > selectedOldFiles.length) {
                numPlotsInput.value = selectedOldFiles.length;
            }
            generateManualMergerSources();
        }
        renderBadges();
    }

    // ── Verify AJAX ───────────────────────────────────────────────────────────
    btnVerifyOld.addEventListener('click', function () {
        const rawFile = oldFileInput.value.trim();
        if (!rawFile) {
            Swal.fire({ icon: 'warning', title: 'Input Required', text: 'Please enter a valid file number to verify.' });
            return;
        }
        const fileNo = rawFile.toUpperCase();

        if (selectedOldFiles.includes(fileNo)) {
            Swal.fire({ icon: 'info', title: 'Duplicate', text: 'This file is already selected.' });
            return;
        }
        if (selectedWorkflow !== 'Merger' && selectedOldFiles.length >= 1) {
            Swal.fire({ icon: 'warning', title: 'Limit Exceeded', text: `${selectedWorkflow} allows exactly one legacy source file.` });
            return;
        }
        if (selectedWorkflow === 'Merger') {
            const numPlots = parseInt(document.getElementById('num_plots')?.value || '0') || 0;
            if (numPlots > 0 && selectedOldFiles.length >= numPlots) {
                Swal.fire({ icon: 'warning', title: 'All Source Slots Filled', text: `This merger is set to ${numPlots} plots. Increase "Plots to Merge" before adding another source file.` });
                return;
            }
        }

        verifyLoader.classList.remove('hidden');
        verifyCard.classList.add('hidden');
        if (vAlreadyLinked) { vAlreadyLinked.classList.add('hidden'); vAlreadyLinked.textContent = ''; }

        fetch(`{{ route('admin.manual-linkage.search-old-file') }}?file_number=${encodeURIComponent(fileNo)}`)
            .then(r => r.json())
            .then(data => {
                verifyLoader.classList.add('hidden');

                if (data.error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.error });
                    return;
                }

                // Already decommissioned — hard block
                if (data.is_decommissioned) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Already Decommissioned',
                        html: `<strong>${fileNo}</strong> has already been decommissioned and cannot be used as a source file.<br><br>
                               <span class="text-slate-500 text-sm">${data.decommissioning_reason || data.message || ''}</span>`,
                    });
                    return;
                }

                if (!data.exists) {
                    Swal.fire({ icon: 'error', title: 'Not Found', text: data.message || 'File number could not be found in active records.' });
                    return;
                }

                // Already linked in a previous manual linkage — warn but allow operator to decide
                if (data.existing_linkage) {
                    if (vAlreadyLinked) {
                        vAlreadyLinked.textContent = `Previously linked → ${data.existing_linkage.new_file_number} (${data.existing_linkage.workflow_type}, ${data.existing_linkage.date})`;
                        vAlreadyLinked.classList.remove('hidden');
                    }
                }

                // Populate verify card
                vTitle.textContent    = data.file_title;
                vLand.textContent     = data.land_use;
                vLocation.textContent = [data.location, data.district, data.lga].filter(v => v && v !== 'N/A').join(', ') || 'N/A';
                vPropid.textContent   = data.prop_id;
                const tx = data.transactions;
                vTx.innerHTML = `<div class="flex gap-2 mt-1 flex-wrap">
                    <span class="px-2 py-0.5 bg-slate-200 rounded text-slate-700">CofO: <strong>${tx.cofo}</strong></span>
                    <span class="px-2 py-0.5 bg-slate-200 rounded text-slate-700">PRA: <strong>${tx.pra}</strong></span>
                    <span class="px-2 py-0.5 bg-slate-200 rounded text-slate-700">Deeds: <strong>${tx.deeds}</strong></span>
                </div>`;
                verifyCard.classList.remove('hidden');

                const existingLinkageWarning = data.existing_linkage
                    ? `<br><br><span class="text-orange-600 font-semibold text-sm">⚠ Warning: This file was previously processed on ${data.existing_linkage.date} as a <em>${data.existing_linkage.workflow_type}</em> → ${data.existing_linkage.new_file_number}. The system will block a duplicate linkage at save time.</span>`
                    : '';

                Swal.fire({
                    title: 'Verify File Details',
                    html: `Add <strong>${data.file_number}</strong> (${data.file_title})? It has ${tx.total} historical transaction(s).${existingLinkageWarning}`,
                    icon: data.existing_linkage ? 'warning' : 'question',
                    showCancelButton:    true,
                    confirmButtonColor:  '#3085d6',
                    cancelButtonColor:   '#d33',
                    confirmButtonText:   'Yes, Add File',
                }).then(result => {
                    if (result.isConfirmed) addFileToList(data);
                });
            })
            .catch(() => {
                verifyLoader.classList.add('hidden');
                Swal.fire({ icon: 'error', title: 'Request Failed', text: 'Unable to communicate with verification server.' });
            });
    });

    function addFileToList(fileData) {
        const fileNo = fileData.file_number;
        selectedOldFiles.push(fileNo);
        selectedOldFileDetails.push(fileData);

        if (selectedOldFiles.length === 1) {
            firstFileDetails = fileData;
            selectedList.innerHTML = '';
            const plotNumInput = document.getElementById('plot_number');
            if (plotNumInput && !plotNumInput.value.trim() && fileData.plot_number !== 'N/A') {
                plotNumInput.value = fileData.plot_number;
            }
        }

        if (selectedWorkflow === 'Merger') {
            const numPlotsInput = document.getElementById('num_plots');
            if (numPlotsInput) {
                const currentCount = parseInt(numPlotsInput.value || '0') || 0;
                if (selectedOldFiles.length > currentCount) numPlotsInput.value = selectedOldFiles.length;
                generateManualMergerSources();
                backfillManualMergerCard(selectedOldFiles.length, fileData);
                document.querySelectorAll('.manual-merger-location-card').forEach(c => c.classList.add('hidden'));
                document.getElementById(`manual_merger_location_card_${selectedOldFiles.length}`)?.classList.remove('hidden');
                const currentLabel = document.getElementById('manual_current_plot_label');
                if (currentLabel) currentLabel.textContent = String(selectedOldFiles.length);
            }
        }

        renderBadges();
        oldFileInput.value = '';
        verifyCard.classList.add('hidden');
        if (vAlreadyLinked) { vAlreadyLinked.classList.add('hidden'); vAlreadyLinked.textContent = ''; }
    }

    // ── Form Submit ───────────────────────────────────────────────────────────
    linkageForm.addEventListener('submit', function (e) {
        e.preventDefault();

        if (selectedWorkflow === 'Merger') {
            // Validate merged result is selected
            const mergedFileNo = document.getElementById('new_file_number')?.value?.trim();
            if (!mergedFileNo) {
                Swal.fire({ icon: 'error', title: 'Merged File Missing', text: 'Please select the merged result file using the "Select File" button on the left side.' });
                return;
            }
            // Validate num_plots >= 2
            const numPlots = parseInt(document.getElementById('num_plots')?.value || '0') || 0;
            if (numPlots < 2) {
                Swal.fire({ icon: 'error', title: 'Source Plots Required', text: 'A Merger requires at least 2 source plots. Enter the number above.' });
                return;
            }
            // Collect source file numbers from location cards
            const mergerSrcFiles = [];
            for (let i = 1; i <= numPlots; i++) {
                const card = document.getElementById(`manual_merger_location_card_${i}`);
                const srcNo = card?.querySelector(`input[name="location_details[${i}][source_file_no]"]`)?.value?.trim();
                if (!srcNo) {
                    Swal.fire({ icon: 'error', title: `Plot ${i} Missing Source File`, text: `Use the "Select File" button on Plot ${i}'s card to choose its old source file number.` });
                    return;
                }
                mergerSrcFiles.push(srcNo);
            }
            // Populate hidden old_file_numbers[] inputs from location cards
            const container = document.getElementById('hidden-inputs-container');
            if (container) {
                container.innerHTML = '';
                mergerSrcFiles.forEach(fn => {
                    const inp = document.createElement('input');
                    inp.type = 'hidden'; inp.name = 'old_file_numbers[]'; inp.value = fn;
                    container.appendChild(inp);
                });
            }
        } else if (selectedWorkflow === 'Subdivision') {
            const parentFile = document.getElementById('sub-parent-hidden')?.value?.trim();
            if (!parentFile) {
                Swal.fire({ icon: 'error', title: 'Parent File Not Selected',
                    text: 'Please use the "Select File" button to choose the parent file to be decommissioned.' });
                return;
            }
        } else if (selectedOldFiles.length === 0) {
            Swal.fire({ icon: 'error', title: 'Legacy Files Missing', text: 'Please verify and add at least one old file number.' });
            return;
        }
        if (selectedWorkflow === 'Subdivision') {
            if (subdivisionChildren.length === 0) {
                Swal.fire({ icon: 'error', title: 'No Child Plots', text: 'Add at least one child plot file number in the "Child Plot Files" section.' });
                return;
            }
            const emptyChild = subdivisionChildren.find(c => !c.new_file_number.trim());
            if (emptyChild) {
                Swal.fire({ icon: 'error', title: 'Incomplete Child Row', text: 'All child plot rows must have a file selected. Use the "Select" button on each row.' });
                return;
            }
        }

        if (selectedWorkflow === 'Plot Extension' || selectedWorkflow === 'Change of Purpose') {
            const newFile = document.getElementById('new_file_number')?.value?.trim();
            if (!newFile) {
                Swal.fire({ icon: 'error', title: 'Destination File Not Selected',
                    text: 'Please use the "Select File" button to choose the already-processed destination file.' });
                return;
            }
        }

        // Build confirm summary
        let newFileDisplay = '';
        let sourceFilesDisplay = selectedOldFiles.join(', ');
        if (selectedWorkflow === 'Subdivision') {
            sourceFilesDisplay = document.getElementById('sub-parent-hidden')?.value?.trim() || '—';
            newFileDisplay = subdivisionChildren.map((c, i) => `Child ${i + 1}: <strong>${c.new_file_number}</strong>`).join('<br>');
        } else if (selectedWorkflow === 'Merger') {
            newFileDisplay = `<strong>${document.getElementById('new_file_number')?.value?.trim() || '(not set)'}</strong>`;
            // Collect source display from location cards
            const numPlots = parseInt(document.getElementById('num_plots')?.value || '0') || 0;
            const srcList = [];
            for (let i = 1; i <= numPlots; i++) {
                const card = document.getElementById(`manual_merger_location_card_${i}`);
                const srcNo = card?.querySelector(`input[name="location_details[${i}][source_file_no]"]`)?.value?.trim();
                if (srcNo) srcList.push(srcNo);
            }
            sourceFilesDisplay = srcList.join(', ');
        } else {
            newFileDisplay = `<strong>${document.getElementById('new_file_number')?.value?.trim() || '(not set)'}</strong>`;
        }

        const approvalRef  = document.querySelector('input[name="approval_reference"]')?.value?.trim() || '—';
        const approvalDate = document.querySelector('input[name="approval_date"]')?.value || '—';

        Swal.fire({
            title: 'Confirm Manual Linkage Operation',
            html: `<div class="text-left space-y-2 text-sm">
                <div><span class="text-slate-400">Workflow:</span> <strong>${selectedWorkflow}</strong></div>
                <div><span class="text-slate-400">Source File(s):</span> <strong class="font-mono">${sourceFilesDisplay}</strong></div>
                ${newFileDisplay ? `<div><span class="text-slate-400">Merged/Linked To:</span><br>${newFileDisplay}</div>` : ''}
                ${selectedWorkflow !== 'Merger' ? `<div><span class="text-slate-400">Approval Ref:</span> ${approvalRef} &nbsp;|&nbsp; <span class="text-slate-400">Date:</span> ${approvalDate}</div>` : ''}
                <hr class="my-2 border-slate-200">
                <p class="text-red-600 font-semibold text-xs">This will decommission the source file(s), patch lineage on the destination(s), and is audited. This action is irreversible.</p>
            </div>`,
            icon:               'warning',
            showCancelButton:   true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor:  '#d33',
            confirmButtonText:  'Yes, Backfill Linkage',
        }).then(result => {
            if (result.isConfirmed) linkageForm.submit();
        });
    });
});
</script>
@endsection
