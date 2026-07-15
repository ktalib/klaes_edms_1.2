<!-- Instrument Type Section -->
<div class="form-section" data-section="instrument-details">
    <div class="space-y-3">
        <div class="space-y-1">
            <div class="flex items-center justify-between">
                <label class="text-sm">Registration Number</label>
                <button type="button" id="add-reg-row-btn"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add More
                </button>
            </div>

            {{-- Row 1 — primary (bound to form-controller state, names unchanged) --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3" data-group="registration-number">
                <div>
                    <label for="serialNo" class="text-xs">Serial No.</label>
                    <input id="serialNo" name="serialNo" class="form-input text-xs py-1" placeholder="e.g. 1"
                        data-model="serialNo" value="{{ old('serialNo') }}" type="number" min="1">
                </div>
                <div>
                    <label for="pageNo" class="text-xs">Page No.</label>
                    <input id="pageNo" name="pageNo" class="form-input text-xs py-1 bg-gray-100 cursor-not-allowed"
                        placeholder="e.g. 1" data-model="pageNo" value="{{ old('pageNo') }}" type="number" min="1">
                </div>
                <div>
                    <label for="volumeNo" class="text-xs">Volume No.</label>
                    <input id="volumeNo" name="volumeNo" class="form-input text-xs py-1" placeholder="e.g. 2"
                        data-model="volumeNo" value="{{ old('volumeNo') }}" type="number" min="1">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                <div>
                    <label for="transactionDate" class="text-xs">Transaction/Certificate Date</label>
                    <input type="date" id="transactionDate" name="transactionDate" class="form-input text-xs py-1"
                        data-model="transactionDate" value="{{ old('transactionDate') }}">
                </div>
                <div>
                    <label for="deeds_date" class="text-xs">Deeds Date
                        <span class="text-gray-400 hidden" data-visible-when="property">(optional)</span>
                    </label>
                    <input id="deeds_date" name="deeds_date" type="date" class="form-input text-xs py-1"
                        data-model="deedsDate" value="{{ old('deeds_date') }}"
                        data-index-class="border-blue-300 focus:border-blue-500">
                </div>
                <div>
                    <label for="deeds_time" class="text-xs">Deeds Time
                        <span class="text-gray-400 hidden" data-visible-when="property">(optional)</span>
                    </label>
                    <input id="deeds_time" name="deeds_time" type="time" class="form-input text-xs py-1"
                        data-model="deedsTime" value="{{ old('deeds_time') }}"
                        data-index-class="border-blue-300 focus:border-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3 hidden" data-role="op-fields-wrapper">
                <div>
                    <label for="op_type" class="text-xs">OP Type</label>
                    <select id="op_type" name="op_type" class="form-select text-xs py-1" data-model="opType">
                        <option value="">Select OP type</option>
                        <option value="Resettlement">Resettlement</option>
                        <option value="Direct Allocation">Direct Allocation</option>
                    </select>
                </div>
                <div>
                    <label for="op_serial_number" class="text-xs">OP Serial Number</label>
                    <input id="op_serial_number" name="op_serial_number" class="form-input text-xs py-1"
                        placeholder="Enter OP serial number" data-model="opSerialNumber"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').replace(/^0+/, '')"
                        value="{{ old('op_serial_number') }}" type="text" autocomplete="off">
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 mt-3 hidden" data-role="cofo-fields-wrapper">
                <div>
                    <label for="cofo_type" class="text-xs">CofO Type</label>
                    <select id="cofo_type" name="cofo_type" class="form-select text-xs py-1" data-model="cofoType">
                        <option value="">Select CofO type</option>
                        <option value="Old CofO (Ministry)">Old CofO (Ministry)</option>
                        <option value="KANGIS CofO">KANGIS CofO</option>
                        <option value="New KANGIS CofO">New KANGIS CofO</option>
                    </select>
                </div>
            </div>

            <div class="mt-2 p-3 bg-blue-50 border-2 border-blue-200 rounded-lg shadow-sm hidden"
                data-role="reg-preview">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm font-semibold text-blue-700">Registration Number:</span>
                    </div>
                    <span class="text-lg font-bold text-blue-800 tracking-wider" data-role="reg-preview-value"></span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
                <label for="landUse" class="text-sm">Land Use</label>
                <select id="landUse" name="land_use" class="form-select text-sm" data-model="landUse" required>
                    <option value="">Select land use</option>
                    <option value="RESIDENTIAL">RESIDENTIAL</option>
                    <option value="AGRICULTURAL">AGRICULTURAL</option>
                    <option value="COMMERCIAL">COMMERCIAL</option>
                    <option value="COMMERCIAL ( WARE HOUSE)">COMMERCIAL ( WARE HOUSE)</option>
                    <option value="COMMERCIAL (OFFICES)">COMMERCIAL (OFFICES)</option>
                    <option value="COMMERCIAL (PETROL FILLING STATION)">COMMERCIAL (PETROL FILLING STATION)</option>
                    <option value="COMMERCIAL (RICE PROCESSING)">COMMERCIAL (RICE PROCESSING)</option>
                    <option value="COMMERCIAL (SCHOOL)">COMMERCIAL (SCHOOL)</option>
                    <option value="COMMERCIAL (SHOPS & PUBLIC CONVINIENCE)">COMMERCIAL (SHOPS & PUBLIC CONVINIENCE)
                    </option>
                    <option value="COMMERCIAL (SHOPS AND OFFICES)">COMMERCIAL (SHOPS AND OFFICES)</option>
                    <option value="COMMERCIAL (SHOPS)">COMMERCIAL (SHOPS)</option>
                    <option value="COMMERCIAL (WAREHOUSE)">COMMERCIAL (WAREHOUSE)</option>
                    <option value="COMMERCIAL (WORKSHOP AND OFFICES)">COMMERCIAL (WORKSHOP AND OFFICES)</option>
                    <option value="COMMERCIAL AND RESIDENTIAL">COMMERCIAL AND RESIDENTIAL</option>
                    <option value="INDUSTRIAL">INDUSTRIAL</option>
                    <option value="INDUSTRIAL (SMALL SCALE)">INDUSTRIAL (SMALL SCALE)</option>
                    <option value="RESIDENTIAL AND COMMERCIAL">RESIDENTIAL AND COMMERCIAL</option>
                    <option value="RESIDENTIAL/COMMERCIAL">RESIDENTIAL/COMMERCIAL</option>
                    <option value="RESIDENTIAL/COMMERCIAL LAYOUT">RESIDENTIAL/COMMERCIAL LAYOUT</option>
                </select>
            </div>

            <div class="space-y-1">
                <label for="period" class="text-sm">Period/Tenancy</label>
                <div class="flex space-x-1">
                    <input id="period" name="period" type="number" class="form-input text-sm" placeholder="Period"
                        data-model="period" value="{{ old('period') }}">
                    <select id="periodUnit" name="periodUnit" class="form-select text-sm w-[90px]"
                        data-model="periodUnit">
                        <option value="Days" {{ old('periodUnit') === 'Days' ? 'selected' : '' }}>Days</option>
                        <option value="Months" {{ old('periodUnit') === 'Months' ? 'selected' : '' }}>Months</option>
                        <option value="Years" {{ old('periodUnit', 'Years') === 'Years' ? 'selected' : '' }}>Years
                        </option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Extra registration number rows (appended by JS) --}}
        <div id="extra-reg-rows-container" class="space-y-3 mt-1"></div>
    </div>
