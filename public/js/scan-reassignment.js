/**
 * Scan Reassignment JavaScript Module
 * Handles reassignment of misplaced scanned documents to correct file numbers
 */

class ScanReassignmentManager {
    constructor(config) {
        this.config = config || {};
        this.modal = null;
        this.selectedScanIds = [];
        this.scanData = [];
        this.targetFileNumber = '';
        this.destinationInfo = null;
        this.selectedRegistry = null;
        this.sourceRegistry = null;
        this.checkTimeout = null;

        this.init();
    }

    init() {
        this.modal = document.querySelector('[data-reassign-modal]');
        if (!this.modal) {
            console.warn('Reassign modal not found in DOM');
            return;
        }

        console.log('Initializing ScanReassignmentManager');
        
        // Bind modal events
        this.bindModalEvents();
    }

    /**
     * Bind all modal event listeners
     */
    bindModalEvents() {
        // Close buttons
        document.querySelector('[data-reassign-close]')?.addEventListener('click', () => this.closeModal());
        document.querySelector('[data-reassign-cancel]')?.addEventListener('click', () => this.closeModal());

        // Confirm button
        document.querySelector('#reassign-confirm-btn')?.addEventListener('click', () => this.confirmReassignment());

        // Registry change
        document.querySelector('#reassign-registry-select')?.addEventListener('change', (e) => {
            this.selectedRegistry = e.target.value;
            console.log('Registry selected:', this.selectedRegistry);
        });

        // Open global file selector button
        document.querySelector('#reassign-open-file-selector-btn')?.addEventListener('click', () => {
            this.openGlobalFileSelector();
        });

        // Change file button
        document.querySelector('#reassign-change-file-btn')?.addEventListener('click', () => {
            this.openGlobalFileSelector();
        });

        // Close on backdrop click
        this.modal?.addEventListener('click', (e) => {
            if (e.target === this.modal) this.closeModal();
        });
    }

    /**
     * Open the global file number modal
     */
    openGlobalFileSelector() {
        if (typeof GlobalFileNoModal !== 'undefined') {
            console.log('Opening GlobalFileNoModal for reassignment');
            // Pass callback to handle file selection
            GlobalFileNoModal.open({
                callback: (fileData) => this.onFileNumberSelected(fileData)
            });
        } else {
            console.error('GlobalFileNoModal not available');
            this.showAlert('File selector not available', 'error');
        }
    }

    /**
     * Handle file selection from global modal
     */
    onFileNumberSelected(fileData) {
        if (!fileData || !fileData.fileNumber) {
            console.warn('Invalid file data received:', fileData);
            return;
        }

        console.log('File selected from global modal:', fileData);

        this.targetFileNumber = fileData.fileNumber;
        
        // Update display
        const selectedDisplay = document.querySelector('#reassign-selected-file-display');
        const selectedFileNumber = document.querySelector('#reassign-selected-file-number');
        const openButton = document.querySelector('#reassign-open-file-selector-btn');
        
        if (selectedDisplay && selectedFileNumber) {
            selectedFileNumber.textContent = fileData.fileNumber;
            selectedDisplay.classList.remove('hidden');
            openButton?.classList.add('hidden');
        }

        // Trigger validation
        this.checkTargetFileNumber();
    }

    /**
     * Open the reassign modal with selected scans and their data
     */
    openModal(scanIds, scanData) {
        if (!scanIds || scanIds.length === 0) {
            this.showAlert('Please select at least one document to reassign.', 'warning');
            return;
        }

        this.selectedScanIds = Array.isArray(scanIds) ? scanIds : [scanIds];
        this.scanData = Array.isArray(scanData) ? scanData : (scanData ? [scanData] : []);
        
        // Extract registry from first scan's data
        if (this.scanData.length > 0 && this.scanData[0].registry) {
            this.sourceRegistry = this.scanData[0].registry;
            this.selectedRegistry = this.sourceRegistry;
            // Pre-select the source registry in the dropdown
            const registrySelect = document.querySelector('#reassign-registry-select');
            if (registrySelect) {
                registrySelect.value = this.sourceRegistry;
            }
        }
        
        this.resetForm();
        this.renderSelectedDocuments();
        this.showModal();
    }

