<!-- Custom Styles for Dropdown   -->
<style>
    .dropdown-menu {
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        border-radius: 8px;
        overflow: hidden;
        animation: fadeIn 0.15s ease-out;
    }

    .dropdown-menu button {
        transition: all 0.15s ease;
    }

    .dropdown-menu button:hover {
        transform: translateX(2px);
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Ensure dropdown appears above DataTable elements */
    .dropdown-menu,
    .action-dropdown-menu {
        z-index: 1050 !important;
        background-color: white;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        min-width: 14rem;
        padding-top: 0.25rem;
        padding-bottom: 0.25rem;
    }

    /* Hide by default */
    .action-dropdown-menu {
        display: none;
    }

    .action-dropdown-menu.show {
        display: block;
    }

    /* Initially hidden state for the JS toggle logic which uses 'hidden' class */
    .action-dropdown-menu.hidden {
        display: none;
    }
</style>

<!-- jsPDF Library for PDF Generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js">    </script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.1/jspdf.plugin.autotable.min.js"></script>

@php
    // Fetch all commissioning sheets and build a normalized lookup map (trimmed, uppercased keys)
    $commissioningSheetsRaw = \DB::connection('sqlsrv')
        ->table('file_commissioning_sheets')
        ->select('id', 'file_number')
        ->get();
    $commissioningSheets = [];
    foreach ($commissioningSheetsRaw as $sheet) {
        $key = strtoupper(trim($sheet->file_number));
        $commissioningSheets[$key] = [
            'id' => $sheet->id,
            'file_number' => $sheet->file_number
        ];
    }
@endphp

<script>
    console.log('DEBUG: mls_js.blade.php script tag reached');
    console.log('DEBUG: Alpine status:', window.Alpine ? 'Loaded' : 'NOT Loaded');
    // Create a JS object for quick lookup (normalized keys)
    const commissioningSheetsMap = @json($commissioningSheets);

    // Provide server time to prevent client-side time issues
    const getServerTime = () => {
        const serverTime = "{{ date('Y-m-d H:i') }}";
        const [datePart, timePart] = serverTime.split(' ');
        return { date: datePart, time: timePart };
    };

    // Helper function to format 24h time string to 12h AM/PM
    const formatTimeToAMPM = (timeStr) => {
        if (!timeStr || typeof timeStr !== 'string') return '';
        if (timeStr.includes('AM') || timeStr.includes('PM')) return timeStr;
        
        const parts = timeStr.split(':');
        if (parts.length < 2) return timeStr;
        
        let hours = parseInt(parts[0]);
        let minutes = parts[1].substring(0, 2);
        
        if (isNaN(hours)) return timeStr;
        
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        
        return `${String(hours).padStart(2, '0')}:${minutes} ${ampm}`;
    };

    // Helper function to get commissioning sheet id for a row
    function getCommissioningSheetId(row) {
        const fileNumbers = [row.mlsfNo, row.kangisFileNo, row.NewKANGISFileNo]
            .filter(fn => fn && fn !== 'N/A' && fn.trim() !== '')
            .map(fn => fn.trim().toUpperCase());
        // ...
        for (const fn of fileNumbers) {
            if (commissioningSheetsMap[fn]) {
                return commissioningSheetsMap[fn].id;
            }
        }
        return null;
    }

    const groupingLookupState = {
        debounceHandle: null,
        inFlightRequestId: 0,
        lastSuccessfulNumber: null
    };

    const GROUPING_LOOKUP_TIMEOUT_MS = 4500;
    const GENERATE_REQUEST_TIMEOUT_MS = 60000;

    function setActionButtonsDisabled(isDisabled) {
        const overrideBtn = document.getElementById('overrideButton');
        const generateBtn = document.getElementById('generateButton');

        [overrideBtn, generateBtn].forEach((button) => {
            if (!button) {
                return;
            }

            button.disabled = isDisabled;

            if (isDisabled) {
                button.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                button.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        });
    }

    // Helper function to render file numbers with badges
    function renderFileNumberBadge(fileNumber) {
        if (!fileNumber || fileNumber === 'N/A' || fileNumber.trim() === '') {
            return '\u003cspan class="text-gray-400 text-sm"\u003e-\u003c/span\u003e';
        }
        // Determine badge class based on file number prefix
        let badgeClass = 'badge-default';
        const upperFileNumber = fileNumber.toUpperCase();

        if (upperFileNumber.startsWith('RES') || upperFileNumber.includes('CON-RES')) {
            badgeClass = 'badge-res';
        } else if (upperFileNumber.startsWith('COM') || upperFileNumber.includes('CON-COM')) {
            badgeClass = 'badge-com';
        } else if (upperFileNumber.startsWith('IND') || upperFileNumber.includes('CON-IND')) {
            badgeClass = 'badge-ind';
        } else if (upperFileNumber.startsWith('AG') || upperFileNumber.includes('CON-AG')) {
            badgeClass = 'badge-ag';
        }

        return `\u003cspan class="file-badge ${badgeClass}"\u003e${fileNumber}\u003c/span\u003e`;
    }

    function resetTrackingIdDisplay(text = '--') {
        const displayEl = document.getElementById('trackingIdDisplay');
        if (displayEl) {
            displayEl.textContent = text;
        }

        const inputEl = document.getElementById('trackingIdInput');
        if (inputEl) {
            inputEl.value = '';
        }

        groupingLookupState.lastSuccessfulNumber = null;
        setActionButtonsDisabled(true);
    }

    function applyTrackingIdToDisplay(trackingId, fallbackText = '--') {
        const value = (trackingId || '').trim();
        const displayValue = value !== '' ? value : fallbackText;

        const displayEl = document.getElementById('trackingIdDisplay');
        if (displayEl) {
            displayEl.textContent = displayValue;
        }

        const summaryTrackingEl = document.getElementById('summaryTrackingId');
        if (summaryTrackingEl) {
            summaryTrackingEl.textContent = displayValue;
        }

        const inputEl = document.getElementById('trackingIdInput');
        if (inputEl) {
            inputEl.value = value;
        }
    }

    function isEligibleForGroupingLookup(value) {
        const sanitized = (value || '').replace(/[^A-Z0-9]/gi, '');
        return sanitized.length >= 5 && /\d/.test(sanitized);
    }

    function deriveGroupingLookupCandidate(fileOption, previewText, existingFileNo) {
        let preview = (previewText || '').trim();
        const existing = (existingFileNo || '').trim();

        // If it's a batch range (e.g., "RES-2024-1 to RES-2024-10"), take only the first part
        if (preview.includes(' to ')) {
            preview = preview.split(' to ')[0].trim();
        }

        if (!fileOption) {
            return preview;
        }

        switch (fileOption) {
            case 'extension':
                if (existing !== '') {
                    return existing;
                }
                return preview.replace(/\s+AND\s+EXTENSION$/i, '').trim();
            case 'temporary':
                if (existing !== '') {
                    return existing;
                }
                return preview.replace(/\(T\)\s*$/i, '').trim();
            case 'old_mls':
                return preview.replace(/\s+/g, '-');
            default:
                return preview;
        }
    }

    function queueGroupingLookup(fileNumber) {
        // Clean trailing hyphens from file number (common with extension/temporary files)
        let cleaned = (fileNumber || '').trim().replace(/-+$/, '').toUpperCase();
        const normalized = cleaned;

        if (!normalized || normalized === '-' || !isEligibleForGroupingLookup(normalized)) {
            resetTrackingIdDisplay('--');
            return;
        }

        if (groupingLookupState.lastSuccessfulNumber === normalized) {
            return;
        }

        if (groupingLookupState.debounceHandle) {
            clearTimeout(groupingLookupState.debounceHandle);
        }

        groupingLookupState.debounceHandle = setTimeout(() => {
            performGroupingLookup(normalized);
        }, 350);
    }

    async function performGroupingLookup(fileNumber) {
        groupingLookupState.debounceHandle = null;
        const requestId = Date.now();
        groupingLookupState.inFlightRequestId = requestId;

        const displayEl = document.getElementById('trackingIdDisplay');
        if (displayEl) {
            displayEl.textContent = 'Looking...';
        }

        setActionButtonsDisabled(true);

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
        const controller = new AbortController();
        const timeoutHandle = setTimeout(() => controller.abort(), GROUPING_LOOKUP_TIMEOUT_MS);

        try {
            const response = await fetch('/api/grouping/link-mls', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                signal: controller.signal,
                body: JSON.stringify({ mls_file_number: fileNumber })
            });

            clearTimeout(timeoutHandle);

            const payload = await response.json();

            if (groupingLookupState.inFlightRequestId !== requestId) {
                return;
            }

            if (response.ok && payload.success) {
                const trackingId = (payload.data?.tracking_id ?? '').trim();
                applyTrackingIdToDisplay(trackingId, '--');

                groupingLookupState.lastSuccessfulNumber = fileNumber;
                setActionButtonsDisabled(trackingId === '');

                if (trackingId === '') {
                    applyTrackingIdToDisplay('', 'Not Found');
                    Swal.fire({
                        icon: 'warning',
                        title: 'Tracking ID Missing',
                        text: 'Tracking ID must come from grouping table for this file number. Please update grouping record before generating.',
                        confirmButtonColor: '#f59e0b'
                    });
                }
            } else {
                handleGroupingLookupError(payload, response.status);
            }
        } catch (error) {
            clearTimeout(timeoutHandle);
            if (groupingLookupState.inFlightRequestId === requestId) {
                applyTrackingIdToDisplay('', 'Not Found');
                setActionButtonsDisabled(true);
            }
            console.error('Grouping lookup failed:', error);
        }
    }

    function handleGroupingLookupError(payload, status) {
        resetTrackingIdDisplay('--');

        if (status === 409) {
            Swal.fire({
                icon: 'warning',
                title: 'Mapping Conflict',
                text: payload?.message || 'Grouping record is mapped to a different MLS file number.',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        if (status === 404) {
            applyTrackingIdToDisplay('', 'Not Found');
            setActionButtonsDisabled(true);
            console.warn('Grouping record not found for MLS file number lookup.', payload);
            return;
        }

        if (status >= 500) {
            Swal.fire({
                icon: 'error',
                title: 'Grouping Lookup Failed',
                text: payload?.message || 'Unable to link grouping record right now.',
                confirmButtonColor: '#ef4444'
            });
            applyTrackingIdToDisplay('', 'Not Found');
            setActionButtonsDisabled(true);
        }
    }
    // ...

    let table;
    let nextSerialNo = 1;
    let isOverrideMode = false;

    // Loading utility functions
    function showLoadingButton(buttonElement, originalText) {
        if (buttonElement) {
            buttonElement.disabled = true;
            buttonElement.innerHTML = `
                    <i data-lucide="loader" class="w-4 h-4 mr-2 animate-spin"></i>
                    Loading...
                `;
            lucide.createIcons();
        }
    }

    function hideLoadingButton(buttonElement, originalText) {
        if (buttonElement) {
            buttonElement.disabled = false;
            buttonElement.innerHTML = originalText;
            lucide.createIcons();
        }
    }

    function showGlobalLoading(message = 'Processing...') {
        Swal.fire({
            title: message,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }

    function hideGlobalLoading() {
        Swal.close();
    }

    function formatLandUse(landUse, mlsfNo) {
        let landUsePrefix = '';

        // If landUse is provided, use it as the prefix base
        if (landUse && landUse !== 'N/A' && landUse.trim() !== '') {
            landUsePrefix = landUse.trim().toUpperCase();
        } else if (mlsfNo && mlsfNo !== 'N/A' && mlsfNo.trim() !== '') {
            // Otherwise, derive it from the MLS File Number
            const parts = mlsfNo.split('-');
            if (parts.length > 0) {
                landUsePrefix = parts[0].trim().toUpperCase();
                // Handle CON- prefix (e.g., CON-COM-...) - use the second part if available and valid
                if (landUsePrefix === 'CON' && parts.length > 1) {
                    const secondPart = parts[1].trim().toUpperCase();
                    if (isNaN(secondPart)) {
                        landUsePrefix = secondPart;
                    }
                }
            }
        }

        if (!landUsePrefix || isNaN(landUsePrefix) === false) {
            return '-';
        }

        // Map prefixes to full names and colors (Uppercased as requested)
        const mapping = {
            'RES': { name: 'RESIDENTIAL', class: 'bg-green-100 text-green-800' },
            'COM': { name: 'COMMERCIAL', class: 'bg-blue-100 text-blue-800' },
            'IND': { name: 'INDUSTRIAL', class: 'bg-orange-100 text-orange-800' },
            'AG': { name: 'AGRICULTURAL', class: 'bg-lime-100 text-lime-800' },
            'KN': { name: 'KANO HERITAGE', class: 'bg-purple-100 text-purple-800' },
            'MISC': { name: 'MISCELLANEOUS', class: 'bg-gray-100 text-gray-800' },
            'SLTR': { name: 'STATUTORY RIGHT OF OCCUPANCY', class: 'bg-indigo-100 text-indigo-800' },
            'SIT': { name: 'SIT', class: 'bg-yellow-100 text-yellow-800' }
        };

        let displayText = landUsePrefix;
        let badgeClass = 'bg-gray-100 text-gray-800'; // Default color

        // Check for direct match or partial match
        for (const [key, config] of Object.entries(mapping)) {
            if (landUsePrefix.includes(key)) {
                displayText = config.name;
                badgeClass = config.class;
                break;
            }
        }

        return `<span class="px-2 inline-flex text-xs leading-5 font-bold rounded-full ${badgeClass}">${displayText}</span>`;
    }


    // --- Batch Viewing Logic (Moved to Top) ---

    // Expose to window to ensure availability
    window.viewBatch = function(batchNo) {
        if (!batchNo) {
            console.error('viewBatch called without batchNo');
            return;
        }
        console.log('Opening batch view for:', batchNo);

        const modal = document.getElementById('batchDetailsModal');
        const title = document.getElementById('batchModalTitle');
        const subtitle = document.getElementById('batchModalSubtitle');
        const tableBody = document.getElementById('batchDetailsTableBody');

        if (!modal) {
             window.alert('ERROR: batchDetailsModal NOT found!');
             console.error('CRITICAL: batchDetailsModal element NOT found in DOM!');
             return;
        }

        // Move modal to body if it's not already there to avoid clipping/containment issues
        if (modal.parentNode !== document.body) {
            console.log('Moving modal to document body for higher visibility...');
            document.body.appendChild(modal);
        }

        console.log('Modal found, forcing show...');
        console.log('Modal style before:', modal.getAttribute('style'));
        console.log('Modal classes before:', modal.className);

        title.innerText = `Batch Details: ${batchNo}`;
        subtitle.innerText = `Viewing all records grouped under batch ${batchNo}`;
        tableBody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500"><div class="flex items-center justify-center"><i data-lucide="loader" class="w-6 h-6 animate-spin mr-2"></i>Loading records...</div></td></tr>';

        if (typeof lucide !== 'undefined') lucide.createIcons();

        // Force Show modal with ultra-aggressive styling
        modal.classList.remove('hidden');
        modal.style.setProperty('display', 'block', 'important');
        modal.style.setProperty('opacity', '1', 'important');
        modal.style.setProperty('visibility', 'visible', 'important');
        modal.style.setProperty('z-index', '999999', 'important');
        modal.style.setProperty('position', 'fixed', 'important');
        modal.style.setProperty('top', '0', 'important');
        modal.style.setProperty('left', '0', 'important');
        modal.style.setProperty('width', '100%', 'important');
        modal.style.setProperty('height', '100%', 'important');
        
        document.body.style.overflow = 'hidden';
        console.log('Modal display property set to:', modal.style.display);
        console.log('Modal rect:', modal.getBoundingClientRect());

        // Fetch batch records
        fetch('{{ route("mls-fileno.batch-records") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ batch_no: batchNo })
        })
            .then(response => response.json())
            .then(result => {
                if (result.success && result.data && result.data.length > 0) {
                    console.log(`Found ${result.data.length} records for batch ${batchNo}`);
                    renderBatchTable(result.data);
                } else {
                    console.warn('No records found for batch:', batchNo);
                    tableBody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-red-500">No records found for this batch.</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error fetching batch records:', error);
                tableBody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-red-500">Failed to load batch data.</td></tr>';
            });
    }; // End viewBatch

    function renderBatchTable(records) {
        const tableBody = document.getElementById('batchDetailsTableBody');
        tableBody.innerHTML = '';

        records.forEach(record => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50 transition-colors cursor-default';
            row.innerHTML = `
                <td class="px-4 py-3 whitespace-nowrap text-sm font-bold text-gray-900">${(record.mlsfNo || record.full_file_number || 'N/A').toUpperCase()}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${(record.file_name || record.FileName || 'N/A').toUpperCase()}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                    <div class="flex flex-col space-y-1">
                        <div>${formatLandUse(record.land_use, record.full_file_number || record.mlsfNo)}</div>
                        <div class="text-[10px] text-gray-400 font-bold tracking-tight">${(record.purpose_name && record.purpose_name !== 'N/A') ? record.purpose_name.toUpperCase() : '-'}</div>
                    </div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${(record.plot_no || 'N/A').toUpperCase()}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${(record.location || 'N/A').toUpperCase()}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${(record.lga || 'N/A').toUpperCase()}</td>
                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium space-x-2">
                    <button onclick="editRecord(${record.filenumber_id})" class="inline-flex p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit Record">
                        <i data-lucide="pencil" class="w-4 h-4"></i>
                    </button>
                    <button onclick="openFilePrinterManager('${record.filenumber_id}', '${record.mlsfNo || record.full_file_number || ''}', '${record.batch_no}', true)" class="inline-flex p-1.5 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Print Document">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                    </button>
                </td>
            `;
            tableBody.appendChild(row);
        });

        // Re-initialize Lucide icons for the new buttons
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    window.closeBatchDetailsModal = function() {
        const modal = document.getElementById('batchDetailsModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
            modal.style.opacity = '0';
            modal.style.visibility = 'hidden';
        }
        document.body.style.overflow = '';
    }

    $(document).ready(function () {
        resetTrackingIdDisplay('--');
        setActionButtonsDisabled(true);
        // Initialize DataTable with performance optimizations
        // Initialize DataTable
        table = $('#mlsfTable').DataTable({
            processing: true,
            serverSide: true,
            deferRender: true, // Improve performance for large datasets
            stateSave: true, // Save pagination/sorting state
            stateDuration: 300, // 5 minutes state duration
            ajax: {
                url: '{{ route("file-numbers.data") }}',
                type: 'GET',
                timeout: 120000, // 120 second timeout for large production datasets
                data: function (d) {
                    d.source = 'New'; // Filter for Generated records only
                    console.log('DataTables request:', d);
                    return d;
                },
                dataSrc: function (json) {
                    console.log('DataTables response:', json);
                    if (json.error) {
                        console.error('Server error:', json.error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Server Error',
                            text: json.error,
                            confirmButtonColor: '#ef4444'
                        });
                    }
                    const allRows = json.data || [];
                    return allRows;
                },
                error: function (xhr, error, code) {
                    console.error('DataTables AJAX error:', error);
                    console.error('Status:', xhr.status);
                    console.error('Response:', xhr.responseText);

                    let errorMessage = 'Failed to load file numbers. Please check your connection and try again.';

                    if (xhr.status === 500) {
                        errorMessage = 'Server error occurred. Please contact the administrator.';
                    } else if (xhr.status === 404) {
                        errorMessage = 'Data endpoint not found. Please contact the administrator.';
                    } else if (xhr.status === 0) {
                        errorMessage = 'Request timed out or connection was interrupted. Please retry.';
                    }

                    Swal.fire({
                        icon: 'error',
                        title: 'Error Loading Data',
                        text: errorMessage,
                        confirmButtonColor: '#ef4444',
                        footer: `<small>Error Code: ${xhr.status} - ${error}</small>`
                    });
                }
            },
            // Optimize DOM structure for better performance
            dom: '<"top"flp>rt<"bottom"ip><"clear">',
            columns: [
                {
                    data: null,
                    title: 'S/N',
                    orderable: false,
                    searchable: false,
                    render: function (data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                {
                    data: 'customer_type',
                    name: 'customer_type',
                    title: 'Customer Type',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        return `<span class="text-gray-700 font-bold">${data || 'N/A'}</span>`;
                    }
                },
                {
                    data: 'source',
                    name: 'source',
                    title: 'Source',
                    defaultContent: 'N/A',
                    orderable: false,
                    searchable: false,
                    render: function (data, type, row) {
                        const sourceValue = (data || row.source || row.file_source || row.file_option || 'N/A').toString();
                        const srcLower = sourceValue.toLowerCase();
                        const isOP = srcLower.includes('op resettlement') || srcLower.includes('op direct allocation');
                        if (isOP) {
                            const subLabel = sourceValue.replace(/^op\s+/i, '').toUpperCase();
                            const subColor = srcLower.includes('direct') ? 'color:#7c3aed' : 'color:#ea580c';
                            return `<div style="line-height:1.3">
                                <div style="font-size:12px;font-weight:700;color:#0369a1;white-space:nowrap">Occupancy Permit (OP)</div>
                                <div style="font-size:11px;font-weight:600;white-space:nowrap;${subColor}">${subLabel}</div>
                            </div>`;
                        }
                        return sourceValue !== 'N/A'
                            ? `<span style="display:inline-flex;align-items:center;padding:1px 8px;border-radius:9999px;font-size:12px;font-weight:600;border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8">${sourceValue}</span>`
                            : 'N/A';
                    }
                },
                {
                    data: 'mlsfNo',
                    name: 'mlsfNo',
                    title: 'MLS File No',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        let display = data ? data.toUpperCase() : 'N/A';
                        const sourceTempFileno = row.source_temp_fileno ? String(row.source_temp_fileno).toUpperCase() : '';
                        const sourcePropId = row.source_prop_id || '';
                        const sourceCaptureId = row.source_instrument_capture_id || '';
                        const sourcePraId = row.source_pra_id || '';

                        // Format as range if batch
                        let isRange = false;
                        if (row.batch_no && row.batch_count > 1 && row.batch_first_file) {
                            const lastFile = display;
                            const firstFile = row.batch_first_file.toUpperCase();

                            // Extract parts separated by dash (Expect format like PREFIX-YEAR-SERIAL)
                            const lastParts = lastFile.split('-');
                            const firstParts = firstFile.split('-');

                            if (lastParts.length >= 2 && firstParts.length === lastParts.length) {
                                // Check if prefix parts (everything except last numeric) match
                                const lastPrefix = lastParts.slice(0, -1).join('-');
                                const firstPrefix = firstParts.slice(0, -1).join('-');

                                if (lastPrefix === firstPrefix) {
                                    const lastSerial = lastParts[lastParts.length - 1];
                                    const firstSerial = firstParts[firstParts.length - 1];

                                    if (firstSerial !== lastSerial) {
                                        display = `${lastPrefix}-${firstSerial}-${lastSerial}`;
                                        isRange = true;
                                    }
                                }
                            }
                        }

                        let html;
                        if (isRange) {
                            html = `<div class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-indigo-100 text-indigo-800 border border-indigo-200">
                                ${display}
                            </div>`;
                        } else {
                            html = `<div class="font-bold text-gray-900">${display}</div>`;
                        }
                        if (row.batch_no && row.batch_count > 1) {
                            html += `
                                <div class="mt-1">
                                    <button onclick="viewBatch('${row.batch_no}')" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors border border-blue-200">
                                        <i data-lucide="layers" class="w-3 h-3 mr-1"></i>
                                        Group (${row.batch_count})
                                    </button>
                                </div>`;
                            // Re-initialize Lucide icons after render
                            setTimeout(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); }, 100);
                        }

                        const canResolveSourceRow = !!(sourceCaptureId || sourcePraId);
                        if (sourceTempFileno && sourceTempFileno !== 'N/A' && canResolveSourceRow) {
                            html += `
                                <div class="mt-1">
                                    <button
                                        type="button"
                                        class="js-temp-file-link text-[11px] font-bold text-indigo-600 underline underline-offset-2 hover:text-indigo-800"
                                        data-prop-id="${sourcePropId}"
                                        data-source-capture-id="${sourceCaptureId}"
                                        data-source-pra-id="${sourcePraId}"
                                        data-temp-fileno="${sourceTempFileno}"
                                        data-new-mls-fileno="${display}"
                                        data-new-party-name="${String(row.FileName || '').replace(/\"/g, '&quot;')}"
                                    >
                                        ${sourceTempFileno}
                                    </button>
                                </div>`;
                        }

                        return html;
                    }
                },
                {
                    data: 'FileName',
                    name: 'FileName',
                    title: 'File Title',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        return data ? data.toUpperCase() : 'N/A';
                    }
                },
                {
                    data: 'land_use',
                    name: 'land_use',
                    title: 'Land Use',
                    defaultContent: '-',
                    render: function (data, type, row) {
                        const landUseBadge = formatLandUse(data, row.mlsfNo);
                        const purpose = (row.purpose_name && row.purpose_name !== 'N/A') ? row.purpose_name.toUpperCase() : '-';
                        return `
                            <div class="flex flex-col space-y-1">
                                <div>${landUseBadge}</div>
                                <div class="text-[10px] text-gray-400 font-bold tracking-tight">${purpose}</div>
                            </div>
                        `;
                    }
                },
                {
                    data: 'tp_no',
                    name: 'tp_no',
                    title: 'TP No',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        return data ? data.toUpperCase() : 'N/A';
                    }
                },
                {
                    data: 'plot_no',
                    name: 'plot_no',
                    title: 'Plot No',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        return data ? data.toUpperCase() : 'N/A';
                    }
                },
                {
                    data: 'lga',
                    name: 'lga',
                    title: 'LGA',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        return data ? data.toUpperCase() : 'N/A';
                    }
                },
                {
                    data: 'location',
                    name: 'location',
                    title: 'Location',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        return data ? data.toUpperCase() : 'N/A';
                    }
                },
                {
                    data: 'created_by',
                    name: 'created_by',
                    title: 'Commissioned By',
                    defaultContent: 'System',
                    render: function (data, type, row) {
                        return data ? data.toUpperCase() : 'SYSTEM';
                    }
                },
                {
                    data: 'created_at',
                    name: 'created_at',
                    title: 'Time Commissioned',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        if (type === 'display' && data && data !== 'N/A') {
                            const date = new Date(data);
                            return date.toLocaleTimeString('en-US', {
                                hour: '2-digit',
                                minute: '2-digit',
                                hour12: true
                            });
                        }
                        return data || 'N/A';
                    }
                },
                {
                    data: 'created_at',
                    name: 'created_at',
                    title: 'Date Commissioned',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        if (type === 'display' && data && data !== 'N/A') {
                            const date = new Date(data);
                            return date.toLocaleDateString('en-US', {
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric'
                            });
                        }
                        return data || 'N/A';
                    }
                },
                {
                    data: null,
                    name: 'action',
                    title: 'Actions',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    width: '220px',
                    render: function (data, type, row) {
                        // Check if record has a batch_no
                        const hasBatchNo = row.batch_no && row.batch_no !== null && row.batch_no.trim() !== '';
                        const batchNo = hasBatchNo ? row.batch_no : '';

                        return `
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="openFilePrinterManager(${row.id}, '${row.mlsfNo}', '${batchNo}')"
                                        class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition-colors shadow-sm"
                                        title="Print">
                                    <i data-lucide="printer" class="w-4 h-4 mr-1.5"></i>
                                    <span class="text-xs font-semibold">Print</span>
                                </button>

                                <div class="action-dropdown shadow-sm border border-gray-200 rounded-lg">
                                    <button class="flex items-center space-x-2 px-3 py-1.5 bg-white text-gray-700 hover:bg-gray-50 transition-colors focus:outline-none" title="More actions">
                                        <i data-lucide="ellipsis" class="w-4 h-4 text-gray-400"></i>
                                    </button>
                                    <div class="action-dropdown-menu">
                                    <!-- Edit Record -->
                                    <button onclick="openEditModalFromAction(event, ${row.id})" 
                                            class="w-full text-left px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 flex items-center space-x-3">
                                        <i data-lucide="edit-3" class="w-4 h-4 text-slate-500"></i>
                                        <span class="font-medium">Edit</span>
                                    </button>

                                    <!-- Direct Allocation -->
                                    <button onclick="directAllocation(${row.id})" 
                                            class="w-full text-left px-4 py-2.5 text-sm text-green-700 hover:bg-green-50 flex items-center space-x-3">
                                        <i data-lucide="map-pin" class="w-4 h-4 text-green-500"></i>
                                        <span class="font-medium">Update Allocation data</span>
                                    </button>

                                    <div class="border-t border-gray-100 my-1"></div>

                                    <!-- Delete Record -->
                                    <button onclick="deleteRecord(${row.id})" 
                                            class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 flex items-center space-x-3">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        <span class="font-medium">Delete Record</span>
                                    </button>
                                </div>
                            </div>
                            </div>
                        `;
                    }
                }
            ],
            order: [[0, 'desc']],
            pageLength: 20,
            lengthMenu: [[10, 20, 25, 50, 100], [10, 20, 25, 50, 100]],
            responsive: true,
            scrollCollapse: true,
            scroller: {
                displayBuffer: 9
            },
            searchDelay: 500, // Delay search to reduce server requests
            language: {
                processing: '<div class="flex items-center justify-center"><i data-lucide="loader" class="w-4 h-4 mr-2 animate-spin"></i>Loading file numbers...</div>',
                emptyTable: '<div class="text-center py-8"><div class="text-gray-400 mb-2"><i data-lucide="database" class="w-12 h-12 mx-auto mb-2"></i></div><h3 class="text-lg font-medium text-gray-900 mb-1">No file numbers found</h3><p class="text-gray-500">Start by generating your first MLS file number using the button above.</p></div>',
                zeroRecords: '<div class="text-center py-8"><div class="text-gray-400 mb-2"><i data-lucide="search" class="w-12 h-12 mx-auto mb-2"></i></div><h3 class="text-lg font-medium text-gray-900 mb-1">No matching records found</h3><p class="text-gray-500">Try adjusting your search criteria.</p></div>',
                info: "Showing _START_ to _END_ of _TOTAL_ file numbers",
                infoEmpty: "No file numbers available",
                infoFiltered: "(filtered from _MAX_ total file numbers)",
                lengthMenu: "Show _MENU_ file numbers per page",
                search: "Search file numbers:",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            },
            drawCallback: function (settings) {
                // Optimized icon initialization - use requestAnimationFrame for better performance
                requestAnimationFrame(function () {
                    lucide.createIcons();
                });
            },
            initComplete: function (settings, json) {
                console.log('DataTable initialized:', {
                    recordsTotal: json?.recordsTotal || 0,
                    recordsFiltered: json?.recordsFiltered || 0,
                    dataLength: json?.data?.length || 0
                });

                // Show a message if no data is available
                if (json && json.recordsTotal === 0) {
                    console.log('No records found in database');
                }
            }
        });

        // Get next serial number
        getNextSerialNumber();

        // Load existing file numbers for extension dropdown
        loadExistingFileNumbers();

        async function showTempFileDetailsByPropId(propId, sourceCaptureId, sourcePraId, tempFilenoLabel, newMlsFilenoLabel, newPartyName) {
            if (!propId && !sourceCaptureId && !sourcePraId) return;

            Swal.fire({
                title: 'Loading Temp File Details...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
            });

            try {
                const qs = new URLSearchParams();
                if (sourceCaptureId) {
                    qs.set('source_capture_id', String(sourceCaptureId));
                } else if (sourcePraId) {
                    qs.set('pra_id', String(sourcePraId));
                } else if (propId) {
                    // Last-resort fallback only; can be ambiguous when prop_id was
                    // incorrectly reused across multiple OP source rows.
                    qs.set('prop_id', String(propId));
                }

                const res = await fetch(`/mls-fileno/temp-file-details?${qs.toString()}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const body = await res.json();

                if (!res.ok || !body || body.success !== true || !body.data) {
                    throw new Error((body && body.message) ? body.message : 'Temp file details not found.');
                }
 
                const row = body.data;
                const sourceLabelMap = {
                    instrument_capture: 'Instrument Capture',
                    pra: 'PRA Fallback'
                };
                const sourceLabel = sourceLabelMap[String(body.source || '').toLowerCase()] || 'Unknown';
                const esc = (v) => {
                    const s = String(v ?? '—');
                    return s
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                };

                const normalizeText = (value, mode = 'general') => {
                    const raw = String(value ?? '').trim();
                    if (!raw) return '—';

                    const title = raw
                        .toLowerCase()
                        .replace(/\b\w/g, function (ch) { return ch.toUpperCase(); });

                    if (mode === 'name' || mode === 'location') {
                        return title;
                    }

                    if (mode === 'land_use') {
                        const map = {
                            RES: 'Residential',
                            RESIDENTIAL: 'Residential',
                            COM: 'Commercial',
                            COMMERCIAL: 'Commercial',
                            IND: 'Industrial',
                            INDUSTRIAL: 'Industrial',
                            AGR: 'Agricultural',
                            AGRICULTURAL: 'Agricultural'
                        };
                        return map[raw.toUpperCase()] || title;
                    }

                    return title
                        .replace(/\bOp\b/g, 'OP')
                        .replace(/\bLga\b/g, 'LGA')
                        .replace(/\bTp\b/g, 'TP');
                };

                const rawTempParty1 = String(row.party_1_name ?? '').trim();
                const rawTempParty2 = String(row.party_2_name ?? '').trim();
                const rawNewPartyName = String(newPartyName ?? '').trim();
                const normalizeName = (value) => String(value || '').trim().toLowerCase().replace(/\s+/g, ' ');

                // A valid source_pra_id can exist for normal OP commissioning too.
                // Treat it as change-of-name only when the entered new name actually differs.
                const isChangeOfName = rawNewPartyName !== ''
                    && rawTempParty2 !== ''
                    && normalizeName(rawNewPartyName) !== normalizeName(rawTempParty2);

                const tempParty1 = normalizeText(rawTempParty1, 'name');
                const tempParty2 = normalizeText(rawTempParty2, 'name');
                const newAppParty1 = isChangeOfName
                    ? normalizeText(rawTempParty2, 'name')
                    : tempParty1;
                const newAppParty2 = isChangeOfName
                    ? normalizeText(rawNewPartyName, 'name')
                    : normalizeText(rawTempParty2 || rawNewPartyName, 'name');

                const html = `
                    <div class="text-left text-sm font-sans">
                        <div class="hidden">
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                                Prop ID: <strong class="text-slate-700 ml-0.5">${esc(row.prop_id)}</strong>
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 7h18M3 12h18M3 17h18"/></svg>
                                Source: <strong class="text-slate-700 ml-0.5">${esc(sourceLabel)}</strong>
                            </span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="rounded-xl border border-sky-200 bg-sky-50/60 p-4">
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-sky-600 text-white font-extrabold text-sm">1</span>
                                    <span class="text-xs font-extrabold uppercase tracking-widest text-sky-700">Occupancy Permit</span>
                                </div>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-sky-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 13h4"/></svg>
                                        <div><div class="text-[10px] text-slate-400">Temp File No</div><div class="font-mono font-bold text-slate-800 text-xs">${esc(row.temp_fileno)}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-orange-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 7h10M7 12h10M7 17h5"/><rect x="2" y="2" width="20" height="20" rx="3"/></svg>
                                        <div><div class="text-[10px] text-slate-400">OP Type</div><div class="font-semibold text-orange-700 text-xs">${esc(normalizeText(row.op_type))}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-sky-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h8m-8 6h16"/></svg>
                                        <div><div class="text-[10px] text-slate-400">OP Serial No</div><div class="font-bold text-slate-800 text-xs">${esc(row.op_serial_number)}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-sky-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                        <div><div class="text-[10px] text-slate-400">Registration No</div><div class="font-semibold text-slate-800 text-xs">${esc(row.registration_number)}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-sky-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                                        <div><div class="text-[10px] text-slate-400">Party 1</div><div class="font-semibold text-slate-800 text-xs">${esc(normalizeText(row.orig_party_1_name || row.party_1_name, 'name'))}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-sky-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                                        <div><div class="text-[10px] text-slate-400">Party 2</div><div class="font-semibold text-slate-800 text-xs">${esc(normalizeText(row.orig_party_2_name || row.party_2_name, 'name'))}</div></div>
                                    </div>
                                    <div class="col-span-2 flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-sky-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0"/></svg>
                                        <div><div class="text-[10px] text-slate-400">Property</div><div class="font-semibold text-slate-800 text-xs leading-snug">${esc(normalizeText(row.property_description || row.property_location, 'location'))}</div></div>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-emerald-600 text-white font-extrabold text-sm">2</span>
                                    <span class="text-xs font-extrabold uppercase tracking-widest text-emerald-700">New Application</span>
                                </div>
                                <div class="mb-3 flex items-start gap-1.5">
                                    <svg class="w-3 h-3 mt-0.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    <div><div class="text-[10px] text-slate-400">New MLS File No</div><div class="font-mono font-extrabold text-emerald-800 text-sm">${esc(newMlsFilenoLabel || row.mlsFNo)}</div></div>
                                </div>
                                <div class="mb-3 flex items-start gap-1.5">
                                    <svg class="w-3 h-3 mt-0.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h8m-8 6h16"/></svg>
                                    <div><div class="text-[10px] text-slate-400">OP Serial No</div><div class="font-bold text-emerald-800 text-xs">${esc(row.op_serial_number)}</div></div>
                                </div>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                                        <div><div class="text-[10px] text-slate-400">Party 1</div><div class="font-semibold text-slate-800 text-xs">${esc(newAppParty1)}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                                        <div><div class="text-[10px] text-slate-400">Party 2</div><div class="font-semibold text-slate-800 text-xs">${esc(newAppParty2)}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 7h4l3 9 4-16 3 7h4"/></svg>
                                        <div><div class="text-[10px] text-slate-400">Land Use</div><div class="font-semibold text-slate-800 text-xs">${esc(normalizeText(row.land_use, 'land_use'))}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                                        <div><div class="text-[10px] text-slate-400">TP No</div><div class="font-semibold text-slate-800 text-xs">${esc(row.tp_no)}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="15" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
                                        <div><div class="text-[10px] text-slate-400">Plot No</div><div class="font-semibold text-slate-800 text-xs">${esc(row.plot_number)}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7m-14 0v8a1 1 0 001 1h3m10-9l2 2m-2-2v8a1 1 0 01-1 1h-3m-6 0l6-6"/></svg>
                                        <div><div class="text-[10px] text-slate-400">LGA</div><div class="font-semibold text-slate-800 text-xs">${esc(normalizeText(row.lga))}</div></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                Swal.fire({
                    icon: 'info',
                    title: `Occupancy Permit (OP) Details (${esc(tempFilenoLabel || row.temp_fileno || '—')}/${esc(newMlsFilenoLabel || row.mlsFNo || '—')})`,
                    html,
                    width: 900,
                    confirmButtonText: 'Close',
                });
            } catch (err) {
                Swal.fire('Error', err.message || 'Unable to load temp file details.', 'error');
            }
        }

        $(document).off('click.tempFileDetails', '.js-temp-file-link').on('click.tempFileDetails', '.js-temp-file-link', function (e) {
            e.preventDefault();
            const propId = $(this).data('prop-id');
            const sourceCaptureId = $(this).data('source-capture-id');
            const sourcePraId = $(this).data('source-pra-id');
            const tempFileno = $(this).data('temp-fileno');
            const newMlsFileno = $(this).data('new-mls-fileno');
            const newPartyName = $(this).data('new-party-name');
            showTempFileDetailsByPropId(propId, sourceCaptureId, sourcePraId, tempFileno, newMlsFileno, newPartyName);
        });
    });

    function openGeneratorModalMain() {
        console.log('openGeneratorModalMain() called');
        const modalEl = document.getElementById('generateModal');
        console.log('Generate Modal Element Found:', modalEl ? 'Yes' : 'No');
        if (modalEl) {
            console.log('Generate Modal x-data attribute:', modalEl.getAttribute('x-data'));
        }
        
        // Ensure Printer Manager is closed to prevent conflicts
        if (typeof closePrinterManager === 'function') {
            closePrinterManager();
        }
        
        resetTrackingIdDisplay('--');
        setActionButtonsDisabled(true);
        resetForm();
        document.getElementById('generateModal').classList.remove('hidden');

        // Fetch latest serial numbers from database
        getNextSerialNumber();

        // Set current time for commission time input
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const timeInput = document.getElementById('commissionTime');
        if (timeInput) {
            timeInput.value = `${hours}:${minutes}`;
        }

        // Ensure the serial number is properly set when modal opens
        setTimeout(() => {
            updateAlpineSerialNumber();
        }, 100);
    }

    function closeGenerateModal() {
        document.getElementById('generateModal').classList.add('hidden');
    }

    function resetForm() {
        // Reset the main form
        const form = document.getElementById('generateForm');
        if (form) {
            form.reset();
        }

        // Reset Alpine.js component data
        const modalContainer = document.querySelector('[x-data="fileNumberGenerator()"]');
        if (modalContainer && modalContainer._x_dataStack && modalContainer._x_dataStack[0]) {
            const component = modalContainer._x_dataStack[0];

            // Reset all form fields to initial values
            component.applicationType = 'new';
            component.allocatedByFilter = null;
            component.defaultAllocationType = '';
            component._currentAllocationSourceType = 'default';
            component.fileName = '';
            component.landUse = '';
            component.fileOption = '';
            component.existingFileNo = '';
            component.middlePrefix = 'KN';
            component.year = new Date().getFullYear();
            component.serialNo = nextSerialNo;
            component.preview = '-';
            component.sourceInstrumentCaptureId = '';
            component.sourcePropId = '';
            component.sourcePraId = '';
            component.sourceOpSerialNumber = '';
            component.sourceRegistrationNumber = '';
            component.sourceSerialNo = '';
            component.sourcePageNo = '';
            component.sourceVolumeNo = '';
            component.sourceOriginalOwner = '';
            component.requireOpSource = false;
            component.subSource = '';

            // Reset related file fields
            component.relatedFileNo = '';
            component.relatedFileTitle = '';
            component.relatedFileIndexingId = '';
            component.isRecertificationPrefix = false;

            // Update the preview
            component.updatePreview();
        }

        // Reset override mode
        isOverrideMode = false;

        // Reset form field states
        const yearInput = document.getElementById('year');
        const serialInput = document.getElementById('serialNo');

        if (yearInput) {
            yearInput.readOnly = true;
            yearInput.classList.remove('bg-white', 'text-gray-900');
            yearInput.classList.add('bg-gray-100', 'text-gray-600');
            yearInput.value = new Date().getFullYear();
        }

        if (serialInput) {
            serialInput.readOnly = true;
            serialInput.classList.remove('bg-white', 'text-gray-900');
            serialInput.classList.add('bg-gray-100', 'text-gray-600');
            serialInput.value = nextSerialNo;
        }

        // Reset radio buttons to default (Direct Allocation)
        const newRadio = document.querySelector('input[name="application_type"][value="new"]');
        if (newRadio) {
            newRadio.checked = true;
        }

        // Reset allocation source radio to Default
        const defaultSourceRadio = document.querySelector('input[name="allocation_source_type"][value="default"]');
        if (defaultSourceRadio) {
            defaultSourceRadio.checked = true;
        }

        // Clear all text inputs
        const textInputs = form.querySelectorAll('input[type="text"]:not([disabled])');
        textInputs.forEach(input => {
            if (input.id !== 'middlePrefix') { // Keep middle prefix as 'KN'
                input.value = '';
            }
        });

        // Reset all select dropdowns
        const selects = form.querySelectorAll('select');
        selects.forEach(select => {
            select.selectedIndex = 0;
        });

        // Reset hidden sections
        document.getElementById('middlePrefixSection')?.classList.add('hidden');
        document.getElementById('yearSection')?.classList.add('hidden');

        // Reset manual input toggles to default (use dropdown) - Alpine.js will handle this automatically
        // when the component data is reset, but we can also trigger it manually
        setTimeout(() => {
            const extensionInputRadios = form.querySelectorAll('input[name="extension_input_type"][value="false"]');
            extensionInputRadios.forEach(radio => {
                radio.checked = true;
            });

            const temporaryInputRadios = form.querySelectorAll('input[name="temporary_input_type"][value="false"]');
            temporaryInputRadios.forEach(radio => {
                radio.checked = true;
            });
        }, 100);

        // Update the preview one more time
        updatePreview();
        resetTrackingIdDisplay('--');
        setActionButtonsDisabled(true);
    }

    function updateApplicationType(type) {
        const newOptions = document.getElementById('newOptions');
        const conversionOptions = document.getElementById('conversionOptions');
        const rcOptions = document.querySelector('optgroup[label="RC Options"]');
        const landUseSelect = document.getElementById('landUse');

        if (type === 'new') {
            newOptions.style.display = 'block';
            conversionOptions.style.display = 'none';
            rcOptions.style.display = 'block';
        } else {
            newOptions.style.display = 'none';
            conversionOptions.style.display = 'block';
            rcOptions.style.display = 'none'; // Hide RC options for conversion
        }

        // Reset land use selection
        landUseSelect.value = '';
        updatePreview();
    }

    // Separate function for just updating preview without form manipulation
    function updatePreviewOnly() {
        const serialNo = document.getElementById('serialNo')?.value;
        const year = document.getElementById('year')?.value;
        const landUse = document.getElementById('landUse')?.value;
        const fileOption = document.getElementById('fileOption')?.value;

        // Get existing file number from Alpine.js component instead of DOM
        let existingFileNo = '';
        const modalContainer = document.querySelector('[x-data="fileNumberGenerator()"]');
        if (modalContainer && modalContainer._x_dataStack && modalContainer._x_dataStack[0]) {
            existingFileNo = modalContainer._x_dataStack[0].existingFileNo;
        }

        const middlePrefix = document.getElementById('middlePrefix')?.value || '';
        const preview = document.getElementById('mlsfPreview');

        // Preview generation logic only
        let previewText = '-';

        if (fileOption === 'extension' && existingFileNo) {
            previewText = existingFileNo.trim().replace(/-$/, '') + ' AND EXTENSION';
        } else if (fileOption === 'temporary' && existingFileNo) {
            previewText = existingFileNo + '(T)';
        } else if (fileOption === 'miscellaneous' && middlePrefix && serialNo && year) {
            previewText = `MISC-${middlePrefix}-${year}-${serialNo}`;
        } else if (fileOption === 'old_mls' && serialNo) {
            previewText = `KN ${serialNo}`;
        } else if (fileOption === 'sltr' && serialNo) {
            previewText = `SLTR-${serialNo}`;
        } else if (fileOption === 'sit' && serialNo) {
            previewText = `SIT-${year}-${serialNo}`;
        } else if (fileOption === 'normal' && serialNo && year && landUse) {
            previewText = `${landUse}-${year}-${serialNo}`;
        }

        if (preview) {
            preview.textContent = previewText;

            if (previewText !== '-') {
                preview.classList.remove('text-gray-400');
                preview.classList.add('text-green-600');
            } else {
                preview.classList.remove('text-green-600');
                preview.classList.add('text-gray-400');
            }
        }

        const lookupCandidate = deriveGroupingLookupCandidate(fileOption, previewText, existingFileNo);
        if (lookupCandidate) {
            queueGroupingLookup(lookupCandidate);
        } else {
            resetTrackingIdDisplay('--');
        } 
    }

    async function updatePreview() {
        const serialNoField = document.getElementById('serialNo');
        const year = document.getElementById('year')?.value;
        const landUse = document.getElementById('landUse')?.value;
        const prefix = document.getElementById('prefix')?.value; // Get Prefix
        const fileOption = document.getElementById('fileOption')?.value;
        let serialNo = serialNoField?.value;

        // Get existing file number from Alpine.js component instead of DOM
        let existingFileNo = '';
        const modalContainer = document.querySelector('[x-data="fileNumberGenerator()"]');
        if (modalContainer && modalContainer._x_dataStack && modalContainer._x_dataStack[0]) {
            existingFileNo = modalContainer._x_dataStack[0].existingFileNo;
        }

        const middlePrefix = document.getElementById('middlePrefix')?.value || '';
        const preview = document.getElementById('mlsfPreview');
        const serialDescription = document.getElementById('serialNoDescription');

        // Only call updateGenerateForm if elements exist and not during text input for miscellaneous files
        if (document.getElementById('middlePrefixSection') &&
            document.getElementById('yearSection')) {
            // Don't reset form if we're actively typing in miscellaneous serial field
            const isTypingInMiscSerial = fileOption === 'miscellaneous' &&
                document.activeElement === serialNoField &&
                serialNoField.getAttribute('data-text-field') === 'true';

            if (!isTypingInMiscSerial) {
                updateGenerateForm(fileOption);
            }
        }

        // Attempt reservation for normal flow
        const hasCode = prefix || landUse;
        const batchModeToggled = document.querySelector('[x-model="batchMode"]')?.checked;
        const batchQty = parseInt(document.getElementById('batchQuantity')?.value || '1');

        if (fileOption === 'normal' && hasCode && year && !isOverrideMode) {
            try {
                if (typeof commissionModalReservation !== 'undefined') {
                    if (batchModeToggled && batchQty > 1) {
                        const batchResult = await commissionModalReservation.reserveBatch(prefix || landUse, landUse || prefix, year, batchQty);
                        if (batchResult?.success) {
                            serialNo = batchResult.startSerial;
                            if (serialNoField) {
                                serialNoField.value = batchResult.startSerial;
                            }
                            if (modalContainer && modalContainer._x_dataStack && modalContainer._x_dataStack[0]) {
                                const alpineData = modalContainer._x_dataStack[0];
                                alpineData.serialNo = batchResult.startSerial;
                                alpineData.serialRangePreview = `${batchResult.startSerial} to ${batchResult.endSerial}`;
                            }
                        }
                    } else {
                        const reserveResult = await commissionModalReservation.reserve(prefix || landUse, landUse || prefix, year);
                        if (reserveResult?.success) {
                            serialNo = reserveResult.serialNumber;
                            if (serialNoField) {
                                serialNoField.value = reserveResult.serialNumber;
                            }
                            if (modalContainer && modalContainer._x_dataStack && modalContainer._x_dataStack[0]) {
                                modalContainer._x_dataStack[0].serialNo = reserveResult.serialNumber;
                            }
                        }
                    }
                }
            } catch (error) {
                console.error('[Reservation] Failed to reserve serial', error);
            }
        }

        // Preview generation logic - exact same as capture_existing
        let previewText = '-';

        // Generate base file number for extension/temporary fallback
        let baseFileNumber = '';
        if (serialNo && year) {
            const code = prefix || landUse;
            if (code) {
                baseFileNumber = `${code}-${year}-${serialNo}`;
            }
        }

        if (fileOption === 'extension') {
            previewText = (existingFileNo || baseFileNumber).trim().replace(/-$/, '') + ' AND EXTENSION';
        } else if (fileOption === 'temporary') {
            previewText = (existingFileNo || baseFileNumber) + '(T)';
        } else if (fileOption === 'miscellaneous' && middlePrefix && serialNo && year) {
            previewText = `MISC-${middlePrefix}-${year}-${serialNo}`;
        } else if (fileOption === 'old_mls' && serialNo) {
            previewText = `KN ${serialNo}`;
        } else if (fileOption === 'sltr' && serialNo) {
            previewText = `SLTR-${serialNo}`;
        } else if (fileOption === 'sit' && serialNo) {
            previewText = `SIT-${year}-${serialNo}`;
        } else if (fileOption === 'normal' && serialNo && year) {
            const code = prefix || landUse;
            if (code) {
                if (batchModeToggled && batchQty > 1) {
                    const endSerial = parseInt(serialNo) + batchQty - 1;
                    previewText = `${code}-${year}-${serialNo} to ${code}-${year}-${endSerial}`;
                } else {
                    previewText = `${code}-${year}-${serialNo}`;
                }
            }
        }

        preview.textContent = previewText;

        // Update Alpine.js preview property (only if not in batch mode)
        if (modalContainer && modalContainer._x_dataStack && modalContainer._x_dataStack[0]) {
            const alpineData = modalContainer._x_dataStack[0];
            if (!alpineData.batchMode) {
                alpineData.preview = previewText;
            }
        }

        if (previewText !== '-') {
            preview.classList.remove('text-gray-400');
            preview.classList.add('text-green-600');
        } else {
            preview.classList.remove('text-green-600');
            preview.classList.add('text-gray-400');
        }

        const lookupCandidate = deriveGroupingLookupCandidate(fileOption, previewText, existingFileNo);
        if (lookupCandidate) {
            queueGroupingLookup(lookupCandidate);
        } else {
            resetTrackingIdDisplay('--');
        }
    }

    // Add the dedicated form update function like capture_existing
    function updateGenerateForm(type) {
        const middlePrefixSection = document.getElementById('middlePrefixSection');
        const yearSection = document.getElementById('yearSection');
        const serialNoField = document.getElementById('serialNo');
        const serialDescription = document.getElementById('serialNoDescription');

        // Hide all sections first
        middlePrefixSection.classList.add('hidden');
        yearSection.classList.add('hidden');

        // Reset serial number field properties and remove all restrictive attributes
        serialNoField.type = 'text';
        serialNoField.removeAttribute('min');
        serialNoField.removeAttribute('max');
        serialNoField.removeAttribute('step');
        serialNoField.removeAttribute('maxlength');
        serialNoField.removeAttribute('pattern');

        // Reset text field tracking attribute
        serialNoField.removeAttribute('data-text-field');

        if (type === 'normal') {
            yearSection.classList.remove('hidden');
            // For normal files, keep serial as number for auto-padding
            serialNoField.type = 'number';
            serialNoField.setAttribute('min', '1');
            serialNoField.setAttribute('max', '9999');
            serialNoField.placeholder = 'Auto-generated';
            serialNoField.readOnly = true;
            serialNoField.classList.add('bg-gray-100', 'text-gray-600');
            serialNoField.classList.remove('bg-white', 'text-gray-900');
            serialNoField.value = nextSerialNo;

            if (serialDescription) {
                serialDescription.textContent = 'Auto-generated';
                serialDescription.className = 'text-xs text-gray-500 mt-1';
            }
        } else if (type === 'temporary') {
            // Temporary files use the toggle section in right column
            serialNoField.placeholder = 'Not required for temporary files';
            serialNoField.value = '';
            serialNoField.readOnly = true;
            serialNoField.classList.add('bg-gray-100', 'text-gray-600');
            serialNoField.classList.remove('bg-white', 'text-gray-900');

            if (serialDescription) {
                serialDescription.textContent = 'Select existing file for temporary version';
                serialDescription.className = 'text-xs text-blue-600 mt-1 font-medium';
            }
        } else if (type === 'extension') {
            // Extension files use the toggle section in right column
            yearSection.classList.remove('hidden');
            serialNoField.placeholder = 'Not required for extensions';
            serialNoField.value = '';
            serialNoField.readOnly = true;
            serialNoField.classList.add('bg-gray-100', 'text-gray-600');
            serialNoField.classList.remove('bg-white', 'text-gray-900');

            if (serialDescription) {
                serialDescription.textContent = 'Not required for extensions';
                serialDescription.className = 'text-xs text-gray-500 mt-1';
            }
        } else if (type === 'miscellaneous' || type === 'sltr' || type === 'sit' || type === 'old_mls') {
            if (type === 'miscellaneous') {
                middlePrefixSection.classList.remove('hidden');
                yearSection.classList.remove('hidden');
            } else if (type === 'sit') {
                yearSection.classList.remove('hidden');
            }
            // Make serial number plain text and editable for these types
            serialNoField.type = 'text';
            serialNoField.readOnly = false;
            serialNoField.classList.remove('bg-gray-100', 'text-gray-600');
            serialNoField.classList.add('bg-white', 'text-gray-900');

            // Only clear value if it's not already in a text field state (initial setup)
            if (serialNoField.getAttribute('data-text-field') !== 'true') {
                serialNoField.value = '';
                serialNoField.setAttribute('data-text-field', 'true');
            }

            // Completely remove all input restrictions for text fields
            serialNoField.removeAttribute('min');
            serialNoField.removeAttribute('max');
            serialNoField.removeAttribute('step');
            serialNoField.removeAttribute('maxlength');
            serialNoField.removeAttribute('pattern');
            serialNoField.setAttribute('inputmode', 'text');

            if (type === 'miscellaneous') {
                serialNoField.placeholder = 'Enter custom serial (e.g., 001, ABC123)';
            } else if (type === 'sltr') {
                serialNoField.placeholder = 'Enter SLTR serial (e.g., 001, 2024-001)';
            } else if (type === 'sit') {
                serialNoField.placeholder = 'Enter SIT serial (e.g., 001, 2024-001)';
            } else if (type === 'old_mls') {
                serialNoField.placeholder = 'Enter Old MLS number (e.g., 5467, 34874857488758)';
            }

            if (serialDescription) {
                serialDescription.textContent = 'Manual entry';
                serialDescription.className = 'text-xs text-blue-600 mt-1 font-medium';
            }
        }
    }

    function loadExistingFileNumbers() {
        fetch('{{ route("file-numbers.existing") }}')
            .then(response => response.json())
            .then(data => {
                // Populate extension dropdown
                const extensionSelect = document.getElementById('extensionFileNo');
                if (extensionSelect) {  
                    extensionSelect.innerHTML = '<option value="">Select existing file number...</option>';

                    data.forEach(fileNo => {
                        const option = document.createElement('option');
                        option.value = fileNo.mlsfNo;
                        option.textContent = fileNo.mlsfNo;
                        extensionSelect.appendChild(option);
                    });
                }

                // Populate temporary dropdown
                const temporarySelect = document.getElementById('temporaryFileNo');
                if (temporarySelect) {
                    temporarySelect.innerHTML = '<option value="">Select existing file number...</option>';

                    data.forEach(fileNo => {
                        const option = document.createElement('option');
                        option.value = fileNo.mlsfNo;
                        option.textContent = fileNo.mlsfNo;
                        temporarySelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading existing file numbers:', error);
            });
    }

    // Store serial numbers per land use
    let serialNumbersMap = {};

    function getNextSerialNumber() {
        // Fetch serial status for all land uses
        fetch('{{ route("mls-fileno.serial-status") }}')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    // Build map of land use to next serial
                    serialNumbersMap = {};
                    data.data.forEach(item => {
                        // Next serial is last_serial + 1
                        serialNumbersMap[item.land_use] = item.last_serial + 1;
                    });
                    console.log('Serial numbers map loaded for year ' + (data.data[0] ? data.data[0].year : ''), serialNumbersMap);

                    // Update the modal if it's open
                    const modalElement = document.getElementById('generateModal');
                    if (modalElement && !modalElement.classList.contains('hidden')) {
                        updateAlpineSerialNumber();
                    }
                } else {
                    console.error('Failed to load serial numbers:', data.message);
                }
            })
            .catch(error => {
                console.error('Error getting serial numbers:', error);
                serialNumbersMap = {}; // Reset to empty map
            });
    }

    // Function to get next serial for specific land use
    function getNextSerialForLandUse(landUse) {
        if (!landUse) return 1;
        // Remove any prefix like "CON-" to get the base land use
        const baseLandUse = landUse.replace(/^CON-/, '');
        const nextSerial = serialNumbersMap[landUse] || serialNumbersMap[baseLandUse] || 1;
        console.log(`Next serial for ${landUse}:`, nextSerial);
        return nextSerial;
    }

    // Function to update Alpine.js component with new serial number
    function updateAlpineSerialNumber() {
        // Try to update via Alpine component method
        const modalContainer = document.querySelector('[x-data="fileNumberGenerator()"]');
        if (modalContainer && modalContainer._x_dataStack && modalContainer._x_dataStack[0]) {
            const component = modalContainer._x_dataStack[0];
            if (component.refreshSerialNumber) {
                component.refreshSerialNumber();
                return;
            }
        }

        // Fallback to direct DOM manipulation
        const serialNoElement = document.getElementById('serialNo');
        if (serialNoElement && nextSerialNo && !isOverrideMode) {
            serialNoElement.value = nextSerialNo;
            // Trigger both input and change events to ensure Alpine.js updates
            serialNoElement.dispatchEvent(new Event('input', { bubbles: true }));
            serialNoElement.dispatchEvent(new Event('change', { bubbles: true }));
            updatePreview();
        }
    }

    function showOverrideModal() {
        document.getElementById('overrideModal').classList.remove('hidden');
        document.getElementById('overrideYear').value = document.getElementById('year').value;
        document.getElementById('overrideSerialNo').value = document.getElementById('serialNo').value;
    }

    function closeOverrideModal() {
        document.getElementById('overrideModal').classList.add('hidden');
    }



    
    function submitOverrideForm(event) {
        event.preventDefault();

        const overrideYear = document.getElementById('overrideYear').value;
        const overrideSerialNo = document.getElementById('overrideSerialNo').value;
        const overrideExtension = document.getElementById('overrideExtension').checked;

        // Apply override values to main form
        const yearInput = document.getElementById('year');
        const serialInput = document.getElementById('serialNo');

        yearInput.value = overrideYear;
        serialInput.value = overrideSerialNo;

        // Enable manual editing
        isOverrideMode = true;
        yearInput.readOnly = false;
        serialInput.readOnly = false;
        yearInput.classList.remove('bg-gray-100', 'text-gray-600');
        serialInput.classList.remove('bg-gray-100', 'text-gray-600');
        yearInput.classList.add('bg-white', 'text-gray-900');
        serialInput.classList.add('bg-white', 'text-gray-900');

        // Trigger events to update Alpine.js state
        yearInput.dispatchEvent(new Event('input', { bubbles: true }));
        serialInput.dispatchEvent(new Event('input', { bubbles: true }));

        if (overrideExtension) {
            document.querySelector('input[name="file_option"][value="extension"]').checked = true;
        }

        updatePreview();
        closeOverrideModal();
    }

    function openMigrationModal() {
        document.getElementById('migrationModal').classList.remove('hidden');
    }

    function closeMigrationModal() {
        document.getElementById('migrationModal').classList.add('hidden');
    }

    function submitMigrationForm(event) {
        event.preventDefault();

        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading on button
        showLoadingButton(submitBtn, originalText);

        // Show global loading
        showGlobalLoading('Migrating data... Please wait.');

        const formData = new FormData(document.getElementById('migrationForm'));

        fetch('{{ route("file-numbers.migrate") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
            .then(response => response.json())
            .then(data => {
                hideGlobalLoading();
                hideLoadingButton(submitBtn, originalText);

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        confirmButtonColor: '#10b981'
                    });
                    closeMigrationModal();
                    table.ajax.reload();
                    updateStats();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'An error occurred during migration',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                hideGlobalLoading();
                hideLoadingButton(submitBtn, originalText);
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while migrating data',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    function submitForm(event) {
        event.preventDefault();

        // Check for batch mode
        const modalContainer = document.querySelector('[x-data="fileNumberGenerator()"]');
        let alpineData = null;
        if (modalContainer && modalContainer._x_dataStack) {
            alpineData = modalContainer._x_dataStack[0];

            // If batch mode is active, show summary modal instead
            if (alpineData.batchMode) {
                showBatchSummaryModal(alpineData);
                return;
            }
        }

        // LGA is required when Application Type is Conversion
        if (alpineData && alpineData.applicationType === 'conversion') {
            const lgaValue = (alpineData.lga || '').toString().trim();
            if (!lgaValue) {
                Swal.fire({
                    icon: 'warning',
                    title: 'LGA Required',
                    text: 'Please select an LGA for Conversion applications.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }
        }

        // Related file is required for Recertification (-RC) prefixes
        if (alpineData && alpineData.isRecertificationPrefix) {
            const relatedFile = (alpineData.relatedFileNo || '').toString().trim();
            const relatedTitle = (alpineData.relatedFileTitle || '').toString().trim();
            if (!relatedFile) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Related File Required',
                    text: 'Please select the original file number that this recertification relates to.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }
            if (!relatedTitle) {
                Swal.fire({
                    icon: 'warning',
                    title: 'File Title Required',
                    text: 'Please enter the file title for the related original file.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }
        }

        // File Name is required for all new file number generations
        {
            const fileNameValue = (alpineData && alpineData.fileName
                ? alpineData.fileName
                : (document.getElementById('fileName')?.value || '')).toString().trim();
            if (!fileNameValue) {
                Swal.fire({
                    icon: 'warning',
                    title: 'File Name Required',
                    text: 'Please enter a File Name before generating the file number.',
                    confirmButtonColor: '#f59e0b'
                }).then(() => {
                    const fileNameInput = document.getElementById('fileName');
                    if (fileNameInput) fileNameInput.focus();
                });
                return;
            }
        }

        const submitBtn = event.submitter || event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.innerHTML : '';

        if (submitBtn) {
            showLoadingButton(submitBtn, originalText);
        }

        showGlobalLoading('Generating file number...');

        const formData = new FormData(document.getElementById('generateForm'));

        // Use prefix as land_use if available (for normal files)
        const prefix = document.getElementById('prefix')?.value;
        const fileOption = document.getElementById('fileOption')?.value;
        if (prefix && fileOption === 'normal') {
            formData.set('land_use', prefix);
        }

        // SIT files: force land_use to 'SIT' and customer_type to 'Government'
        if (fileOption === 'sit') {
            formData.set('land_use', 'SIT');
            formData.set('customer_type', 'Government');
        }

        // Handle Direct Allocation (is_allocated check)
        if (alpineData && alpineData.applicationType === 'new' && alpineData.allocationId) {
            formData.set('allocation_id', alpineData.allocationId);
            // Ensure file_name is correctly set from Alpine if it wasn't picked up
            if (!formData.get('file_name')) {
                formData.set('file_name', alpineData.fileName);
            }
        }

        // Debug: Log the form data
        console.log('Form data being submitted:');
        for (let [key, value] of formData.entries()) {
            console.log(`${key}: ${value}`);
        }

        const trackingIdValue = (formData.get('tracking_id') || '').toString().trim();
        if (trackingIdValue === '') {
            hideGlobalLoading();
            if (submitBtn) {
                hideLoadingButton(submitBtn, originalText);
            }
            Swal.fire({
                icon: 'warning',
                title: 'Tracking ID Required',
                text: 'Tracking ID must be fetched from grouping table before generating this file number.',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        let sourceCaptureId = (formData.get('source_instrument_capture_id') || '').toString().trim();
        const hasPreSyncedPraContext = !!(window.pendingExistingOpPraContext && window.pendingExistingOpPraContext.prop_id);
        if (hasPreSyncedPraContext && window.pendingExistingOpPraContext.source_pra_id) {
            formData.set('source_pra_id', String(window.pendingExistingOpPraContext.source_pra_id));
        }

        // Manual Capture Existing OP path: PRA has already been pre-synced before
        // opening commission modal, so source capture ID is optional here.
        if (hasPreSyncedPraContext && sourceCaptureId === '') {
            formData.set('require_op_source', '0');
            if (alpineData) {
                alpineData.requireOpSource = false;
            }
        }

        // OSS Change-of-Name commissioning should allow normal generation without OP linkage.
        const currentUrl = new URL(window.location.href);
        const isOssCommissioningFlow =
            currentUrl.pathname.includes('/lands-one-stop-shop/') ||
            currentUrl.searchParams.get('source') === 'lands-one-stop-shop';

        if (isOssCommissioningFlow && sourceCaptureId === '' && !hasPreSyncedPraContext) {
            formData.set('require_op_source', '0');
            if (alpineData) {
                alpineData.requireOpSource = false;
            }
        }

        if (alpineData && alpineData.requireOpSource === true && sourceCaptureId === '' && !hasPreSyncedPraContext && window.lastCommissionSourceOp?.id) {
            // Recover linkage when a duplicate commission modal/form instance exists.
            sourceCaptureId = String(window.lastCommissionSourceOp.id).trim();
            formData.set('source_instrument_capture_id', sourceCaptureId);
            formData.set('source_prop_id', window.lastCommissionSourceOp.prop_id || '');
            formData.set('source_op_serial_number', window.lastCommissionSourceOp.op_serial_number || '');
            formData.set('source_registration_number', window.lastCommissionSourceOp.registration_number || '');
            formData.set('source_serial_no', window.lastCommissionSourceOp.serial_no || '');
            formData.set('source_page_no', window.lastCommissionSourceOp.page_no || '');
            formData.set('source_volume_no', window.lastCommissionSourceOp.volume_no || '');
            formData.set('source_original_owner', window.lastCommissionSourceOp.original_owner || '');
            formData.set('require_op_source', '1');
        }

        if (alpineData && alpineData.requireOpSource === true && sourceCaptureId === '' && !hasPreSyncedPraContext && !isOssCommissioningFlow) {
            hideGlobalLoading();
            if (submitBtn) {
                hideLoadingButton(submitBtn, originalText);
            }
            Swal.fire({
                icon: 'warning',
                title: 'Source OP Required',
                text: 'Please capture/select the Occupancy Permit (OP) record first so commissioning can mirror to PRA, deed, and instrument tables.',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        // Send to new MLS generation endpoint
        const controller = new AbortController();
        const timeoutHandle = setTimeout(() => controller.abort(), GENERATE_REQUEST_TIMEOUT_MS);

        fetch('{{ route("mls-fileno.generate") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            signal: controller.signal
        })
            .then(response => response.json())
            .then(async data => {
                clearTimeout(timeoutHandle);
                hideGlobalLoading();
                if (submitBtn) {
                    hideLoadingButton(submitBtn, originalText);
                }

                if (data.success) {
                    const generatedFileNumber = (
                        data?.data?.file_number ||
                        data?.data?.full_file_number ||
                        data?.data?.mlsfNo ||
                        data?.file_number ||
                        ''
                    ).toString().trim();
                    const commissionedFileName = (
                        data?.data?.file_name ||
                        data?.data?.FileName ||
                        alpineData?.fileName ||
                        ''
                    ).toString().trim();

                    // Confirm the active reservation so it is marked as used
                   
                    if (typeof commissionModalReservation !== 'undefined') {
                        try {
                            await commissionModalReservation.confirm();
                        } catch (e) {
                            console.warn('[Reservation] Confirm failed', e);
                        }
                    }


                    getNextSerialNumber();

                    // Clear cache to ensure fresh data on next load
                    fetch('{{ route("file-numbers.clear-cache") }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                    }).catch(e => console.warn('Cache clear failed:', e));

                    // If a new (non-existing) OP was captured, submit the PRA record
                    // now with the real commissioned file number.
                    if (typeof window.submitPendingNewOpPra === 'function' && window.pendingNewOpFormData) {
                        await window.submitPendingNewOpPra(generatedFileNumber);
                    }

                    // Existing OP flow: after temp-file PRA sync on capture, update PRA
                    // to the commissioned file number using the same prop_id.
                    // Grantor = original allottee (from source_op_party_2), NOT KSG.
                    if (typeof window.submitPendingExistingOpPra === 'function' && window.pendingExistingOpPraContext) {
                        await window.submitPendingExistingOpPra(
                            generatedFileNumber,
                            commissionedFileName
                        );
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'File Number Generated!',
                        html: `<div class="text-left">
                            <p class="text-2xl font-black font-mono text-blue-700 text-center mb-3">${generatedFileNumber || data.message}</p>
                            ${data.data?.tracking_id ? `<p class="text-xs text-slate-500 text-center mb-3">Tracking ID: <span class="font-mono font-semibold">${data.data.tracking_id}</span></p>` : ''}
                            ${data.mirror_created ? '<p class="mt-2 text-emerald-700 text-sm font-semibold text-center">&#10003;.</p>' : ''}
                        </div>`,
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        // If table has no AJAX source (server-rendered), full page reload
                        if (!table.ajax || !table.ajax.url()) {
                            window.location.reload();
                        }
                    });

                    // Reset the form and close modal
                    resetForm();
                    closeGenerateModal();
                    try { table.ajax.reload(); } catch(e) { /* server-rendered table, reload handled above */ }
                    try { updateStats(); } catch(e) { /* may not exist on all pages */ }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'An error occurred',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                clearTimeout(timeoutHandle);
                hideGlobalLoading();
                if (submitBtn) {
                    hideLoadingButton(submitBtn, originalText);
                }
                console.error('Error:', error);
                const isTimeout = error && (error.name === 'AbortError' || String(error).includes('aborted'));
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: isTimeout
                        ? 'Generation timed out on the server. Please retry. If it repeats, we need to optimize the backend generate flow.'
                        : 'An error occurred while generating the file number',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    // Batch Summary Modal Functions
    function showBatchSummaryModal(alpineData) {
        // Populate summary data
        const startSerial = parseInt(alpineData.serialNo);
        const endSerial = startSerial + parseInt(alpineData.batchQuantity) - 1;

        document.getElementById('summaryBatchSize').textContent = `${alpineData.batchQuantity} files`;
        document.getElementById('summarySerialRange').textContent = `${startSerial} to ${endSerial}`;
        document.getElementById('summaryLandUse').textContent = alpineData.landUseFullText || alpineData.landUse;
        document.getElementById('summaryFileName').textContent = alpineData.fileName || '-';
        document.getElementById('summaryTotalFiles').textContent = alpineData.batchQuantity;

        // Set current time for commission time input
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const timeString = `${hours}:${minutes}`;
        const timeInput = document.getElementById('commissionTime');
        if (timeInput) {
            timeInput.value = timeString;
        }

        // Generate file number preview
        const code = alpineData.prefix || alpineData.landUse;
        const fileNumberPreview = `${code}-${alpineData.year}-${startSerial} to ${code}-${alpineData.year}-${endSerial}`;
        document.getElementById('summaryFileNumbers').textContent = fileNumberPreview;

        // Populate location details list
        const locationList = document.getElementById('summaryLocationList');
        locationList.innerHTML = '';

        alpineData.locationEntries.forEach((entry, index) => {
            const serial = startSerial + index;
            const fileNo = `${code}-${alpineData.year}-${serial}`;

            const entryDiv = document.createElement('div');
            entryDiv.className = 'mb-3 pb-3 border-b border-gray-200 last:border-0';
            entryDiv.innerHTML = `
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-900 mb-1">${fileNo}</p>
                        <div class="grid grid-cols-2 gap-2 text-xs text-gray-600">
                            <div><span class="font-medium">Plot:</span> ${entry.plotNo || 'N/A'}</div>
                            <div><span class="font-medium">TP:</span> ${entry.tpNo || 'N/A'}</div>
                            <div><span class="font-medium">Location:</span> ${entry.location || 'N/A'}</div>
                            <div><span class="font-medium">LGA:</span> ${entry.lga || 'N/A'}</div>
                      
                        </div>
                    </div>
                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full">#${index + 1}</span>
                </div>
            `;
            locationList.appendChild(entryDiv);
        });

        // Show the modal
        document.getElementById('batchSummaryModal').classList.remove('hidden');

        // Re-initialize lucide icons for the modal
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    function closeBatchSummaryModal() {
        document.getElementById('batchSummaryModal').classList.add('hidden');
    }

    function confirmBatchGeneration() {
        const confirmBtn = document.getElementById('confirmBatchButton');
        const originalText = confirmBtn.innerHTML;

        showLoadingButton(confirmBtn, originalText);
        showGlobalLoading('Generating batch file numbers...');

        // Get Alpine.js data
        const modalContainer = document.querySelector('[x-data="fileNumberGenerator()"]');
        if (!modalContainer || !modalContainer._x_dataStack) {
            console.error('Alpine.js component not found');
            hideGlobalLoading();
            hideLoadingButton(confirmBtn, originalText);
            return;
        }
        const alpineData = modalContainer._x_dataStack[0];

        // Ensure location entries are synced if "Apply to All" is checked but not triggered
        if (alpineData.applyLocationToAll && alpineData.locationEntries.length > 0) {
            const currentEntry = alpineData.locationEntries[alpineData.currentEntryIndex] || alpineData.locationEntries[0];
            if (currentEntry) {
                for (let i = 0; i < alpineData.batchQuantity; i++) {
                     if (alpineData.locationEntries[i]) {
                         alpineData.locationEntries[i].plotNo = currentEntry.plotNo;
                         alpineData.locationEntries[i].tpNo = currentEntry.tpNo;
                         alpineData.locationEntries[i].location = currentEntry.location;
                         alpineData.locationEntries[i].lga = currentEntry.lga;
                     }
                }
            }
        }

        // Prepare batch data
        const batchData = {
            batch_mode: true,
            batch_quantity: alpineData.batchQuantity,
            application_type: alpineData.applicationType,
            file_option: alpineData.fileOption,
            file_name: alpineData.fileName,
            land_use: alpineData.prefix || alpineData.landUse,
            year: alpineData.year,
            serial_start: alpineData.serialNo,
            location_entries: alpineData.locationEntries,
            commissioned_by: document.getElementById('commissionedBy').value,
            commission_date: document.getElementById('commissionDate').value,
            commission_time: document.getElementById('commissionTime').value,
            customer_type: alpineData.customerType,
            purpose_id: alpineData.purpose,
            sub_source: alpineData.subSource || '',
            // New fields
            phone_no: document.getElementById('generatePhoneNo')?.value || '',
            address: document.getElementById('generateAddress')?.value || '',
            rep_phone_no: document.getElementById('generateRepPhoneNo')?.value || '',
            rep_address: document.getElementById('generateRepAddress')?.value || ''
        };

        // Send batch generation request
        fetch('{{ route("mls-fileno.generate-batch") }}', {
            method: 'POST',
            body: JSON.stringify(batchData),
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
            .then(response => response.json())
            .then(async data => {
                hideGlobalLoading();
                hideLoadingButton(confirmBtn, originalText);

                if (data.success) {
                    // Confirm batch reservations when generation succeeds
                    if (typeof commissionModalReservation !== 'undefined') {
                        try {
                            await commissionModalReservation.confirmBatch();
                        } catch (e) {
                            console.warn('[Reservation] Batch confirm failed', e);
                        }
                    }

                    // Update next serial number
                    getNextSerialNumber();

                    // Clear cache
                    fetch('{{ route("file-numbers.clear-cache") }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                    }).catch(e => console.warn('Cache clear failed:', e));

                    Swal.fire({
                        icon: 'success',
                        title: 'Batch Generated!',
                        html: `<p>${data.message}</p><p class="text-sm text-gray-600 mt-2">Generated files: ${data.files.join(', ')}</p>`,
                        confirmButtonColor: '#10b981',
                        width: '600px'
                    });

                    // Close modals and reset
                    closeBatchSummaryModal();
                    closeGenerateModal();
                    resetForm();
                    table.ajax.reload();
                    updateStats();
                } else {
                    let errorMessage = data.message || 'An error occurred during batch generation';
                    if (data.errors) {
                        errorMessage += ':\n' + Object.values(data.errors).flat().join('\n');
                    }

                    Swal.fire({
                        icon: 'error',
                        title: 'Batch Generation Failed',
                        text: errorMessage,
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                hideGlobalLoading();
                hideLoadingButton(confirmBtn, originalText);
                console.error('Batch generation error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while generating batch file numbers',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    function openEditModalFromAction(event, id) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        document.querySelectorAll('.action-dropdown-menu').forEach(d => {
            d.classList.add('hidden');
            d.classList.remove('show');
        });

        editRecord(id);
    }

    function editRecord(id) {
        // Show loading while fetching record details
        showGlobalLoading('Loading record details...');

        fetch(`{{ route("file-numbers.show", ":id") }}`.replace(':id', id))
            .then(response => response.json())
            .then(data => {
                hideGlobalLoading();
                
                // Populate fields
                document.getElementById('editId').value = data.id;
                document.getElementById('editMlsfNo').value = data.mlsfNo || data.kangisFileNo;
                document.getElementById('editFileName').value = data.FileName || '';
                document.getElementById('editPlotNo').value = data.plot_no || '';
                document.getElementById('editTpNo').value = data.tp_no || '';
                document.getElementById('editLocation').value = data.location || '';
                document.getElementById('editLga').value = data.lga || '';
                
                // Populate District if element exists
                if (document.getElementById('editDistrict')) {
                    document.getElementById('editDistrict').value = data.district || '';
                }

                const customerTypeSelect = document.getElementById('editCustomerType');
                if (customerTypeSelect) {
                    customerTypeSelect.value = 'Individual';
                }
                document.getElementById('editPurpose').value = data.purpose_id || '';
                
                // New Fields
                if (document.getElementById('editPhoneNo')) {
                    document.getElementById('editPhoneNo').value = data.phone_no || data.PhoneNo || '';
                }
                if (document.getElementById('editAddress')) {
                    document.getElementById('editAddress').value = data.address || '';
                }

                const modal = document.getElementById('editModal');
                if (modal) {
                     // TELEPORT: Move modal to body
                    if (modal.parentNode !== document.body) {
                        document.body.appendChild(modal);
                    }
                    modal.classList.remove('hidden');
                    
                    // Ultra-aggressive styling to ensure it's on top
                    modal.style.setProperty('display', 'block', 'important');
                    modal.style.setProperty('z-index', '1000005', 'important');
                    modal.style.setProperty('position', 'fixed', 'important');
                    modal.style.setProperty('top', '0', 'important');
                    modal.style.setProperty('left', '0', 'important');
                    modal.style.setProperty('width', '100%', 'important');
                    modal.style.setProperty('height', '100%', 'important');
                } else {
                    console.error('Edit Modal not found!');
                    Swal.fire('Error', 'Edit Modal element missing', 'error');
                }
            })
            .catch(error => {
                hideGlobalLoading();
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Failed to load record details',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    function closeEditModal() {
        const modal = document.getElementById('editModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    }

    function updateEditLocation() {
        const plotNo = document.getElementById('editPlotNo')?.value.trim() || '';
        const district = document.getElementById('editDistrict')?.value.trim() || '';
        const lga = document.getElementById('editLga')?.value.trim() || '';
        
        let parts = [];
        if (plotNo) {
            parts.push(`PLOT NUMBER ${plotNo}`);
        }
        
        let locationDetails = [];
        if (district) locationDetails.push(district);
        if (lga) locationDetails.push(lga);
        locationDetails.push('KANO');
        
        const plotPart = parts.join(' ');
        const addressPart = locationDetails.join(', ');
        
        const locationString = [plotPart, addressPart].filter(Boolean).join(' ');
        
        const locationInput = document.getElementById('editLocation');
        if (locationInput) {
            locationInput.value = locationString.toUpperCase();
        }
    }

    // Attach listeners for Edit Modal fields
    $(document).ready(function() {
        $(document).on('input change', '#editPlotNo, #editLga, #editDistrict', function() {
            updateEditLocation();
        });
    });

    function submitEditForm(event) {
        event.preventDefault();

        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading on button
        showLoadingButton(submitBtn, originalText);

        // Show global loading
        showGlobalLoading('Updating record...');

        const id = document.getElementById('editId').value;
        const formData = new FormData(document.getElementById('editForm'));

        fetch(`{{ route("file-numbers.update", ":id") }}`.replace(':id', id), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
            .then(response => response.json())
            .then(data => {
                hideGlobalLoading();
                if (submitBtn) {
                    hideLoadingButton(submitBtn, originalText);
                }

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        confirmButtonColor: '#10b981'
                    });

                    document.querySelectorAll('.action-dropdown-menu').forEach(d => {
                        d.classList.add('hidden');
                        d.classList.remove('show');
                    });

                    closeEditModal();
                    table.ajax.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'An error occurred',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                hideGlobalLoading();
                hideLoadingButton(submitBtn, originalText);
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while updating the record',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    function directAllocation(id) {
        // Show loading while fetching record details
        showGlobalLoading('Loading record details...');

        fetch(`{{ route("file-numbers.show", ":id") }}`.replace(':id', id))
            .then(response => response.json())
            .then(data => {
                hideGlobalLoading();
                
                // Populate fields
                document.getElementById('daEditId').value = data.id;
                document.getElementById('daPlotNo').value = data.plot_no || '';
                document.getElementById('daLocation').value = data.location || '';
                document.getElementById('daLga').value = data.lga || '';
                
                // Populate District if element exists
                if (document.getElementById('daDistrict')) {
                    document.getElementById('daDistrict').value = data.district || '';
                }

                // phone_no might be empty if the column doesn't exist, we will handle that
                if (document.getElementById('daPhoneNo')) {
                    document.getElementById('daPhoneNo').value = data.phone_no || data.PhoneNo || '';
                }
                
                const modal = document.getElementById('directAllocationModal');
                if (modal) {
                     // TELEPORT: Move modal to body
                    if (modal.parentNode !== document.body) {
                        document.body.appendChild(modal);
                    }
                    modal.classList.remove('hidden');
                    
                    // Ultra-aggressive styling to ensure it's on top
                    modal.style.setProperty('display', 'block', 'important');
                    modal.style.setProperty('z-index', '1000005', 'important');
                    modal.style.setProperty('position', 'fixed', 'important');
                    modal.style.setProperty('top', '0', 'important');
                    modal.style.setProperty('left', '0', 'important');
                    modal.style.setProperty('width', '100%', 'important');
                    modal.style.setProperty('height', '100%', 'important');
                } else {
                    console.error('Direct Allocation Modal not found!');
                    Swal.fire('Error', 'Direct Allocation Modal element missing', 'error');
                }
            })
            .catch(error => {
                hideGlobalLoading();
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Failed to load record details',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    function closeDirectAllocationModal() {
        const modal = document.getElementById('directAllocationModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    }

    function updateDirectAllocationLocation() {
        const plotNo = document.getElementById('daPlotNo')?.value.trim() || '';
        const district = document.getElementById('daDistrict')?.value.trim() || '';
        const lga = document.getElementById('daLga')?.value.trim() || '';
        
        let parts = [];
        if (plotNo) {
            parts.push(`PLOT NUMBER ${plotNo}`);
        }
        
        let locationDetails = [];
        if (district) locationDetails.push(district);
        if (lga) locationDetails.push(lga);
        locationDetails.push('KANO');
        
        const plotPart = parts.join(' ');
        const addressPart = locationDetails.join(', ');
        
        const locationString = [plotPart, addressPart].filter(Boolean).join(' ');
        
        const locationInput = document.getElementById('daLocation');
        if (locationInput) {
            locationInput.value = locationString.toUpperCase();
        }
    }

    // Attach listeners for Direct Allocation fields
    $(document).ready(function() {
        $(document).on('input change', '#daPlotNo, #daLga, #daDistrict', function() {
            updateDirectAllocationLocation();
        });
    });

    function submitDirectAllocationForm(event) {
        event.preventDefault();

        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading on button
        showLoadingButton(submitBtn, originalText);

        // Show global loading
        showGlobalLoading('Updating allocation...');

        const id = document.getElementById('daEditId').value;
        const formData = new FormData(document.getElementById('directAllocationForm'));

        // Since it's direct allocation, we might not have file_name, but the controller requires file_name.
        // We will fetch the existing file name and append it to formData.
        fetch(`{{ route("file-numbers.show", ":id") }}`.replace(':id', id))
            .then(response => response.json())
            .then(data => {
                if (data.FileName) {
                    formData.append('file_name', data.FileName);
                }
                
                return fetch(`{{ route("file-numbers.update", ":id") }}`.replace(':id', id), {
                    method: 'POST', // Form specifies @method('PUT') but we send as POST with _method=PUT from the blade directive
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });
            })
            .then(response => {
                return response.json();
            })
            .then(data => {
                hideGlobalLoading();
                if (submitBtn) {
                    hideLoadingButton(submitBtn, originalText);
                }

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message || 'Direct allocation updated successfully',
                        confirmButtonColor: '#10b981'
                    });
                    closeDirectAllocationModal();
                    if (typeof table !== 'undefined' && table.ajax) {
                        table.ajax.reload(null, false);
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'An error occurred',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                hideGlobalLoading();
                hideLoadingButton(submitBtn, originalText);
                console.error('Error:', error);
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Failed to update direct allocation',
                    confirmButtonColor: '#ef4444'
                });
            });
    }
 
    function deleteRecord(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280', 
            confirmButtonText: 'Yes, delete it!',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch(`{{ route("file-numbers.destroy", ":id") }}`.replace(':id', id), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    }
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(response.statusText);
                        }
                        return response.json();
                    })
                    .catch(error => {
                        Swal.showValidationMessage(
                            `Request failed: ${error}`
                        );
                    });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const data = result.value;
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: data.message,
                        confirmButtonColor: '#10b981'
                    });
                    table.ajax.reload();
                    updateStats();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'An error occurred',
                        confirmButtonColor: '#ef4444'
                    });
                }
            }
        });
    }

    function updateStats() {
        fetch('{{ route("file-numbers.stats") }}')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const totalEl = document.getElementById('stat-total');
                    const todayEl = document.getElementById('stat-today');
                    const monthEl = document.getElementById('stat-month');

                    if (totalEl) totalEl.textContent = new Intl.NumberFormat().format(data.data.total);
                    if (todayEl) todayEl.textContent = new Intl.NumberFormat().format(data.data.today);
                    if (monthEl) monthEl.textContent = new Intl.NumberFormat().format(data.data.month);
                }
            })
            .catch(error => {
                console.error('Error updating stats:', error);
            });
    }

    function testDatabaseConnection() {
        // Show loading for database test
        showGlobalLoading('Testing database connection...');

        fetch('{{ route("file-numbers.test-db") }}')
            .then(response => response.json())
            .then(data => {
                hideGlobalLoading();

                if (data.success) {
                    let message = `Database Connection Test Results:\n\n`;
                    message += `✅ Connection: ${data.connection}\n`;
                    message += `✅ Database: ${data.database_name}\n`;
                    message += `✅ Table Exists: ${data.table_exists ? 'Yes' : 'No'}\n`;
                    message += `✅ Record Count: ${data.record_count}\n`;
                    message += `✅ Server: ${data.server_info.substring(0, 50)}...\n\n`;

                    if (data.columns && data.columns.length > 0) {
                        message += `Table Columns:\n`;
                        data.columns.forEach(col => {
                            message += `- ${col.COLUMN_NAME} (${col.DATA_TYPE})\n`;
                        });
                    }

                    if (data.sample_records && data.sample_records.length > 0) {
                        message += `\nSample Records:\n`;
                        data.sample_records.forEach((record, index) => {
                            message += `${index + 1}. ${record.mlsfNo || record.kangisFileNo || 'No ID'}\n`;
                        });
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Database Test Successful',
                        text: message,
                        confirmButtonColor: '#10b981',
                        customClass: {
                            content: 'text-left'
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Database Test Failed',
                        text: data.error || 'Unknown error occurred',
                        confirmButtonColor: '#ef4444',
                        footer: '<small>Check the browser console for more details</small>'
                    });
                    console.error('Database test error:', data);
                }
            })
            .catch(error => {
                hideGlobalLoading();
                console.error('Database test error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Database Test Failed',
                    text: 'Failed to connect to test endpoint: ' + error.message,
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    function debugTableData() {
        // Show loading for debug data
        showGlobalLoading('Debugging table data...');

        fetch('{{ route("file-numbers.debug-data") }}')
            .then(response => response.json())
            .then(data => {
                hideGlobalLoading();

                if (data.success) {
                    console.log('Raw Data:', data.raw_data);
                    console.log('Formatted Data:', data.formatted_data);

                    let message = `Debug Data Results:\n\n`;
                    message += `Raw Records Found: ${data.raw_data.length}\n`;
                    message += `Formatted Records: ${data.formatted_data.length}\n\n`;

                    if (data.raw_data.length > 0) {
                        message += `Raw Data Sample:\n`;
                        data.raw_data.slice(0, 3).forEach((record, index) => {
                            message += `${index + 1}. ID: ${record.id}\n`;
                            message += `   kangisFileNo: "${record.kangisFileNo}"\n`;
                            message += `   NewKANGISFileNo: "${record.NewKANGISFileNo}"\n`;
                            message += `   FileName: "${record.FileName}"\n`;
                            message += `   mlsfNo: "${record.mlsfNo}"\n\n`;
                        });
                    }

                    if (data.formatted_data.length > 0) {
                        message += `Formatted Data Sample:\n`;
                        data.formatted_data.slice(0, 3).forEach((record, index) => {
                            message += `${index + 1}. ID: ${record.id}\n`;
                            message += `   kangisFileNo: "${record.kangisFileNo}"\n`;
                            message += `   NewKANGISFileNo: "${record.NewKANGISFileNo}"\n`;
                            message += `   FileName: "${record.FileName}"\n`;
                            message += `   mlsfNo: "${record.mlsfNo}"\n\n`;
                        });
                    }

                    Swal.fire({
                        icon: 'info',
                        title: 'Debug Data Results',
                        text: message,
                        confirmButtonColor: '#8b5cf6',
                        customClass: {
                            content: 'text-left'
                        },
                        width: '600px'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Debug Failed',
                        text: data.error || 'Unknown error occurred',
                        confirmButtonColor: '#ef4444'
                    });
                    console.error('Debug error:', data);
                }
            })
            .catch(error => {
                hideGlobalLoading();
                console.error('Debug error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Debug Failed',
                    text: 'Failed to connect to debug endpoint: ' + error.message,
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    // Simplified commissioning sheet function for better performance
    function openCommissioningSheet(recordId, fileNo, fileName, plotNo, tpNo, location, lga, trackingId, createdAt, createdBy) {
        // Pre-fill the commissioning sheet modal with available data
        document.getElementById('cs_file_number').value = fileNo || '';
        document.getElementById('cs_file_name').value = fileName || '';
        document.getElementById('cs_name_allottee').value = fileName || '';
        document.getElementById('cs_plot_number').value = (plotNo && plotNo !== 'N/A') ? plotNo : '';
        document.getElementById('cs_tp_number').value = (tpNo && tpNo !== 'N/A') ? tpNo : '';
        document.getElementById('cs_location').value = (location && location !== 'N/A') ? location : '';
        document.getElementById('cs_lga').value = (lga && lga !== 'N/A') ? lga : '';
        document.getElementById('cs_tracking_id').value = trackingId || '';

        // Handle Date and Time from createdAt if available
        if (createdAt && createdAt !== 'N/A' && createdAt !== '') {
            const date = new Date(createdAt);

            // Set Date (YYYY-MM-DD format for input type="date")
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            document.getElementById('cs_date_created').value = `${year}-${month}-${day}`;

            // Set Time (HH:MM format for input type="time")
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            document.getElementById('cs_time_created').value = `${hours}:${minutes}`;
        } else {
            // Fallback to server time instead of client time
            const serverInfo = getServerTime();
            document.getElementById('cs_date_created').value = serverInfo.date;
            document.getElementById('cs_time_created').value = serverInfo.time;
        }

        // Handle Commission             ed By
        if (createdBy && createdBy !== 'N/A' && createdBy !== '') {
            document.getElementById('cs_created_by').value = createdBy;
        } else {
            // Fallback to current user
            @if(Auth::check())
                document.getElementById('cs_created_by').value = '{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}';
            @endif
        }

        // Open the modal
        document.getElementById('commissioningSheetModal').classList.remove('hidden');
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.relative')) {
            closeAllDropdowns();
        }
    });

    // Add event listeners for form inputs
    // Add event listeners for form inputs with safety checks
    document.addEventListener('DOMContentLoaded', function () {
        const serialNoEl = document.getElementById('serialNo');
        if (serialNoEl) serialNoEl.addEventListener('input', updatePreview);

        const yearEl = document.getElementById('year');
        if (yearEl) yearEl.addEventListener('input', updatePreview);

        const landUseEl = document.getElementById('landUse');
        if (landUseEl) landUseEl.addEventListener('change', updatePreview);

        // Add event listeners for file option dropdown
        const fileOptionEl = document.getElementById('fileOption');
        if (fileOptionEl) fileOptionEl.addEventListener('change', updatePreview);

        // Add event listener for existing file number dropdown
        const existingFileNoEl = document.getElementById('existingFileNo');
        if (existingFileNoEl) existingFileNoEl.addEventListener('change', updatePreview);

        // Add event for middle prefix input if present
        const middlePrefixEl = document.getElementById('middlePrefix');
        if (middlePrefixEl) {
            middlePrefixEl.addEventListener('input', updatePreview);
        }
    });

    // Alpine.js component for file number generator
    function fileNumberGenerator() {
        console.log('fileNumberGenerator() function is being defined/called');
        return {
            // Data properties
            applicationType: 'change_of_purpose',
            allocatedByFilter: null,
            defaultAllocationType: '',
            _currentAllocationSourceType: 'default', // Track current allocation source type
            fileName: '',
            hasCustomFileName: true, // Toggle for custom file name input - default to checked
            allocationId: null,
            landUse: '',
            landUseFullText: '',
            // Dependent Data
            purposes: [],
            prefixes: [],
            purpose: '',
            prefix: '',
            customerType: '',
            // Default to normal file
            fileOption: 'normal',
            existingFileNo: '',
            middlePrefix: 'KN',
            year: new Date().getFullYear(),
            serialNo: '',
            plotNo: '',
            tpNo: '',
            location: '',
            lga: '',
            preview: '-',
            // New fields for reset logic
            phone_no: '',
            address: '',
            rep_phone_no: '',
            rep_address: '',
            isInherited: false, // Flag to track if fields were auto-populated from existing file
            sourceInstrumentCaptureId: '',
            sourcePropId: '',
            sourcePraId: '',
            sourceOpSerialNumber: '',
            sourceRegistrationNumber: '',
            sourceSerialNo: '',
            sourcePageNo: '',
            sourceVolumeNo: '',
            sourceOriginalOwner: '',
            requireOpSource: false,
            subSource: '',

            // Related File Properties (Recertification -RC)
            relatedFileNo: '',
            relatedFileTitle: '',
            relatedFileIndexingId: '',
            isRecertificationPrefix: false,
            
            // Subdivision / Merger Properties
            subdivisionAppId: '',
            mergerAppId: '',
            subdivisionFileNo: '',
            mergerFileNo: '',

            // Batch Mode Properties
            batchMode: false,
            batchQuantity: 2,
            locationEntries: [],
            currentEntryIndex: 0,
            serialRangePreview: '-',
            applyLocationToAll: false, // Toggle for batch location sync

            // Initialize the Select2 for Direct Allocation
            init() {
                try {
                    console.log('Alpine fileNumberGenerator init() started');
                    this.$watch('applicationType', (value) => {
                        console.log('applicationType watched change:', value);
                        if (value === 'new') {
                            if (this.allocatedByFilter === 'Allocation List') {
                                this.$nextTick(() => {
                                    this.initAllocationSelect2();
                                });
                            }
                        } else {
                            this.allocatedByFilter = '';
                        }
                    });

                    // Trigger on first load if default is 'new' and allocation list is selected
                    if (this.applicationType === 'new') {
                        if (this.allocatedByFilter === 'Allocation List') {
                            console.log('Initial applicationType is new, calling initAllocationSelect2');
                            this.$nextTick(() => {
                                this.initAllocationSelect2();
                            });
                        }
                    }
                } catch (e) {
                    console.error('Error in Alpine init():', e);
                }
            },

            initAllocationSelect2() {
                const self = this;
                if (!this.allocatedByFilter) {
                    console.log('initAllocationSelect2() skipping: no filter selected');
                    return;
                }
                try {
                    console.log('initAllocationSelect2() started, filter:', this.allocatedByFilter);
                    const $select = $('#allocationSelect');
                    if ($select.length === 0) return;

                    if ($select.hasClass('select2-hidden-accessible')) {
                        $select.select2('destroy');
                    }

                    const mergedAllocationFilters = ['Governor', 'Commissioner'];

                    // Pre-filter options before initializing Select2
                    $select.find('option').each(function() {
                        const $opt = $(this);
                        const optAllocatedBy = $opt.attr('data-allocated-by');

                        const isMergedAllocationList = self.allocatedByFilter === 'Allocation List';
                        const matchesMergedFilter = isMergedAllocationList && mergedAllocationFilters.includes(optAllocatedBy);
                        const matchesSpecificFilter = !isMergedAllocationList && optAllocatedBy === self.allocatedByFilter;

                        if (matchesMergedFilter || matchesSpecificFilter) {
                            $opt.prop('disabled', false).show();
                        } else if ($opt.val() === "") {
                            $opt.prop('disabled', false).show(); // Keep the placeholder
                        } else {
                            $opt.prop('disabled', true).hide();
                        }
                    });

                    $select.select2({
                        placeholder: this.allocatedByFilter === 'Allocation List'
                            ? 'Search and select from Allocation List'
                            : `Search and select from ${this.allocatedByFilter} list`,
                        allowClear: true,
                        dropdownParent: document.getElementById('generateModal'),
                        width: '100%',
                        templateResult: function(data) {
                            if (!data.id) return data.text;
                            if ($(data.element).prop('disabled')) return null;
                            return data.text;
                        }
                    });

                    $select.on('select2:open', () => {
                        setTimeout(() => {
                            const searchField = document.querySelector('.select2-search__field');
                            if (searchField) searchField.focus();
                        }, 150);
                    });

                    $select.on('select2:select', (e) => {
                        const data = e.params.data;
                        const $option = $(data.element);
                        
                        this.$nextTick(() => {
                            this.allocationId = data.id;
                            this.fileName = $option.attr('data-full-name') || '';
                            
                            const allotteeAddress = $option.attr('data-allottee-address');
                            if (allotteeAddress && allotteeAddress.trim() && allotteeAddress !== 'null') {
                                this.address = allotteeAddress.trim().toUpperCase();
                            } else {
                                let addressParts = [];
                                const plot = $option.attr('data-plot');
                                const district = $option.attr('data-district');
                                const lga = $option.attr('data-lga');
                                const state = $option.attr('data-state');

                                if (plot && plot.trim() && plot !== 'null') addressParts.push(`Plot ${plot}`);
                                if (district && district.trim() && district !== 'null') addressParts.push(district);
                                if (lga && lga.trim() && lga !== 'null') addressParts.push(lga);
                                if (state && state.trim() && state !== 'null') addressParts.push(state);
                                
                                this.address = addressParts.join(', ').toUpperCase();
                            }
                            this.isInherited = false;
                            this.updatePreview();
                        });
                    });

                    $select.on('select2:unselect', () => {
                        this.allocationId = null;
                        this.fileName = '';
                        this.address = '';
                        this.updatePreview();
                    });
                } catch (e) {
                    console.error('Error in initAllocationSelect2():', e);
                }
            },

            handleAllocationFilterChange() {
                console.log('Allocation Filter Changed to:', this.allocatedByFilter);
                this.allocationId = null;
                this.fileName = '';
                this.address = '';

                // OP mode (default allocation source) does not allow batch generation.
                if (this.applicationType === 'new' && this.allocatedByFilter === '') {
                    this.batchMode = false;
                    this.locationEntries = [];
                    this.currentEntryIndex = 0;
                    this.serialRangePreview = '-';
                } else {
                    // Leaving OP mode – clear stale flags.
                    this.requireOpSource = false;
                    window.ossOpContext = false;
                }
                
                if (this.allocatedByFilter !== '') {
                    this.$nextTick(() => {
                        this.initAllocationSelect2();
                    });
                }
                
                this.$nextTick(() => {
                    this.updatePreview();
                });
            },

            // Computed properties
            get showYearSection() {
                return this.fileOption !== 'old_mls' && this.fileOption !== 'sltr';
            },

            get isYearEditable() {
                return isOverrideMode;
            },

            get isSerialEditable() {
                return this.fileOption === 'miscellaneous' || this.fileOption === 'sltr' || this.fileOption === 'old_mls' || isOverrideMode;
            },

            get isSerialReadonly() {
                // Readonly for normal (auto-generated), temporary, extension, and SIT types
                return (this.fileOption === 'normal' || this.fileOption === 'temporary' || this.fileOption === 'extension' || this.fileOption === 'sit') && !isOverrideMode;
            },

            get isSerialDisabled() {
                // Disable for extension and temporary types (not needed)
                return (this.fileOption === 'extension' || this.fileOption === 'temporary') && !isOverrideMode;
            },

            get serialFieldType() {
                return this.fileOption === 'normal' && !isOverrideMode ? 'number' : 'text';
            },

            get yearFieldClass() {
                return this.isYearEditable ? 'w-full px-3 py-2 border border-gray-300 rounded-md text-gray-900' : 'w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-600';
            },

            get serialFieldClass() {
                if (this.isSerialDisabled) {
                    return 'w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-500 cursor-not-allowed';
                } else if (this.isSerialReadonly) {
                    return 'w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-gray-700';
                } else {
                    return 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900';
                }
            },

            get serialPlaceholder() {
                if (this.fileOption === 'extension') {
                    return 'Not required for extensions';
                } else if (this.fileOption === 'temporary') {
                    return 'Not required for temporary files';
                } else if (this.fileOption === 'miscellaneous') {
                    return 'Enter custom serial (e.g., 001, ABC123)';
                } else if (this.fileOption === 'sltr') {
                    return 'Enter SLTR serial (e.g., 001, 2024-001)';
                } else if (this.fileOption === 'sit') {
                    return 'Auto-generated';
                } else if (this.fileOption === 'old_mls') {
                    return 'Enter Old MLS number (e.g., 5467, 34874857488758)';
                } else {
                    return 'Auto-generated';
                }
            },

            get serialDescription() {
                if (this.fileOption === 'extension') {
                    return 'Not required for extensions';
                } else if (this.fileOption === 'temporary') {
                    return 'Select existing file for temporary version';
                } else if (this.fileOption === 'miscellaneous' || this.fileOption === 'sltr' || this.fileOption === 'old_mls') {
                    return 'Manual entry ';
                } else {
                    return 'Auto-generated';
                }
            },

            get serialDescriptionClass() {
                if (this.fileOption === 'miscellaneous' || this.fileOption === 'sltr' || this.fileOption === 'old_mls' || this.fileOption === 'temporary') {
                    return 'text-blue-600 font-medium';
                } else {
                    return 'text-gray-500';
                }
            },

            get yearDescription() {
                return this.isYearEditable ? 'Editable' : 'Auto-filled';
            },

            get previewClass() {
                return this.preview !== '-' ? 'text-green-600' : 'text-gray-400';
            },

            get isOpFormHidden() {
                // Hide the form below allocation-source checkboxes when the
                // OP allocation source is selected but no OP capture has
                // been completed yet.  This keeps the user focused on the
                // OP capture step before filling the rest of the form.

                // Change of Name flow: always require OP capture first,
                // regardless of which allocation source radio is selected.
                if (this.subSource === 'OP Change of Name') {
                    if (this.sourceInstrumentCaptureId) return false;
                    if (this.sourcePropId) return false;
                    if (window.pendingExistingOpPraContext && window.pendingExistingOpPraContext.prop_id) return false;
                    return true;
                }

                // Determine if OP mode is active — either via requireOpSource
                // (set by OSS page) or _currentAllocationSourceType (MLS page).
                // In both cases the OP allocation radio must actually be selected
                // (allocatedByFilter === '') — not just flagged.
                var isOpMode = (this.requireOpSource && this.allocatedByFilter === '') ||
                    (this._currentAllocationSourceType === 'op' && this.allocatedByFilter === '');

                if (!isOpMode) return false;

                // OP capture already completed — show the form.
                if (this.sourceInstrumentCaptureId) return false;
                if (this.sourcePropId) return false;
                if (window.pendingExistingOpPraContext && window.pendingExistingOpPraContext.prop_id) return false;
                return true;
            },

            get isBatchModeLocked() {
                // Batch mode is only locked when current selection is OP mode
                // (Default allocation source under Direct Allocation type).
                // Do NOT check requireOpSource / ossOpContext here – those
                // flags persist globally and would wrongly lock batch mode
                // after the user switches to Conversion or Allocation List.
                return this.applicationType === 'new' && this.allocatedByFilter === '';
            },

            // Batch Mode Computed Properties
            // Initial Data
            landUses: @json($landUses), // Available for lookups if needed
            allAllPrefixes: @json($allPrefixes ?? []), // The full raw list from controller

            // Computed
            get filledEntriesCount() {
                if (!this.batchMode) return 0;
                return this.locationEntries.filter(entry =>
                    entry.plotNo || entry.tpNo || entry.location || entry.lga
                ).length;
            },

            get filteredPrefixes() {
                // Filter the FULL list based on application type
                // Note: We use 'allAllPrefixes' which is the raw list from controller
                if (!this.allAllPrefixes) return [];

                if (this.applicationType === 'conversion') {
                    return this.allAllPrefixes.filter(p => p.prefix.includes('CON-'));
                } else {
                    return this.allAllPrefixes.filter(p => !p.prefix.includes('CON-'));
                }
            },

            // Methods
            handlePrefixChange(event) {
                // Find the selected prefix object from our full list
                // We can't rely just on the filtered list index, so find by string
                const selectedPrefixStr = this.prefix; 
                if (!selectedPrefixStr) {
                    this.landUse = '';
                    this.purpose = '';
                    this.purposes = [];
                    return;
                }

                const prefixObj = this.allAllPrefixes.find(p => p.prefix === selectedPrefixStr);
                
                if (prefixObj && prefixObj.land_use_id) {
                     // 1. Find the Land Use Code based on ID (to set the model)
                     // We need to iterate our select options or look up in landUses array
                     // The landUses array from PHP has 'id', 'landuse' (name).
                     // But our model 'this.landUse' expects the CODE (e.g., 'RES', 'COM').
                     // We need a way to map ID -> CODE. 
                     // Let's use the HTML select options logic to be consistent or just map it here.
                     
                     const lu = this.landUses.find(l => l.id == prefixObj.land_use_id);
                     if (lu) {
                         let code = '';
                         const name = lu.landuse.toUpperCase();
                         
                        if (name.includes('RESIDENTIAL')) code = 'RES';
                        else if (name.includes('COMMERCIAL')) code = 'COM';
                        else if (name.includes('INDUSTRIAL')) code = 'IND';
                        else if (name.includes('AGRICULTURAL')) code = 'AG';
                        else code = name.substring(0, 3);
                        
                        this.landUse = code;
                        
                        // Auto-set Customer Type based on Land Use
                        if (code === 'COM' || code === 'IND') {
                            this.customerType = 'Corporate';
                        } else {
                            this.customerType = 'Individual';
                        }
                        
                        // 2. Fetch Purposes for this Land Use ID
                        this.fetchDependentData(prefixObj.land_use_id);
                     }
                }
                
                this.updatePreview();

                // Detect recertification prefix (-RC)
                this.isRecertificationPrefix = selectedPrefixStr.includes('-RC');
                if (!this.isRecertificationPrefix) {
                    this.clearRelatedFile();
                }
            },

            // Open GlobalFileNoModal to select related file (recertification)
            openRelatedFileModal() {
                if (typeof GlobalFileNoModal === 'undefined' || typeof GlobalFileNoModal.open !== 'function') {
                    alert('File number selector is not available. Please refresh the page.');
                    return;
                }
                const self = this;
                GlobalFileNoModal.open({
                    callback: function(data) {
                        if (!data || !data.fileNumber) return;
                        self.relatedFileNo = data.fileNumber;
                        self.relatedFileTitle = (data.record && (data.record.file_name || data.record.FileName)) || '';
                        self.relatedFileIndexingId = (data.record && data.record.id) || '';
                    }
                });
            },

            clearRelatedFile() {
                this.relatedFileNo = '';
                this.relatedFileTitle = '';
                this.relatedFileIndexingId = '';
            },

            // Modified to only fetch Purposes (and potentially Prefixes but we have them already)
            // renaming conceptually to fetchPurposes might be better but keeping name for minimal diff
            fetchDependentData(landUseId) {
                if (!landUseId) {
                    this.purposes = [];
                    // this.prefixes = []; // Don't clear prefixes as they drive the selection
                    this.purpose = '';
                    // this.prefix = ''; 
                    return;
                }

                showGlobalLoading('Fetching purposes...');

                fetch(`/mls-fileno/get-dependent-data?land_use_id=${landUseId}`)
                    .then(response => response.json())
                    .then(data => {
                        this.purposes = data.purposes || [];
                        // this.prefixes = data.prefixes || []; // We use the global list now
                        
                        // Reset purpose
                        this.purpose = '';
                    })
                    .catch(error => {
                        console.error('Error fetching dependent data:', error);
                        this.purposes = [];
                        // this.prefixes = []; // Don't touch prefixes
                    })
                    .finally(() => {
                         hideGlobalLoading();
                    });
            },

            fetchAllPurposes() {
                fetch('/mls-fileno/get-dependent-data?land_use_id=all')
                    .then(response => response.json())
                    .then(data => {
                        this.purposes = data.purposes || [];
                        this.purpose = '';
                    })
                    .catch(error => {
                        console.error('Error fetching all purposes:', error);
                        this.purposes = [];
                    });
            },

            init() {
                // Serial will be updated when land use is selected
                this.serialNo = '';
                this.updatePreview();
            },

            updateApplicationType() {
                this.landUse = '';
                this.prefix = ''; // Reset prefix when application type changes

                if (this.applicationType === 'new') {
                    this.allocatedByFilter = '';
                } else {
                    this.allocatedByFilter = '';
                    this.defaultAllocationType = '';
                    // Leaving OP mode – clear OP-specific flags so they
                    // don't interfere with Conversion / Allocation List.
                    this.requireOpSource = false;
                    window.ossOpContext = false;
                }

                // Reset file option to normal if SIT is selected but conversion type is chosen
                if (this.fileOption === 'sit' && this.applicationType === 'conversion') {
                    this.fileOption = 'normal';
                }

                // Enforce OP rule after app type change.
                if (this.applicationType === 'new' && this.allocatedByFilter === '') {
                    this.batchMode = false;
                    this.locationEntries = [];
                    this.currentEntryIndex = 0;
                    this.serialRangePreview = '-';
                }
                this.updatePreview();
            },

            openSubdivisionFileModal() {
                if (typeof GlobalFileNoModal === 'undefined' || typeof GlobalFileNoModal.open !== 'function') {
                    alert('File number selector is not available. Please refresh the page.');
                    return;
                }
                const self = this;
                GlobalFileNoModal.open({
                    callback: function(data) {
                        if (!data || !data.fileNumber) return;
                        
                        showGlobalLoading('Verifying subdivision...');
                        fetch(`/plot-subdivision/find-by-file/${encodeURIComponent(data.fileNumber)}`)
                            .then(response => response.json())
                            .then(res => {
                                hideGlobalLoading();
                                if (res.success) {
                                    self.subdivisionFileNo = data.fileNumber;
                                    self.subdivisionAppId = res.data.id;
                                    self.batchMode = true;
                                    self.batchQuantity = res.data.num_plots;
                                    
                                    // Trigger Alpine reactivity for batchQuantity and batchMode
                                    self.$nextTick(() => {
                                        if (typeof self.updatePreview === 'function') {
                                            self.updatePreview();
                                        }
                                    });
                                    
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Subdivision Found',
                                        text: `This file has an approved subdivision for ${res.data.num_plots} plots. Batch mode enabled.`,
                                        timer: 3000,
                                        showConfirmButton: false
                                    });
                                } else {
                                    Swal.fire('Not Found', res.message, 'warning');
                                }
                            })
                            .catch(err => {
                                hideGlobalLoading();
                                console.error(err);
                                Swal.fire('Error', 'Failed to lookup subdivision application.', 'error');
                            });
                    }
                });
            },

            openMergerFileModal() {
                if (typeof GlobalFileNoModal === 'undefined' || typeof GlobalFileNoModal.open !== 'function') {
                    alert('File number selector is not available. Please refresh the page.');
                    return;
                }
                const self = this;
                GlobalFileNoModal.open({
                    callback: function(data) {
                        if (!data || !data.fileNumber) return;
                        
                        showGlobalLoading('Verifying merger...');
                        fetch(`/plot-merger/find-by-file/${encodeURIComponent(data.fileNumber)}`)
                            .then(response => response.json())
                            .then(res => {
                                hideGlobalLoading();
                                if (res.success) {
                                    self.mergerFileNo = data.fileNumber;
                                    self.mergerAppId = res.data.id;
                                    
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Merger Found',
                                        text: 'This file has an approved merger application.',
                                        timer: 2000,
                                        showConfirmButton: false
                                    });
                                } else {
                                    Swal.fire('Not Found', res.message, 'warning');
                                }
                            })
                            .catch(err => {
                                hideGlobalLoading();
                                console.error(err);
                                Swal.fire('Error', 'Failed to lookup merger application.', 'error');
                            });
                    }
                });
            },

            updateFileOption() {
                // Clear serial number when changing file option to special types
                if (this.fileOption === 'miscellaneous' || this.fileOption === 'sltr' || this.fileOption === 'old_mls') {
                    this.serialNo = '';
                    this.isInherited = false;
                } else if (this.fileOption === 'sit') {
                    // SIT: auto-serial, no land use, customer type is always Government
                    this.landUse = 'SIT';
                    this.customerType = 'Government';
                    this.isInherited = false;
                    // Load the auto-generated serial for SIT
                    this.serialNo = getNextSerialForLandUse('SIT');
                    // Load all purposes (not filtered by land use)
                    this.fetchAllPurposes();
                } else if (this.fileOption === 'extension' || this.fileOption === 'temporary') {
                    this.serialNo = '';
                    this.existingFileNo = ''; // Clear existing file selection
                    this.isInherited = false;
                    // Reset fields that might have been inherited
                    this.fileName = '';
                    this.plotNo = '';
                    this.tpNo = '';
                    this.location = '';
                    this.lga = '';
                    this.phone_no = '';
                    this.address = '';
                    this.rep_phone_no = '';
                    this.rep_address = '';
                } else if (this.fileOption === 'normal') {
                    this.isInherited = false;
                    // Reset SIT-specific overrides if switching from SIT
                    if (this.landUse === 'SIT') {
                        this.landUse = '';
                        this.customerType = '';
                        this.purposes = [];
                        this.purpose = '';
                        this.prefix = '';
                    }
                    // Reset to auto-generated for normal files based on land use
                    if (this.landUse || this.prefix) {
                        this.serialNo = getNextSerialForLandUse(this.prefix || this.landUse);
                    }
                } else if (this.fileOption === 'subdivision' || this.fileOption === 'merger') {
                    this.serialNo = '';
                    this.subdivisionAppId = '';
                    this.mergerAppId = '';
                    this.subdivisionFileNo = '';
                    this.mergerFileNo = '';
                    this.batchMode = false;
                    this.isInherited = false;
                    
                    // Reset fields
                    this.fileName = '';
                    this.plotNo = '';
                    this.tpNo = '';
                    this.location = '';
                    this.lga = '';
                }

                this.updatePreview();
            },

            updatePreview() {
                // Auto-update serial number when land use changes for normal files
                if (this.fileOption === 'normal' && (this.landUse || this.prefix) && !isOverrideMode) {
                    const newSerial = getNextSerialForLandUse(this.prefix || this.landUse);
                    if (this.serialNo !== newSerial) {
                        this.serialNo = newSerial;
                    }
                }

                // Auto-update serial for SIT files using MlsSerialControl
                if (this.fileOption === 'sit' && !isOverrideMode) {
                    const sitSerial = getNextSerialForLandUse('SIT');
                    if (this.serialNo !== sitSerial) {
                        this.serialNo = sitSerial;
                    }
                }

                let previewText = '-';

                // Generate base file number for extension/temporary fallback
                let baseFileNumber = '';
                if (this.serialNo && this.year) {
                    const code = this.prefix || this.landUse;
                    if (code) {
                        baseFileNumber = `${code}-${this.year}-${this.serialNo}`;
                    }
                }

                if (this.fileOption === 'extension') {
                    previewText = (this.existingFileNo || baseFileNumber).trim().replace(/-$/, '') + ' AND EXTENSION';
                } else if (this.fileOption === 'temporary') {
                    previewText = (this.existingFileNo || baseFileNumber) + '(T)';
                } else if (this.fileOption === 'miscellaneous' && this.middlePrefix && this.serialNo && this.year) {
                    previewText = `MISC-${this.middlePrefix}-${this.year}-${this.serialNo}`;
                } else if (this.fileOption === 'old_mls' && this.serialNo) {
                    previewText = `KN ${this.serialNo}`;
                } else if (this.fileOption === 'sltr' && this.serialNo) {
                    previewText = `SLTR-${this.serialNo}`;
                } else if (this.fileOption === 'sit' && this.serialNo) {
                    previewText = `SIT-${this.year}-${this.serialNo}`;
                } else if (this.fileOption === 'normal' && this.serialNo && this.year) {
                    const code = this.prefix || this.landUse;
                    if (code) {
                         previewText = `${code}-${this.year}-${this.serialNo}`;
                    }
                }

                this.preview = previewText;

                // Update Land Use Full Text to show only exact land use (e.g., Commercial instead of COM - Commercial)
                if (this.fileOption === 'sit') {
                    // SIT files don't have land use - show only purpose if selected
                    this.landUseFullText = '';
                } else if (this.landUse) {
                    const lu = this.landUse.toUpperCase();
                    if (lu.includes('COM')) {
                        this.landUseFullText = 'Commercial';
                    } else if (lu.includes('IND')) {
                        this.landUseFullText = 'Industrial';
                    } else if (lu.includes('AG')) {
                        this.landUseFullText = 'Agricultural';
                    } else if (lu.includes('RES')) {
                        this.landUseFullText = 'Residential';
                    } else {
                        // Fallback logic for any other prefixes
                        const select = document.getElementById('landUse');
                        if (select && select.selectedIndex >= 0) {
                            const text = select.options[select.selectedIndex].text;
                            this.landUseFullText = text.includes(' - ') ? text.split(' - ')[1] : text;
                        } else {
                            this.landUseFullText = this.landUse;
                        }
                    }
                } else {
                    this.landUseFullText = '-';
                }

                // Append Purpose if available
                if (this.purpose) {
                     // Find purpose name from array
                     const selectedPurpose = this.purposes.find(p => p.id == this.purpose);
                     if (selectedPurpose) {
                         this.landUseFullText += ` (${selectedPurpose.name})`;
                     }
                }

                // Trigger grouping lookup to enable/disable Generate button
                if (typeof deriveGroupingLookupCandidate === 'function' && typeof queueGroupingLookup === 'function') {
                    const lookupCandidate = deriveGroupingLookupCandidate(this.fileOption, previewText, this.existingFileNo);
                    if (lookupCandidate) {
                        queueGroupingLookup(lookupCandidate);
                    } else {
                        // If no candidate, we might need to reset. 
                        // But rely on global functions to handle reset if needed.
                        // Actually, global updatePreview calls resetTrackingIdDisplay('--') if no candidate.
                        if (typeof resetTrackingIdDisplay === 'function') {
                             resetTrackingIdDisplay('--');
                        }
                    }
                }

                // If in batch mode, call updateBatchPreview to override this.preview with range
                if (this.batchMode) {
                    this.updateBatchPreview();
                }
            },

            // Method to refresh serial number from external call
            refreshSerialNumber() {
                if ((this.fileOption === 'normal' || this.fileOption === '') && !isOverrideMode) {
                    this.serialNo = nextSerialNo;
                    this.updatePreview();
                }
            },

            // Batch Mode Methods
            toggleBatchMode() {
                if (this.isBatchModeLocked) {
                    this.batchMode = false;
                    this.locationEntries = [];
                    this.currentEntryIndex = 0;
                    this.serialRangePreview = '-';
                    return;
                }

                if (this.batchMode) {
                    // Initialize location entries array
                    this.initializeLocationEntries();
                    this.updateBatchPreview();
                } else {
                    // Clear batch data
                    this.locationEntries = [];
                    this.currentEntryIndex = 0;
                    this.serialRangePreview = '-';
                }
            },

            initializeLocationEntries() {
                this.locationEntries = [];
                for (let i = 0; i < this.batchQuantity; i++) {
                    this.locationEntries.push({
                        plotNo: '',
                        tpNo: '',
                        location: '',
                        lga: '',
                        tracking_id: null
                    });
                }
                this.currentEntryIndex = 0;
            },

            updateLocationEntry(field, value) {
                if (this.locationEntries[this.currentEntryIndex]) {
                    this.locationEntries[this.currentEntryIndex][field] = value;
                    
                    if (this.applyLocationToAll) {
                        for (let i = 0; i < this.batchQuantity; i++) {
                            if (this.locationEntries[i]) {
                                this.locationEntries[i][field] = value;
                            }
                        }
                    }
                }
            },

            previousEntry() {
                if (this.currentEntryIndex > 0) {
                    this.currentEntryIndex--;
                }
            },

            nextEntry() {
                if (this.currentEntryIndex < this.batchQuantity - 1) {
                    this.currentEntryIndex++;
                    // Initialize entry if it doesn't exist
                    if (!this.locationEntries[this.currentEntryIndex]) {
                        this.locationEntries[this.currentEntryIndex] = {
                            plotNo: '',
                            tpNo: '',
                            location: '',
                            lga: '',
                            tracking_id: null
                        };
                    }
                }
            },

            isEntryFilled(index) {
                if (!this.locationEntries[index]) return false;
                const entry = this.locationEntries[index];
                return entry.plotNo || entry.tpNo || entry.location || entry.lga;
            },

            updateBatchPreview() {
                if (!this.batchMode) return;

                // Adjust location entries array size when quantity changes
                // This must happen before any early returns to keep array in sync with batchQuantity
                const currentLength = this.locationEntries.length;
                if (this.batchQuantity > currentLength) {
                    // Add new entries
                    for (let i = currentLength; i < this.batchQuantity; i++) {
                        this.locationEntries.push({
                            plotNo: '',
                            tpNo: '',
                            location: '',
                            lga: '',
                            tracking_id: null
                        });
                    }
                } else if (this.batchQuantity < currentLength) {
                    // Remove extra entries
                    this.locationEntries = this.locationEntries.slice(0, this.batchQuantity);
                    // Adjust current index if needed
                    if (this.currentEntryIndex >= this.batchQuantity) {
                        this.currentEntryIndex = this.batchQuantity - 1;
                    }
                }

                if (!this.serialNo || !(this.prefix || this.landUse) || !this.year) {
                    this.serialRangePreview = '-';
                    return;
                }

                // Calculate serial range (no zero-padding)
                const startSerial = parseInt(this.serialNo);
                const endSerial = startSerial + parseInt(this.batchQuantity) - 1;
                this.serialRangePreview = `${startSerial} to ${endSerial}`;

                // Update preview to show range
                const code = this.prefix || this.landUse;
                if (this.fileOption === 'normal' && code && this.year) {
                    this.preview = `${code}-${this.year}-${startSerial} to ${code}-${this.year}-${endSerial}`;
                }

                // Perform batch grouping lookup
                this.queueBatchGroupingLookup();
            },

            queueBatchGroupingLookup() {
                if (this.batchLookupHandle) {
                    clearTimeout(this.batchLookupHandle);
                }

                this.batchLookupHandle = setTimeout(() => {
                    this.performBatchGroupingLookup();
                }, 500);
            },

            async performBatchGroupingLookup() {
                if (!this.batchMode || !this.landUse || !this.year || !this.serialNo) return;

                const startSerial = parseInt(this.serialNo);
                const code = this.prefix || this.landUse;
                const fileNumbers = [];
                for (let i = 0; i < this.batchQuantity; i++) {
                    fileNumbers.push(`${code}-${this.year}-${startSerial + i}`);
                }

                try {
                    const response = await fetch('/api/grouping/bulk-lookup', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ file_numbers: fileNumbers })
                    });

                    const payload = await response.json();
                    if (payload.success && payload.data) {
                        // Create a map for fast lookup
                        const trackingMap = {};
                        payload.data.forEach(item => {
                            if (item.found && item.data && item.data.tracking_id) {
                                trackingMap[item.file_number] = item.data.tracking_id;
                            }
                        });

                        // Update location entries with found tracking IDs
                        this.locationEntries.forEach((entry, index) => {
                            const fileNo = fileNumbers[index];
                            if (trackingMap[fileNo]) {
                                entry.tracking_id = trackingMap[fileNo];
                            }
                        });

                        // Update summary display if only one tracking ID found for the first one
                        const firstTrackingId = trackingMap[fileNumbers[0]];
                        if (firstTrackingId) {
                            const displayEl = document.getElementById('trackingIdDisplay');
                            if (displayEl) displayEl.textContent = firstTrackingId;

                            const summaryTrackingEl = document.getElementById('summaryTrackingId');
                            if (summaryTrackingEl) summaryTrackingEl.textContent = firstTrackingId;

                            const inputEl = document.getElementById('trackingIdInput');
                            if (inputEl) inputEl.value = firstTrackingId;
                        }
                    }
                } catch (error) {
                    console.error('Batch grouping lookup failed:', error);
                }
            },

            // Apply current location details to all files in batch
            applyLocationToBatch() {
                if (!this.batchMode || this.batchQuantity < 2) {
                    return;
                }

                // Get current location details from the current entry
                const currentEntry = this.locationEntries[this.currentEntryIndex];
                if (!currentEntry) {
                    console.error('Apply to batch failed: current entry at index ' + this.currentEntryIndex + ' is undefined');
                    return;
                }

                const locationData = {
                    plotNo: currentEntry.plotNo || '',
                    tpNo: currentEntry.tpNo || '',
                    location: currentEntry.location || '',
                    lga: currentEntry.lga || ''
                };

                // Apply to all entries in the batch
                // Use the length of locationEntries instead of batchQuantity for safety
                const count = Math.min(this.batchQuantity, this.locationEntries.length);
                for (let i = 0; i < count; i++) {
                    if (this.locationEntries[i]) {
                        this.locationEntries[i] = {
                            ...this.locationEntries[i],
                            ...locationData
                        };
                    } else {
                        console.warn('Location entry at index ' + i + ' is missing during batch apply');
                    }
                }

                // Show success notification
                Swal.fire({
                    icon: 'success',
                    title: 'Location Applied',
                    text: `Location details applied to all ${count} files in the batch`,
                    timer: 2000,
                    showConfirmButton: false
                });

                // Refresh icons after the DOM updates
                this.$nextTick(() => {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
            }
        }
    }

    // Commissioning Sheet Functions
    function openCommissioningSheetModal() {
        document.getElementById('commissioningSheetModal').classList.remove('hidden');
    }

    // Global function to be called from the modal or button
    window.openTemporaryFileSelector = function() {
        console.log('[FileNumberGenerator] openTemporaryFileSelector called');
        if (typeof GlobalFileNoModal !== 'undefined') {
            GlobalFileNoModal.open({
                callback: function (fileData) {
                    console.log('[FileNumberGenerator] Temporary file selector callback:', fileData);
                    if (fileData && fileData.fileNumber) {
                        // Clean the file number by removing trailing hyphens
                        let cleanedFileNumber = fileData.fileNumber.trim().replace(/-+$/, '');
                        console.log('[FileNumberGenerator] File number received:', fileData.fileNumber, '-> cleaned:', cleanedFileNumber);
                        // Find the Alpine controller on the generate modal
                        const modalEl = document.querySelector('[x-data^="fileNumberGenerator"]');
                        if (modalEl) {
                            // Try Alpine v3 data access
                            try {
                                if (window.Alpine) {
                                    const data = window.Alpine.$data(modalEl);
                                    data.existingFileNo = cleanedFileNumber;
                                    data.updatePreview();
                                    console.log('[FileNumberGenerator] Alpine data updated, existing file no:', data.existingFileNo);
                                } else {
                                    // Fallback if Alpine not global
                                    const displayInput = document.getElementById('displayTemporaryFileNo');
                                    if (displayInput) {
                                        displayInput.value = cleanedFileNumber;
                                        displayInput.dispatchEvent(new Event('input', { bubbles: true }));
                                        displayInput.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                }
                            } catch (e) {
                                console.error('Error updating Alpine data:', e);
                            }
                        }
                        
                        // Important: Queue grouping lookup to fetch tracking ID for this file
                        console.log('[FileNumberGenerator] Calling queueGroupingLookup with:', cleanedFileNumber);
                        queueGroupingLookup(cleanedFileNumber);

                        // Auto-fill applicant details from the selected file
                        fetchTrackingIdForFile(cleanedFileNumber).then(() => {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    toast: true,
                                    position: 'top-end',
                                    icon: 'info',
                                    title: 'Details Auto-Filled',
                                    text: 'Details auto-filled based on selected file number. Please review before submitting.',
                                    showConfirmButton: false,
                                    timer: 5000,
                                    timerProgressBar: true
                                });
                            }
                        });
                    } else {
                        console.warn('[FileNumberGenerator] fileData.fileNumber is empty or undefined:', fileData);
                    }
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Global file number selector is not loaded.'
            });
        }
    };

    // Global function for Extension File Selector
    window.openExtensionFileSelector = function() {
        console.log('[FileNumberGenerator] openExtensionFileSelector called');
        if (typeof GlobalFileNoModal !== 'undefined') {
            GlobalFileNoModal.open({
                callback: function (fileData) {
                    console.log('[FileNumberGenerator] Extension file selector callback:', fileData);
                    if (fileData && fileData.fileNumber) {
                        // Clean the file number by removing trailing hyphens
                        let cleanedFileNumber = fileData.fileNumber.trim().replace(/-+$/, '');
                        console.log('[FileNumberGenerator] File number received:', fileData.fileNumber, '-> cleaned:', cleanedFileNumber);
                        // Find the Alpine controller on the generate modal
                        const modalEl = document.querySelector('[x-data^="fileNumberGenerator"]');
                        if (modalEl) {
                            // Try Alpine v3 data access
                            try {
                                if (window.Alpine) {
                                    const data = window.Alpine.$data(modalEl);
                                    data.existingFileNo = cleanedFileNumber;
                                    data.updatePreview();
                                    console.log('[FileNumberGenerator] Alpine data updated, existing file no:', data.existingFileNo);
                                } else {
                                    // Fallback if Alpine not global
                                    const displayInput = document.getElementById('displayExtensionFileNo');
                                    if (displayInput) {
                                        displayInput.value = cleanedFileNumber;
                                        displayInput.dispatchEvent(new Event('input', { bubbles: true }));
                                        displayInput.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                }
                            } catch (e) {
                                console.error('Error updating Alpine data:', e);
                            }
                        }
                        
                        // Important: Queue grouping lookup to fetch tracking ID for this file
                        console.log('[FileNumberGenerator] Calling queueGroupingLookup with:', cleanedFileNumber);
                        queueGroupingLookup(cleanedFileNumber);

                        // Auto-fill applicant details from the selected file
                        fetchTrackingIdForFile(cleanedFileNumber).then(() => {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    toast: true,
                                    position: 'top-end',
                                    icon: 'info',
                                    title: 'Details Auto-Filled',
                                    text: 'Details auto-filled based on selected file number. Please review before submitting.',
                                    showConfirmButton: false,
                                    timer: 5000,
                                    timerProgressBar: true
                                });
                            }
                        });
                    } else {
                        console.warn('[FileNumberGenerator] fileData.fileNumber is empty or undefined:', fileData);
                    }
                }
            });
        } else {
             Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'File selector module not loaded. Please refresh the page.',
                confirmButtonColor: '#ef4444'
            });
        }
    };

    // Support function to fetch tracking ID and auto-populate fields
    async function fetchTrackingIdForFile(fileNumber) {
        if (!fileNumber) return;
        
        try {
            // Use the centralized lookup endpoint (escaped for JS/Blade context)
            const baseUrl = "{{ route('api.file-numbers.lookup') }}";
            const response = await fetch(`${baseUrl}?file_number=${encodeURIComponent(fileNumber)}`);
            const data = await response.json();
            
            if (data.success && data.data) {
                const match = data.data;
                
                // 1. Update Tracking ID UI (Legacy support)
                if (match && match.tracking_id) {
                    const displayEl = document.getElementById('trackingIdDisplay');
                    if (displayEl) displayEl.textContent = match.tracking_id;
                    
                    const inputEl = document.getElementById('trackingIdInput');
                    if (inputEl) inputEl.value = match.tracking_id;
                }

                // 2. Update Alpine Data (Preferred)
                const modalEl = document.querySelector('[x-data^="fileNumberGenerator"]');
                if (modalEl && window.Alpine) {
                    const alpineData = window.Alpine.$data(modalEl);
                    
                    // Mark as inherited to trigger UI effects
                    alpineData.isInherited = true;

                    // Populate fields if they exist in match
                    if (match.file_name) alpineData.fileName = match.file_name;
                    if (match.location) alpineData.location = match.location;
                    if (match.lga) alpineData.lga = match.lga;
                    if (match.plot_no) alpineData.plotNo = match.plot_no;
                    if (match.tp_no) alpineData.tpNo = match.tp_no;
                    if (match.phone_no) alpineData.phone_no = match.phone_no;
                    if (match.address) alpineData.address = match.address;
                    if (match.rep_phone_no) alpineData.rep_phone_no = match.rep_phone_no;
                    if (match.rep_address) alpineData.rep_address = match.rep_address;

                    // Specialized handling for Land Use and Prefix
                    if (match.land_use || fileNumber) {
                        let luCode = '';
                        let matchedPrefix = null;

                        if (match.land_use) {
                            // 1. Success from API/DB join
                            const luName = match.land_use.toUpperCase();
                            if (luName.includes('RESIDENTIAL')) luCode = 'RES';
                            else if (luName.includes('COMMERCIAL')) luCode = 'COM';
                            else if (luName.includes('INDUSTRIAL')) luCode = 'IND';
                            else if (luName.includes('AGRICULTURAL')) luCode = 'AG';
                            else luCode = luName.substring(0, 3);
                        } else {
                            // 2. Fallback: Parse from file number string (e.g., "AG-RC-2026-32")
                            console.log('No land_use in data, attempting fallback from file number:', fileNumber);
                            const parts = fileNumber.split('-');
                            if (parts.length > 0) {
                                const prefixFound = parts[0]; // e.g., "AG" or "RES"
                                matchedPrefix = alpineData.allAllPrefixes.find(p => p.prefix === prefixFound || p.prefix === `CON-${prefixFound}`);
                                if (matchedPrefix) {
                                    luCode = prefixFound;
                                }
                            }
                        }

                        if (luCode) {
                            alpineData.landUse = luCode;
                            
                            // Try to find a matching prefix for the dropdown
                            if (!matchedPrefix) {
                                if (alpineData.applicationType === 'conversion') {
                                    matchedPrefix = alpineData.allAllPrefixes.find(p => p.prefix.includes('CON-') && p.prefix.includes(luCode));
                                } else {
                                    matchedPrefix = alpineData.allAllPrefixes.find(p => !p.prefix.includes('CON-') && p.prefix.includes(luCode));
                                }
                            }

                            if (matchedPrefix) {
                                alpineData.prefix = matchedPrefix.prefix;
                                // Fetch purposes for this land use ID to keep UI consistent
                                if (matchedPrefix.land_use_id) {
                                    alpineData.fetchDependentData(matchedPrefix.land_use_id);
                                }
                            }
                        }
                    }

                    alpineData.updatePreview();
                    console.log('Successfully auto-populated fields from file:', fileNumber);
                }
            }
        } catch (error) {
            console.error('Error fetching file details:', error);
        }
    }

    // Function to handle commissioning sheet from dropdown
    function openCommissioningSheetFromDropdown(button) {
        openCommissioningSheetForRowData(button);
    }

    // Function to handle commissioning sheet with row data
    function openCommissioningSheetForRowData(button) {
        try {
            // Get data from button attributes
            const mlsfNo = button.getAttribute('data-mlsf-no');
            const kangisNo = button.getAttribute('data-kangis-no');
            const newKangisNo = button.getAttribute('data-new-kangis-no');
            const fileName = button.getAttribute('data-file-name');
            const plotNo = button.getAttribute('data-plot-no');
            const tpNo = button.getAttribute('data-tp-no');
            const location = button.getAttribute('data-location');
            const lga = button.getAttribute('data-lga');
            const trackingId = button.getAttribute('data-tracking-id');

            console.log('Button data attributes:', {
                mlsfNo, kangisNo, newKangisNo, fileName, plotNo, tpNo, location, lga, trackingId
            });

            // Determine the best file number to use (prioritize mlsfNo, then kangisNo, then newKangisNo)
            let fileNumber = '';
            if (mlsfNo && mlsfNo !== 'N/A' && mlsfNo.trim() !== '') {
                fileNumber = mlsfNo;
            } else if (kangisNo && kangisNo !== 'N/A' && kangisNo.trim() !== '') {
                fileNumber = kangisNo;
            } else if (newKangisNo && newKangisNo !== 'N/A' && newKangisNo.trim() !== '') {
                fileNumber = newKangisNo;
            }

            // Clean up other fields (replace 'N/A' with empty string)
            const cleanFileName = (fileName && fileName !== 'N/A') ? fileName : '';
            const cleanPlotNo = (plotNo && plotNo !== 'N/A') ? plotNo : '';
            const cleanTpNo = (tpNo && tpNo !== 'N/A') ? tpNo : '';
            const cleanLocation = (location && location !== 'N/A') ? location : '';
            const cleanLga = (lga && lga !== 'N/A') ? lga : '';

            openCommissioningSheetForFile(fileNumber, cleanFileName, cleanPlotNo, cleanTpNo, cleanLocation, cleanLga, trackingId);

        } catch (error) {
            console.error('Error reading button data:', error);
            // Fallback - just open the modal without pre-filling
            openCommissioningSheetModal();
        }
    }

    function openCommissioningSheetForFile(fileNumber, fileName, plotNo, tpNo, location, lga, trackingId) {
        // Debug logging
        console.log('Opening commissioning sheet with data:', {
            fileNumber: fileNumber,
            fileName: fileName,
            plotNo: plotNo,
            tpNo: tpNo,
            location: location,
            lga: lga,
            trackingId: trackingId
        });

        // Set current time
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        document.getElementById('cs_time_created').value = `${hours}:${minutes}`;

        // Open the modal
        document.getElementById('commissioningSheetModal').classList.remove('hidden');

        // Pre-fill         the form with fil           e data
        document.getElementById('cs_file_number').value = fileNumber || '';
        document.getElementById('cs_file_name').value = fileName || '';
        document.getElementById('cs_plot_number').value = plotNo || '';
        document.getElementById('cs_tp_number').value = tpNo || '';
        document.getElementById('cs_location').value = location || '';
        document.getElementById('cs_lga').value = lga || '';
        document.getElementById('cs_tracking_id').value = trackingId || '';

        // Set today's date
        document.getElementById('cs_date_created').value = new Date().toISOString().split('T')[0];

        // Set current user if available
        @if(Auth::check())
            document.getElementById('cs_created_by').value = '{{ Auth::user()->name }}';
        @endif

        // Set allottee field to same value as file name
        document.getElementById('cs_name_allottee').value = fileName || '';

        // Show data load status if any data was pre-filled
        const statusElement = document.getElementById('dataLoadStatus');
        if (fileNumber || fileName || plotNo || tpNo || location) {
            statusElement.classList.remove('hidden');
            setTimeout(() => {
                statusElement.classList.add('hidden');
            }, 5000); // Hide after 5 seconds
        }
    }

    function closeCommissioningSheetModal() {
        document.getElementById('commissioningSheetModal').classList.add('hidden');
        // Hide status message
        document.getElementById('dataLoadStatus').classList.add('hidden');
        // Reset form
        document.getElementById('commissioningSheetForm').reset();
        // Set today's date
        document.getElementById('cs_date_created').value = new Date().toISOString().split('T')[0];
    }

    function submitCommissioningSheet(event) {
        event.preventDefault();

        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading on button
        showLoadingButton(submitBtn, originalText);

        const formData = new FormData(document.getElementById('commissioningSheetForm'));

        fetch('{{ route("commissioning-sheet.store") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
            .then(response => response.json())
            .then(data => {
                hideLoadingButton(submitBtn, originalText);

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Commissioning sheet saved successfully',
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        closeCommissioningSheetModal();
                        // Reload the table to update the commissioning sheet status
                        if (typeof table !== 'undefined' && table.ajax) {
                            table.ajax.reload();
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'Failed to save commissioning sheet',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                hideLoadingButton(submitBtn, originalText);
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while saving the commissioning sheet',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    function generateAndPrintCommissioningSheet() {
        const form = document.getElementById('commissioningSheetForm');
        const formData = new FormData(form);
        // Validate required fields
        const fileNumber = formData.get('file_number');
        if (!fileNumber) {
            Swal.fire({
                icon: 'warning',
                title: 'Validation Error',
                text: 'File number is required',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        // Show loading
        showGlobalLoading('Saving commissioning sheet...');

        // Save to DB first, then generate/print
        fetch('{{ route("commissioning-sheet.store") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
            .then(response => response.json())
            .then(data => {
                hideGlobalLoading();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: 'Commissioning sheet saved. Generating PDF...',
                        showConfirmButton: false,
                        timer: 1000
                    });
                    // Now generate/print PDF
                    generateCommissioningSheetPDF(formData);
                    // Optionally reload table
                    if (typeof table !== 'undefined' && table.ajax) {
                        table.ajax.reload();
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'Failed to save commissioning sheet',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                hideGlobalLoading();
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while saving the commissioning sheet',
                });
            });
    }


    // Helper function to fetch images as base64 (shared or private)
    async function getImageBase64(url) {
        if (!url) return null;
        try {
            const response = await fetch(url, {
                mode: 'cors',
                headers: { 'Accept': 'image/*' }
            });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const blob = await response.blob();
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onloadend = () => resolve(reader.result);
                reader.onerror = reject;
                reader.readAsDataURL(blob);
            });
        } catch (error) {
            console.warn(`Failed to fetch image from ${url}:`, error);
            return null;
        }
    }

    async function generateCommissioningSheetPDF(formData, watermarkText = 'ORIGINAL') {
        try {
            showGlobalLoading('Generating PDF...');

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            // Fetch logos
            const logo1Base64 = await getImageBase64('/assets/logo/logo1.png') || await getImageBase64('/assets/logo/logo1.jpg') || await getImageBase64('/assets/logo/logo1.jpeg');
            const logo2Base64 = await getImageBase64('/assets/logo/logo3.jpeg') || await getImageBase64('/assets/logo/las.jpeg') || await getImageBase64('/assets/logo/logo3.jpg');
            // LAS logo for footer
            const leftFooterLogoBase64 = await getImageBase64('/assets/logo/1.jpeg');
            // Try PNG first, fallback to JPEG
            // Try local asset path for LAS logo
            let footerLogoBase64 = await getImageBase64('/assets/logo/las.png');
            if (!footerLogoBase64) {
                footerLogoBase64 = await getImageBase64('/assets/logo/las.jpg');
            }
            // Fallback to remote if local fails
            if (!footerLogoBase64) {
                footerLogoBase64 = await getImageBase64('http://app.klaes.ng/assets/logo/las.png');
            }
            if (!footerLogoBase64) {
                footerLogoBase64 = await getImageBase64('http://app.klaes.ng/assets/logo/las.jpg');
            }

            // Add Header
            if (logo1Base64) doc.addImage(logo1Base64, 'JPEG', 20, 12, 20, 20);
            if (logo2Base64) doc.addImage(logo2Base64, 'JPEG', 170, 12, 20, 20);

            doc.setFontSize(14);
            doc.setFont("helvetica", "bold");
            doc.text("MINISTRY OF LAND & PHYSICAL PLANNING", 105, 18, { align: "center" });
            doc.setFontSize(12);
            doc.text("DEPARTMENT OF LAND", 105, 26, { align: "center" });
            doc.setFontSize(11);
            doc.text("FILE COMMISSIONING SHEET", 105, 36, { align: "center" });

            // Add Watermark
            doc.setTextColor(255, 0, 0);
            doc.setGState(doc.GState({ opacity: 0.2 }));
            doc.setFontSize(45);
            doc.text(watermarkText, 105, 148.5, { align: "center", angle: 45, baseline: 'middle' });
            doc.setGState(doc.GState({ opacity: 1.0 }));
            doc.setTextColor(0, 0, 0);

            // Time/Date/TrackingID Logic
            const trackingId = formData.get('tracking_id') || '';
            if (trackingId) {
                try {
                    const qrContainer = document.createElement('div');
                    new QRCode(qrContainer, { text: trackingId, width: 128, height: 128 });
                    const canvas = qrContainer.querySelector('canvas');
                    if (canvas) {
                        doc.addImage(canvas.toDataURL('image/png'), 'PNG', 92.5, 42, 25, 25);
                    }
                } catch (e) { console.warn('QR failed', e); }
            }

            // Body content
            doc.setFontSize(10);
            doc.setFont("helvetica", "normal");
            let y = 85;
            const fields = [
                ['File No:', formData.get('file_number')],
                ['File Name:', formData.get('file_name')],
                ['Plot No:', formData.get('plot_number')],
                ['TP No:', formData.get('tp_number')],
                ['Location:', formData.get('location')],
                ['Time Commissioned:', formatTimeToAMPM(formData.get('time_created'))],
                ['Date Commissioned:', formData.get('date_created')],
                ['Commissioned by:', formData.get('created_by')]
            ];

            fields.forEach(([label, value]) => {
                doc.setFont("helvetica", "bold");
                doc.text(label, 25, y);
                doc.setFont("helvetica", "normal");
                doc.text(String(value || ''), 72, y);
                doc.line(70, y + 2, 185, y + 2);
                y += 12;
            });

            // Signatures & Footer
            y += 15;
            doc.text("Created by Signature", 50, y, { align: "center" });
            doc.text("Approved by Signature", 150, y, { align: "center" });
            doc.line(25, y + 10, 75, y + 10); doc.line(125, y + 10, 175, y + 10);

            if (leftFooterLogoBase64) doc.addImage(leftFooterLogoBase64, 'JPEG', 15, 272, 18, 18);
            if (footerLogoBase64) doc.addImage(footerLogoBase64, 'JPEG', 165, 272, 28, 12);

            hideGlobalLoading();
            doc.save(`commissioning-sheet-${formData.get('file_number')}.pdf`);

        } catch (error) {
            hideGlobalLoading();
            console.error('PDF Error:', error);
            Swal.fire('Error', 'Failed to generate PDF: ' + error.message, 'error');
        }
    }


    async function generateBatchCommissioningSheetPDF(records, batchNo, watermarkText = 'ORIGINAL') {
        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            // Fetch logos once and reuse for all pages.
            const logo1Base64 = await getImageBase64('/assets/logo/logo1.png') || await getImageBase64('/assets/logo/logo1.jpg') || await getImageBase64('/assets/logo/logo1.jpeg');
            const logo2Base64 = await getImageBase64('/assets/logo/logo3.jpeg') || await getImageBase64('/assets/logo/las.jpeg') || await getImageBase64('/assets/logo/logo3.jpg');
            const leftFooterLogoBase64 = await getImageBase64('/assets/logo/1.jpeg');
            const footerLogoBase64 = await getImageBase64('/assets/logo/las.jpeg');

            const totalPages = records.length;

            for (let i = 0; i < records.length; i++) {
                if (i > 0) doc.addPage();

                const row = records[i] || {};

                // Header logos
                if (logo1Base64) doc.addImage(logo1Base64, 'JPEG', 20, 12, 20, 20);
                if (logo2Base64) doc.addImage(logo2Base64, 'JPEG', 170, 12, 20, 20);

                // Header text
                doc.setFontSize(14);
                doc.setFont('helvetica', 'bold');
                doc.text('MINISTRY OF LAND & PHYSICAL PLANNING', 105, 18, { align: 'center' });
                doc.setFontSize(12);
                doc.text('DEPARTMENT OF LAND', 105, 26, { align: 'center' });
                doc.setFontSize(11);
                doc.text('FILE COMMISSIONING SHEET', 105, 36, { align: 'center' });

                // Watermark
                doc.setTextColor(255, 0, 0);
                doc.setGState(doc.GState({ opacity: 0.2 }));
                doc.setFontSize(45);
                doc.text(watermarkText, 105, 148.5, { align: 'center', angle: 45, baseline: 'middle' });
                doc.setGState(doc.GState({ opacity: 1.0 }));
                doc.setTextColor(0, 0, 0);

                // QR per record
                const trackingId = row.tracking_id || '';
                if (trackingId) {
                    try {
                        const qrContainer = document.createElement('div');
                        new QRCode(qrContainer, { text: trackingId, width: 128, height: 128 });
                        const canvas = qrContainer.querySelector('canvas');
                        if (canvas) doc.addImage(canvas.toDataURL('image/png'), 'PNG', 92.5, 42, 25, 25);
                    } catch (e) {
                        console.warn('QR failed', e);
                    }
                }

                // Body fields for this row/page
                doc.setFontSize(10);
                let y = 85;
                const leftMargin = 25;
                const textStartX = 72;

                const createdAt = row.created_at ? new Date(row.created_at) : new Date();

                const fields = [
                    ['File No:', row.full_file_number || row.mlsf_no || row.file_number || ''],
                    ['File Name:', row.file_name || ''],
                    ['Plot No:', row.plot_no || 'N/A'],
                    ['TP No:', row.tp_no || 'N/A'],
                    ['Location:', row.location || 'N/A'],
                    ['Time Commissioned:', createdAt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })],
                    ['Date Commissioned:', createdAt.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })],
                    ['Commissioned by:', row.created_by || '']
                ];

                fields.forEach(([label, value]) => {
                    doc.setFont('helvetica', 'bold');
                    doc.text(label, leftMargin, y);
                    doc.setFont('helvetica', 'normal');
                    doc.text(String(value || ''), textStartX, y);
                    doc.line(70, y + 2, 185, y + 2);
                    y += 12;
                });

                // Signatures
                y += 15;
                doc.text('Created by Signature', 50, y, { align: 'center' });
                doc.text('Approved by Signature', 150, y, { align: 'center' });
                doc.line(25, y + 10, 75, y + 10);
                doc.line(125, y + 10, 175, y + 10);

                // Footer
                doc.setFontSize(8);
                doc.text(`Generated on ${new Date().toLocaleString()} | Batch: ${batchNo || 'N/A'} | Page ${i + 1} of ${totalPages}`, 105, 270, { align: 'center' });
                if (leftFooterLogoBase64) doc.addImage(leftFooterLogoBase64, 'JPEG', 15, 272, 18, 18);
                if (footerLogoBase64) doc.addImage(footerLogoBase64, 'JPEG', 150, 272, 45, 18);
            }

            hideGlobalLoading();
            doc.save(`batch-commissioning-sheet-${batchNo}.pdf`);

            Swal.fire({
                icon: 'success',
                title: 'Processed',
                html: `Batch commissioning sheet generated successfully.<br><strong>${records.length}</strong> page${records.length === 1 ? '' : 's'} for batch <strong>${batchNo}</strong>.`,
                confirmButtonColor: '#10b981'
            });
        } catch (error) {
            hideGlobalLoading();
            console.error('Error generating batch PDF:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Failed to generate batch PDF: ' + error.message,
                confirmButtonColor: '#ef4444'
            });
        }
    }

    // Generate Batch PDF for all records in a batch (or date)
    async function generateBatchPDF(identifier, status = 'Original', scope = 'batch') {
        try {
            if (!identifier) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Identifier Required',
                    text: 'Please select a valid batch or date first.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }

            // Show loading with info
            showGlobalLoading('Fetching records...');

            // Fetch all records
            const requestBody = scope === 'date' 
                ? { scope: 'date', date: identifier }
                : { batch_no: identifier };

            const response = await fetch('{{ route("mls-fileno.batch-records") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ ...requestBody, include_printed: true })
            });

            const result = await response.json();

            if (!result.success || !result.data || result.data.length === 0) {
                hideGlobalLoading();
                Swal.fire({
                    icon: 'error',
                    title: 'No Records Found',
                    text: result.message || 'No records found for this ' + scope,
                    confirmButtonColor: '#ef4444'
                });
                return;
            }

            const records = result.data;
            const count = result.count;

            // Show notification about generating PDF
            Swal.fire({
                icon: 'info',
                title: 'Processing',
                html: `Total Number Batch for Commissioning Sheet is processing for ${scope} <strong>${identifier}</strong>`,
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false
            });

            // Wait a moment for the notification to be visible
            await new Promise(resolve => setTimeout(resolve, 500));

            showGlobalLoading(`Generating summary PDF with ${count} records...`);

            // Determine watermark text based on status
            const watermarkText = (status === 'Certified True Copy') ? 'CERTIFIED TRUE COPY' : 'ORIGINAL';

            // Call the dedicated PDF generation function
            await generateBatchCommissioningSheetPDF(records, identifier, watermarkText);

        } catch (error) {
            hideGlobalLoading();
            console.error('Error generating batch PDF:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Failed to generate batch PDF: ' + error.message,
                confirmButtonColor: '#ef4444'
            });
        }
    }

    // Generate one consolidated commissioning sheet for all files commissioned in the last 24 hours.
    async function generateDaily24hPDF(status = 'Original') {
        try {
            showGlobalLoading('Fetching all commissioned files for the last 24 hours...');

            const response = await fetch('{{ route("mls-fileno.batch-records") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ scope: 'daily_24h' })
            });

            const result = await response.json();

            if (!result.success || !result.data || result.data.length === 0) {
                hideGlobalLoading();
                Swal.fire({
                    icon: 'warning',
                    title: 'No Records in Last 24 Hours',
                    text: result.message || 'No commissioned files found in the last 24 hours.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }

            const records = result.data;
            const watermarkText = (status === 'Certified True Copy') ? 'CERTIFIED TRUE COPY' : 'ORIGINAL';
            const now = new Date();
            const label = `DAILY-24H-${now.toISOString().slice(0, 10)}`;

            await generateBatchCommissioningSheetPDF(records, label, watermarkText);
        } catch (error) {
            hideGlobalLoading();
            console.error('Error generating 24-hour batch PDF:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Failed to generate last-24-hours commissioning sheet: ' + error.message,
                confirmButtonColor: '#ef4444'
            });
        }
    }

    // Dropdown Functions
    function toggleDropdown(button) {
        // Close all other dropdowns first
        closeAllDropdowns();

        // Toggle the clicked dropdown
        const dropdown = button.nextElementSibling;
        if (dropdown && dropdown.classList.contains('dropdown-menu')) {
            dropdown.classList.toggle('hidden');
        }
    }

    function closeAllDropdowns() {
        document.querySelectorAll('.dropdown-menu').forEach(dropdown => {
            dropdown.classList.add('hidden');
        });
    }



    // View existing commissioning sheet
    function viewCommissioningSheet(commissioningSheetId) {
        if (!commissioningSheetId) {
            alert('Commissioning sheet ID not found');
            return;
        }

        // Show loading
        showGlobalLoading('Loading commissioning sheet...');

        // Fetch the commissioning sheet data
        fetch(`/commissioning-sheet/${commissioningSheetId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                hideGlobalLoading();

                if (data.success && data.data) {
                    // Convert the data object to FormData format expected by generateCommissioningSheetPDF
                    const formData = new FormData();

                    // Map the response data to FormData
                    const responseData = data.data;
                    formData.append('file_number', responseData.file_number || '');
                    formData.append('file_name', responseData.file_name || '');
                    formData.append('name_or_allottee', responseData.name_or_allottee || '');
                    formData.append('plot_number', responseData.plot_number || '');
                    formData.append('tp_number', responseData.tp_number || '');
                    formData.append('location', responseData.location || '');
                    formData.append('date_created', responseData.date_created || responseData.created_at || '');
                    formData.append('created_by', responseData.created_by || '');

                    // Extract and append time from created_at if not explicitly provided
                    if (responseData.created_at) {
                        const date = new Date(responseData.created_at);
                        const hours = String(date.getHours()).padStart(2, '0');
                        const minutes = String(date.getMinutes()).padStart(2, '0');
                        formData.append('time_created', `${hours}:${minutes}`);
                    }

                    // Generate PDF with the FormData
                    generateCommissioningSheetPDF(formData);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Error loading commissioning sheet',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                hideGlobalLoading();
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error loading commissioning sheet data',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.relative')) {
            closeAllDropdowns();
        }
    });

    // Add event listeners for form inputs
    document.addEventListener('DOMContentLoaded', function () {
        // Load serial status when page loads
        if (document.getElementById('content-serial-init')) {
            loadSerialStatus();
        }

        // Initiali    ze Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });

    // ==================== TAB MANAGEMENT ====================

    /**
       * Switch between tabs
     */
    window.switchTab = function (tabName) {
        // Update tab buttons
        const tabs = ['generator', 'serial-init', 'consolidation'];
        tabs.forEach(tab => {
            const button = document.getElementById(`tab-${tab}`);
            const content = document.getElementById(`content-${tab}`);

            if (!button || !content) return;

            if (tab === tabName) {
                button.classList.add('active');
                button.classList.remove('border-transparent', 'text-gray-500');
                button.classList.add('border-blue-600', 'text-blue-600');
                content.classList.remove('hidden');
            } else {
                button.classList.remove('active');
                button.classList.remove('border-blue-600', 'text-blue-600');
                button.classList.add('border-transparent', 'text-gray-500');
                content.classList.add('hidden');
            }
        });

        // Refresh icons after DOM changes
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Load serial status if switching to serial-init tab
        if (tabName === 'serial-init') {
            loadSerialStatus();
        }
    };

    // ==================== SERIAL INITIALIZATION ====================

    /**
     * Load serial control status from server
     */
    window.loadSerialStatus = function () {
        const tableBody = document.getElementById('serialTableBody');
        if (!tableBody) return;

        // Show loading state
        tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                        <i data-lucide="loader" class="w-6 h-6 inline-block animate-spin"></i>
                        <p class="mt-2">Loading serial control data...</p>
                    </td>
                </tr>
            `;

        fetch('{{ route("mls-fileno.serial-status") }}')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to fetch serial status');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.data) {
                    renderSerialTable(data.data);
                } else {
                    throw new Error(data.message || 'Invalid response format');
                }
            })
            .catch(error => {
                console.error('Error loading serial status:', error);
                tableBody.innerHTML = `
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-red-500">
                                <i data-lucide="alert-circle" class="w-6 h-6 inline-block"></i>
                                <p class="mt-2">Error loading data: ${error.message}</p>
                                <button onclick="loadSerialStatus()" class="mt-4 text-blue-600 hover:text-blue-700">
                                    Try Again
                                </button>
                            </td>
                        </tr>
                    `;
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            });
    };

    /**
     * Render serial control table
     */
    function renderSerialTable(data) {
        const tableBody = document.getElementById('serialTableBody');
        if (!tableBody) return;

        if (!data || data.length === 0) {
            tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            No serial control data found
                        </td>
                    </tr>
                `;
            return;
        }

        let html = '';
        data.forEach(item => {
            const statusBadge = getStatusBadge(item);
            const actionButton = getActionButton(item);
            const initializedAt = item.initialized_at
                ? new Date(item.initialized_at).toLocaleDateString()
                : '-';

            html += `
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="font-medium text-gray-900">${item.land_use}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-600">${item.year}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <input 
                                type="number" 
                                id="serial-${item.land_use}" 
                                value="${item.last_serial}" 
                                ${item.is_locked ? 'disabled readonly' : ''}
                                class="w-24 px-2 py-1 border rounded ${item.is_locked ? 'bg-gray-100 text-gray-500' : 'border-gray-300'}"
                                min="0"
                            />
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">${statusBadge}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">${item.initialized_by || '-'}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">${initializedAt}</td>
                        <td class="px-4 py-3 whitespace-nowrap">${actionButton}</td>
                    </tr>
                `;
        });

        tableBody.innerHTML = html;

        // Refresh icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    /**
     * Get status badge HTML
     */
    function getStatusBadge(item) {
        if (item.is_locked) {
            return `
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <i data-lucide="lock" class="w-3 h-3 mr-1"></i>
                        Locked
                    </span>
                `;
        } else if (item.is_initialized) {
            return `
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        <i data-lucide="check-circle" class="w-3 h-3 mr-1"></i>
                        Initialized
                    </span>
                `;
        } else {
            return `
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                        <i data-lucide="minus-circle" class="w-3 h-3 mr-1"></i>
                        Not Initialized
                    </span>
                `;
        }
    }

    /**
     * Get action button HTML
     */
    function getActionButton(item) {
        if (item.is_locked) {
            return `
                    <div class="flex items-center space-x-2 text-gray-400">
                        <i data-lucide="lock" class="w-4 h-4"></i>
                        <span class="text-xs">Locked</span>
                    </div>
                `;
        } else {
            return `
                    <button 
                        onclick="initializeSerial('${item.land_use}', ${item.year})"
                        class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center space-x-1">
                        <i data-lucide="settings" class="w-4 h-4"></i>
                        <span>Initialize</span>
                    </button>
                `;
        }
    }

    /**
     * Initialize serial number for a land use
     */
    window.initializeSerial = function (landUse, year) {
        const inputElement = document.getElementById(`serial-${landUse}`);
        if (!inputElement) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Could not find serial input field'
            });
            return;
        }

        const lastSerial = parseInt(inputElement.value);

        if (isNaN(lastSerial) || lastSerial < 0) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Value',
                text: 'Please enter a valid serial number (0 or greater)'
            });
            return;
        }

        // Show confirmation dialog with warning
        Swal.fire({
            title: 'Initialize Serial Number?',
            html: `
                    <div class="text-left">
                        <p class="mb-4">You are about to initialize the serial number for:</p>
                        <ul class="list-disc list-inside mb-4 text-gray-700">
                            <li><strong>Land Use:</strong> ${landUse}</li>
                            <li><strong>Year:</strong> ${year}</li>
                            <li><strong>Last Serial:</strong> ${lastSerial}</li>
                        </ul>
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-3 mb-4">
                            <p class="text-sm text-yellow-700">
                                <strong>⚠️ Warning:</strong> This action cannot be undone!
                                Once locked, the serial number cannot be modified through the UI.
                            </p>
                        </div>
                        <p class="text-sm">The next generated file number will be: <strong>${landUse}-${year}-${lastSerial + 1}</strong></p>
                    </div>
                `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Initialize & Lock',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            reverseButtons: true,
            customClass: {
                popup: 'text-left'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                performInitialization(landUse, year, lastSerial);
            }
        });
    };

    /**
     * Perform the actual initialization
     */
    function performInitialization(landUse, year, lastSerial) {
        // Show loading
        Swal.fire({
            title: 'Initializing...',
            text: 'Please wait while we initialize the serial number',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('{{ route("mls-fileno.initialize-serial") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                land_use: landUse,
                year: year,
                last_serial: lastSerial
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Serial number initialized and locked successfully',
                        confirmButtonColor: '#10b981'
                    });

                    // Refresh the table
                    loadSerialStatus();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed',
                        text: data.message || 'Could not initialize serial number'
                    });
                }
            })
            .catch(error => {
                console.error('Error initializing serial:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while initializing the serial number'
                });
            });
    }

    /**
     * Refresh serial table
     */
    window.refreshSerialTable = function () {
        loadSerialStatus();
    };

    window.generateConversionApplication = function (id, fileNo) {
        Swal.fire({
            title: 'Property Acquisition Method',
            html: `
                <div class="text-left space-y-3 p-2">
                    <p class="text-sm text-gray-600 mb-4">Please select how the property for <strong>${fileNo}</strong> was acquired:</p>
                    <div class="space-y-2">
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="a" class="w-4 h-4 text-blue-600" checked>
                            <span class="text-sm font-medium text-gray-700">a. By Purchase</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="b" class="w-4 h-4 text-blue-600">
                            <span class="text-sm font-medium text-gray-700">b. By Inheritance</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="c" class="w-4 h-4 text-blue-600">
                            <span class="text-sm font-medium text-gray-700">c. By Gift</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="d" class="w-4 h-4 text-blue-600">
                            <span class="text-sm font-medium text-gray-700">d. Direct Local Government Allocation</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="e" class="w-4 h-4 text-blue-600" id="method_other_radio">
                            <span class="text-sm font-medium text-gray-700">e. Any other (Specify)</span>
                        </label>
                    </div>
                    <div id="other_specify_container" class="mt-3 hidden animate-fadeIn">
                        <input type="text" id="other_specify_input" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all" 
                               placeholder="Please specify other method...">
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Generate Document',
            cancelButtonText: 'Cancel',
            didOpen: () => {
                const radios = document.querySelectorAll('input[name="acquisition_method"]');
                const otherContainer = document.getElementById('other_specify_container');
                const otherInput = document.getElementById('other_specify_input');

                radios.forEach(radio => {
                    radio.addEventListener('change', (e) => {
                        if (e.target.value === 'e') {
                            otherContainer.classList.remove('hidden');
                            otherInput.focus();
                        } else {
                            otherContainer.classList.add('hidden');
                        }
                    });
                });
            },
            preConfirm: () => {
                const selectedMethod = document.querySelector('input[name="acquisition_method"]:checked').value;
                const otherValue = document.getElementById('other_specify_input').value;

                if (selectedMethod === 'e' && !otherValue.trim()) {
                    Swal.showValidationMessage('Please specify the other acquisition method');
                    return false;
                }

                return { method: selectedMethod, other: otherValue };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const { method, other } = result.value;
                let url = `/file-numbers/conversion-application/${id}?method=${method}`;
                if (method === 'e' && other) {
                    url += `&other=${encodeURIComponent(other)}`;
                }
                // Open in new tab
                window.open(url, '_blank');
            }
        });
    }

    window.generateBatchConversionApplication = function (batchNo) {
        Swal.fire({
            title: 'Batch Property Acquisition Method',
            html: `
                <div class="text-left space-y-3 p-2">
                    <p class="text-sm text-gray-600 mb-4">Please select how the properties in batch <strong>${batchNo}</strong> were acquired:</p>
                    <div class="space-y-2">
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="batch_acquisition_method" value="a" class="w-4 h-4 text-blue-600" checked>
                            <span class="text-sm font-medium text-gray-700">a. By Purchase</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="batch_acquisition_method" value="b" class="w-4 h-4 text-blue-600">
                            <span class="text-sm font-medium text-gray-700">b. By Inheritance</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="batch_acquisition_method" value="c" class="w-4 h-4 text-blue-600">
                            <span class="text-sm font-medium text-gray-700">c. By Gift</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="batch_acquisition_method" value="d" class="w-4 h-4 text-blue-600">
                            <span class="text-sm font-medium text-gray-700">d. Director Local Government Allocation</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="batch_acquisition_method" value="e" class="w-4 h-4 text-blue-600" id="batch_method_other_radio">
                            <span class="text-sm font-medium text-gray-700">e. Any other (Specify)</span>
                        </label>
                    </div>
                    <div id="batch_other_specify_container" class="mt-3 hidden animate-fadeIn">
                        <input type="text" id="batch_other_specify_input" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all" 
                               placeholder="Please specify other method...">
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Generate Batch Document',
            cancelButtonText: 'Cancel',
            didOpen: () => {
                const radios = document.querySelectorAll('input[name="batch_acquisition_method"]');
                const otherContainer = document.getElementById('batch_other_specify_container');
                const otherInput = document.getElementById('batch_other_specify_input');

                radios.forEach(radio => {
                    radio.addEventListener('change', (e) => {
                        if (e.target.value === 'e') {
                            otherContainer.classList.remove('hidden');
                            otherInput.focus();
                        } else {
                            otherContainer.classList.add('hidden');
                        }
                    });
                });
            },
            preConfirm: () => {
                const selectedMethod = document.querySelector('input[name="batch_acquisition_method"]:checked').value;
                const otherValue = document.getElementById('batch_other_specify_input').value;

                if (selectedMethod === 'e' && !otherValue.trim()) {
                    Swal.showValidationMessage('Please specify the other acquisition method');
                    return false;
                }

                return { method: selectedMethod, other: otherValue };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const { method, other } = result.value;

                // Show processing notice
                Swal.fire({
                    icon: 'info',
                    title: 'Processing',
                    text: 'Total Number Batch for Conversion Application is processing...',
                    timer: 2000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    didClose: () => {
                        let url = `{{ route("file-numbers.batch-conversion-application", ":batchNo") }}`.replace(':batchNo', batchNo) + `?method=${method}`;
                        if (method === 'e' && other) {
                            url += `&other=${encodeURIComponent(other)}`;
                        }
                        // Open in new tab
                        window.open(url, '_blank');

                        // Show success notice
                        setTimeout(() => {
                            Swal.fire({
                                icon: 'success',
                                title: 'Processed',
                                text: 'Total Number Batch for Conversion Application processed!',
                                confirmButtonColor: '#10b981'
                            });
                        }, 500);
                    }
                });
            }
        });
    }

    // Initialize Action Dropdowns with Smart Positioning to prevent clipping
    document.addEventListener('click', function (e) {
        // Toggle Dropdown
        const button = e.target.closest('.action-dropdown button');
        if (button) {
            e.stopPropagation();
            const dropdown = button.nextElementSibling;

            // Close all other dropdowns
            document.querySelectorAll('.action-dropdown-menu').forEach(d => {
                if (d !== dropdown) {
                    d.classList.add('hidden');
                    d.classList.remove('show');
                }
            });

            if (dropdown && dropdown.classList.contains('action-dropdown-menu')) {
                const isHidden = dropdown.classList.contains('hidden') || getComputedStyle(dropdown).display === 'none';

                if (isHidden) {
                    // Show first to measure
                    dropdown.classList.remove('hidden');
                    dropdown.classList.add('show');

                    // Fixed Positioning Logic
                    const rect = button.getBoundingClientRect();
                    const dropdownRect = dropdown.getBoundingClientRect();
                    const dropdownWidth = dropdownRect.width || 220;
                    const dropdownHeight = dropdownRect.height || 200;
                    const windowWidth = window.innerWidth;
                    const windowHeight = window.innerHeight;

                    dropdown.style.position = 'fixed';
                    dropdown.style.zIndex = '9999';

                    // Horizontal Positioning
                    // Default to aligning left edge with button left edge
                    let leftPos = rect.left;

                    // Check right overflow: if it goes off screen, align right edge to window right - margin
                    if (leftPos + dropdownWidth > windowWidth - 10) {
                        leftPos = windowWidth - dropdownWidth - 10;
                    }

                    // Don't go off screen left
                    if (leftPos < 10) leftPos = 10;

                    dropdown.style.left = `${leftPos}px`;
                    dropdown.style.right = 'auto'; // clear right

                    // Vertical Positioning
                    const spaceBelow = windowHeight - rect.bottom;
                    const spaceAbove = rect.top;

                    // Smart Decision:
                    // 1. If not enough space below, try above.
                    // 2. OR if we are in the bottom 40% of screen, prefer above (better UX).
                    // 3. Ensure we have enough space above before flipping.

                    const notEnoughBelow = spaceBelow < dropdownHeight;
                    const inBottomZone = rect.top > (windowHeight * 0.6);
                    const hasSpaceAbove = spaceAbove > dropdownHeight;

                    if ((notEnoughBelow || inBottomZone) && hasSpaceAbove) {
                        // Show Above
                        dropdown.style.top = 'auto';
                        dropdown.style.bottom = `${windowHeight - rect.top + 2}px`; // 2px margin above button
                    } else {
                        // Show Below (Default)
                        dropdown.style.top = `${rect.bottom + 2}px`; // 2px margin below button
                        dropdown.style.bottom = 'auto';
                    }

                } else {
                    // Hide
                    dropdown.classList.add('hidden');
                    dropdown.classList.remove('show');
                }
            }
            return;
        }

        // Close when clicking outside
        if (!e.target.closest('.action-dropdown-menu')) {
            document.querySelectorAll('.action-dropdown-menu').forEach(d => {
                d.classList.add('hidden');
                d.classList.remove('show');
            });
        }
    });


    // Close dropdowns on scroll to keep fixed position valid
    window.addEventListener('scroll', function () {
        document.querySelectorAll('.action-dropdown-menu.show').forEach(d => {
            d.classList.add('hidden');
            d.classList.remove('show');
        });
    }, true);

    // Printer Manager Logic
    let pmState = {
        id: null,
        batchNo: null,
        selectedBatchNo: null,
        fileNo: null, // We need to fetch this or pass it
        mode: 'Individual', // Individual, Batch or Daily24h
        docType: 'Commissioning Sheet',
        allowed: false
    };
    const DAILY_PRINT_REFERENCE = 'MLS-DAILY-24H';

    function openFilePrinterManager(id, arg2, arg3, forceSingle = false) {
        // Ensure Generator Modal is closed to prevent conflicts
        if (typeof closeGenerateModal === 'function') {
            closeGenerateModal();
        }

        const modal = document.getElementById('printerManagerModal');
        
        if (modal) {
            // TELEPORT: Move modal to body to break out of any stacking contexts
            if (modal.parentNode !== document.body) {
                document.body.appendChild(modal);
            }

            modal.classList.remove('hidden');
            
            // Ultra-aggressive styling to ensure it's on top of Batch Details
            modal.style.setProperty('display', 'block', 'important');
            modal.style.setProperty('z-index', '1000010', 'important'); 
            modal.style.setProperty('position', 'fixed', 'important');
            modal.style.setProperty('top', '0', 'important');
            modal.style.setProperty('left', '0', 'important');
            modal.style.setProperty('width', '100%', 'important');
            modal.style.setProperty('height', '100%', 'important');
            
            // Ensure icons are rendered
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        } else {
            console.error('Printer Manager Modal element NOT found!');
            return;
        }

        try {
            pmState.id = id;

            // Handle variable arguments to support both JS-render (3 args) and PHP-render (2 args)
            if (arg3 !== undefined) {
                // Called as (id, fileNo, batchNo) from JS DataTables render
                pmState.fileNo = arg2;
                pmState.batchNo = arg3;
            } else {
                // Called as (id, batchNo) from Controller (Capture Existing)
                pmState.batchNo = arg2;

                // Try to lookup fileNo from DataTables if available, or default to placeholder
                try {
                    const table = $('#mlsfTable').DataTable();
                    const rowData = table.row((idx, data) => data.id == id).data();
                    pmState.fileNo = rowData ? rowData.mlsfNo : '--';
                } catch (e) {
                    console.warn('Could not lookup file number for Printer Manager', e);
                    pmState.fileNo = '--';
                }
            }

            console.log('PM State initialized:', pmState);

            const batchNo = pmState.batchNo;

            // Set Display Texts
            const refDisplay = document.getElementById('pmRefDisplay');
            const batchDisplay = document.getElementById('pmBatchDisplay');
            if (refDisplay) refDisplay.innerText = pmState.fileNo || '--';
            if (batchDisplay) batchDisplay.innerText = batchNo || 'N/A';

            pmState.selectedBatchNo = batchNo || null;

            loadPrintableBatches(pmState.selectedBatchNo);

            syncDocTypeForMode();

            // Toggle visibility of batch info
            const batchContainer = document.getElementById('pmBatchContainer');
            if (batchContainer) {
                if (batchNo && batchNo !== 'null' && batchNo !== 'undefined') {
                    batchContainer.classList.remove('hidden');
                } else {
                    batchContainer.classList.add('hidden');
                }
            }

            const batchBtn = document.getElementById('pmModeBatch');
            if (batchBtn) {
                const shouldDisableBatch = !!forceSingle;
                batchBtn.disabled = shouldDisableBatch;

                if (shouldDisableBatch) {
                    batchBtn.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    batchBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }

            // Reset to default - force Individual if forced or no batchNo, else prefer Batch if avail
            if (forceSingle) {
                setPrintMode('Individual');
            } else {
                setPrintMode(batchNo ? 'Batch' : 'Individual');
            }

            // Sync print status from server
            checkPrintStatus();
        } catch (error) {
            console.error('Error initializing Printer Manager:', error);
        }
    }


    function syncDocTypeForMode() {
        const docTypeSelect = document.getElementById('pmDocType');
        if (!docTypeSelect) return;

        const conversionOption = docTypeSelect.querySelector('option[value="Application for Conversion"]');
        if (!conversionOption) return;

        const isConversionFile = pmState.fileNo && (
            String(pmState.fileNo).trim().toUpperCase().startsWith('CON-')
        );

        const allowConversion = pmState.mode !== 'Daily24h' && isConversionFile;

        if (allowConversion) {
            conversionOption.hidden = false;
            conversionOption.disabled = false;
            conversionOption.style.display = '';
        } else {
            conversionOption.hidden = true;
            conversionOption.disabled = true;
            conversionOption.style.display = 'none';
            if (docTypeSelect.value === 'Application for Conversion') {
                docTypeSelect.value = 'Commissioning Sheet';
            }
        }
    }

    function onBatchSelectionChange() {
        const selector = document.getElementById('pmBatchSelect');
        if (!selector) return;

        pmState.selectedBatchNo = selector.value || null;

        const batchDisplay = document.getElementById('pmBatchDisplay');
        if (batchDisplay) {
            batchDisplay.innerText = pmState.selectedBatchNo || 'N/A';
        }

        checkPrintStatus();
    }

    async function loadPrintableBatches(preselectBatchNo = null) {
        const selector = document.getElementById('pmBatchSelect');
        if (!selector) return;

        try {
            selector.innerHTML = '<option value="">Loading unprinted batches...</option>';

            const response = await fetch('{{ route("mls-fileno.printable-batches") }}');
            const payload = await response.json();

            if (!payload.success) {
                throw new Error(payload.message || 'Failed to load printable batches');
            }

            const options = ['<option value="">Select an unprinted batch...</option>'];
            (payload.data || []).forEach((row) => {
                const batchNo = row.batch_no || '';
                if (!batchNo) return;
                const count = Number(row.total_records || 0);
                options.push(`<option value="${batchNo}">${batchNo} (${count} file${count === 1 ? '' : 's'})</option>`);
            });

            selector.innerHTML = options.join('');

            const preferred = preselectBatchNo || pmState.selectedBatchNo;
            if (preferred && selector.querySelector(`option[value="${preferred}"]`)) {
                selector.value = preferred;
                pmState.selectedBatchNo = preferred;
            } else {
                pmState.selectedBatchNo = selector.value || null;
            }

            const batchDisplay = document.getElementById('pmBatchDisplay');
            if (batchDisplay) {
                batchDisplay.innerText = pmState.selectedBatchNo || 'N/A';
            }
        } catch (error) {
            console.error('Failed to load printable batches', error);
            selector.innerHTML = '<option value="">Unable to load batch list</option>';
            pmState.selectedBatchNo = null;
        }
    }

    function closePrinterManager() {
        console.log('Closing Printer Manager...');
        const modal = document.getElementById('printerManagerModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
        
        // Reset pmState
        pmState = {
            id: null,
            batchNo: null,
            selectedBatchNo: null,
            fileNo: null,
            mode: 'Individual',
            docType: 'Commissioning Sheet',
            allowed: false
        };
    }

    function setPrintMode(mode) {
        pmState.mode = mode;

        // UI Update
        const indBtn = document.getElementById('pmModeIndividual');
        const batchBtn = document.getElementById('pmModeBatch');
        const dailyBtn = document.getElementById('pmModeDaily24h');

        [indBtn, batchBtn, dailyBtn].forEach((btn) => {
            if (!btn) return;
            btn.classList.remove('bg-gray-100', 'ring-2', 'ring-blue-700', 'text-blue-700', 'z-10');
        });

        if (mode === 'Individual' && indBtn) {
            indBtn.classList.add('bg-gray-100', 'ring-2', 'ring-blue-700', 'text-blue-700', 'z-10');
        } else if (mode === 'Batch' && batchBtn) {
            batchBtn.classList.add('bg-gray-100', 'ring-2', 'ring-blue-700', 'text-blue-700', 'z-10');
        } else if (mode === 'Daily24h' && dailyBtn) {
            dailyBtn.classList.add('bg-gray-100', 'ring-2', 'ring-blue-700', 'text-blue-700', 'z-10');
        }

        const refDisplay = document.getElementById('pmRefDisplay');
        const batchContainer = document.getElementById('pmBatchContainer');
        const batchSelectorContainer = document.getElementById('pmBatchSelectorContainer');
        if (mode === 'Daily24h') {
            if (refDisplay) refDisplay.innerText = 'All Users (24hrs)';
            if (batchContainer) batchContainer.classList.add('hidden');
            if (batchSelectorContainer) batchSelectorContainer.classList.add('hidden');
        } else {
            if (refDisplay) refDisplay.innerText = pmState.fileNo || '--';
            if (batchContainer) {
                if (pmState.batchNo && pmState.batchNo !== 'null' && pmState.batchNo !== 'undefined') {
                    batchContainer.classList.remove('hidden');
                } else {
                    batchContainer.classList.add('hidden');
                }
            }

            if (batchSelectorContainer) {
                if (mode === 'Batch') {
                    batchSelectorContainer.classList.remove('hidden');
                    loadPrintableBatches(pmState.selectedBatchNo || pmState.batchNo || null);
                } else {
                    batchSelectorContainer.classList.add('hidden');
                }
            }
        }

        syncDocTypeForMode();

        checkPrintStatus();
    }

    async function checkPrintStatus() {
        console.log('checkPrintStatus called for state:', pmState);
        const docTypeSelect = document.getElementById('pmDocType');
        if (!docTypeSelect) {
            console.error('pmDocType selector not found!');
            return;
        }
        const docType = docTypeSelect.value;
        pmState.docType = docType;

        if (pmState.mode === 'Batch' && !(pmState.selectedBatchNo || pmState.batchNo)) {
            const btn = document.getElementById('pmExecuteBtn');
            const btnText = document.getElementById('pmBtnText');
            const count = document.getElementById('pmCountDisplay');
            const badge = document.getElementById('pmStatusBadge');
            if (count) count.innerText = '--';
            if (badge) badge.innerText = 'Select Batch';
            if (btn) btn.disabled = true;
            if (btnText) btnText.innerText = 'Select Batch ID';
            pmState.allowed = false;
            return;
        }

        let reference = pmState.mode === 'Batch'
            ? (pmState.selectedBatchNo || pmState.batchNo)
            : (pmState.mode === 'Daily24h' ? DAILY_PRINT_REFERENCE : pmState.fileNo);
        if (!reference || reference === '--' || reference === 'N/A') {
            reference = document.getElementById('pmRefDisplay')?.innerText;
        }
        console.log('Reference for status check:', reference);
        
        if (pmState.mode === 'Individual' && !pmState.fileNo) {
            console.warn('Individual mode but no file number set');
            return;
        }

        // Show loading state
        const btnText = document.getElementById('pmBtnText');
        const executeBtn = document.getElementById('pmExecuteBtn');
        if (btnText) btnText.innerText = 'Checking Status...';
        if (executeBtn) executeBtn.disabled = true;

        try {
            let batchParam = '';
            const batchRef = pmState.selectedBatchNo || pmState.batchNo;
            if (pmState.mode === 'Batch' && batchRef && batchRef !== 'null' && batchRef !== 'undefined') {
                batchParam = `&batch_no=${encodeURIComponent(batchRef)}`;
            }
            
            const url = `/file-numbers/get-print-status?reference=${encodeURIComponent(reference)}&type=${pmState.mode}&doc_type=${encodeURIComponent(docType)}${batchParam}`;
            console.log('Fetching print status from:', url);
            
            const response = await fetch(url);
            const data = await response.json();

            // Update UI
            document.getElementById('pmCountDisplay').innerText = data.count;
            const badge = document.getElementById('pmStatusBadge');
            badge.innerText = data.status;
            pmState.status = data.status; // Store status for use in generation

            if (data.status === 'Original') {
                badge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800';
            } else if (data.status === 'Certified True Copy') {
                badge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800';
            } else {
                badge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800';
            }

            pmState.allowed = data.allowed;

            const btn = document.getElementById('pmExecuteBtn');
            if (data.allowed) {
                btn.disabled = false;
                btn.className = 'w-full py-3 px-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold rounded-lg shadow-lg transform transition hover:scale-[1.02] flex items-center justify-center gap-2';
                document.getElementById('pmBtnText').innerText = data.status === 'Original' ? 'Print Original' : 'Print Certified True Copy';
            } else {
                btn.disabled = true;
                btn.className = 'w-full py-3 px-4 bg-gray-300 text-gray-500 font-bold rounded-lg cursor-not-allowed flex items-center justify-center gap-2';
                document.getElementById('pmBtnText').innerText = 'Print Limit Reached';
            }

        } catch (error) {
            console.error('Error checking print status:', error);
            document.getElementById('pmBtnText').innerText = 'System Error';
        }
    }

    async function executePrintProtocol() {
        if (!pmState.allowed) return;

        if (pmState.mode === 'Batch' && !(pmState.selectedBatchNo || pmState.batchNo)) {
            await Swal.fire({
                icon: 'warning',
                title: 'Batch Required',
                text: 'Please select a batch ID from the dropdown before printing.',
                target: document.getElementById('printerManagerModal') || 'body'
            });
            return;
        }

        // Confirm if creating copy
        const isCopy = document.getElementById('pmBtnText').innerText.includes('Certified True Copy');
        if (isCopy) {
            const result = await Swal.fire({
                title: 'Print Certified True Copy?',
                text: "This will count as your final allowed copy.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Print Copy',
                target: document.getElementById('printerManagerModal') || 'body'
            });
            if (!result.isConfirmed) return;
        }

        // Record Print
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            // Ensure we have a reference. Fallback to DOM display if state is lost.
            let reference = pmState.mode === 'Batch'
                ? (pmState.selectedBatchNo || pmState.batchNo)
                : (pmState.mode === 'Daily24h' ? DAILY_PRINT_REFERENCE : pmState.fileNo);
            if (!reference || reference === '--' || reference === 'N/A') {
                reference = document.getElementById('pmRefDisplay')?.innerText;
            }

            if (!reference || reference === '--' || reference === 'N/A') {
                console.error('Print attempted with invalid reference:', { reference, state: pmState });
                await Swal.fire({
                    icon: 'error',
                    title: 'Reference Missing',
                    text: 'System could not identify the file number. Please close and reopen the Printer Manager.',
                    target: document.getElementById('printerManagerModal') || 'body'
                });
                return;
            }

            const recordResponse = await fetch('/file-numbers/record-print', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    reference: reference,
                    type: pmState.mode,
                    doc_type: pmState.docType
                })
            });

            const recordData = await recordResponse.json();

            if (recordData.success) {
                // Refresh status to reflect the new print
                checkPrintStatus();

                // Trigger PDF Generation
                if (pmState.docType === 'Commissioning Sheet') {
                    if (pmState.mode === 'Batch') {
                        const selectedBatch = pmState.selectedBatchNo || pmState.batchNo;
                        generateBatchPDF(selectedBatch, pmState.status);
                    } else if (pmState.mode === 'Daily24h') {
                        generateDaily24hPDF(pmState.status);
                    } else {
                        // Individual Commissioning Sheet
                        fetchAndGenerateCommissioningSheet(pmState.id, pmState.status);
                    }
                } else {
                    // Conversion Application
                    if (pmState.mode === 'Batch') {
                        generateBatchConversionApplication(pmState.batchNo);
                    } else {
                        // Individual Conversion
                        generateConversionApplication(pmState.id, pmState.fileNo);
                    }
                }

                // Refresh Status
                checkPrintStatus();

                if (pmState.mode === 'Batch') {
                    loadPrintableBatches();
                }

                closePrinterManager();

            } else {
                Swal.fire('Error', recordData.message || 'Could not record print job', 'error');
            }

        } catch (error) {
            console.error('Print execution failed:', error);
            Swal.fire('Error', 'System communication failed', 'error');
        } 
    } 

    // Helper to fetch data and generate individual Commissioning Sheet
    function fetchAndGenerateCommissioningSheet(id, status = 'Original') {
        showGlobalLoading('Preparing Commissioning Sheet...');

        // Use the more robust mls-fileno.show which handles both ID and file number identifiers
        fetch(`{{ route("mls-fileno.show", ":id") }}`.replace(':id', id))
            .then(response => {
                if (!response.ok) {
                    console.error('API responded with error status:', response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Commissioning Sheet data received:', data);
                // Handle both wrapped (success/data) and flat response formats
                const record = (data.success && data.data) ? data.data : data;
                
                if (record && (record.mlsfNo || record.full_file_number || record.mlsf_no || record.kangisFileNo || record.NewKANGISFileNo || record.st_file_no || record.id)) {
                    // map API data to form fields expected by generateCommissioningSheetPDF
                    const formData = new FormData();
                    formData.append('file_number', record.mlsfNo || record.full_file_number || record.mlsf_no || record.kangisFileNo || record.NewKANGISFileNo || record.st_file_no || '');
                    formData.append('file_name', record.FileName || '');
                    formData.append('name_or_allottee', record.FileName || ''); // Auto-filled from file name
                    formData.append('plot_number', record.plot_no || '');
                    formData.append('tp_number', record.tp_no || '');
                    formData.append('location', record.location || '');
                    formData.append('lga', record.lga || '');
                    formData.append('tracking_id', record.tracking_id || '');

                    // Add current time/date/user
                    const now = new Date();
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    formData.append('time_created', `${hours}:${minutes}`);
                    formData.append('date_created', now.toISOString().split('T')[0]);

                    @if(Auth::check())
                        formData.append('created_by', '{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}');
                    @else
                        formData.append('created_by', 'System User');
                    @endif

                    // Generate PDF
                    const watermarkText = (status === 'Certified True Copy') ? 'CERTIFIED TRUE COPY' : 'ORIGINAL';
                    generateCommissioningSheetPDF(formData, watermarkText);
                } else {
                    hideGlobalLoading();
                    const msg = (data && data.message) ? data.message : 'Could not fetch file details';
                    Swal.fire('Error', msg, 'error');
                }
            })
            .catch(error => {
                hideGlobalLoading();
                console.error('Error fetching file details:', error);
                Swal.fire('Error', 'Failed to fetch file details', 'error');
            });
    }

</script>
<script>
    /**
     * Opens the shared Capture Occupancy Permit modal from Commission modal
     * and preselects OP type based on Commission selection.
     */
    function openCommissionOpCaptureModal(selection) {
        const normalized = String(selection || '').toLowerCase() === 'resettlement' ? 'resettlement' : 'direct';

        if (typeof window.openRegistrationDialog !== 'function') {
            Swal.fire({
                icon: 'warning',
                title: 'Capture Modal Unavailable',
                text: 'Capture Occupancy Permit modal is not available on this page.'
            });
            return;
        }

        // Ensure OP-specific flow and labels match OSS OP capture behavior.
        window.ossOpSubmitLabel = 'Capture Existing OP';
        window.ossOpContext = true;
        // Commissioning mirror inserts depend on an existing OP source capture record.
        window.requireOpLookupForCommission = true;
        window.prefillOpTypeFromCommission = normalized;

        // Keep commission modal open; user will return to click Generate.

        window.openRegistrationDialog('occupancy-permit');

        // Fallback preselect in case downstream script has not yet applied it.
        setTimeout(() => {
            const targetId = normalized === 'resettlement' ? 'op_type_resettlement' : 'op_type_direct_allocation';
            const target = document.getElementById(targetId);
            if (target) {
                target.checked = true;
                target.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }, 120);
    }

    window.openCommissionOpCaptureModal = openCommissionOpCaptureModal;

    // STANDALONE BATCH PRINT MODAL
    // =====================================================================

    async function onBatchPrintDateChange() {
        const dateInput = document.getElementById("bpDateSelect");
        const countInfo = document.getElementById("bpCountInfo");
        const countText = document.getElementById("bpCountText");
        const printBtn = document.getElementById("bpPrintBtn");
        const noBatchesNotice = document.getElementById("bpNoBatchesNotice");

        const selectedDate = dateInput ? dateInput.value : "";

        if (!selectedDate) {
            if (countInfo) countInfo.classList.add("hidden");
            if (noBatchesNotice) noBatchesNotice.classList.add("hidden");
            if (printBtn) printBtn.disabled = true;
            return;
        }

        if (countInfo) countInfo.classList.add("hidden");
        if (noBatchesNotice) noBatchesNotice.classList.add("hidden");
        if (printBtn) printBtn.disabled = true;

        try {
            const response = await fetch("{{ route('mls-fileno.batch-records') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
                },
                body: JSON.stringify({ scope: "date", date: selectedDate })
            });

            const payload = await response.json();

            if (!payload.success || !payload.data || payload.data.length === 0) {
                if (noBatchesNotice) noBatchesNotice.classList.remove("hidden");
                return;
            }

            const count = payload.data.length;
            if (countText) countText.textContent = count;
            if (countInfo) countInfo.classList.remove("hidden");
            if (printBtn) printBtn.disabled = false;

        } catch (err) {
            console.error("onBatchPrintDateChange error:", err);
            if (noBatchesNotice) noBatchesNotice.classList.remove("hidden");
        }
    }

    async function openBatchPrintModal() {
        const modal = document.getElementById("batchPrintModal");
        if (modal) {
            modal.classList.remove("hidden");
            modal.style.display = "";
        }
        
        const dateInput = document.getElementById("bpDateSelect");
        if (dateInput && !dateInput.value) {
            const today = new Date().toISOString().split("T")[0];
            dateInput.value = today;
        }
        
        await onBatchPrintDateChange();
        if (typeof lucide !== "undefined") lucide.createIcons();
    }

    function closeBatchPrintModal() {
        const modal = document.getElementById("batchPrintModal");
        if (modal) {
            modal.classList.add("hidden");
            modal.style.display = "none";
        }
        const countInfo = document.getElementById("bpCountInfo");
        if (countInfo) countInfo.classList.add("hidden");
        const printBtn = document.getElementById("bpPrintBtn");
        if (printBtn) printBtn.disabled = true;
    }

    async function executeBatchPrint() {
        const dateInput = document.getElementById("bpDateSelect");
        const selectedDate = dateInput ? dateInput.value : "";

        if (!selectedDate) {
            Swal.fire({ icon: "warning", title: "No Date Selected", text: "Please select a date before printing." });
            return;
        }

        const printBtn = document.getElementById("bpPrintBtn");
        if (printBtn) printBtn.disabled = true;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute("content");
            closeBatchPrintModal();
            
            // Generate the PDF first
            await generateBatchPDF(selectedDate, "Original", "date");

            // ONLY record the print AFTER the PDF has been successfully generated
            const recordResponse = await fetch("/file-numbers/record-print", {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": csrfToken },
                body: JSON.stringify({ reference: selectedDate, type: "Date", doc_type: "Commissioning Sheet" })
            });
            const recordData = await recordResponse.json();

            if (!recordData.success) {
                console.warn("Record print warning:", recordData.message);
            }

        } catch (err) {
            console.error("executeBatchPrint error:", err);
            Swal.fire({ icon: "error", title: "Print Failed", text: "An unexpected error occurred. Please try again." });
            if (printBtn) printBtn.disabled = false;
        }
    }

    window.openBatchPrintModal = openBatchPrintModal;
    window.closeBatchPrintModal = closeBatchPrintModal;
    window.onBatchPrintDateChange = onBatchPrintDateChange;
    window.executeBatchPrint = executeBatchPrint;

    // ==================== CONSOLIDATION REPORT ====================

    /** In-memory store of fetched report data (for export) */
    let _crReportData = [];

    /**
     * Fetch consolidation report data from backend based on current filter values.
     */
    window.fetchConsolidationReport = async function () {
        const dateFrom = document.getElementById('crDateFrom')?.value || '';
        const dateTo   = document.getElementById('crDateTo')?.value || '';
        const fileYear = document.getElementById('crFileYear')?.value || '';
        const prefix   = document.getElementById('crPrefix')?.value || '';

        // Build query string
        const params = new URLSearchParams();
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo)   params.append('date_to', dateTo);
        if (fileYear) params.append('file_year', fileYear);
        if (prefix)   params.append('prefix', prefix);

        const tbody = document.getElementById('crPreviewBody');
        if (!tbody) return;

        // Loading state
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                    <div class="flex flex-col items-center gap-2">
                        <i data-lucide="loader" class="w-8 h-8 text-emerald-500 animate-spin"></i>
                        <span class="text-sm">Fetching consolidation data...</span>
                    </div>
                </td>
            </tr>
        `;
        if (typeof lucide !== 'undefined') lucide.createIcons();

        try {
            const response = await fetch(`{{ route("file-numbers.consolidation-report") }}?${params.toString()}`);
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'Server error');
            }

            _crReportData = result.data || [];
            const count = result.count || 0;

            // Update UI
            document.getElementById('crRecordCount').textContent = `${count} record${count !== 1 ? 's' : ''}`;
            document.getElementById('crExportBtns').classList.remove('hidden');
            document.getElementById('crFilterSummary').classList.remove('hidden');

            // Build summary badge text
            const parts = [];
            if (fileYear) parts.push(`Year: ${fileYear}`);
            if (prefix)   parts.push(`Prefix: ${prefix}`);
            if (dateFrom || dateTo) parts.push(`Date: ${dateFrom || '…'} → ${dateTo || '…'}`);
            document.getElementById('crSummaryBadge').textContent = parts.length ? parts.join(' | ') : 'All Records';

            if (count === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                            <div class="flex flex-col items-center gap-3">
                                <i data-lucide="inbox" class="w-10 h-10 text-gray-300"></i>
                                <span class="text-sm">No records found matching the selected filters</span>
                            </div>
                        </td>
                    </tr>
                `;
                if (typeof lucide !== 'undefined') lucide.createIcons();
                return;
            }

            // Render rows
            let html = '';
            _crReportData.forEach((row, idx) => {
                const createdAt = row.created_at ? new Date(row.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
                html += `
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 text-sm text-gray-500 font-medium">${idx + 1}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-gray-900">${row.full_file_number || '-'}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">${row.file_name || '-'}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200">${row.land_use_full || row.land_use || '-'}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">${row.location || '-'}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">${row.lga || '-'}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">${createdAt}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;

        } catch (error) {
            console.error('Consolidation report error:', error);
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-red-500">
                        <div class="flex flex-col items-center gap-2">
                            <i data-lucide="alert-circle" class="w-8 h-8 text-red-400"></i>
                            <span class="text-sm">Error: ${error.message}</span>
                            <button onclick="fetchConsolidationReport()" class="mt-2 text-emerald-600 hover:text-emerald-700 text-sm font-medium">Try Again</button>
                        </div>
                    </td>
                </tr>
            `;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    };

    /**
     * Reset all consolidation report filters to defaults.
     */
    window.resetConsolidationFilters = function () {
        document.getElementById('crDateFrom').value = '';
        document.getElementById('crDateTo').value = '';
        document.getElementById('crFileYear').value = '{{ date("Y") }}';
        document.getElementById('crPrefix').value = '';
        document.getElementById('crExportBtns').classList.add('hidden');
        document.getElementById('crFilterSummary').classList.add('hidden');

        _crReportData = [];
        document.getElementById('crPreviewBody').innerHTML = `
            <tr>
                <td colspan="7" class="px-6 py-16 text-center text-gray-400">
                    <div class="flex flex-col items-center gap-3">
                        <i data-lucide="file-bar-chart" class="w-10 h-10 text-gray-300"></i>
                        <span class="text-sm">Apply filters and click <strong>"Generate Report"</strong> to preview data</span>
                    </div>
                </td>
            </tr>
        `;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    };

    /**
     * Export the consolidation report as PDF.
     * Layout mirrors the Instrument Export template (ministry header, logos, autoTable).
     */
    window.exportConsolidationPDF = async function () {
        if (!_crReportData || _crReportData.length === 0) {
            Swal.fire({ icon: 'warning', title: 'No Data', text: 'Please generate a report first.' });
            return;
        }

        try {
            showGlobalLoading('Generating PDF...');

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

            // Fetch logos
            const logo1 = await getImageBase64('/assets/logo/logo1.png') || await getImageBase64('/assets/logo/logo1.jpg');
            const logo2 = await getImageBase64('/assets/logo/logo3.jpeg') || await getImageBase64('/assets/logo/las.jpeg');

            // Header
            if (logo1) doc.addImage(logo1, 'JPEG', 15, 8, 18, 18);
            if (logo2) doc.addImage(logo2, 'JPEG', 265, 8, 18, 18);

            doc.setFontSize(14);
            doc.setFont('helvetica', 'bold');
            doc.text('MINISTRY OF LAND & PHYSICAL PLANNING', 148, 14, { align: 'center' });
            doc.setFontSize(11);
            doc.text('DEPARTMENT OF LAND', 148, 20, { align: 'center' });
            doc.setFontSize(10);
            doc.setFont('helvetica', 'normal');
            doc.text('MLS FILE NUMBER CONSOLIDATION REPORT', 148, 27, { align: 'center' });

            // Filter summary line
            const parts = [];
            const fileYear = document.getElementById('crFileYear')?.value;
            const prefix   = document.getElementById('crPrefix')?.value;
            const dateFrom = document.getElementById('crDateFrom')?.value;
            const dateTo   = document.getElementById('crDateTo')?.value;
            if (fileYear) parts.push(`Year: ${fileYear}`);
            if (prefix)   parts.push(`Prefix: ${prefix}`);
            if (dateFrom || dateTo) parts.push(`Date: ${dateFrom || '…'} - ${dateTo || '…'}`);
            if (parts.length) {
                doc.setFontSize(8);
                doc.setTextColor(100);
                doc.text(`Filters: ${parts.join(' | ')}`, 148, 32, { align: 'center' });
                doc.setTextColor(0);
            }

            // Coat of Arms watermark (centered)
            const coatOfArms = await getImageBase64('/assets/logo/Nigerian-Coat-of-Arms.png') || await getImageBase64('/assets/logo/court_of arms.jpeg');
            if (coatOfArms) {
                const pageW = doc.internal.pageSize.getWidth();
                const pageH = doc.internal.pageSize.getHeight();
                const wmSize = 90;
                doc.setGState(doc.GState({ opacity: 0.06 }));
                doc.addImage(coatOfArms, 'PNG', (pageW - wmSize) / 2, (pageH - wmSize) / 2, wmSize, wmSize);
                doc.setGState(doc.GState({ opacity: 1.0 }));
            }

            // Table data
            const tableHead = [['SN', 'File Number', 'File Name / Allottee', 'Land Use', 'Location', 'LGA', 'Date Commissioned']];
            const tableBody = _crReportData.map((row, i) => {
                const d = row.created_at ? new Date(row.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
                return [i + 1, row.full_file_number || '-', row.file_name || '-', row.land_use_full || row.land_use || '-', row.location || '-', row.lga || '-', d];
            });

            doc.autoTable({
                startY: 36,
                head: tableHead,
                body: tableBody,
                styles: { fontSize: 8, cellPadding: 2, lineWidth: 0.1, lineColor: [200, 200, 200] },
                headStyles: {
                    fillColor: [16, 185, 129],
                    textColor: 255,
                    fontStyle: 'bold',
                    fontSize: 8
                },
                alternateRowStyles: { fillColor: [245, 245, 245] },
                margin: { left: 10, right: 10 },
                didDrawPage: function (data) {
                    const pg = doc.internal.pageSize;
                    // Coat of Arms watermark on every page
                    if (coatOfArms) {
                        const wmSize = 90;
                        doc.setGState(doc.GState({ opacity: 0.06 }));
                        doc.addImage(coatOfArms, 'PNG', (pg.getWidth() - wmSize) / 2, (pg.getHeight() - wmSize) / 2, wmSize, wmSize);
                        doc.setGState(doc.GState({ opacity: 1.0 }));
                    }
                    // Footer on every page
                    const pageCount = doc.internal.getNumberOfPages();
                    doc.setFontSize(7);
                    doc.setTextColor(150);
                    doc.text(
                        `Generated on ${new Date().toLocaleString()} | Page ${data.pageNumber} of ${pageCount} | Total Records: ${_crReportData.length}`,
                        148, pg.getHeight() - 8,
                        { align: 'center' }
                    );
                }
            });

            hideGlobalLoading();

            const filename = `consolidation-report${fileYear ? '-' + fileYear : ''}${prefix ? '-' + prefix : ''}.pdf`;
            doc.save(filename);

            Swal.fire({
                icon: 'success',
                title: 'PDF Generated',
                html: `Report exported successfully.<br><strong>${_crReportData.length}</strong> record${_crReportData.length !== 1 ? 's' : ''}.`,
                confirmButtonColor: '#10b981'
            });
        } catch (error) {
            hideGlobalLoading();
            console.error('PDF export error:', error);
            Swal.fire({ icon: 'error', title: 'PDF Error', text: 'Failed to generate PDF: ' + error.message });
        }
    };

    /**
     * Export the consolidation report as Excel (CSV download).
     */
    window.exportConsolidationExcel = function () {
        if (!_crReportData || _crReportData.length === 0) {
            Swal.fire({ icon: 'warning', title: 'No Data', text: 'Please generate a report first.' });
            return;
        }

        try {
            const headers = ['SN', 'File Number', 'File Name', 'Land Use', 'Customer Type', 'Location', 'LGA', 'Source', 'Commissioned By', 'Date'];
            const csvRows = [headers.join(',')];

            _crReportData.forEach((row, i) => {
                const d = row.created_at ? new Date(row.created_at).toLocaleDateString('en-GB') : '';
                const line = [
                    i + 1,
                    `"${(row.full_file_number || '').replace(/"/g, '""')}"`,
                    `"${(row.file_name || '').replace(/"/g, '""')}"`,
                    `"${(row.land_use_full || row.land_use || '').replace(/"/g, '""')}"`,
                    `"${(row.customer_type || '').replace(/"/g, '""')}"`,
                    `"${(row.location || '').replace(/"/g, '""')}"`,
                    `"${(row.lga || '').replace(/"/g, '""')}"`,
                    `"${(row.source || '').replace(/"/g, '""')}"`,
                    `"${(row.created_by || '').replace(/"/g, '""')}"`,
                    d
                ];
                csvRows.push(line.join(','));
            });

            const csvContent = csvRows.join('\n');
            const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);

            const fileYear = document.getElementById('crFileYear')?.value || '';
            const prefix   = document.getElementById('crPrefix')?.value || '';
            link.download = `consolidation-report${fileYear ? '-' + fileYear : ''}${prefix ? '-' + prefix : ''}.csv`;
            link.click();
            URL.revokeObjectURL(link.href);

            Swal.fire({
                icon: 'success',
                title: 'Excel/CSV Exported',
                html: `<strong>${_crReportData.length}</strong> record${_crReportData.length !== 1 ? 's' : ''} exported successfully.`,
                confirmButtonColor: '#10b981'
            });
        } catch (error) {
            console.error('Excel export error:', error);
            Swal.fire({ icon: 'error', title: 'Export Error', text: 'Failed to export: ' + error.message });
        }
    };

</script>
