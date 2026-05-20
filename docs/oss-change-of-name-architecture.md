# OSS File Commissioning & OP Matching — Change of Name
## Architecture Overview Report

**Module:** Lands One-Stop-Shop (OSS)  
**Feature:** Change of Name (Transfer of Title on Occupancy Permit)  
**Entry URL:** `/lands-one-stop-shop/applications/op-resettlement?source=lands-one-stop-shop&type=change-of-name`  
**Date:** 2026-05-20

---

## 1. High-Level Purpose

The Change of Name module is a sub-mode of the OP Resettlement Applications screen. It tracks **Transfer of Title (ToT)** transactions on existing Occupancy Permits — i.e., when the ownership of a land file passes from one person (Grantor) to another (Grantee). It provides file commissioning status, OP-to-ToT matching, scenario classification (standard, subdivision, merger), and multi-table synchronisation across the land records system.

---

## 2. System Boundaries

```
Browser (DataTable UI)
        │
        ▼
Laravel Router  ──routes/app3.php──►  OpResettlementApplicationController
                                               │
                          ┌────────────────────┼────────────────────┐
                          ▼                    ▼                    ▼
                    SQL Server DB         MySQL DB             Blade Views
                  (sqlsrv connection)  (default conn.)   lands_one_stop_shop/
                                                           applications.blade.php
```

- **Primary datastore:** SQL Server (via `sqlsrv` Laravel connection)  
- **Supporting datastore:** MySQL (application config, users, street names, LGAs)  
- **Frontend:** Server-rendered Blade with DataTables JS (no SPA framework)

---

## 3. Route Map

Defined in `routes/app3.php` (lines ~218–260), all routes sit under the `/lands-one-stop-shop` prefix:

| Method | URI | Handler | Purpose |
|--------|-----|---------|---------|
| GET | `/applications/op-resettlement` | `OpResettlementApplicationController@index` | List OP / Change of Name records |
| GET | `/applications/{id}/pra-transactions` | `@praTransactions` | AJAX: fetch full transaction history for a prop_id |
| PATCH | `/applications/{id}/land-use` | `@updateLandUse` | AJAX: update land use on IC record |
| PUT | `/applications/{id}` | `@updateDetails` | AJAX: save edits from Edit modal |
| DELETE | `/applications/{id}` | `@deleteMaster` | AJAX: soft/hard delete single record |
| POST | `/applications/bulk-delete` | `@deleteMasterBulk` | AJAX: bulk delete |
| POST | `/applications/match-op` | `@matchOp` | AJAX: link ToT to its source OP |
| POST | `/applications/flag-merger` | `@flagMergerOp` | AJAX: mark record as merger OP |
| GET | `/bill` | `OpResettlementBillController@index` | Bill listing |
| GET | `/bill/{id}/print` | `@printBill` | Print bill receipt |

**Mode selector:** `?type=change-of-name` on the index route switches the query branch from regular OP display to Transfer of Title display. All other parameters work identically in both modes.

---

## 4. URL Parameter Reference

| Parameter | Accepted Values | Effect |
|-----------|----------------|--------|
| `type` | `change-of-name` | Activates ToT filter; excludes IC-source records |
| `record_type` | `fc` / `fefr` | FC = File Commissioned; FEFR = not yet commissioned |
| `search` | any string | Full-text filter across 14 columns |
| `limit` | `10`–`200` (default `25`) | Rows per page (clamped server-side) |
| `page` | integer (default `1`) | Pagination offset |

---

## 5. Controller Architecture

### 5.1 `OpResettlementApplicationController`
**Path:** `app/Http/Controllers/LandsOneStopShop/OpResettlementApplicationController.php`

This is the primary controller. It contains no service injection — all database logic is inline using the Laravel query builder.

#### `index(Request $request): View`

The most complex method in the module. Responsibilities:

1. **Schema introspection** — checks at runtime whether optional columns (`customers_staging.file_number`, `file_indexings.file_type`, etc.) exist, building COALESCE expressions dynamically to handle schema drift between environments.

2. **Instrument filter branch** — determined by `$isChangeOfName`:
   ```php
   // Change of Name (this URL)
   AND (instrument_type LIKE '%Transfer of Title%'
        OR transaction_type LIKE '%Transfer of Title%')

   // Regular OP view
   AND (instrument_type IS NULL
        OR instrument_type NOT LIKE '%Transfer of Title%') ...
   ```

