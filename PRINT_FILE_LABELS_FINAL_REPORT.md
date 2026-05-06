# Print File Labels — Final Study Report

## 1. Overview

The Print File Labels module generates and prints physical rack/shelf labels for files held in the land registry archive. It handles two distinct file populations (KANGIS and SLTR) through one shared controller, view set, and API surface. The goal of this report is to document everything needed to copy this module and create **two separate pages**: one for **KANGIS** files and one for **SLTR** files.

---

## 2. Entry Points

| Item | Path | Notes |
|---|---|---|
| Main page (combined) | `GET /printlabel` | Accepts `?url=st` to switch to ST mode |
| Primary route file | `routes/web.php` L1659 | Route group `prefix('printlabel')` |
| Secondary route file | `routes/apps2.php` L295 | Extra API endpoints |
| Controller | `app/Http/Controllers/PrintLabelController.php` | Single controller, both modes |
| Main view | `resources/views/printlabel/index.blade.php` | Shell |
| Page partial | `resources/views/printlabel/partials/page.blade.php` | All tabs/UI |
| JS logic | `resources/views/printlabel/assets/js.blade.php` | All state + AJAX |
| Print template | `resources/views/printlabel/print-file-lab.blade.php` | Standalone print window |

---

## 3. Data Models

All models use `protected $connection = 'sqlsrv'`.

| Model | Table | Purpose |
|---|---|---|
| `PrintLabelBatch` | `print_label_batches` | One batch = one print job |
| `PrintLabelBatchItem` | `print_label_batch_items` | One row per file in the batch |
| `RackShelfLabel` | `Rack_Shelf_Labels` | Rack/shelf label master; tracks usage counter |
| `FileIndexing` | `file_indexings` | Source file records (both KANGIS and SLTR live here) |

Key columns on `file_indexings` used by this module:
- `batch_no` — KANGIS batch reference
- `general_registry` — Registry code (DCIV, DEEDS, MORTGAGE, etc.)
- `main_application_id` / `subapplication_id` — ST/SLTR links
- `shelf_location` — human-readable shelf assignment
- `shelf_label_id` — FK to `Rack_Shelf_Labels.id`
- `st_fillno` — ST file number field

---

## 4. Two Distinct Modes (Current Combined Page)

### 4.1 KANGIS / Lands Registry Mode (default)
- URL: `/printlabel` (no query param)
- Files queried from `file_indexings` where `batch_no IS NOT NULL`
- Batch preview reads from `grouping` table using `registry_batch_no`
- `globalRegistrySelect` defaults to `LANDS`
- Rack system and all shelf controls are active

### 4.2 SLTR / ST Mode
- URL: `/printlabel?url=st`
- Files queried from `file_indexings` where `main_application_id IS NOT NULL OR subapplication_id IS NOT NULL`
- Records come from `file_indexings` only (no `grouping` table join)
- Controller detects mode via `$request->get('url') === 'st'`
- Page title changes to "Print ST File Labels"
- Max file selection: **20 files** (vs 30 for KANGIS)

---

## 5. Controller — Key Methods

| Method | HTTP | Route | Description |
|---|---|---|---|
| `index` | GET | `/printlabel` | Render page, pass stats |
| `getAvailableFiles` | GET | `/printlabel/api/files` | File picker AJAX, supports `?st=true` and `?registry=CODE` |
| `previewGroupingBatch` | GET | `/printlabel/api/grouping/preview` | Load grouping-based batch preview |
| `createBatch` | POST | `/printlabel/api/batch` | Create batch, assign to shelf labels |
| `getBatches` | GET | `/printlabel/api/batches` | List all batches |
| `getBatchForPrinting` | GET | `/printlabel/api/batch/{id}/print` | Return print payload |
| `markBatchAsPrinted` | PATCH | `/printlabel/api/batch/{id}/print` | Mark batch as printed |
| `deleteBatch` | DELETE | `/printlabel/api/batch/{id}` | Delete a batch |
| `getRackLabelStatus` | GET | `/printlabel/api/rack-label/status` | Get current shelf counter |
| `searchRegistryBatches` | GET | `/printlabel/api/registry-batches` | Search registry batch numbers |
| `getPrintableRegistries` | GET | (apps2) | Return active non-Lands registries |
| `lookupFileNumbers` | POST | `/printlabel/api/override/lookup` | Manual file number override lookup |

---

## 6. Rack / Shelf System

### Rack Types
| Type | Max shelves per letter | Shelf capacity (files) |
|---|---|---|
| `default` | 48 | 100 |
| `28` | 28 | 100 |
| `42` | 42 | 100 |

