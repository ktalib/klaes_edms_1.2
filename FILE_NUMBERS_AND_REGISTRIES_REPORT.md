# KLAES GIS EDMS — File Numbers & Registries System Report

> Generated: April 2026 | Scope: File number formats, registries, grouping tables, global selector modal, APIs

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Registries](#2-registries)
3. [File Number Formats](#3-file-number-formats)
4. [Database Tables](#4-database-tables)
5. [Grouping Tables](#5-grouping-tables)
6. [Services & Serial Allocation](#6-services--serial-allocation)
7. [Global File Number Selector Modal](#7-global-file-number-selector-modal)
8. [File Number APIs & Routes](#8-file-number-apis--routes)
9. [Models](#9-models)
10. [Helpers & Normalization](#10-helpers--normalization)
11. [Architecture Diagram](#11-architecture-diagram)

---

## 1. System Overview

The KLAES file number system manages multiple file number formats across **6 registries**, each with its own grouping table, serial control, and commissioning workflow. The system has evolved from legacy KANGIS/MLS formats to the current **ST (Sectional Titling)** standard.

**Key design principles:**
- Atomic serial allocation via database-level row locking (`lockForUpdate()`)
- Dual persistence: every MLS file number is saved to both `fileNumber` and `mls_file_no`
- Registry-specific grouping tables for EDMS pipeline
- Gap-filling prevention — sequential numbering with reservation lifecycle
- Soft-delete everywhere (`is_deleted` flag)

---

## 2. Registries

**Model:** `app/Models/Registry.php` → table `registries`

| Column        | Type    | Notes              |
|---------------|---------|--------------------|
| `id`          | PK      |                    |
| `name`        | string  | Registry full name |
| `code`        | string  | Unique short code  |
| `description` | text    | Optional           |
| `is_active`   | boolean | Default `true`     |
| `created_at`  | timestamp |                  |
| `updated_at`  | timestamp |                  |

### Registry Types

| Registry Name    | Code   | Grouping Table    | Serial Control Model  | File Number Model |
|------------------|--------|-------------------|-----------------------|-------------------|
| Lands Registry   | LANDS  | `grouping`        | `MlsSerialControl`    | `MlsFileNo`      |
| ST Registry      | ST     | (via `st_file_numbers`) | `STFileNumberService` | `StFileNumber`    |
| SLTR Registry    | SLTR   | `sltr_grouping`   | —                     | —                 |
| DCIV Registry    | DCIV   | `dciv_grouping`   | `DcivSerialControl`   | `DcivFileNo`      |
| SIT Registry     | SIT    | `sit_grouping`    | —                     | —                 |
| GKN Registry     | GKN    | `gkn_grouping`    | `GknSerialControl`    | `GknFileNo`       |

**Usage in code:**
```php
// Always use dynamic lookup, never hardcode
$registries = Registry::where('is_active', true)->get();
```

---

## 3. File Number Formats

### 3.1 MLS File Numbers (Lands Registry)

| Type          | Format                             | Example               |
|---------------|------------------------------------|-----------------------|
| Normal        | `{LAND_USE}-{YEAR}-{SERIAL}`       | `RES-2024-0001`       |
| Extension     | `{FILE_NO} AND EXTENSION`          | `RES-2024-0001 AND EXTENSION` |
| Temporary     | `{FILE_NO}(T)`                     | `RES-2024-0001(T)`    |
| Miscellaneous | `MISC-{CODE}-{SERIAL}`             | `MISC-KN-0203`        |
| SLTR          | `SLTR-{SERIAL}`                    | `SLTR-0203567`        |
| SIT           | `SIT-{YEAR}-{SERIAL}`              | `SIT-2025-0203567`    |
| Old MLS       | `KN {SERIAL}`                      | `KN 12345`            |

### 3.2 ST File Numbers (Sectional Titling)

| Type    | Format                                      | Example              |
|---------|---------------------------------------------|----------------------|
| Primary | `ST-{LAND_USE_CODE}-{YEAR}-{SERIAL}`        | `ST-RES-2025-1`      |
| SUA     | `ST-{LAND_USE_CODE}-{YEAR}-{SERIAL}-001`    | `ST-RES-2025-1-001`  |
| PUA     | `ST-{LAND_USE_CODE}-{YEAR}-{SERIAL}-{UNIT}` | `ST-RES-2025-1-002`  |

**Land Use Codes:** `RES` (Residential), `COM` (Commercial), `IND` (Industrial), `MIXED`

### 3.3 Legacy Formats

| System      | Field              | Pattern                           | Example            |
|-------------|--------------------|------------------------------------|-------------------|
| KANGIS      | `kangisFileNo`     | `{PREFIX}/{YEAR}/{SERIAL}`         | `RES/2024/001`    |
| New KANGIS  | `NewKANGISFileNo`  | `N{PREFIX}/{YEAR}/{SERIAL}`        | `NRES/2024/001`   |

### 3.4 Tracking IDs

Format: `TRK-{8 alphanumeric}-{5 alphanumeric}` → e.g. `TRK-ABC23456-XYZ89`

Generated per file number for audit trail and cross-referencing.

---

## 4. Database Tables

### 4.1 Core File Number Tables

#### `fileNumber` — Master Record Table

| Column              | Type     | Description                                    |
|---------------------|----------|------------------------------------------------|
| `id`                | PK       | Auto-increment                                 |
| `kangisFileNo`      | string   | KANGIS format file number                      |
| `mlsfNo`            | string   | MLS format file number                         |
| `NewKANGISFileNo`   | string   | New KANGIS format                              |
| `st_file_no`        | string   | ST format file number                          |
| `FileName`          | string   | Applicant/file label                           |
| `plot_no`           | string   | Plot number                                    |
| `tp_no`             | string   | Town planning number                           |
| `location`          | string   | Physical location                              |
| `lga`               | string   | Local government area                          |
| `tracking_id`       | string   | Unique tracking reference                      |
| `type`              | string   | `MlsFileNO`, `Captured`, `Migrated`            |
| `SOURCE`            | string   | `MLS_Commissioned`, `MLS_Commissioned_Batch`, `Captured`, `Migrated`, `KANGIS GIS`, `indexing` |
| `is_deleted`        | bit      | Soft delete flag                               |
| `is_decommissioned` | bit      | Decommission flag                              |
| `created_by`        | string   | User who created                               |
| `updated_by`        | string   | User who last updated                          |
| `created_at`        | datetime |                                                |
| `updated_at`        | datetime |                                                |

#### `mls_file_no` — MLS Enrichment Table

| Column                         | Type     | Description                          |
|--------------------------------|----------|--------------------------------------|
| `id`                           | PK       |                                      |
| `full_file_number`             | string   | Complete MLS file number             |
| `land_use`                     | string   | Land use category                    |
| `year`                         | int      | Year of issuance                     |
| `serial_number`                | int      | Sequential serial                    |
| `applicant_type` / `customer_type` | string | Individual / Corporate / Government  |
| `batch_no`                     | string   | Batch reference for grouped ops      |
| `commissioning_date`           | datetime | When commissioned                    |
| `source`                       | string   | Origin (MLS, EDMS, etc.)             |
| `sub_source`                   | string   | Sub-origin (OP Change of Name, etc.) |
| `source_instrument_capture_id` | int      | FK to instrument_capture             |
| `source_pra_id`                | int      | FK to pra table                      |
| `purpose_id`                   | int      | FK to purposes table                 |
| `tracking_id`                  | string   | Audit trail reference                |
| `lga`                          | string   |                                      |
| `phone_no`, `address`          | string   | Contact info                         |
| `rep_phone_no`, `rep_address`  | string   | Representative info                  |
| `created_by`                   | string   |                                      |
| `created_at`, `updated_at`     | datetime |                                      |

#### `st_file_numbers` — ST File Number Central Registry

| Column                  | Type     | Description                              |
|-------------------------|----------|------------------------------------------|
| `id`                    | PK       |                                          |
| `np_fileno`             | string   | Primary file number (e.g. `ST-RES-2025-1`) |
| `fileno`                | string   | Full file number (unique) incl. unit seq |
| `mls_fileno`            | string   | Linked MLS file number                   |
| `land_use`              | string   | Full name (Residential, etc.)            |
| `land_use_code`         | string   | Code (RES, COM, IND, MIXED)              |
| `serial_no`             | int      | Sequential within land_use/year          |
| `unit_sequence`         | int/null | NULL for primary, 001+ for units         |
| `year`                  | int      |                                          |
| `file_no_type`          | enum     | `PRIMARY`, `SUA`, `PUA`                  |
| `parent_id`             | int/null | FK to self (for PUA → parent)            |
| `mother_application_id` | int/null | FK to `mother_applications`              |
| `subapplication_id`     | int/null | FK to `subapplications`                  |
| `status`                | enum     | `RESERVED`, `ACTIVE`, `USED`, `CANCELLED` |
| `reserved_at`           | datetime | When reserved                            |
| `expires_at`            | datetime | Reservation expiry (24 hours)            |
| `used_at`               | datetime | When application was submitted           |
| `tra`                   | string   | Transaction reference                    |
| `applicant_title`       | string   |                                          |
| `first_name`            | string   |                                          |
| `surname`               | string   |                                          |
| `corporate_name`        | string   |                                          |
| `rc_number`             | string   |                                          |
| `multiple_owners_names` | text     |                                          |
| `applicant_type`        | string   | Individual / Corporate / Multiple        |
| `created_by`            | string   |                                          |
| `created_at`            | datetime |                                          |
| `updated_at`            | datetime |                                          |

### 4.2 Serial Control Tables

#### `mls_serial_controls`

| Column           | Type    | Description                     |
|------------------|---------|---------------------------------|
| `id`             | PK      |                                 |
| `land_use`       | string  | Land use category               |
| `year`           | int     | Year                            |
| `last_serial`    | int     | Current highest serial          |
| `is_initialized` | boolean | Has been set up                 |
| `is_locked`      | boolean | Currently locked for allocation |
| `initialized_at` | datetime |                                |
| `initialized_by` | string  |                                 |

#### `land_use_serials` (ST specific)

| Column           | Type    | Description                     |
|------------------|---------|---------------------------------|
| `land_use_type`  | string  | COMMERCIAL, RESIDENTIAL, etc.   |
| `prefix`         | string  | ST-COM, ST-RES, etc.            |
| `year`           | int     |                                 |
| `current_serial` | int     | Current highest serial          |

### 4.3 Reservation Tables

#### `file_number_reservations`

| Column             | Type     | Description                      |
|--------------------|----------|----------------------------------|
| `id`               | PK       |                                  |
| `file_number`      | string   | Reserved file number             |
| `land_use_type`    | string   |                                  |
| `serial_number`    | int      |                                  |
| `year`             | int      |                                  |
| `status`           | string   | `reserved`, `used`, `expired`, `released` |
| `reservation_uuid` | string   | UUID for tracking                |
| `draft_id`         | int/null | Linked draft                     |
| `application_id`   | int/null | Linked application               |
| `reserved_at`      | datetime |                                  |
| `expires_at`       | datetime | Default: 15 minutes              |
| `used_at`          | datetime |                                  |
| `released_at`      | datetime |                                  |
| `metadata`         | json     | Additional context               |

### 4.4 Registry-Specific File Number Tables

| Table              | Model           | Format Pattern    |
|--------------------|-----------------|-------------------|
| `gkn_file_nos`     | `GknFileNo`     | GKN prefix series |
| `dciv_file_nos`    | `DcivFileNo`    | DCIV prefix series|
| `sua_file_numbers` | —               | SUA ST format     |

---

## 5. Grouping Tables

Grouping tables are the backbone of the EDMS registry pipeline. They track files from intake through indexing, mapping each "awaiting" file number to its commissioned MLS/ST file number.

### 5.1 Architecture

```
Registry → Grouping Table → File Indexing → Scanning → Page Typing → Archive
```

### 5.2 Grouping Table Structure (Common Columns)

All grouping tables share this structure:

| Column                    | Type     | Description                          |
|---------------------------|----------|--------------------------------------|
| `id`                      | PK       |                                      |
| `{prefix}_awaiting_fileno`| string   | Original unprocessed file number     |
| `{prefix}_fileno` / `mls_fileno` | string | Linked commissioned file number |
| `mapping`                 | tinyint  | 0 = unmapped, 1 = mapped            |
| `batch_no`                | string   | Physical batch reference             |
| `mdc_batch_no`            | string   | MDC batch reference                  |
| `sys_batch_no`            | string   | System-generated batch               |
| `registry_batch_no`       | string   | Registry-specific batch              |
| `year_batch_no`           | string   | Year-based batch                     |
| `shelf_rack`              | string   | Physical storage location            |
| `group`                   | string   | Logical grouping                     |
| `year`                    | string   | Year                                 |
| `landuse`                 | string   | Land use category                    |
| `tracking_id`             | string   | Unique tracking reference            |
| `registry`                | string   | Source registry name                 |
| `indexing_mapping`        | string   | Indexing link status                 |
| `indexing_mls_fileno`     | string   | MLS file number from indexing        |
| `indexed_by`              | string   | Indexed by user                      |
| `date_index`              | datetime | Date indexed                         |
| `created_by`              | string   |                                      |
| `updated_by`              | string   |                                      |
| `deleted_by`              | string   |                                      |
| `created_at`              | datetime |                                      |
| `updated_at`              | datetime |                                      |
| `deleted_at`              | datetime | Soft delete                          |

### 5.3 Registry ↔ Grouping Table Mapping

| Registry | Grouping Table    | Awaiting Column          | MLS Column        |
|----------|-------------------|--------------------------|--------------------|
| Lands    | `grouping`        | `awaiting_fileno`        | `mls_fileno`       |
| SLTR     | `sltr_grouping`   | `sltr_awaiting_fileno`   | `sltr_fileno`      |
| SIT      | `sit_grouping`    | `sit_awaiting_fileno`    | `sit_fileno`       |
| DCIV     | `dciv_grouping`   | `dciv_awaiting_fileno`   | `dciv_fileno`      |
| GKN      | `gkn_grouping`    | `gkn_awaiting_fileno`    | `gkn_fileno`       |
| KANGIS   | `kangis_grouping` *(detected by prefix)* | `kangis_awaiting_fileno` | `kangis_fileno` |

### 5.4 Grouping Service

**Service:** `app/Services/GroupingFileNumberService.php`

| Method                   | Description                                          |
|--------------------------|------------------------------------------------------|
| `linkAwaitingToMls()`    | Links single awaiting file number to MLS             |
| `bulkLinkAwaitingToMls()`| Bulk links with conflict detection, auto-tracking ID |
| `findGroupingRecord()`   | Finds by exact match OR normalized match             |
| `getTableName()`         | Auto-detects table by file number prefix             |
| `getColumnNameByTable()` | Maps table → awaiting column name                    |
| `getMlsColumnName()`     | Maps table → MLS column name                         |

**Prefix → Table Detection Logic:**
```
GKN*   → gkn_grouping
DCIV*  → dciv_grouping
SLTR*  → sltr_grouping
SIT*   → sit_grouping
KANGIS*→ kangis_grouping
LPKN*  → grouping (Lands)
MISC*  → grouping (Lands)
default→ grouping (Lands)
```

### 5.5 Batch Systems

#### Print Label Batches
- **Model:** `PrintLabelBatch` → `print_label_batches`
- Status lifecycle: `pending` → `generated` → `printed` → `completed`
- Formats: `standard`, `compact`, `qr_code`, `30-in-1`
- Each batch has items (`PrintLabelBatchItem`) linking to `file_indexings`

#### File Indexing Batches
- **Model:** `FileindexingBatch` → `fileindexing_batch`
- Fixed capacity: **100 items** per batch
- Tracks shelf assignments and batch fullness

---

## 6. Services & Serial Allocation

### 6.1 STFileNumberService

**Location:** `app/Services/STFileNumberService.php`

| Method                      | Description                                    |
|-----------------------------|------------------------------------------------|
| `generatePrimaryFileNumber()` | Creates primary ST file number; locks serial row |
| `generateSUAFileNumber()`     | Creates SUA file number (unit = 001 always)    |
| `generatePUAFileNumber()`     | Creates PUA file number (increments unit seq)  |
| `confirmReservation()`        | Marks RESERVED → USED, links to application   |
| `releaseReservation()`        | Marks RESERVED → CANCELLED                    |
| `getFileNumberDetails()`      | Full metadata retrieval                        |
| `getUnitsByParent()`          | Lists all PUA units for a primary              |
| `normalizeLandUse()`          | Maps land use to RES/COM/IND/MIXED             |
| `parseFileNumber()`           | Regex extraction of ST components              |

**Atomic Allocation Flow:**
```
1. BEGIN TRANSACTION
2. lockForUpdate() → exclusive row lock on serial control
3. READ  → get current last_serial for land_use/year
4. CALC  → next_serial = last_serial + 1
5. UPDATE → increment last_serial in DB
6. INSERT → create st_file_numbers record with RESERVED status
7. COMMIT → release lock
```

### 6.2 FileNumberReservationService (Legacy)

**Location:** `app/Services/FileNumberReservationService.php`

| Method                       | Description                                  |
|------------------------------|----------------------------------------------|
| `reserveNextSerial()`        | Reserves next sequential serial (24h expiry) |
| `reserveBatchSerials()`      | Bulk reserve multiple serials                |
| `getNextAvailableSerial()`   | MAX(serial) from existing + active reservations |
| `confirmReservation()`       | Marks `confirmed`                            |
| `releaseReservation()`       | Returns to pool                              |
| `releaseExpiredReservations()` | Auto-cleanup of expired records            |
| `isSerialAvailable()`        | Checks if specific serial is free            |

**Format:** `NPFN-{YEAR}-{LAND_USE_CODE}-{SERIAL}` (being phased out in favor of ST)

### 6.3 SUAFileNumberService

**Location:** `app/Services/SUAFileNumberService.php`

| Method                   | Description                                     |
|--------------------------|-------------------------------------------------|
| `generateSUAFileNumbers()` | Returns `['main' => '', 'sua' => '', 'mls' => '']` |
| `storeSUAFileNumbers()`    | Persists to `sua_file_numbers` table            |
| `getNextMainSequence()`    | MAX from `mother_applications` + `sua_file_numbers` |
| `getNextUnitSequence()`    | Always returns 1 (SUA = single unit)            |
| `mapLandUseToCode()`       | Bidirectional land use mapping                  |

### 6.4 MlsSerialControl (Atomic Lock Model)

**Location:** `app/Models/MlsSerialControl.php`

| Method                      | Description                           |
|-----------------------------|---------------------------------------|
| `getNextSerial($landUse, $year)` | Increments and returns with lock |
| `initialize($landUse, $year, $lastSerial)` | Sets starting point         |
| `isLocked($landUse, $year)` | Check lock status                     |
| `getCurrentSerial()`        | Peek without increment                |
| `getAllForYear($year)`       | All controls for a year               |

---

## 7. Global File Number Selector Modal

### 7.1 Blade Components

| File | Description |
|------|-------------|
| `resources/views/components/file-number-modal.blade.php` | Tabbed modal with MLS/KANGIS/New KANGIS format tabs |
| `resources/views/partials/file_number_modal.blade.php` | Lightweight Tailwind-based modal for quick lookup |
| `resources/views/caveat/partials/manual_file_number_modal.blade.php` | Caveat-specific variant |
| `resources/views/components/smart_fileno_selector.blade.php` | **Main reusable component** — dropdown + manual toggle |
| `resources/views/components/smart_fileno_selector_property.blade.php` | Alpine.js property card variant |
| `resources/views/components/smart_fileno_selector_indexing.blade.php` | Indexing-specific variant |

### 7.2 JavaScript Files

| File | Purpose |
|------|---------|
| `public/js/file-number-selector.js` | **Main selector engine** — popover pattern with search |
| `public/js/file-number-search.js` | Advanced search & autofill logic |
| `public/js/manual-file-number-modal.js` | Manual entry modal with mode switching |

### 7.3 How It Works

**Smart Selector Pattern (`smart_fileno_selector.blade.php`):**
1. **Dropdown mode** (default): Select2-enabled searchable dropdown
2. **Manual mode**: Tab-based form with manual file number entry
3. Toggle between modes preserves selection; disables inputs when hidden

**Popover Selector Pattern (`file-number-selector.js`):**
1. User clicks trigger → popover opens
2. Auto-loads **top 10** recent file numbers via `GET /file-numbers/api/top`
3. User types → **debounced search** (300ms) via `GET /file-numbers/api/search?query=...`
4. Results render with file number + type badge + status badge
5. User clicks result → updates hidden `#file_number` input
6. Triggers `window.searchFileNumberInTables()` if defined (cross-module integration)

### 7.4 API Endpoints Called

| Endpoint                              | Method | Description                    |
|---------------------------------------|--------|--------------------------------|
| `GET /file-numbers/api/search`        | AJAX   | Search across all file number fields (min 2 chars) |
| `GET /file-numbers/api/top`           | AJAX   | Top 10 recent active file numbers |
| `GET /file-numbers/api/details/{id}`  | AJAX   | Full details by ID             |

### 7.5 Search Logic

Searches across these fields using `OR` with `LIKE`:
- `kangisFileNo`
- `mlsfNo`
- `NewKANGISFileNo`
- `FileName`

Excludes deleted records. Paginated response with priority display: KANGIS → New KANGIS → MLSF.

### 7.6 Response Structure

```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "kangis_file_no": "RES/2024/001",
      "mlsf_no": "RES-2024-0001",
      "new_kangis_file_no": "kn ",
      "file_name": "Residential Property",
      "status": "Active",
      "created_at": "2024-01-15 10:30:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 10,
    "total": 150,
    "total_pages": 15,
    "has_more": true
  }
}
```

### 7.7 Integration Points

The global selector is used across:
- Caveat entry forms
- Survey record forms
- GIS data entry
- Commission forms (MLS + ST)
- File scanning intake
- Property cards
- File indexing
- One-Stop Shop (OSS)

---

## 8. File Number APIs & Routes

### 8.1 MLS File Numbers — `routes/file_numbers.php`

**Prefix:** `/file-numbers/` | **Middleware:** `auth`, `XSS`  
**Controller:** `FileNumberController`

#### Dashboard & Stats
| Method | Route | Action | Type |
|--------|-------|--------|------|
| GET | `/` | `index()` | Page |
| GET | `/data` | `getData()` | DataTable AJAX |
| GET | `/stats` | `getStats()` | JSON |
| GET | `/test-db` | `testDatabase()` | JSON |

#### File Number Generation
| Method | Route | Action | Type |
|--------|-------|--------|------|
| GET | `/next-serial` | `getNextSerial()` | JSON |
| GET | `/existing` | `getExistingFileNumbers()` | JSON |
| POST | `/store` | `store()` | JSON |
| POST | `/migrate` | `migrate()` | JSON (CSV import) |

#### Global Search API
| Method | Route | Action | Type |
|--------|-------|--------|------|
| GET | `/api/search` | `searchFileNumbers()` | JSON |
| GET | `/api/top` | `getTopFileNumbers()` | JSON |
| GET | `/api/details/{id}` | `getFileNumberDetails()` | JSON |

#### Capture Existing File Numbers
| Method | Route | Action | Type |
|--------|-------|--------|------|
| GET | `/existing-file-numbers/` | `captureIndex()` | Page (Global View) |
| GET | `/existing-file-numbers/data` | `getCaptureData()` | DataTable AJAX |
| POST | `/existing-file-numbers/store` | `captureStore()` | JSON |

#### Conversion Applications
| Method | Route | Action | Type |
|--------|-------|--------|------|
| GET | `/conversion-application/{id}` | `generateConversionApplication()` | PDF |
| GET | `/batch-conversion-application/{batchNo}` | `generateBatchConversionApplication()` | PDF |

#### Print Logging
| Method | Route | Action | Type |
|--------|-------|--------|------|
| GET | `/get-print-status` | `getPrintStatus()` | JSON |
| POST | `/record-print` | `recordPrint()` | JSON |

#### CRUD
| Method | Route | Action | Type |
|--------|-------|--------|------|
| GET | `/{id}` | `show()` | JSON |
| PUT | `/{id}` | `update()` | JSON |
| DELETE | `/{id}` | `destroy()` | JSON |
| GET | `/count/total` | `getCount()` | JSON |
| POST | `/clear-cache` | `clearCache()` | JSON |

### 8.2 ST File Numbers — `routes/app3.php`

**Prefix:** `/api/st-file-numbers/` | **Middleware:** `auth`  
**Controller:** `STFileNumberController`

#### Reservation
| Method | Route | Action | Type |
|--------|-------|--------|------|
| POST | `/reserve-primary` | `reservePrimary()` | JSON |
| POST | `/reserve-sua` | `reserveSUA()` | JSON |
| POST | `/reserve-pua` | `reservePUA()` | JSON |

#### Lifecycle
| Method | Route | Action | Type |
|--------|-------|--------|------|
| POST | `/confirm/{fileNumber}` | `confirm()` | JSON |
| DELETE | `/release/{fileNumber}` | `release()` | JSON |

#### Details & Search
| Method | Route | Action | Type |
|--------|-------|--------|------|
| GET | `/details/{fileNumber}` | `getDetails()` | JSON |
| GET | `/units/{parentFileNumber}` | `getUnitsByParent()` | JSON |
| GET | `/buyers/{parentFileNumber}` | `getBuyersForParent()` | JSON |
| GET | `/validate/{fileNumber}` | `validateFileNumber()` | JSON |
| GET | `/search` | `search()` | JSON |

#### Preview
| Method | Route | Action | Type |
|--------|-------|--------|------|
| POST | `/preview` | `getNextPreview()` | JSON |

### 8.3 Commission New ST — `routes/app3.php`

**Prefix:** `/commission-new-st/` | **Middleware:** `auth`  
**Controller:** `CommissionNewSTController`

| Method | Route | Action | Type |
|--------|-------|--------|------|
| GET | `/` | `index()` | Page |
| GET | `/primary-data` | `getPrimaryData()` | DataTable |
| GET | `/sua-data` | `getSuAData()` | DataTable |
| GET | `/pua-data` | `getPuAData()` | DataTable |
| GET | `/next-fileno` | `nextFileNo()` | JSON |
| GET | `/sua-next-fileno` | `suaNextFileNo()` | JSON |
| GET | `/pua-next-fileno` | `puaNextFileNo()` | JSON |
| POST | `/commission` | `commission()` | JSON |
| POST | `/commission-pua` | `commissionPuA()` | JSON |
| GET | `/primary-available` | `getAvailablePrimaryFileNumbers()` | JSON |

### 8.4 File Number Matching — `routes/app3.php`

Each registry has a matching controller with identical route structures:

| Registry | Prefix | Controller |
|----------|--------|------------|
| MLS | `/mls-file-no-matching/` | `MlsFileNoMatchingController` |
| Lands | `/lands-file-no-matching/` | `LandsFileNoMatchingController` |
| ST | `/st-file-no-matching/` | `StFileNoMatchingController` |
| SLTR | `/sltr-file-no-matching/` | `SltrFileNoMatchingController` |

**Common routes per matching controller:**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/` | `index()` |
| GET | `/available` | `getAvailableMls()` |
| GET | `/details` | `getFileDetails()` |
| POST | `/store` | `store()` |
| GET | `/batch-members/{batchNo}` | `getBatchMembers()` |
| GET | `/{id}/edit` | `edit()` |
| PUT | `/{id}/update` | `update()` |

---

## 9. Models

### 9.1 File Number Models

| Model | Table | Key Fields |
|-------|-------|------------|
| `FileNumber` | `fileNumber` | `kangisFileNo`, `mlsfNo`, `NewKANGISFileNo`, `st_file_no`, `tracking_id`, `SOURCE`, `type`, `is_deleted` |
| `MlsFileNo` | `mls_file_no` | `full_file_number`, `land_use`, `year`, `serial_number`, `batch_no`, `tracking_id`, `source`, `sub_source` |
| `GknFileNo` | `gkn_file_nos` | GKN-prefixed file numbers |
| `DcivFileNo` | `dciv_file_nos` | DCIV-prefixed file numbers |

### 9.2 Serial Control Models

| Model | Table | Key Fields |
|-------|-------|------------|
| `MlsSerialControl` | `mls_serial_controls` | `land_use`, `year`, `last_serial`, `is_locked` |
| `GknSerialControl` | `gkn_serial_controls` | `prefix`, `last_serial`, `is_initialized` |
| `DcivSerialControl` | `dciv_serial_controls` | `prefix`, `year`, `last_serial` |

### 9.3 Grouping Models

| Model | Table | Soft Delete |
|-------|-------|-------------|
| `Grouping` | `grouping` | Yes |
| `SltrGrouping` | `sltr_grouping` | Yes |
| `SitGrouping` | `sit_grouping` | Yes |
| `DcivGrouping` | `dciv_grouping` | Yes |
| `GknGrouping` | `gkn_grouping` | Yes |

### 9.4 Supporting Models

| Model | Table | Purpose |
|-------|-------|---------|
| `Registry` | `registries` | Registry definitions |
| `LandUse` | `land_uses` | Land use categories with `purposes()` and `prefixes()` relationships |
| `Prefix` | `prefixes` | Land use prefix mappings (FK to `land_uses`) |
| `FileNumberReservation` | `file_number_reservations` | Reservation lifecycle (15-min default expiry, UUID-based) |
| `AllocationListEntry` | `allocation_list_entries` | Unallocated file number entries |
| `PrintLabelBatch` | `print_label_batches` | Print batch management |
| `PrintLabelBatchItem` | `print_label_batch_items` | Individual items in print batches |
| `FileindexingBatch` | `fileindexing_batch` | 100-item shelf capacity batches |

---

## 10. Helpers & Normalization

### 10.1 File Number Normalization (`app/Helper/helper.php`)

The normalization pipeline (from `AGENTS.md`):

```
1. Trim and uppercase
2. Replace O-variants with O; /, =, _ with -
3. Split concatenated file numbers
4. Normalize prefixes: CN → CON, C0M → COM, R3S → RES
5. Normalize years: expand 2-digit years, fix 18XX → 19XX
6. Clean serial digits: O → 0, I/l → 1 in numeric positions
7. Classify pattern (ST, MLS, KANGIS)
8. Validate pattern integrity
```

### 10.2 ST File Number Generation Helper

```php
generateSTFileNumber($prefix, $year)
// Validates prefix against ['ST-COM', 'ST-RES', 'ST-IND']
// Generates ST-{PREFIX}-{YEAR}-{SERIAL} with zero-padded serial
// Stores in st_files table
```

### 10.3 Applicant Parsing Helpers

```php
parseApplicantOwnersList($raw)
// Recursively extracts names from mixed arrays/JSON/strings
// Supports keys: name, corporate_name, first_name/surname, owners array
// Deduplicates and trims results

formatApplicantDisplayName()
// Formats display based on type (Individual/Corporate/Multiple)
```

---

## 11. Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                        REGISTRIES                                   │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ │
│  │  Lands   │ │   ST     │ │  SLTR    │ │  DCIV    │ │  GKN     │ │
│  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘ │
│       │            │            │            │            │        │
│       ▼            ▼            ▼            ▼            ▼        │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ │
│  │ grouping │ │st_file_  │ │sltr_     │ │dciv_     │ │gkn_      │ │
│  │          │ │numbers   │ │grouping  │ │grouping  │ │grouping  │ │
│  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘ │
│       │            │            │            │            │        │
│       └────────────┴────────────┴────────────┴────────────┘        │
│                              │                                      │
│                              ▼                                      │
│                    ┌───────────────────┐                            │
│                    │    fileNumber     │ ← Master record             │
│                    │ (kangis, mls, st) │                            │
│                    └────────┬──────────┘                            │
│                             │                                       │
│                    ┌────────┴──────────┐                            │
│                    │   mls_file_no     │ ← MLS enrichment           │
│                    │ (land_use, batch) │                            │
│                    └──────────────────┘                             │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────┐      │
│  │              Serial Control Layer                         │      │
│  │  MlsSerialControl │ GknSerialControl │ DcivSerialControl │      │
│  │  land_use_serials  │ file_number_reservations             │      │
│  └──────────────────────────────────────────────────────────┘      │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────┐      │
│  │              Matching Controllers                         │      │
│  │  MLS │ Lands │ ST │ SLTR  ← link awaiting → commissioned │      │
│  └──────────────────────────────────────────────────────────┘      │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────┐      │
│  │              Global File Number Selector                  │      │
│  │  smart_fileno_selector.blade.php                          │      │
│  │  file-number-selector.js                                  │      │
│  │  APIs: /api/search, /api/top, /api/details/{id}          │      │
│  └──────────────────────────────────────────────────────────┘      │
└─────────────────────────────────────────────────────────────────────┘
```

### Data Flow: File Number Lifecycle

```
1. COMMISSION    → Serial allocated (atomic lock) → Record in fileNumber + mls_file_no
2. RESERVE (ST)  → st_file_numbers (status=RESERVED, 24h expiry)
3. CONFIRM       → st_file_numbers (status=USED, linked to application)
4. GROUP         → grouping table (awaiting_fileno → mls_fileno mapping)
5. INDEX         → file_indexings (file_number linked)
6. SCAN          → scanning pipeline
7. ARCHIVE       → file archive with physical shelf/rack reference
```

### Key Relationships

```
st_file_numbers.parent_id         → st_file_numbers.id       (PUA → Primary)
st_file_numbers.mother_application_id → mother_applications.id
st_file_numbers.subapplication_id → subapplications.id
mls_file_no.purpose_id           → purposes.id
mls_file_no.source_pra_id        → pra.id
mls_file_no.source_instrument_capture_id → instrument_capture.id
grouping.tracking_id             → fileNumber.tracking_id
file_indexings.file_number        → fileNumber.mlsfNo
```

---

## Summary

The KLAES file number system is a multi-registry, multi-format architecture that has evolved from legacy KANGIS formats through MLS to the current ST standard. Each registry maintains its own grouping table and serial control mechanism, all converging on the master `fileNumber` table. The global file number selector modal provides a unified search interface across all formats, with the system enforcing atomic serial allocation to prevent duplicates under concurrent load.
