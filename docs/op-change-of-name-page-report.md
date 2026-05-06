# Applications (Change of Name) — Page Report

> **Page URL:** `/lands-one-stop-shop/applications/op-resettlement?source=lands-one-stop-shop&type=change-of-name`  
> **Purpose:** Land One Stop Shop overview of Change of Name Occupancy Permit records.

---

## 1. Route

**File:** `routes/app3.php` (line 185)

```
GET /lands-one-stop-shop/applications/op-resettlement
```

- Route name: `lands-one-stop-shop.applications.index`
- Controller: `OpResettlementApplicationController::index`
- Route group prefix: `lands-one-stop-shop`

The query parameters `?source=lands-one-stop-shop&type=change-of-name` are **not consumed by the controller**. They are only read in the Blade view to determine the page title and which action menu items to display.

### Related Routes (Same Controller)

| Method | URI | Action | Purpose |
|--------|-----|--------|---------|
| `PUT` | `/applications/op-resettlement/{id}/update-land-use` | `updateLandUse` | Update `land_use` on an instrument_capture record |
| `PUT` | `/applications/op-resettlement/{id}/update-details` | `updateDetails` | Update record details from Edit modal |
| `GET` | `/applications/op-resettlement/pra-transactions` | `praTransactions` | Fetch PRA transaction history for a given prop_id / file number |

---

## 1.1 Dedicated Source & Commissioned Date

**Source value:** `OSS_CHANGE_OF_NAME` stored in `fileNumber.SOURCE`.  
This replaces the old composite WHERE (`mfn.sub_source = 'OP Change of Name' OR fn.SOURCE = 'FFR_Existing_Capture'`).

**Commissioned date:** `mls_file_no.con_commissioned_at` (`datetime`, nullable).  
Dedicated to Change of Name records — set to `NOW()` on first save. Used for table sorting (column 12, descending).

- On form submit (`updateDetails()`): `fn.SOURCE` is always set to `'OSS_CHANGE_OF_NAME'`, `mfn.sub_source` to `'OP Change of Name'`, and `mfn.con_commissioned_at` is set to `NOW()` if not already populated.
- Migration: `2026_03_27_030000_add_con_commissioned_at_to_mls_file_no_table.php`
- Backfill: 86 existing records updated — SOURCE set, `con_commissioned_at` backfilled from `fn.created_at`.




## 2. Controller

**File:** `app/Http/Controllers/LandsOneStopShop/OpResettlementApplicationController.php`

### `index()` Method

Accepts an optional `limit` (default 25, clamped 10–200) and optional `search` query string.

---

## 3. Database Tables & Queries

**Connection:** `sqlsrv` (SQL Server)

### 3.1 Primary Query — Main Table Data

The main query joins **4 tables**:

```
fileNumber (fn)
  └── LEFT JOIN mls_file_no (mfn)         ON fn.tracking_id = mfn.tracking_id
        ├── LEFT JOIN instrument_capture (source_capture)  ON mfn.source_instrument_capture_id = source_capture.id
        └── LEFT JOIN pra (source_pra)     ON mfn.source_pra_id = source_pra.id
```

#### Table Roles

| Table (Alias) | Description |
|---------------|-------------|
| **`fileNumber`** (`fn`) | Core file-number registry. Provides `mlsfNo`, `FileName`, `SOURCE`, `temp_fileno`, `plot_no`, `tp_no`, `lga`, `location`, `created_by`, `commissioning_date`, `is_deleted`. |
| **`mls_file_no`** (`mfn`) | MLS tracking link. Provides `source`, `sub_source`, `land_use`, `customer_type`, `source_instrument_capture_id`, `source_pra_id`. |
| **`instrument_capture`** (`source_capture`) | Origin instrument record. Provides `purpose`, `land_use`, `district`, `party_1_name`, `party_1_phone`, `party_1_address`, `party_2_name`, `party_2_phone`, `party_2_address`, `prop_id`, `instrument_type`. |
| **`pra`** (`source_pra`) | Property Registration Authority transactions. Provides `Grantor`, `Grantee`, `party_1`, `party_2`, `prop_id`, `temp_fileno`, `instrument_type`, `created_at`. |

