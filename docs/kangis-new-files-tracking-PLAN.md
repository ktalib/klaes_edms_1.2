# KANGIS New Files Tracking — Implementation Plan

**Status:** Draft · April 2026  
**Scope:** New KANGIS file type, 7-step office workflow, KANGIS tracking sheet, SMS stub  
**Reference:** `docs/kangis new files tracking.md`

---

## 1. Overview

The current file tracker handles **existing files** already in the KANGIS system.  
This plan extends tracking for **New KANGIS** files — files being created and processed for the first time — following a fixed 7-stage office journey from Indexing through to Registry.

---

## 2. Workflow Stages

```
[Indexing Tracking Sheet (Origin)]
           ↓ Log Out
   [Customer Service]
           ↓ Log Out
   [Vetting Committee]
           ↓ Log Out
    [Geometry (GIS)]
           ↓ Log Out
   [Production (GIS)]
           ↓ Log Out
    [Collection (DG)]
           ↓ Log In (final destination)
        [Registry]
```

Each log-out from a department triggers an SMS stub to the applicant.

---

## 3. File Number Types in KANGIS Registry

When indexing under KANGIS Registry, the user selects one of three master file number types:

| Type | Field in DB (`file_indexings.file_number`) | Meaning |
|------|-------------------------------------------|---------|
| **MLS** | Legacy MLS number | Ministry of Land & Survey legacy |
| **KANGIS** | Existing KANGIS number | Already-registered KANGIS file |
| **New KANGIS** | New KN-series number (e.g. `KN1`, `KN100`, `KN10000`) | Brand new file, triggers 7-step workflow |

The selected type and value go to `file_indexings.file_number`. All three types must also be displayed on the KANGIS tracking sheet.

---

## 4. Components To Build / Modify

### 4.1 Database — `kn_grouping` Table (already exists, confirmed in DB)

> **No new table needed.** `kn_grouping` already exists and follows the same pattern as `gkn_grouping`, `lpkn_grouping`, and `miscs_kn_grouping`. It is **distinct from `kangis_grouping`** — one file can have entries in both (e.g. a file can have an existing KANGIS number _and_ a new KN number).
>
> The Laravel tinker check failed because it connects to a different DB instance. SSMS confirms the table exists with 100+ rows (`KN1`–`KN100`).

Confirmed columns in `kn_grouping` (from SSMS):

| Column | Purpose |
|--------|---------|
| `id` | PK |
| `kn_awaiting_fileno` | New KN file number — format `KN{serial}` e.g. `KN1`, `KN100`, `KN10000`. **This is the canonical New KANGIS file number.** |
| `kn_fileno` | Final file number once assigned |
| `date` | Date created |
| `created_by` | User ID |
| `indexed_by` | Indexing source (e.g. `Generated`) |
| `date_index` | Date indexed |
| `year` | Year |
| `landuse` | Land use code |
| `updated_by`, `deleted_by` | Audit fields |
| `number` | Sequential serial number |
| `registry` | Registry code (`KN`) |
| `mapping` | 0 = not yet mapped to MLS, 1 = mapped |
| `group` | Group/batch reference |
| `mdc_batch_no` | MDC batch number |

**`GroupingFileNumberService` must be extended** to recognise `KN` prefix → `kn_grouping` table, following the same pattern as the other registries:

```php
// In getTableName()
if (str_contains($f, 'KN') && !str_contains($f, 'GKN') && !str_contains($f, 'LPKN')) return 'kn_grouping';

// In getColumnNameByTable()
case 'kn_grouping': return 'kn_awaiting_fileno';

// In getMlsColumnName()
'kn_grouping' => 'kn_fileno',
```

**New columns needed on `file_indexings`** (add with `IF NOT EXISTS` guard, idempotent):

