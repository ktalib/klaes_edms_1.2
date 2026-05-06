# Page Typing Workflow Documentation

## Overview

The Page Typing workflow is a comprehensive document management system that handles both PDF and image files. It automatically splits PDFs into individual pages and provides a unified interface for categorizing and typing document content.

## Key Features

### 1. **Automatic File Processing**
- **PDF Splitting**: PDFs are automatically split into individual pages using FPDI
- **Image Handling**: Images are processed as single-page documents
- **Combined File Retention**: Original combined PDFs are preserved for reference
- **Storage Organization**: Files are organized in `/storage/app/public/EDMS/PAGETYPING/{FileNo}/`

### 2. **Unified Page Classification**
- **Cover Types**: Front Cover (FC) and Back Cover (BC) classification
- **Page Types**: Application, Bill Notice, Correspondence, Legal, etc.
- **Page Subtypes**: Detailed categorization within each page type
- **Serial Numbering**: Automatic serial number generation and management

### 3. **Upload More Functionality**
- **Incremental Updates**: Handle additional file uploads for existing files
- **Source Tracking**: Mark pages with `source=upload_more` for new additions
- **Database Consistency**: Maintain referential integrity across updates

### 4. **Quality Control Integration**
- **QC Status Tracking**: Pending, Passed, Failed status for each page
- **Override Capabilities**: QC override with notes and justification
- **Audit Trail**: Complete history of QC actions and decisions

## Technical Architecture

### File Structure
```
storage/app/public/EDMS/
├── PAGETYPING/
│   └── {FileNo}/
│       ├── combined.pdf (original PDF)
│       ├── page_001.pdf (split page 1)
│       ├── page_002.pdf (split page 2)
│       └── page_001.jpg (image files)
├── THUMBNAILS/ (generated thumbnails)
└── TEMP/ (temporary processing files)
```

### Database Schema

#### `page_typings` Table
- `file_indexing_id`: Reference to file indexing record
- `scanning_id`: Reference to original scanning record
- `page_number`: Sequential page number
- `page_type`: Classification type
- `page_subtype`: Sub-classification
- `serial_number`: Unique serial within file
- `page_code`: Generated code (CoverType-PageType-SubType-Serial)
- `file_path`: Path to processed page file
- `source`: Origin of page (manual, pdf_split, image_copy, upload_more)
- `qc_status`: Quality control status
- `typed_by`: User who performed the typing

### API Endpoints

#### Core Endpoints
- `GET /pagetyping/api/stats` - Dashboard statistics
- `GET /pagetyping/api/files` - Files by status (pending, in-progress, completed)
- `GET /pagetyping/api/file-details` - Detailed file information
- `POST /pagetyping/api/process-file` - Process file for page typing
- `POST /pagetyping/save-single` - Save single page classification

#### File Processing
- `POST /pagetyping/api/split-pdf` - Split PDF into pages
- `POST /pagetyping/api/handle-upload-more` - Handle additional uploads
- `GET /pagetyping/api/file-preview/{id}` - Get file preview information

#### Data Management
- `GET /pagetyping/api/typing-data` - Cover types, page types, subtypes
- `GET /pagetyping/api/next-serial-for-page-type` - Calculate next serial number

## Workflow Process

### 1. **File Upload and Scanning**
Files are uploaded through the Scanning module and stored with CDN links.

### 2. **Page Typing Initiation**
When a user clicks "Start Typing" on a pending file:
1. System checks if files have been processed
2. If not processed, automatically splits PDFs or copies images
3. Creates individual page files in the PAGETYPING directory
4. Updates file status and prepares for classification

### 3. **Page Classification**
For each page, users can:
- Select Cover Type (Front/Back Cover)
- Choose Page Type (Application, Legal, etc.)
- Select Page Subtype (specific document type)
- Assign Serial Number (auto-calculated)
- Generate Page Code (automatic format: FC-APP-CO-01)

### 4. **Database Storage**
Each classified page creates a record in `page_typings` with:
- Complete metadata and classification
- File path references
- User tracking information
- QC status initialization

### 5. **Upload More Handling**
When additional files are uploaded to an existing file:
1. System detects `is_updated=1` flag
2. Processes new files alongside existing ones
3. Marks new pages with `source=upload_more`
4. Maintains continuity of serial numbering