3. **UNION query construction** — combines two sources:
   - **Branch A (PRA):** Records from `pra` where `system_source = 'OSSOPCHANGEOFNAME'`
   - **Branch B (IC):** Records from `instrument_capture` with `instrument_type = 'Occupancy Permit (OP)'` that have no matching PRA row — **entirely excluded** when `?type=change-of-name` via `WHERE '0' = '1'`

4. **Deduplication** — `ROW_NUMBER() OVER (PARTITION BY prop_id, instrument_type ORDER BY id DESC) = 1` keeps only the latest record per property per instrument type.

5. **11 LEFT JOINs** for data enrichment (see Section 6).

6. **Commissioning filter** — `WHERE mfn.full_file_number IS NOT NULL` (fc) or `IS NULL` (fefr).

7. **Search** — OR-chain across 14 columns including computed COALESCE expressions.

8. **Post-query mapping** — transforms raw SQL rows into normalised arrays:
   - `file_title`: shows `Grantee` (new owner name) in Change of Name mode
   - `land_use`: expands abbreviations (RES→RESIDENTIAL, COM→COMMERCIAL, etc.)
   - `mls_file_no`: falls back from `mlsFNo` → `source_temp_fileno`
   - `commissioned_at`: resolves from `con_commissioned_at` → `commissioning_date` → `fn_created_at`

9. **Scenario detection** — classifies each ToT record:
   - **Merger:** `plot_no` contains multiple plot numbers (`,`, `/`, `&`, `and` patterns)
   - **Subdivision:** same `op_serial_number` produced >1 ToT rows in the result set
   - **Standard:** neither of the above

10. **Summary statistics** — a second, leaner query counts total / RES / COM / IND / AGR records and today's additions for the dashboard cards.

#### `praTransactions(Request $request): JsonResponse`

AJAX endpoint that fetches the full transaction history for a given property. Accepts `prop_id`, `parent_prop_id`, `mls_fileno`, `temp_fileno`, or `source_pra_id`. Auto-discovers `parent_prop_id` if not supplied. Merges PRA rows and IC rows, placing OP records before Transfer of Title in sort order. Also expands merger groups via `merger_group_id`.

#### `updateDetails(Request $request, string $id): JsonResponse`

Multi-table save for the Edit modal. Handles three ID formats:
- `{integer}` — `fileNumber.id` lookup with PRA existence check
- `pra-{id}` — direct PRA row lookup (no fileNumber row)
- `ic-{id}` — direct IC row lookup

Writes are distributed across up to 5 tables in a single transaction:
1. `fileNumber` — file name, plot, TP number, location, LGA
2. `instrument_capture` — full party/property/registration fields (OP rows only)
3. `pra` — Grantor/Grantee (differs for OP vs ToT row type), property fields, registration particulars
4. `pra` siblings — syncs shared property fields to the sibling Transfer of Title row(s)
5. `customers_staging` / `entities_staging` — customer name and address sync

The `row_type` parameter (`op` vs `transfer_of_title`) controls which party fields are written and whether IC is updated.

#### `updateLandUse`, `deleteMaster`, `deleteMasterBulk`, `flagMergerOp`

Focused AJAX helpers. `updateLandUse` writes only to `instrument_capture`. Delete methods check for and respect `is_deleted` soft-delete flags. `flagMergerOp` marks a PRA row with `is_merger_op = 1` and assigns a `merger_group_id`.

### 5.2 `ApplicationController`
**Path:** `app/Http/Controllers/LandsOneStopShop/ApplicationController.php`

Handles the OSS application form lifecycle (residential / commercial / industrial / agricultural). Key methods used by the Change of Name page:

| Method | Description |
|--------|-------------|
| `saveFfrChangeOfName` | Saves Change of Name workflow data to `oss_applications` |
| `saveVerification` | Writes verification record, attaches passport photo |
| `saveAcknowledgementDb` | Persists acknowledgement record |
| `saveRecommendation` | Saves recommendation and generates letter |
| `printAcknowledgement` | Returns printable acknowledgement PDF/view |
| `printVerification` | Returns printable verification form |
| `searchInstrumentCaptures` | AJAX search used by file-number picker |
| `lookupFileIndexing` | AJAX file-index record lookup |

