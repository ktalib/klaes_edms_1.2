# PTQ QC Review Tab Fix

## Issue
The QC Review tab is not showing content when a file is selected for review.

## Root Cause
The `renderQCReviewView()` function is missing from the JavaScript, and the `updateUI()` function doesn't call it when switching to the QC review tab.

## Solution
Add the missing QC review functionality to the JavaScript:

### 1. Update the `updateUI()` function
Add this line after the existing code in `updateUI()`:
```javascript
// Render QC review content if on QC review tab
if (state.activeTab === 'qc-review' && state.selectedFile) {
  renderQCReviewView();
}
```

### 2. Add the `renderQCReviewView()` function
Add this complete function to handle QC review rendering:

```javascript
function renderQCReviewView() {
  const file = getFileById(state.selectedFile);
  if (!file) {
    elements.qcReviewCard.innerHTML = `
      <div class="p-6 text-center">
        <h3 class="mb-2 text-lg font-medium">File not found</h3>
        <p class="mb-4 text-sm text-muted-foreground">The selected file could not be loaded</p>
        <button class="btn btn-outline btn-sm" onclick="state.selectedFile = null; state.activeTab = 'pending'; updateUI();">
          Back to Pending Files
        </button>
      </div>
    `;
    return;
  }
  
  // QC Review content with page cards and action buttons
  const content = `
    <div class="p-6 border-b">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-lg font-semibold">
            <span class="text-blue-600">${file.fileNumber}</span> - ${file.name}
          </h2>
          <p class="text-sm text-muted-foreground">Review and approve/reject each page</p>
        </div>
        <div class="flex items-center gap-2">
          <button class="btn btn-outline btn-sm back-button">Back to Files</button>
        </div>
      </div>
    </div>
    <div class="p-6">
      <div class="space-y-6">
        <div class="flex justify-between items-center">
          <h3 class="text-lg font-medium">Pages for QC Review</h3>
          <span class="badge bg-blue-500 text-white">${file.pages} pages</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          ${Array.from({length: file.pages}, (_, index) => {
            const isProcessed = state.processedPages[index];
            const processedInfo = state.processedPages[index];
            
            return `
              <div class="border rounded-md overflow-hidden ${
                isProcessed
                  ? processedInfo?.status === 'approved' 
                    ? 'border-green-500 bg-green-50'
                    : processedInfo?.status === 'rejected'
                    ? 'border-red-500 bg-red-50'
                    : 'border-yellow-500 bg-yellow-50'
                  : 'hover:border-blue-500'
              } transition-colors">
                <div class="h-48 bg-muted flex items-center justify-center relative">
                  ${isProcessed 
                    ? `<div class="absolute top-2 right-2 z-10">
                        <span class="badge ${
                          processedInfo?.status === 'approved' ? 'bg-green-500' : 
                          processedInfo?.status === 'rejected' ? 'bg-red-500' : 'bg-yellow-500'
                        } text-white">
                          ${processedInfo?.status === 'approved' ? 'Approved' : 
                            processedInfo?.status === 'rejected' ? 'Rejected' : 'Override'}
                        </span>
                      </div>`
                    : ''}
                  <div class="text-center">
                    <i data-lucide="file-text" class="h-12 w-12 text-muted-foreground mb-2"></i>
                    <p class="text-sm font-medium">Page ${index + 1}</p>
                    <p class="text-xs text-muted-foreground">${file.fileNumber}-${(index + 1).toString().padStart(2, '0')}</p>
                  </div>
                </div>
                <div class="p-3 bg-white border-t">
                  <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium">Page ${index + 1}</span>
                    <span class="badge badge-outline text-xs">
                      ${index === 0 ? 'Cover' : 'Content'}
                    </span>
                  </div>
                  ${isProcessed 
                    ? `<div class="text-center">
                        <span class="badge ${
                          processedInfo?.status === 'approved' ? 'bg-green-500' : 
                          processedInfo?.status === 'rejected' ? 'bg-red-500' : 'bg-yellow-500'
                        } text-white text-xs w-full justify-center">
                          QC ${processedInfo?.status === 'approved' ? 'Approved' : 
                               processedInfo?.status === 'rejected' ? 'Rejected' : 'Override'}
                        </span>
                      </div>`
                    : `<div class="flex gap-1">
                        <button class="btn btn-xs bg-green-500 text-white flex-1 approve-page-btn" data-index="${index}">
                          Approve
                        </button>
                        <button class="btn btn-xs bg-red-500 text-white flex-1 reject-page-btn" data-index="${index}">
                          Reject
                        </button>
                        <button class="btn btn-xs bg-yellow-500 text-white flex-1 override-page-btn" data-index="${index}">
                          Override
                        </button>
                      </div>`}
                </div>
              </div>
            `;
          }).join('')}
        </div>
      </div>
    </div>
  `;
  
  // Update the QC review card
  elements.qcReviewCard.innerHTML = content;
  
  // Initialize icons for the new elements
  lucide.createIcons();
  
  // Add event listeners for QC actions
  addQCEventListeners();
}
```

