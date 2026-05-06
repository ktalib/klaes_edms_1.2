/**
 * Track File Archive JavaScript
 * Handles file number selection, file details display, and archive search functionality
 */

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    initializeGlobalFileNoModal();
    initializeLucideIcons();
});

/**
 * Initialize Global File Number Modal integration
 */
function initializeGlobalFileNoModal() {
    if (typeof GlobalFileNoModal === 'undefined') {
        console.error('GlobalFileNoModal is not loaded');
        return;
    }

    GlobalFileNoModal.init();
    
    // Set up the file number selector button
    const selectorBtn = document.getElementById('archive-fileno-selector-btn');
    if (selectorBtn) {
        selectorBtn.addEventListener('click', function() {
            GlobalFileNoModal.open({
                targetFields: ['archive-file-no'],
                callback: handleFileNumberSelection
            });
        });
    }
}

/**
 * Handle file number selection from modal
 * @param {Object} data - Data from modal callback
 */
function handleFileNumberSelection(data) {
    console.log('Modal callback data:', data);
    
    if (!data || !data.fileNumber) {
        console.warn('No file number selected');
        return;
    }

    // Reset details before populating fresh data
    resetFileDetails();

    // Set file number
    const fileNoInput = document.getElementById('archive-file-no');
    if (fileNoInput) {
        fileNoInput.value = data.fileNumber;
        
        // Show file number as bold green badge
        showFileNumberBadge(data.fileNumber);
    }

    // Set registry to hardcoded value "3"
    updateBadge('detail-registry', '3', 'purple');

    // Derive land use from file number if record data is unavailable
    applyDerivedLandUseFromFileNumber(data.fileNumber);
    
    // Set file name from record data
    const fileNameInput = document.getElementById('archive-file-name');
    if (fileNameInput && data.record) {
        // Try multiple possible field names for file name
        const fileName = data.record.file_name 
            || data.record.fileName 
            || data.record.file_title 
            || data.record.fileTitle
            || data.record.name
            || data.record.title
            || '';
        
        if (fileName) {
            fileNameInput.value = fileName;
            fileNameInput.classList.remove('text-gray-500');
            fileNameInput.classList.add('text-gray-900');
        } else {
            // File number found but no name - try to fetch from API
            fetchFileNameFromApi(data.fileNumber);
        }
        
        // Update file details in second card
        updateFileDetails(data.record);
    } else if (fileNameInput) {
        // No record data - try to fetch from API
        fetchFileNameFromApi(data.fileNumber);
    }
    
    console.log('File number selected:', data.fileNumber);

    fetchFileTrackerHistory(data.fileNumber);
    fetchLabelMetadata(data.fileNumber);
}

/**
 * Fetch and display label metadata for a file number
 * @param {string} fileNumber - The file number to fetch metadata for
 */
