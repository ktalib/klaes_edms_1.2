   \#
   
   
    Dynamic EDMS Usage Guide

## Overview
The Dynamic EDMS system has been fully implemented with interconnected File Indexing, Scanning, and Page Typing modules. Here's how to use the new dynamic system.

## File Structure Updates

### New Dynamic Files Created:
1. **File Indexing**: Updated existing files with dynamic functionality
2. **Scanning**: 
   - `resources/views/scanning/index_dynamic.blade.php` (new dynamic version)
   - `resources/views/scanning/assets/js_dynamic.blade.php` (new dynamic JavaScript)
3. **Page Typing**: 
   - `resources/views/pagetyping/index_dynamic.blade.php` (new dynamic version)
   - `resources/views/pagetyping/js/javascript_dynamic.blade.php` (new dynamic JavaScript)

### Database Updates:
- Run the SQL script: `database_updates.sql`

## How to Use the Dynamic System

### Step 1: File Indexing
1. **Access**: Navigate to `/fileindexing`
2. **Features**:
   - View real-time statistics (pending files, indexed today, total indexed)
   - See pending applications that need indexing
   - Create new file indexes with smart file number selection
   - Search and select from existing applications
   - Manual file number entry option

3. **Workflow**:
   - Click "New File Index" button
   - Choose between application selection or manual entry
   - Fill in property details and file properties
   - Save and automatically proceed to scanning

### Step 2: Scanning
1. **Access**: Navigate to `/scanning` or automatically redirected from file indexing
2. **Features**:
   - Real-time statistics (uploads today, pending page typing, total scanned)
   - File-aware document upload (linked to specific file indexes)
   - Drag and drop file upload
   - Document metadata management
   - Progress tracking with visual indicators

3. **Workflow**:
   - Select indexed file (auto-selected if coming from file indexing)
   - Upload scanned documents (PDF, images)
   - Documents are automatically organized by file_indexing_id
   - Proceed to page typing when upload is complete

### Step 3: Page Typing
1. **Access**: Navigate to `/pagetyping` or automatically redirected from scanning
2. **Features**:
   - Real-time statistics (pending, in progress, completed)
   - Document-aware page classification
   - Interactive document viewer
   - Page-by-page typing interface
   - Progress tracking and completion detection

3. **Workflow**:
   - Select file with scanned documents
   - View documents in integrated viewer
   - Classify each page with type, subtype, and metadata
   - Save individual pages or batch save
   - Complete workflow when all pages are typed

## Dynamic Features

### Seamless Navigation
- **File Indexing → Scanning**: Automatic redirect with file_indexing_id
- **Scanning → Page Typing**: Automatic redirect with file_indexing_id
- **Cross-module Links**: Each module can link to the next step

### Real-time Data
- **Statistics**: Live counts from database
- **File Lists**: Dynamic loading from database
- **Status Tracking**: Real-time workflow status updates
- **Search**: AJAX-powered search across all modules

### Workflow Integration
- **File Selection**: Smart dropdowns with application data
- **Document Organization**: Files organized by file_indexing_id
- **Status Updates**: Automatic status changes (pending → scanned → typed)
- **Progress Tracking**: Visual progress indicators

## API Endpoints

### File Indexing
- `GET /fileindexing` - Dashboard with statistics
- `POST /fileindexing/store` - Create new file index
- `GET /fileindexing/search/applications` - Search available applications
- `GET /fileindexing/list/file-indexings` - Get file indexing list

### Scanning
- `GET /scanning?file_indexing_id=X` - Dashboard with pre-selected file
- `POST /scanning/upload` - Upload documents
- `GET /scanning/list/scanned-files` - Get scanned files list
- `PUT /scanning/update-details/{id}` - Update document metadata

### Page Typing
- `GET /pagetyping?file_indexing_id=X` - Dashboard with pre-selected file
- `POST /pagetyping/store` - Batch save page classifications
- `POST /pagetyping/save-single` - Save single page classification
- `GET /pagetyping/list/page-typings` - Get page typings list

## URL Parameters

### Cross-Module Navigation
- **To Scanning**: `/scanning?file_indexing_id=123`
- **To Page Typing**: `/pagetyping?file_indexing_id=123`
- **From File Indexing**: Automatic redirect after creation

### Search and Filtering
- **File Status**: `?status=indexed|scanned|typed`
- **Search Term**: `?search=file_number_or_title`
- **File Indexing**: `?file_indexing_id=123`

## Database Relationships

### Core Tables
```sql
file_indexings (main anchor)
├── main_application_id → mother_applications.id
├── subapplication_id → subapplications.id
└── file_number (unique identifier)

scannings
├── file_indexing_id → file_indexings.id
├── document_path (file storage path)
└── status (pending/scanned/typed)

pagetypings
├── file_indexing_id → file_indexings.id
├── scanning_id → scannings.id
└── page_number, page_type, etc.
```

### Status Flow
1. **File Indexing**: Creates file_indexings record
2. **Scanning**: Creates scannings records, status = 'pending'
3. **Page Typing**: Creates pagetypings records, updates scanning status = 'typed'

## Error Handling

### Validation
- **File Upload**: Type and size validation
- **Form Data**: Required field validation
- **Relationships**: Foreign key validation

### User Feedback
- **Success Messages**: Clear confirmation of actions
- **Error Messages**: Specific error descriptions
- **Loading States**: Visual feedback during operations
- **Progress Indicators**: Real-time progress updates

## Testing the System

### 1. Complete Workflow Test
1. Go to `/fileindexing`
2. Create new file index
3. Should redirect to `/scanning?file_indexing_id=X`
4. Upload documents
5. Should show option to proceed to page typing
6. Go to `/pagetyping?file_indexing_id=X`
7. Complete page typing

### 2. Individual Module Tests
- **File Indexing**: Test application search and manual entry
- **Scanning**: Test file selection and document upload
- **Page Typing**: Test document viewing and page classification

### 3. Data Validation Tests
- **Statistics**: Verify counts match database
- **Search**: Test search functionality
- **Status Updates**: Verify status changes correctly

## Troubleshooting

### Common Issues
1. **Missing Statistics**: Check database connection and table names
2. **Upload Failures**: Verify file permissions and storage configuration
3. **Navigation Issues**: Check route definitions and parameters
4. **JavaScript Errors**: Check browser console for errors

### Debug Steps
1. Check Laravel logs for server errors
2. Check browser console for JavaScript errors
3. Verify database tables have required fields
4. Test API endpoints directly

## Migration from Static to Dynamic

### To Use New Dynamic Files:
1. **Scanning**: Use `index_dynamic.blade.php` instead of `index.blade.php`
2. **Page Typing**: Use `index_dynamic.blade.php` instead of `index.blade.php`
3. **Update Routes**: Ensure routes point to updated controllers
4. **Run Database Updates**: Execute `database_updates.sql`

### Backup Considerations:
- Original files are preserved with their original names
- New dynamic files have `_dynamic` suffix
- Can switch back to static files if needed

This dynamic system provides a complete, integrated EDMS workflow with seamless navigation between all three major components.