```sql
-- Track which file number type was selected at indexing time
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('file_indexings') AND name = 'kangis_file_type')
    ALTER TABLE file_indexings ADD kangis_file_type VARCHAR(20) NULL;  -- 'MLS' | 'KANGIS' | 'new_kangis'

-- Store all three numbers side-by-side for the tracking sheet
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('file_indexings') AND name = 'mls_file_no')
    ALTER TABLE file_indexings ADD mls_file_no VARCHAR(100) NULL;

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('file_indexings') AND name = 'kangis_file_no')
    ALTER TABLE file_indexings ADD kangis_file_no VARCHAR(100) NULL;

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('file_indexings') AND name = 'new_kangis_file_no')
    ALTER TABLE file_indexings ADD new_kangis_file_no VARCHAR(100) NULL;

-- Phone number for SMS (copied from entity/customer at index time)
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('file_indexings') AND name = 'applicant_phone')
    ALTER TABLE file_indexings ADD applicant_phone VARCHAR(30) NULL;

-- Link back to kn_grouping (nullable — only set for new_kangis type)
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('file_indexings') AND name = 'kn_grouping_id')
    ALTER TABLE file_indexings ADD kn_grouping_id BIGINT NULL REFERENCES kn_grouping(id);
```

> Add these to `database/migrations/` and provide raw SQL for online DB.

### 4.2 `file_indexings` — Entity & Customer Tables

Add cross-reference columns so all three file number types are searchable from the entity/customer records when a new KANGIS file is indexed:

```sql
-- entities table
ALTER TABLE entities ADD new_kangis_file_no VARCHAR(100) NULL;

-- customers table  
ALTER TABLE customers ADD new_kangis_file_no VARCHAR(100) NULL;
```

These are populated in `FileIndexController::store()` alongside existing MLS/KANGIS columns.

---

### 4.3 Create File Index UI — `resources/views/fileindexing/file-indexing.blade.php`

**Current state:** MLS and KANGIS radio buttons exist; "New KANGIS" exists as a radio option (line 1045) but the downstream fields are incomplete.

**Changes needed:**

1. **Enable New KANGIS field set** — currently disabled/hidden; activate the conditional panel that shows the `new_kangis_file_no` input.

2. **Guard: "Already Indexed?" confirmation modal** — when user picks "New KANGIS" and tries to save, show a SweetAlert2 confirm:

   ```
   ┌──────────────────────────────────────────────────────────────┐
   │  Has this file already been indexed?                         │
   │                                                              │
   │  [Yes, it's indexed — continue]   [No — open indexing form] │
   └──────────────────────────────────────────────────────────────┘
   ```

   - **"Yes"** → proceed with normal save.
   - **"No"** → `window.open(route('fileindex.create'), '_blank')` and abort the current submit.

3. **Phone number field** — add `applicant_phone` input (or auto-populate from the linked entity/customer).

4. **Validation** — add `new_kangis_file_no` to the file number type switch block already at line 1037–1046.

---

### 4.4 `FileIndexController::store()` — `app/Http/Controllers/FileIndexController.php`

Add to the validated payload processing:

```php
// Persist extra file number columns
$fileIndexing->kangis_file_type    = $validated['kangis_file_type'];     // 'MLS'|'KANGIS'|'new_kangis'
$fileIndexing->mls_file_no         = $validated['mls_file_no']        ?? null;
$fileIndexing->kangis_file_no      = $validated['kangis_file_no']      ?? null;
$fileIndexing->new_kangis_file_no  = $validated['new_kangis_file_no']  ?? null;
$fileIndexing->applicant_phone     = $validated['applicant_phone']      ?? null;
```

When `kangis_file_type === 'new_kangis'`, link `kn_grouping_id` to the matching `kn_grouping` row (looked up by `kn_awaiting_fileno`).

Also push `new_kangis_file_no` into the matched `entities`/`customers` row (same pattern as existing MLS/KANGIS cross-ref).

---

### 4.5 KANGIS Tracking Sheet — New View

**Create:** `resources/views/fileindexing/kangis-tracking-sheet.blade.php`  
**Based on:** `resources/views/fileindexing/batch-tracking-sheet.blade.php`

Key differences from the existing batch sheet:

| Feature | batch-tracking-sheet | kangis-tracking-sheet |
|---------|---------------------|-----------------------|
| Header | Ministry branding | KANGIS Registry branding + `assets/logo/kangis.jpg` |
| File number columns | Single `file_number` | Three columns: MLS / KANGIS / New KANGIS |
| Workflow stages table | None | 7-stage sign-off grid (each dept has a signature row) |
| QR code data | `file_number` | `tracking_id` (for Search File Trackers scan) |

**Workflow sign-off grid on the printed sheet:**