#### WHERE Conditions

```sql
WHERE fn.SOURCE = 'OSS_CHANGE_OF_NAME'
  AND (fn.is_deleted IS NULL OR fn.is_deleted = 0)
ORDER BY mfn.con_commissioned_at DESC
```

#### Search Filter (when `?search=` is provided)

Searches across: `fn.mlsfNo`, `fn.FileName`, `mfn.source`, `fn.SOURCE`, `fn.plot_no`, `fn.tp_no`, `fn.lga`, `fn.location`, `fn.created_by`, resolved customer type expression, `mfn.land_use`.

### 3.2 Selected Columns & Subqueries

#### `resolved_customer_type`

Resolved via `COALESCE(...)` from up to 4 sources (each checked for column existence at runtime):

1. `mfn.customer_type` — from `mls_file_no`
2. Subquery → `customers_staging.customer_type` (matched on `file_number = fn.mlsfNo`)
3. Subquery → `file_indexings.file_type` (matched on `file_number` or `full_file_number = fn.mlsfNo`)
4. Subquery → `file_indexings.customer_type` (same table, different column)

#### `land_use`

```sql
COALESCE(
    mfn.land_use,
    CASE
        WHEN fn.mlsfNo LIKE 'RES%' THEN 'RES'
        WHEN fn.mlsfNo LIKE 'COM%' THEN 'COM'
        WHEN fn.mlsfNo LIKE 'CON%' THEN 'COM'
        WHEN fn.mlsfNo LIKE 'IND%' THEN 'IND'
        WHEN fn.mlsfNo LIKE 'AGR%' THEN 'AGR'
        ELSE NULL
    END
)
```

#### `created_by_name`

```sql
COALESCE(
    (SELECT TOP 1 CONCAT(u.first_name, ' ', u.last_name) FROM users u WHERE u.id = TRY_CONVERT(int, fn.created_by)),
    fn.created_by,
    '—'
)
```

#### `source_prop_id`

```sql
COALESCE(
    source_capture.prop_id,
    source_pra.prop_id,
    (SELECT TOP 1 p.prop_id FROM pra p WHERE p.temp_fileno = fn.temp_fileno OR p.mlsFNo = fn.mlsfNo OR p.fileno = fn.mlsfNo ORDER BY p.id DESC)
)
```

#### `source_instrument_type`

```sql
COALESCE(
    source_capture.instrument_type,
    source_pra.instrument_type,
    (SELECT TOP 1 p2.instrument_type FROM pra p2 WHERE p2.temp_fileno = fn.temp_fileno OR p2.mlsFNo = fn.mlsfNo OR p2.fileno = fn.mlsfNo ORDER BY p2.id DESC)
)
```

#### `pra_created_at`

```sql
COALESCE(
    source_pra.created_at,
    (SELECT TOP 1 p_created.created_at FROM pra p_created WHERE p_created.temp_fileno = fn.temp_fileno OR p_created.mlsFNo = fn.mlsfNo OR p_created.fileno = fn.mlsfNo ORDER BY p_created.id DESC)
)
```

#### `source_temp_fileno_fallback`

```sql
COALESCE(
    (SELECT TOP 1 ic_hist.temp_fileno FROM instrument_capture ic_hist WHERE ic_hist.prop_id = source_capture.prop_id AND ic_hist.temp_fileno IS NOT NULL ORDER BY ic_hist.id DESC),
    source_pra.temp_fileno,
    (SELECT TOP 1 p.temp_fileno FROM pra p WHERE p.temp_fileno IS NOT NULL AND (p.temp_fileno = fn.temp_fileno OR p.mlsFNo = fn.mlsfNo OR p.fileno = fn.mlsfNo) ORDER BY p.id DESC),
    fn.temp_fileno
)
```

### 3.3 Card Count — Total Commissioned