    /**
     * Render list of selected documents
     */
    renderSelectedDocuments() {
        const container = document.querySelector('#reassign-selected-docs');
        if (!container) return;

        container.innerHTML = '';

        this.selectedScanIds.forEach((scanId, index) => {
            // First try to get data from scanData array
            let scanInfo = this.scanData?.[index];
            
            // If no data provided, try to find fallback info
            if (!scanInfo) {
                scanInfo = {
                    fileName: `Document #${scanId}`,
                    fileNumber: '',
                    scanId: scanId
                };
            }

            const fileName = scanInfo.fileName || `Document #${scanId}`;
            // Show actual file number if available, otherwise show that it's not indexed
            const fileNumber = (scanInfo.fileNumber && String(scanInfo.fileNumber).trim())
                ? String(scanInfo.fileNumber).trim()
                : '(Not indexed)';

            const item = document.createElement('div');
            item.className = 'flex items-start gap-2 p-2 bg-white rounded border border-gray-300 text-sm';
            item.innerHTML = `
                <i data-lucide="file" class="h-4 w-4 text-gray-500 flex-shrink-0 mt-0.5"></i>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900 truncate">${this.escapeHtml(fileName)}</p>
                    <p class="text-xs text-gray-600">Current: ${this.escapeHtml(fileNumber)}</p>
                </div>
            `;
            container.appendChild(item);
        });

        // Re-initialize Lucide icons
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    /**
     * Validate target file number via AJAX
     */
    async checkTargetFileNumber() {
        const preview = document.querySelector('#reassign-preview');
        const error = document.querySelector('#reassign-error');
        const constraintWarning = document.querySelector('#reassign-constraint-warning');

        if (!this.targetFileNumber) {
            preview?.classList.add('hidden');
            return;
        }

        try {
            const response = await fetch('/scan-uploads/reassign/check', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    target_file_number: this.targetFileNumber,
                }),
            });

            const json = await response.json();

            if (!json.success) {
                error?.classList.remove('hidden');
                error.textContent = json.message || 'Invalid file number.';
                preview?.classList.add('hidden');
                this.destinationInfo = null;
                return;
            }