```
┌────────────────────┬──────────┬──────────┬──────────────────────────┐
│ Department         │ Date Out │ Date In  │ Officer Signature         │
├────────────────────┼──────────┼──────────┼──────────────────────────┤
│ Customer Service   │          │          │                          │
│ Vetting Committee  │          │          │                          │
│ Geometry (GIS)     │          │          │                          │
│ Production (GIS)   │          │          │                          │
│ Collection (DG)    │          │          │                          │
│ Registry           │          │          │                          │
└────────────────────┴──────────┴──────────┴──────────────────────────┘
```

---

### 4.6 Controller Method — `generateKangisTrackingSheet()`

Add to `FileIndexController` (or a new `KangisTrackingController`):

```php
public function generateKangisTrackingSheet(Request $request)
{
    $fileIds = explode(',', $request->input('files', ''));
    $fileIndexings = FileIndexing::whereIn('id', $fileIds)
        ->where('kangis_file_type', 'new_kangis')  // or all three types
        ->get();

    $trackersData = /* build tracker map same as generateBatchTrackingSheet */;

    return view('fileindexing.kangis-tracking-sheet', compact(
        'fileIndexings', 'trackersData'
    ));
}
```

Route (add to `routes/web.php` above existing wildcard routes):

```php
Route::get('/fileindexing/kangis-tracking-sheet',
    [FileIndexController::class, 'generateKangisTrackingSheet'])
    ->name('fileindexing.kangis-tracking-sheet');
```

---

### 4.7 File Tracker Integration — `file_tracker` Table

When a new KANGIS file is indexed, automatically create a `file_tracker` row:

```php
FileTracker::create([
    'module'               => 'kangis',
    'file_number'          => $fileIndexing->new_kangis_file_no ?? $fileIndexing->file_number,
    'file_title'           => $fileIndexing->file_title,
    'workflow_type'        => FileTracker::WORKFLOW_KANGIS_NEW,   // new constant
    'workflow_step'        => 1,
    'current_office_code'  => 'KRE',   // KANGIS Registry (origin)
    'current_office_name'  => 'KANGIS Registry',
    'module_meta'          => json_encode([
        'file_indexing_id' => $fileIndexing->id,
        'mls_file_no'      => $fileIndexing->mls_file_no,
        'kangis_file_no'   => $fileIndexing->kangis_file_no,
        'new_kangis_file_no'=> $fileIndexing->new_kangis_file_no,
        'applicant_phone'  => $fileIndexing->applicant_phone,
    ]),
]);
```

The existing `file_tracker` **QR scan → "Search File Trackers"** flow is reused as-is for each department to log the file in.

---

### 4.8 7-Step Workflow Definition — `FileTracker` Model

Add a new constant alongside `WORKFLOW_KANGIS_3STEP`:

```php
const WORKFLOW_KANGIS_NEW = 'kangis_new_file';

const KANGIS_NEW_FILE_DEFINITION = [
    1 => ['from_code' => 'KRE',  'from_name' => 'KANGIS Registry',    'to_code' => 'CS',   'to_name' => 'Customer Service',    'label' => 'Customer Service',   'action' => 'log_out'],
    2 => ['from_code' => 'CS',   'from_name' => 'Customer Service',   'to_code' => 'VET',  'to_name' => 'Vetting Committee',   'label' => 'Vetting',            'action' => 'log_out'],
    3 => ['from_code' => 'VET',  'from_name' => 'Vetting Committee',  'to_code' => 'GEO',  'to_name' => 'Geometry (GIS)',      'label' => 'Geometry',           'action' => 'log_out'],
    4 => ['from_code' => 'GEO',  'from_name' => 'Geometry (GIS)',     'to_code' => 'PROD', 'to_name' => 'Production (GIS)',    'label' => 'Production',         'action' => 'log_out'],
    5 => ['from_code' => 'PROD', 'from_name' => 'Production (GIS)',   'to_code' => 'DG',   'to_name' => 'Collection (DG)',     'label' => 'Collection',         'action' => 'log_out'],
    6 => ['from_code' => 'DG',   'from_name' => 'Collection (DG)',    'to_code' => 'KRE',  'to_name' => 'Registry',            'label' => 'Registry Log-In',    'action' => 'log_in'],
];
```