</div>

<script>
    // Sync Serial No to Page No and handle leading zeros
    document.addEventListener('DOMContentLoaded', function () {
        const serialNoInput = document.getElementById('serialNo');
        const pageNoInput = document.getElementById('pageNo');
        const volumeNoInput = document.getElementById('volumeNo');

        function stripLeadingZeros(value) {
            if (!value) return '';
            // Remove non-digits and strip leading zeros
            const clean = value.toString().replace(/\D/g, '');
            if (!clean) return '';
            return parseInt(clean, 10).toString();
        }

        function handleSyncAndSanitize(input, syncTo = null) {
            if (!input) return;

            // Handle typing - allow numbers
            input.addEventListener('input', function () {
                let value = this.value;
                // If it starts with '0' and has more than 1 char, or is just '0', sanitize it
                if (value.startsWith('0')) {
                    this.value = stripLeadingZeros(value);
                }
                
                if (syncTo) {
                    syncTo.value = this.value;
                    syncTo.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });

            // Handle validation on blur
            input.addEventListener('blur', function () {
                const sanitized = stripLeadingZeros(this.value);
                this.value = sanitized;
                if (syncTo) {
                    syncTo.value = sanitized;
                    syncTo.dispatchEvent(new Event('input', { bubbles: true }));
                }
                this.dispatchEvent(new Event('input', { bubbles: true }));
            });
        }

        handleSyncAndSanitize(serialNoInput, pageNoInput);
        
        if (volumeNoInput) {
            volumeNoInput.addEventListener('input', function() {
                if (this.value.startsWith('0')) {
                    this.value = stripLeadingZeros(this.value);
                }
            });
            volumeNoInput.addEventListener('blur', function() {
                this.value = stripLeadingZeros(this.value);
                this.dispatchEvent(new Event('input', { bubbles: true }));
            });
        }

        // Initial check for existing values
        if (serialNoInput && serialNoInput.value.startsWith('0')) {
            serialNoInput.value = stripLeadingZeros(serialNoInput.value);
            if (pageNoInput) {
                pageNoInput.value = serialNoInput.value;
            }
        }
        if (volumeNoInput && volumeNoInput.value.startsWith('0')) {
            volumeNoInput.value = stripLeadingZeros(volumeNoInput.value);
        }

        // ── Add More Registration Number Rows ──────────────────────────
        const addRegRowBtn = document.getElementById('add-reg-row-btn');
        const extraRegContainer = document.getElementById('extra-reg-rows-container');
        let extraRegCount = 0;

        if (addRegRowBtn && extraRegContainer) {
            addRegRowBtn.addEventListener('click', function () {
                const idx = extraRegCount++;

                // Clone land use options from the main select so they stay in sync
                const sourceLandUse = document.getElementById('landUse');
                const landUseOptionsHtml = sourceLandUse ? sourceLandUse.innerHTML : '<option value="">Select land use</option>';

                const row = document.createElement('div');
                row.className = 'relative border border-gray-200 rounded-md p-3 bg-gray-50 space-y-3';
                row.dataset.extraRegIdx = idx;
                row.innerHTML =
                    // Remove button
                    '<button type="button" title="Remove this row" ' +
                        'class="absolute top-2 right-2 text-gray-400 hover:text-red-500" ' +
                        'onclick="this.closest(\'[data-extra-reg-idx]\').remove()">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>' +
                        '</svg>' +
                    '</button>' +
                    // Row 1: Serial / Page / Volume
                    '<div class="grid grid-cols-1 md:grid-cols-3 gap-3">' +
                        '<div>' +
                            '<label class="text-xs text-gray-600">Serial No.</label>' +
                            '<input name="extra_regs[' + idx + '][serial_no]" class="form-input text-xs py-1" placeholder="e.g. 1" type="number" min="1">' +
                        '</div>' +
                        '<div>' +
                            '<label class="text-xs text-gray-600">Page No.</label>' +
                            '<input name="extra_regs[' + idx + '][page_no]" class="form-input text-xs py-1" placeholder="e.g. 1" type="number" min="1">' +
                        '</div>' +
                        '<div>' +
                            '<label class="text-xs text-gray-600">Volume No.</label>' +
                            '<input name="extra_regs[' + idx + '][volume_no]" class="form-input text-xs py-1" placeholder="e.g. 2" type="number" min="1">' +
                        '</div>' +
                    '</div>' +
                    // Row 2: Transaction Date / Deeds Date / Deeds Time
                    '<div class="grid grid-cols-1 md:grid-cols-3 gap-3">' +
                        '<div>' +
                            '<label class="text-xs text-gray-600">Transaction/Certificate Date</label>' +
                            '<input name="extra_regs[' + idx + '][transaction_date]" type="date" class="form-input text-xs py-1">' +
                        '</div>' +
                        '<div>' +
                            '<label class="text-xs text-gray-600">Deeds Date <span class="text-gray-400">(optional)</span></label>' +
                            '<input name="extra_regs[' + idx + '][deeds_date]" type="date" class="form-input text-xs py-1">' +
                        '</div>' +
                        '<div>' +
                            '<label class="text-xs text-gray-600">Deeds Time <span class="text-gray-400">(optional)</span></label>' +
                            '<input name="extra_regs[' + idx + '][deeds_time]" type="time" class="form-input text-xs py-1">' +
                        '</div>' +
                    '</div>' +
                    // Row 3: Land Use / Period+Unit
                    '<div class="grid grid-cols-2 gap-3">' +
                        '<div>' +
                            '<label class="text-xs text-gray-600">Land Use</label>' +
                            '<select name="extra_regs[' + idx + '][land_use]" class="form-select text-xs py-1">' +
                                landUseOptionsHtml +
                            '</select>' +
                        '</div>' +
                        '<div>' +
                            '<label class="text-xs text-gray-600">Period/Tenancy</label>' +
                            '<div class="flex space-x-1">' +
                                '<input name="extra_regs[' + idx + '][period]" type="number" class="form-input text-xs" placeholder="Period">' +
                                '<select name="extra_regs[' + idx + '][period_unit]" class="form-select text-xs w-[90px]">' +
                                    '<option value="Days">Days</option>' +
                                    '<option value="Months">Months</option>' +
                                    '<option value="Years" selected>Years</option>' +
                                '</select>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
                extraRegContainer.appendChild(row);
            });
        }
    });
</script>