@extends('layouts.app')

@section('styles')
<style>
    .transition-all {
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .badge-trans {
        animation: fadeIn 0.3s ease-out;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .modal-backdrop {
        background-color: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(8px);
    }
</style>
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle'       => 'Manually Processed File Linkages',
        'PageDescription' => 'Record and manage linkages for files that were manually processed for Subdivision, Merger, Temporary File, or Change of Purpose.'
    ])

    <div class="py-10 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            {{-- -- Page Heading -- --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 mt-1">Manual File Linkage Portal</h1>
                    <p class="text-sm text-slate-500 mt-1">Safely decommission legacy files and establish secure linkages for new manual entries.</p>
                </div>
                <div>
                    <button type="button" onclick="openLinkageModal()"
                        class="inline-flex items-center gap-2 px-6 py-3.5 bg-blue-600 text-white rounded-xl text-sm font-bold shadow-md hover:bg-blue-700 transition-all transform hover:-translate-y-0.5">
                        <i data-lucide="plus" class="w-5 h-5"></i> New Manual Linkage
                    </button>
                </div>
            </div>

            {{-- Alert messages --}}
            @if(session('success'))
                <div class="p-4 bg-green-50 border-l-4 border-green-500 rounded-xl text-green-700 shadow-sm flex items-center gap-3">
                    <i data-lucide="check-circle" class="w-5 h-5 text-green-500"></i>
                    <div>
                        <p class="font-semibold">{{ session('success') }}</p>
                    </div>
                </div>
            @endif

            @if($errors->any())
                <div class="p-4 bg-red-50 border-l-4 border-red-500 rounded-xl text-red-700 shadow-sm space-y-1">
                    <div class="flex items-center gap-3">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i>
                        <p class="font-semibold">Something went wrong:</p>
                    </div>
                    <ul class="list-disc list-inside text-sm pl-8">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- -- Linkage History Table -- --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="font-bold text-slate-800 text-lg">Historical Manual Linkages</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Audit log of all manual workflow linkages registered on KLAES.</p>
                    </div>
                    <span class="bg-slate-100 text-slate-600 font-semibold px-3 py-1 rounded-full text-xs self-start sm:self-auto">
                        {{ $linkages->total() }} Total Linkages
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-400 font-semibold text-xs border-b border-slate-200">
                                <th class="px-6 py-4">Workflow Type</th>
                                <th class="px-6 py-4">Old File(s) Decommissioned</th>
                                <th class="px-6 py-4">New File Commissioned</th>
                                <th class="px-6 py-4">Linked Prop ID</th>
                                <th class="px-6 py-4">Applicant / Holder</th>
                                <th class="px-6 py-4">Processed By</th>
                                <th class="px-6 py-4">Logged Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                            @forelse($linkages as $link)
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="px-6 py-4 font-semibold">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold 
                                            @if($link->workflow_type === 'Subdivision') bg-indigo-50 text-indigo-700
                                            @elseif($link->workflow_type === 'Merger') bg-amber-50 text-amber-700
                                            @elseif($link->workflow_type === 'Temporary File') bg-purple-50 text-purple-700
                                            @else bg-blue-50 text-blue-700 @endif">
                                            {{ $link->workflow_type }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-xs font-mono font-medium max-w-[200px] truncate">
                                        @php
                                            $oldFiles = json_decode($link->old_file_numbers, true) ?: [$link->old_file_numbers];
                                        @endphp
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($oldFiles as $oldFile)
                                                <span class="bg-slate-100 text-slate-800 px-1.5 py-0.5 rounded border border-slate-200">{{ $oldFile }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 font-mono font-bold text-slate-900">{{ $link->new_file_number }}</td>
                                    <td class="px-6 py-4 text-xs text-blue-600 font-bold font-mono">{{ $link->prop_id ?: '—' }}</td>
                                    <td class="px-6 py-4">{{ $link->applicant_name ?: '—' }}</td>
                                    <td class="px-6 py-4 font-medium text-slate-800">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center text-[10px] font-bold text-slate-600">
                                                {{ substr($link->processed_by, 0, 2) }}
                                            </div>
                                            <span>{{ $link->processed_by }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-slate-400">
                                        {{ \Carbon\Carbon::parse($link->created_at)->format('d/m/Y H:i') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-slate-400 italic">
                                        No manual process linkages recorded yet. Click the "New Manual Linkage" button to record one.
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

{{-- -- MODAL DIALOG CONTAINER -- --}}
<div id="linkage-modal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
    {{-- Backdrop --}}
    <div class="fixed inset-0 modal-backdrop transition-opacity duration-300" onclick="closeLinkageModal()"></div>

    {{-- Content Card --}}
    <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 z-10 w-full max-w-[90%] lg:max-w-6xl max-h-[90vh] overflow-hidden flex flex-col transition-all duration-300 transform scale-95 opacity-0" id="modal-content">
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-blue-700 to-indigo-800 px-8 py-5 flex items-center justify-between text-white flex-shrink-0">
            <div class="flex items-center gap-3">
                <i data-lucide="link" class="w-6 h-6"></i>
                <div>
                    <h2 class="text-lg font-bold">New Manual Process Linkage</h2>
                    <p class="text-xs text-blue-200">Decommission legacy numbers and commission the new destination record.</p>
                </div>
            </div>
            <button type="button" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition-all text-white text-lg font-bold" onclick="closeLinkageModal()">
                &times;
            </button>
        </div>

        {{-- Scrollable wizard steps container --}}
        <div class="flex-1 overflow-y-auto p-8 bg-slate-50/30">
            
            {{-- STEP 1: Workflow Type Selection --}}
            <div id="step-select-workflow" class="space-y-6">
                <div class="text-center max-w-xl mx-auto space-y-2">
                    <h3 class="text-xl font-bold text-slate-800">Select Workflow Process Type</h3>
                    <p class="text-sm text-slate-500">Every process has a different, custom form optimized exactly for its real-world registry workflow.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mx-auto pt-4">
                    {{-- Subdivision Card --}}
                    <div onclick="selectWorkflow('Subdivision')" class="bg-white hover:bg-indigo-50/30 border border-slate-200 hover:border-indigo-500 p-6 rounded-2xl cursor-pointer shadow-sm hover:shadow-md transition-all group flex gap-4 items-start">
                        <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-all">
                            <i data-lucide="split" class="w-6 h-6"></i>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-bold text-slate-800 text-base group-hover:text-indigo-700 transition">Subdivision Linkage</h4>
                            <p class="text-xs text-slate-400">Splits exactly **one** legacy parent plot file into **multiple** new subdivided unit files.</p>
                        </div>
                    </div>

                    {{-- Merger Card --}}
                    <div onclick="selectWorkflow('Merger')" class="bg-white hover:bg-amber-50/30 border border-slate-200 hover:border-amber-500 p-6 rounded-2xl cursor-pointer shadow-sm hover:shadow-md transition-all group flex gap-4 items-start">
                        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-all">
                            <i data-lucide="git-merge" class="w-6 h-6"></i>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-bold text-slate-800 text-base group-hover:text-amber-700 transition">Merger Linkage</h4>
                            <p class="text-xs text-slate-400">Consolidates **multiple** legacy plot files into **one** single newly merged file.</p>
                        </div>
                    </div>

                    {{-- Temporary File Card --}}
                    <div onclick="selectWorkflow('Temporary File')" class="bg-white hover:bg-purple-50/30 border border-slate-200 hover:border-purple-500 p-6 rounded-2xl cursor-pointer shadow-sm hover:shadow-md transition-all group flex gap-4 items-start">
                        <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-all">
                            <i data-lucide="file-clock" class="w-6 h-6"></i>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-bold text-slate-800 text-base group-hover:text-purple-700 transition">Temporary to Permanent File</h4>
                            <p class="text-xs text-slate-400">Converts **one** legacy temporary request file to **one** permanent official file number.</p>
                        </div>
                    </div>

                    {{-- Change of Purpose Card --}}
                    <div onclick="selectWorkflow('Change of Purpose')" class="bg-white hover:bg-blue-50/30 border border-slate-200 hover:border-blue-500 p-6 rounded-2xl cursor-pointer shadow-sm hover:shadow-md transition-all group flex gap-4 items-start">
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-all">
                            <i data-lucide="refresh-cw" class="w-6 h-6"></i>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-bold text-slate-800 text-base group-hover:text-blue-700 transition">Change of Purpose</h4>
                            <p class="text-xs text-slate-400">Re-commissions **one** legacy file to a **new** file with changed land use prefix.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- STEP 2: Tailored Linkage Form --}}
            <div id="step-linkage-form" class="hidden">
                <form action="{{ route('admin.manual-linkage.store') }}" method="POST" id="linkage-form">
                    @csrf
                    
                    {{-- Selected workflow state tracker --}}
                    <input type="hidden" name="workflow_type" id="hidden_workflow_type">

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 bg-white border border-slate-200 rounded-3xl p-6 lg:p-8">
                        
                        {{-- LEFT SIDE: Old File Details (Dynamically tailored based on selection) --}}
                        <div class="space-y-6">
                            <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                                <span class="w-7 h-7 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-sm">1</span>
                                <h3 class="font-bold text-slate-800 text-base" id="old-side-header">Legacy / Source File Details</h3>
                            </div>

                            {{-- Old File Search Form --}}
                            <div class="space-y-4">
                                <label class="block text-sm font-semibold text-slate-700" id="old-input-label">Add Old File Number</label>
                                <div class="flex gap-2">
                                    <div class="relative flex-1">
                                        <i data-lucide="file-text" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                        <input type="text" id="old-file-input" placeholder="e.g. CON-RES-2000-158"
                                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                    </div>
                                    <button type="button" id="btn-verify-old"
                                        class="px-5 py-3 bg-slate-800 text-white rounded-xl text-sm font-semibold hover:bg-slate-700 transition">
                                        Verify & Add
                                    </button>
                                </div>
                                <p class="text-[11px] text-slate-400" id="old-help-text">Type the file number and click verify.</p>
                            </div>

                            {{-- Dynamic Verification Result Info Card --}}
                            <div id="verify-loader" class="hidden py-4 flex justify-center">
                                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                            </div>

                            <div id="verify-card" class="hidden p-5 bg-slate-50 border border-slate-200 rounded-2xl transition-all badge-trans">
                                <div class="flex justify-between items-start mb-3">
                                    <span class="text-xs font-semibold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded">Active Record Verified</span>
                                </div>
                                <table class="w-full text-xs text-slate-600 space-y-2">
                                    <tr>
                                        <td class="font-medium text-slate-400 pb-1 w-24">File Title:</td>
                                        <td class="font-semibold text-slate-800 pb-1" id="v-title">-</td>
                                    </tr>
                                    <tr>
                                        <td class="font-medium text-slate-400 pb-1">Land Use:</td>
                                        <td class="font-semibold text-slate-800 pb-1" id="v-land">-</td>
                                    </tr>
                                    <tr>
                                        <td class="font-medium text-slate-400 pb-1">LGA/Location:</td>
                                        <td class="font-semibold text-slate-800 pb-1" id="v-location">-</td>
                                    </tr>
                                    <tr>
                                        <td class="font-medium text-slate-400 pb-1">Property ID:</td>
                                        <td class="font-semibold text-blue-600 pb-1" id="v-propid">-</td>
                                    </tr>
                                    <tr>
                                        <td class="font-medium text-slate-400">Transactions:</td>
                                        <td>
                                            <span class="font-semibold text-slate-800" id="v-tx">-</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            {{-- Selected Old Files Badges Container --}}
                            <div class="space-y-3">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400" id="list-label">Selected Files to Decommission</label>
                                <div id="selected-files-list" class="flex flex-wrap gap-2 py-2">
                                    <span class="text-xs text-slate-400 italic">No legacy files added yet.</span>
                                </div>
                                {{-- Hidden Inputs Container for Form Submission --}}
                                <div id="hidden-inputs-container"></div>
                            </div>
                        </div>

                        {{-- RIGHT SIDE: New File Details (Tailored dynamically) --}}
                        <div class="space-y-6">
                            <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                                <span class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm">2</span>
                                <h3 class="font-bold text-slate-800 text-base" id="new-side-header">New Destination File Details</h3>
                            </div>

                            {{-- Form inputs injected dynamically in JS --}}
                            <div id="dynamic-form-fields" class="space-y-5">
                                {{-- Fields are generated by renderTailoredForm() in JavaScript --}}
                            </div>

                            {{-- Standard Applicant / Remarks fields which exist on all forms but prefilled --}}
                            <div class="space-y-5 pt-3 border-t border-slate-100">
                                {{-- Applicant Name --}}
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-slate-700">Applicant / Current Holder Name</label>
                                    <div class="relative">
                                        <i data-lucide="user" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                        <input type="text" name="applicant_name" id="applicant_name" placeholder="Leave blank to copy from old file"
                                            class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                    </div>
                                </div>

                                {{-- Remarks --}}
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-slate-700">Remarks / Property Description</label>
                                    <textarea name="remarks" id="remarks" rows="2" placeholder="Enter any extra details or property changes..."
                                        class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Form Footer Actions --}}
                    <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <button type="button" onclick="goBackToStep1()"
                            class="px-6 py-3.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-sm font-bold transition flex items-center gap-2 w-full sm:w-auto justify-center">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Process Type
                        </button>
                        <button type="submit" id="btn-submit-linkage"
                            class="px-8 py-3.5 bg-blue-600 text-white rounded-xl text-sm font-bold shadow-md hover:bg-blue-700 transition flex items-center gap-2 w-full sm:w-auto justify-center">
                            <i data-lucide="check-square" class="w-4 h-4"></i> Link and Decommission Files
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
<script>
    let selectedWorkflow = null;
    let selectedOldFiles = [];
    let firstFileDetails = null;

    // Open Modal
    function openLinkageModal() {
        const modal = document.getElementById('linkage-modal');
        const content = document.getElementById('modal-content');
        modal.classList.remove('hidden');
        setTimeout(() => {
            content.classList.remove('scale-95', 'opacity-0');
            content.classList.add('scale-100', 'opacity-100');
        }, 50);

        goBackToStep1();
    }

    // Close Modal
    function closeLinkageModal() {
        const modal = document.getElementById('linkage-modal');
        const content = document.getElementById('modal-content');
        content.classList.remove('scale-100', 'opacity-100');
        content.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 200);
    }

    // Step navigation
    function goBackToStep1() {
        document.getElementById('step-select-workflow').classList.remove('hidden');
        document.getElementById('step-linkage-form').classList.add('hidden');
        selectedWorkflow = null;
        selectedOldFiles = [];
        firstFileDetails = null;
        renderBadges();
        document.getElementById('linkage-form').reset();
    }

    function selectWorkflow(workflow) {
        selectedWorkflow = workflow;
        document.getElementById('hidden_workflow_type').value = workflow;

        // Swap steps visual
        document.getElementById('step-select-workflow').classList.add('hidden');
        document.getElementById('step-linkage-form').classList.remove('hidden');

        // Tailor Left & Right form layouts
        tailorLeftForm();
        renderTailoredForm();

        // Re-init lucide icons on dynamic content
        lucide.createIcons();
    }

    // Tailor the legacy old file verification left side
    function tailorLeftForm() {
        const oldSideHeader = document.getElementById('old-side-header');
        const oldInputLabel = document.getElementById('old-input-label');
        const oldHelpText = document.getElementById('old-help-text');
        const listLabel = document.getElementById('list-label');

        if (selectedWorkflow === 'Subdivision') {
            oldSideHeader.textContent = 'Subdivision Parent File';
            oldInputLabel.textContent = 'Enter Single Legacy Parent File';
            oldHelpText.textContent = 'Type the parent file number to split and click verify. (Exactly 1 required).';
            listLabel.textContent = 'Parent File to Decommission';
        } else if (selectedWorkflow === 'Merger') {
            oldSideHeader.textContent = 'Merger Source Files';
            oldInputLabel.textContent = 'Add Legacy File Numbers to Merge';
            oldHelpText.textContent = 'Verify and add legacy files one-by-one. (At least 2 required).';
            listLabel.textContent = 'Source Files to Consolidate';
        } else if (selectedWorkflow === 'Temporary File') {
            oldSideHeader.textContent = 'Legacy Temporary File';
            oldInputLabel.textContent = 'Enter Legacy Temporary File Number';
            oldHelpText.textContent = 'Type the temporary file number and click verify. (Exactly 1 required).';
            listLabel.textContent = 'Temporary File to Retire';
        } else if (selectedWorkflow === 'Change of Purpose') {
            oldSideHeader.textContent = 'Legacy Source File';
            oldInputLabel.textContent = 'Enter Legacy File Number';
            oldHelpText.textContent = 'Type the legacy file number to re-purpose and click verify. (Exactly 1 required).';
            listLabel.textContent = 'Legacy File to Re-Purpose';
        }
    }

    // Render completely specialized dynamic input elements for the new destination file
    function renderTailoredForm() {
        const fieldsContainer = document.getElementById('dynamic-form-fields');
        fieldsContainer.innerHTML = ''; // reset

        let html = '';

        if (selectedWorkflow === 'Subdivision') {
            html = `
                <!-- Subdivision Dynamic Form -->
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-slate-700">New Subdivided File Number</label>
                    <div class="relative">
                        <i data-lucide="key" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="new_file_number" id="new_file_number" required placeholder="e.g. CON-RES-2026-302-001"
                            class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-slate-700">Subdivided Plot Number</label>
                        <input type="text" name="plot_number" id="plot_number" required placeholder="e.g. Plot 14A"
                            class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-slate-700">Subdivided Plot Size</label>
                        <input type="text" name="plot_size" id="plot_size" placeholder="e.g. 500 sqm"
                            class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>
                </div>
            `;
        } else if (selectedWorkflow === 'Merger') {
            html = `
                <!-- Merger Dynamic Form -->
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-slate-700">New Consolidated Merged File Number</label>
                    <div class="relative">
                        <i data-lucide="key" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-mono"></i>
                        <input type="text" name="new_file_number" id="new_file_number" required placeholder="e.g. CON-COM-2026-905"
                            class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-slate-700">Merged Plot Number(s)</label>
                        <input type="text" name="plot_number" id="plot_number" required placeholder="e.g. Plot 14 & 15"
                            class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-slate-700">Consolidated Plot Size</label>
                        <input type="text" name="plot_size" id="plot_size" placeholder="e.g. 1500 sqm"
                            class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>
                </div>
            `;
        } else if (selectedWorkflow === 'Temporary File') {
            html = `
                <!-- Temporary Dynamic Form -->
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-slate-700">New Permanent Official File Number</label>
                    <div class="relative">
                        <i data-lucide="key" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-mono"></i>
                        <input type="text" name="new_file_number" id="new_file_number" required placeholder="e.g. CON-RES-2026-1054"
                            class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 font-mono">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-slate-700">Original Plot Number</label>
                    <input type="text" name="plot_number" id="plot_number" placeholder="e.g. Plot 12B"
                        class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                </div>
            `;
        } else if (selectedWorkflow === 'Change of Purpose') {
            html = `
                <!-- Change of Purpose Dynamic Form -->
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-slate-700">New Re-Purposed File Number</label>
                    <div class="relative">
                        <i data-lucide="key" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-mono"></i>
                        <input type="text" name="new_file_number" id="new_file_number" required placeholder="e.g. CON-COM-2026-302"
                            class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 font-mono">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-slate-700">New Land Use Category</label>
                    <select name="land_use_type" id="land_use_type" required
                        class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        <option value="COM">Commercial (COM)</option>
                        <option value="RES">Residential (RES)</option>
                        <option value="IND">Industrial (IND)</option>
                        <option value="AGR">Agricultural (AGR)</option>
                        <option value="INS">Institutional (INS)</option>
                    </select>
                </div>
            `;
        }

        fieldsContainer.innerHTML = html;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const oldFileInput = document.getElementById('old-file-input');
        const btnVerifyOld = document.getElementById('btn-verify-old');
        const verifyLoader = document.getElementById('verify-loader');
        const verifyCard = document.getElementById('verify-card');
        const selectedList = document.getElementById('selected-files-list');
        const hiddenInputs = document.getElementById('hidden-inputs-container');
        const linkageForm = document.getElementById('linkage-form');

        // Details elements
        const vTitle = document.getElementById('v-title');
        const vLand = document.getElementById('v-land');
        const vLocation = document.getElementById('v-location');
        const vPropid = document.getElementById('v-propid');
        const vTx = document.getElementById('v-tx');

        // AJAX Verification
        btnVerifyOld.addEventListener('click', function () {
            const rawFile = oldFileInput.value.trim();
            if (!rawFile) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Input Required',
                    text: 'Please enter a valid file number to verify.'
                });
                return;
            }

            const fileNo = rawFile.toUpperCase();

            // Check if already in the select list
            if (selectedOldFiles.includes(fileNo)) {
                Swal.fire({
                    icon: 'info',
                    title: 'Duplicate',
                    text: 'This file is already selected.'
                });
                return;
            }

            // Enforce single file limits for Subdivision, CoP, Temporary
            if (selectedWorkflow !== 'Merger' && selectedOldFiles.length >= 1) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Limit Exceeded',
                    text: `A ${selectedWorkflow} operation allows exactly one legacy source file.`
                });
                return;
            }

            verifyLoader.classList.remove('hidden');
            verifyCard.classList.add('hidden');

            fetch(`{{ route('admin.manual-linkage.search-old-file') }}?file_number=${encodeURIComponent(fileNo)}`)
                .then(response => response.json())
                .then(data => {
                    verifyLoader.classList.add('hidden');
                    if (data.error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.error
                        });
                        return;
                    }

                    if (!data.exists) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Not Found',
                            text: data.message || 'File number could not be found in active records.'
                        });
                        return;
                    }

                    // Display details
                    vTitle.textContent = data.file_title;
                    vLand.textContent = data.land_use;
                    vLocation.textContent = `${data.location} (${data.district}, ${data.lga})`;
                    vPropid.textContent = data.prop_id;

                    const tx = data.transactions;
                    vTx.innerHTML = `
                        <div class="flex gap-2 mt-1">
                            <span class="px-2 py-0.5 bg-slate-200 rounded text-slate-700">CofO: <strong>${tx.cofo}</strong></span>
                            <span class="px-2 py-0.5 bg-slate-200 rounded text-slate-700">PRA: <strong>${tx.pra}</strong></span>
                            <span class="px-2 py-0.5 bg-slate-200 rounded text-slate-700">Deeds: <strong>${tx.deeds}</strong></span>
                        </div>
                    `;

                    verifyCard.classList.remove('hidden');

                    Swal.fire({
                        title: 'Verify File Details',
                        html: `Do you want to add <strong>${data.file_number}</strong> (${data.file_title})?<br/>
                                <small class="text-slate-400">This file has ${tx.total} historical transaction(s) that will be linked.</small>`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, Add File'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            addFileToList(data);
                        }
                    });
                })
                .catch(err => {
                    verifyLoader.classList.add('hidden');
                    Swal.fire({
                        icon: 'error',
                        title: 'Request Failed',
                        text: 'Unable to communicate with verification server.'
                    });
                });
        });

        function addFileToList(fileData) {
            const fileNo = fileData.file_number;
            selectedOldFiles.push(fileNo);

            if (selectedOldFiles.length === 1) {
                firstFileDetails = fileData;
                selectedList.innerHTML = '';
                
                // Prefill Applicant
                const appNameInput = document.getElementById('applicant_name');
                if (!appNameInput.value.trim()) {
                    appNameInput.value = fileData.file_title;
                }

                // Prefill plot number if present in merger/subdivision/temporary
                const plotNumInput = document.getElementById('plot_number');
                if (plotNumInput && !plotNumInput.value.trim()) {
                    plotNumInput.value = fileData.plot_number !== 'N/A' ? fileData.plot_number : '';
                }
            }

            renderBadges();
            oldFileInput.value = '';
            verifyCard.classList.add('hidden');
        }

        function renderBadges() {
            if (selectedOldFiles.length === 0) {
                selectedList.innerHTML = '<span class="text-xs text-slate-400 italic">No legacy files added yet.</span>';
                hiddenInputs.innerHTML = '';
                return;
            }

            selectedList.innerHTML = '';
            hiddenInputs.innerHTML = '';

            selectedOldFiles.forEach(fileNo => {
                const badge = document.createElement('span');
                badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-800 border border-amber-200 rounded-lg text-sm font-semibold badge-trans font-mono';
                badge.innerHTML = `
                    <span>${fileNo}</span>
                    <button type="button" class="w-4 h-4 rounded-full bg-amber-200 hover:bg-amber-300 text-amber-900 flex items-center justify-center text-[10px]" data-remove="${fileNo}">
                        &times;
                    </button>
                `;
                selectedList.appendChild(badge);

                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'old_file_numbers[]';
                hiddenInput.value = fileNo;
                hiddenInputs.appendChild(hiddenInput);
            });

            // Register remove listeners
            document.querySelectorAll('[data-remove]').forEach(btn => {
                btn.removeEventListener('click', removeBadgeHandler); // clean
                btn.addEventListener('click', removeBadgeHandler);
            });
        }

        function removeBadgeHandler() {
            const removeNo = this.getAttribute('data-remove');
            selectedOldFiles = selectedOldFiles.filter(item => item !== removeNo);
            renderBadges();
        }

        // Form Submit Warning
        linkageForm.addEventListener('submit', function (e) {
            e.preventDefault();

            if (selectedOldFiles.length === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Legacy Files Missing',
                    text: 'Please verify and add at least one old file number.'
                });
                return;
            }

            if (selectedWorkflow === 'Merger' && selectedOldFiles.length < 2) {
                Swal.fire({
                    icon: 'error',
                    title: 'Merger Requires 2+ Files',
                    text: 'A Merger linkage requires at least two legacy files to consolidate.'
                });
                return;
            }

            const newFile = document.getElementById('new_file_number').value.trim();

            Swal.fire({
                title: 'Confirm Manual Linkage Operation',
                html: `You are about to link files with the following parameters:<br/><br/>
                       <strong>Workflow Process:</strong> ${selectedWorkflow}<br/>
                       <strong>Legacy File(s):</strong> ${selectedOldFiles.join(', ')}<br/>
                       <strong>New File:</strong> ${newFile}<br/><br/>
                       <span class="text-red-600 font-semibold font-sm">⚠️ This will decommission and archive the old files. This action is audited and irreversible!</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Link and Decommission'
            }).then((result) => {
                if (result.isConfirmed) {
                    linkageForm.submit();
                }
            });
        });
    });
</script>
@endsection
