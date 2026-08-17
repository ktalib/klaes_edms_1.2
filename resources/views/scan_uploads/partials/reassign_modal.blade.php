<!-- Scan Reassignment Modal -->
<div id="reassign-modal" class="dialog-backdrop hidden z-50" aria-hidden="true" data-reassign-modal style="z-index: 9999;">
  <div class="dialog-content max-w-2xl animate-fade-in" style="z-index: 10000;">
    <div class="p-6 border-b border-gray-200 flex items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-3">
          <div class="p-2 bg-amber-100 rounded-lg">
            <i data-lucide="git-branch" class="h-5 w-5 text-amber-600"></i>
          </div>
          <div>
            <h2 class="text-xl font-bold text-gray-900">Reassign Scanned Document</h2>
            <p class="text-sm text-gray-600">Move misplaced pages to the correct file number</p>
          </div>
        </div>
      </div>
      <button type="button" class="btn btn-ghost" data-reassign-close>
        <i data-lucide="x" class="h-5 w-5"></i>
      </button>
    </div>

    <div class="px-6 py-5 space-y-5">
      <!-- What will move -->
      <div>
        <div class="flex items-center justify-between gap-3 mb-3">
          <label class="block text-sm font-semibold text-gray-900">Documents to Move</label>
          <span id="reassign-scope-count" class="text-xs font-medium text-gray-500"></span>
        </div>

        <div id="reassign-selected-docs" class="space-y-2 max-h-48 overflow-y-auto p-3 bg-gray-50 rounded-lg border border-gray-200">
          <!-- Populated dynamically -->
        </div>
      </div>

      <!-- Target File Number Input (Global Selector) -->
      <div>
        <label class="block text-sm font-semibold text-gray-900 mb-3">
          Target File Number
          <span class="text-red-500">*</span>
        </label>
        <p class="text-xs text-gray-600 mb-3">
          <i data-lucide="info" class="h-3.5 w-3.5 inline mr-1"></i>
          Pick the file to move these pages to — nothing can be confirmed until you do
        </p>

        <!-- Selected File Display -->
        <div id="reassign-selected-file-display" class="p-3 mb-3 bg-blue-50 border border-blue-200 rounded-lg hidden">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-semibold text-blue-900 uppercase tracking-wide">Selected File</p>
              <p class="text-sm font-mono font-bold text-blue-900 mt-1" id="reassign-selected-file-number">-</p>
            </div>
            <button type="button" class="btn btn-outline btn-sm text-blue-600" id="reassign-change-file-btn">
              <i data-lucide="edit-2" class="h-3.5 w-3.5"></i>
              Change
            </button>
          </div>
        </div>

        <!-- Open File Selector Button -->
        <button type="button" class="btn btn-primary w-full gap-2" id="reassign-open-file-selector-btn">
          <i data-lucide="search" class="h-4 w-4"></i>
          Search File Number
        </button>

        <!-- Registry Filter (Optional) — narrows the search above -->
        <div class="mt-3">
          <label for="reassign-registry-select" class="block text-xs font-medium text-gray-700 mb-2">
            Filter by Registry (Optional)
          </label>
          <select id="reassign-registry-select" class="input input-bordered w-full text-sm">
            <option value="">Select Registry</option>
            <option value="Lands Registry">Lands Registry</option>
            <option value="Cadastral Registry">Cadastral Registry</option>
            <option value="DCIV Registry">DCIV Registry</option>
            <option value="Secret Registry">Secret Registry</option>
            <option value="KANGIS Registry">KANGIS Registry</option>
            <option value="SLTR Registry">SLTR Registry</option>
            <option value="ST Registry">ST Registry</option>
          </select>
          <p class="text-xs text-gray-500 mt-1">Pre-filtered to match your current file's registry by default</p>
        </div>
      </div>

      <!-- Destination Preview -->
      <div id="reassign-preview" class="hidden p-4 bg-gradient-to-br from-indigo-50 to-blue-50 border border-indigo-200 rounded-lg">
        <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-2">Destination Preview</p>
        
        <div id="reassign-preview-indexed" class="hidden space-y-2">
          <p class="text-sm text-gray-900">
            <span class="font-semibold">Will move to:</span>
            <code class="bg-white px-2 py-1 rounded text-xs ml-1">
              <span id="reassign-preview-registry"></span> / 
              <span id="reassign-preview-file-number"></span> / 
              <span id="reassign-preview-paper-size"></span>
            </code>
          </p>
          <p class="text-xs text-gray-600">
            <i data-lucide="info" class="h-3.5 w-3.5 inline mr-1"></i>
            File indexed in system. Scans will be stored in SCAN_UPLOAD folder.
          </p>
          <div class="text-xs text-gray-700 bg-white p-2 rounded border-l-2 border-indigo-400">
            <p><strong>Existing scans:</strong> <span id="reassign-preview-scan-count">0</span></p>
          </div>
        </div>

        <div id="reassign-preview-blind" class="hidden space-y-2">
          <p class="text-sm text-gray-900">
            <span class="font-semibold">Will move to:</span>
            <code class="bg-white px-2 py-1 rounded text-xs ml-1">BLIND_SCAN / <span id="reassign-preview-blind-file-number"></span></code>
          </p>
          <p class="text-xs text-amber-700 bg-white p-2 rounded border-l-2 border-amber-400">
            <i data-lucide="alert-circle" class="h-3.5 w-3.5 inline mr-1"></i>
            File not yet indexed. Scans will be stored in BLIND_SCAN staging folder and picked up when file is indexed.
          </p>
        </div>
      </div>

      <!-- Error Message -->
      <div id="reassign-error" class="hidden p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
        <!-- Populated dynamically -->
      </div>

      <!-- Constraint Warning: PageTyping (warning, not a blocker) -->
      <div id="reassign-constraint-warning" class="hidden p-4 bg-orange-50 border border-orange-200 rounded-lg">
        <div class="flex gap-3">
          <i data-lucide="alert-triangle" class="h-5 w-5 text-orange-600 flex-shrink-0 mt-0.5"></i>
          <div class="text-sm text-orange-800">
            <p class="font-semibold">This document is already page typed</p>
            <p class="text-xs mt-1" id="reassign-constraint-message">
              Page typing exists for one or more selected documents. The typed pages will be
              re-linked to the new indexing record and their files moved with them.
            </p>
            <label class="flex items-start gap-2 mt-3 text-xs font-medium cursor-pointer">
              <input type="checkbox" id="reassign-constraint-ack" class="checkbox checkbox-xs mt-0.5">
              <span>I understand the typed pages will be re-linked to the new file number.</span>
            </label>
          </div>
        </div>
      </div>

      <!-- Reason (Optional) - Hidden with Default -->
      <div class="hidden">
        <label for="reassign-reason" class="block text-sm font-semibold text-gray-900 mb-2">Reason (Optional)</label>
        <textarea
          id="reassign-reason"
          class="input input-bordered w-full h-20 p-3 resize-none"
          placeholder="e.g., Pages belong to file 0042 not 0041"
          maxlength="500"
        >Document reassigned via reassignment modal</textarea>
        <p class="text-xs text-gray-500 mt-1">Stored in audit log for compliance</p>
      </div>
    </div>

    <div class="p-6 border-t border-gray-200 flex items-center gap-3 justify-end">
      <button type="button" class="btn btn-outline" data-reassign-cancel>Cancel</button>
      {{-- Stays disabled until a target file number has been picked --}}
      <button type="button" class="btn btn-primary" data-reassign-confirm id="reassign-confirm-btn"
              disabled title="Select a target file number first">
        <i data-lucide="check" class="h-4 w-4"></i>
        Confirm Reassignment
      </button>
    </div>

    <!-- Loading Overlay -->
    <div id="reassign-loading-overlay" class="hidden absolute inset-0 bg-black/10 rounded-lg flex items-center justify-center">
      <div class="bg-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-3">
        <div class="loading-spinner small"></div>
        <span class="text-sm font-medium text-gray-900">Processing reassignment...</span>
      </div>
    </div>
  </div>
</div>

<!-- Include Global File Number Modal for Reassignment -->
@include('components.global-fileno-modal')
