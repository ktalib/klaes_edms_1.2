@extends('layouts.app')

@section('page-title')
    {{ isset($record) ? __('Update File Index') : __('Create File Index') }}
@endsection

@section('content')
    <link rel="stylesheet" href="{{ asset('css/fileindexing/create-indexing-standalone.css') }}">
        <link rel="stylesheet" href="{{ asset('css/fileindexing/extra.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">

    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">

 
            <div class="form-shell">
                <!-- Standalone form content without modal wrapper -->
                <div class="bg-white rounded-lg shadow-lg p-8 max-w-6xl mx-auto">
                    @if(!empty($backButton))
                        <div class="mb-5">
                            <a href="{{ $backButton['route'] }}"
                                class="inline-flex items-center gap-2 rounded-md border border-blue-300 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 transition-colors hover:border-blue-400 hover:bg-blue-100 hover:text-blue-900">
                                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                                <span>{{ $backButton['label'] }}</span>
                            </a>
                        </div>
                    @endif
                    <div class="mb-6">
                        <div class="flex items-center gap-3 mb-2">
                            <i data-lucide="{{ isset($record) ? 'edit-3' : 'file-plus' }}"
                                class="h-5 w-5 text-blue-600"></i>
                            <h2 class="text-xl font-semibold text-gray-800">
                                {{ isset($record) ? 'Update File Index' : 'Create New File Index' }}
                            </h2>
                        </div>
                        <p class="text-gray-600">
                            {{ isset($record) ? 'Update the file indexing details below' : 'Enter the details for the new file to be indexed' }}
                        </p>
                        @if(isset($record))
                            <div class="mt-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded-md">
                                <p class="text-sm text-blue-800">
                                    <span class="font-semibold">Editing:</span> {{ $record->file_number ?? 'Unknown' }} -
                                    {{ $record->file_title ?? 'No Title' }}
                                </p>
                            </div>
                        @endif
                    </div>

                    <form id="new-file-form" data-default-indexer="{{ Auth::user()->name ?? 'Current User' }}"
                        data-check-indexed-url="{{ route('fileindex.check-indexed') }}"
                        data-indexed-view-url-template="{{ route('fileindexing.show', ['id' => '__ID__']) }}"
                        data-transactions-url-template="{{ route('fileindexing.transactions', ['fileIndexing' => '__ID__']) }}"
                        @if(isset($record)) data-editing-id="{{ $record->id }}"
                        data-action-url="{{ route('fileindexing.update', $record->id) }}" data-method="PUT" @else
                        data-action-url="{{ route('fileindexing.store') }}" data-method="POST" @endif
                        x-data="indexingForm()"
                        @grouping-record-updated.window="onGroupingRecordUpdated($event.detail)">
                        @csrf
                        @if(isset($record))
                            @method('PUT')
                            <input type="hidden" name="record_id" value="{{ $record->id }}">
                        @endif
                        <input type="hidden" value="PRO1.2" name="test_control">
                        <input type="hidden" id="prop-id-field" name="prop_id" value="">

                        @if(isset($record))
                            <!-- Pre-populate form with existing data -->
                            <script>
                                window.editingRecord = @json($record);
                                window.isEditMode = true;
                                window.isNewKnMode = false;
                                window.prefillFileNumber = '';
                                window.returnToUrl = '';
                            </script>
                        @else
                            <script>
                                window.editingRecord = null;
                                window.isEditMode = false;
                                window.isNewKnMode = {{ ($isNewKnMode ?? false) ? 'true' : 'false' }};
                                window.prefillFileNumber = @json($prefillFileNumber ?? '');
                                window.prefillTrackingId = @json($prefillTrackingId ?? '');
                                window.returnToUrl = @json($returnTo ?? '');
                            </script>
                        @endif

                        <!-- Basic Information Cards (2x2 Grid) -->
                        <div class="form-grid-2">
                            <!-- Indexing Type Card -->
                            <div class="rounded-lg border border-gray-200 bg-white p-6">
                                <h4 class="mb-6 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                                    <i data-lucide="layers" class="h-4 w-4 text-indigo-600"></i>
                                    Indexing Type
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="form-group">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Indexing Type <span class="text-red-500">*</span></label>
                                        <select id="indexing-type" name="indexing_type"
                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            x-model="indexing_type"
                                            required>
                                            <option value="Regular" {{ (isset($record) && $record->indexing_type == 'Regular') ? 'selected' : '' }}>Regular Indexing</option>
                                            <option value="Block" {{ (isset($record) && $record->indexing_type == 'Block') ? 'selected' : '' }}>Block Indexing</option>
                                        </select>
                                    </div>

                                    {{-- Title Status (optional) — mirrors the standalone Title Status module --}}
                                    <div class="form-group">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Title Status <span class="text-red-500">*</span></label>
                                        <select id="ts-title-type" required
                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                            <option value="" selected disabled>Select Title Status</option>
                                            <option value="Normal">Normal</option>
                                            <option value="Withdrawal (Application)">Withdrawal (Application)</option>
                                            <option value="Cancellation (RofO)">Cancellation (RofO)</option>
                                            <option value="Revoke (CofO)">Revocation (CofO)</option>
                                            <option value="Litigation">Litigation</option>
                                            <option value="Amendment/Reconsideration (Application/RofO/CofO)">Amendment / Reconsideration</option>
                                            <option value="Surrender">Surrender</option>
                                            <option value="Re-grant">Re-grant</option>
                                            <option value="Subdivision">Subdivision</option>
                                            <option value="Merger">Merger</option>
                                            <option value="Change of Purpose">Change of Purpose</option>
                                            <option value="Extension">Extension</option>
                                            <option value="Change of Name">Change of Name</option>
                                            <option value="Separation">Separation</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- General Registry--> 
                            <div class="rounded-lg border border-gray-200 bg-white p-6">
                                <h4 class="mb-6 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                                    <i data-lucide="building-2" class="h-4 w-4 text-purple-600"></i>
                                    General Registry
                                </h4>
                                <div class="form-group">
                                    <label for="general-registry" class="block text-sm font-medium text-gray-700 mb-2">General Registry <span class="text-red-500">*</span></label>
                                    <select id="general-registry"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        name="general_registry" required>
                                        <option value="">Select Registry</option>
                                        @if(isset($registries))
                                            @foreach($registries as $registry)
                                                <option value="{{ $registry->name }}" {{ (isset($record) && $record->general_registry == $registry->name) ? 'selected' : '' }}>
                                                    {{ $registry->name }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>

                                <!-- Reason for Investigation (DCIV) - Conditional Display -->
                                <div class="form-group mt-6 hidden" id="dciv-reason-container">
                                    <label for="dciv-reason" class="block text-sm font-medium text-gray-700 mb-2">
                                        <i data-lucide="alert-circle" class="inline h-4 w-4 text-red-600"></i>
                                        Reason for Investigation
                                    </label>
                                    <input type="text" id="dciv-reason" name="dciv_reason"
                                        class="block w-full px-3 py-2 border border-red-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm bg-red-50"
                                        placeholder="Enter reason for investigation" value="{{ isset($record) ? $record->dciv_reason : '' }}">
                                </div>
                            </div>
                        </div>

                        {{-- Title Status details (shown when a Title Status is selected) --}}
                        <div id="ts-status-fields" class="hidden mt-6 rounded-lg border border-teal-200 bg-teal-50/40 p-6 space-y-5">
                            <h4 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-teal-700">
                                <i data-lucide="badge-check" class="h-4 w-4 text-teal-600"></i>
                                Title Status Details
                            </h4>

                            {{-- See (Additional File No.) — Re-grant only --}}
                            <div id="ts-see-row" class="form-group hidden">
                                <input type="hidden" id="ts-see-fileno">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Initial FileNo</label>
                                <div class="flex items-stretch gap-2">
                                    <input type="text" id="ts-see-fileno-display" readonly
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 sm:text-sm"
                                        placeholder="Click Select to choose the additional file number...">
                                    <button type="button" id="ts-see-select-btn"
                                        class="px-4 py-2 rounded-md bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700 transition">Select</button>
                                    <button type="button" id="ts-see-clear-btn"
                                        class="px-3 py-2 rounded-md border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition">Clear</button>
                                </div>
                                <p class="text-[11px] text-gray-400 mt-1">The related file number written as "See ..." on the physical file.</p>
                            </div>

                            {{-- Reason --}}
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Reason</label>
                                <textarea id="ts-reason" rows="2"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-500 focus:border-teal-500 sm:text-sm"
                                    placeholder="Reason for this title status action..."></textarea>
                            </div>

                            {{-- Remark / Comment --}}
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Remark / Comment</label>
                                <textarea id="ts-remark" rows="3"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-500 focus:border-teal-500 sm:text-sm"></textarea>
                            </div>
                        </div>



                        @include('fileindexing.addons.partials.sections.file_identification')

                        <hr class="my-8 border-t border-gray-200">

                        <!-- Personal & Property Details Cards (2x2 Grid) -->
                        <div class="form-grid-2">
                            @include('fileindexing.addons.partials.sections.contact_information')
                            @include('fileindexing.addons.partials.sections.property_details')
                        </div>



                        <hr class="my-8 border-t border-gray-200">

                        @include('fileindexing.addons.partials.sections.file_flags')

                        <hr class="my-8 border-t border-gray-200">

                        @include('fileindexing.addons.partials.sections.file_archive_details')
                        <hr class="my-8 border-t border-gray-200">

                        @include('fileindexing.addons.partials.sections.entity_customer')

                        <hr class="my-8 border-t border-gray-200">

                        @include('fileindexing.addons.partials.sections.rofo_details')

                        <hr class="my-8 border-t border-gray-200">

                        @include('fileindexing.addons.partials.sections.cofo_details')

                        <hr class="my-8 border-t border-gray-200">

                        @include('fileindexing.addons.partials.sections.file_history')
                        @include('fileindexing.addons.partials.sections.related_fileno_details')
                        @include('fileindexing.addons.partials.sections.form_actions')
                    </form>
                </div>
            </div>
        </div>
    </div>

    @include('components.global-fileno-modal')
    @include('fileindexing.partial.property_transaction_modal')

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    <script src="{{ asset('js/fileindexing/create-indexing-dialog.js') }}?v={{ @filemtime(public_path('js/fileindexing/create-indexing-dialog.js')) }}"></script>

    <script>
        // Phone number validation
        (function () {
            const phoneInput = document.getElementById('phone');
            const phoneError = document.getElementById('phone-error');

            if (!phoneInput) return;

            function validateNigerianPhone() {
                const phoneNumber = phoneInput.value.trim();

                if (phoneNumber) {
                    // Nigerian phone numbers should be 11 digits starting with 0
                    if (!/^0\d{10}$/.test(phoneNumber)) {
                        phoneError.textContent = 'Nigerian phone numbers must be 11 digits starting with 0 (e.g., 08012345678)';
                        phoneError.classList.remove('hidden');
                        phoneInput.classList.add('border-red-500');
                        return false;
                    } else {
                        phoneError.classList.add('hidden');
                        phoneInput.classList.remove('border-red-500');
                        return true;
                    }
                } else {
                    phoneError.classList.add('hidden');
                    phoneInput.classList.remove('border-red-500');
                    return true;
                }
            }

            // Only allow numeric input
            phoneInput.addEventListener('input', function (e) {
                this.value = this.value.replace(/[^0-9]/g, '');
                validateNigerianPhone();
            });

            phoneInput.addEventListener('blur', validateNigerianPhone);

            // Validate before form submission
            const form = document.getElementById('new-file-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    // File Title required validation (covers one or more file_title[] inputs)
                    const titleInputs = form.querySelectorAll('input[name="file_title[]"]');
                    let firstEmptyTitle = null;
                    titleInputs.forEach(input => {
                        if (!firstEmptyTitle && !input.value.trim()) {
                            firstEmptyTitle = input;
                        }
                    });
                    if (firstEmptyTitle) {
                        e.preventDefault();
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'File Title Required',
                                text: 'Please enter a File Title before submitting.',
                                confirmButtonColor: '#f59e0b'
                            }).then(() => {
                                firstEmptyTitle.focus();
                                firstEmptyTitle.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            });
                        } else {
                            firstEmptyTitle.focus();
                            firstEmptyTitle.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        return;
                    }

                    if (!validateNigerianPhone() && phoneInput.value.trim()) {
                        e.preventDefault();
                        phoneInput.focus();
                        phoneInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            }
        })();

        // File Type and RC NO conditional display
        (function () {
            const fileTypeSelect = document.getElementById('file-type');
            const rcNoGroup = document.getElementById('rc-no-group');
            const rcNoInput = document.getElementById('rc-no');

            if (!fileTypeSelect || !rcNoGroup) return;

            function toggleRcNo() {
                if (fileTypeSelect.value === 'Corporate') {
                    rcNoGroup.style.display = 'block';
                } else {
                    rcNoGroup.style.display = 'none';
                    if (rcNoInput) rcNoInput.value = '';
                }
            }

            fileTypeSelect.addEventListener('change', toggleRcNo);
            toggleRcNo(); // Initial check
        })();

        // NIN validation (13 digits)
        (function () {
            const ninInput = document.getElementById('nin');
            if (!ninInput) return;

            ninInput.addEventListener('input', function (e) {
                this.value = this.value.replace(/[^0-9]/g, '').substring(0, 13);
            });
        })();
    </script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('indexingForm', () => ({
                fileParams: [
                    { 
                        id: Date.now(), 
                        title: '', 
                        applicant_name: '',
                        plot_number: '', 
                        district: '', 
                        custom_district: '', 
                        lga: '', 
                        location: '', 
                        land_use_type: '', 
                        tp_number: '', 
                        lpkn_no: '', 
                        plot_size: '', 
                        customer_name: '', 
                        entity_name: '',
                        street_name: '',
                        custom_street_name: ''
                    }
                ],
                activeTab: 0,
                indexing_type: '{{ isset($record) ? $record->indexing_type : 'Regular' }}',
                applyPropertyToAll: false,
                applyPersonalToAll: false,
                applyEntityToAll: false,

                addFileParam() {
                    this.fileParams.push({
                        id: Date.now() + Math.random(), 
                        title: '',
                        applicant_name: '',
                        plot_number: '',
                        district: '',
                        custom_district: '',
                        lga: '',
                        location: '',
                        land_use_type: '',
                        tp_number: '',
                        lpkn_no: '',
                        plot_size: '',
                        customer_name: '',
                        entity_name: '',
                        street_name: '',
                        custom_street_name: ''
                    });
                },

                removeFileParam(index) {
                    if (this.fileParams.length > 1) {
                        this.fileParams.splice(index, 1);
                        if (this.activeTab >= this.fileParams.length) {
                            this.activeTab = this.fileParams.length - 1;
                        }
                    }
                },

                applyPropertyDetailsToAllFiles() {
                    if (this.indexing_type !== 'Block' || this.fileParams.length < 2) return;
                    
                    const current = this.fileParams[this.activeTab];
                    const data = {
                        land_use_type: current.land_use_type,
                        plot_number: current.plot_number,
                        tp_number: current.tp_number,
                        lpkn_no: current.lpkn_no,
                        district: current.district,
                        custom_district: current.custom_district,
                        street_name: current.street_name,
                        custom_street_name: current.custom_street_name,
                        lga: current.lga,
                        location: current.location,
                        plot_size: current.plot_size
                    };

                    this.fileParams.forEach((param, idx) => {
                        Object.assign(param, data);
                    });

                    Swal.fire({
                        icon: 'success',
                        title: 'Property Details Applied',
                        text: `Property details applied to all ${this.fileParams.length} files`,
                        timer: 1500,
                        showConfirmButton: false
                    });
                },

                applyPersonalDetailsToAllFiles() {
                    if (this.indexing_type !== 'Block' || this.fileParams.length < 2) return;

                    const current = this.fileParams[this.activeTab];
                    const data = {
                        dob: current.dob,
                        nin: current.nin,
                        tin: current.tin,
                        rc_no: current.rc_no,
                        phone: current.phone,
                        residence_address: current.residence_address,
                        customer_type: current.customer_type
                    };

                    this.fileParams.forEach((param, idx) => {
                        Object.assign(param, data);
                    });

                    Swal.fire({
                        icon: 'success',
                        title: 'Personal Details Applied',
                        text: `Personal details applied to all ${this.fileParams.length} files`,
                        timer: 1500,
                        showConfirmButton: false
                    });
                },

                applyEntityDetailsToAllFiles() {
                    if (this.indexing_type !== 'Block' || this.fileParams.length < 2) return;

                    const current = this.fileParams[this.activeTab];
                    const data = {
                        entity_type: current.entity_type,
                        entity_name: current.entity_name,
                        entity_physical_address: current.entity_physical_address,
                        customer_type: current.customer_type,
                        customer_name: current.customer_name,
                        customer_account_no: current.customer_account_no,
                        customer_code: current.customer_code,
                        property_address: current.property_address
                    };

                    this.fileParams.forEach((param, idx) => {
                        Object.assign(param, data);
                    });

                    Swal.fire({
                        icon: 'success',
                        title: 'Entity & Customer Details Applied',
                        text: `Entity & Customer details applied to all ${this.fileParams.length} files`,
                        timer: 1500,
                        showConfirmButton: false
                    });
                },

                onGroupingRecordUpdated(record) {
                    if (!record) return;
                    
                    if (this.fileParams.length > 0) {
                        const param = this.fileParams[0];
                        
                        // File Title & Applicant Name
                        param.applicant_name = record.applicant_name || record.file_title || record.title || record.comments || param.applicant_name || '';
                        param.title = param.applicant_name;

                        // Property Details
                        param.plot_number = record.plot_no || record.plot_number || param.plot_number || '';
                        param.tp_number = record.tp_number || param.tp_number || '';
                        param.lpkn_no = record.lpkn_no || param.lpkn_no || '';
                        param.land_use_type = record.land_use || record.land_use_type || record.LandUseType || param.land_use_type || '';
                        param.plot_size = record.plot_size || record.PlotSize || param.plot_size || '';

                        // Handle District & Custom District
                        if (record.district || record.District) {
                            const districtVal = record.district || record.District;
                            const districtSelect = document.getElementById('district-select-0'); 
                            
                            // Note: We might need to access reference data store here, but for now relies on DOM
                            param.district = districtVal;
                        }
                        
                        param.lga = record.lga || record.lgsaOrCity || record.LGA || param.lga || '';

                        if (record.location || record.property_location || record.Location) {
                            param.location = record.location || record.property_location || record.Location;
                        }

                        // Entity & Customer Details - Try multiple aliases
                        param.entity_type = record.entity_type || record.EntityType || param.entity_type || 'Corporate';
                        param.entity_name = record.Grantor || record.first_party || record.entity_name || record.EntityName || param.entity_name || '';
                        param.entity_physical_address = record.entity_physical_address || record.EntityPhysicalAddress || param.entity_physical_address || '';
                        
                        param.customer_type = record.customer_type || record.CustomerType || param.customer_type || 'Individual';
                        param.customer_name = record.Grantee || record.second_party || record.customer_name || record.CustomerName || param.customer_name || '';
                        param.customer_account_no = record.customer_account_no || record.CustomerAccountNo || param.customer_account_no || '';
                        param.customer_code = record.customer_code || record.CustomerCode || param.customer_code || '';
                        param.property_address = record.property_address || record.PropertyAddress || param.property_address || '';

                        // Personal Details - Try multiple aliases
                        param.dob = record.dob || record.DOB || param.dob || '';
                        param.nin = record.nin || record.NIN || record.NationalID || param.nin || '';
                        param.tin = record.tin || record.TIN || record.TaxID || param.tin || '';
                        param.rc_no = record.rc_no || record.RCNo || record.RC_NO || param.rc_no || '';
                        param.phone = record.phone || record.Phone || record.PhoneNumber || param.phone || '';
                        param.email = record.email || record.Email || param.email || '';
                        param.residence_address = record.residence_address || record.ResidenceAddress || record.Address || param.residence_address || '';

                        // Handle General Registry (Top-level field)
                        const genRegistrySelect = document.getElementById('general-registry');
                        if (genRegistrySelect) {
                            const registryVal = record.general_registry || record.gen_reg || record.registry || '';
                            if (registryVal) {
                                let matched = false;
                                for (let i = 0; i < genRegistrySelect.options.length; i++) {
                                    if (genRegistrySelect.options[i].value === registryVal || genRegistrySelect.options[i].text === registryVal) {
                                        genRegistrySelect.selectedIndex = i;
                                        matched = true;
                                        break;
                                    }
                                }
                                if (!matched && registryVal) {
                                     // Value not found in dropdown options — leave dropdown unchanged
                                }
                                genRegistrySelect.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        }
                    }
                },

                // Sync File Title to Current Holder in Regular Indexing mode
                init() {
                    const record = window.editingRecord;
                    if (record) {
                        // If the record has file_titles array (from previous multi-file save), use it
                        if (Array.isArray(record.file_titles) && record.file_titles.length > 0) {
                             this.fileParams = record.file_titles.map((title, index) => ({
                                 id: Date.now() + Math.random() + index,
                                 title: title,
                             }));
                        } else if (record.file_title) {
                            // Legacy single record or first time editing a single record
                            this.fileParams = [{
                                id: Date.now(),
                                title: record.file_title,
                                applicant_name: record.applicant_name || record.file_title,
                                
                                // Entity & Customer Details
                                entity_type: record.entity_type,
                                entity_name: record.entity_name,
                                entity_physical_address: record.entity_physical_address,
                                customer_type: record.customer_type,
                                customer_name: record.customer_name,
                                customer_account_no: record.customer_account_no,
                                customer_code: record.customer_code,
                                property_address: record.property_address,
                                
                                // Personal Details
                                dob: record.dob,
                                nin: record.nin,
                                tin: record.tin,
                                rc_no: record.rc_no,
                                phone: record.phone,
                                residence_address: record.residence_address,

                                // Property Details
                                land_use_type: record.land_use_type,
                                plot_number: record.plot_number,
                                tp_number: record.tp_number,
                                lpkn_no: record.lpkn_no,
                                district: record.district,
                                street_name: record.street_name,
                                custom_street_name: record.street_name === 'Other' || record.street_name === 'other' ? record.custom_street_name : '',
                                lga: record.lga || record.lgsaOrCity,
                                location: record.location,
                                plot_size: record.plot_size
                            }];
                        }
                    }

                    // Watchers for title sync and batch apply
                    this.$watch('fileParams', (params, oldParams) => {
                        // Sync title to applicant_name (instead of the reverse which was locking the field)
                        params.forEach(param => {
                            if (param.title !== param.applicant_name) {
                                param.applicant_name = param.title;
                            }
                        });

                        // Real-time batch apply for Property Details
                        if (this.indexing_type === 'Block' && this.applyPropertyToAll && params.length > 1) {
                            const current = params[this.activeTab];
                            const propFields = ['land_use_type', 'plot_number', 'tp_number', 'lpkn_no', 'district', 'custom_district', 'street_name', 'custom_street_name', 'lga', 'location', 'plot_size'];
                            
                            params.forEach((param, idx) => {
                                if (idx !== this.activeTab) {
                                    propFields.forEach(field => {
                                        if (param[field] !== current[field]) {
                                            param[field] = current[field];
                                        }
                                    });
                                }
                            });
                        }

                        // Real-time batch apply for Personal Details
                        if (this.indexing_type === 'Block' && this.applyPersonalToAll && params.length > 1) {
                            const current = params[this.activeTab];
                            const personalFields = ['dob', 'nin', 'tin', 'rc_no', 'phone', 'residence_address', 'customer_type'];
                            
                            params.forEach((param, idx) => {
                                if (idx !== this.activeTab) {
                                    personalFields.forEach(field => {
                                        if (param[field] !== current[field]) {
                                            param[field] = current[field];
                                        }
                                    });
                                }
                            });
                        }

                        // Real-time batch apply for Entity & Customer Details
                        if (this.indexing_type === 'Block' && this.applyEntityToAll && params.length > 1) {
                            const current = params[this.activeTab];
                            const entityFields = ['entity_type', 'entity_name', 'entity_physical_address', 'customer_type', 'customer_name', 'customer_account_no', 'customer_code', 'property_address'];
                            
                            params.forEach((param, idx) => {
                                if (idx !== this.activeTab) {
                                    entityFields.forEach(field => {
                                        if (param[field] !== current[field]) {
                                            param[field] = current[field];
                                        }
                                    });
                                }
                            });
                        }

                        this.$nextTick(() => {
                            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                                window.lucide.createIcons();
                            }
                        });

                        if (this.indexing_type === 'Regular' && params.length > 0) {
                            const firstTitle = params[0].title;
                            const holderInput = document.getElementById('current-holder');
                            if (holderInput) {
                                holderInput.value = firstTitle;
                                holderInput.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                        } else if (this.indexing_type === 'Block' && params.length > 0) {
                            const wrapper = document.getElementById('current-holders-wrapper');
                            if (wrapper) {
                                let rows = wrapper.querySelectorAll('.current-holder-row');
                                
                                // Remove excess rows
                                while (rows.length > params.length) {
                                    wrapper.removeChild(rows[rows.length - 1]);
                                    rows = wrapper.querySelectorAll('.current-holder-row');
                                }
                                
                                // Add missing rows
                                while (rows.length < params.length) {
                                    const newRow = document.createElement('div');
                                    newRow.className = 'current-holder-row flex gap-2';
                                    
                                    const input = document.createElement('input');
                                    input.type = 'text';
                                    input.name = 'current_holder[]';
                                    input.className = 'current-holder-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm';
                                    
                                    const btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'remove-holder-btn inline-flex items-center px-3 py-2 border border-red-600 text-red-600 rounded-md hover:bg-red-50';
                                    btn.innerHTML = '<i data-lucide="trash-2" class="h-4 w-4 text-red-600"></i>';
                                    btn.onclick = function() {
                                        // Manual remove if needed
                                        newRow.remove();
                                    };
                                    
                                    newRow.appendChild(input);
                                    newRow.appendChild(btn);
                                    wrapper.appendChild(newRow);
                                    
                                    if (window.lucide && typeof window.lucide.createIcons === 'function') {
                                        window.lucide.createIcons();
                                    }
                                    rows = wrapper.querySelectorAll('.current-holder-row');
                                }
                                
                                // Update values
                                params.forEach((param, index) => {
                                    if(rows[index]) {
                                        const input = rows[index].querySelector('input.current-holder-input');
                                        if (input && input.value !== param.title) {
                                            input.value = param.title;
                                            input.dispatchEvent(new Event('input', { bubbles: true }));
                                        }
                                    }
                                });
                            }
                        }
                    }, { deep: true });

                    this.$watch('indexing_type', (type) => {
                        if (type === 'Regular' && this.fileParams.length > 0) {
                            const firstTitle = this.fileParams[0].title;
                            const holderInput = document.getElementById('current-holder');
                            if (holderInput) {
                                holderInput.value = firstTitle;
                                holderInput.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                        } else if (type === 'Block' && this.fileParams.length > 0) {
                            const params = this.fileParams;
                            const wrapper = document.getElementById('current-holders-wrapper');
                            if (wrapper) {
                                let rows = wrapper.querySelectorAll('.current-holder-row');
                                
                                // Remove excess rows
                                while (rows.length > params.length) {
                                    wrapper.removeChild(rows[rows.length - 1]);
                                    rows = wrapper.querySelectorAll('.current-holder-row');
                                }
                                
                                // Add missing rows
                                while (rows.length < params.length) {
                                    const newRow = document.createElement('div');
                                    newRow.className = 'current-holder-row flex gap-2';
                                    
                                    const input = document.createElement('input');
                                    input.type = 'text';
                                    input.name = 'current_holder[]';
                                    input.className = 'current-holder-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm';
                                    
                                    const btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'remove-holder-btn inline-flex items-center px-3 py-2 border border-red-600 text-red-600 rounded-md hover:bg-red-50';
                                    btn.innerHTML = '<i data-lucide="trash-2" class="h-4 w-4 text-red-600"></i>';
                                    btn.onclick = function() {
                                        newRow.remove();
                                    };
                                    
                                    newRow.appendChild(input);
                                    newRow.appendChild(btn);
                                    wrapper.appendChild(newRow);
                                    
                                    if (window.lucide && typeof window.lucide.createIcons === 'function') {
                                        window.lucide.createIcons();
                                    }
                                    rows = wrapper.querySelectorAll('.current-holder-row');
                                }
                                
                                // Update values
                                params.forEach((param, index) => {
                                    if(rows[index]) {
                                        const input = rows[index].querySelector('input.current-holder-input');
                                        if (input && input.value !== param.title) {
                                            input.value = param.title;
                                            input.dispatchEvent(new Event('input', { bubbles: true }));
                                        }
                                    }
                                });
                            }
                        }
                    });
                }

            }));
        });
    </script>

    {{-- Title Status integration (reuses the standalone title-status backend) --}}
    <script>
        (function () {
            const TS_STORE_URL = '{{ route('title-status.store') }}';
            const TS_INITIATED_BY_BY_TYPE = @json(\App\Models\TitleStatusApplication::INITIATED_BY_BY_TYPE);
            const TS_TYPE_VERB = {
                'Withdrawal (Application)':                            'Withdrawal',
                'Cancellation (RofO)':                                'Cancellation',
                'Revoke (CofO)':                                      'Revocation',
                'Litigation':                                         'Litigation',
                'Amendment/Reconsideration (Application/RofO/CofO)':  'Amendment/Reconsideration',
                'Surrender':                                          'Surrender',
                'Re-grant':                                           'Re-grant',
                'Subdivision':                                        'Subdivision',
                'Merger':                                             'Merger',
                'Change of Purpose':                                  'Change of Purpose',
                'Extension':                                          'Extension',
                'Change of Name':                                     'Change of Name',
                'Separation':                                         'Separation',
            };

            const typeSelect   = document.getElementById('ts-title-type');
            const statusBlock  = document.getElementById('ts-status-fields');
            const seeRow       = document.getElementById('ts-see-row');
            const seeHidden    = document.getElementById('ts-see-fileno');
            const seeDisplay   = document.getElementById('ts-see-fileno-display');
            const reasonEl     = document.getElementById('ts-reason');
            const remarkEl     = document.getElementById('ts-remark');

            if (!typeSelect) return;

            const getFileNo = () =>
                ((document.getElementById('fileno')?.value
                    || document.getElementById('file-number-display')?.value
                    || '').trim()).replace(/\(\s*T\s*\)\s*$/i, '').trim();

            const initiatedByFor = (type) => {
                const opts = TS_INITIATED_BY_BY_TYPE[type] || ['Ministry'];
                return opts[0] || 'Ministry';
            };

            // Treat empty default and "Normal" as "no title status action".
            const isActionableType = (t) => t !== '' && t !== 'Normal';

            function buildRemark() {
                const type   = typeSelect.value;
                if (!isActionableType(type)) return '';
                const verb   = TS_TYPE_VERB[type] || type;
                const fileNo = getFileNo();
                const reason = (reasonEl?.value || '').trim() || '[Reason]';
                const initiator = initiatedByFor(type);
                const now = new Date();
                const pad = n => String(n).padStart(2, '0');
                const dt  = `${pad(now.getDate())}/${pad(now.getMonth() + 1)}/${now.getFullYear()} ${pad(now.getHours())}:${pad(now.getMinutes())}`;
                const fileRef = fileNo ? ` FileNo ${fileNo}` : '';
                return `${verb}${fileRef} was initiated by ${initiator} on ${dt} due to ${reason}`;
            }

            function refreshRemark() {
                if (remarkEl) remarkEl.value = buildRemark();
            }

            function toggleStatusFields() {
                const hasType   = isActionableType(typeSelect.value);
                const isRegrant = typeSelect.value === 'Re-grant';
                statusBlock?.classList.toggle('hidden', !hasType);
                seeRow?.classList.toggle('hidden', !isRegrant);
                if (!isRegrant && seeHidden) { seeHidden.value = ''; if (seeDisplay) seeDisplay.value = ''; }
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }

            typeSelect.addEventListener('change', function () {
                toggleStatusFields();
                refreshRemark();
            });
            reasonEl?.addEventListener('input', refreshRemark);

            // See (Additional File No.) picker
            document.getElementById('ts-see-select-btn')?.addEventListener('click', function () {
                if (!window.GlobalFileNoModal) return;
                GlobalFileNoModal.open({
                    // Do NOT write into the main #fileno / file_number inputs — the See file
                    // is a separate reference and must not overwrite the main file number.
                    autoPopulateGenericFields: false,
                    callback: function (data) {
                        if (!data || !data.fileNumber) return;
                        seeHidden.value = data.fileNumber;
                        seeDisplay.value = data.fileNumber;
                        refreshRemark();
                    }
                });
            });
            document.getElementById('ts-see-clear-btn')?.addEventListener('click', function () {
                seeHidden.value = '';
                seeDisplay.value = '';
            });

            // Called by create-indexing-dialog.js after a file index is created/updated.
            window.tsAfterFileIndexCreated = function (data, formData) {
                const type = typeSelect.value;
                if (!isActionableType(type)) return; // "Normal" or unselected → no title status

                formData = formData || {};
                const pick = (v) => Array.isArray(v) ? (v[0] || '') : (v || '');
                const reg  = (formData.general_registry || '').toString().toLowerCase();
                const url  = reg.includes('dciv') ? 'dciv' : 'land';

                const payload = {
                    url,
                    title_type:     type,
                    file_no:        getFileNo() || pick(formData.file_number),
                    see_fileno:     (seeHidden?.value || '').trim(),
                    file_title:     pick(formData.file_title),
                    applicant_name: pick(formData.current_holder) || pick(formData.file_title),
                    plot_no:        pick(formData.plot_number),
                    location:       pick(formData.location),
                    land_use:       pick(formData.land_use_type),
                    district:       pick(formData.district) || pick(formData.custom_district),
                    lga:            pick(formData.lga),
                    initiated_by:   initiatedByFor(type),
                    reason:         (reasonEl?.value || '').trim(),
                    remark:         (remarkEl?.value || '').trim() || buildRemark(),
                    // Title Status raised from File Indexing = false decommissioning
                    // (the file is flagged but NOT actually decommissioned).
                    false_decommissioning: 1,
                };

                if (!payload.file_no) return;

                fetch(TS_STORE_URL, {
                    method: 'POST',
                    keepalive: true,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify(payload),
                })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        console.warn('Title status not recorded:', res.message || res.errors);
                    } else {
                        console.log('Title status recorded:', res.data?.remark || '');
                    }
                })
                .catch(err => console.warn('Title status request failed:', err));
            };
        })();
    </script>
@endsection