### 5.3 `OpResettlementBillController`
**Path:** `app/Http/Controllers/LandsOneStopShop/OpResettlementBillController.php`

Manages billing for commissioned OP records. `printBill($id)` renders the bill receipt view.

---

## 6. Database Architecture

### 6.1 Primary Tables

#### `pra` (Property Record Account) — SQL Server
The central source of truth for land transactions.

| Column | Role in this feature |
|--------|---------------------|
| `id` | PRA row PK |
| `prop_id` | Property identity key — groups all transactions on one plot |
| `parent_prop_id` | Points to original OP `prop_id` on ToT rows |
| `system_source` | `'OSSOPCHANGEOFNAME'` — scopes all queries in this module |
| `instrument_type` | `'Transfer of Title'` or `'Occupancy Permit (OP)'` |
| `transaction_type` | Alias for `instrument_type` in older rows |
| `mlsFNo` | MLS file number (primary file identifier) |
| `fileno` | Legacy file number (fallback) |
| `temp_fileno` | Temporary file number assigned before commissioning |
| `Grantor` | Previous owner (Kano State Govt on OP rows) |
| `Grantee` | New owner — displayed as `file_title` in Change of Name mode |
| `op_type` / `op_serial_number` | OP classification and serial |
| `merger_group_id` / `is_merger_op` | Merger tracking fields |
| `is_deleted` | Soft delete flag |

#### `instrument_capture` (IC) — SQL Server
Source of truth for Deeds Registration OP captures. Referenced for OP source data; not directly queried as a primary source in Change of Name mode.

#### `fileNumber` — SQL Server
Maps MLS file numbers to commissioned file names and plot details.

| Column | Role |
|--------|------|
| `mlsfNo` | Join key to `pra.mlsFNo` |
| `FileName` | Registered file title (allottee name) |
| `commissioning_date` | Date the file was formally commissioned |
| `SOURCE` | Stamped as `'OSS_CHANGE_OF_NAME'` on saves |
| `is_deleted` | Soft delete; rows with `is_deleted = 1` are excluded |

#### `mls_file_no` — SQL Server
Tracks commissioning events and enrichment data.

| Column | Role |
|--------|------|
| `full_file_number` | Permanent file number; `NULL` = FEFR, non-null = FC |
| `con_commissioned_at` | Commissioning timestamp (highest priority for date display) |
| `tracking_id` | Links to `fileNumber.tracking_id` |
| `source_instrument_capture_id` | Pointer back to the originating IC row |
| `source_pra_id` | Pointer back to the originating PRA row |
| `customer_type` | Individual / Corporate / Multiple |
| `land_use` | Preferred land use (checked first in COALESCE chain) |

### 6.2 Supporting Tables (via LEFT JOINs)

| Table | Join Key | Data Provided |
|-------|----------|--------------|
| `fileNumber` (aliased `fn`) | `mlsFNo` | File title, plot, TP no, commissioning date |
| `mls_file_no` (aliased `mfn`) | `full_file_number` | Commissioning status, customer type, source IDs |
| `instrument_capture` (aliased `source_capture`) | `mfn.source_instrument_capture_id` | Purpose, district, party phones/addresses |
| `users` (aliased `pra_user`) | `TRY_CAST(p.created_by AS INT)` | Human-readable "Commissioned By" from PRA |
| `users` (aliased `fn_user`) | `TRY_CAST(fn.created_by AS INT)` | Human-readable "Commissioned By" from fileNumber |
| `pra` (aliased `tot_agg`) | `parent_prop_id` / `prop_id` | Latest ToT timestamp per OP |
| `pra` (aliased `tf_agg`) | `prop_id` | Earliest temp file number for the parent OP |
| `customers_staging` (aliased `cs_agg`) | `file_number` | Fallback customer type |
| `file_indexings` (aliased `fi_agg`) | `file_number` | Fallback file type / customer type |

### 6.3 OSS Application Tables (MySQL)

| Table | Purpose |
|-------|---------|
| `oss_applications` | Main application form data (all types) |
| `oss_change_of_ownership` | Ownership transfer records |
| `oss_verifications` | Verification records with passport photo |
| `oss_acknowledgements` | Acknowledgement records |
| `oss_application_plot_sizes` | Multiple plot sizes per application |

