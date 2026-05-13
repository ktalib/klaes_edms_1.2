    <style>
        #valuation-form input[type="text"], 
    #valuation-form textarea, 
    #valuation-form input[type="search"] {
        text-transform: uppercase !important;
    }

    /* Select2 Premium Styling for Valuation Modal */
    .select2-container--default .select2-selection--multiple {
        border-radius: 0.75rem !important;
        border: 1px solid #e2e8f0 !important;
        background-color: #f8fafc !important;
        padding: 4px 8px !important;
        min-height: 46px !important;
        transition: all 0.2s ease !important;
    }
    .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: #3b82f6 !important;
        background-color: #fff !important;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #eff6ff !important;
        border: 1px solid #bfdbfe !important;
        color: #1e40af !important;
        border-radius: 0.5rem !important;
        padding: 2px 8px !important;
        font-weight: 700 !important;
        font-size: 11px !important;
        margin-top: 6px !important;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #3b82f6 !important;
        margin-right: 5px !important;
    }
    .select2-dropdown {
        border-radius: 1rem !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
        overflow: hidden !important;
    }
    </style>
<!-- Valuation for Compensation Modal -->
<div id="valuation-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <!-- Background overlay -->
    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" aria-hidden="true" id="modal-overlay"></div>

    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden border border-slate-100 transform transition-all">
        <!-- Header -->
        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
            <div>
                <h3 class="text-xl font-bold text-slate-900" id="modal-title">Valuation for Compensation Data Entry</h3>
                <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-semibold">Record property compensation details</p>
            </div>
            <button type="button" class="close-modal text-slate-400 hover:text-slate-600 p-2 hover:bg-slate-50 rounded-xl transition">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>

        <!-- Form Body -->
        <form id="valuation-form" action="{{ route('valuation-compensations.store') }}" method="POST" class="flex-1 overflow-y-auto">
            @csrf
            <input type="hidden" name="id" id="record_id">
            
            <div class="px-8 py-8 space-y-8">
                <!-- Section 0: Project & Worker (Mandatory for New) -->
                <div class="space-y-6 pb-8 border-b border-slate-100" id="project-selection-section">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded-full bg-amber-50 flex items-center justify-center text-amber-600">
                            <i data-lucide="briefcase" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Project & Assignment</h4>
                    </div>

                    <div class="space-y-6">
                        <!-- Row 1: Project Selection -->
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Select Project <span class="text-red-500">*</span></label>
                            <select name="project_id" id="vfc_project_id" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                <option value="">Select Project</option>
                            </select>
                        </div>

                        <!-- Row 2: Project Summary (Full Width) -->
                        <div id="project-info" class="hidden p-6 rounded-2xl bg-blue-50/50 border border-blue-100/50">
                            <p class="text-[10px] font-bold text-blue-600 uppercase tracking-widest flex items-center gap-2 mb-4 px-1">
                                <i data-lucide="info" class="h-3 w-3"></i> Project Summary
                            </p>
                            <div class="space-y-6">
                                <!-- Primary Identifiers -->
                                <div class="flex flex-wrap items-start justify-between gap-x-10 gap-y-4 pb-5 border-b border-blue-100/50">
                                    <div class="flex flex-col">
                                        <span class="text-[9px] text-blue-400 font-bold uppercase tracking-widest mb-1.5">Project FileNo</span>
                                        <div class="flex items-center gap-2.5">
                                            <i data-lucide="file-text" class="h-4 w-4 text-blue-500"></i>
                                            <span id="proj_fileno_summary" class="text-[13px] font-black text-blue-900 font-mono whitespace-nowrap">-</span>
                                        </div>
                                    </div>

                                    <div class="flex flex-col">
                                        <span class="text-[9px] text-amber-500/70 font-bold uppercase tracking-widest mb-1.5">Project Code</span>
                                        <div class="flex items-center gap-2.5">
                                            <i data-lucide="tag" class="h-4 w-4 text-amber-600"></i>
                                            <span id="proj_code_summary" class="text-[13px] font-black text-amber-800 font-mono whitespace-nowrap">-</span>
                                        </div>
                                    </div>

                                    <div class="flex flex-col">
                                        <span class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mb-1.5">Project ID</span>
                                        <div class="flex items-center gap-2.5">
                                            <i data-lucide="hash" class="h-4 w-4 text-slate-500"></i>
                                            <span id="proj_id_summary" class="text-[13px] font-black text-slate-700">-</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Statistics -->
                                <div class="flex flex-wrap items-center gap-x-16 gap-y-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-xl bg-white border border-slate-100 shadow-sm flex items-center justify-center text-indigo-400">
                                            <i data-lucide="users" class="h-5 w-5"></i>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-[9px] text-slate-400 font-bold uppercase tracking-tight">Total Workers</span>
                                            <span id="proj_workers_summary" class="text-sm font-black text-slate-800">0</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-xl bg-white border border-slate-100 shadow-sm flex items-center justify-center text-emerald-400">
                                            <i data-lucide="check-circle" class="h-5 w-5"></i>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-[9px] text-slate-400 font-bold uppercase tracking-tight">Forms Filled</span>
                                            <span id="proj_valuations_summary" class="text-sm font-black text-emerald-700">0</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-xl bg-white border border-slate-100 shadow-sm flex items-center justify-center text-amber-400">
                                            <i data-lucide="layers" class="h-5 w-5"></i>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-[9px] text-slate-400 font-bold uppercase tracking-tight">Sub-Projects</span>
                                            <span id="proj_subprojects_summary" class="text-sm font-black text-amber-700">0</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 3: Worker & Sub-Project -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Assigned Worker <span class="text-red-500">*</span></label>
                                <select name="worker_id" id="vfc_worker_id" required
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                    <option value="">Select Worker (Select Project First)</option>
                                </select>
                                <p class="text-[10px] text-slate-400 mt-1 italic">The Worker ID will be automatically recorded.</p>
                            </div>
                            <div id="sub-project-section" class="hidden">
                                <label class="block text-xs font-bold text-indigo-600 uppercase tracking-wider mb-2">Select Sub-Project <span class="text-red-500">*</span></label>
                                <select name="sub_project_id" id="vfc_sub_project_id"
                                    class="w-full px-4 py-3 rounded-xl border border-indigo-200 bg-indigo-50/30 focus:border-indigo-500 focus:bg-white transition text-sm font-bold text-indigo-700">
                                    <option value="">Select Sub-Project</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 1: File & Owner -->
                <div class="space-y-6">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">
                            <i data-lucide="file-text" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">File & Owner Information</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Project References (From Project) -->
                        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Project Code <span class="text-slate-400 font-normal italic">(From Project)</span></label>
                                <input type="text" id="project_code_display" readonly
                                    class="w-full px-4 py-3 rounded-xl border border-amber-200 bg-amber-50 text-sm font-bold font-mono text-amber-700 cursor-not-allowed shadow-sm"
                                    placeholder="Select Project First...">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Project FileNo <span class="text-slate-400 font-normal italic">(From Project)</span></label>
                                <input type="text" id="our_ref" readonly
                                    class="w-full px-4 py-3 rounded-xl border border-blue-200 bg-blue-50 text-sm font-bold font-mono text-blue-700 cursor-not-allowed shadow-sm"
                                    placeholder="Select Project First...">
                                <input type="hidden" name="project_fileno" id="hidden_project_fileno">
                            </div>
                        </div>

                        <!-- References -->
                        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Our Reference <span class="text-red-500">*</span></label>
                                <input type="text" name="our_ref" id="manual_our_ref" readonly
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-sm font-bold font-mono text-slate-500 cursor-not-allowed shadow-inner"
                                    placeholder="Select Project First...">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Your Reference</label>
                                <input type="text" name="your_ref" id="your_ref" readonly
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-sm font-medium text-slate-500 cursor-not-allowed shadow-inner"
                                    placeholder="Select Project First...">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Owner Name <span class="text-red-500">*</span></label>
                            <input type="text" name="owner_name" id="owner_name" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                placeholder="e.g. Musa Yakubu">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Valuation Date <span class="text-red-500">*</span></label>
                            <input type="date" name="valuation_date" id="valuation_date" required value="{{ date('Y-m-d') }}"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Building & Compensated Items -->
                <div class="pt-8 border-t border-slate-100">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600">
                            <i data-lucide="home" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Building & Compensated Items</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Building Type <span class="text-red-500">*</span></label>
                            <select name="building_type" id="building_type" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                <option value="">Select Building Type</option>
                                @foreach($buildingTypes as $type)
                                    <option value="{{ $type->name }}">{{ $type->name }}</option>
                                @endforeach
                                <option value="Other">Other (Please specify)</option>
                            </select>
                            <input type="text" id="building_type_other" 
                                class="hidden w-full mt-3 px-4 py-3 rounded-xl border border-blue-200 bg-blue-50/30 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                placeholder="Specify building type...">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Building Count <span class="text-red-500">*</span></label>
                            <input type="number" name="building_count" id="building_count" required min="1" value="1"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                        </div>

                        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Length (L) <span class="text-slate-400 font-normal italic">(m)</span></label>
                                <input type="number" step="0.01" name="length" id="length"
                                    class="calc-trigger w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                    placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Breadth (B) <span class="text-slate-400 font-normal italic">(m)</span></label>
                                <input type="number" step="0.01" name="breadth" id="breadth"
                                    class="calc-trigger w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                    placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Area Covered (m²) <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" name="area_covered" id="area_covered" required
                                    class="calc-trigger w-full px-4 py-3 rounded-xl border border-blue-100 bg-blue-50/50 text-sm font-bold text-blue-700 shadow-inner"
                                    placeholder="0.00">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Rate of Cost (₦) <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" name="rate_of_cost" id="rate_of_cost" required
                                class="calc-trigger w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                placeholder="0.00">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Amount of Compensation</label>
                            <div class="relative">
                                <input type="number" step="0.01" name="compensation_amount" id="compensation_amount" required
                                    class="w-full pl-10 pr-4 py-3 rounded-xl border border-blue-200 bg-white font-bold text-blue-700 text-lg focus:ring-0 shadow-sm"
                                    placeholder="0.00">
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 text-blue-400 font-bold">₦</div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2.1: Structure Type -->
                    <div class="pt-6 mb-6">
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-4">Structure Type</label>
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                            @foreach($valuationItems->filter(fn($i) => in_array($i->name, ['Permanent', 'Semi-Permanent'])) as $item)
                            <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 hover:border-blue-200 hover:bg-blue-50/50 transition cursor-pointer group relative">
                                <input type="radio" name="structure_type" value="{{ $item->name }}" 
                                    class="structure-type-radio absolute top-3 right-3 w-4 h-4 text-blue-600 border-slate-300 focus:ring-blue-500">
                                <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-blue-100 group-hover:text-blue-600 transition">
                                    <i data-lucide="building" class="h-4 w-4"></i>
                                </div>
                                <span class="text-[11px] font-bold text-slate-600 group-hover:text-blue-700 leading-tight pr-5">{{ $item->name }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Compensated Items -->
                    <div class="pt-6">
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-4">Items Considered During Valuation</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($valuationItems->filter(fn($i) => !in_array($i->name, ['Permanent', 'Semi-Permanent'])) as $item)
                            @php
                                $icon = 'box';
                                $lower = strtolower($item->name);
                                if (str_contains($lower, 'building') || str_contains($lower, 'permanent') || str_contains($lower, 'warehouse')) $icon = 'building';
                                if (str_contains($lower, 'wall') || str_contains($lower, 'fence')) $icon = 'fence';
                                if (str_contains($lower, 'pavement') || str_contains($lower, 'court yard')) $icon = 'layers';
                                if (str_contains($lower, 'borehole') || str_contains($lower, 'well')) $icon = 'droplets';
                                if (str_contains($lower, 'soackaway') || str_contains($lower, 'pitlatrine')) $icon = 'trash-2';
                                if (str_contains($lower, 'reservoir') || str_contains($lower, 'pond')) $icon = 'container';
                                if (str_contains($lower, 'hut') || str_contains($lower, 'shed') || str_contains($lower, 'cage')) $icon = 'home';
                                if (str_contains($lower, 'nest') || str_contains($lower, 'animal') || str_contains($lower, 'fish')) $icon = 'ghost';
                                if (str_contains($lower, 'granary')) $icon = 'archive';
                                if (str_contains($lower, 'fuel') || str_contains($lower, 'pump')) $icon = 'fuel';
                                if (str_contains($lower, 'wire') || str_contains($lower, 'mesh')) $icon = 'grid';
                                if (str_contains($lower, 'dpc')) $icon = 'maximize';
                            @endphp
                            <div class="flex flex-col gap-2 p-3 rounded-xl border border-slate-100 hover:border-blue-200 hover:bg-blue-50/50 transition group">
                                <label class="flex items-center gap-3 cursor-pointer relative">
                                    <input type="checkbox" name="compensated_items_list[]" value="{{ $item->name }}" 
                                        class="item-checkbox absolute top-0 right-0 w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                                    <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-blue-100 group-hover:text-blue-600 transition">
                                        <i data-lucide="{{ $icon }}" class="h-4 w-4"></i>
                                    </div>
                                    <span class="text-[11px] font-bold text-slate-600 group-hover:text-blue-700 leading-tight pr-5">{{ $item->name }}</span>
                                </label>
                                <div class="item-amount-wrapper hidden mt-1">
                                    <div class="relative">
                                        <input type="number" step="0.01" class="item-amount-input w-full pl-7 pr-3 py-1.5 rounded-lg border border-slate-200 bg-white text-[11px] font-bold text-blue-600 focus:border-blue-400 outline-none transition" 
                                            placeholder="Amount" data-item-name="{{ $item->name }}">
                                        <div class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px] text-blue-400 font-bold">₦</div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        <input type="text" name="compensated_items_other" id="compensated_items_other"
                            class="hidden mt-4 w-full px-4 py-3 rounded-xl border border-blue-200 bg-blue-50/30 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                            placeholder="Specify other items (separate with commas)...">
                        <input type="hidden" name="compensated_items" id="compensated_items_val">
                    </div>
                </div>

                <!-- Section 3: Account & Payment Details -->
                <div class="pt-8 border-t border-slate-100">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-8 h-8 rounded-full bg-teal-50 flex items-center justify-center text-teal-600">
                            <i data-lucide="credit-card" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Account & Payment Details</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="relative">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Bank Name</label>
                            <div class="relative group">
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center" id="selected_bank_logo">
                                    <i data-lucide="building-2" class="h-4 w-4 text-slate-400"></i>
                                </div>
                                <input type="text" id="bank_search" autocomplete="off"
                                    class="w-full pl-12 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                    placeholder="Search Nigerian Bank...">
                                <input type="hidden" name="bank_name" id="bank_name_val">
                                
                                <div id="bank_dropdown" class="hidden absolute z-[60] left-0 right-0 mt-2 bg-white border border-slate-200 rounded-2xl shadow-2xl max-h-64 overflow-y-auto overflow-x-hidden">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Account Number <span class="text-red-500">*</span></label>
                            <input type="text" name="account_number" id="account_number" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-bold tracking-widest"
                                placeholder="e.g. 0123456789" maxlength="10">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Account Name <span class="text-red-500">*</span></label>
                            <input type="text" name="account_name" id="account_name" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium uppercase"
                                placeholder="Full Name as on Bank Account">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Phone Number <span class="text-red-500">*</span></label>
                            <input type="text" name="phone_number" id="phone_number" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                placeholder="e.g. 08012345678">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">National Identity Number (NIN)</label>
                            <input type="text" name="nin" id="nin"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                placeholder="e.g. 12345678901">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Remarks</label>
                            <input type="text" name="remarks" id="remarks"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                placeholder="Any additional notes...">
                        </div>
                    </div>
                </div>

                <!-- Section 4: Property Location -->
                <div class="pt-8 border-t border-slate-100">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600">
                            <i data-lucide="map-pin" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Property Location</h4>
                    </div>

                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Plot Number</label>
                                <input type="text" name="plot_no" id="plot_no"
                                    class="loc-trigger w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                    placeholder="e.g. Plot 12A">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">District <span class="text-slate-400 font-normal italic">(Multi-select)</span></label>
                                <select id="loc_district" multiple="multiple" class="loc-trigger vfc-select2 w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                    @foreach($districts as $district)
                                        <option value="{{ $district->name }}">{{ $district->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">LGA <span class="text-red-500">*</span> <span class="text-slate-400 font-normal italic">(Multi-select)</span></label>
                                <select id="loc_lga" required multiple="multiple" class="loc-trigger vfc-select2 w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                    @foreach($lgas as $lga)
                                        <option value="{{ $lga->LGAName }}">{{ $lga->LGAName }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">State <span class="text-red-500">*</span></label>
                            <select id="loc_state" required class="loc-trigger w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                <option value="">Select State</option>
                                @foreach($states as $state)
                                    <option value="{{ $state->StateName }}" {{ $state->StateName == 'Kano' ? 'selected' : '' }}>{{ $state->StateName }}</option>
                                @endforeach
                            </select>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Full Location Address <span class="text-red-500">*</span></label>
                            <textarea name="location" id="location" required rows="2"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                placeholder="Detailed location description..."></textarea>
                            <p class="text-[10px] text-slate-400 mt-1 italic">Automatically built from fields above but can be edited manually.</p>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Footer -->
        <div class="px-8 py-6 border-t border-slate-100 flex items-center justify-end gap-3 bg-white shrink-0 shadow-inner">
            <button type="button" class="close-modal px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition">
                Cancel
            </button>
            <button type="submit" form="valuation-form" id="submit-btn"
                class="px-8 py-3 rounded-xl bg-blue-600 text-white font-bold shadow-lg shadow-blue-100 hover:bg-blue-700 transition flex items-center gap-2">
                <span>Submit</span>
                <i data-lucide="send" class="h-4 w-4"></i>
            </button>
        </div>
    </div>
</div>
