# File Tracker Backend Implementation - KLAES Lands Module

## Overview

This implementation provides a comprehensive backend for File Tracker with MS SQL integration for the KLAES Lands Module. The system supports real-time tracking of physical land files using both RFID and manual updates.

## Database Schema
 
### Table: `file_trackings`

```sql
CREATE TABLE dbo.file_trackings (
    [id] INT IDENTITY(1,1) PRIMARY KEY,
    [file_indexing_id] INT NOT NULL,
    [rfid_tag] VARCHAR(100) NULL,
    [qr_code] VARCHAR(100) NULL,
    [current_location] VARCHAR(255) NULL,
    [current_holder] VARCHAR(255) NULL,
    [current_handler] VARCHAR(255) NULL,
    [date_received] DATETIME NULL,
    [due_date] DATETIME NULL,
    [status] VARCHAR(50) NOT NULL DEFAULT 'active',
    [movement_history] TEXT NULL,
    [created_at] DATETIME NOT NULL DEFAULT GETDATE(),
    [updated_at] DATETIME NULL,
    
    -- Constraints and indexes included
);
```

**Status Values:**
- `active` - File is available and being tracked
- `checked_out` - File has been checked out to someone
- `overdue` - File is past its due date
- `returned` - File has been returned
- `lost` - File is reported as lost
- `archived` - File has been archived

## API Endpoints

### Main File Tracking Endpoints

#### 1. List All Tracked Files
```http
GET /api/file-trackings
```

**Query Parameters:**
- `status` - Filter by status (active, checked_out, overdue, etc.)
- `location` - Filter by current location
- `handler` - Filter by current handler
- `overdue` - Filter overdue files (true/false)
- `active` - Filter active files (true/false)
- `search` - Search by file number or title
- `sort_by` - Sort field (default: created_at)
- `sort_order` - Sort order (asc/desc, default: desc)
- `per_page` - Items per page (default: 15)

**Response:**
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "file_indexing_id": 123,
                "rfid_tag": "RFID2501131234ABCD",
                "qr_code": "QR001",
                "current_location": "Archive Room A",
                "current_holder": "Musa Ali",
                "current_handler": "Jane Smith",
                "date_received": "2025-01-13T10:00:00Z",
                "due_date": "2025-02-13T10:00:00Z",
                "status": "active",
                "movement_history": [...],
                "is_overdue": false,
                "days_until_due": 31,
                "file_indexing": {
                    "id": 123,
                    "file_number": "LND/2025/001",
                    "file_title": "Sample Land File"
                }
            }
        ],
        "total": 50
    },
    "message": "File trackings retrieved successfully"
}
```

#### 2. Register New File Tracking
```http
POST /api/file-trackings
```

**Request Body:**
```json
{
    "file_indexing_id": 123,
    "rfid_tag": "RFID2501131234ABCD",
    "qr_code": "QR001",
    "current_location": "Archive Room A",
    "current_holder": "Musa Ali",
    "current_handler": "Jane Smith",
    "date_received": "2025-01-13T10:00:00Z",
    "due_date": "2025-02-13T10:00:00Z",
    "status": "active"
}
```

#### 3. Get File Tracking Details
```http
GET /api/file-trackings/{id}
```

#### 4. Update File Tracking
```http
PUT /api/file-trackings/{id}
```

**Request Body:**
```json
{
    "current_location": "Legal Department",
    "current_handler": "Mike Johnson",
    "status": "checked_out",
    "reason": "File transferred for legal review"
}
```

#### 5. Add Movement Entry
```http
POST /api/file-trackings/{id}/move
```

**Request Body:**
```json
{
    "action": "location_change",
    "from_location": "Archive Room A",
    "to_location": "Legal Department",
    "from_handler": "Jane Smith",
    "to_handler": "Mike Johnson",
    "reason": "Legal review required",
    "notes": "Urgent case - priority handling"
}
```

### RFID Integration Endpoints

#### 1. Register RFID Tag
```http
POST /api/rfid/register
```

**Request Body:**
```json
{
    "file_indexing_id": 123,
    "rfid_tag": "RFID2501131234ABCD"
}
```

#### 2. Scan RFID Tag
```http
GET /api/rfid/scan/{tag}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "file_indexing_id": 123,
        "rfid_tag": "RFID2501131234ABCD",
        "current_location": "Archive Room A",
        "current_handler": "Jane Smith",
        "status": "active",
        "is_overdue": false,
        "days_until_due": 31,
        "file_indexing": {
            "file_number": "LND/2025/001",
            "file_title": "Sample Land File"
        },
        "movement_history": [...]
    },
    "message": "File tracking retrieved by RFID scan"
}
```

#### 3. Generate Reports
```http
GET /api/rfid/report?type=summary
```

**Report Types:**
- `summary` - Overall statistics
- `overdue` - Overdue files report
- `movement` - Movement history report
- `location` - Files by location
- `handler` - Files by handler

### Batch Operations

#### 1. Batch Update Overdue Files
```http
POST /api/file-trackings/batch/overdue
```

**Request Body:**
```json
{
    "action": "extend_due_date",
    "tracking_ids": [1, 2, 3, 4],
    "new_due_date": "2025-03-15T10:00:00Z",
    "reason": "Extension approved by supervisor"
}
```

**Actions:**
- `mark_overdue` - Mark files as overdue
- `extend_due_date` - Extend due dates
- `return_files` - Mark files as returned

## Models and Relationships

### FileTracking Model

**Key Features:**
- Automatic movement history tracking
- Status transition validation
- Overdue detection
- Relationship with FileIndexing

**Key Methods:**
- `addMovementEntry()` - Add movement to history
- `updateLocation()` - Update location with history
- `updateHandler()` - Update handler with history
- `updateStatus()` - Update status with history

### FileIndexing Model (Updated)

**New Relationships:**
- `fileTracking()` - One-to-one relationship
- `getTrackingStatusAttribute()` - Get tracking status
- `getIsTrackedAttribute()` - Check if file is tracked

## Services

### FileTrackingValidationService

Provides comprehensive validation for:
- RFID tag uniqueness and format
- QR code validation
- Due date validation
- Status transition validation
- Movement data validation
- Batch operation validation

### RfidService

Provides RFID-specific functionality:
- RFID tag generation
- Tag registration
- Tag scanning with history
- Location/handler updates via RFID
- Check-in/check-out operations
- Scan statistics
- Bulk operations

## Installation and Setup

### 1. Database Setup

Run the SQL script to create the table:
```bash
# Execute the SQL file in your MS SQL Server
sqlcmd -S your_server -d your_database -i database/sql/create_file_trackings_table.sql
```

### 2. Model Registration

The models are automatically loaded by Laravel's autoloader.

### 3. API Routes

The routes are defined in `routes/api.php` and are automatically registered.

### 4. Permissions

Ensure your application has proper authentication middleware configured for the API routes.

## Usage Examples

### 1. Register a File for Tracking

```php
use App\Models\FileTracking;