---

## 7. Data Flow

### 7.1 Page Load (Read Path)

```
GET /lands-one-stop-shop/applications/op-resettlement
    ?source=lands-one-stop-shop&type=change-of-name[&record_type=fc|fefr][&search=...][&page=N][&limit=N]
                                   │
                                   ▼
                    OpResettlementApplicationController::index()
                                   │
                  ┌────────────────┴────────────────┐
                  │   Schema introspection (once)    │
                  │   INFORMATION_SCHEMA.COLUMNS     │
                  └────────────────┬────────────────┘
                                   │
                  ┌────────────────▼────────────────────────┐
                  │  UNION subquery (aliased as `p`)         │
                  │                                          │
                  │  Branch A: pra                           │
                  │    WHERE system_source='OSSOPCHANGEOFNAME'│
                  │    AND instrument_type LIKE '%ToT%'      │
                  │    ROW_NUMBER() OVER (PARTITION BY       │
                  │      prop_id, instrument_type)           │
                  │                                          │
                  │  Branch B: instrument_capture            │
                  │    WHERE '0' = '1'  ← excluded in CoN   │
                  └────────────────┬────────────────────────┘
                                   │ WHERE p.rn = 1
                                   │
                  ┌────────────────▼──────────────────────────┐
                  │  11 LEFT JOINs                             │
                  │  fileNumber, mls_file_no, source_capture,  │
                  │  pra_user, fn_user, tot_agg, tf_agg,       │
                  │  cs_agg, fi_agg                            │
                  └────────────────┬──────────────────────────┘
                                   │
                  ┌────────────────▼──────────────────────────┐
                  │  Filters: record_type, search, soft-delete │
                  │  ORDER BY COALESCE(con_commissioned_at,    │
                  │           p.created_at) DESC               │
                  │  OFFSET/LIMIT for pagination               │
                  └────────────────┬──────────────────────────┘
                                   │
                  ┌────────────────▼──────────────────────────┐
                  │  PHP post-processing (.map())              │
                  │  - Normalise land use abbreviations        │
                  │  - Resolve file_title → Grantee (CoN mode) │
                  │  - Resolve commissioned_at timestamp chain  │
                  │  - Resolve source/instrument display label  │
                  │  - Detect scenario: standard/merger/subdiv │
                  └────────────────┬──────────────────────────┘
                                   │
                  ┌────────────────▼──────────────────────────┐
                  │  Parallel stats query (card counts)        │
                  │  COUNT DISTINCT prop_id by land_use        │
                  │  + today's count                           │
                  └────────────────┬──────────────────────────┘
                                   │
                  ┌────────────────▼──────────────────────────┐
                  │  view('lands_one_stop_shop.applications')  │
                  │  Passes: records, pagination meta,         │
                  │  cardCounts, states, lgas, districts,      │
                  │  streetNames, recordType                   │
                  └────────────────────────────────────────────┘
```

### 7.2 Edit/Save Path (Write Path)

```
User edits a row → Edit modal (JS populates form from row data)
         │
         ▼
PUT /lands-one-stop-shop/applications/{id}
    Body: { file_name, customer_type, land_use, plot_number,
            tp_number, location, lga, party_1_name, party_2_name,
            pra_id, row_type, registration fields... }
         │
         ▼
OpResettlementApplicationController::updateDetails()
         │
    ┌────┴──────────────────────────────┐
    │  Resolve record identity           │
    │  pra-{N} → PRA direct             │
    │  ic-{N}  → IC direct              │
    │  {N}     → fileNumber + PRA check  │
    │  fallback: pra_id payload pointer  │
    └────┬──────────────────────────────┘
         │
    DB::beginTransaction()
         │
    ┌────▼────────────────────────────────────────────────────┐
    │  1. UPDATE fileNumber                                    │
    │     FileName, plot_no, tp_no, location, lga             │
    │     SOURCE = 'OSS_CHANGE_OF_NAME'                       │
    │                                                          │
    │  2. UPDATE instrument_capture (if OP row, not ToT)      │
    │     Party names, phones, addresses, all reg fields,     │
    │     op_type, op_serial_number, land_use, purpose        │
    │                                                          │
    │  3. UPDATE pra (target row)                             │
    │     OP row:  Grantor = 'KANO STATE GOVT', Grantee = name│
    │     ToT row: Grantor = party_1_name, Grantee = party_2  │
    │     Shared: plot, tp, lga, location, reg particulars    │
    │     system_source stamped = 'OSSOPCHANGEOFNAME'         │
    │                                                          │
    │  4. UPDATE pra siblings (ToT rows sharing prop_id)     │
    │     Sync shared property/reg fields only                │
    │     (party fields NOT synced — ToT has own Save button) │
    │                                                          │
    │  5. UPDATE customers_staging / entities_staging         │
    │     customer_name, property_address, customer_type      │
    └────┬────────────────────────────────────────────────────┘
         │
    DB::commit()
         │
    JSON { success: true }  →  JS reloads DataTable row
```