Separate `COUNT(*)` query on the same join with the same WHERE conditions (no limit):

```sql
SELECT COUNT(*)
FROM fileNumber fn
LEFT JOIN mls_file_no mfn ON fn.tracking_id = mfn.tracking_id
WHERE fn.SOURCE = 'OSS_CHANGE_OF_NAME'
  AND (fn.is_deleted IS NULL OR fn.is_deleted = 0)
```

### 3.4 Card Counts — By Land Use

Computed **in PHP** by iterating the loaded `$records` collection:

```php
foreach ($records as $record) {
    // Buckets: Residential, Commercial, Industrial, Agriculture
    // Checks if land_use contains 'RES', 'COM', 'IND', or 'AGR'
}
```

### 3.5 OSS Records Count

```sql
SELECT COUNT(*) FROM oss_applications WHERE (is_deleted IS NULL OR is_deleted = 0)
```

Falls back to `$records->count()` if the table is empty or doesn't exist.

### 3.6 Lookup/Reference Data

| Table | Purpose |
|-------|---------|
| `States` | State dropdown for modals |
| `lgas` (where `is_active = 1`) | LGA dropdown for modals |
| `districts` (where `is_active = 1`) | District dropdown for modals |
| `street_names` (Eloquent: `StreetName`) | Street name dropdown for modals |

---

## 4. PHP Record Mapping

Each database row is transformed in a `->map()` callback into an associative array with these keys:

### Primary Display Fields

| Key | Source | Description |
|-----|--------|-------------|
| `id` | `fn.id` | Primary key |
| `sn` | `fn.id` | Serial number (overridden by `$loop->iteration` in Blade) |
| `customer_type` | `resolved_customer_type` | Uppercased; defaults to `—` |
| `source` | `mfn.source` or `fn.SOURCE` | For FFR records, replaced with `instrument_type` or `'Occupancy Permit (OP)'` |
| `mls_file_no` | `fn.mlsfNo` | Uppercased |
| `file_title` | `fn.FileName` | Uppercased |
| `land_use` | Resolved COALESCE | Mapped to full names (RES → RESIDENTIAL, COM → COMMERCIAL, etc.) |
| `tp_no` | `fn.tp_no` | Town planning number |
| `plot_no` | `fn.plot_no` | Plot number |
| `lga` | `fn.lga` | Local Government Area |
| `location` | `fn.location` | Property location |
| `commissioned_by` | `created_by_name` | Full name from `users` table |
| `time_commissioned` | `commissioning_date` or `created_at` | Formatted `g:i A` |
| `date_commissioned` | `commissioning_date` or `created_at` | Formatted `M d, Y` |
| `date_created` | `pra_created_at` | Formatted `M d, Y` |

### Compatibility / Action-handler Fields

| Key | Source |
|-----|--------|
| `source_instrument_capture_id` | `mfn.source_instrument_capture_id` |
| `source_pra_id` | `mfn.source_pra_id` |
| `source_temp_fileno` | Fallback chain from instrument_capture → pra → fileNumber |
| `source_prop_id` | COALESCE from capture → pra → subquery |
| `purpose` | `source_capture.purpose` |
| `party_1_name` | COALESCE(`source_capture.party_1_name`, `source_pra.Grantor`, `source_pra.party_1`) |
| `party_2_name` | COALESCE(`source_capture.party_2_name`, `source_pra.Grantee`, `source_pra.party_2`) |
| `party_1_phone`, `party_1_address`, `party_2_phone`, `party_2_address` | From `source_capture` |
| `district` | `source_capture.district` |
| `source_mls_file_no_id` | `mfn.id` |

---

## 5. View / Blade Template

**File:** `resources/views/lands_one_stop_shop/applications.blade.php`

### Query Parameter Behavior

| Parameter | Blade Logic |
|-----------|-------------|
| `type=change-of-name` | Sets page title to "Applications (Change of Name)" and shows change-of-name action menu |
| `source=lands-one-stop-shop` | Combined with `type=change-of-name`, shows the "Generate FileNos" variant action menu |