### 3. Add QC action event listeners
```javascript
function addQCEventListeners() {
  document.querySelector('.back-button')?.addEventListener('click', () => {
    state.selectedFile = null;
    state.activeTab = 'pending';
    updateUI();
  });
  
  // Approve page buttons
  document.querySelectorAll('.approve-page-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const index = parseInt(btn.getAttribute('data-index'));
      state.processedPages[index] = { status: 'approved' };
      alert('Page approved successfully!');
      updateUI();
    });
  });
  
  // Reject page buttons
  document.querySelectorAll('.reject-page-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const index = parseInt(btn.getAttribute('data-index'));
      const reason = prompt('Please provide a reason for rejecting this page:');
      if (reason !== null) {
        state.processedPages[index] = { status: 'rejected', reason: reason };
        alert('Page rejected successfully!');
        updateUI();
      }
    });
  });
  
  // Override page buttons
  document.querySelectorAll('.override-page-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const index = parseInt(btn.getAttribute('data-index'));
      showOverrideModal(index);
    });
  });
}
```

### 4. Add modal functions
```javascript
function showOverrideModal(index) {
  const file = getFileById(state.selectedFile);
  state.selectedPageInFolder = index;
  
  // Populate modal with current page info
  document.getElementById('override-page-id').value = index;
  document.getElementById('override-page-details').innerHTML = `
    <p><strong>Page Number:</strong> ${index + 1}</p>
    <p><strong>File Number:</strong> ${file.fileNumber}</p>
    <p><strong>Page Code:</strong> ${file.fileNumber}-${(index + 1).toString().padStart(2, '0')}</p>
  `;
  
  // Show modal
  document.getElementById('qc-override-modal').classList.remove('hidden');
  document.getElementById('qc-override-modal').setAttribute('aria-hidden', 'false');
}

function hideOverrideModal() {
  document.getElementById('qc-override-modal').classList.add('hidden');
  document.getElementById('qc-override-modal').setAttribute('aria-hidden', 'true');
  document.getElementById('override-note').value = '';
  state.selectedPageInFolder = null;
}

function submitOverride() {
  const note = document.getElementById('override-note').value.trim();
  if (!note) {
    alert('Please provide a reason for the override.');
    return;
  }
  
  const index = state.selectedPageInFolder;
  if (index !== null) {
    state.processedPages[index] = { status: 'overridden', note: note };
    alert('Page override submitted successfully!');
    hideOverrideModal();
    updateUI();
  }
}
```

### 5. Add modal event listeners to initialization
In the `DOMContentLoaded` event listener, add:
```javascript
// Override modal event listeners
document.getElementById('cancel-override-btn').addEventListener('click', hideOverrideModal);
document.getElementById('override-modal-backdrop').addEventListener('click', hideOverrideModal);

// Override form submit
document.getElementById('qc-override-form').addEventListener('submit', (e) => {
  e.preventDefault();
  submitOverride();
});
```

## Expected Result
After implementing these changes:
1. When a file is selected for QC review, the QC Review tab will show the content
2. Each page will be displayed as a card with Approve/Reject/Override buttons
3. QC actions will work and update the page status
4. The override modal will function properly
5. Users can navigate back to the file list

This will complete the QC Review functionality and make the tab fully functional.