        <!-- Primary Tab - NPFN Generation -->
        <div x-show="activeTab === 'primary'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0">
           <div class="bg-white rounded-lg shadow overflow-hidden">
                <form id="commissionPrimaryForm" method="POST" action="javascript:void(0)" enctype="multipart/form-data" onsubmit="return false;">
                    @csrf
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-bold text-center text-gray-800">Commission New ST File Number - Primary Application</h2>
                        </div>

                        <div class="mb-6">
                           
                            <p class="text-gray-600">Generate and commission a new primary file number for sectional titling</p>
                        </div>

                        {{-- Land Use Selection Section --}}
                        <div class="bg-gradient-to-r from-purple-50 to-pink-50 border border-purple-200 rounded-xl p-6 shadow-sm mb-6">
                            <div class="flex items-center mb-4">
                                <div class="bg-purple-500 p-2 rounded-lg mr-3">
                                    <i data-lucide="map" class="w-5 h-5 text-white"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Land Use Selection</h3>
                                    <p class="text-sm text-gray-600">Select the land use type for this ST file number</p>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <label class="block text-sm font-medium text-gray-700 mb-3">
                                    Land Use Type <span class="text-red-500">*</span>
                                </label>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                                    {{-- Commercial Option --}}
                                    <label class="relative flex items-center p-3 bg-white rounded-lg border-2 border-blue-400 cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition-all group selected">
                                        <input type="checkbox" name="selectedLandUse" class="sr-only" value="COMMERCIAL" checked onchange="handleLandUseChange(this)">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center group-hover:bg-blue-200 transition-all">
                                                <i data-lucide="building-2" class="w-4 h-4 text-blue-600"></i>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="font-medium text-gray-900 text-sm">Commercial</span>
                                                <span class="text-xs text-blue-600 font-semibold bg-blue-100 px-2 py-1 rounded">COM</span>
                                            </div>
                                        </div>
                                        <div class="absolute top-1 right-1 w-4 h-4 bg-blue-600 rounded-full items-center justify-center hidden group-[.selected]:flex">
                                            <i data-lucide="check" class="w-2.5 h-2.5 text-white"></i>
                                        </div>
                                    </label>
                                    
                                    {{-- Residential Option --}}
                                    <label class="relative flex items-center p-3 bg-white rounded-lg border-2 border-gray-200 cursor-pointer hover:border-green-500 hover:bg-green-50 transition-all group">
                                        <input type="checkbox" name="selectedLandUse" class="sr-only" value="RESIDENTIAL" onchange="handleLandUseChange(this)">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center group-hover:bg-green-200 transition-all">
                                                <i data-lucide="home" class="w-4 h-4 text-green-600"></i>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="font-medium text-gray-900 text-sm">Residential</span>
                                                <span class="text-xs text-green-600 font-semibold bg-green-100 px-2 py-1 rounded">RES</span>
                                            </div>
                                        </div>
                                        <div class="absolute top-1 right-1 w-4 h-4 bg-green-600 rounded-full items-center justify-center hidden group-[.selected]:flex">
                                            <i data-lucide="check" class="w-2.5 h-2.5 text-white"></i>
                                        </div>
                                    </label>
                                    
                                    {{-- Industrial Option --}}
                                    <label class="relative flex items-center p-3 bg-white rounded-lg border-2 border-gray-200 cursor-pointer hover:border-orange-500 hover:bg-orange-50 transition-all group">
                                        <input type="checkbox" name="selectedLandUse" class="sr-only" value="INDUSTRIAL" onchange="handleLandUseChange(this)">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center group-hover:bg-orange-200 transition-all">
                                                <i data-lucide="factory" class="w-4 h-4 text-orange-600"></i>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="font-medium text-gray-900 text-sm">Industrial</span>
                                                <span class="text-xs text-orange-600 font-semibold bg-orange-100 px-2 py-1 rounded">IND</span>
                                            </div>
                                        </div>
                                        <div class="absolute top-1 right-1 w-4 h-4 bg-orange-600 rounded-full items-center justify-center hidden group-[.selected]:flex">
                                            <i data-lucide="check" class="w-2.5 h-2.5 text-white"></i>
                                        </div>
                                    </label>
                                    
                                    {{-- Mixed Use Option --}}
                                    <label class="relative flex items-center p-3 bg-white rounded-lg border-2 border-gray-200 cursor-pointer hover:border-purple-500 hover:bg-purple-50 transition-all group">
                                        <input type="checkbox" name="selectedLandUse" class="sr-only" value="MIXED" onchange="handleLandUseChange(this)">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center group-hover:bg-purple-200 transition-all">
                                                <i data-lucide="layers" class="w-4 h-4 text-purple-600"></i>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="font-medium text-gray-900 text-sm">Mixed Use</span>
                                                <span class="text-xs text-purple-600 font-semibold bg-purple-100 px-2 py-1 rounded">MIXED</span>
                                            </div>
                                        </div>
                                        <div class="absolute top-1 right-1 w-4 h-4 bg-purple-600 rounded-full items-center justify-center hidden group-[.selected]:flex">
                                            <i data-lucide="check" class="w-2.5 h-2.5 text-white"></i>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mt-4 p-3 bg-purple-50 border border-purple-200 rounded-lg">
                                <div class="flex items-start">
                                    <i data-lucide="info" class="w-4 h-4 text-purple-600 mr-2 mt-0.5 flex-shrink-0"></i>
                                    <p class="text-xs text-purple-800">
                                        Select one land use type. The file number format will update to reflect your selection (ST-{CODE}-{YEAR}-{SERIAL}).
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Hidden Fields --}}
                        <input type="hidden" name="land_use" value="COMMERCIAL" id="hiddenLandUse">
                        <input type="hidden" name="np_fileno" value="{{ $npFileNo ?? '' }}">
                        <input type="hidden" name="serial_no" value="{{ $serialNo ?? '' }}">
                        <input type="hidden" name="current_year" value="{{ $currentYear ?? date('Y') }}">
                        <input type="hidden" name="draft_id" id="draftIdInput" value="{{ $draftMeta['draft_id'] ?? '' }}">
                        <input type="hidden" name="draft_version" id="draftVersionInput" value="{{ $draftMeta['version'] ?? 1 }}">
                        <input type="hidden" name="draft_last_completed_step" id="draftStepInput" value="{{ $draftMeta['last_completed_step'] ?? 1 }}">
                        <input type="hidden" name="tracking_id" id="primary-tracking-id" value="{{ $trackingId ?? '' }}">
                        
                        <div class="mb-6">
                             
                            <hr class="my-4">
                          

                            {{-- File Numbers Section --}}
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-6 px-4">
                                {{-- New Primary FileNo (NPFN) Card --}}
                                <div class="bg-gradient-to-br from-blue-50 via-indigo-50 to-blue-100 border border-blue-200 rounded-xl p-10 shadow-lg min-h-[450px] w-full max-w-none">
                                    <div class="flex items-center mb-4">
                                        <div class="bg-blue-500 p-3 rounded-full mr-4 shadow-md">
                                            <i data-lucide="file-text" class="w-6 h-6 text-white"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="text-lg font-semibold text-gray-900">New Primary FileNo</h3>
                                            <p class="text-sm text-gray-600">Auto-generated identifier</p>
                                        </div>
                                        <div class="bg-green-500 px-3 py-1 rounded-full shadow-sm">
                                            <span class="text-white text-xs font-medium">AUTO</span>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-white rounded-lg border border-blue-100 p-6 shadow-inner">
                                                                            <label class="block text-sm font-medium text-gray-700 mb-3">Generated FileNo (NPFN)</label>
                                                                            <div class="mb-4">
                                                                                <div class="w-full px-4 py-2 bg-gray-100 rounded-lg">
                                                                                    <div class="text-gray-700 font-mono text-sm font-bold">
                                                                                        <div class="flex items-center">
                                                                                            <i data-lucide="file-search" class="h-4 w-4 mr-1 flex-shrink-0"></i>
                                                                                            <span class="whitespace-nowrap">Tracking ID: <span id="primary-tracking-display" class="text-red-600 font-bold">{{ 'Awaiting selection' }}</span></span>
                                                                                        </div>        </div>
                                                                                </div>
                                                                            </div>

                                                                            <div class="relative">
                                                                                <input type="text" 
                                                                                       name="np_fileno"
                                                                                       id="np-fileno-display"
                                                                                       class="w-full px-4 py-4 bg-blue-50 border-2 border-blue-200 rounded-lg text-blue-900 font-mono text-xl font-bold cursor-not-allowed transition-all" 
                                                                                       value="{{ $npFileNo ?? 'ST-COM-'.date('Y').'-01' }}" 
                                                                                       readonly
                                                                                       title="New Primary FileNo - Auto Generated">
                                                                                <div class="absolute inset-y-0 right-0 flex items-center pr-4">
                                                                                    <i data-lucide="lock" class="w-6 h-6 text-blue-500"></i>
                                                                                </div>
                                                                            </div>
                                                                            
                                                                            {{-- Components Display --}}
                                                                            <div class="mt-6 space-y-3">
                                                                                <div class="flex justify-between items-center text-sm bg-gray-50 px-3 py-2 rounded">
                                                                                    <span class="text-gray-600">Prefix:</span>
                                                                                    <span class="font-semibold text-blue-700">ST</span>
                                                                                </div>
                                                                                <div class="flex justify-between items-center text-sm bg-gray-50 px-3 py-2 rounded">
                                                                                    <span class="text-gray-600">Land Use:</span>
                                                                                    <span class="font-semibold text-blue-700 land-use-code-display">
                                                                                        COM
                                                                                    </span>
                                                                                </div>
                                                                                <div class="flex justify-between items-center text-sm bg-gray-50 px-3 py-2 rounded">
                                                                                    <span class="text-gray-600">Year:</span>
                                                                                    <span class="font-semibold text-blue-700">{{ $currentYear ?? date('Y') }}</span>
                                                                                </div>
                                                                                <div class="flex justify-between items-center text-sm bg-gray-50 px-3 py-2 rounded">
                                                                                    <span class="text-gray-600">Serial:</span>
                                                                                    <span id="serial-number-display" class="font-semibold text-blue-700">{{ $serialNo ?? '1' }}</span>
                                                                                </div>
                                                                            </div>
                                                                            
                                                                            <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                                                                <div class="flex items-start">
                                                                                    <i data-lucide="info" class="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0"></i>
                                                                                    <p class="text-xs text-blue-800">
                                                                                        Primary identifier for this application and all unit applications.
                                                                                    </p>
                                                                                </div>
                                                                            </div>
                                                                        </div>        </div>

                                {{-- File Number Information Card --}}
                                <div class="bg-gradient-to-br from-green-50 via-emerald-50 to-green-100 border border-green-200 rounded-xl p-10 shadow-lg min-h-[450px]">
                                    <div class="flex items-center mb-4">
                                        <div class="bg-green-500 p-3 rounded-full mr-4 shadow-md">
                                            <i data-lucide="search" class="w-6 h-6 text-white"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="text-lg font-semibold text-gray-900">File Number Information</h3>
                                            <p class="text-sm text-gray-600">Select existing file number</p>
                                        </div>
                                        <div class="bg-orange-500 px-3 py-1 rounded-full shadow-sm">
                                            <span class="text-white text-xs font-medium">SELECT</span>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-white rounded-lg border border-green-100 p-6 shadow-inner">
                                        <div class="flex items-center justify-between mb-4">
                                            <label class="block text-sm font-medium text-gray-700">
                                                <i data-lucide="hash" class="w-4 h-4 inline mr-1"></i>
                                                Applied File Number
                                            </label>
                                        </div>

                                        <div class="relative">
                                            <select id="applied-file-number"
                                                    name="applied_file_number"
                                                    class="w-full bg-gray-50 border-2 border-green-200 rounded-lg text-gray-900 font-mono text-lg select2-file-number"
                                                    style="width:100%">
                                                <option value="">Search and select file number...</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mt-4 p-3 bg-green-50 rounded-lg border border-green-200">
                                            <div class="flex items-start">
                                                <i data-lucide="info" class="w-4 h-4 text-green-600 mr-2 mt-0.5 flex-shrink-0"></i>
                                                <p class="text-xs text-green-800">
                                                    Link this application to an existing file number from the registry.
                                                </p>
                                            </div>
                                        </div>

                                        {{-- Allocation Type Section (inside green card) --}}
                                        <div class="mt-6 pt-6 border-t border-green-200">
                                            <div class="flex items-center mb-4">
                                                <div class="bg-orange-500 p-2 rounded-lg mr-2">
                                                    <i data-lucide="clipboard-list" class="w-4 h-4 text-white"></i>
                                                </div>
                                                <div>
                                                    <h4 class="text-sm font-semibold text-gray-900">Allocation Type <span class="text-red-500">*</span></h4>
                                                    <p class="text-xs text-gray-600">Select the type of application</p>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-center space-x-6 bg-white rounded-lg p-3 border border-gray-200">
                                                {{-- Direct Allocation Option --}}
                                                <label class="flex items-center cursor-pointer">
                                                    <input type="radio" 
                                                           name="application_type" 
                                                           value="Direct Allocation" 
                                                           checked 
                                                           onchange="handlePrimaryApplicationTypeChange(this)"
                                                           class="w-4 h-4 text-green-600 border-gray-300 focus:ring-green-500">
                                                    <span class="ml-2 text-sm font-medium text-gray-900">Direct Allocation</span>
                                                </label>
                                                
                                                {{-- Conversion Option --}}
                                                <label class="flex items-center cursor-pointer">
                                                    <input type="radio" 
                                                           name="application_type" 
                                                           value="Conversion" 
                                                           onchange="handlePrimaryApplicationTypeChange(this)"
                                                           class="w-4 h-4 text-green-600 border-gray-300 focus:ring-green-500">
                                                    <span class="ml-2 text-sm font-medium text-gray-900">Conversion</span>
                                                </label>
                                            </div>
                                            
                                            <div class="mt-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                                <div class="flex items-start">
                                                    <i data-lucide="info" class="w-4 h-4 text-yellow-700 mr-2 mt-0.5 flex-shrink-0"></i>
                                                    <p class="text-xs text-yellow-800">
                                                        <strong>Direct Allocation:</strong> New application with immediate file number assignment. 
                                                        <strong>Conversion:</strong> Converting an existing application.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                          
                            {{-- Primary Tab Applicant Information --}}
                            @include('commission_new_st.partials.primary-applicant', ['titles' => $titles ?? []])

                            {{-- Location Details Section --}}
                            <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 mb-6">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center">
                                        <div class="bg-teal-500 p-2 rounded-lg mr-3">
                                            <i data-lucide="map-pin" class="w-5 h-5 text-white"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">Location Details</h3>
                                            <p id="locationDetailsHint" class="text-sm text-gray-600">Backfilled automatically from the selected existing file number (read-only)</p>
                                        </div>
                                    </div>
                                    <button type="button" id="locationDetailsEditBtn" onclick="toggleLocationDetailsEdit()" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-teal-700 bg-teal-50 border border-teal-200 rounded-md hover:bg-teal-100">
                                        <i data-lucide="pencil" class="w-4 h-4 mr-1"></i>
                                        Edit
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm mb-1">House No.</label>
                                        <input type="text" id="propertyHouseNo" name="property_house_no" class="location-detail-field w-full p-2 border border-gray-300 rounded-md bg-gray-100 text-gray-500 cursor-not-allowed" placeholder="ENTER HOUSE NUMBER" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase(); updatePropertyAddressDisplay();" disabled>
                                    </div>
                                    <div>
                                        <label class="block text-sm mb-1">Plot No.</label>
                                        <input type="text" id="propertyPlotNo" name="property_plot_no" class="location-detail-field w-full p-2 border border-gray-300 rounded-md bg-gray-100 text-gray-500 cursor-not-allowed" placeholder="ENTER PLOT NUMBER" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase(); updatePropertyAddressDisplay();" disabled>
                                    </div>
                                    <div>
                                        <label class="block text-sm mb-1">Street Name</label>
                                        <input type="text" id="propertyStreetName" name="property_street_name" class="location-detail-field w-full p-2 border border-gray-300 rounded-md bg-gray-100 text-gray-500 cursor-not-allowed" placeholder="ENTER STREET NAME" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase(); updatePropertyAddressDisplay();" disabled>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm mb-1">District</label>
                                        <input type="text" id="propertyDistrict" name="property_district" class="location-detail-field w-full p-2 border border-gray-300 rounded-md bg-gray-100 text-gray-500 cursor-not-allowed" placeholder="ENTER DISTRICT" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase(); updatePropertyAddressDisplay();" disabled>
                                    </div>
                                    <div>
                                        <label class="block text-sm mb-1">LGA</label>
                                        <select id="propertyLga" name="property_lga" class="location-detail-field w-full p-2 border border-gray-300 rounded-md bg-gray-100 text-gray-500 cursor-not-allowed" onchange="updatePropertyAddressDisplay();" disabled>
                                            <option value="">Select LGA</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm mb-1">State</label>
                                        <select id="propertyState" name="property_state" class="location-detail-field w-full p-2 border border-gray-300 rounded-md bg-gray-100 text-gray-500 cursor-not-allowed" onchange="selectPropertyLGA(this); updatePropertyAddressDisplay();" disabled>
                                            <option value="">Select State</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm mb-1">Property Address:</label>
                                    <div id="propertyAddressPreview" class="p-2 bg-gray-100 border border-gray-300 rounded-md min-h-[40px]">
                                        <span id="fullPropertyAddress" style="display: block; padding: 4px; text-transform: uppercase;"></span>
                                    </div>
                                    <input type="hidden" name="property_address" id="propertyAddressDisplay">
                                </div>
                            </div>

                        {{-- Commission Information & Generate ST FileNo Card --}}
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-8 shadow-sm mt-6">
                            {{-- Grid Layout --}}
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                
                                {{-- Left Column: Commission Information --}}
                                <div class="space-y-6">
                                    <div class="flex items-center mb-4">
                                        <div class="bg-green-500 p-2 rounded-lg mr-3">
                                            <i data-lucide="user-check" class="w-5 h-5 text-white"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">Commission Information</h3>
                                            <p class="text-sm text-gray-600">Process details</p>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-4">
                                        {{-- Commissioned By --}}
                                        <div class="relative">
                                            <label class="flex items-center text-sm font-medium text-gray-700 mb-2">
                                                <i data-lucide="user" class="w-4 h-4 mr-2 text-green-600"></i>
                                                Commissioned By
                                            </label>
                                            <div class="relative">
                                                <input type="text" 
                                                       class="w-full pl-10 pr-4 py-3 bg-white border border-blue-200 rounded-lg cursor-not-allowed" 
                                                       value="{{ Auth::user()->name ?? 'System User' }}" 
                                                       name="commissioned_by" 
                                                       readonly
                                                       disabled>
                                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                    <i data-lucide="lock" class="w-4 h-4 text-blue-400"></i>
                                                </div>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-1">Current user commissioning the file number</p>
                                        </div>
                                        
                                        {{-- Commissioned Date --}}
                                        <div class="relative">
                                            <label class="flex items-center text-sm font-medium text-gray-700 mb-2">
                                                <i data-lucide="calendar-clock" class="w-4 h-4 mr-2 text-blue-600"></i>
                                                Commissioned Date
                                            </label>
                                            <div class="relative">
                                                <input type="date" 
                                                       class="w-full pl-10 pr-4 py-3 bg-white border border-blue-200 rounded-lg cursor-not-allowed" 
                                                       value="{{ date('Y-m-d') }}" 
                                                       name="commissioned_date" 
                                                       readonly
                                                       disabled>
                                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                    <i data-lucide="lock" class="w-4 h-4 text-blue-400"></i>
                                                </div>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-1">Date when file number is commissioned</p>
                                        </div>
                                    </div>
                                    
                                    {{-- Info Alert --}}
                                    <div class="p-3 bg-blue-100 border border-blue-300 rounded-lg">
                                        <div class="flex items-start">
                                            <i data-lucide="info" class="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0"></i>
                                            <p class="text-xs text-blue-800">
                                                Commission information is automatically captured when the file number is generated.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                {{-- Right Column: Ready to Generate --}}
                                <div class="flex flex-col justify-center items-center text-center space-y-6">
                                    <!-- Status Indicator -->
                                    <div class="flex items-center justify-center">
                                        <div class="bg-blue-500 p-4 rounded-full shadow-md">
                                            <i data-lucide="shield-check" class="w-8 h-8 text-white"></i>
                                        </div>
                                    </div>
                                    
                                    <!-- Info Text -->
                                    <div class="space-y-3">
                                        <h4 class="text-xl font-semibold text-gray-900">Ready to Generate</h4>
                                        <div class="flex items-center justify-center text-sm text-blue-700 bg-blue-200 px-4 py-2 rounded-full">
                                            <i data-lucide="info" class="w-4 h-4 mr-2"></i>
                                            File number will be reserved upon generation
                                        </div>
                                        <p class="text-sm text-gray-600">This action will create a new ST file number in the system</p>
                                    </div>
                                    
                                    <!-- Action Button -->
                                    <button type="button" 
                                            class="group relative px-10 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-lg font-semibold rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-blue-300"
                                            onclick="event.preventDefault(); commissionFileNumber(); return false;"
                                            id="generateSTFileNoBtn">
                                        <div class="flex items-center justify-center">
                                            <div class="bg-white/20 p-2 rounded-lg mr-3 group-hover:bg-white/30 transition-all">
                                                <i data-lucide="file-plus" class="w-5 h-5"></i>
                                            </div>
                                            <span>Generate ST FileNo</span>
                                        </div>
                                        
                                        <!-- Loading State (hidden by default) -->
                                        <div class="absolute inset-0 bg-blue-700 rounded-xl flex items-center justify-center opacity-0 group-disabled:opacity-100 transition-opacity">
                                            <div class="flex items-center">
                                                <div class="animate-spin rounded-full h-5 w-5 border-2 border-white border-t-transparent mr-3"></div>
                                                <span>Generating...</span>
                                            </div>
                                        </div>
                                    </button>
                                    
                                    <!-- Security Notice -->
                                    <div class="p-4 bg-white/60 border border-blue-200 rounded-lg">
                                        <div class="flex items-start text-xs text-gray-600">
                                            <i data-lucide="lock" class="w-4 h-4 mr-2 mt-0.5 text-blue-500 flex-shrink-0"></i>
                                            <div class="text-left">
                                                <p class="font-medium text-gray-700 mb-1">Secure Generation Process</p>
                                                <p>File numbers are generated using atomic reservation to prevent duplicates and ensure data integrity.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>        </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        if (typeof $ === 'undefined' || !$.fn.select2) {
            console.warn('Select2 or jQuery not loaded for primary file number dropdown.');
            return;
        }

        $('#applied-file-number').select2({
            placeholder: 'Search file number...',
            allowClear: true,
            width: '100%',
            ajax: {
                url: '{{ route("file-indexing.search") }}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { search: params.term, per_page: 20 };
                },
                processResults: function (data) {
                    return {
                        results: $.map(data.data || [], function (item) {
                            return {
                                id: item.file_number,
                                text: item.file_number + (item.file_title ? ' - ' + item.file_title : ''),
                                file_title: item.file_title || '',
                                tracking_id: item.tracking_id || '',
                                district: item.district || '',
                                lga: item.lga || '',
                                street_name: item.street_name || '',
                                plot_number: item.plot_number || '',
                                location: item.location || ''
                            };
                        })
                    };
                },
                cache: true
            },
            minimumInputLength: 2
        }).on('select2:select', function (e) {
            var data = e.params.data;
            var el = document.getElementById('applied-file-number');
            if (el) {
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (typeof loadPrimaryTrackingId === 'function') {
                loadPrimaryTrackingId(data.id);
            }

            
            // ---- Populate Tracking ID from indexing record ----
            var trackingDisplay = document.getElementById('primary-tracking-display');
            if (trackingDisplay) {
                if (data.tracking_id) {
                    trackingDisplay.textContent = data.tracking_id;
                    trackingDisplay.classList.remove('text-red-600');
                    trackingDisplay.classList.add('text-green-600');
                } else {
                    trackingDisplay.textContent = 'Not found in registry';
                    trackingDisplay.classList.remove('text-green-600');
                    trackingDisplay.classList.add('text-red-600');
                }
            }

            // ---- Auto-fill Applicant Information ----
            var titleStr = (data.file_title || '').trim();
            if (titleStr) {
                // Corporate
                var corp = document.getElementById('primary_corporate_name');
                if (corp) corp.value = titleStr;

                // Individual / Multiple: split first word as first name, rest as surname
                var parts = titleStr.split(/\s+/).filter(Boolean);
                var firstName = parts[0] || '';
                var lastName  = parts.length > 1 ? parts.slice(1).join(' ') : '';

                var fn1 = document.getElementById('primary_first_name');
                var ln1 = document.getElementById('primary_last_name');
                if (fn1) fn1.value = firstName;
                if (ln1) ln1.value = lastName;

                var fn2 = document.getElementById('primary_owner_first_name');
                var ln2 = document.getElementById('primary_owner_last_name');
                if (fn2) fn2.value = firstName;
                if (ln2) ln2.value = lastName;
            }

            // ---- Backfill Location Details from the selected file's indexing record ----
            if (typeof backfillLocationDetails === 'function') {
                backfillLocationDetails(data);
            }

        }).on('select2:unselect', function () {
            var el = document.getElementById('applied-file-number');
            if (el) {
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (typeof resetPrimaryTracking === 'function') resetPrimaryTracking();

            // Reset tracking display
            var trackingDisplay2 = document.getElementById('primary-tracking-display');
            if (trackingDisplay2) {
                trackingDisplay2.textContent = 'Awaiting selection';
                trackingDisplay2.classList.remove('text-green-600');
                trackingDisplay2.classList.add('text-red-600');
            }

            // Clear applicant fields
            ['primary_first_name','primary_last_name','primary_corporate_name',
             'primary_owner_first_name','primary_owner_last_name'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.value = '';
            });

            // Clear location fields
            ['propertyHouseNo','propertyPlotNo','propertyStreetName','propertyDistrict'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.value = '';
            });
            var lgaClear = document.getElementById('propertyLga');
            if (lgaClear) lgaClear.value = '';
            var stateClear = document.getElementById('propertyState');
            if (stateClear) stateClear.value = '';
            if (typeof updatePropertyAddressDisplay === 'function') {
                updatePropertyAddressDisplay();
            }
        });
    }, 500);
});

