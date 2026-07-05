<div class="form-section" id="occupancy-permit-section">
    <div class="flex justify-between items-center mb-4">
        <div class="flex items-center gap-2">
            <i data-lucide="stamp" class="h-5 w-5 text-amber-600"></i>
            <h3 class="form-section-title" style="margin-bottom: 0;">Occupancy Permit Details</h3>
        </div>
        <div class="flex items-center">
            <input type="checkbox" id="has-occupancy-permit-toggle"
                class="mr-2 h-4 w-4 text-amber-600 border-gray-300 rounded focus:ring-amber-500"
                {{ (isset($record) && $record->has_occupancy_permit) ? 'checked' : '' }}>
            <label for="has-occupancy-permit-toggle" class="block text-sm font-medium text-gray-700">Has Occupancy Permit</label>
        </div>
    </div>

    <hr class="mb-6 border-t border-gray-200">

    <div id="occupancy-permit-details-container" class="{{ (isset($record) && $record->has_occupancy_permit) ? '' : 'hidden' }}">
        <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
                <label for="occupancy-permit-instrument-type" class="block text-sm font-medium text-gray-700 mb-2">Instrument Type</label>
                <select id="occupancy-permit-instrument-type"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 sm:text-sm"
                    disabled>
                    <option value="Occupancy Permit" selected>Occupancy Permit</option>
                </select>
                <input type="hidden" name="occupancy_permit_instrument_type" value="Occupancy Permit">
            </div>
            <div class="form-group">
                <label for="occupancy-permit-status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                @php $occupancyPermitStatusValue = isset($record) ? ($record->occupancy_permit_status ?? 'Normal') : 'Normal'; @endphp
                <select id="occupancy-permit-status" name="occupancy_permit_status"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm">
                    <option value="Normal" {{ $occupancyPermitStatusValue === 'Normal' ? 'selected' : '' }}>Normal</option>
                    <option value="Normal Cancellation" {{ $occupancyPermitStatusValue === 'Normal Cancellation' ? 'selected' : '' }}>Normal Cancellation</option>
                    <option value="Total Cancellation" {{ $occupancyPermitStatusValue === 'Total Cancellation' ? 'selected' : '' }}>Total Cancellation</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
                <label for="occupancy-permit-op-type" class="block text-sm font-medium text-gray-700 mb-2">Occupancy Permit (OP) Type</label>
                @php $occupancyPermitOpTypeValue = isset($record) ? ($record->occupancy_permit_op_type ?? '') : ''; @endphp
                <select id="occupancy-permit-op-type" name="occupancy_permit_op_type"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm">
                    <option value="">Select OP Type</option>
                    <option value="Resettlement" {{ $occupancyPermitOpTypeValue === 'Resettlement' ? 'selected' : '' }}>Resettlement</option>
                    <option value="Direct Allocation" {{ $occupancyPermitOpTypeValue === 'Direct Allocation' ? 'selected' : '' }}>Direct Allocation</option>
                </select>
            </div>
            <div class="form-group">
                <label for="occupancy-permit-op-serial-number" class="block text-sm font-medium text-gray-700 mb-2">OP Serial Number</label>
                <input type="text" id="occupancy-permit-op-serial-number" name="occupancy_permit_op_serial_number"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm"
                    placeholder="Enter OP serial number"
                    value="{{ isset($record) ? ($record->occupancy_permit_op_serial_number ?? '') : '' }}">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
                <label for="occupancy-permit-date" class="block text-sm font-medium text-gray-700 mb-2">Transaction Date</label>
                <input type="date" id="occupancy-permit-date" name="occupancy_permit_date"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm"
                    value="{{ isset($record) ? ($record->occupancy_permit_date ?? '') : '' }}">
            </div>
            <div class="form-group">
                <label for="occupancy-permit-file-number" class="block text-sm font-medium text-gray-700 mb-2">File Number</label>
                <input type="text" id="occupancy-permit-file-number" name="occupancy_permit_file_number"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm bg-gray-50"
                    placeholder="Auto-populated from file number"
                    value="{{ isset($record) ? ($record->occupancy_permit_file_number ?? $record->file_number ?? '') : '' }}"
                    readonly>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4 mt-4">
            <div class="form-group">
                <label for="occupancy-permit-land-use" class="block text-sm font-medium text-gray-700 mb-2">Land Use</label>
                <select id="occupancy-permit-land-use" name="occupancy_permit_land_use"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm">
                    <option value="">Select Land Use</option>
                    @if(isset($landUseTypes))
                        @foreach($landUseTypes as $key => $label)
                            <option value="{{ $key }}" {{ (isset($record) && ($record->occupancy_permit_land_use ?? '') == $key) ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="form-group">
                <label for="occupancy-permit-grantor" class="block text-sm font-medium text-gray-700 mb-2">Grantor</label>
                <input type="text" id="occupancy-permit-grantor" name="occupancy_permit_grantor"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm bg-gray-100"
                    value="{{ isset($record) ? ($record->occupancy_permit_grantor ?? 'KANO STATE GOVERNMENT') : 'KANO STATE GOVERNMENT' }}"
                    readonly>
            </div>
            <div class="form-group">
                <label for="occupancy-permit-grantee" class="block text-sm font-medium text-gray-700 mb-2">Grantee</label>
                <input type="text" id="occupancy-permit-grantee" name="occupancy_permit_grantee"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm"
                    placeholder="Enter grantee name"
                    value="{{ isset($record) ? ($record->occupancy_permit_grantee ?? '') : '' }}"
                    style="text-transform: uppercase;">
            </div>
        </div>
    </div>
</div>