Extend `getWorkflowProgress()` and `advanceWorkflowStep()` to handle both `kangis_3step` and `kangis_new_file`.

---

### 4.9 SMS — EBulkSMS Integration (Triggered on Log-Out)

**Provider:** [EBulkSMS](https://www.ebulksms.com) — JSON POST API  
**Endpoint:** `https://api.ebulksms.com/sendsms.json`  
**Config keys** (add to `config/services.php`):

```php
'ebulksms' => [
    'username' => env('EBULKSMS_USERNAME'),   // your login email
    'apikey'   => env('EBULKSMS_APIKEY'),
    'sender'   => env('EBULKSMS_SENDER', 'KANGIS'),  // max 11 chars
],
```

Add to `.env` (and `.env.example`):
```
EBULKSMS_USERNAME=
EBULKSMS_APIKEY=
EBULKSMS_SENDER=KANGIS
```

Create `app/Services/EBulkSmsService.php`:

```php
<?php
namespace App\Services;

use Illuminate\Support\Str;

class EBulkSmsService
{
    private string $endpoint = 'https://api.ebulksms.com/sendsms.json';

    public function send(string $phone, string $message): bool
    {
        $username = config('services.ebulksms.username');
        $apikey   = config('services.ebulksms.apikey');
        $sender   = config('services.ebulksms.sender', 'KANGIS');

        if (!$username || !$apikey) {
            return false;
        }

        // Normalize Nigerian phone number to international format (234xxxxxxxxxx)
        $mobile = ltrim($phone, '+');
        if (str_starts_with($mobile, '0')) {
            $mobile = '234' . substr($mobile, 1);
        }

        $payload = json_encode([
            'SMS' => [
                'auth'       => ['username' => $username, 'apikey' => $apikey],
                'message'    => [
                    'sender'      => substr($sender, 0, 11),
                    'messagetext' => substr($message, 0, 160),
                    'flash'       => '0',
                ],
                'recipients' => [
                    'gsm' => [
                        ['msidn' => $mobile, 'msgid' => substr(Str::uuid()->toString(), 0, 30)],
                    ],
                ],
                'dndsender'  => '0',
            ],
        ]);

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        return ($result['response']['status'] ?? '') === 'SUCCESS';
    }
}
```

In `FileTrackerApiController::addMovement()`, after advancing the step for a `kangis_new_file` tracker:

```php
$meta  = json_decode($tracker->module_meta ?? '{}', true);
$phone = $meta['applicant_phone'] ?? null;
$smsSent = false;

if ($phone) {
    $msg = "Dear Applicant, your KANGIS file {$tracker->file_number} has moved to {$tracker->current_office_name}. KANGIS Registry.";
    $smsSent = app(\App\Services\EBulkSmsService::class)->send($phone, $msg);
}
```

API response shape:

```json
{
  "success": true,
  "sms": { "sent": true, "phone": "0801..." }
}
```

Frontend SweetAlert2 toast:
- SMS sent: `✅ File logged. Applicant notified via SMS.`
- SMS failed: `✅ File logged. ⚠️ SMS notification could not be sent.`

---

### 4.10 `?url=kangis` Create File Tracker Page ✅ Confirmed

All New KANGIS file tracker creation happens under the existing `?url=kangis` module.

On the KANGIS tracker create form, add a **"New File (New KANGIS)"** toggle that:
- Sets `workflow_type = kangis_new_file`
- Pre-selects KANGIS Registry as origin
- Searches `kn_grouping.kn_awaiting_fileno` to validate/auto-complete the file number
- Shows a link "Not indexed yet? Index this file first →" that opens `fileindex.create` in a new tab

This reuses the existing stepper partial (`workflow-3step-js.blade.php`) extended for 6 steps.

---

## 5. Offices to Seed (if not present)

```sql
-- Ensure all 7 workflow offices exist in the offices table
INSERT INTO offices (code, name, department, is_active) VALUES
  ('KRE',  'KANGIS Registry',   'Registry',         1),
  ('CS',   'Customer Service',  'Customer Service', 1),
  ('VET',  'Vetting Committee', 'Vetting',          1),
  ('GEO',  'Geometry GIS',      'GIS',              1),
  ('PROD', 'Production GIS',    'GIS',              1),
  ('DG',   'Collection DG',     'Director General', 1);
-- (KRE and DG may already exist — use IF NOT EXISTS / MERGE)
```

---

## 6. File Delivery Order

| # | File / Component | Action | Notes |
|---|-----------------|--------|-------|
| 1 | `database/migrations/…_add_new_kangis_columns_to_file_indexings.php` | Create | SQL Server; idempotent — adds 6 columns only, `kangis_grouping` already exists |
| 2 | `app/Services/EBulkSmsService.php` | Create | EBulkSMS JSON API wrapper — see §4.9 for full code |
| 3 | `app/Models/FileTracker.php` | Modify | Add `WORKFLOW_KANGIS_NEW`, `KANGIS_NEW_FILE_DEFINITION`, extend methods |
| 4 | `app/Http/Controllers/FileIndexController.php` | Modify | `store()` — persist extra columns, create tracker, seed entity/customer |
| 5 | `app/Http/Controllers/FileIndexController.php` | Modify | Add `generateKangisTrackingSheet()` |
| 6 | `app/Http/Controllers/Api/FileTrackerApiController.php` | Modify | `addMovement()` — SMS stub |
| 7 | `resources/views/fileindexing/file-indexing.blade.php` | Modify | Enable New KANGIS fields, "already indexed?" guard modal |
| 8 | `resources/views/fileindexing/kangis-tracking-sheet.blade.php` | Create | KANGIS-branded sheet with 3 file-number columns + 7-stage grid |
| 9 | `resources/views/create_file_tracker_page/index.blade.php` | Modify | "New File" toggle, 6-step stepper, link to indexing |
| 10 | `resources/views/create_file_tracker_page/partials/workflow-3step-js.blade.php` | Modify | Extend to support 6-step `kangis_new_file` definition |
| 11 | `routes/web.php` | Modify | Add `kangis-tracking-sheet` route |
| 12 | `database/sql/seed_kangis_workflow_offices.sql` | Create | Idempotent office seeding |

---

## 7. Testing Checklist

- [ ] Create a New KANGIS file index — confirm tracker row auto-created at origin (KRE)
- [ ] Attempt to create tracker without indexing — confirm "Already Indexed?" modal fires with correct "No → open tab" behaviour
- [ ] Log out file from Customer Service via QR scan — confirm step advances to Vetting; SMS stub SweetAlert appears
- [ ] Repeat log-out for all 6 transitions — confirm `workflow_step` increments correctly
- [ ] Final log-in at Registry — confirm tracker status = completed
- [ ] Print KANGIS tracking sheet — all 3 file numbers visible (MLS, KANGIS, New KANGIS); 7-stage sign-off grid present; KANGIS logo shows
- [ ] Export PDF/CSV from `?url=kangis` — KANGIS branding applied (per existing monthly report fix)
- [ ] MLS and KANGIS file types still index normally (regression check)

---

## 8. Decisions — Resolved

| # | Question | Decision |
|---|----------|----------|
| 1 | New KANGIS number format? | ✅ `KN{serial}` — e.g. `KN1`, `KN100`, `KN10000`. Stored in `kn_grouping.kn_awaiting_fileno`. No year/dash separator. |
| 2 | Which `kn_grouping` rows are New KANGIS files? | ✅ All rows in `kn_grouping` — table is exclusively for new KN-series files. `kangis_grouping` is separate (KNGP prefix). |
| 3 | Login/Logout status per row? | ✅ Each department row tracks independently: when a dept **receives** the file (logs in) → their row status = **Log In**, login time/date set, logout time/date empty. When that same dept **sends** the file out (logs out) → their row logout time/date fills in, status = **Log Out**. This cascades through all 6 steps. |
| 4 | SMS gateway provider? | ✅ **EBulkSMS** — JSON POST to `https://api.ebulksms.com/sendsms.json`. Config: `EBULKSMS_USERNAME`, `EBULKSMS_APIKEY`, `EBULKSMS_SENDER` in `.env`. See §4.9 for full `EBulkSmsService` implementation. |
| 5 | Which URL/module? | ✅ `?url=kangis` — all New KANGIS tracking is under the existing KANGIS module. Same role gates apply. |


