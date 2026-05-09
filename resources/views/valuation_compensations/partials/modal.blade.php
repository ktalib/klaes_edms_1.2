<!-- Valuation for Compensation Modal -->
<div id="valuation-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <!-- Background overlay -->
    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" aria-hidden="true" id="modal-overlay"></div>

    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden border border-slate-100 transform transition-all">
        <!-- Header -->
        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
            <div>
                <h3 class="text-xl font-bold text-slate-900" id="modal-title">COMPENSATION for VALUATION (CFV) Data entry</h3>
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

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Select Project <span class="text-red-500">*</span></label>
                            <select name="project_id" id="vfc_project_id" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                <option value="">Select Project</option>
                            </select>
                            <div id="project-info" class="hidden mt-2 p-3 rounded-lg bg-blue-50 border border-blue-100">
                                <p class="text-[10px] font-bold text-blue-700 uppercase tracking-wider">Project Summary</p>
                                <div class="grid grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 mt-4">
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-2 mb-1">
                                            <i data-lucide="hash" class="h-3.5 w-3.5 text-blue-400"></i>
                                            <span class="text-[10px] text-blue-600 font-bold uppercase tracking-wider">Project ID</span>
                                        </div>
                                        <span id="proj_id_summary" class="text-sm font-bold text-blue-900">-</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-2 mb-1">
                                            <i data-lucide="file-text" class="h-3.5 w-3.5 text-blue-400"></i>
                                            <span class="text-[10px] text-blue-600 font-bold uppercase tracking-wider">Project FileNo</span>
                                        </div>
                                        <span id="proj_fileno_summary" class="text-sm font-bold text-blue-900">-</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-2 mb-1">
                                            <i data-lucide="tag" class="h-3.5 w-3.5 text-blue-400"></i>
                                            <span class="text-[10px] text-blue-600 font-bold uppercase tracking-wider">Project Code</span>
                                        </div>
                                        <span id="proj_code_summary" class="text-sm font-bold text-blue-900">-</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-2 mb-1">
                                            <i data-lucide="layers" class="h-3.5 w-3.5 text-blue-400"></i>
                                            <span class="text-[10px] text-blue-600 font-bold uppercase tracking-wider">Template Rows</span>
                                        </div>
                                        <span id="proj_total" class="text-sm font-bold text-blue-900">0</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-2 mb-1">
                                            <i data-lucide="users" class="h-3.5 w-3.5 text-blue-400"></i>
                                            <span class="text-[10px] text-blue-600 font-bold uppercase tracking-wider">Total Workers</span>
                                        </div>
                                        <span id="proj_workers_summary" class="text-sm font-bold text-blue-900">0</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-2 mb-1">
                                            <i data-lucide="clipboard-check" class="h-3.5 w-3.5 text-blue-400"></i>
                                            <span class="text-[10px] text-blue-600 font-bold uppercase tracking-wider">Form Filled</span>
                                        </div>
                                        <span id="proj_rem" class="text-sm font-bold text-blue-900">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Assigned Worker <span class="text-red-500">*</span></label>
                            <select name="worker_id" id="vfc_worker_id" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                <option value="">Select Worker (Select Project First)</option>
                            </select>
                            <p class="text-[10px] text-slate-400 mt-1 italic">The Worker ID will be automatically recorded.</p>
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

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Area Covered (m²) <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" name="area_covered" id="area_covered" required
                                    class="calc-trigger w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                    placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Rate of Cost (₦) <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" name="rate_of_cost" id="rate_of_cost" required
                                    class="calc-trigger w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                    placeholder="0.00">
                            </div>
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

                    <!-- Compensated Items -->
                    <div class="pt-6">
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-4">Items Considered During Valuation</label>
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                            @foreach($valuationItems as $item)
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
                            <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 hover:border-blue-200 hover:bg-blue-50/50 transition cursor-pointer group relative">
                                <input type="checkbox" name="compensated_items_list[]" value="{{ $item->name }}" 
                                    class="item-checkbox absolute top-3 right-3 w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                                <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-blue-100 group-hover:text-blue-600 transition">
                                    <i data-lucide="{{ $icon }}" class="h-4 w-4"></i>
                                </div>
                                <span class="text-[11px] font-bold text-slate-600 group-hover:text-blue-700 leading-tight pr-5">{{ $item->name }}</span>
                            </label>
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
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Plot Number</label>
                                <input type="text" name="plot_no" id="plot_no"
                                    class="loc-trigger w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                    placeholder="e.g. Plot 12A">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Street Name</label>
                                <select name="street_name" id="street_name" data-id="street_name" class="loc-trigger w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                    <option value="">Select Street</option>
                                    @foreach($streets as $street)
                                        <option value="{{ $street->name }}">{{ $street->name }}</option>
                                    @endforeach
                                    <option value="Other">Other</option>
                                </select>
                                <input type="text" id="street_name_other" class="loc-trigger hidden mt-3 w-full px-4 py-3 rounded-xl border border-blue-200 bg-blue-50/30 text-sm font-medium" placeholder="Type street name...">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">District</label>
                                <select id="loc_district" class="loc-trigger w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                    <option value="">Select District</option>
                                    @foreach($districts as $district)
                                        <option value="{{ $district->name }}">{{ $district->name }}</option>
                                    @endforeach
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">LGA <span class="text-red-500">*</span></label>
                                <select id="loc_lga" required class="loc-trigger w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                    <option value="">Select LGA</option>
                                    @foreach($lgas as $lga)
                                        <option value="{{ $lga->LGAName }}">{{ $lga->LGAName }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">State <span class="text-red-500">*</span></label>
                                <select id="loc_state" required class="loc-trigger w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                    <option value="">Select State</option>
                                    @foreach($states as $state)
                                        <option value="{{ $state->StateName }}" {{ $state->StateName == 'Kano' ? 'selected' : '' }}>{{ $state->StateName }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        
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