async function fetchLabelMetadata(fileNumber) {
    console.log('Fetching metadata for file number:', fileNumber);
    
    try {
        // Show loading state
        updateBadge('detail-tracking-id', 'Loading...', 'gray');
        updateBadge('detail-land-use', 'Loading...', 'gray');
        updateBadge('detail-shelf', 'Loading...', 'gray');
        updateBadge('detail-rack', 'Loading...', 'gray');
        updateBadge('detail-full-shelf-rack', 'Loading...', 'gray');

        const response = await fetch(`/api/track-file-archive/label-metadata?file_number=${encodeURIComponent(fileNumber)}`);
        console.log('API response status:', response.status);
        console.log('API response headers:', response.headers);
        
        if (!response.ok) {
            console.error('API response not ok:', response.status, response.statusText);
            const errorText = await response.text();
            console.error('API error body:', errorText);
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        console.log('Raw API response data:', data);
        console.log('API response success:', data.success);
        console.log('API response data:', data.data);
        console.log('API response message:', data.message);

        if (data.success) {
            applyLabelMetadata(data.data);
        } else {
            console.warn('API returned success=false:', data.message);
            resetBadgesToNA();
        }
    } catch (error) {
        console.error('Error fetching label metadata:', error);
        resetBadgesToNA();
    }
}

/**
 * Reset all badges to N/A
 */
function resetBadgesToNA() {
    resetBadgeIfLoading('detail-tracking-id');
    resetBadgeIfLoading('detail-land-use');
    resetBadgeIfLoading('detail-shelf');
    resetBadgeIfLoading('detail-rack');
    resetBadgeIfLoading('detail-full-shelf-rack');
}

/**
 * Reset badge text if it is still in a loading or empty state
 * @param {string} elementId - Badge element identifier
 */
function resetBadgeIfLoading(elementId) {
    const badge = document.getElementById(elementId);
    if (!badge) {
        return;
    }

    const current = (badge.textContent || '').trim().toLowerCase();
    if (current === '' || current === 'loading...' || current === 'n/a' || current === 'not selected') {
        updateBadge(elementId, 'N/A', 'gray');
    }
}

/**
 * Fetch file name from API
 * @param {string} fileNumber - File number to lookup
 */
function fetchFileNameFromApi(fileNumber) {
    const fileNameInput = document.getElementById('archive-file-name');
    if (!fileNameInput || !fileNumber) return;
    
    // Show loading state
    fileNameInput.value = 'Loading...';
    fileNameInput.classList.add('text-gray-500');
    
    // Fetch file details from file indexing API
    fetch(`/api/file-indexings/lookup-by-number?file_number=${encodeURIComponent(fileNumber)}`)
        .then(response => response.json())
        .then(data => {
            if (data && data.success && data.data) {
                const fileName = data.data.file_name || data.data.file_title || '';
                if (fileName) {
                    fileNameInput.value = fileName;
                    fileNameInput.classList.remove('text-gray-500');
                    fileNameInput.classList.add('text-gray-900');
                } else {
                    fileNameInput.value = '';
                    fileNameInput.classList.add('text-gray-500');
                }
                
                // Update file details in second card
                updateFileDetails(data.data);

                fetchLabelMetadata(fileNumber);
                fetchFileTrackerHistory(fileNumber);
            } else {
                fileNameInput.value = '';
                fileNameInput.classList.add('text-gray-500');
            }
        })
        .catch(error => {
            console.error('Error fetching file name:', error);
            fileNameInput.value = '';
            fileNameInput.classList.add('text-gray-500');
        });
}

/**
 * Update file details in the second card
 * @param {Object} record - File record data
 */
function updateFileDetails(record) {
    if (!record) {
        return;
    }

    const trackingId = record.tracking_id || record.trackingId || null;
    if (trackingId) {
        updateBadge('detail-tracking-id', trackingId, 'blue');
    }

    const landUse = record.land_use || record.landUse || null;
    if (landUse) {
        updateBadge('detail-land-use', String(landUse).toUpperCase(), getLandUseColor(landUse));
    }

    const fileType = record.file_type || record.fileType || record.type || null;
    if (fileType) {
        updateBadge('detail-file-type', fileType, 'purple');
    }

    const shelf = record.shelf || record.shelf_number || record.shelfNumber || null;
    if (shelf) {
        updateBadge('detail-shelf', shelf, 'amber');
    }

    const rack = record.rack || record.rack_number || record.rackNumber || null;
    if (rack) {
        updateBadge('detail-rack', rack, 'orange');
    }

    const locationCode = record.full_shelf_rack || record.fullShelfRack || record.location_code || null;
    if (locationCode) {
        updateBadge('detail-full-shelf-rack', locationCode, 'indigo');
    } else if (shelf && rack) {
        updateBadge('detail-full-shelf-rack', `${shelf}${rack}`, 'indigo');
    }

    const currentLocation = record.current_location
        || record.currentLocation
        || record.location
        || record.office_location
        || record.officeLocation
        || null;
    if (currentLocation) {
        updateBadge('detail-current-location', currentLocation, 'cyan');
    }

    const status = record.status || null;
    if (status) {
        updateBadge('detail-status', status, getStatusColor(status));
    }
}

/**
 * Update a badge element
 * @param {string} elementId - Element ID
 * @param {string} text - Text to display
 * @param {string} color - Color theme (blue, green, purple, etc.)
 */
function updateBadge(elementId, text, color) {
    const badge = document.getElementById(elementId);
    if (!badge) return;
    
    const resolvedText = text === undefined || text === null || text === ''
        ? 'N/A'
        : String(text);

    badge.textContent = resolvedText;
    
    const isNA = resolvedText === 'N/A' || resolvedText === 'Not Selected';
    const colorClass = isNA ? 'gray-400' : `${color}-600`;
    
    badge.className = `inline-flex items-center justify-center px-2 py-1 rounded-full text-xs font-semibold bg-${colorClass} text-white`;
}

/**
 * Get color for land use type
 * @param {string} landUse - Land use type
 * @returns {string} Color name
 */
function getLandUseColor(landUse) {
    const landUseUpper = landUse.toUpperCase();
    
    switch(landUseUpper) {
        case 'RESIDENTIAL':
        case 'RES':
            return 'green';
        case 'COMMERCIAL':
        case 'COM':
            return 'blue';
        case 'INDUSTRIAL':
        case 'IND':
            return 'orange';
        case 'AGRICULTURAL':
        case 'AG':
            return 'yellow';
        default:
            return 'gray';
    }
}

/**
 * Get color for status
 * @param {string} status - Status value
 * @returns {string} Color name
 */
function getStatusColor(status) {
    const statusLower = status.toLowerCase();
    
    switch(statusLower) {
        case 'active':
            return 'green';
        case 'archived':
            return 'indigo';
        case 'pending':
            return 'yellow';
        default:
            return 'gray';
    }
}

/**
 * Show file number as bold green badge
 * @param {string} fileNumber - File number to display
 */
function showFileNumberBadge(fileNumber) {
    const badgeContainer = document.getElementById('file-number-badge-container');
    const badge = document.getElementById('file-number-badge');
    
    if (badgeContainer && badge && fileNumber) {
        badge.textContent = fileNumber;
        badgeContainer.classList.remove('hidden');
    }
}

/**
 * Hide file number badge
 */
function hideFileNumberBadge() {
    const badgeContainer = document.getElementById('file-number-badge-container');
    const badge = document.getElementById('file-number-badge');
    
    if (badgeContainer && badge) {
        badge.textContent = '';
        badgeContainer.classList.add('hidden');
    }
}

/**
 * Reset file details to default values
 */
function resetFileDetails() {
    hideFileNumberBadge();
    updateBadge('detail-tracking-id', 'Not Selected', 'gray');
    updateBadge('detail-land-use', 'N/A', 'gray');
    updateBadge('detail-registry', '-', 'gray');
    updateBadge('detail-shelf', 'N/A', 'gray');
    updateBadge('detail-rack', 'N/A', 'gray');
    updateBadge('detail-full-shelf-rack', 'N/A', 'gray');
    updateBadge('detail-current-location', 'N/A', 'gray');
    updateBadge('detail-status', 'Ready', 'gray');
    resetFileHistoryTimeline();
}

/**
 * Derive land use from the file number structure
 * @param {string} fileNumber - Selected file number
 * @returns {{code: string, label: string}|null}
 */
function deriveLandUseFromFileNumber(fileNumber) {
    if (!fileNumber) {
        return null;
    }

    const landUseLookup = {
        RES: 'Residential',
        RESIDENTIAL: 'Residential',
        COM: 'Commercial',
        COMMERCIAL: 'Commercial',
        IND: 'Industrial',
        INDUSTRIAL: 'Industrial',
        AG: 'Agricultural',
        AGR: 'Agricultural',
        AGRICULTURAL: 'Agricultural',
        MIX: 'Mixed',
        MIXED: 'Mixed'
    };

    const safeSegments = String(fileNumber)
        .toUpperCase()
        .split(/[-\s_\/\\]+/)
        .filter(Boolean);

    for (const segment of safeSegments) {
        if (landUseLookup[segment]) {
            return { code: segment, label: landUseLookup[segment] };
        }

        const alphaOnly = segment.replace(/[^A-Z]/g, '');
        if (alphaOnly && landUseLookup[alphaOnly]) {
            return { code: alphaOnly, label: landUseLookup[alphaOnly] };
        }
    }

    return null;
}

/**
 * Apply derived land use to the UI if no explicit land use is present
 * @param {string} fileNumber - Selected file number
 */
function applyDerivedLandUseFromFileNumber(fileNumber) {
    const landUse = deriveLandUseFromFileNumber(fileNumber);
    if (!landUse) {
        return;
    }

    const badge = document.getElementById('detail-land-use');
    if (!badge) {
        return;
    }

    const currentValue = badge.textContent ? badge.textContent.trim().toUpperCase() : '';
    const shouldUpdate = !currentValue
        || currentValue === 'N/A'
        || currentValue === 'NOT SELECTED'
        || currentValue === 'LOADING...';

    if (!shouldUpdate) {
        return;
    }

    const displayLabel = landUse.label.toUpperCase();
    updateBadge('detail-land-use', displayLabel, getLandUseColor(displayLabel));
}

/**
 * Fetch file tracker history and update status indicator
 * @param {string} fileNumber - File number to query
 */
function fetchFileTrackerHistory(fileNumber) {
    if (!fileNumber) {
        return;
    }

    updateBadge('detail-status', 'Loading history...', 'gray');
    updateBadge('detail-current-location', 'Loading...', 'gray');
    setFileHistoryTimelineLoading();

    const params = new URLSearchParams({
        file_numbers: fileNumber,
        sort_by: 'updated_at',
        sort_order: 'desc',
        per_page: '10'
    });

    fetch(`/api/file-trackers?${params.toString()}`)
        .then(async (response) => {
            let payload = null;

            try {
                payload = await response.json();
            } catch (error) {
                console.error('Invalid JSON from file tracker endpoint', error);
            }

            if (!response.ok) {
                if (payload && payload.message) {
                    console.info('File tracker history not available:', payload.message);
                }
                setTrackerFallbackState('Tracker unavailable');
                return;
            }

            const paginated = payload && payload.data ? payload.data : null;
            const trackers = Array.isArray(paginated?.data) ? paginated.data : [];

            if (trackers.length === 0) {
                setTrackerFallbackState('No tracker history');
                return;
            }

            const normalizedFileNumber = String(fileNumber).toUpperCase();
            const matchingTracker = trackers.find((item) => {
                const candidate = (item?.file_number || '').toString().toUpperCase();
                return candidate === normalizedFileNumber;
            }) || trackers[0];

            applyFileTrackerDetails(matchingTracker);
        })
        .catch((error) => {
            console.error('Error fetching file tracker history:', error);
            setTrackerFallbackState('Tracker unavailable');
        });
}

/**
 * Apply tracker details to UI badges
 * @param {Object} tracker - Tracker record from API
 */
function applyFileTrackerDetails(tracker) {
    if (!tracker || typeof tracker !== 'object') {
        setTrackerFallbackState('No tracker history');
        return;
    }

    const statusLabel = formatStatusText(tracker.status || tracker.assignment_status || 'Active');
    const isOverdue = Boolean(tracker.is_overdue);

    const movementLog = normalizeMovementLog(tracker.movement_log);
    const latestMovement = getLatestMovementEntry(movementLog);

    let locationLabel = tracker.current_office_name
        || tracker.receiving_office_name
        || (latestMovement && (latestMovement.receiving_office_name || latestMovement.office_name))
        || null;

    if (!locationLabel && tracker.department) {
        locationLabel = tracker.department;
    }

    const movementStatus = latestMovement ? formatStatusText(latestMovement.status || '') : '';
    const composedStatus = [statusLabel, movementStatus, locationLabel]
        .filter(Boolean)
        .reduce((acc, part, index) => {
            if (index === 0) {
                return part;
            }
            if (acc.includes(part)) {
                return acc;
            }
            return `${acc} • ${part}`;
        }, '');

    const trackerColor = getTrackerStatusBadgeColor(statusLabel, isOverdue, movementStatus);

    updateBadge('detail-status', composedStatus || statusLabel, trackerColor);

    if (locationLabel) {
        updateBadge('detail-current-location', locationLabel, 'cyan');
    } else {
        updateBadge('detail-current-location', 'N/A', 'gray');
    }

    const trackingId = tracker.tracking_id || tracker.trackingId;
    if (trackingId) {
        updateBadge('detail-tracking-id', trackingId, 'blue');
    }

    renderFileHistoryTimeline(tracker, movementLog);
}

/**
 * Normalize movement log entries to an array
 * @param {*} movementLog - Raw movement log value
 * @returns {Array<Object>} Array of movement entries
 */
function normalizeMovementLog(movementLog) {
    if (!movementLog) {
        return [];
    }

    if (Array.isArray(movementLog)) {
        return movementLog;
    }

    if (typeof movementLog === 'string') {
        const trimmed = movementLog.trim();
        if (!trimmed) {
            return [];
        }
        try {
            const parsed = JSON.parse(trimmed);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            console.error('Unable to parse movement log string', error);
        }
    }

    return [];
}

/**
 * Select the latest movement entry based on timestamp or date
 * @param {Array<Object>} movementLog - Movement entries
 * @returns {Object|null} Latest movement entry
 */
function getLatestMovementEntry(movementLog) {
    if (!Array.isArray(movementLog) || movementLog.length === 0) {
        return null;
    }

    let latest = null;
    let latestValue = -Infinity;

    movementLog.forEach((entry) => {
        const timestamp = resolveMovementTimestamp(entry);
        if (timestamp > latestValue) {
            latestValue = timestamp;
            latest = entry;
        }
    });

    return latest;
}

/**
 * Convert movement entry timestamps to comparable numeric value
 * @param {Object} entry - Movement entry
 * @returns {number} Epoch milliseconds fallback to 0
 */
function resolveMovementTimestamp(entry) {
    if (!entry || typeof entry !== 'object') {
        return 0;
    }

    const tsCandidates = [
        entry.timestamp,
        entry.updated_at,
        entry.created_at,
        entry.log_in_date ? `${entry.log_in_date}T${entry.log_in_time || '00:00'}` : null
    ];

    for (const value of tsCandidates) {
        if (!value) {
            continue;
        }

        const parsed = Date.parse(value);
        if (!Number.isNaN(parsed)) {
            return parsed;
        }
    }

    return 0;
}

/**
 * Format status label text for display
 * @param {string} status - Raw status string
 * @returns {string} Human-readable status
 */
function formatStatusText(status) {
    const safeStatus = String(status || '').trim();
    if (!safeStatus) {
        return '';
    }

    return safeStatus
        .toLowerCase()
        .split(/[_\s-]+/)
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

/**
 * Determine tracker status badge color
 * @param {string} statusLabel - Normalized status label
 * @param {boolean} isOverdue - Whether tracker is overdue
 * @param {string} movementStatus - Latest movement status
 * @returns {string} Tailwind color keyword
 */
function getTrackerStatusBadgeColor(statusLabel, isOverdue, movementStatus) {
    if (isOverdue) {
        return 'red';
    }

    const status = statusLabel.toLowerCase();
    const movement = (movementStatus || '').toLowerCase();

    if (movement.includes('pending')) {
        return 'amber';
    }

    switch (status) {
        case 'active':
            return 'green';
        case 'completed':
        case 'archived':
            return 'indigo';
        case 'cancelled':
        case 'closed':
            return 'gray';
        case 'on hold':
        case 'pending':
            return 'yellow';
        default:
            return 'gray';
    }
}

/**
 * Apply fallback status when tracker data is unavailable
 * @param {string} message - Message to display in status badge
 */
function setTrackerFallbackState(message) {
    updateBadge('detail-status', message || 'No tracker history', 'gray');
    updateBadge('detail-current-location', 'N/A', 'gray');
    setFileHistoryTimelineFallback(message || 'No tracker history');
}

/**
 * Render movement history timeline inside the details card
 * @param {Object|null} tracker - Tracker record containing metadata
 * @param {Array<Object>} movementLog - Ordered movement entries
 */
function renderFileHistoryTimeline(tracker, movementLog) {
    const container = document.getElementById('file-history-timeline');
    const lastUpdatedEl = document.getElementById('file-history-last-updated');

    if (!container) {
        return;
    }

    container.innerHTML = '';

    if (!Array.isArray(movementLog) || movementLog.length === 0) {
        setFileHistoryTimelineFallback('No movement history available.');
        if (lastUpdatedEl) {
            lastUpdatedEl.textContent = 'Last updated: --';
        }
        return;
    }

    const sortedLog = [...movementLog].sort((a, b) => resolveMovementTimestamp(b) - resolveMovementTimestamp(a));

    sortedLog.forEach((entry, index) => {
        container.appendChild(buildTimelineEntry(entry, index === 0));
    });

    if (lastUpdatedEl) {
        const updatedSource = tracker && (tracker.updated_at || tracker.date_created || tracker.created_at);
        const formatted = formatDateTimeDisplay(updatedSource);
        lastUpdatedEl.textContent = formatted ? `Last updated: ${formatted}` : 'Last updated: --';
    }
}

function buildTimelineEntry(entry, isLatest) {
    const wrapper = document.createElement('div');
    wrapper.className = 'relative';

    const marker = document.createElement('span');
    const markerClasses = ['absolute', '-left-6', 'top-1', 'h-3', 'w-3', 'rounded-full', 'border-2', 'shadow-sm'];
    if (isLatest) {
        markerClasses.push('bg-blue-500', 'border-blue-200');
    } else {
        markerClasses.push('bg-white', 'border-slate-300');
    }
    marker.className = markerClasses.join(' ');
    wrapper.appendChild(marker);

    const headerRow = document.createElement('div');
    headerRow.className = 'flex flex-wrap items-center gap-2';

    const officeName = entry.receiving_office_name || entry.office_name || 'Unknown Office';
    const officeEl = document.createElement('span');
    officeEl.className = 'text-sm font-semibold text-gray-900';
    officeEl.textContent = officeName;
    headerRow.appendChild(officeEl);

    const statusLabel = formatStatusText(entry.status || '');
    if (statusLabel) {
        headerRow.appendChild(createTimelineStatusBadge(statusLabel));
    }

    if (isLatest) {
        const latestTag = document.createElement('span');
        latestTag.className = 'text-xs font-semibold uppercase tracking-wide text-blue-600';
        latestTag.textContent = 'Most Recent';
        headerRow.appendChild(latestTag);
    }

    const timeLabel = formatMovementEntryDateTime(entry);
    if (timeLabel) {
        const timeEl = document.createElement('span');
        timeEl.className = 'text-xs uppercase tracking-wide text-gray-500';
        timeEl.textContent = timeLabel;
        headerRow.appendChild(timeEl);
    }

    wrapper.appendChild(headerRow);

    const metaParts = [];
    if (entry.origin_office_name) {
        metaParts.push(`Origin: ${entry.origin_office_name}`);
    }
    if (entry.receiving_officer_name) {
        metaParts.push(`Assigned to: ${entry.receiving_officer_name}`);
    }
    if (entry.user_name) {
        metaParts.push(`Logged by ${entry.user_name}`);
    }
    if (entry.manual_update && entry.manual_update_at) {
        const manualStamp = formatDateTimeDisplay(entry.manual_update_at);
        metaParts.push(`Manual update: ${manualStamp || entry.manual_update_at}`);
    }

    if (metaParts.length > 0) {
        const metaEl = document.createElement('div');
        metaEl.className = 'text-xs text-gray-500';
        metaEl.textContent = metaParts.join(' • ');
        wrapper.appendChild(metaEl);
    }

    const notes = (entry.notes || '').trim();
    if (notes) {
        const notesEl = document.createElement('div');
        notesEl.className = 'text-xs text-gray-600 italic';
        notesEl.textContent = `Notes: ${notes}`;
        wrapper.appendChild(notesEl);
    }

    return wrapper;
}

function createTimelineStatusBadge(label) {
    const badge = document.createElement('span');
    badge.textContent = label;

    const badgeClasses = ['inline-flex', 'items-center', 'rounded-full', 'px-2', 'py-0.5', 'text-xs', 'font-medium', 'border'];
    badgeClasses.push(...getTimelineStatusBadgeColor(label));
    badge.className = badgeClasses.join(' ');

    return badge;
}

function getTimelineStatusBadgeColor(label) {
    const safe = (label || '').toLowerCase();

    if (safe.includes('pending')) {
        return ['bg-amber-100', 'text-amber-700', 'border-amber-200'];
    }
    if (safe.includes('complete') || safe.includes('accept') || safe.includes('receive')) {
        return ['bg-green-100', 'text-green-700', 'border-green-200'];
    }
    if (safe.includes('reject') || safe.includes('cancel')) {
        return ['bg-red-100', 'text-red-700', 'border-red-200'];
    }

    return ['bg-slate-100', 'text-slate-700', 'border-slate-200'];
}

function formatMovementEntryDateTime(entry) {
    const candidates = [
        entry.log_in_date ? `${entry.log_in_date}T${entry.log_in_time || '00:00'}` : null,
        entry.timestamp,
        entry.updated_at,
        entry.created_at
    ];

    for (const candidate of candidates) {
        const formatted = formatDateTimeDisplay(candidate);
        if (formatted) {
            return formatted;
        }
    }

    return null;
}

function formatDateTimeDisplay(value) {
    if (!value) {
        return null;
    }

    const parsed = Date.parse(value);
    if (Number.isNaN(parsed)) {
        return null;
    }

    const date = new Date(parsed);

    try {
        return date.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
    } catch (error) {
        // Fall back to ISO-style formatting if locale conversion fails
    }

    return date.toISOString().replace('T', ' ').slice(0, 16);
}

function setFileHistoryTimelineLoading() {
    const container = document.getElementById('file-history-timeline');
    const lastUpdatedEl = document.getElementById('file-history-last-updated');

    if (!container) {
        return;
    }

    container.innerHTML = '';

    const loadingEl = document.createElement('div');
    loadingEl.className = 'text-sm text-gray-500';
    loadingEl.textContent = 'Loading movement history...';
    container.appendChild(loadingEl);

    if (lastUpdatedEl) {
        lastUpdatedEl.textContent = 'Last updated: --';
    }
}

function resetFileHistoryTimeline(message) {
    setFileHistoryTimelineFallback(message || 'Select a file to view movement history.');
}

function setFileHistoryTimelineFallback(message) {
    const container = document.getElementById('file-history-timeline');
    const lastUpdatedEl = document.getElementById('file-history-last-updated');

    if (!container) {
        return;
    }

    container.innerHTML = '';

    const placeholderEl = document.createElement('div');
    placeholderEl.className = 'text-sm text-gray-500';
    placeholderEl.textContent = message || 'No movement history available.';
    container.appendChild(placeholderEl);

    if (lastUpdatedEl) {
        lastUpdatedEl.textContent = 'Last updated: --';
    }
}

/**
 * Initialize Lucide icons
 */
function initializeLucideIcons() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

/**
 * Archive search functionality (placeholder)
 */
function searchArchive() {
    const fileNumber = document.getElementById('archive-file-no').value;
    
    if (!fileNumber) {
        alert('Please enter or select a file number');
        return;
    }
    
    console.log('Searching archive for:', fileNumber);
    // TODO: Implement archive search functionality
}

/**
 * Apply label metadata to badge fields
 * @param {Object} metadata - Label metadata response
 */
function applyLabelMetadata(metadata) {
    console.log('Applying metadata:', metadata);
    
    if (!metadata || typeof metadata !== 'object') {
        console.warn('Invalid metadata object:', metadata);
        return;
    }

    // Force update land use from either source
    const rawLandUse = metadata.land_use_type_label
        || metadata.land_use_type
        || (metadata.raw_record && metadata.raw_record.land_use_type)
        || (metadata.qr_code_payload && (metadata.qr_code_payload.land_use_type || metadata.qr_code_payload.landUseType))
        || null;

    console.log('Raw land use found:', rawLandUse);

    if (rawLandUse) {
        const safeLandUse = String(rawLandUse).trim();
        if (safeLandUse !== '') {
            console.log('Updating land use badge with:', safeLandUse.toUpperCase());
            updateBadge('detail-land-use', safeLandUse.toUpperCase(), getLandUseColor(safeLandUse));
        }
    }

    // Force update tracking ID
    const trackingId = metadata.tracking_id
        || (metadata.raw_record && metadata.raw_record.tracking_id)
        || (metadata.qr_code_payload && metadata.qr_code_payload.tracking_id)
        || 'N/A';
    
    console.log('Tracking ID found:', trackingId);
    if (trackingId && trackingId !== 'N/A') {
        updateBadge('detail-tracking-id', trackingId, 'blue');
    }

    // Force update shelf location data
    const shelfLocationLabel = metadata.shelf_location_label
        || (metadata.raw_record && metadata.raw_record.shelf_location)
        || (metadata.qr_code_payload && (metadata.qr_code_payload.shelf_location_label || metadata.qr_code_payload.shelfLocationLabel))
        || null;

    console.log('Shelf location found:', shelfLocationLabel);

    const normalizedShelfLocation = metadata.shelf_location
        || (shelfLocationLabel ? String(shelfLocationLabel).replace(/[\s\/\\-]+/g, '').toUpperCase() : null)
        || null;

    const shelf = metadata.shelf
        || (normalizedShelfLocation ? normalizedShelfLocation.charAt(0) : null);
    console.log('Shelf found:', shelf);
    if (shelf) {
        updateBadge('detail-shelf', shelf, 'amber');
    }

    const rack = metadata.rack
        || (normalizedShelfLocation && normalizedShelfLocation.length > 1
            ? normalizedShelfLocation.substring(1)
            : null);
    console.log('Rack found:', rack);
    if (rack) {
        updateBadge('detail-rack', rack, 'orange');
    }

    const fullShelfRack = normalizedShelfLocation
        || (shelfLocationLabel ? String(shelfLocationLabel).trim() : null)
        || (shelf && rack ? `${shelf}${rack}` : null);

    console.log('Full shelf rack found:', fullShelfRack);
    if (fullShelfRack) {
        updateBadge('detail-full-shelf-rack', fullShelfRack, 'indigo');
    }
}