### Shelf Label Format
- Format: `{RACK_LETTER}{SHELF_NUMBER}` e.g. `A1`, `B28`, `AB3`
- Letter advances: `A → B → … → Z → AA → AB …`
- When shelf reaches rack-type max, letter advances (e.g. `A28 → B1` in 28-mode)
- Capacity always 100 files per shelf label regardless of rack type

### Relevant Constants (Controller)
```php
private const RACK_SHELF_CAPACITY = 100;
private const MAX_BATCH_SELECTION = 500;
private const RACK_TYPE_SHELVES = ['default' => 48, '28' => 28, '42' => 42];
```

### Relevant Constants (JS)
```javascript
const RACK_MAX_SHELVES = { default: 48, '28': 28, '42': 42 };
const LABEL_CAPACITY = 100;
```

---

## 7. Frontend Architecture

The entire UI is one Alpine.js-free, plain-JS state machine inside `js.blade.php`.

```javascript
let state = {
    selectedFiles: [],            // files chosen in picker
    registryId: 'LANDS',         // active global registry
    rackSystem: 'default',        // '28', '42', or 'default'
    rackPrimary: 'A',             // primary rack letter
    shelfNumber: '1',             // shelf within rack
    fullLabel: 'A1',              // computed label string
    stFilterActive: false,        // true when ?url=st
    batchMode: true,              // grouping mode vs manual
    shelfLabelMode: false,        // print shelf-only labels
    ...
}
```

**Tab flow:** `Select Files → Label Settings → Preview & Print`

Each tab advances only when previous step is complete. "Continue to Label Settings" button is gated by file count validation:
- Regular (KANGIS): 1–30 files
- ST (SLTR): 1–20 files

---

## 8. Print Template

`print-file-lab.blade.php` is a standalone full-page Blade view (not extending `layouts.app`).
- 25 labels per printed page (`LABELS_PER_PAGE = 25`)
- QR code generated client-side via `QRious` library
- Payload from `getBatchForPrinting` contains `records` array with file metadata + shelf assignment
- After print window closes, opener page is notified to refresh batch list

---

## 9. File Source Differences: KANGIS vs SLTR

| Factor | KANGIS | SLTR / ST |
|---|---|---|
| File filter | `batch_no IS NOT NULL` | `main_application_id IS NOT NULL OR subapplication_id IS NOT NULL` |
| Batch preview source | `grouping` table | `file_indexings` only |
| Max selection | 30 | 20 |
| Rack type applies | Yes | Yes (same system) |
| Registry dropdown | LANDS (default) + all active registries | Same |
| QR payload | `file_number`, `shelf_label`, `registry` | `st_fillno` / `np_fileno`, `shelf_label` |
| Page title | "Print File Labels" | "Print ST File Labels" |
| ST-specific columns shown | No | `st_fillno`, application type |

---

## 10. Existing Route Summary

### `routes/web.php` (Primary)
```
GET    /printlabel                          → index()
GET    /printlabel/api/files                → getAvailableFiles()
POST   /printlabel/api/batch                → createBatch()
GET    /printlabel/api/batches              → getBatches()
GET    /printlabel/api/batch/{id}           → getBatchDetails()
GET    /printlabel/api/batch/{id}/print     → getBatchForPrinting()
PATCH  /printlabel/api/batch/{id}/print     → markBatchAsPrinted()
DELETE /printlabel/api/batch/{id}           → deleteBatch()
GET    /printlabel/api/statistics           → getStatistics()
GET    /printlabel/api/grouping/preview     → previewGroupingBatch()
GET    /printlabel/api/printable-registries → getPrintableRegistries() [not in web.php, served via apps2]
GET    /printlabel/print-template           → returns print-file-lab.blade.php
```

### `routes/apps2.php` (Additional API)
```
GET    /printlabel/api/batches/options      → getBatchOptions()
GET    /printlabel/api/batches/search       → searchBatches()
GET    /printlabel/api/batches/preview      → previewBatch()
POST   /printlabel/api/batches/assign       → assignBatch()
GET    /printlabel/api/statistics           → getStatistics()
GET    /printlabel/api/grouping/preview     → previewGroupingBatch()
GET    /printlabel/api/registry-batches     → searchRegistryBatches()
GET    /printlabel/api/rack-label/status    → getRackLabelStatus()
POST   /printlabel/api/override/lookup      → lookupFileNumbers()
```

---

