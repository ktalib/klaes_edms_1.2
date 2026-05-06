# ST File Number Generation Instructions

## Overview

This document provides comprehensive instructions for implementing the backend logic for file number generation in the KLAES GIS EDMS system. The system handles three types of applications:

- **Primary Applications** - Generate new primary file numbers (NPFN)
- **SUA (Standalone Unit Applications)** - Independent unit applications with their own primary file numbers
- **PUA (Parented Unit Applications)** - Unit applications that inherit from existing primary file numbers

## Current Implementation Analysis

### Existing Services

#### 1. FileNumberReservationService
**Location:** `app\Services\FileNumberReservationService.php`

**Key Features:**
- Implements atomic file number reservation to prevent race conditions
- Uses gap-filling strategy to reuse expired/released reservations
- Manages reservation lifecycle (reserved → used/expired/released)
- Supports 3-day expiry for reservations
- Tracks reservations in `file_number_reservations` table

**Current Format:** `NPFN-{YEAR}-{LAND_USE_CODE}-{SERIAL}`
- Example: `NPFN-2025-RES-0001`

**Issues Identified:**
- Format doesn't match required ST format (`ST-{LAND_USE}-{YEAR}-{SERIAL}`)
- Gap-filling logic is complex and may cause user confusion
- Reservation expiry management needs improvement

#### 2. SUAFileNumberService
**Location:** `app\Services\SUAFileNumberService.php`

**Key Features:**
- Generates SUA file numbers in correct ST format
- Manages sequence numbering per land use type
- Stores records in `sua_file_numbers` table
- Handles unit sequence numbering (always 001 for SUA)

**Current Format:** `ST-{LAND_USE_CODE}-{YEAR}-{SEQUENCE}-{UNIT_SEQUENCE}`
- Main: `ST-RES-2025-1`
- SUA: `ST-RES-2025-1-001`

### Current Database Tables

#### 1. land_use_serials
**Purpose:** Tracks current serial numbers per land use type
**Columns:**
- `land_use_type` (COMMERCIAL, RESIDENTIAL, INDUSTRIAL, MIXED)
- `prefix` (ST-COM, ST-RES, ST-IND, ST-MIXED)
- `year`
- `current_serial`
- `created_at`, `updated_at`

#### 2. sua_file_numbers
**Purpose:** Stores SUA file number records
**Columns:**
- `main_file_number`
- `sua_file_number`
- `mls_file_number`
- `land_use_code`
- `land_use_full`
- `year`
- `sequence_number`
- `unit_sequence`
- `status`
- `subapplication_id`
- `mother_application_id`
- `is_auto_generated`
- `generation_method`
- `notes`
- `created_by`
- `created_at`, `updated_at`

#### 3. file_number_reservations
**Purpose:** Manages file number reservations to prevent duplicates
**Columns:**
- `file_number`
- `land_use_type`
- `serial_number`
- `year`
- `status` (reserved, used, expired, released)
- `draft_id`
- `application_id`
- `reserved_at`, `expires_at`
- `used_at`, `released_at`
- `created_at`, `updated_at`

## Proposed Improved Implementation

### Unified File Number Service

Create a new comprehensive service: `STFileNumberService.php`

**Key Improvements:**
1. **Unified Format:** All file numbers use `ST-{LAND_USE}-{YEAR}-{SERIAL}` format
2. **Simplified Logic:** Remove complex gap-filling, use sequential numbering
3. **Better Separation:** Clear distinction between Primary, SUA, and PUA logic
4. **Atomic Operations:** Ensure thread-safe number generation
5. **Better Tracking:** Comprehensive audit trail and relationship tracking

### Proposed Table Structure

#### New Table: st_file_numbers
**Purpose:** Central registry for all ST file numbers and their relationships

