<div class="form-section" id="cofo-section">
    <div class="flex justify-between items-center mb-4">
        <div class="flex items-center gap-2">
            <i data-lucide="shield-check" class="h-5 w-5 text-purple-600"></i>
            <h3 class="form-section-title" style="margin-bottom: 0;">Certificate of Occupancy (CofO) Details</h3>
        </div>
        <div class="flex items-center">
            <input type="checkbox" id="has-cofo-toggle"
                class="mr-2 h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
            <label for="has-cofo-toggle" class="block text-sm font-medium text-gray-700">Has CofO</label>
        </div>
    </div>

    <hr class="mb-6 border-t border-gray-200">
    <div id="cofo-autofill-status" style="display:none;" class="text-sm text-blue-600 mb-3 text-right">
        <span class="cofo-status-content">
            <span class="cofo-status-icon"></span>
            <span class="cofo-status-text"></span>
        </span>
    </div>

    <div id="cofo-details-container" class="hidden">
        <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
                <label for="cofo-instrument-type" class="block text-sm font-medium text-gray-700 mb-2">Instrument
                    Type</label>
                <select id="cofo-instrument-type" name="cofo_instrument_type"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="">Select Instrument Type</option>
                    <option value="Certificate of Occupancy" selected>Certificate of Occupancy</option>
                    <option value="ST Certificate of Occupancy">ST Certificate of Occupancy</option>
                    <option value="SLTR Certificate of Occupancy">SLTR Certificate of Occupancy</option>
                    <option value="Customary Right of Occupancy">Customary Right of Occupancy</option>
                    <option value="Deed of Transfer">Deed of Transfer</option>
                    <option value="Deed of Assignment">Deed of Assignment</option>
                    <option value="ST Assignment">ST Assignment</option>
                    <option value="Deed of Mortgage">Deed of Mortgage</option>
                    <option value="Tripartite Mortgage">Tripartite Mortgage</option>
                    <option value="Deed of Sub Lease">Deed of Sub Lease</option>
                    <option value="Deed of Sub Under Lease">Deed of Sub Under Lease</option>
                    <option value="Power of Attorney">Power of Attorney</option>
                    <option value="Irrevocable Power of Attorney">Irrevocable Power of Attorney</option>
                    <option value="Conveyance">Conveyance</option>
                    <option value="Deed of Gift">Deed of Gift</option>
                    <option value="Court Affidavit">Court Affidavit</option>
                    <option value="Consent Judgment">Consent Judgment</option>
                    <option value="Right of Occupancy">Right of Occupancy</option>
                </select>
            </div>
            <div class="form-group">
                <label for="cofo-date" class="block text-sm font-medium text-gray-700 mb-2">CofO Date</label>
                <input type="date" id="cofo-date" name="cofo_date"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
                <label for="cofo-number" class="block text-sm font-medium text-gray-700 mb-2">CofO Number</label>
                <input type="text" id="cofo-number" name="cofo_no"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    placeholder="e.g. KNCOFO/2024/001">
            </div>
            <div class="form-group">
                <label for="cofo-land-use" class="block text-sm font-medium text-gray-700 mb-2">Documented Land
                    Use</label>
                <select id="cofo-land-use"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="">Select Documented Land Use</option>
                    @if(isset($landUseTypes))
                        @foreach($landUseTypes as $key => $label)
                            <option value="{{ $key }}" {{ (isset($record) && ($record->land_use_type == $key || $record->land_use == $key)) ? 'selected' : '' }}>
                                {{ $label }} 
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4 mt-4">
            <div class="form-group">
                <label for="cofo-serial-no" class="block text-sm font-medium text-gray-700 mb-2">Serial No</label>
                <input type="text" id="cofo-serial-no" name="cofo_serial_no"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div class="form-group">
                <label for="cofo-page-no" class="block text-sm font-medium text-gray-700 mb-2">Page No</label>
                <input type="text" id="cofo-page-no" name="cofo_page_no"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm bg-gray-50"
                    readonly style="cursor: default;">
            </div>
            <div class="form-group">
                <label for="cofo-vol-no" class="block text-sm font-medium text-gray-700 mb-2">Vol No</label>
                <input type="text" id="cofo-vol-no" name="cofo_vol_no"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
                <label for="cofo-period" class="block text-sm font-medium text-gray-700 mb-2">Lease Period</label>
                <input type="number" min="0" step="1" id="cofo-period"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    placeholder="e.g. 99">
            </div>
            <div class="form-group">
                <label for="cofo-period-unit" class="block text-sm font-medium text-gray-700 mb-2">Period Unit</label>
                <select id="cofo-period-unit"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="Years" selected>Years</option>
                    <option value="Months">Months</option>
                    <option value="Days">Days</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
                <label for="cofo-deeds-time" class="block text-sm font-medium text-gray-700 mb-2">Deeds Time</label>
                <input type="time" id="cofo-deeds-time" name="cofo_deeds_time"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div class="form-group">
                <label for="cofo-deeds-date" class="block text-sm font-medium text-gray-700 mb-2">Deeds Date</label>
                <input type="date" id="cofo-deeds-date" name="cofo_deeds_date"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
        </div>

        <div id="cofo-transaction-details" class="hidden mt-4 p-4 bg-blue-50 border border-blue-200 rounded-md">
            <h5 class="text-sm font-semibold text-blue-900 mb-3">Transaction Details</h5>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="form-group">
                    <label for="cofo-first-party" class="block text-sm font-medium text-gray-700 mb-2"
                        id="cofo-first-party-label">Grantor</label>
                    <input type="text" id="cofo-first-party" name="cofo_first_party"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div class="form-group">
                    <label for="cofo-second-party" class="block text-sm font-medium text-gray-700 mb-2"
                        id="cofo-second-party-label">Grantee</label>
                    <input type="text" id="cofo-second-party" name="cofo_second_party"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        style="text-transform: uppercase;">
                </div>
            </div>
        </div>
    </div>
</div>