---

## 8. Frontend Architecture

### 8.1 View Structure

```
resources/views/lands_one_stop_shop/
├── applications.blade.php          ← Main page (this feature)
└── partials/
    ├── application-form-modal.blade.php   ← OSS application CRUD form
    ├── change-of-ownership-modal.blade.php
    ├── verification-modal.blade.php
    ├── acknowledgement-modal.blade.php
    ├── recommendation-modal.blade.php
    ├── bill-modal.blade.php
    └── print-manager-modal.blade.php
```

The main view detects `?type=change-of-name` via a Blade `$request->query('type')` check and adjusts column headers and action menu labels accordingly.

### 8.2 DataTable Configuration

- **Client-side only** (all records passed from PHP as a JSON blob inside `<script>`)
- 16 visible columns: #, Customer Type, Source, MLS File No, File Title, Land Use, TP No, Plot No, LGA, Location, Commissioned By, Time, Date, Date Created, Scenario, Actions
- Native DataTables search/length controls are hidden; replaced with a custom filter bar
- Horizontal scroll enabled for the wide table

### 8.3 JavaScript (`public/js/lands-one-stop-shop/applications.js`)

Key responsibilities:

| Area | Implementation |
|------|---------------|
| DataTable init | Initialises `#op-resettlement-table` with column defs, order, and responsive scroll |
| Search | Debounced input → full page reload with `?search=` appended |
| Filter bar | FC/FEFR radio → page reload with `?record_type=` |
| Edit modal | Populates form fields from row data object; submits PUT via `fetch()` |
| OP Details modal | AJAX GET to `/pra-transactions` → renders transaction timeline |
| Match OP | POST to `/match-op` with selected prop_id pairs |
| Delete | SweetAlert2 confirm → DELETE via `fetch()` → row removal |
| Address builder | Composes full address from sub-fields; "Other" input toggle |

---

## 9. File Commissioning Explained

File Commissioning is the process of assigning a **permanent, structured file number** to an OP record that was initially tracked by a temporary or MLS number.

### States

| State | DB Signal | Display Label | Filter |
|-------|-----------|--------------|--------|
| Commissioned | `mls_file_no.full_file_number IS NOT NULL` | FC | `?record_type=fc` |
| Not commissioned | `mls_file_no.full_file_number IS NULL` | FEFR | `?record_type=fefr` |

### Commissioning Date Resolution (priority order)

```
1. mls_file_no.con_commissioned_at      ← most authoritative
2. fileNumber.commissioning_date
3. fileNumber.created_at (fn_created_at)
4. pra.created_at / tot_agg.latest_tot_created_at
```

### Stamp Written on Save

Every `updateDetails` call stamps `fileNumber.SOURCE = 'OSS_CHANGE_OF_NAME'` so records are identifiable by origin module.

---

## 10. OP Matching Explained

OP Matching links a **Transfer of Title** transaction to its **source Occupancy Permit** via the `prop_id` / `parent_prop_id` relationship.

### Relationship Model

```
instrument_capture (id=50, prop_id=12345)   ← Original OP
        │
        │  prop_id shared
        ▼
pra (prop_id=12345, instrument_type='Occupancy Permit (OP)', system_source='OSSOPCHANGEOFNAME')
pra (prop_id=12345, parent_prop_id=12345, instrument_type='Transfer of Title', system_source='OSSOPCHANGEOFNAME')
                                                         ▲
                                                  This is what the Change of Name view shows
```

