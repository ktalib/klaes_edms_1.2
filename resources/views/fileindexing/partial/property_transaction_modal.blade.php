{{-- Property Record Transaction Modal for File Indexing --}}
<!-- Alpine.js is already loaded in the parent view -->
@php
    $transactionTypeOptions = [];
    try {
        $results = \Illuminate\Support\Facades\DB::connection('sqlsrv')->select("SELECT DISTINCT RTRIM(LTRIM([InstrumentName])) as InstrumentName FROM [klas].[dbo].[InstrumentTypes] WHERE [InstrumentName] IS NOT NULL ORDER BY InstrumentName");
        foreach ($results as $row) {
            $name = $row->InstrumentName;
            if (!empty($name)) {
                $transactionTypeOptions[] = $name;
            }
        }
    } catch (\Exception $e) {
        // Fallback in case of DB error
        $transactionTypeOptions = ['Other'];
    }

    if (!in_array('Other', $transactionTypeOptions)) {
        $transactionTypeOptions[] = 'Other';
    }
@endphp
  

<div id="property-transaction-dialog" class="property-transaction-overlay hidden"
    style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 10000; display: none; align-items: center; justify-content: center; background-color: rgba(0, 0, 0, 0.5);">
    <div class="dialog-content property-form-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
        <div class="flex justify-between items-center mb-4">
            <h2 id="transaction-form-title" class="text-xl font-bold">Add  Property Transaction Details</h2>
            <button id="close-property-transaction-form" class="text-gray-500 hover:text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div x-data="{
            isUpdateMode: false,
            // Transaction fields
            transactions: [{
                id: 1,
                recordId: null,
                transactionType: '',
                transactionDate: '',
                opType: '',
                opSerialNumber: '',
                serialNo: '',
                pageNo: '',
                volumeNo: '',
                regDate: new Date().toISOString().split('T')[0],
                regTime: '09:00',
                landUse: '',
                period: '',
                periodUnit: 'Years',
                comments: '',
                firstParty: '',
                secondParty: '',
                coFirstParty: '',
                thirdParty: '',
                includeCoSurrenderor: false,
                includeThirdParty: false,
                includeCoMortgagor: false,
                includeMortgagor3: false,
                mortgagor3: '',
                includeFourthParty: false,
                fourthParty: '',
                fifthParty: ''
            }],

            // File indexing data (will be populated from outside)
            fileIndexingData: {
                lga: '',
                district: '',
                state: 'KANO',
                plot_no: '',
                tp_no: '',
                property_description: ''
            },

            lgas: [],
            districts: [],

            // District Selection Logic
            districtSelection: '',
            customDistrict: '',

            syncDistrictSelection() {
                if (!this.fileIndexingData.district) {
                    this.districtSelection = '';
                    this.customDistrict = '';
                    return;
                }
                
                // If districts are loaded, check if value exists
                if (this.districts.length > 0) {
                    if (this.districts.includes(this.fileIndexingData.district)) {
                        this.districtSelection = this.fileIndexingData.district;
                        this.customDistrict = '';
                    } else {
                        this.districtSelection = 'Other';
                        this.customDistrict = this.fileIndexingData.district;
                    }
                } else {
                    // Fallback if not loaded yet - assume it might be existing
                    this.districtSelection = this.fileIndexingData.district; 
                }
            },

            handleDistrictChange() {
                if (this.districtSelection === 'Other') {
                    this.fileIndexingData.district = this.customDistrict;
                } else {
                    this.fileIndexingData.district = this.districtSelection;
                }
                this.updatePropertyDescription();
            },

            updateFromCustom() {
                if (this.districtSelection === 'Other') {
                    this.fileIndexingData.district = this.customDistrict;
                    this.updatePropertyDescription();
                }
            },

            // Party labels for different transaction types
            partyLabels: {
                'Power of Attorney': { first: 'Grantor', second: 'Grantee' },
                'Deed of Assignment': { first: 'Assignor', second: 'Assignee' },
                'ST Assignment': { first: 'Assignor', second: 'Assignee' },
                'Deed of Mortgage': { first: 'Mortgagor', second: 'Mortgagee' },
                'Tripartite Mortgage': { first: 'Mortgagor', second: 'Mortgagee' },
                'Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
                'ST Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
                'SLTR Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
                'Customary Right of Occupancy': { first: 'Grantor', second: 'Grantee' },
                'Deed of Transfer': { first: 'Transferor', second: 'Transferee' },
                'Deed of Gift': { first: 'Donor', second: 'Donee' },
                'Deed of Lease': { first: 'Lessor', second: 'Lessee' },
                'Deed of Sub Lease': { first: 'Lessor', second: 'Lessee' },
                'Deed of Sub Under Lease': { first: 'Lessor', second: 'Lessee' },
                'Indenture of Lease': { first: 'Lessor', second: 'Lessee' },
                'Quarry Lease': { first: 'Lessor', second: 'Lessee' },
                'Private Lease': { first: 'Lessor', second: 'Lessee' },
                'Building Lease': { first: 'Lessor', second: 'Lessee' },
                'Tenancy Agreement': { first: 'Landlord', second: 'Tenant' },
                'Deed of Release': { first: 'Releasor', second: 'Releasee' },
                'Deed of Surrender': { first: 'Surrenderor', second: 'Surrenderee' },
                'Deed of Surrender and Release': { first: 'Surrenderor', second: 'Surrenderee' },
                'Letter of Administration': { first: 'Administrator', second: 'Beneficiary' },
                'Certificate of Purchase': { first: 'Vendor', second: 'Purchaser' },
                'Occupancy Permit (OP)': { first: 'Grantor', second: 'Grantee' },
                'OCCUPANCY PERMIT (OP)': { first: 'Grantor', second: 'Grantee' },
                'OCCUPANCY PERMIT': { first: 'Grantor', second: 'Grantee' }
            },

            transactionTypeOptions: @js($transactionTypeOptions),
            additionalTransactionTypes: [],

            registerTransactionType(type) {
                if (!type) {
                    return;
                }
                const normalized = normalizePropertyTransactionType(type);
                if (!normalized) {
                    return;
                }
                if (!this.transactionTypeOptions.includes(normalized) && !this.additionalTransactionTypes.includes(normalized)) {
                    this.additionalTransactionTypes = [...this.additionalTransactionTypes, normalized];
                }
            },

            ensureTransactionTypes(types) {
                if (!Array.isArray(types)) {
                    return;
                }
                types.forEach(type => this.registerTransactionType(type));
            },

            // Add new transaction
            addTransaction() {
                this.transactions.push({
                    id: this.transactions.length + 1,
                    recordId: null,
                    transactionType: '',
                    transactionDate: '',
                    opSerialNumber: '',
                    serialNo: '',
                    pageNo: '',
                    volumeNo: '',
                    regDate: new Date().toISOString().split('T')[0],
                    regTime: '09:00',
                    landUse: '',
                    period: '',
                    periodUnit: 'Years',
                    firstParty: '',
                    secondParty: '',
                    coFirstParty: '',
                    thirdParty: '',
                    includeCoSurrenderor: false,
                    includeThirdParty: false,
                    includeCoMortgagor: false,
                    includeFourthParty: false,
                    fourthParty: ''
                });
            },

            // Remove transaction
            removeTransaction(index) {
                if (this.transactions.length > 1) {
                    this.transactions.splice(index, 1);
                }
            },

            // Get party labels for a transaction
            getPartyLabels(transactionType) {
                const labels = this.partyLabels[transactionType] || { first: 'Grantor', second: 'Grantee' };
                return {
                    first: 'Party 1 / ' + labels.first,
                    second: 'Party 2 / ' + labels.second
                };
            },

            // Check if transaction type is government
            isGovernmentTransaction(transactionType) {
                if (!transactionType) return false;
                
                // Standardize the check: uppercase, trim, and remove extra internal spaces
                const normalized = String(transactionType).toUpperCase().trim().replace(/\s+/g, ' ');
                
                const govKeys = [
                    'CERTIFICATE OF OCCUPANCY', 
                    'ST CERTIFICATE OF OCCUPANCY', 
                    'SLTR CERTIFICATE OF OCCUPANCY', 
                    'CUSTOMARY RIGHT OF OCCUPANCY', 
                    'OCCUPATION PERMIT', 
                    'OCCUPANCY PERMIT', 
                    'OCCUPANCY PERMIT (OP)',
                    'OP'
                ];
                
                const result = govKeys.includes(normalized);
                if (result) return true;
                
                // Partial match fallback for safety
                if (normalized.includes('OCCUPANCY PERMIT') || normalized.includes('CERTIFICATE OF OCCUPANCY') || normalized === 'OP') {
                    return true;
                }
                
                return false;
            },

            isOPTransaction(transactionType) {
                return normalizePropertyTransactionType(transactionType) === 'Occupancy Permit (OP)';
            },

            // Detects Right of Occupancy / Customary Right of Occupancy transactions.
            // For these, Registration Number defaults to 0/0/0 and Reg Date/Time are not applicable.
            isROFOTransaction(transactionType) {
                if (!transactionType) return false;
                const normalized = String(transactionType).toUpperCase().trim().replace(/\s+/g, ' ');
                return normalized === 'RIGHT OF OCCUPANCY'
                    || normalized === 'CUSTOMARY RIGHT OF OCCUPANCY'
                    || normalized === 'STATUTORY RIGHT OF OCCUPANCY';
            },

            // Get registration number preview
            getRegNumberPreview(transaction) {
                const parts = [transaction.serialNo, transaction.pageNo, transaction.volumeNo].filter(Boolean);
                return parts.length > 0 ? parts.join('/') : '';
            },

            // Auto-sync page number with serial number
            syncPageNo(transaction) {
                transaction.pageNo = transaction.serialNo;
            },

            // Get property description handling both indexing and edit page formats
            getPropertyDescription(data) {
                // If it's already built, return it (but check for N/A)
                if (data.property_description && data.property_description !== 'N/A') {
                    return data.property_description;
                }
                
                // Indexing page format: uses location field
                if (data.location && data.location !== 'N/A') {
                    return data.location;
                }
                
                // Fallback: construct from district and lga if available
                const district = data.district || '';
                const lga = data.lga || '';
                const state = data.state || 'KANO';
                
                if (district || lga) {
                    return [district, lga, state].filter(Boolean).join(', ').toUpperCase();
                }
                
                return 'N/A';
            },

            // Update property description from components
            updatePropertyDescription() {
                const parts = [];
                if (this.fileIndexingData.district) parts.push(this.fileIndexingData.district);
                if (this.fileIndexingData.lga) parts.push(this.fileIndexingData.lga);
                if (this.fileIndexingData.state || 'KANO') parts.push(this.fileIndexingData.state || 'KANO');
                
                this.fileIndexingData.property_description = parts.join(', ').toUpperCase();
            },

            // Auto-fill first party for government transactions
            handleTransactionTypeChange(transaction) {
                // First see if it's already a valid option to avoid breaking selection
                const originalValue = transaction.transactionType;
                const normalized = normalizePropertyTransactionType(originalValue);
                
                // If normalized is different but both are valid, stick to what's in the list
                if (normalized !== originalValue) {
                    if (this.transactionTypeOptions.includes(normalized) || this.additionalTransactionTypes.includes(normalized)) {
                        transaction.transactionType = normalized;
                    } else if (!this.transactionTypeOptions.includes(originalValue) && !this.additionalTransactionTypes.includes(originalValue)) {
                         transaction.transactionType = normalized;
                    }
                }
                
                this.registerTransactionType(transaction.transactionType);
                
                const isGov = this.isGovernmentTransaction(transaction.transactionType);
                console.log('Transaction Type changed:', {
                    value: transaction.transactionType,
                    isGov: isGov
                });

                if (isGov) {
                    transaction.firstParty = 'KANO STATE GOVERNMENT';
                } else {
                    if (transaction.firstParty === 'KANO STATE GOVERNMENT') {
                        transaction.firstParty = '';
                    }
                }

                // Reset extra fields when type changes
                transaction.includeCoSurrenderor = false;
                transaction.includeThirdParty = false;
                transaction.includeCoMortgagor = false;
                transaction.includeFourthParty = false;
                transaction.coFirstParty = '';
                transaction.thirdParty = '';
                transaction.fourthParty = '';

                if (!this.isOPTransaction(transaction.transactionType)) {
                    transaction.opSerialNumber = '';
                }

                // Right of Occupancy: Registration Number is fixed at 0/0/0 and Reg Date/Time are not applicable.
                if (this.isROFOTransaction(transaction.transactionType)) {
                    transaction.serialNo = '0';
                    transaction.pageNo = '0';
                    transaction.volumeNo = '0';
                    transaction.regDate = '';
                    transaction.regTime = '';
                }
            },

            // Submit form
            submitTransactions() {
                console.log('Submitting transactions:', this.transactions);
                console.log('File indexing data:', this.fileIndexingData);

                // Validate that at least one transaction has required fields
                const hasValidTransaction = this.transactions.some(t => {
                    if (this.isUpdateMode) {
                        return t.transactionType;
                    }
                    return t.transactionType && t.transactionDate;
                });

                if (!hasValidTransaction) {
                    const errorText = this.isUpdateMode
                        ? 'Please fill in at least one transaction with a Transaction Type.'
                        : 'Please fill in at least one transaction with Transaction Type and Date.';
                        
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: errorText,
                            confirmButtonText: 'OK'
                        });
                    } else {
                        alert(errorText);
                    }
                    return;
                }

                const missingOpSerial = this.transactions.some(t =>
                    this.isOPTransaction(t.transactionType) && !String(t.opSerialNumber || '').trim()
                );

                if (missingOpSerial) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'OP Serial Number is required for Occupancy Permit (OP) transactions.',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        alert('OP Serial Number is required for Occupancy Permit (OP) transactions.');
                    }
                    return;

                }

                const missingOpType = this.transactions.some(t =>
                    this.isOPTransaction(t.transactionType) && !String(t.opType || '').trim()
                );

                if (missingOpType) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'OP Type is required for Occupancy Permit (OP) transactions. Please select either \'OP Direct Allocation\' or \'OP Resettlement\'.',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        alert('OP Type is required for Occupancy Permit (OP) transactions.');
                    }
                    return;
                }

                const missingOpRegNo = this.transactions.some(t =>
                    this.isOPTransaction(t.transactionType) && (!String(t.serialNo || '').trim() || !String(t.volumeNo || '').trim())
                );

                if (missingOpRegNo) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Registration Number (Serial No. and Volume No.) is required for Occupancy Permit (OP) transactions.',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        alert('Registration Number (Serial No. and Volume No.) is required for Occupancy Permit (OP) transactions.');
                    }
                    return;
                }

                // Call the global submission handler
                if (typeof submitPropertyTransactions === 'function') {
                    submitPropertyTransactions(this.transactions, this.fileIndexingData);
                } else {
                    console.error('submitPropertyTransactions function not found');
                }
            }
        }" x-init="
            // Watch for changes and sync page numbers
            $watch('transactions', (value) => {
                value.forEach(t => {
                    if (t.serialNo && !t.pageNo) {
                        t.pageNo = t.serialNo;
                    }
                });
            }, { deep: true });
        ">
            <form id="property-transaction-form" @submit.prevent="submitTransactions">
                @csrf

                <div class="space-y-4 py-2 flex-1">
                    <!-- Info Box showing file indexing details -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <div class="flex items-start gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mt-0.5"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                    clip-rule="evenodd" />
                            </svg>
                            <div class="flex-1">
                                <h4 class="text-sm font-semibold text-blue-800 mb-2">File Indexing Information</h4>
                                <div class="grid grid-cols-2 gap-2 text-sm text-blue-700">
                                    <div><strong>File Number:</strong> <span
                                            x-text="fileIndexingData.file_number || 'N/A'"></span></div>
                                    <div><strong>LGA:</strong> <span x-text="fileIndexingData.lga || 'N/A'"></span>
                                    </div>
                                    <div><strong>Plot No:</strong> <span
                                            x-text="fileIndexingData.plot_no || fileIndexingData.plot_number || 'N/A'"></span>
                                    </div>
                                    <div><strong>TP No:</strong> <span x-text="fileIndexingData.tp_no || 'N/A'"></span>
                                    </div>
                                    <div class="col-span-2"><strong>Property Description:</strong> <span
                                            x-text="getPropertyDescription(fileIndexingData)"></span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Property Details Builder Section -->
                    <div class="border border-blue-100 rounded-lg p-4 mb-4 bg-blue-50/30">
                        <h4 class="text-sm font-semibold text-blue-800 mb-3 flex items-center gap-2">
                             <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Property Registration Details
                        </h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">District</label>
                                <select x-model="districtSelection" @change="handleDistrictChange()"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors bg-white appearance-none">
                                    <option value="">Select District</option>
                                    <template x-for="name in districts" :key="name">
                                        <option :value="name" x-text="name" :selected="districtSelection === name"></option>
                                    </template>
                                    <option value="Other">Other</option>
                                </select>
                                <div x-show="districtSelection === 'Other'" class="mt-2" x-transition>
                                    <input type="text" x-model="customDistrict" @input="updateFromCustom()"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                        placeholder="Specify District Name">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">LGA</label>
                                <select x-model="fileIndexingData.lga" @change="updatePropertyDescription()"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors bg-white appearance-none">
                                    <option value="">Select LGA</option>
                                    <template x-for="name in lgas" :key="name">
                                        <option :value="name" x-text="name" :selected="fileIndexingData.lga === name"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">State</label>
                                <input type="text" x-model="fileIndexingData.state" readonly
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-md bg-gray-50 text-gray-500 cursor-not-allowed">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Plot No.</label>
                                <input type="text" x-model="fileIndexingData.plot_no"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                    placeholder="Enter Plot No">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">TP No.</label>
                                <input type="text" x-model="fileIndexingData.tp_no"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                    placeholder="Enter TP No"
                                    @input="fileIndexingData.tp_no = fileIndexingData.tp_no.replace(/-/g, '/')">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Built Property Description</label>
                            <textarea x-model="fileIndexingData.property_description" readonly
                                class="w-full px-3 py-2 text-sm border border-blue-200 rounded-md bg-blue-50/50 text-blue-800 font-semibold uppercase"
                                rows="2" placeholder="Description will be built from LGA and District..."></textarea>
                        </div>
                    </div>

                    <!-- Transactions Container -->
                    <template x-for="(transaction, index) in transactions" :key="transaction.id">
                        <div class="border border-gray-300 rounded-lg p-4 mb-4 bg-white shadow-sm">
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="text-lg font-semibold text-gray-700">
                                    Transaction <span x-text="index + 1"></span>
                                </h3>
                                <button type="button" @click="removeTransaction(index)" x-show="transactions.length > 1"
                                    class="text-red-500 hover:text-red-700 text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>

                            <div class="space-y-3">
                                <!-- Transaction Type and Date - 2x2 Grid -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Type
                                            <span class="text-red-500">*</span></label>
                                        <select x-model="transaction.transactionType"
                                            @change="handleTransactionTypeChange(transaction); if (!isOPTransaction(transaction.transactionType)) transaction.opType = ''"
                                            :name="'transactions[' + index + '][transaction_type]'"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors bg-white">
                                            <option value="">Select type</option>
                                            <template x-for="option in transactionTypeOptions"
                                                :key="'default-' + option">
                                                <option :value="option" x-text="option"></option>
                                            </template>
                                            <template x-for="option in additionalTransactionTypes"
                                                :key="'custom-' + option">
                                                <option :value="option" x-text="option"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 mb-1">Transaction/Certificate
                                            Date <span x-show="!isUpdateMode" class="text-red-500">*</span></label>
                                        <input type="date" x-model="transaction.transactionDate"
                                            :name="'transactions[' + index + '][transaction_date]'"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors">
                                    </div>
                                </div>

                                <!-- OP Type (shown only for Occupancy Permit) -->
                                <div x-show="isOPTransaction(transaction.transactionType)" x-cloak>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        OP Type <span class="text-red-500">*</span>
                                    </label>
                                    <select x-model="transaction.opType"
                                        :name="'transactions[' + index + '][op_type]'"
                                        :required="isOPTransaction(transaction.transactionType)"
                                        :class="isOPTransaction(transaction.transactionType) && !transaction.opType ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-300'"
                                        class="w-full px-3 py-2 text-sm border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors bg-white">
                                        <option value="">Select OP type</option>
                                        <option value="OP Direct Allocation">OP Direct Allocation</option>
                                        <option value="OP Resettlement">OP Resettlement</option>
                                    </select>
                                </div>

                                <!-- Registration Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Registration
                                        Number
                                        <span x-show="isOPTransaction(transaction.transactionType)" class="text-red-500 text-xs font-normal ml-1">(Serial No. &amp; Volume No. required for OP)</span>
                                    </label>
                                    <div class="grid grid-cols-5 gap-2">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Serial
                                                No.<span x-show="isOPTransaction(transaction.transactionType)" class="text-red-500"> *</span></label>
                                            <input type="text" x-model="transaction.serialNo"
                                                @input="transaction.serialNo = transaction.serialNo.replace(/[^0-9]/g, '').replace(/^0+(\d)/, '$1'); syncPageNo(transaction)"
                                                :name="'transactions[' + index + '][serial_no]'"
                                                :class="isOPTransaction(transaction.transactionType) && !transaction.serialNo ? 'border-red-400 ring-1 ring-red-300' : (isROFOTransaction(transaction.transactionType) ? 'border-gray-200 bg-gray-50 text-gray-500 cursor-not-allowed' : 'border-gray-300')"
                                                :readonly="isROFOTransaction(transaction.transactionType)"
                                                class="w-full px-2 py-1.5 text-xs border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                placeholder="e.g. 1">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1">Page No.
                                                (Auto-filled)</label>
                                            <input type="text" x-model="transaction.pageNo"
                                                :name="'transactions[' + index + '][page_no]'" readonly
                                                class="w-full px-2 py-1.5 text-xs border border-gray-200 rounded-md bg-gray-50 text-gray-500 cursor-not-allowed"
                                                placeholder="Same as Serial">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Volume
                                                No.<span x-show="isOPTransaction(transaction.transactionType)" class="text-red-500"> *</span></label>
                                            <input type="text" x-model="transaction.volumeNo"
                                                @input="transaction.volumeNo = transaction.volumeNo.replace(/[^0-9]/g, '').replace(/^0+(\d)/, '$1')"
                                                :name="'transactions[' + index + '][volume_no]'"
                                                :class="isOPTransaction(transaction.transactionType) && !transaction.volumeNo ? 'border-red-400 ring-1 ring-red-300' : (isROFOTransaction(transaction.transactionType) ? 'border-gray-200 bg-gray-50 text-gray-500 cursor-not-allowed' : 'border-gray-300')"
                                                :readonly="isROFOTransaction(transaction.transactionType)"
                                                class="w-full px-2 py-1.5 text-xs border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                placeholder="e.g. 2">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Reg Time</label>
                                            <input type="time" x-model="transaction.regTime"
                                                :name="'transactions[' + index + '][reg_time]'"
                                                :disabled="isROFOTransaction(transaction.transactionType)"
                                                :class="isROFOTransaction(transaction.transactionType) ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : ''"
                                                class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Reg Date</label>
                                            <input type="date" x-model="transaction.regDate"
                                                :name="'transactions[' + index + '][reg_date]'"
                                                :disabled="isROFOTransaction(transaction.transactionType)"
                                                :class="isROFOTransaction(transaction.transactionType) ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : ''"
                                                class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors">
                                        </div>
                                    </div>

                                    <!-- Registration Number Preview -->
                                    <div x-show="getRegNumberPreview(transaction)" x-transition
                                        class="mt-2 p-2 bg-blue-50 border border-blue-200 rounded">
                                        <span class="text-sm font-semibold text-blue-700">Registration Number:</span>
                                        <span class="text-sm font-bold text-blue-800 ml-2"
                                            x-text="getRegNumberPreview(transaction)"></span>
                                    </div>

                                    <div x-show="isOPTransaction(transaction.transactionType)" x-transition class="mt-3">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            OP Serial Number <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" x-model="transaction.opSerialNumber"
                                            :name="'transactions[' + index + '][op_serial_number]'"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                            placeholder="Enter OP serial number"
                                            @input="
                                                let v = $event.target.value.replace(/[^0-9]/g, '');
                                                v = v.replace(/^0+(\d)/, '$1');
                                                transaction.opSerialNumber = v;
                                                $event.target.value = v;
                                            ">
                                    </div>
                                </div>

                                <!-- Land Use and Period -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Land Use</label>
                                        <select x-model="transaction.landUse"
                                            :name="'transactions[' + index + '][land_use]'"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors bg-white">
                                            <option value="">Select land use</option>
                                            <option value="RESIDENTIAL">RESIDENTIAL</option>
                                            <option value="AGRICULTURAL">AGRICULTURAL</option>
                                            <option value="COMMERCIAL">COMMERCIAL</option>
                                            <option value="INDUSTRIAL">INDUSTRIAL</option>
                                            <option value="RESIDENTIAL/COMMERCIAL">RESIDENTIAL/COMMERCIAL</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 mb-1">Period/Tenancy</label>
                                        <div class="flex space-x-2">
                                            <input type="number" x-model="transaction.period"
                                                :name="'transactions[' + index + '][period]'"
                                                class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                placeholder="Period">
                                            <select x-model="transaction.periodUnit"
                                                :name="'transactions[' + index + '][period_unit]'"
                                                class="w-24 px-2 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors bg-white">
                                                <option value="Days">Days</option>
                                                <option value="Months">Months</option>
                                                <option value="Years">Years</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Comments/Remarks -->
                                <div class='hidden'>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Comments (e.g. Temp File Number)</label>
                                    <textarea x-model="transaction.comments"
                                        :name="'transactions[' + index + '][comments]'"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                        rows="2" placeholder="Add any comments or remarks here..."></textarea>
                                </div>

                                <!-- Transaction Parties -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1"
                                            x-text="getPartyLabels(transaction.transactionType).first"></label>
                                        <input type="text" x-model="transaction.firstParty"
                                            :name="'transactions[' + index + '][first_party]'"
                                            :class="isGovernmentTransaction(transaction.transactionType) ? 'w-full px-3 py-2 text-sm border border-gray-200 rounded-md shadow-sm bg-gray-50 text-gray-600 cursor-not-allowed' : 'w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors'"
                                            :readonly="isGovernmentTransaction(transaction.transactionType)"
                                            :placeholder="isGovernmentTransaction(transaction.transactionType) ? 'KANO STATE GOVERNMENT' : 'Enter name'">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1"
                                            x-text="getPartyLabels(transaction.transactionType).second"></label>
                                        <input type="text" x-model="transaction.secondParty"
                                            :name="'transactions[' + index + '][second_party]'"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                            placeholder="Enter name">
                                    </div>
                                </div>
                                <!-- Extended Party Fields (Co-Surrenderor / Co-Mortgagor / Third Party) -->
                                <div class="col-span-2 mt-2"
                                    x-show="normalizePropertyTransactionType(transaction.transactionType) === 'Deed of Surrender' || 
                                            normalizePropertyTransactionType(transaction.transactionType) === 'Tripartite Mortgage' || 
                                            normalizePropertyTransactionType(transaction.transactionType) === 'Deed of Mortgage' ||
                                            normalizePropertyTransactionType(transaction.transactionType) === 'Deed of Surrender and Release'">

                                    <!-- Deed of Surrender specific -->
                                    <div
                                        x-show="normalizePropertyTransactionType(transaction.transactionType) === 'Deed of Surrender' || normalizePropertyTransactionType(transaction.transactionType) === 'Deed of Surrender and Release'">
                                        <div class="flex items-center mb-2">
                                            <input type="checkbox" x-model="transaction.includeCoSurrenderor"
                                                :id="'include-co-surrenderor-' + index"
                                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <label :for="'include-co-surrenderor-' + index"
                                                class="ml-2 block text-sm text-gray-900">
                                                Include Co-Surrenderor
                                            </label>
                                        </div>
                                        <div x-show="transaction.includeCoSurrenderor" x-transition>
                                            <label
                                                class="block text-sm font-medium text-gray-700 mb-1">Co-Surrenderor</label>
                                            <input type="text" x-model="transaction.coFirstParty"
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                placeholder="Enter Co-Surrenderor name">
                                        </div>
                                    </div>

                                    <!-- Tripartite Mortgage specific -->
                                    <div
                                        x-show="normalizePropertyTransactionType(transaction.transactionType) === 'Tripartite Mortgage'">
                                        <!-- Co-Mortgagor (Mortgagor 2) -->
                                        <div class="space-y-3 pl-6 border-l-2 border-blue-100">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Co-Mortgagor (Mortgagor 2)</label>
                                                <input type="text" x-model="transaction.coFirstParty"
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                    placeholder="Enter Co-Mortgagor name">
                                            </div>

                                            <div class="flex items-center">
                                                <input type="checkbox" x-model="transaction.includeMortgagor3"
                                                    :id="'include-mortgagor3-trip-' + index"
                                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label :for="'include-mortgagor3-trip-' + index"
                                                    class="ml-2 block text-sm text-gray-900">
                                                    Include Mortgagor 3?
                                                </label>
                                            </div>

                                            <div x-show="transaction.includeMortgagor3" x-transition>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Mortgagor 3 (party_4)</label>
                                                <input type="text" x-model="transaction.mortgagor3"
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                    placeholder="Enter Mortgagor 3 name">
                                            </div>

                                            <div class="flex items-center">
                                                <input type="checkbox" x-model="transaction.includeFourthParty"
                                                    :id="'include-fourth-party-trip-' + index"
                                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label :for="'include-fourth-party-trip-' + index"
                                                    class="ml-2 block text-sm text-gray-900">
                                                    Include Fourth Party Agreement?
                                                </label>
                                            </div>

                                            <div x-show="transaction.includeFourthParty" x-transition>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Fourth Party (party_5)</label>
                                                <input type="text" x-model="transaction.fourthParty"
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                    placeholder="Enter Fourth Party name">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Deed of Mortgage specific: Co-Mortgagor and Fourth Party -->
                                    <div x-show="normalizePropertyTransactionType(transaction.transactionType) === 'Deed of Mortgage'">
                                        <div class="flex items-center mb-2">
                                            <input type="checkbox" x-model="transaction.includeCoMortgagor"
                                                :id="'include-co-mortgagor-' + index"
                                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <label :for="'include-co-mortgagor-' + index"
                                                class="ml-2 block text-sm text-gray-900">
                                                Include Co-Mortgagor (three-party agreement)
                                            </label>
                                        </div>

                                        <div x-show="transaction.includeCoMortgagor" x-transition class="space-y-3 pl-6 border-l-2 border-blue-100">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Co-Mortgagor</label>
                                                <input type="text" x-model="transaction.coFirstParty"
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                    placeholder="Enter Co-Mortgagor name">
                                            </div>

                                            <div class="flex items-center">
                                                <input type="checkbox" x-model="transaction.includeFourthParty"
                                                    :id="'include-fourth-party-' + index"
                                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label :for="'include-fourth-party-' + index"
                                                    class="ml-2 block text-sm text-gray-900 ">
                                                    Include Fourth Party?
                                                </label>
                                            </div> 

                                            <div x-show="transaction.includeFourthParty" x-transition>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Fourth Party</label>
                                                <input type="text" x-model="transaction.fourthParty"
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                    placeholder="Enter Fourth Party name">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Add Transaction Button -->
                    <div class="flex justify-center mt-4">
                        <button type="button" @click="addTransaction()"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 hover:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                    clip-rule="evenodd" />
                            </svg>
                            Add Another Transaction
                        </button>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200 mt-6">
                        <button type="button" id="cancel-property-transaction"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            Cancel
                        </button>
                       
                        <button type="submit" id="save-transaction-btn"
                            class="px-6 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            Save Transaction Details
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Property Transaction Modal overlay styles - Use unique class name to avoid conflicts */
    .property-transaction-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    }

    .property-transaction-overlay.hidden {
        display: none !important;
    }

    .property-transaction-overlay .dialog-content {
        background: white;
        border-radius: 0.5rem;
        padding: 1.5rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        position: relative;
        z-index: 10001;
    }

    .property-form-content {
        width: 100%;
        max-width: 900px;
    }

    /* Ensure SweetAlert appears above this modal */
    .swal2-container {
        z-index: 20000 !important;
    }