### Summary Cards (6 total)

| Card | Value Source | Visibility |
|------|-------------|------------|
| Total Commissioned | `$totalCommissioned` (separate COUNT query) | Visible |
| OSS Records | `$totalOssRecords` | **Hidden** (`hidden` class) |
| Residential | `$cardCounts['Residential']` | Visible |
| Commercial | `$cardCounts['Commercial']` | Visible |
| Industrial | `$cardCounts['Industrial']` | Visible |
| Agriculture | `$cardCounts['Agriculture']` | Visible |

### Table Columns (15)

| # | Header | Data Key | Notes |
|---|--------|----------|-------|
| 1 | S/N | `$loop->iteration` | Sequential row number |
| 2 | Customer Type | `customer_type` | Title-cased |
| 3 | Source | `source` | Color-coded badge (orange for Resettlement, violet for Direct Allocation, blue for others) |
| 4 | MLS File No | `mls_file_no` | Blue monospace font + clickable TEMP file link below |
| 5 | File Title | `file_title` | |
| 6 | Land Use | `land_use` | Green badge |
| 7 | TP No | `tp_no` | |
| 8 | Plot No | `plot_no` | |
| 9 | LGA | `lga` | |
| 10 | Location | `location` | Wraps text, 180–260px width |
| 11 | Commissioned By | `commissioned_by` | |
| 12 | Time Commissioned | `time_commissioned` | Monospace |
| 13 | Date Commissioned | `date_commissioned` | Monospace |
| 14 | Date Created | `date_created` | Monospace, sortable via `data-order` |
| 15 | Actions | — | Dropdown menu |

### Action Menu (Change of Name variant — `type=change-of-name`)

| Action | JS Handler |
|--------|------------|
| View Record | — |
| Edit Record | `openOpEditModal(this)` |
| Verification | `openVerificationForOP(this)` |
| Acknowledgement | `openAcknowledgementModal(this)` |
| Land 12 | `openLand12ForOP(this)` |
| Recommendation | `openRecommendationModal(this)` |
| Print Manager | `openPrintManagerModal(this)` |
| Delete Entry | — |

### Toolbar Features

- **Search**: Client-side filter via `#op-search` input
- **Filter Bar**: Toggle-able advanced filters (File Title, LGA, Source, MLS File No, Commissioned By, Date Range)
- **CSV Export**: `exportOssTableToCsv()`
- **Print/PDF**: `printOssTable()`
- **Row Limit**: Dropdown (25/50/100/150/200)
- **FileNo Commissioning**: `#btn-file-commissioning` button
- **Fetch Existing File Record**: `openFfrModal()` button

---

## 6. Model

**File:** `app/Models/OpResettlementApplication.php`

```php
class OpResettlementApplication extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'op_resettlement_applications';
    protected $guarded = [];
}
```

> **Note:** This Eloquent model exists but is **not used** by the controller's `index()` method. The controller builds raw queries using the `DB` facade against `fileNumber`, `mls_file_no`, `instrument_capture`, and `pra` tables directly.

---

## 7. Data Flow Diagram

```
Browser Request
    │
    ▼
Route: GET /lands-one-stop-shop/applications/op-resettlement?type=change-of-name
    │
    ▼
OpResettlementApplicationController::index()
    │
    ├──► Schema::hasColumn() checks (runtime column detection)
    │
    ├──► Main Query: fileNumber + mls_file_no + instrument_capture + pra
    │    WHERE sub_source = 'OP Change of Name' OR SOURCE = 'FFR_Existing_Capture'
    │    └──► ->map() transforms rows into display-ready associative arrays
    │
    ├──► Card Count Query: COUNT(*) with same WHERE conditions
    │
    ├──► OSS Count Query: oss_applications table (with fallback)
    │
    ├──► Reference Data: States, LGAs, Districts, Street Names
    │
    └──► return view('lands_one_stop_shop.applications', [...])
              │
              ▼
         Blade renders table with DataTables (client-side pagination/sorting)
```