### Scenario Classification

After the main query, PHP inspects each row's `op_serial_number` and `plot_no`:

| Scenario | Detection Rule | `scenario_type` |
|----------|---------------|----------------|
| Standard | Neither merger nor subdivision | `standard` |
| Merger | `plot_no` contains `,` `/` `&` or `and` between numbers | `merger` |
| Subdivision | Same `op_serial_number` appears on >1 ToT rows in result | `subdivision` |

`scenario_count` holds the number of sub-plots (merger) or ToT siblings (subdivision).

### Match OP Endpoint (`POST /match-op`)

Accepts a list of `{ prop_id, parent_prop_id }` pairs and writes the `parent_prop_id` linkage into PRA, formally connecting a ToT row to its source OP.

---

## 11. Key Design Decisions & Constraints

| Decision | Reason |
|----------|--------|
| Runtime schema introspection (`INFORMATION_SCHEMA.COLUMNS`) | Handles column presence variance across dev/staging/prod SQL Server instances without migration lock-step |
| `WHERE '0' = '1'` to exclude IC branch in CoN mode | Clean way to keep the UNION query shape identical in both modes without duplicating the JOIN tree |
| Deduplication via `ROW_NUMBER()` rather than `DISTINCT` | Preserves latest record when the same `prop_id + instrument_type` has multiple PRA entries (re-imports, corrections) |
| `system_source = 'OSSOPCHANGEOFNAME'` scope on all reads and writes | Prevents cross-contamination with records from other modules (DR, MLS, SLTR) that share the same `pra` table |
| mls_file_no NOT updated in `updateDetails` | Intentional: updating OP/ToT from the Edit modal must not mutate the commissioning record |
| IC Party 1 always hardcoded to `'KANO STATE GOVERNMENT'` | Business rule: original OP Grantor is always the government |
| Sibling sync on OP save (not on ToT save) | ToT rows have their own Save button; shared property fields propagate only downward from OP edits |

---

## 12. Files Reference

| Component | Path |
|-----------|------|
| Routes | `routes/app3.php` (lines ~218–260) |
| Main Controller | `app/Http/Controllers/LandsOneStopShop/OpResettlementApplicationController.php` |
| CRUD/Workflow Controller | `app/Http/Controllers/LandsOneStopShop/ApplicationController.php` |
| Bill Controller | `app/Http/Controllers/LandsOneStopShop/OpResettlementBillController.php` |
| OSS Model | `app/Models/LandsOneStopShopApplication.php` |
| Main View | `resources/views/lands_one_stop_shop/applications.blade.php` |
| Modal Partials | `resources/views/lands_one_stop_shop/partials/` |
| JavaScript | `public/js/lands-one-stop-shop/applications.js` |

---

## 13. Glossary

| Term | Definition |
|------|-----------|
| **OP** | Occupancy Permit — primary land title document issued by Kano State |
| **ToT** | Transfer of Title — instrument recording ownership change on an existing OP |
| **Change of Name** | The OSS workflow for processing a ToT and updating the file holder's name |
| **FC** | File Commissioned — OP assigned a permanent full file number in `mls_file_no` |
| **FEFR** | File Existing File Record — OP without a permanent file number yet |
| **prop_id** | Unique property identifier shared across all transactions on the same plot |
| **parent_prop_id** | The original OP's `prop_id`, stored on ToT/CoN PRA rows to establish lineage |
| **PRA** | `pra` table — Property Record Account, the multi-instrument land register |
| **IC** | `instrument_capture` table — Deeds Registration capture source |
| **MLS** | Management and Land System — source system for file numbers |
| **mlsFNo** | MLS file number — primary join key between PRA, IC, fileNumber, and mls_file_no |
| **Grantor** | Outgoing owner (Kano State Govt on original OP; previous owner on ToT) |
| **Grantee** | Incoming/new owner — displayed as the file title in Change of Name mode |
| **Merger** | Single ToT referencing multiple source plots (combined into one parcel) |
| **Subdivision** | One OP producing multiple ToT rows (plot split into sub-parcels) |
| **system_source** | PRA column scoping rows to a specific module (`OSSOPCHANGEOFNAME` here) |
