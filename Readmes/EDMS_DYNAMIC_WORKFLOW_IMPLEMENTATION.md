# Dynamic EDMS Workflow Implementation

## Overview
This implementation transforms the static File Indexing, Scanning, and Page Typing modules into a fully dynamic, interconnected Electronic Document Management System (EDMS) workflow.

## Workflow Architecture

```
Select File Number (Dropdown or Manual Entry)
                    ↓
            file_indexings (Main anchor)
                    ↓
        ┌───────────┬─────────────┐
        │           │             │
    Scanning   Page Typing (via file_indexing_id)
```

## Key Features

### 1. Dynamic File Indexing
- **Smart File Number Selection**: Choose from existing applications or enter manually
- **Application Integration**: Automatically pulls data from mother_applications table
- **Validation**: Prevents duplicate indexing for the same application
- **Status Tracking**: Real-time workflow status updates

### 2. Connected Scanning Module
- **File-Based Upload**: Documents are organized by file_indexing_id
- **Auto-Detection**: Automatic paper size and document type detection
- **Metadata Management**: Rich document metadata including notes and classifications
- **Progress Tracking**: Visual progress indicators and status updates

### 3. Integrated Page Typing
- **Document-Aware**: Loads scanned documents for classification
- **Page-Level Typing**: Individual page classification with metadata
- **Batch Operations**: Support for bulk page typing operations
- **Completion Tracking**: Automatic workflow completion detection

## Database Schema Updates

### file_indexings Table
```sql
- main_application_id (FK to mother_applications)
- subapplication_id (FK to subapplications) 
- st_fillno (nullable)
- file_number (from application or manual)
- file_title
- land_use_type
- plot_number
- district
- lga
- has_cofo (boolean)
- is_merged (boolean)
- has_transaction (boolean)
- is_problematic (boolean)
- is_co_owned_plot (boolean)
```

### scannings Table
```sql
- file_indexing_id (FK)
- document_path
- uploaded_by (FK to users)
- status (pending/scanned/typed)
- original_filename
- paper_size (A3/A4/A5/Letter/Legal/Custom)
- document_type (Certificate/Deed/Letter/etc.)
- notes
```

### pagetypings Table
```sql
- file_indexing_id (FK)
- page_type
- page_subtype
- serial_number
- page_code
- file_path
- typed_by (FK to users)
- page_number
- scanning_id (FK to scannings)
```

## Controller Enhancements

### FileIndexController
- **Dynamic Dashboard**: Real-time statistics and recent activity
- **Application Search**: AJAX-powered application lookup
- **CRUD Operations**: Full create, read, update, delete functionality
- **Workflow Integration**: Seamless navigation to next steps

### ScanningController
- **File-Aware Uploads**: Documents linked to specific file indexings
- **Batch Processing**: Multiple document upload with progress tracking
- **Document Management**: View, edit, and delete scanned documents
- **Auto-Classification**: Intelligent document type detection

### PageTypingController
- **Document Integration**: Loads scanned documents for typing
- **Page-Level Operations**: Individual and batch page classification
- **Progress Tracking**: Real-time completion status
- **Workflow Completion**: Automatic status updates

## API Endpoints

### File Indexing
- `GET /fileindexing` - Dashboard with statistics
- `POST /fileindexing/store` - Create new file index
- `GET /fileindexing/search/applications` - Search available applications
- `GET /fileindexing/list/file-indexings` - Get file indexing list

### Scanning
- `POST /scanning/upload` - Upload scanned documents
- `PUT /scanning/update-details/{id}` - Update document metadata
- `GET /scanning/list/scanned-files` - Get scanned files list

### Page Typing
- `POST /pagetyping/store` - Batch save page classifications
- `POST /pagetyping/save-single` - Save single page classification
- `GET /pagetyping/list/page-typings` - Get page typings list

## Workflow Navigation

### Step 1: File Indexing
1. Select file number from dropdown or enter manually
2. Fill in property details and file properties
3. Save and proceed to scanning

### Step 2: Scanning
1. Select indexed file (auto-selected if coming from step 1)
2. Upload scanned documents (PDF, images)
3. Review and organize uploaded documents
4. Proceed to page typing

### Step 3: Page Typing
1. Select file with scanned documents
2. Classify each page with type, subtype, and metadata
3. Save classifications (individual or batch)
4. Complete workflow

## Status Tracking

### File Indexing Status
- **Indexed**: File index created, no documents uploaded
- **Scanned**: Documents uploaded, no page typing
- **Typed**: Page typing completed

### Document Status
- **Pending**: Uploaded but not processed
- **Scanned**: Uploaded and organized
- **Typed**: Page typing completed

## Security & Validation

### Input Validation
- File type restrictions (PDF, JPG, PNG, TIFF)
- File size limits (20MB per file)
- Required field validation
- Data type validation

### Access Control
- User authentication required
- Role-based access control
- Activity logging
- Audit trails

## Performance Optimizations

### Database Indexes
- Foreign key indexes for joins
- Status indexes for filtering
- Composite indexes for common queries

### Caching
- Application data caching
- File metadata caching
- Statistics caching

### File Storage
- Organized directory structure
- Unique filename generation
- Storage path optimization

## Error Handling

### Graceful Degradation
- Fallback for missing data
- Error recovery mechanisms
- User-friendly error messages

### Logging
- Comprehensive error logging
- Activity tracking
- Performance monitoring

## Future Enhancements

### Planned Features
- PDF page thumbnail generation
- OCR text extraction
- Document search functionality
- Workflow automation
- Reporting and analytics

### Integration Points
- Document versioning
- Digital signatures
- Workflow notifications
- External system integration

## Installation & Setup

1. **Database Updates**: Run the SQL script in `database_updates.sql`
2. **File Permissions**: Ensure storage directories are writable
3. **Dependencies**: Verify all required packages are installed
4. **Configuration**: Update file upload limits if needed

## Usage Guidelines

### Best Practices
1. Always start with file indexing
2. Upload documents in logical batches
3. Use consistent naming conventions
4. Complete page typing promptly
5. Review and verify data accuracy

### Troubleshooting
- Check file permissions for upload issues
- Verify database connections
- Review error logs for debugging
- Ensure proper route configuration

This implementation provides a robust, scalable foundation for the EDMS workflow with seamless integration between all three major components.