**Columns:**
- `id` (Primary Key)
- `np_fileno` (Primary file number, e.g., ST-RES-2025-1)
- `fileno` (Unit file number, e.g., ST-RES-2025-1-001, or same as np_fileno for primary)
- `mls_fileno` (MLS file number, typically same as np_fileno)
- `land_use` (Full name: Residential, Commercial, Industrial, Mixed)
- `land_use_code` (RES, COM, IND, MIXED)
- `serial_no` (Sequential number within land use/year)
- `unit_sequence` (NULL for primary, 001+ for units)
- `year`
- `file_no_type` (PRIMARY, SUA, PUA)
- `parent_id` (References id of parent record for PUA applications)
- `mother_application_id` (FK to mother_applications)
- `subapplication_id` (FK to subapplications)
- `status` (RESERVED, ACTIVE, USED, CANCELLED)
- `reserved_at`
- `expires_at` (For reservations)
- `used_at` (When application was submitted)
- `tra` (Transaction Reference)
- `applicant_type` (Individual, Corporate, Multiple)
- `applicant_title`
- `first_name`
- `surname`
- `corporate_name`
- `rc_number`
- `multiple_owners_names`
- `created_by`
- `created_at`, `updated_at`

### Service Methods

#### Primary File Number Generation
```php
public function generatePrimaryFileNumber(string $landUse, array $applicantData): array
```
- Generates new primary file number
- Creates record with `file_no_type = 'PRIMARY'`
- Returns reservation that expires in 24 hours

#### SUA File Number Generation
```php
public function generateSUAFileNumber(string $landUse, array $applicantData): array
```
- Generates both primary and unit file numbers for standalone applications
- Creates record with `file_no_type = 'SUA'`
- Unit sequence is always 001

#### PUA File Number Generation
```php
public function generatePUAFileNumber(string $parentFileNumber, array $applicantData): array
```
- Generates unit file number from existing primary file number
- Extracts land use, year, serial from parent
- Finds next available unit sequence
- Creates record with `file_no_type = 'PUA'` and `parent_id`

#### Utility Methods
```php
public function reserveFileNumber(string $type, array $params): array
public function confirmReservation(string $fileNumber, int $applicationId): bool
public function releaseReservation(string $fileNumber): bool
public function getFileNumberDetails(string $fileNumber): ?object
public function getUnitsByParent(string $parentFileNumber): array
```

### File Number Formats

#### Primary Applications
- **Format:** `ST-{LAND_USE}-{YEAR}-{SERIAL}`
- **Example:** `ST-RES-2025-1`

#### SUA Applications
- **Primary:** `ST-{LAND_USE}-{YEAR}-{SERIAL}`
- **Unit:** `ST-{LAND_USE}-{YEAR}-{SERIAL}-001`
- **Example:** Primary: `ST-COM-2025-5`, Unit: `ST-COM-2025-5-001`

#### PUA Applications
- **Primary:** Inherited from parent
- **Unit:** `ST-{LAND_USE}-{YEAR}-{SERIAL}-{UNIT_SEQUENCE}`
- **Example:** Parent: `ST-RES-2025-1`, Units: `ST-RES-2025-1-001`, `ST-RES-2025-1-002`

### Land Use Mapping

```php
private function normalizeLandUse(string $landUse): array
{
    return match(strtoupper(trim($landUse))) {
        'COMMERCIAL', 'COMMERCIAL USE' => ['full' => 'Commercial', 'code' => 'COM'],
        'INDUSTRIAL', 'INDUSTRIAL USE' => ['full' => 'Industrial', 'code' => 'IND'],
        'RESIDENTIAL', 'RESIDENTIAL USE' => ['full' => 'Residential', 'code' => 'RES'],
        'MIXED', 'MIXED USE' => ['full' => 'Mixed', 'code' => 'MIXED'],
        default => ['full' => 'Residential', 'code' => 'RES']
    };
}
```

### Serial Number Management

Each land use type maintains its own independent serial sequence:
- **Residential:** ST-RES-2025-1, ST-RES-2025-2, ST-RES-2025-3...
- **Commercial:** ST-COM-2025-1, ST-COM-2025-2, ST-COM-2025-3...
- **Industrial:** ST-IND-2025-1, ST-IND-2025-2, ST-IND-2025-3...
- **Mixed:** ST-MIXED-2025-1, ST-MIXED-2025-2, ST-MIXED-2025-3...

Each new year resets the sequence to 1 for all land use types.

### Integration Points

#### Controllers
Update these controllers to use the new service:
- `PrimaryFormController` - For primary applications
- `SubActionsController` - For unit applications
- `SUAController` - For standalone applications
- `PrimaryFormDraftController` - For draft management

#### Frontend Integration
- **AJAX Endpoints:** Create RESTful endpoints for file number operations
- **Real-time Updates:** Use HTMX or JavaScript to update forms dynamically
- **Validation:** Client-side validation with server-side confirmation
- **Error Handling:** SweetAlert notifications for all operations