            this.destinationInfo = json.data;
            this.renderDestinationPreview();
            this.checkPageTypingConstraints();
        } catch (err) {
            error?.classList.remove('hidden');
            error.textContent = 'Error validating file number: ' + err.message;
            this.destinationInfo = null;
        }
    }

    /**
     * Render destination preview based on resolution result
     */
    renderDestinationPreview() {
        const preview = document.querySelector('#reassign-preview');
        const previewIndexed = document.querySelector('#reassign-preview-indexed');
        const previewBlind = document.querySelector('#reassign-preview-blind');

        if (!preview || !this.destinationInfo) return;

        preview.classList.remove('hidden');

        if (this.destinationInfo.destination_type === 'scan_upload') {
            previewIndexed?.classList.remove('hidden');
            previewBlind?.classList.add('hidden');

            document.querySelector('#reassign-preview-registry').textContent = this.destinationInfo.registry || 'Unknown Registry';
            document.querySelector('#reassign-preview-file-number').textContent = this.escapeHtml(this.destinationInfo.file_number);
            
            // Get paper size from the first scanned document being reassigned
            const paperSize = (this.scanData && this.scanData[0]?.paperSize) || 'A4';
            document.querySelector('#reassign-preview-paper-size').textContent = paperSize;
            
            document.querySelector('#reassign-preview-scan-count').textContent = this.destinationInfo.existing_scan_count || 0;
        } else {
            previewIndexed?.classList.add('hidden');
            previewBlind?.classList.remove('hidden');

            document.querySelector('#reassign-preview-blind-file-number').textContent = this.escapeHtml(this.destinationInfo.file_number);
        }
    }

    /**
     * Check if any selected scans have PageTyping records (constraint)
     */
    async checkPageTypingConstraints() {
        const constraintWarning = document.querySelector('#reassign-constraint-warning');
        const confirmBtn = document.querySelector('#reassign-confirm-btn');

        if (!constraintWarning || !confirmBtn) return;

        try {
            // Fetch page typing status for selected scans
            const response = await fetch('/scan-uploads/reassign/check-constraints', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    scan_ids: this.selectedScanIds,
                }),
            });

            if (response.status === 404) {
                // Endpoint doesn't exist; skip this check
                return;
            }

            const json = await response.json();

            if (json.data?.has_page_typing) {
                constraintWarning.classList.remove('hidden');
                confirmBtn.disabled = true;
            } else {
                constraintWarning.classList.add('hidden');
                confirmBtn.disabled = false;
            }
        } catch (err) {
            // Silently ignore constraint check failures
            console.warn('Could not check page typing constraints:', err);
        }
    }

    /**
     * Confirm reassignment and submit
     */
    async confirmReassignment() {
        const confirmBtn = document.querySelector('#reassign-confirm-btn');
        const loadingOverlay = document.querySelector('#reassign-loading-overlay');
        const error = document.querySelector('#reassign-error');

        if (!this.targetFileNumber || !this.selectedScanIds.length) {
            this.showAlert('Please select documents and enter a target file number.', 'error');
            return;
        }

        confirmBtn.disabled = true;
        loadingOverlay?.classList.remove('hidden');
        error?.classList.add('hidden');

        try {
            const reason = document.querySelector('#reassign-reason')?.value?.trim() || null;

            const response = await fetch('/scan-uploads/reassign', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    scan_ids: this.selectedScanIds,
                    target_file_number: this.targetFileNumber,
                    reason: reason,
                }),
            });

            const json = await response.json();
            loadingOverlay?.classList.add('hidden');

            if (!json.success) {
                confirmBtn.disabled = false;
                error?.classList.remove('hidden');
                let errorMsg = json.message || 'Reassignment failed.';
                // Append details from individual failed scans if available
                if (json.data?.failed?.length) {
                    const details = json.data.failed
                        .map(f => f.error)
                        .filter(Boolean);
                    if (details.length && details[0] !== errorMsg) {
                        errorMsg += '\n' + details.join('\n');
                    }
                }
                error.textContent = errorMsg;
                return;
            }

            // Success
            this.showAlert(`✓ ${json.message}`, 'success');
            this.closeModal();

            // Dispatch custom event for parent view to refresh
            window.dispatchEvent(new CustomEvent('scans-reassigned', {
                detail: {
                    movedCount: json.data.moved_count,
                    failedCount: json.data.failed_count,
                    documents: json.data.documents,
                }
            }));

            // Refresh the parent view (if available)
            if (window.location.href.includes('/scan-uploads')) {
                setTimeout(() => {
                    window.location.reload();
                }, 800);
            }
        } catch (err) {
            loadingOverlay?.classList.add('hidden');
            confirmBtn.disabled = false;
            error?.classList.remove('hidden');
            error.textContent = 'Error during reassignment: ' + err.message;
        }
    }

    /**
     * Show/hide modal
     */
    showModal() {
        if (this.modal) {
            this.modal.classList.remove('hidden');
            this.modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }
    }

    closeModal() {
        if (this.modal) {
            this.modal.classList.add('hidden');
            this.modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = 'auto';
            // Clear scan data when fully closing
            this.scanData = [];
            this.selectedScanIds = [];
        }
    }

    /**
     * Reset form state
     */
    resetForm() {
        // Hide selected file display and show file selector button
        const selectedDisplay = document.querySelector('#reassign-selected-file-display');
        if (selectedDisplay) {
            selectedDisplay.classList.add('hidden');
        }

        const openButton = document.querySelector('#reassign-open-file-selector-btn');
        if (openButton) {
            openButton.classList.remove('hidden');
        }

        const selectedFileNumber = document.querySelector('#reassign-selected-file-number');
        if (selectedFileNumber) {
            selectedFileNumber.textContent = '-';
        }

        // Reset registry to source registry if available
        const registrySelect = document.querySelector('#reassign-registry-select');
        if (registrySelect && this.sourceRegistry) {
            registrySelect.value = this.sourceRegistry;
            this.selectedRegistry = this.sourceRegistry;
        }

        // Reset reason (with default value)
        const reasonEl = document.querySelector('#reassign-reason');
        if (reasonEl) {
            reasonEl.value = 'Document reassigned via reassignment modal';
        }
        
        // Hide preview sections
        document.querySelector('#reassign-preview')?.classList.add('hidden');
        document.querySelector('#reassign-error')?.classList.add('hidden');
        document.querySelector('#reassign-constraint-warning')?.classList.add('hidden');
        
        // Reset buttons
        const confirmBtn = document.querySelector('#reassign-confirm-btn');
        if (confirmBtn) {
            confirmBtn.disabled = false;
        }
        
        this.targetFileNumber = '';
        this.destinationInfo = null;
        // DO NOT clear this.scanData here - it persists for the reassignment flow
        // Only clear scanData when modal fully closes, not during UI reset
    }

    /**
     * Show alert message (integrate with your notification system)
     */
    showAlert(message, type = 'info') {
        // Use Bootstrap notify or your notification system
        if (window.$.notify) {
            $.notify({
                message: message,
            }, {
                type: type === 'error' ? 'danger' : type,
                newest_on_top: true,
                offset: {
                    x: 20,
                    y: 70
                },
            });
        } else {
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    }

    /**
     * Escape HTML special characters
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
}

// Initialize on document ready
document.addEventListener('DOMContentLoaded', () => {
    window.scanReassignmentManager = new ScanReassignmentManager();
});
