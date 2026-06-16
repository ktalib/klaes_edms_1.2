<template x-for="(param, index) in fileParams" :key="param.id">
    <div x-show="index === activeTab" class="rounded-lg border border-gray-200 bg-white p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-2 min-w-0">
                <i data-lucide="map-pin" class="h-4 w-4 text-emerald-600 flex-shrink-0"></i>
                <h4 class="text-sm font-bold uppercase tracking-wide text-gray-700 whitespace-nowrap">
                    Property Details
                </h4>
                <span x-show="fileParams.length > 1" 
                      x-text="'(File ' + (index+1) + '/' + fileParams.length + ')'" 
                      class="text-xs font-medium text-gray-400 whitespace-nowrap ml-1"></span>
            </div>
             
            <div class="flex items-center gap-3">
                <!-- Apply to All Files in Batch Toggle -->
                <div x-show="indexing_type === 'Block' && fileParams.length > 1" 
                     class="flex items-center gap-2 px-3 py-1.5 bg-emerald-50 border border-emerald-100 rounded-lg hover:bg-emerald-100 transition-colors shadow-sm cursor-pointer"
                     @click="$refs.propertyCheck.click()">
                    <input type="checkbox" id="applyPropertyToAllBatch" x-model="applyPropertyToAll" x-ref="propertyCheck"
                           class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300 rounded cursor-pointer"
                           @click.stop>
                    <label for="applyPropertyToAllBatch" class="text-[11px] font-bold text-emerald-700 cursor-pointer select-none whitespace-nowrap">
                        Apply to All
                    </label>
                </div>

                <div class="flex items-center gap-1.5" x-show="fileParams.length > 1">
                    <button type="button" @click="activeTab = Math.max(0, activeTab - 1)" 
                        :disabled="activeTab === 0"
                        class="p-1.5 rounded-md text-indigo-600 bg-indigo-50 hover:bg-indigo-100 disabled:opacity-30 disabled:cursor-not-allowed transition-all border border-indigo-100"
                        title="Previous File">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="m15 18-6-6 6-6"/></svg>
                    </button>
                    
                    <button type="button" @click="activeTab = Math.min(fileParams.length - 1, activeTab + 1)"
                        :disabled="activeTab === fileParams.length - 1"
                        class="p-1.5 rounded-md text-indigo-600 bg-indigo-50 hover:bg-indigo-100 disabled:opacity-30 disabled:cursor-not-allowed transition-all border border-indigo-100"
                        title="Next File">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Apply to Batch Button (shown when toggle is enabled) -->
        <div x-show="indexing_type === 'Block' && fileParams.length > 1 && applyPropertyToAll" class="mb-6">
            <button type="button" 
                    @click="applyPropertyDetailsToAllFiles()" 
                    class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm transition-all flex items-center justify-center space-x-2">
                <i data-lucide="copy" class="w-4 h-4"></i>
                <span>Apply Current Property Details to All <span x-text="fileParams.length"></span> Files</span>
            </button>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label class="block text-sm font-medium text-gray-700 mb-2">Land Use Type <span class="text-red-500">*</span></label>
                <select class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" :id="'land-use-type-' + index" :name="'land_use_type[' + index + ']'" x-model="param.land_use_type" required aria-required="true">
                    <option value="">Select Land Use Type</option>
                    @if(isset($landUseTypes))
                        @foreach($landUseTypes as $key => $label)
                            <option value="{{ $key }}">
                                {{ $label }} 
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="form-group">
                <label class="block text-sm font-medium text-gray-700 mb-2">Plot Number</label>
                <input type="text" :id="'plot-number-' + index" :name="'plot_number[' + index + ']'" x-model="param.plot_number" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label class="block text-sm font-medium text-gray-700 mb-2">TP Number</label>
                <input type="text" :id="'tp-number-' + index" :name="'tp_number[' + index + ']'" x-model="param.tp_number" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div class="form-group">
                <label class="block text-sm font-medium text-gray-700 mb-2">LPKN Number</label>
                <input type="text" :id="'lpkn-no-' + index" :name="'lpkn_no[' + index + ']'" x-model="param.lpkn_no" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label class="block text-sm font-medium text-gray-700 mb-2">Street Name</label>
                <select class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" :id="'street-name-' + index" :name="'street_name[' + index + ']'" x-model="param.street_name" style="text-transform: uppercase;">
                    <option value="">Select Street Name</option>
                    @if(isset($streetNames))
                        @foreach($streetNames as $streetName)
                            <option value="{{ $streetName->name }}">
                                {{ $streetName->name }}
                            </option>
                        @endforeach
                    @endif
                    <option value="Other">Other</option>
                </select>
                <div :id="'custom-street-name-container-' + index" x-show="param.street_name === 'Other' || param.street_name === 'other'" style="margin-top: 0.5rem;" x-cloak>
                    <input type="text" :id="'custom-street-name-input-' + index" :name="'custom_street_name[' + index + ']'" x-model="param.custom_street_name" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Specify street name..." style="text-transform: uppercase;">
                </div>
            </div>
            <div class="form-group">
                <label class="block text-sm font-medium text-gray-700 mb-2">District</label>
                <select class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" :id="'district-select-' + index" :name="'district[' + index + ']'" x-model="param.district" style="text-transform: uppercase;">
                    <option value="">Select District</option>
                    @if(isset($districts))
                        @foreach($districts as $districtName)
                            <option value="{{ $districtName }}">
                                {{ $districtName }}
                            </option>
                        @endforeach
                    @endif
                </select>
                <div :id="'custom-district-container-' + index" x-show="param.district === 'Other' || param.district === 'other'" style="margin-top: 0.5rem;" x-cloak>
                    <input type="text" :id="'custom-district-input-' + index" :name="'custom_district[' + index + ']'" x-model="param.custom_district" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Enter district name" style="text-transform: uppercase;">
                </div>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label class="block text-sm font-medium text-gray-700 mb-2">LGA</label>
                <select :id="'lga-city-' + index" :name="'lga[' + index + ']'" x-model="param.lga" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" style="text-transform: uppercase;">
                    <option value="">Select LGA</option>
                    @if(isset($lgas))
                        @foreach($lgas as $lgaName)
                            <option value="{{ $lgaName }}">
                                {{ $lgaName }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="form-group">
                <label class="block text-sm font-medium text-gray-700 mb-2">Location</label>
                <input type="text" :id="'location-' + index" name="location[]" x-model="param.location" 
                    x-effect="
                        let parts = [];
                        if (param.plot_number) parts.push('PLOTNO: ' + param.plot_number);

                        if (param.lga && !['', 'Select LGA'].includes(param.lga)) {
                            parts.push('LGA: ' + param.lga);
                        }

                        if (parts.length > 0) {
                            parts.push('STATE: KANO');
                            param.location = parts.join(', ').toUpperCase();
                        }
                    "
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm bg-gray-50" readonly placeholder="Auto-generated as PLOTNO, LGA, STATE" style="cursor: default; text-transform: uppercase;">
            </div>
            <div class="form-group">
                <label class="block text-sm font-medium text-gray-700 mb-2">Plot Size</label>
                <input type="text" :id="'plot-size-' + index" :name="'plot_size[' + index + ']'" x-model="param.plot_size" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" style="text-transform: uppercase;">
            </div>
        </div>
    </div>
</template>