## User Interface

### Dashboard Tabs
1. **Pending Page Typing**: Files awaiting initial processing
2. **In Progress**: Partially typed files
3. **Completed**: Fully typed files
4. **PageType More**: Files with additional uploads
5. **Typing**: Active typing interface

### Typing Interface Features
- **Folder View**: Visual grid of all pages in a file
- **Page Preview**: Document viewer with zoom and rotation
- **Classification Form**: Dropdowns for all classification options
- **Progress Tracking**: Visual indicators for completed pages
- **Batch Operations**: Process multiple pages efficiently

## Error Handling

### File Processing Errors
- **Corrupt PDFs**: Logged with error details, user notification
- **Missing Files**: Graceful fallback with error messages
- **Permission Issues**: Clear error reporting and resolution steps

### Database Errors
- **Transaction Rollback**: Automatic rollback on save failures
- **Constraint Violations**: Validation and user feedback
- **Connection Issues**: Retry logic and error reporting

## Configuration

### Required PHP Extensions
- **GD**: Image processing and thumbnail generation
- **FileInfo**: File type detection and validation
- **ZIP**: Archive handling capabilities

### Storage Requirements
- **Disk Space**: Adequate space for split PDF files
- **Permissions**: Write access to storage directories
- **Backup**: Regular backup of PAGETYPING directory

### Performance Optimization
- **Lazy Loading**: Load files only when needed
- **Caching**: Cache frequently accessed data
- **Batch Processing**: Handle multiple operations efficiently

## Installation

### Automatic Installation
Run the installation script:
```bash
php install_pagetyping.php
```

### Manual Installation
1. Install FPDI library:
   ```bash
   composer require setasign/fpdi
   ```

2. Create storage directories:
   ```bash
   mkdir -p storage/app/public/EDMS/{PAGETYPING,THUMBNAILS,TEMP}
   ```

3. Run database migration:
   ```bash
   php artisan migrate
   ```

4. Create storage link:
   ```bash
   php artisan storage:link
   ```

## Troubleshooting

### Common Issues

#### PDF Splitting Fails
- **Cause**: FPDI library not installed or corrupt PDF
- **Solution**: Reinstall FPDI, check PDF file integrity

#### File Upload Errors
- **Cause**: Storage permissions or disk space
- **Solution**: Check directory permissions, free up disk space

#### Database Connection Issues
- **Cause**: SQL Server connection problems
- **Solution**: Verify database configuration, check connection

#### Thumbnail Generation Fails
- **Cause**: GD extension not enabled
- **Solution**: Enable GD extension in PHP configuration

### Debug Tools
- **Test Routes**: Access `/pagetyping/test-routes` for diagnostics
- **PDF Diagnostic**: Use `/pagetyping/pdf-diagnostic` for PDF issues
- **Database Debug**: Check `/pagetyping/debug-database` for DB issues

## Security Considerations

### File Access Control
- **Authentication**: All routes require user authentication
- **Authorization**: Role-based access to page typing functions
- **File Validation**: Strict file type and size validation

### Data Protection
- **Input Sanitization**: All user inputs are validated and sanitized
- **SQL Injection Prevention**: Parameterized queries and ORM usage
- **XSS Protection**: Output encoding and CSRF protection

## Maintenance

### Regular Tasks
- **Storage Cleanup**: Remove temporary files periodically
- **Database Optimization**: Regular index maintenance
- **Backup Verification**: Ensure backup processes are working

### Monitoring
- **Error Logs**: Monitor application logs for issues
- **Performance Metrics**: Track processing times and success rates
- **Storage Usage**: Monitor disk space utilization

## Support and Documentation

### Additional Resources
- **API Documentation**: Detailed endpoint specifications
- **User Manual**: Step-by-step user guide
- **Video Tutorials**: Visual workflow demonstrations

### Getting Help
- **Error Logs**: Check Laravel logs for detailed error information
- **Debug Mode**: Enable debug mode for development troubleshooting
- **Community Support**: Laravel and FPDI community resources

---

This documentation provides a comprehensive overview of the Page Typing workflow. For specific implementation details, refer to the source code and inline comments.