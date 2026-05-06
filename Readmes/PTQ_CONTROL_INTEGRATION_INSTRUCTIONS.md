# PTQ Control Backend Integration Instructions

## Issue Identified
The PTQ Control interface is showing dummy data because the frontend JavaScript is not properly calling the real backend APIs.

## Root Cause
1. The frontend is using hardcoded sample data arrays instead of loading from backend
2. The API calls are not being made on page load and tab switches
3. The data transformation from backend response to frontend format needs to be implemented

## Solution Implemented

### 1. Fixed PTQController Backend
- **Updated Query Logic**: Changed `listPending()` to properly query for pagetyped files that haven't been QC reviewed
- **Correct File Selection**: Files with page typings but no `qc_reviewed_at` timestamps
- **Proper Data Transformation**: Added proper data mapping for frontend consumption

### 2. Key Backend Changes Made

#### PTQController::listPending()
```php
// OLD (incorrect) - looking for qc_status = 'pending'
->whereHas('pagetypings', function($q) {
    $q->where('qc_status', 'pending');
})

// NEW (correct) - looking for pagetyped files without QC review
->whereHas('pagetypings') // Must have page typings
->whereDoesntHave('pagetypings', function($q) {
    $q->whereNotNull('qc_reviewed_at'); // No QC review done yet
})
```

#### Statistics Calculation
```php
// Updated to count files properly
'pending_qc' => FileIndexing::whereHas('pagetypings')
    ->whereDoesntHave('pagetypings', function($q) {
        $q->whereNotNull('qc_reviewed_at');
    })->count(),
```

### 3. Frontend Integration Required

#### Replace Sample Data
```javascript
// OLD - Hardcoded arrays
const pendingQCFiles = [/* hardcoded data */];

// NEW - Dynamic arrays
let pendingQCFiles = [];
let inProgressQCFiles = [];
let completedQCFiles = [];
```

#### Add API Loading Functions
```javascript
async function loadPendingFiles() {
  const response = await fetch('/ptq-control/list-pending');
  const data = await response.json();
  if (data.success) {
    pendingQCFiles = data.data.map(file => ({
      id: file.id,
      fileNumber: file.file_number,
      name: file.file_title || 'Untitled File',
      // ... other mappings
    }));
    renderPendingQCFiles();
  }
}
```

#### Update Initialization
```javascript
document.addEventListener('DOMContentLoaded', async () => {
  // Load initial data
  await loadPendingFiles();
  updateUI();
});
```

## Files That Need Updates

### 1. Backend (✅ COMPLETED)
- `app/Http/Controllers/PTQController.php` - Fixed query logic
- Database schema updates via `database_updates_ptq_workflow.sql`

### 2. Frontend (⚠️ NEEDS IMPLEMENTATION)
- `resources/views/qc/ptq_control.blade.php` - Replace dummy data with API calls

## Implementation Steps

### Step 1: Apply Database Updates
```sql
-- Run the database migration script
EXEC('database_updates_ptq_workflow.sql');
```

### Step 2: Update Frontend JavaScript
Replace the sample data section in `ptq_control.blade.php` with the API integration code from `PTQ_FRONTEND_UPDATE.js`.

### Step 3: Test the Integration
1. Navigate to PTQ Control interface
2. Verify that real pagetyped files appear in "Pending QC" tab
3. Test QC operations (approve/reject/override)
4. Verify data updates in real-time

## Expected Results After Integration

### Pending QC Tab
- Shows files that have been pagetyped (have page_typings records)
- Shows files where no pages have been QC reviewed (`qc_reviewed_at` is NULL)
- Displays real file numbers, titles, and page counts

### QC Operations
- Approve/Reject/Override operations update the database
- File status progresses through workflow correctly
- Audit trail is maintained

### Statistics
- Real-time counts of pending, in-progress, and completed QC files
- Accurate progress tracking

## Verification Checklist

- [ ] Database schema updated with QC fields
- [ ] PTQController queries return real pagetyped files
- [ ] Frontend loads data from backend APIs
- [ ] QC operations work end-to-end
- [ ] File workflow status updates correctly
- [ ] Audit trail is maintained

## Next Steps

1. **Immediate**: Update the frontend JavaScript in `ptq_control.blade.php`
2. **Testing**: Verify with real pagetyped files in the system
3. **Deployment**: Apply database updates and deploy to production

The backend is now correctly implemented and ready. The frontend just needs to be updated to call the real APIs instead of using dummy data.