$tracking = FileTracking::create([
    'file_indexing_id' => 123,
    'rfid_tag' => 'RFID2501131234ABCD',
    'current_location' => 'Archive Room A',
    'current_handler' => 'Jane Smith',
    'status' => 'active',
    'date_received' => now(),
    'due_date' => now()->addDays(30)
]);
```

### 2. Update File Location

```php
$tracking = FileTracking::find(1);
$tracking->updateLocation('Legal Department', 'Transferred for review');
```

### 3. Scan RFID Tag

```php
use App\Services\RfidService;

$tracking = RfidService::scanTag('RFID2501131234ABCD');
if ($tracking) {
    echo "File found: " . $tracking->fileIndexing->file_number;
}
```

### 4. Generate Reports

```php
use App\Http\Controllers\FileTrackingController;

$controller = new FileTrackingController();
$report = $controller->generateReport(request(['type' => 'overdue']));
```

## Security Features

1. **Input Validation** - All inputs are validated using Laravel's validation system
2. **SQL Injection Prevention** - Using Eloquent ORM prevents SQL injection
3. **RFID Tag Uniqueness** - Enforced at database and application level
4. **Movement History** - Immutable audit trail of all changes
5. **User Tracking** - All actions are logged with user information

## Performance Considerations

1. **Database Indexes** - Proper indexes on frequently queried fields
2. **Pagination** - All list endpoints support pagination
3. **Caching** - RFID scan data is cached for performance
4. **Movement History Limit** - Limited to 100 entries per file to prevent excessive data

## Error Handling

All endpoints return consistent error responses:

```json
{
    "success": false,
    "message": "Error description",
    "errors": {
        "field_name": ["Validation error message"]
    }
}
```

## Logging

All operations are logged using Laravel's logging system:
- File tracking creation/updates
- RFID operations
- Batch operations
- Error conditions

## Testing

The system includes comprehensive validation and error handling. Test with:

1. **Valid Data** - Ensure normal operations work
2. **Invalid Data** - Test validation rules
3. **Edge Cases** - Test overdue detection, status transitions
4. **RFID Operations** - Test tag scanning and registration
5. **Batch Operations** - Test bulk updates

## Future Enhancements

1. **Real-time Notifications** - WebSocket integration for real-time updates
2. **Mobile App Integration** - API endpoints are ready for mobile consumption
3. **Advanced Reporting** - More detailed analytics and reports
4. **Integration with Physical RFID Readers** - Hardware integration capabilities
5. **Automated Overdue Processing** - Scheduled tasks for overdue file management

## Support

For issues or questions regarding this implementation, refer to:
- Laravel documentation for framework-specific questions
- SQL Server documentation for database-related issues
- The codebase comments for implementation details