## 11. What "Copy for KANGIS and SLTR Separately" Will Require

When the user provides logic for the two new pages, the following pieces will need to be created/duplicated per page:

### A. Routes
Two new prefixes in `routes/app3.php` (preferred for new work):
```
/printlabel-kangis          → KANGIS-specific page
/printlabel-sltr            → SLTR-specific page
```
API endpoints can remain shared on `PrintLabelController` (add a `type` parameter), or a new `KangisLabelController` / `SltrLabelController` can be added.

### B. Controller Changes
- `index()` — pass `type = 'kangis'` or `type = 'sltr'` to view
- `getAvailableFiles()` — enforce correct file filter per type at the route level (not URL param)
- `createBatch()` — tag batch with type/source for audit purposes
- No changes needed to rack/shelf logic (shared)

### C. Views
- `resources/views/printlabel-kangis/index.blade.php` — extends same layout, includes type-specific partial
- `resources/views/printlabel-sltr/index.blade.php` — same structure
- Partials and JS can be either duplicated or include-driven with a `$type` variable

### D. Key Separation Points in JS
- `stFilterActive` flag becomes implicit (always false for KANGIS, always true for SLTR)
- Max file count: KANGIS=30, SLTR=20
- File query param: KANGIS sends no `st` param; SLTR sends `st=true`
- Column display: SLTR shows `st_fillno`/`np_fileno` instead of `batch_no`

### E. No DB Changes Required
- Both pages use existing tables
- No new migrations needed unless the user wants to tag batches by source type

---

## 12. Files to Study / Copy

| File | Role |
|---|---|
| [app/Http/Controllers/PrintLabelController.php](app/Http/Controllers/PrintLabelController.php) | Backend logic (copy + adapt) |
| [app/Models/PrintLabelBatch.php](app/Models/PrintLabelBatch.php) | Batch model |
| [app/Models/PrintLabelBatchItem.php](app/Models/PrintLabelBatchItem.php) | Batch item model |
| [app/Models/RackShelfLabel.php](app/Models/RackShelfLabel.php) | Rack shelf model |
| [resources/views/printlabel/index.blade.php](resources/views/printlabel/index.blade.php) | Shell view |
| [resources/views/printlabel/partials/page.blade.php](resources/views/printlabel/partials/page.blade.php) | Tab UI |
| [resources/views/printlabel/assets/js.blade.php](resources/views/printlabel/assets/js.blade.php) | All frontend state/logic |
| [resources/views/printlabel/assets/style.blade.php](resources/views/printlabel/assets/style.blade.php) | CSS |
| [resources/views/printlabel/assets/head.blade.php](resources/views/printlabel/assets/head.blade.php) | Assets/head includes |
| [resources/views/printlabel/assets/labels-css.blade.php](resources/views/printlabel/assets/labels-css.blade.php) | Label-print CSS |
| [resources/views/printlabel/print-file-lab.blade.php](resources/views/printlabel/print-file-lab.blade.php) | Print window template |
| [routes/web.php](routes/web.php) L1659 | Current routes |
| [routes/apps2.php](routes/apps2.php) L295 | Extra API routes |

---

## 13. Known Issues to Be Aware Of

1. **Capacity inconsistency**: Some code paths treat `28`/`42` as capacity (not max-shelf). The confirmed rule is capacity = always 100; rack type controls shelf count only.
2. **Route duplication**: Both `routes/web.php` and `routes/apps2.php` declare the same `printlabel.index` name — the last one loaded wins.
3. **Batch size constraint**: SQL creation script sets `batch_size <= 100` constraint but controller allows 500. Be consistent when copying.
4. **`capacity_overridden` flag**: Always evaluates false due to logic bug in `claimRackLabelForRange` — harmless but misleading metadata.
5. **Print history table**: Currently shows hardcoded placeholder data (PRINT-001, etc.) — needs real data binding if required for new pages.

---

## 14. Dependency Summary

```
PrintLabelController
├── FileIndexing (sqlsrv)      ← source records
├── PrintLabelBatch (sqlsrv)   ← batch records
├── PrintLabelBatchItem (sqlsrv)
├── RackShelfLabel (sqlsrv)    ← Rack_Shelf_Labels table
└── DB::connection('sqlsrv')   ← raw queries for grouping, registries
```

No Mail, Queue, or caching dependencies. All queries are synchronous. Registry list comes from the `registries` table (active, non-LANDS rows).

---

*Study completed: April 14, 2026. Ready to proceed with KANGIS and SLTR separate page implementation once logic is provided.*