/**
 * Update the Property Address preview from the Location Details fields.
 */
function updatePropertyAddressDisplay() {
    var houseNo = document.getElementById('propertyHouseNo')?.value || '';
    var streetName = document.getElementById('propertyStreetName')?.value || '';
    var district = document.getElementById('propertyDistrict')?.value || '';
    var lga = document.getElementById('propertyLga')?.value || '';
    var state = document.getElementById('propertyState')?.value || '';

    var fullAddress = [houseNo, streetName, district, lga, state]
        .filter(function (part) { return part && part.trim() !== ''; })
        .join(', ');

    var addressHidden = document.getElementById('propertyAddressDisplay');
    if (addressHidden) addressHidden.value = fullAddress;

    var addressSpan = document.getElementById('fullPropertyAddress');
    if (addressSpan) addressSpan.textContent = fullAddress || 'Enter property details above';
}
window.updatePropertyAddressDisplay = updatePropertyAddressDisplay;

/**
 * Toggle the Location Details fields between the default read-only
 * (backfilled) state and an editable state, via the section's Edit button.
 */
function toggleLocationDetailsEdit() {
    var btn = document.getElementById('locationDetailsEditBtn');
    var hint = document.getElementById('locationDetailsHint');
    var fields = document.querySelectorAll('.location-detail-field');
    var willEnable = fields.length ? fields[0].disabled : true;

    fields.forEach(function (field) {
        field.disabled = !willEnable;
        field.classList.toggle('bg-gray-100', !willEnable);
        field.classList.toggle('text-gray-500', !willEnable);
        field.classList.toggle('cursor-not-allowed', !willEnable);
        field.classList.toggle('bg-white', willEnable);
        field.classList.toggle('text-gray-900', willEnable);
        field.classList.toggle('cursor-text', willEnable);
    });

    if (btn) {
        btn.innerHTML = willEnable
            ? '<i data-lucide="lock" class="w-4 h-4 mr-1"></i>Done'
            : '<i data-lucide="pencil" class="w-4 h-4 mr-1"></i>Edit';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    if (hint) {
        hint.textContent = willEnable
            ? 'Editing enabled - values will be used as entered'
            : 'Backfilled automatically from the selected existing file number (read-only)';
    }
}
window.toggleLocationDetailsEdit = toggleLocationDetailsEdit;

/**
 * Backfill the Location Details section from the selected file's indexing
 * record (district/lga/street_name/plot_number). The State select
 * has no source value here, so the LGA's parent state is resolved by
 * reverse-lookup against the Nigerian states/LGA reference data before the
 * LGA option list is populated and selected.
 */
function backfillLocationDetails(data) {
    var streetEl = document.getElementById('propertyStreetName');
    if (streetEl) streetEl.value = data.street_name || '';

    var plotEl = document.getElementById('propertyPlotNo');
    if (plotEl) plotEl.value = data.plot_number || '';

    var districtEl = document.getElementById('propertyDistrict');
    if (districtEl) districtEl.value = data.district || '';

    applyLgaBackfill(data.lga || '', 10);
    updatePropertyAddressDisplay();
}

function applyLgaBackfill(lgaName, attemptsLeft) {
    if (!lgaName) return;

    if (typeof nigerianStatesData === 'undefined' || !nigerianStatesData.length) {
        if (attemptsLeft > 0) {
            setTimeout(function () { applyLgaBackfill(lgaName, attemptsLeft - 1); }, 300);
        }
        return;
    }

    var matchedState = nigerianStatesData.find(function (state) {
        return (state.local_governments || []).some(function (lgaOption) {
            return lgaOption.toLowerCase() === lgaName.toLowerCase();
        });
    });

    if (!matchedState) return;

    var stateSelect = document.getElementById('propertyState');
    var lgaSelect = document.getElementById('propertyLga');
    if (!stateSelect || !lgaSelect) return;

    stateSelect.value = matchedState.name;
    if (typeof selectPropertyLGA === 'function') {
        selectPropertyLGA(stateSelect);
    }

    var matchedOption = Array.from(lgaSelect.options).find(function (opt) {
        return opt.value.toLowerCase() === lgaName.toLowerCase();
    });
    if (matchedOption) {
        lgaSelect.value = matchedOption.value;
    }

    updatePropertyAddressDisplay();
}
</script>

                </form>
            </div>
        </div>