</style>

<script>
    const PROPERTY_TRANSACTION_TYPE_MAP = {
        'DEED OF TRANSFER': 'Deed of Transfer',
        'DEED OF ASSIGNMENT': 'Deed of Assignment',
        'ST ASSIGNMENT': 'ST Assignment',
        'CERTIFICATE OF OCCUPANCY': 'Certificate of Occupancy',
        'C OF O': 'Certificate of Occupancy',
        'ST CERTIFICATE OF OCCUPANCY': 'ST Certificate of Occupancy',
        'SLTR CERTIFICATE OF OCCUPANCY': 'SLTR Certificate of Occupancy',
        'IRREVOCABLE POWER OF ATTORNEY': 'Irrevocable Power of Attorney',
        'POWER OF ATTORNEY': 'Power of Attorney',
        'DEED OF RELEASE': 'Deed of Release',
        'DEED OF MORTGAGE': 'Deed of Mortgage',
        'TRIPARTITE MORTGAGE': 'Tripartite Mortgage',
        'DEED OF SUB LEASE': 'Deed of Sub Lease',
        'DEED OF SUBLEASE': 'Deed of Sub Lease',
        'DEED OF SUB UNDER LEASE': 'Deed of Sub Under Lease',
        'DEED OF LEASE': 'Deed of Lease',
        'INDENTURE OF LEASE': 'Indenture of Lease',
        'DEED OF VARIATION': 'Deed of Variation',
        'DEED OF SURRENDER': 'Deed of Surrender',
        'DEED OF SURRENDER AND RELEASE': 'Deed of Surrender and Release',
        'DEED OF GIFT': 'Deed of Gift',
        'LETTER OF ADMINISTRATION': 'Letter of Administration',
        'CERTIFICATE OF PURCHASE': 'Certificate of Purchase',
        'TENANCY AGREEMENT': 'Tenancy Agreement',
        'CUSTOMARY RIGHT OF OCCUPANCY': 'Customary Right of Occupancy',
        'OCCUPATION PERMIT': 'Occupation Permit',
        'OCCUPANCY PERMIT': 'Occupancy Permit (OP)',
        'OCCUPANCY PERMIT (OP)': 'Occupancy Permit (OP)',
        'OCCUPANCY PERMIT(OP)': 'Occupancy Permit (OP)',
        'OP': 'Occupancy Permit (OP)',
        'OTHER': 'Other',
    };

    function formatDateForInput(value) {
        if (!value) {
            return '';
        }

        const stringValue = String(value).trim();
        if (stringValue === '') {
            return '';
        }

        const isoMatch = stringValue.match(/^(\d{4}-\d{2}-\d{2})/);
        if (isoMatch) {
            return isoMatch[1];
        }

        const parsed = new Date(stringValue);
        if (!isNaN(parsed.getTime())) {
            return parsed.toISOString().split('T')[0];
        }

        return stringValue;
    }

    function formatTimeForInput(value) {
        if (!value) {
            return '';
        }

        const stringValue = String(value).trim();
        if (stringValue === '') {
            return '';
        }

        const timeMatch = stringValue.match(/^(\d{2}:\d{2})/);
        if (timeMatch) {
            return timeMatch[1];
        }

        const parsed = new Date(`1970-01-01T${stringValue}`);
        if (!isNaN(parsed.getTime())) {
            return parsed.toISOString().substring(11, 16);
        }

        return stringValue.substring(0, 5);
    }

    function normalizePropertyTransactionType(value) {
        if (value === null || typeof value === 'undefined') {
            return '';
        }

        const textValue = String(value).trim();
        if (textValue === '') {
            return '';
        }

        const lookupKey = textValue.toUpperCase();
        return PROPERTY_TRANSACTION_TYPE_MAP[lookupKey] || textValue;
    }

    /**
     * Build a standardized property description from various data sources
     */
    function getUnifiedPropertyDescription(data) {
        if (!data) return 'N/A';
        
        // Use property_description if it exists and is valid
        if (data.property_description && data.property_description !== 'N/A' && data.property_description.trim() !== '') {
            return data.property_description;
        }
        
        // Fallback to location
        if (data.location && data.location !== 'N/A' && data.location.trim() !== '') {
            return data.location;
        }
        
        // Re-build from components as last resort
        const parts = [];
        if (data.district) parts.push(data.district);
        if (data.lga) parts.push(data.lga);
        if (data.state || 'KANO') parts.push(data.state || 'KANO');
        
        return parts.length > 0 ? parts.join(', ').toUpperCase() : 'N/A';
    }

    // Global function to open the property transaction modal
    // IMPORTANT: Defined outside DOMContentLoaded to be globally accessible immediately
    function openPropertyTransactionModal(fileIndexingData) {
        console.log('Opening property transaction modal with data:', fileIndexingData);

        const modal = document.getElementById('property-transaction-dialog');
        if (!modal) {
            console.error('Property transaction modal not found!');
            return;
        }

        console.log('Modal element found:', modal);

        // Try to get Alpine component and set file indexing data
        try {
            const alpineElement = modal.querySelector('[x-data]');
            console.log('Alpine element:', alpineElement);

            if (alpineElement && typeof Alpine !== 'undefined') {
                // Wait for Alpine to be ready
                setTimeout(() => {
                    try {
                        const alpineComponent = Alpine.$data(alpineElement);
                        if (alpineComponent) {
                            console.log('Alpine component found, setting data...');
                            
                            // Load reference data if missing
                            if (alpineComponent.lgas.length === 0) {
                                fetch('/api/reference/lgas')
                                    .then(res => res.json())
                                    .then(data => { 
                                        if (data.success && data.data) {
                                            alpineComponent.lgas = data.data.map(item => item.name);
                                        }
                                    });
                            }
                            if (alpineComponent.districts.length === 0) {
                                fetch('/api/reference/districts')
                                    .then(res => res.json())
                                    .then(data => { 
                                        if (data.success && data.data) {
                                            alpineComponent.districts = data.data.map(item => item.name);
                                            // Sync selection after loading districts
                                            if (typeof alpineComponent.syncDistrictSelection === 'function') {
                                                alpineComponent.syncDistrictSelection();
                                            }
                                        }
                                    });
                            } else {
                                // Sync immediately if already loaded
                                if (typeof alpineComponent.syncDistrictSelection === 'function') {
                                    alpineComponent.syncDistrictSelection();
                                }
                            }

                            // Correct and enrich fileIndexingData from existing records if available
                            if (fileIndexingData.existing_records && fileIndexingData.existing_records.length > 0) {
                                const rec = fileIndexingData.existing_records[0];
                                console.log('Enriching header info from first record:', rec);
                                
                                // Map database fields correctly
                                const cleanValue = (val) => (!val || val === 'N/A') ? '' : val;
                                
                                if (!cleanValue(fileIndexingData.lga)) fileIndexingData.lga = rec.lgsaOrCity || rec.lga || '';
                                if (!cleanValue(fileIndexingData.district)) fileIndexingData.district = rec.districtName || rec.district || '';
                                if (!cleanValue(fileIndexingData.plot_no)) {
                                    fileIndexingData.plot_no = rec.plot_no || rec.plot_number || '';
                                }
                                if (!cleanValue(fileIndexingData.tp_no)) fileIndexingData.tp_no = rec.tp_no || '';
                                if (!cleanValue(fileIndexingData.property_description)) {
                                    fileIndexingData.property_description = rec.property_description || rec.location || '';
                                }
                                if (!fileIndexingData.state || fileIndexingData.state === 'N/A') fileIndexingData.state = 'KANO';
                            }
                            
                            
                            alpineComponent.fileIndexingData = fileIndexingData;
                            
                            // Re-sync after setting data
                            if (typeof alpineComponent.syncDistrictSelection === 'function') {
                                alpineComponent.syncDistrictSelection();
                            }

                            // Check if existing records exist and populate transactions
                            if (fileIndexingData.existing_records && fileIndexingData.existing_records.length > 0) {
                                console.log('Found existing records, populating transactions:', fileIndexingData.existing_records.length);
                                console.log('Sample existing record structure:', fileIndexingData.existing_records[0]);

                                // Helper function to extract party names from database record
                                function extractPartyNames(record) {
                                    // Check for specific party fields based on transaction type
                                    const partyFields = [
                                        ['party_1', 'party_2'], // Generic party columns
                                        ['Grantor', 'Grantee'],
                                        ['Assignor', 'Assignee'],
                                        ['Mortgagor', 'Mortgagee'],
                                        ['Surrenderor', 'Surrenderee'],
                                        ['Lessor', 'Lessee'],
                                        ['Landlord', 'Tenant'],
                                        ['Releasor', 'Releasee'],
                                        ['Transferor', 'Transferee'],
                                        ['Donor', 'Donee'],
                                        ['Administrator', 'Beneficiary'],
                                        ['Vendor', 'Purchaser']
                                    ];

                                    let firstParty = '';
                                    let secondParty = '';

                                    // Check each possible party field combination
                                    for (let [first, second] of partyFields) {
                                        if (record[first] || record[second]) {
                                            firstParty = record[first] || '';
                                            secondParty = record[second] || '';
                                            break;
                                        }
                                    }

                                    // Fallback to generic fields if available
                                    if (!firstParty && !secondParty) {
                                        firstParty = record.first_party || record.firstParty || '';
                                        secondParty = record.second_party || record.secondParty || '';
                                    }

                                    return { firstParty, secondParty };
                                }

                                // Convert existing records to transaction format
                                const mappedTransactions = fileIndexingData.existing_records.map((record, index) => {
                                    const parties = extractPartyNames(record);
                                    const formattedTransactionDate = formatDateForInput(record.transaction_date || record.transactionDate || null);
                                    const formattedRegDate = formatDateForInput(record.reg_date || record.regDate || null);
                                    const formattedRegTime = formatTimeForInput(record.reg_time || record.regTime || null);
                                    const normalizedType = normalizePropertyTransactionType(
                                        record.transaction_type ||
                                        record.transactionType ||
                                        record.instrument_type ||
                                        record.instrumentType ||
                                        ''
                                    );

                                    const transactionPayload = {
                                        id: index + 1,
                                        recordId: record.id || record.record_id || null,
                                        transactionType: normalizedType,
                                        instrumentType: normalizedType,
                                        transactionDate: formattedTransactionDate || '',
                                        opType: record.op_type || record.opType || '',
                                        opSerialNumber: record.op_serial_number || record.opSerialNumber || '',
                                        serialNo: record.serialNo || record.serial_no || '',
                                        pageNo: record.pageNo || record.page_no || '',
                                        volumeNo: record.volumeNo || record.volume_no || '',
                                        regDate: formattedRegDate || new Date().toISOString().split('T')[0],
                                        regTime: formattedRegTime || '09:00',
                                        landUse: record.landUse || record.land_use || fileIndexingData.land_use_type || '',
                                        period: record.period || '',
                                        periodUnit: record.periodUnit || record.period_unit || 'Years',
                                        comments: record.comments || record.comment || record.remarks || '',
                                        firstParty: parties.firstParty || '',
                                        secondParty: parties.secondParty || ''
                                    };
                                    return transactionPayload;
                                });

                                if (typeof alpineComponent.ensureTransactionTypes === 'function') {
                                    alpineComponent.ensureTransactionTypes(mappedTransactions.map(t => t.transactionType));
                                }

                                alpineComponent.transactions = mappedTransactions;
                                alpineComponent.isUpdateMode = true;

                                console.log('Populated transactions from existing records:', alpineComponent.transactions);

                                // Update modal title to "Update"
                                const titleElement = document.getElementById('transaction-form-title');
                                if (titleElement) {
                                    titleElement.textContent = 'Update Property Transaction Details';
                                }
                            } else {
                                console.log('No existing records, creating empty transaction');
                                alpineComponent.isUpdateMode = false;

                                // Update modal title to "Add"
                                const titleElement = document.getElementById('transaction-form-title');
                                if (titleElement) {
                                    titleElement.textContent = 'Add Property Transaction Details';
                                }

                                // Create single empty transaction
                                const blankTransaction = {
                                    id: 1,
                                    recordId: null,
                                    transactionType: '',
                                    transactionDate: '',
                                    opSerialNumber: '',
                                    serialNo: '',
                                    pageNo: '',
                                    volumeNo: '',
                                    regDate: new Date().toISOString().split('T')[0],
                                    regTime: '09:00',
                                    landUse: fileIndexingData.land_use_type || '',
                                    period: '',
                                    periodUnit: 'Years',
                                    comments: fileIndexingData.temp_file_no ? 'Temp File: ' + fileIndexingData.temp_file_no : '',
                                    firstParty: '',
                                    secondParty: ''
                                };

                                alpineComponent.ensureTransactionTypes([blankTransaction.transactionType]);
                                alpineComponent.transactions = [blankTransaction];
                            }
                            console.log('Data set successfully');
                        } else {
                            console.warn('Alpine component not found, modal will still open');
                        }
                    } catch (e) {
                        console.error('Error setting Alpine data:', e);
                    }
                }, 100);
            } else {
                console.warn('Alpine not ready or element not found, modal will still open');
            }
        } catch (e) {
            console.error('Error accessing Alpine:', e);
        }

        // Show the modal regardless
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
        console.log('Modal should now be visible');
    }

    // Global function to close the property transaction modal
    function closePropertyTransactionModal() {
        const modal = document.getElementById('property-transaction-dialog');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    // Make functions globally accessible
    window.openPropertyTransactionModal = openPropertyTransactionModal;
    window.closePropertyTransactionModal = closePropertyTransactionModal;

    // Unified property description function for form submission
    function getUnifiedPropertyDescription(data) {
        // Edit page format: uses property_description (district + lga)
        if (data.property_description) {
            return data.property_description;
        }

        // Indexing page format: uses location field
        if (data.location) {
            return data.location;
        }

        // Fallback: construct from district and lga if available
        const district = data.district || '';
        const lga = data.lga || '';
        if (district || lga) {
            return [district, lga].filter(Boolean).join(', ');
        }

        return '';
    }

    // Global function to submit property transactions
    function submitPropertyTransactions(transactions, fileIndexingData) {
        console.log('=== SUBMITTING PROPERTY TRANSACTIONS ===');
        console.log('1. File Indexing Data received:', fileIndexingData);
        console.log('2. File Number:', fileIndexingData?.file_number);
        console.log('3. Original transactions:', transactions);

        // Validate file indexing data
        if (!fileIndexingData || !fileIndexingData.file_number) {
            console.error('ERROR: File indexing data or file number is missing!');
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'File number is missing. Please close the modal and try again.',
                    confirmButtonText: 'OK'
                });
            } else {
                alert('File number is missing. Please close the modal and try again.');
            }
            return;
        }

        // Convert camelCase field names to snake_case for backend
        const convertedTransactions = transactions.map(t => ({
            record_id: t.recordId || null,
            transaction_type: t.transactionType || '',
            instrument_type: t.instrumentType || '',
            op_type: t.opType || '',
            transaction_date: t.transactionDate || '',
            op_serial_number: String(t.opSerialNumber || '').trim().replace(/^0+(\d)/, '$1'),
            serial_no: String(t.serialNo || '').trim().replace(/^0+(\d)/, '$1'),
            page_no: String(t.pageNo || '').trim().replace(/^0+(\d)/, '$1'),
            volume_no: String(t.volumeNo || '').trim().replace(/^0+(\d)/, '$1'),
            reg_date: t.regDate || '',
            reg_time: t.regTime || '',
            land_use: t.landUse || '',
            period: t.period || '',
            period_unit: t.periodUnit || 'Years',
            comments: t.comments || '',
            first_party: t.firstParty || '',
            second_party: t.secondParty || '',
            co_first_party: t.coFirstParty || '',
            third_party: t.thirdParty || '',
            mortgagor_3: t.mortgagor3 || '',
            party_4: t.party4 || t.mortgagor3 || '',
            party_5: t.party5 || t.fourthParty || t.fifthParty || ''
        }));

        console.log('4. Converted transactions:', convertedTransactions);

        // Prepare data for submission with unified field handling
        const formData = {
            file_number: fileIndexingData.file_number,
            temp_fileno: fileIndexingData.temp_file_no || null,
            file_title: fileIndexingData.file_title,
            plot_no: fileIndexingData.plot_no || fileIndexingData.plot_number, // Handle both formats
            tp_no: fileIndexingData.tp_no,
            lpkn_no: fileIndexingData.lpkn_no,
            lga: fileIndexingData.lga,
            district: fileIndexingData.district,
            property_description: getUnifiedPropertyDescription(fileIndexingData),
            test_control: fileIndexingData.test_control || null,
            transactions: convertedTransactions
        };

        console.log('5. Final form data to submit:', formData);
        console.log('6. Form data as JSON:', JSON.stringify(formData, null, 2));

        // Disable submit button/show loading state
        const submitBtn = document.getElementById('save-transaction-btn');
        let originalBtnText = 'Save Transaction Details';
        if (submitBtn) {
            originalBtnText = submitBtn.innerText;
            submitBtn.disabled = true;
            submitBtn.innerText = 'Saving...';
        }

        // Submit to server
        $.ajax({
            url: '{{ route("property-records.storeFromIndexing") }}',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                console.log('Success:', response);

                // Build detailed toast message showing where records went
                let toastParts = [];
                const data = response.data || {};
                const fileHistoryCount = data.file_history_count || 0;
                const cofoCount = data.cofo_count || 0;
                const praCount = data.pra_count || 0;

                if (fileHistoryCount > 0) {
                    toastParts.push('<div class="flex items-center gap-2 mb-1"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> <span>' + fileHistoryCount + ' transaction' + (fileHistoryCount > 1 ? 's' : '') + ' &rarr; <b>File History</b></span></div>');
                }
                if (cofoCount > 0) {
                    toastParts.push('<div class="flex items-center gap-2 mb-1"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> <span>' + cofoCount + ' CofO transaction' + (cofoCount > 1 ? 's' : '') + ' &rarr; <b>CofO</b></span></div>');
                }
                if (praCount > 0) {
                    toastParts.push('<div class="flex items-center gap-2 mb-1"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg> <span>' + praCount + ' OP transaction' + (praCount > 1 ? 's' : '') + ' &rarr; <b>PRA</b></span></div>');
                }

                const detailHtml = toastParts.length > 0
                    ? '<div style="text-align:left; margin-top:8px;">' + toastParts.join('') + '</div>'
                    : '';

                // When the indexing form was opened from another page (e.g. the
                // New KANGIS tracker) via ?return_to=..., redirect back after a
                // successful transaction save with the (possibly new) file
                // number / tracking id so the originating screen can resume.
                const buildReturnUrl = () => {
                    if (!window.returnToUrl) return null;
                    const params = new URLSearchParams();
                    if (fileIndexingData.file_number) params.set('file_number', fileIndexingData.file_number);
                    if (fileIndexingData.tracking_id) params.set('tracking_id', fileIndexingData.tracking_id);
                    if (fileIndexingData.file_title)  params.set('file_title',  fileIndexingData.file_title);
                    const separator = window.returnToUrl.includes('?') ? '&' : '?';
                    return window.returnToUrl + separator + params.toString();
                };

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved Successfully!',
                        html: (response.message || 'Property transaction details saved successfully!') + detailHtml,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        // Re-enable in case user doesn't close modal (though we do close it)
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerText = originalBtnText;
                        }
                        closePropertyTransactionModal();
                        if (typeof checkExistingPropertyRecords === 'function') {
                            checkExistingPropertyRecords();
                        }
                        const returnUrl = buildReturnUrl();
                        if (returnUrl) {
                            window.location.href = returnUrl;
                        }
                    });
                } else {
                    alert(response.message || 'Property transaction details saved successfully!');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerText = originalBtnText;
                    }
                    closePropertyTransactionModal();
                    if (typeof checkExistingPropertyRecords === 'function') {
                        checkExistingPropertyRecords();
                    }
                    const returnUrl = buildReturnUrl();
                    if (returnUrl) {
                        window.location.href = returnUrl;
                    }
                }
            },
            error: function (xhr, status, error) {
                console.error('Error:', error);
                console.error('Response:', xhr.responseText);
                console.error('Full XHR:', xhr);

                // Re-enable button on error
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerText = originalBtnText;
                }

                let errorMessage = 'An error occurred while saving transaction details.';
                let errorDetails = '';

                if (xhr.responseJSON) {
                    console.error('Response JSON:', xhr.responseJSON);

                    if (xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }

                    // Show validation errors if present
                    if (xhr.responseJSON.errors) {
                        errorDetails = '<ul style="text-align: left; margin-top: 10px;">';
                        Object.keys(xhr.responseJSON.errors).forEach(field => {
                            const messages = xhr.responseJSON.errors[field];
                            messages.forEach(msg => {
                                errorDetails += `<li>${msg}</li>`;
                            });
                        });
                        errorDetails += '</ul>';
                    }
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        html: errorMessage + errorDetails,
                        confirmButtonText: 'OK',
                        width: '600px'
                    });
                } else {
                    alert(errorMessage + (errorDetails ? '\n\nValidation Errors:\n' + errorDetails.replace(/<[^>]*>/g, '') : ''));
                }
            }
        });
    }



    // Initialize close button handlers when DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        const propertyTransactionDialog = document.getElementById('property-transaction-dialog');
        const closePropertyTransactionBtn = document.getElementById('close-property-transaction-form');
        const cancelPropertyTransactionBtn = document.getElementById('cancel-property-transaction');

        // Close modal handlers
        if (closePropertyTransactionBtn) {
            closePropertyTransactionBtn.addEventListener('click', function () {
                closePropertyTransactionModal();
            });
        }

        if (cancelPropertyTransactionBtn) {
            cancelPropertyTransactionBtn.addEventListener('click', function () {
                closePropertyTransactionModal();
            });
        }

        // Close on overlay click
        if (propertyTransactionDialog) {
            propertyTransactionDialog.addEventListener('click', function (e) {
                if (e.target === propertyTransactionDialog) {
                    closePropertyTransactionModal();
                }
            });
        }

        

    });
</script>