#### Example AJAX Endpoints
```php
Route::post('/api/file-numbers/reserve-primary', [STFileNumberController::class, 'reservePrimary']);
Route::post('/api/file-numbers/reserve-sua', [STFileNumberController::class, 'reserveSUA']);
Route::post('/api/file-numbers/reserve-pua', [STFileNumberController::class, 'reservePUA']);
Route::post('/api/file-numbers/confirm/{fileNumber}', [STFileNumberController::class, 'confirm']);
Route::delete('/api/file-numbers/release/{fileNumber}', [STFileNumberController::class, 'release']);
```

### Migration Strategy

1. **Create new table:** `st_file_numbers`
2. **Migrate existing data:** From `sua_file_numbers` and `land_use_serials`
3. **Update services:** Implement new `STFileNumberService`
4. **Update controllers:** Replace old service calls
5. **Update frontend:** Modify forms to use new AJAX endpoints
6. **Deprecate old tables:** After successful migration and testing

### Error Handling & Validation

#### Server-side Validation
- Land use type must be valid
- File numbers must follow correct format
- Parent file numbers must exist for PUA applications
- No duplicate file numbers allowed

#### Client-side Feedback
- Use SweetAlert for all notifications
- Show loading states during AJAX requests
- Provide clear error messages
- Display file number immediately upon generation

### Testing Requirements

#### Unit Tests
- Test file number generation for all types
- Test serial number sequencing
- Test reservation lifecycle
- Test error conditions

#### Integration Tests
- Test AJAX endpoints
- Test form submissions
- Test concurrent access scenarios
- Test database consistency

#### User Acceptance Tests
- Test file number display in forms
- Test application submission workflow
- Test error handling scenarios
- Test performance under load

### Performance Considerations

#### Database Optimization
- Index on `land_use_code`, `year`, `serial_no`
- Index on `np_fileno`, `fileno`
- Index on `status`, `reserved_at`, `expires_at`

#### Caching Strategy
- Cache current serial numbers per land use/year
- Cache frequently accessed file number details
- Use Redis for reservation locks

#### Concurrent Access
- Use database transactions for atomic operations
- Implement retry logic for failed reservations
- Use appropriate locking mechanisms

### Security Considerations

#### Access Control
- Verify user permissions before generating file numbers
- Log all file number operations
- Implement rate limiting on API endpoints

#### Data Integrity
- Use foreign key constraints where appropriate
- Implement soft deletes for audit trails
- Validate all input parameters

### Monitoring & Logging

#### Key Metrics
- File numbers generated per day/hour
- Reservation success/failure rates
- Average reservation duration
- Gap occurrences and reasons

#### Logging Requirements
- Log all file number operations
- Include user context and timestamps
- Log performance metrics
- Alert on unusual patterns

## Implementation Checklist

### Phase 1: Core Service
- [ ] Create `STFileNumberService.php`
- [ ] Implement primary file number generation
- [ ] Implement SUA file number generation
- [ ] Implement PUA file number generation
- [ ] Add comprehensive testing

### Phase 2: Database Migration
- [ ] Create `st_file_numbers` table migration
- [ ] Create data migration scripts
- [ ] Migrate existing data
- [ ] Verify data integrity

### Phase 3: Controller Integration
- [ ] Create `STFileNumberController.php`
- [ ] Add AJAX endpoints
- [ ] Update existing controllers
- [ ] Add validation and error handling

### Phase 4: Frontend Integration
- [ ] Update forms to use new endpoints
- [ ] Add real-time file number display
- [ ] Implement SweetAlert notifications
- [ ] Add loading states and error handling

### Phase 5: Testing & Deployment
- [ ] Run comprehensive tests
- [ ] Performance testing
- [ ] User acceptance testing
- [ ] Deploy to staging
- [ ] Deploy to production

### Phase 6: Cleanup
- [ ] Deprecate old services
- [ ] Remove unused code
- [ ] Update documentation
- [ ] Monitor production performance

## Conclusion

This improved implementation will provide:
- Consistent file number formats
- Better performance and scalability
- Improved user experience
- Comprehensive audit trails
- Easier maintenance and debugging

The key success factors are:
- Thorough testing before deployment
- Careful data migration
- Proper error handling
- Clear user feedback
- Comprehensive logging and monitoring
