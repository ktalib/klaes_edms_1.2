
        let fileTrackers = [];
        let officeData = {};
        let isLoading = true;

        async function fetchDashboardData() {
            try {
                const response = await fetch('/api/file-tracker-dashboard/overview-commissioner');
                const result = await response.json();
                if (result.success) {
                    fileTrackers = result.data.trackers;
                    
                    fileTrackers.forEach(t => {
                        if (t.currentOfficeId) {
                            officeData[t.currentOfficeId] = {
                                name: t.currentOffice,
                                code: t.currentOfficeId,
                                department: t.currentOfficeDepartment || t.department
                            };
                        }
                    });
                    
                    isLoading = false;
                    updateDashboardStats();
                    updateFilesTable();
                    updateIdleStats();
                    updateIdleTable();
                    updateRequestedTable();
                    if (typeof chartsVisible !== 'undefined' && chartsVisible) updateCharts();
                }
            } catch (error) {
                console.error('Error fetching dashboard data:', error);
                isLoading = false;
            }
        }
let currentDetailsTracker = null;
        let filteredTrackers = [];
        let priorityChart = null;
        let officeChart = null;
        let chartsVisible = false;
        let currentTab = 'requested';

        // Separate files against the Create File Tracker workflow.
        //   • In Transit  = file logged OUT to a destination office (request purpose /
        //     file request type = "In-Transit") and not yet returned.
        //   • Not in Transit = file logged back IN to the registry (status COMPLETED),
        //     plus any cancelled files that are no longer moving.
        // Classification is computed on the backend (movementStatus / isInTransit /
        // isReturned) so the dashboard matches the File Tracker Log exactly.
        function separateFiles() {
            const transit = [];
            const idle = [];
            fileTrackers.forEach(tracker => {
                if (tracker.isInTransit || tracker.movementStatus === 'in_transit') {
                    transit.push(tracker);
                } else if (tracker.isReturned || tracker.isCanceled ||
                           tracker.movementStatus === 'returned' || tracker.movementStatus === 'canceled') {
                    idle.push(tracker);
                }
            });
            return { transit, idle };
        }

        // Get requested files (logged out for a real request purpose, not plain movement)
        function getRequestedFiles() {
            return fileTrackers.filter(tracker => tracker.isRequested === true);
        }

        // The movement entry where the file currently sits: the last office that has not
        // yet logged the file back out (no logOutDate). Falls back to the last entry.
        function getCurrentOutEntry(tracker) {
            const entries = tracker.logEntries || [];
            if (entries.length === 0) return null;
            for (let i = entries.length - 1; i >= 0; i--) {
                if (!entries[i].logOutDate) return entries[i];
            }
            return entries[entries.length - 1];
        }

        // Update dashboard stats for transit (files currently logged out)
        function updateDashboardStats() {
            const { transit } = separateFiles();
            const totalFiles = transit.length;
            // Every in-transit file is actively out of the registry.
            const activeFiles = totalFiles;
            const highPriority = transit.filter(tracker => tracker.priority === 'HIGH').length;

            const officesInvolved = new Set();
            transit.forEach(tracker => {
                const cur = getCurrentOutEntry(tracker);
                if (cur && cur.officeId) officesInvolved.add(cur.officeId);
            });

            document.getElementById('total-files').textContent = totalFiles;
            document.getElementById('active-files').textContent = activeFiles;
            document.getElementById('high-priority').textContent = highPriority;
            document.getElementById('offices-involved').textContent = officesInvolved.size;
        }

        // Update idle stats (files no longer in transit)
        function updateIdleStats() {
            const { idle } = separateFiles();
            document.getElementById('idle-total').textContent = idle.length;
            // Completed = logged back into the registry; the remainder (e.g. cancelled)
            // are treated as awaiting further action.
            const completed = idle.filter(t => t.isReturned || t.movementStatus === 'returned').length;
            document.getElementById('idle-awaiting').textContent = idle.length - completed;
            document.getElementById('idle-completed').textContent = completed;
        }

        // Format time difference
        function formatTimeDifference(dateString) {
            const now = new Date();
            const date = new Date(dateString);
            const diffMs = now - date;
            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
            const diffHours = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            
            if (diffDays > 0) {
                return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
            } else if (diffHours > 0) {
                return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
            } else {
                return 'Less than an hour ago';
            }
        }

        // Format date for display
        function formatDate(dateString) {
            const d = new Date(dateString);
            return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        // Build the movement chain following the Create File Tracker workflow:
        //   Registry (origin) → destination office(s) → back to Registry (on return).
        // Each hop is an office the file was logged out to; a trailing "↩ Registry"
        // marks a file that has been logged back in.
        function getMovementHistory(tracker) {
            const entries = (tracker && tracker.logEntries) || [];
            if (entries.length === 0) return 'No movement recorded';

            const chain = [];
            const origin = tracker.originOffice || 'Registry';
            chain.push(origin);
            entries.forEach(e => {
                const name = e.officeName || e.officeId;
                if (name && chain[chain.length - 1] !== name) chain.push(name);
            });
            // Logged back into the registry closes the loop.
            if (tracker.isReturned && chain[chain.length - 1] !== origin) {
                chain.push(`↩ ${origin}`);
            }
            return chain.join(' → ');
        }

        // Status label + badge class, derived from the workflow classification.
        function getStatusInfo(tracker) {
            const ms = tracker.movementStatus
                || (tracker.isInTransit ? 'in_transit'
                    : tracker.isReturned ? 'returned'
                    : tracker.isCanceled ? 'canceled'
                    : tracker.isRequested ? 'logout' : 'idle');
            switch (ms) {
                case 'logout':     return { label: 'Log-out', class: 'in-transit' };
                case 'in_transit': return { label: 'In Transit', class: 'in-transit' };
                case 'returned':   return { label: 'Completed', class: 'completed' };
                case 'canceled':   return { label: 'Canceled', class: 'idle' };
                default:           return { label: 'Idle', class: 'idle' };
            }
        }

        // Update files table for transit
        function updateFilesTable() {
            const { transit } = separateFiles();
            const tableBody = document.getElementById('files-table-body');
            const noResults = document.getElementById('no-results');
            
            const searchTerm = document.getElementById('search-input').value.toLowerCase();
            const priorityFilter = document.getElementById('priority-filter').value;
            const officeFilter = document.getElementById('office-filter').value;
            
            filteredTrackers = transit.filter(tracker => {
                const matchesSearch = 
                    tracker.fileName.toLowerCase().includes(searchTerm) ||
                    tracker.fileNo.toLowerCase().includes(searchTerm) ||
                    tracker.trackingId.toLowerCase().includes(searchTerm);
                
                const matchesPriority = priorityFilter === 'ALL' || tracker.priority === priorityFilter;

                const cur = getCurrentOutEntry(tracker);
                const matchesOffice = officeFilter === 'ALL' || (cur && cur.officeId === officeFilter);

                return matchesSearch && matchesPriority && matchesOffice;
            });
            
            if (filteredTrackers.length === 0) {
                tableBody.innerHTML = '';
                noResults.classList.remove('hidden');
                return;
            }
            
            noResults.classList.add('hidden');
            
            tableBody.innerHTML = filteredTrackers.map(tracker => {
                const activeEntry = getCurrentOutEntry(tracker);
                if (!activeEntry) return '';

                const priorityClass = `priority-${tracker.priority}`;
                const priorityText = tracker.priority.charAt(0) + tracker.priority.slice(1).toLowerCase();
                const office = officeData[tracker.currentOfficeId] || { name: tracker.currentOffice || 'Unknown', department: tracker.department || 'N/A' };
                const statusInfo = getStatusInfo(tracker);
                
                return `
                <tr class="file-row fade-in">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i data-lucide="file-text" class="h-5 w-5 text-blue-600"></i>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900">${tracker.fileName}</div>
                                <div class="text-sm text-gray-500">
                                    <span class="font-mono">${tracker.fileNo}</span> • 
                                    <span class="font-mono text-xs">${tracker.trackingId}</span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-8 w-8 bg-gray-100 rounded-full flex items-center justify-center">
                                <i data-lucide="building" class="h-4 w-4 text-gray-600"></i>
                            </div>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">${office.name}</div>
                                <div class="text-xs text-gray-500">${office.department}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">${activeEntry.createdAt ? formatTimeDifference(activeEntry.createdAt) : 'N/A'}</div>
                        <div class="text-xs text-gray-500">${activeEntry.logInDate ? 'Since ' + activeEntry.logInDate : 'Awaiting acceptance'}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${priorityClass}">${priorityText}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="status-badge ${statusInfo.class}">
                            <span class="dot"></span>
                            ${statusInfo.label}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button class="view-details-btn inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50" data-tracking-id="${tracker.trackingId}">
                            <i data-lucide="eye" class="h-4 w-4 mr-1"></i>
                            View Details
                        </button>
                    </td>
                </tr>
                `;
            }).join('');
            
            setupViewDetailsButtons();
            lucide.createIcons();
        }

        // Update idle table
        function updateIdleTable() {
            const { idle } = separateFiles();
            const tableBody = document.getElementById('idle-table-body');
            const noResults = document.getElementById('idle-no-results');
            
            if (idle.length === 0) {
                tableBody.innerHTML = '';
                noResults.classList.remove('hidden');
                return;
            }
            
            noResults.classList.add('hidden');
            
            tableBody.innerHTML = idle.map(tracker => {
                const lastEntry = tracker.logEntries[tracker.logEntries.length - 1];
                const office = officeData[tracker.currentOfficeId] || { name: tracker.currentOffice || 'Unknown', department: tracker.department || 'N/A' };
                const priorityClass = `priority-${tracker.priority}`;
                const priorityText = tracker.priority.charAt(0) + tracker.priority.slice(1).toLowerCase();
                const statusInfo = getStatusInfo(tracker);
                
                return `
                <tr class="file-row fade-in">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10 bg-gray-100 rounded-lg flex items-center justify-center">
                                <i data-lucide="archive" class="h-5 w-5 text-gray-600"></i>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900">${tracker.fileName}</div>
                                <div class="text-sm text-gray-500">
                                    <span class="font-mono">${tracker.fileNo}</span> • 
                                    <span class="font-mono text-xs">${tracker.trackingId}</span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">${office.name}</div>
                        <div class="text-xs text-gray-500">${office.department}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">${lastEntry ? formatTimeDifference(lastEntry.createdAt) : 'N/A'}</div>
                        <div class="text-xs text-gray-500">${lastEntry ? lastEntry.logOutDate : ''}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${priorityClass}">${priorityText}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="status-badge ${statusInfo.class}">
                            <span class="dot"></span>
                            ${statusInfo.label}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button class="view-details-btn inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50" data-tracking-id="${tracker.trackingId}">
                            <i data-lucide="eye" class="h-4 w-4 mr-1"></i>
                            View Details
                        </button>
                    </td>
                </tr>
                `;
            }).join('');
            
            setupViewDetailsButtons();
            lucide.createIcons();
        }

        // Update requested files table
        function updateRequestedTable() {
            const requested = getRequestedFiles();
            const tableBody = document.getElementById('requested-table-body');
            const noResults = document.getElementById('requested-no-results');
            const filter = document.getElementById('requested-filter').value;
            
            let filtered = requested;
            const now = new Date();
            
            if (filter !== 'ALL') {
                let startDate;
                if (filter === 'weekly') {
                    startDate = new Date(now);
                    startDate.setDate(now.getDate() - 7);
                } else if (filter === 'monthly') {
                    startDate = new Date(now);
                    startDate.setMonth(now.getMonth() - 1);
                } else if (filter === 'quarterly') {
                    startDate = new Date(now);
                    startDate.setMonth(now.getMonth() - 3);
                }
                filtered = requested.filter(t => {
                    const reqDate = new Date(t.requestedDate || t.requestDate);
                    return reqDate >= startDate;
                });
            }
            
            if (filtered.length === 0) {
                tableBody.innerHTML = '';
                noResults.classList.remove('hidden');
                return;
            }
            
            noResults.classList.add('hidden');
            
            tableBody.innerHTML = filtered.map(tracker => {
                const priorityClass = `priority-${tracker.priority}`;
                const priorityText = tracker.priority.charAt(0) + tracker.priority.slice(1).toLowerCase();
                const statusInfo = getStatusInfo(tracker);
                
                return `
                <tr class="file-row fade-in">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i data-lucide="file-text" class="h-5 w-5 text-blue-600"></i>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900">${tracker.fileName}</div>
                                <div class="text-sm text-gray-500">
                                    <span class="font-mono">${tracker.fileNo}</span> • 
                                    <span class="font-mono text-xs">${tracker.trackingId}</span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">${formatDate(tracker.requestedDate || tracker.requestDate)}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${priorityClass}">${priorityText}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">${tracker.department || 'N/A'}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="status-badge ${statusInfo.class}">
                            <span class="dot"></span>
                            ${statusInfo.label}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button class="view-details-btn inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50" data-tracking-id="${tracker.trackingId}">
                            <i data-lucide="eye" class="h-4 w-4 mr-1"></i>
                            View Details
                        </button>
                    </td>
                </tr>
                `;
            }).join('');
            
            setupViewDetailsButtons();
            lucide.createIcons();
        }

        // Setup view details buttons
        function setupViewDetailsButtons() {
            document.querySelectorAll('.view-details-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const trackingId = this.dataset.trackingId;
                    showTrackerDetails(trackingId);
                });
            });
        }

        // Show tracker details
        function showTrackerDetails(trackingId) {
            const tracker = fileTrackers.find(t => t.trackingId === trackingId);
            if (!tracker) return;
            
            currentDetailsTracker = tracker;
            
            const priorityClass = `priority-${tracker.priority}`;
            const priorityText = tracker.priority.charAt(0) + tracker.priority.slice(1).toLowerCase();
            const activeEntry = tracker.isReturned ? null : getCurrentOutEntry(tracker);

            const detailsContent = document.getElementById('details-content');
            detailsContent.innerHTML = `
                <div class="py-4 space-y-6">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div>
                            <h3 class="text-xl font-semibold">${tracker.fileName}</h3>
                            <p class="text-sm text-gray-600">File No: ${tracker.fileNo} | Tracking ID: ${tracker.trackingId}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${priorityClass}">${priorityText}</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">${tracker.currentOfficeId}</span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">File Information</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">File Name:</span>
                                        <span class="font-medium">${tracker.fileName}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">File No:</span>
                                        <span class="font-medium">${tracker.fileNo}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Tracking ID:</span>
                                        <span class="font-mono">${tracker.trackingId}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Priority:</span>
                                        <span class="font-medium ${priorityClass} px-2 py-0.5 rounded text-xs">${priorityText}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Department:</span>
                                        <span>${tracker.department || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Case Type:</span>
                                        <span>${tracker.caseType || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Applicant:</span>
                                        <span>${tracker.applicant || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Request Date:</span>
                                        <span>${tracker.requestDate ? formatDate(tracker.requestDate) : 'N/A'}</span>
                                    </div>
                                    ${tracker.isRequested ? `
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Requested Date:</span>
                                        <span>${tracker.requestedDate ? formatDate(tracker.requestedDate) : 'N/A'}</span>
                                    </div>
                                    ` : ''}
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Created:</span>
                                        <span>${new Date(tracker.createdAt).toLocaleString()}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Current Location</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Office:</span>
                                        <span>${tracker.currentOffice}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Office ID:</span>
                                        <span class="font-mono">${tracker.currentOfficeId}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Department:</span>
                                        <span>${officeData[tracker.currentOfficeId]?.department || 'N/A'}</span>
                                    </div>
                                    ${activeEntry ? `
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Time in Office:</span>
                                        <span>${formatTimeDifference(activeEntry.createdAt)}</span>
                                    </div>
                                    <div class="mt-2 p-2 bg-blue-50 rounded border border-blue-100">
                                        <div class="text-xs font-medium text-blue-800 mb-1">Current Notes:</div>
                                        <div class="text-xs text-blue-700">${activeEntry.notes || 'No notes recorded'}</div>
                                    </div>
                                    ` : `
                                    <div class="mt-2 p-2 bg-gray-50 rounded border border-gray-200">
                                        <div class="text-xs font-medium text-gray-600 mb-1">Status:</div>
                                        <div class="text-xs text-gray-700">Not in transit</div>
                                    </div>
                                    `}
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Movement History</h4>
                            <div class="mb-3 p-2 bg-gray-50 rounded border border-gray-200 text-xs text-gray-700">
                                <span class="font-medium text-gray-500">Flow:</span> ${getMovementHistory(tracker)}
                            </div>
                            <div class="space-y-3 max-h-80 overflow-y-auto">
                                <div class="log-entry log-entry-completed p-3 rounded">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-medium">${tracker.originOffice || 'Registry'}</span>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">Origin</span>
                                    </div>
                                    <div class="text-xs text-gray-600">File logged out from the registry.</div>
                                </div>
                                ${tracker.logEntries.map((entry) => {
                                    const returned = !!entry.logOutDate;
                                    const st = (entry.status || '').toLowerCase();
                                    let label, badge, entryClass;
                                    if (returned) {
                                        label = 'Returned'; badge = 'bg-gray-100 text-gray-800'; entryClass = 'log-entry log-entry-completed';
                                    } else if (st === 'pending_acceptance') {
                                        label = 'Pending Acceptance'; badge = 'bg-amber-100 text-amber-800'; entryClass = 'log-entry log-entry-active';
                                    } else {
                                        label = 'In Transit'; badge = 'bg-green-100 text-green-800'; entryClass = 'log-entry log-entry-active';
                                    }
                                    return `
                                    <div class="${entryClass} p-3 rounded">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-sm font-medium">${entry.officeName || entry.officeId || 'Unknown office'}</span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badge}">${label}</span>
                                        </div>
                                        <div class="text-xs text-gray-600 space-y-1">
                                            ${entry.logId ? `<div>Log ID: <span class="font-mono">${entry.logId}</span></div>` : ''}
                                            ${entry.logInDate ? `<div>Received: ${entry.logInDate}${entry.logInTime ? ' at ' + entry.logInTime : ''}</div>` : ''}
                                            ${entry.logOutDate ? `<div>Returned to registry: ${entry.logOutDate}${entry.logOutTime ? ' at ' + entry.logOutTime : ''}</div>` : ''}
                                            ${entry.receivingOfficer ? `<div>Receiving Officer: ${entry.receivingOfficer}</div>` : ''}
                                            ${entry.handledBy ? `<div>Logged by: ${entry.handledBy}</div>` : ''}
                                            ${entry.notes ? `<div class="mt-1"><strong>Notes:</strong> ${entry.notes}</div>` : ''}
                                        </div>
                                    </div>
                                    `;
                                }).join('')}
                                ${tracker.isReturned ? `
                                <div class="log-entry log-entry-completed p-3 rounded">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-medium">↩ ${tracker.originOffice || 'Registry'}</span>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Completed</span>
                                    </div>
                                    <div class="text-xs text-gray-600">File logged back into the registry.</div>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('details-dialog').classList.add('show');
            lucide.createIcons();
        }

        // Filter files based on search and filters
        function filterFiles() {
            updateFilesTable();
            if (chartsVisible) {
                updateCharts();
            }
        }

        // Update charts
        function updateCharts() {
            updatePriorityChart();
            updateOfficeChart();
        }

        // Update priority chart
        function updatePriorityChart() {
            const ctx = document.getElementById('priority-chart').getContext('2d');
            const { transit } = separateFiles();
            
            const priorityCounts = {
                HIGH: transit.filter(t => t.priority === 'HIGH').length,
                MEDIUM: transit.filter(t => t.priority === 'MEDIUM').length,
                LOW: transit.filter(t => t.priority === 'LOW').length
            };
            
            if (priorityChart) {
                priorityChart.destroy();
            }
            
            priorityChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['High Priority', 'Medium Priority', 'Low Priority'],
                    datasets: [{
                        data: [priorityCounts.HIGH, priorityCounts.MEDIUM, priorityCounts.LOW],
                        backgroundColor: [
                            '#EF4444',
                            '#F59E0B',
                            '#10B981'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '70%',
                    animation: {
                        animateScale: true,
                        animateRotate: true
                    }
                }
            });
        }

        // Update office chart
        function updateOfficeChart() {
            const ctx = document.getElementById('office-chart').getContext('2d');
            const { transit } = separateFiles();
            
            const officeCounts = {};
            transit.forEach(tracker => {
                const activeEntry = getCurrentOutEntry(tracker);
                if (activeEntry && activeEntry.officeId) {
                    const officeId = activeEntry.officeId;
                    officeCounts[officeId] = (officeCounts[officeId] || 0) + 1;
                }
            });
            
            const sortedOffices = Object.entries(officeCounts)
                .sort((a, b) => b[1] - a[1])
                .slice(0, 8);
            
            const labels = sortedOffices.map(([officeId]) => officeData[officeId]?.name || officeId);
            const data = sortedOffices.map(([, count]) => count);
            
            if (officeChart) {
                officeChart.destroy();
            }
            
            officeChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Files in Office',
                        data: data,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: 'rgb(59, 130, 246)',
                        borderWidth: 1,
                        borderRadius: 4,
                        hoverBackgroundColor: 'rgba(59, 130, 246, 1)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Files: ${context.raw}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            },
                            grid: {
                                drawBorder: false
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }

        // Toggle charts visibility
        function toggleCharts() {
            const chartsSection = document.getElementById('charts-section');
            const toggleBtn = document.getElementById('toggleChartsBtn');
            
            chartsVisible = !chartsVisible;
            
            if (chartsVisible) {
                chartsSection.classList.add('show');
                toggleBtn.innerHTML = '<i data-lucide="x" class="h-4 w-4 mr-1"></i><span>Hide Charts</span>';
                toggleBtn.classList.remove('bg-purple-50');
                toggleBtn.classList.add('bg-purple-100');
                
                if (!priorityChart || !officeChart) {
                    setTimeout(updateCharts, 300);
                } else {
                    updateCharts();
                }
            } else {
                chartsSection.classList.remove('show');
                toggleBtn.innerHTML = '<i data-lucide="bar-chart-3" class="h-4 w-4 mr-1"></i><span>Show Charts</span>';
                toggleBtn.classList.remove('bg-purple-100');
                toggleBtn.classList.add('bg-purple-50');
            }
            
            lucide.createIcons();
        }

        // Generate Request Sheet based on period
        function generateRequestSheet(period, customStart = null, customEnd = null) {
            const allFiles = fileTrackers;
            let requestedFiles = [];
            const now = new Date();
            let startDate, endDate;
            let periodLabel = '';

            // Get all requested files
            const allRequested = allFiles.filter(f => f.isRequested === true);

            if (period === 'weekly') {
                startDate = new Date(now);
                startDate.setDate(now.getDate() - 7);
                endDate = now;
                periodLabel = 'Weekly Request Sheet';
                requestedFiles = allRequested.filter(f => {
                    const reqDate = new Date(f.requestedDate || f.requestDate);
                    return reqDate >= startDate && reqDate <= endDate;
                });
            } else if (period === 'monthly') {
                startDate = new Date(now);
                startDate.setMonth(now.getMonth() - 1);
                endDate = now;
                periodLabel = 'Monthly Request Sheet';
                requestedFiles = allRequested.filter(f => {
                    const reqDate = new Date(f.requestedDate || f.requestDate);
                    return reqDate >= startDate && reqDate <= endDate;
                });
            } else if (period === 'quarterly') {
                startDate = new Date(now);
                startDate.setMonth(now.getMonth() - 3);
                endDate = now;
                periodLabel = 'Quarterly Request Sheet';
                requestedFiles = allRequested.filter(f => {
                    const reqDate = new Date(f.requestedDate || f.requestDate);
                    return reqDate >= startDate && reqDate <= endDate;
                });
            } else if (period === 'custom' && customStart && customEnd) {
                startDate = new Date(customStart);
                endDate = new Date(customEnd);
                periodLabel = `Custom Request Sheet (${formatDate(customStart)} - ${formatDate(customEnd)})`;
                requestedFiles = allRequested.filter(f => {
                    const reqDate = new Date(f.requestedDate || f.requestDate);
                    return reqDate >= startDate && reqDate <= endDate;
                });
            } else {
                // Default: show all requested files
                startDate = new Date(2000, 0, 1);
                endDate = now;
                periodLabel = 'All Requested Files';
                requestedFiles = allRequested;
            }

            // Sort by request date (newest first)
            requestedFiles.sort((a, b) => {
                const dateA = new Date(a.requestedDate || a.requestDate);
                const dateB = new Date(b.requestedDate || b.requestDate);
                return dateB - dateA;
            });

            const totalFiles = requestedFiles.length;
            const highPriority = requestedFiles.filter(f => f.priority === 'HIGH').length;
            const mediumPriority = requestedFiles.filter(f => f.priority === 'MEDIUM').length;
            const lowPriority = requestedFiles.filter(f => f.priority === 'LOW').length;
            const activeFiles = requestedFiles.filter(f => !f.isReturned && !f.isCanceled).length;
            const completedFiles = requestedFiles.filter(f => f.isReturned).length;
            const departments = [...new Set(requestedFiles.map(f => f.department).filter(Boolean))];

            let html = `
                <div class="header">
                    <h1>File Request Sheet</h1>
                    <div class="subtitle">Honorable Commissioner's Office · KLAES File Tracking System</div>
                    <div class="meta-info">
                        <span><strong>Period:</strong> ${periodLabel}</span>
                        <span><strong>Generated:</strong> ${new Date().toLocaleString()}</span>
                        <span><strong>Total Files:</strong> ${totalFiles}</span>
                        <span><strong>Departments:</strong> ${departments.length}</span>
                    </div>
                </div>

                <div class="summary-stats">
                    <div class="stat-item">
                        <div class="stat-value">${totalFiles}</div>
                        <div class="stat-label">Total Requested</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color:#065f46;">${activeFiles}</div>
                        <div class="stat-label">In Transit</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color:#475569;">${completedFiles}</div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color:#991b1b;">${highPriority}</div>
                        <div class="stat-label">High Priority</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color:#92400e;">${mediumPriority}</div>
                        <div class="stat-label">Medium Priority</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color:#065f46;">${lowPriority}</div>
                        <div class="stat-label">Low Priority</div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width:3%;">#</th>
                            <th style="width:10%;">File No.</th>
                            <th style="width:15%;">File Name</th>
                            <th style="width:8%;">Priority</th>
                            <th style="width:12%;">Department</th>
                            <th style="width:10%;">Current Office</th>
                            <th style="width:10%;">Requested Date</th>
                            <th style="width:8%;">Status</th>
                            <th style="width:24%;">Movement History</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            if (requestedFiles.length === 0) {
                html += `
                    <tr>
                        <td colspan="9" style="text-align:center; padding:2rem; color:#94a3b8; font-size:1rem;">
                            📭 No files have been requested during the selected period.
                        </td>
                    </tr>
                `;
            } else {
                requestedFiles.forEach((file, index) => {
                    const priorityClass = file.priority.toLowerCase();
                    const statusInfo = getStatusInfo(file);
                    const movement = getMovementHistory(file);
                    const lastEntry = file.logEntries[file.logEntries.length - 1];
                    const currentOffice = officeData[file.currentOfficeId]?.name || file.currentOffice;

                    html += `
                        <tr>
                            <td style="text-align:center; font-weight:600;">${index + 1}</td>
                            <td><strong>${file.fileNo}</strong></td>
                            <td>${file.fileName}</td>
                            <td><span class="badge-priority ${priorityClass}">${file.priority}</span></td>
                            <td>${file.department || '—'}</td>
                            <td>${currentOffice}</td>
                            <td>${file.requestedDate ? formatDate(file.requestedDate) : formatDate(file.requestDate)}</td>
                            <td><span class="status-badge ${statusInfo.class}">${statusInfo.label}</span></td>
                            <td>
                                <div class="movement-history">
                                    ${movement !== 'No movement recorded' ? movement : 'No movement recorded'}
                                    ${lastEntry ? `<div style="margin-top:2px; font-size:0.65rem; color:#94a3b8;">Last: ${lastEntry.officeName} (${formatTimeDifference(lastEntry.createdAt)})</div>` : ''}
                                </div>
                            </td>
                        </tr>
                    `;
                });
            }

            html += `
                    </tbody>
                </table>

                <div class="footer">
                    <div>
                        <strong>Commissioner's Signature:</strong>
                        <span class="signature-line"></span>
                        <span style="margin-left:1rem;">Date: ___________</span>
                    </div>
                    <div>
                        <span>Page 1 of 1</span>
                    </div>
                </div>
                <div style="text-align:center; font-size:0.7rem; color:#94a3b8; margin-top:1rem; padding-top:0.5rem; border-top:1px solid #e2e8f0;">
                    This is a system-generated document. All information is based on the latest file tracking data. 
                    For any discrepancies, please contact the Records Department.
                </div>
            `;

            document.getElementById('requestSheetContent').innerHTML = html;
            document.getElementById('requestSheetOverlay').classList.add('show');
            lucide.createIcons();
        }

        // Switch tabs
        function switchTab(tab) {
            currentTab = tab;
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
            
            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.classList.remove('active-panel');
            });
            document.getElementById(`panel-${tab}`).classList.add('active-panel');
            
            if (tab === 'transit') {
                updateDashboardStats();
                updateFilesTable();
                if (chartsVisible) updateCharts();
            } else if (tab === 'idle') {
                updateIdleStats();
                updateIdleTable();
            } else if (tab === 'requested') {
                updateRequestedTable();
            }
        }

        // Initialize dashboard
        function initializeDashboard() {
            // Set default tab to requested
            currentTab = 'requested';
            
            updateDashboardStats();
            updateIdleStats();
            updateFilesTable();
            updateIdleTable();
            updateRequestedTable();
            
            // Tab switching
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    switchTab(this.dataset.tab);
                });
            });
            
            // Set up event listeners
            document.getElementById('search-input').addEventListener('input', filterFiles);
            document.getElementById('priority-filter').addEventListener('change', filterFiles);
            document.getElementById('office-filter').addEventListener('change', filterFiles);
            document.getElementById('clear-filters').addEventListener('click', () => {
                document.getElementById('search-input').value = '';
                document.getElementById('priority-filter').value = 'ALL';
                document.getElementById('office-filter').value = 'ALL';
                filterFiles();
            });
            
            // Requested filter
            document.getElementById('requested-filter').addEventListener('change', updateRequestedTable);
            
            // Generate requested sheet
            document.getElementById('generateRequestedSheet').addEventListener('click', () => {
                const filter = document.getElementById('requested-filter').value;
                if (filter === 'ALL') {
                    generateRequestSheet('all');
                } else {
                    generateRequestSheet(filter);
                }
            });
            
            // Priority chart filter buttons
            document.querySelectorAll('.priority-filter-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.querySelectorAll('.priority-filter-btn').forEach(btn => {
                        btn.classList.remove('bg-blue-100', 'text-blue-700');
                        btn.classList.add('text-gray-500', 'hover:bg-gray-100');
                    });
                    this.classList.remove('text-gray-500', 'hover:bg-gray-100');
                    this.classList.add('bg-blue-100', 'text-blue-700');
                    
                    const priority = this.dataset.priority;
                    document.getElementById('priority-filter').value = priority;
                    filterFiles();
                });
            });
            
            // Office chart filter buttons
            document.querySelectorAll('.office-filter-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.querySelectorAll('.office-filter-btn').forEach(btn => {
                        btn.classList.remove('bg-blue-100', 'text-blue-700');
                        btn.classList.add('text-gray-500', 'hover:bg-gray-100');
                    });
                    this.classList.remove('text-gray-500', 'hover:bg-gray-100');
                    this.classList.add('bg-blue-100', 'text-blue-700');
                });
            });
            
            document.getElementById('refreshBtn')?.addEventListener('click', () => {
                updateDashboardStats();
                updateIdleStats();
                filterFiles();
                updateIdleTable();
                updateRequestedTable();
                if (chartsVisible) {
                    updateCharts();
                }
                const icon = document.querySelector('#refreshBtn i');
                icon?.classList.add('animate-spin');
                setTimeout(() => {
                    icon?.classList.remove('animate-spin');
                }, 1000);
            });

            document.getElementById('toggleChartsBtn').addEventListener('click', toggleCharts);

            // Print menu
            document.getElementById('printMenuBtn')?.addEventListener('click', function(e) {
                e.stopPropagation();
                document.getElementById('printDropdown').classList.toggle('show');
            });
            
            document.addEventListener('click', function() {
                document.getElementById('printDropdown').classList.remove('show');
            });
            
            document.querySelectorAll('.print-dropdown-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const period = this.dataset.period;
                    if (period === 'custom') {
                        const start = prompt('Enter start date (YYYY-MM-DD):');
                        if (!start) return;
                        const end = prompt('Enter end date (YYYY-MM-DD):');
                        if (!end) return;
                        generateRequestSheet('custom', start, end);
                    } else {
                        generateRequestSheet(period);
                    }
                    document.getElementById('printDropdown').classList.remove('show');
                });
            });
            
            // Request sheet close
            document.getElementById('closeRequestSheet').addEventListener('click', () => {
                document.getElementById('requestSheetOverlay').classList.remove('show');
            });
            
            // Click outside to close
            document.getElementById('requestSheetOverlay').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
            
            // Details dialog event listeners
            document.getElementById('close-details').addEventListener('click', () => {
                document.getElementById('details-dialog').classList.remove('show');
            });
            document.getElementById('close-details-btn').addEventListener('click', () => {
                document.getElementById('details-dialog').classList.remove('show');
            });
            document.getElementById('print-details-btn').addEventListener('click', () => {
                if (currentDetailsTracker) {
                    // Mark as requested if not already
                    if (!currentDetailsTracker.isRequested) {
                        currentDetailsTracker.isRequested = true;
                        currentDetailsTracker.requestedDate = new Date().toISOString().split('T')[0];
                        updateRequestedTable();
                    }
                    generateRequestSheet('custom', 
                        currentDetailsTracker.requestedDate,
                        currentDetailsTracker.requestedDate
                    );
                    document.getElementById('details-dialog').classList.remove('show');
                }
            });
        }

        // Initialize when the page loads
        document.addEventListener('DOMContentLoaded', () => {
            initializeDashboard();
            fetchDashboardData();
        });


