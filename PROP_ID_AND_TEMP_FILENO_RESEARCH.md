# Research: `prop_id` and Temp File Number Generation

> Read‑only research. **No fix code is included** — this document is a baseline so that a reset of `prop_id` values and a duplicate cleanup of temp file numbers can be planned safely.

---

## 1. Executive summary

KLAES has two cross‑cutting identifiers that thread through the registry, capture, billing and OSS modules:

| Concern | Identifier | Source of truth | Generator |
|---|---|---|---|
| Property continuity (one piece of land, many transactions) | `prop_id` (INT) | `PropID_Master` table (logical master), with copies on every staging table | [`PropertyIdAllocationService::allocateOrRetrievePropId()`](app/Services/PropertyIdAllocationService.php) |
| Working file number when no official MLS/KANGIS number exists yet | `temp_fileno` (`TEMP-#####`) | `temp_fileno_sequence` table (IDENTITY column) | [`InstrumentController::getNextTempFileNo()`](app/Http/Controllers/InstrumentController.php), [`PropertyRecordController::generateTempFileNumber()`](app/Http/Controllers/PropertyRecordController.php), inline `temp_fileno_sequence->insertGetId()` calls in several services |

Both concepts are written into many tables. Resetting either of them is therefore a multi‑table operation with referential consequences (caveats, OSS dashboards, deed registrations, CofO staging, billing, etc.). Existing housekeeping artefacts (`propid:rebuild` Artisan command, `database/sql/reset_rebuild_prop_id_system.sql`) already exist and should be inspected before any new reset is designed.

---

## 2. `prop_id` — deep dive

### 2.1 What it represents
`prop_id` is a stable INTEGER identifier that ties **all transactions on the same physical property/file** together across staging tables, regardless of whether the file number is MLS, KANGIS, NewKANGIS, np_fileno, or a temporary `TEMP-#####`.

### 2.2 Master table

File: [`database/sql/prop_id_master_table.sql`](database/sql/prop_id_master_table.sql)

`dbo.PropID_Master` columns:

- `id` INT IDENTITY PK
- `prop_id` INT — **business key**, `UNIQUE` via `UQ_PropID_Master_prop_id`
- `primary_file_number` NVARCHAR(255) NOT NULL
- `mlsFNo`, `kangisFileNo`, `NewKANGISFileno`, `temp_fileno` (NVARCHAR(255) NULL)
- `source_table`, `source_record_id`, `status`, `notes`
- `created_at`, `updated_at`
- Computed PERSISTED `*_norm` columns (`UPPER(LTRIM(RTRIM(...)))`)
- Filtered unique indexes per `*_norm` column (only when value IS NOT NULL)
- View: `dbo.vw_prop_id_conflicts` (highlights file numbers mapped to multiple `prop_id`s across sources)

### 2.3 Allocation logic

File: [`app/Services/PropertyIdAllocationService.php`](app/Services/PropertyIdAllocationService.php)

Algorithm (per call):

1. Build identifier map from `primary_file_number`, `mlsFNo`, `kangisFileNo`, `NewKANGISFileno`, `temp_fileno`.
2. If `skip_lookup` is false:
   - Look up by `primary_file_number_norm` in `PropID_Master`.
   - Otherwise look up by any `*_norm` column.
   - Otherwise scan legacy tables (`file_history_staging`, `pra`, `pic`, `CofO_staging`) by their non‑null file number columns (`UPPER(...) = ?`).
   - If found, ensure a master row exists and return its `prop_id`.
3. If no lookup hit:
   - Reject when only a temp number is supplied unless `allow_temp_only = true`.
   - Generate the next `prop_id` via `MAX(prop_id) + 1` from `PropID_Master` (locked) — falls back to `MAX` across legacy tables if `PropID_Master` is missing.
4. Insert the master row inside a SQL Server transaction.

Important behaviour notes:
- `MAX + 1` only happens **inside a transaction with `lockForUpdate()`** on `PropID_Master`. Concurrent allocations are serialised through the row lock. If allocations occur **without going through this service** (raw inserts in controllers — see §2.5), gaps and duplicates can appear.
- Filtered unique indexes on `*_norm` columns mean two rows cannot share the same MLS/KANGIS/Temp value, but rows with different normalised values can still legitimately reuse a file number variant if NULLs differ.

### 2.4 Tables that store `prop_id`

Confirmed by code, migrations, and `database/sql/*.sql`:

| Table | Column | Notes |
|---|---|---|
| `PropID_Master` | `prop_id` (UNIQUE) | Master/owner |
| `file_history_staging` | `prop_id` | Updated by `propid:rebuild` and reset script |
| `pra` | `prop_id` | Indexed: `IX_pra_prop_id_instrument_type`, `IX_pra_prop_id_temp_fileno` |
| `pic` | `prop_id` | |
| `CofO_staging` | `prop_id` | |
| `instrument_capture` | `prop_id` | Migration: [`2026_01_30_160000_create_instrument_capture_table.php`](database/migrations/2026_01_30_160000_create_instrument_capture_table.php), comment: *“Linked to PropID_Master or legacy tables”* |
| `deed_registrations` | `prop_id` | Set by `InstrumentRegistrationService` only when column exists |
| `ls_ground_rent_staging` | `prop_id` (string 50) | Migration: [`2026_03_23_124643_create_ls_ground_rent_staging_table.php`](database/migrations/2026_03_23_124643_create_ls_ground_rent_staging_table.php) |
| `legal_search_timeline_arrangements` | `prop_id` (string 20) | Persists timeline ordering per property; see [`LegalSearchService`](app/Services/LegalSearchService.php) |

> Datatype mismatch warning: most copies are `INT`, but `ls_ground_rent_staging` / `legal_search_timeline_arrangements` are NVARCHAR. A reset must preserve cross‑table joinability and watch for implicit casts; `LegalSearchService` already comments that some `prop_id` columns hold dirty data preventing INT casts.

### 2.5 Code paths that **read or write** `prop_id`

Read/write paths that should be considered before any reset:

- Allocation:
  - [`PropertyIdAllocationService`](app/Services/PropertyIdAllocationService.php) — canonical entry point.
  - [`PropIdMasterController`](app/Http/Controllers/PropIdMasterController.php) — admin UI list/search.
- Direct writes (bypass the service in some flows):
  - [`InstrumentCaptureService`](app/Services/InstrumentCaptureService.php) — sets `prop_id` on `instrument_capture` and on `deed_registrations`.
  - [`InstrumentRegistrationService::createInstrumentRegistration()`](app/Services/InstrumentRegistrationService.php) — copies `prop_id` to `deed_registrations` when column exists.
  - [`OpResettlementApplicationController`](app/Http/Controllers/LandsOneStopShop/OpResettlementApplicationController.php) — many places insert PRA rows with `'prop_id' => $opRow->prop_id` to keep OP / Transfer of Title aligned.
  - [`PraRecordService`](app/Services/Pra/PraRecordService.php), [`PraRecordRepository`](app/Services/Pra/Repositories/PraRecordRepository.php), [`PraHistoryRepository`](app/Services/Pra/Repositories/PraHistoryRepository.php) — propagate `prop_id` updates across all rows sharing a `prop_id` and recompute timeline.
  - [`LegalSearchService`](app/Services/LegalSearchService.php) — orphan `match`/`drop` operations set or null `prop_id`.
  - [`PropertyRecordController`](app/Http/Controllers/PropertyRecordController.php) — `compactPropId()` derivation when saving property records, including `'allow_temp_only' => true`.
- Read‑only consumers:
  - [`OpsDashboardController`](app/Http/Controllers/OpsDashboardController.php), [`PropertyCardController`](app/Http/Controllers/PropertyCardController.php), [`PropertyIndexCardController`](app/Http/Controllers/PropertyIndexCardController.php), [`MlsFileNoController`](app/Http/Controllers/MlsFileNoController.php), [`OpResettlementApplicationController`](app/Http/Controllers/LandsOneStopShop/OpResettlementApplicationController.php) — group/aggregate by `prop_id`.
  - [`DuplicateCheckService`](app/Services/DuplicateCheckService.php) — `prop_id` is one of the duplicate signals.

### 2.6 Existing reset/rebuild artefacts

- Artisan: [`propid:rebuild`](app/Console/Commands/RebuildPropIds.php) — clears `prop_id` on `file_history_staging`, `CofO_staging`, `pic` and remaps via `ROW_NUMBER() OVER (ORDER BY mlsFNo)`. Has `--verify` checks. **Does NOT touch `pra`, `instrument_capture`, `deed_registrations`, `caveats`, `PropID_Master`, `ls_ground_rent_staging`, `legal_search_timeline_arrangements`** — incomplete for a full reset.
- SQL: [`database/sql/reset_rebuild_prop_id_system.sql`](database/sql/reset_rebuild_prop_id_system.sql) — full reset that:
  1. Backs up `PropID_Master` to `PropID_Master_backup_<ts>`.
  2. Nulls `prop_id` in `file_history_staging`, `pra`, `pic`, `CofO_staging`, `instrument_capture`, `deed_registrations`.
  3. Builds a normalised set of file numbers and assigns sequential `prop_id` starting at 1.
  4. Re‑inserts into `PropID_Master`, then JOINs back to update each source table.
  5. Skips `caveats`, `ls_ground_rent_staging`, `legal_search_timeline_arrangements`, `consent_applications`, `applications` — these will be left orphaned by this script and need explicit handling.
- SQL: [`database/sql/validate_prop_id_system.sql`](database/sql/validate_prop_id_system.sql) — post‑reset verification, including `vw_prop_id_conflicts` checks and cross‑table `prop_id` sanity counts.

### 2.7 Risks of a reset

1. **Caveats**: caveats join legal records via `prop_id`; if PRA `prop_id`s are renumbered but `caveats.prop_id` is not, `Registration Number` and history pages break.
2. **OSS Dashboards / OP Resettlement**: aggregations such as `ROW_NUMBER() OVER (PARTITION BY prop_id, instrument_type)` and `MIN(temp_fileno) GROUP BY prop_id` rely on stable groupings. After a reset the same prop should still aggregate, but **rows lacking a normalisable file number will lose membership** (they will not match any `PropID_Master` row).
3. **Timeline arrangements**: `legal_search_timeline_arrangements` stores `prop_id` as STRING. A renumber breaks every saved arrangement unless the migration also rewrites these.
4. **`mother_applications` / `subapplications`**: the application flow stores `prop_id` derived from OP records. A reset must propagate to these or the FFR / consent flows will not find their property history.
5. **Indexes**: filtered unique indexes on `PropID_Master.*_norm` mean re‑seeding requires a clean delete first; partial inserts will collide.

---

## 3. Temp file numbers — deep dive

### 3.1 Format and intent
- Pattern: `TEMP-` + 5+ digit zero‑padded integer (e.g. `TEMP-00042`).
- Used by: `pra`, `pic`, `instrument_capture`, `CofO_staging` (column `temp_fileno`), `deed_registrations` (sometimes carried in `fileno`).

### 3.2 Sequence table

Migrations:
- [`2026_02_07_221337_create_temp_fileno_sequence_table.php`](database/migrations/2026_02_07_221337_create_temp_fileno_sequence_table.php) — creates `temp_fileno_sequence(id IDENTITY, created_by, timestamps)` and `DBCC CHECKIDENT RESEED` to the maximum existing TEMP serial across `pra`, `pic`, `instrument_capture` so the next `INSERT` produces `MAX + 1`.
- [`2026_02_19_183033_add_is_used_to_temp_fileno_sequence.php`](database/migrations/2026_02_19_183033_add_is_used_to_temp_fileno_sequence.php) — adds `is_used BIT` (default 0) column to mark whether the allocated number was actually consumed.

### 3.3 Generators

All generators format the value as `'TEMP-' . str_pad($id, 5, '0', STR_PAD_LEFT)`.

Authoritative generators (use the sequence table):
- [`InstrumentController::getNextTempFileNo()`](app/Http/Controllers/InstrumentController.php) — the AJAX endpoint `/get-next-temp-fileno` consumed by the OP capture form. **Always inserts a new row with `is_used = 1`** to prevent duplicates.
- [`PropertyRecordController::generateTempFileNumber()`](app/Http/Controllers/PropertyRecordController.php) — same pattern for the property record save flow.
- [`InstrumentCaptureService`](app/Services/InstrumentCaptureService.php) lines 100–120 — server‑side fallback when the AJAX value never arrived.
- [`OpResettlementApplicationController`](app/Http/Controllers/LandsOneStopShop/OpResettlementApplicationController.php) (Transfer of Title flow) — `temp_fileno_sequence->insertGetId()` then writes the same `TEMP-XXXXX` to PRA OP row and the new TOT PRA row so they share `prop_id` + `temp_fileno`.

Legacy fallback (problematic — known source of duplicates):
- `InstrumentController::getNextTempFileNoLegacy()` and `PropertyRecordController::generateTempFileNumberLegacy()` walk `pra`, `pic`, `instrument_capture` for `MAX(numeric_part)` and add 1. Used only when the sequence table is unavailable, but **two concurrent callers will produce the same value**.

### 3.4 Other places that emit temp‑style strings (NOT routed through the sequence)

- `MlsFileNoController` sometimes writes the **full mlsFNo** into `temp_fileno` to satisfy NOT‑NULL flows; this can produce values that do not look like `TEMP-#####` but still occupy `temp_fileno`.

### 3.5 Writers of `temp_fileno` (possible duplicate sources)

Inserts/updates that put a value into `temp_fileno` are spread across:

- `pra`: `InstrumentCaptureService`, `PraRecordService`, `OpResettlementApplicationController`, `PropertyRecordController`, `Api\Pra\PraRecordController`.
- `pic`: `InstrumentCaptureService::syncPicRecord()`, `OpResettlementApplicationController` (TOT flow), `MlsFileNoController` (sometimes nulls it).
- `instrument_capture`: `InstrumentCaptureService` create/update.
- `CofO_staging`: assigned via PRA propagation in `PraRecordRepository::updateSharedFieldsForPropId()`.
- `deed_registrations`: cleared/reset by `MlsFileNoController` after promotion to a real number.
- `PropID_Master`: alias column; filtered unique on `temp_fileno_norm`.

Because multiple writers exist and only the **sequence‑based generators** are duplicate‑safe, the legacy fallback path, the `TEMP-time()` Scanning path, and any historical bulk insert are the most likely sources of duplicate `temp_fileno`.

### 3.6 Where duplicates manifest

- `pra.temp_fileno`: duplicates expected because the same `temp_fileno` is **intentionally shared** between an OP row and its Transfer of Title row (`OpResettlementApplicationController` writes the same value into both rows for the same `prop_id`). Any duplicate scan must **partition by `prop_id` first** and only flag entries whose `prop_id` differs.
- `pic.temp_fileno`: similarly shared with PRA for the same instrument.
- `instrument_capture.temp_fileno`: should be unique per active capture; duplicates are real bugs (a guard in `InstrumentCaptureService` lines 46–66 already rejects re‑submits but historical rows pre‑date that).
- `deed_registrations.fileno` containing `TEMP-...`: duplicates indicate a registration was issued before the temp number was promoted to an MLS number.

### 3.7 Risks of resetting / cleaning duplicates

1. **Cross‑table linkage**: `pra`, `pic`, `instrument_capture` are joined on `temp_fileno` for OP captures that have no MLS yet. Reassigning `temp_fileno` on one row without updating its siblings will orphan them.
2. **Sequence drift**: `temp_fileno_sequence` is an IDENTITY column. After cleanup, run `DBCC CHECKIDENT('temp_fileno_sequence', RESEED, <max>)` matching the migration’s logic, otherwise the next allocation could collide with an already‑used number.
3. **Filtered unique index** on `PropID_Master.temp_fileno_norm` will block any consolidation that tries to leave two master rows with the same temp value.
4. **Caveats and OSS** keep `temp_fileno` in their search/display layer (`OpsDashboardController`, `PropertyCardController`); if a reset rewrites values, dashboards still cache via `prop_id` so they will continue to work, but exported reports printed earlier will no longer reconcile.

---

## 4. Inventory of fields by table (quick reference)

### 4.1 `prop_id` columns

| Table | Type | Source |
|---|---|---|
| `PropID_Master.prop_id` | INT (UNIQUE) | `prop_id_master_table.sql` |
| `file_history_staging.prop_id` | INT | reset/rebuild script |
| `pra.prop_id` | INT | reset/rebuild script |
| `pic.prop_id` | INT | reset/rebuild script |
| `CofO_staging.prop_id` | INT | reset/rebuild script |
| `instrument_capture.prop_id` | unsignedBigInteger | migration `2026_01_30_160000` |
| `deed_registrations.prop_id` | conditional column | `InstrumentRegistrationService` |
| `ls_ground_rent_staging.prop_id` | NVARCHAR(50) | migration `2026_03_23_124643` |
| `legal_search_timeline_arrangements.prop_id` | NVARCHAR(20) | migration `2026_03_21_000002` |

### 4.2 Temp‑style file number columns

| Table | Column | Format expected |
|---|---|---|
| `temp_fileno_sequence` | `id` IDENTITY, `is_used` BIT, `created_by`, timestamps | sequence backbone |
| `pra` | `temp_fileno` NVARCHAR(255) | `TEMP-#####` |
| `pic` | `temp_fileno` NVARCHAR(255) | `TEMP-#####` (mirrors PRA) |
| `instrument_capture` | `temp_fileno` NVARCHAR(255) | `TEMP-#####` |
| `CofO_staging` | `temp_fileno` NVARCHAR(255) | `TEMP-#####` |
| `deed_registrations` | `fileno` (sometimes holds `TEMP-...`), `temp_fileno` if column exists | `TEMP-#####` |
| `PropID_Master` | `temp_fileno` (filtered‑unique on `temp_fileno_norm`) | `TEMP-#####` |

---

## 5. Considerations before designing the actual reset

These are the questions that the eventual fix design must answer; the data already exists in the codebase to answer them.

1. **Scope of reset for `prop_id`**: should it be a *full* reset (all tables, including timeline arrangements and ground rent staging) or a partial reset (only legacy staging tables, like `propid:rebuild` does)?  
   - Existing artefacts cover only a subset. Going further requires extending `reset_rebuild_prop_id_system.sql` to cover `ls_ground_rent_staging` and `legal_search_timeline_arrangements`.
2. **Data type alignment**: most `prop_id` columns are INT/BIGINT, but `ls_ground_rent_staging.prop_id` and `legal_search_timeline_arrangements.prop_id` are NVARCHAR. The reset must cast consistently and avoid overflow / leading‑zero issues.
3. **Matching strategy**: the existing rebuild matches by normalised primary file number. Records whose only identifier is `temp_fileno` will not match any `PropID_Master.primary_file_number_norm` (since that column requires an official number). Decide whether `allow_temp_only = true` should be applied during reset, mirroring `PropertyIdAllocationService`.
4. **Backfill of derived tables**: `legal_search_timeline_arrangements` should be cleared (or its `prop_id` rewritten) because saved orderings reference soon‑to‑be‑gone integers. `ls_ground_rent_staging.prop_id` likewise needs to be remapped or cleared.
5. **Backups**: `reset_rebuild_prop_id_system.sql` already auto‑backs up `PropID_Master`. Add backups of every table whose `prop_id` will be touched.
6. **For temp file number duplicates**:
   - Decide what counts as a duplicate. Same `temp_fileno` across `pra`/`pic`/`instrument_capture` for the **same `prop_id`** is by design — those rows must stay.
   - Genuine duplicates are `temp_fileno` shared by **different `prop_id`s** (cross‑contamination from the legacy `MAX+1` fallback or the `TEMP-time()` scanning path).
   - For each duplicate cluster, the canonical row is typically the one already linked to `PropID_Master` and to a real `instrument_capture`/`pra` record; the conflicting siblings should be re‑allocated a fresh value via `temp_fileno_sequence->insertGetId()` (this is what the production code does today in `InstrumentCaptureService` and `OpResettlementApplicationController`).
   - After cleanup, run `DBCC CHECKIDENT('temp_fileno_sequence', RESEED, <max>)` so the IDENTITY counter is past the highest value still in use.

---

## 6. Useful diagnostic queries (read‑only, reference)

These are queries the system already implies are safe to run; they are listed here for the planning phase only — not as part of the fix.

```sql
-- prop_id cross-table conflicts (built-in view)
SELECT * FROM dbo.vw_prop_id_conflicts;

-- prop_id values that exist in source tables but not in PropID_Master
SELECT DISTINCT pra.prop_id
FROM pra
LEFT JOIN dbo.PropID_Master m ON m.prop_id = pra.prop_id
WHERE pra.prop_id IS NOT NULL AND m.prop_id IS NULL;

-- temp_fileno duplicates within instrument_capture (genuine bugs)
SELECT temp_fileno, COUNT(*) AS dup_count
FROM instrument_capture
WHERE temp_fileno LIKE 'TEMP-%'
  AND (is_deleted = 0 OR is_deleted IS NULL)
GROUP BY temp_fileno
HAVING COUNT(*) > 1;

-- temp_fileno duplicates across DIFFERENT prop_id in pra (cross-contamination)
SELECT temp_fileno, COUNT(DISTINCT prop_id) AS distinct_props
FROM pra
WHERE temp_fileno LIKE 'TEMP-%'
GROUP BY temp_fileno
HAVING COUNT(DISTINCT prop_id) > 1;

-- Sequence high-water mark vs actual usage
SELECT
  (SELECT IDENT_CURRENT('temp_fileno_sequence')) AS sequence_current,
  (SELECT MAX(TRY_CONVERT(INT, REPLACE(temp_fileno,'TEMP-',''))) FROM pra WHERE temp_fileno LIKE 'TEMP-%') AS max_in_pra,
  (SELECT MAX(TRY_CONVERT(INT, REPLACE(temp_fileno,'TEMP-',''))) FROM pic WHERE temp_fileno LIKE 'TEMP-%') AS max_in_pic,
  (SELECT MAX(TRY_CONVERT(INT, REPLACE(temp_fileno,'TEMP-',''))) FROM instrument_capture WHERE temp_fileno LIKE 'TEMP-%') AS max_in_ic;
```

---

## 7. References (source files cited)

- [app/Services/PropertyIdAllocationService.php](app/Services/PropertyIdAllocationService.php)
- [app/Services/InstrumentCaptureService.php](app/Services/InstrumentCaptureService.php)
- [app/Services/InstrumentRegistrationService.php](app/Services/InstrumentRegistrationService.php)
- [app/Services/Pra/PraRecordService.php](app/Services/Pra/PraRecordService.php)
- [app/Services/Pra/Repositories/PraRecordRepository.php](app/Services/Pra/Repositories/PraRecordRepository.php)
- [app/Services/Pra/Repositories/PraHistoryRepository.php](app/Services/Pra/Repositories/PraHistoryRepository.php)
- [app/Services/LegalSearchService.php](app/Services/LegalSearchService.php)
- [app/Services/DuplicateCheckService.php](app/Services/DuplicateCheckService.php)
- [app/Http/Controllers/InstrumentController.php](app/Http/Controllers/InstrumentController.php)
- [app/Http/Controllers/PropertyRecordController.php](app/Http/Controllers/PropertyRecordController.php)
- [app/Http/Controllers/LandsOneStopShop/OpResettlementApplicationController.php](app/Http/Controllers/LandsOneStopShop/OpResettlementApplicationController.php)
- [app/Http/Controllers/PropIdMasterController.php](app/Http/Controllers/PropIdMasterController.php)
- [app/Http/Controllers/MlsFileNoController.php](app/Http/Controllers/MlsFileNoController.php)
- [app/Http/Controllers/OpsDashboardController.php](app/Http/Controllers/OpsDashboardController.php)
- [app/Console/Commands/RebuildPropIds.php](app/Console/Commands/RebuildPropIds.php)
- [routes/console.php](routes/console.php) (Artisan command `backfill:temp-op-propid`)
- [database/sql/prop_id_master_table.sql](database/sql/prop_id_master_table.sql)
- [database/sql/reset_rebuild_prop_id_system.sql](database/sql/reset_rebuild_prop_id_system.sql)
- [database/sql/validate_prop_id_system.sql](database/sql/validate_prop_id_system.sql)
- [database/migrations/2026_02_07_221337_create_temp_fileno_sequence_table.php](database/migrations/2026_02_07_221337_create_temp_fileno_sequence_table.php)
- [database/migrations/2026_02_19_183033_add_is_used_to_temp_fileno_sequence.php](database/migrations/2026_02_19_183033_add_is_used_to_temp_fileno_sequence.php)
- [database/migrations/2026_01_30_160000_create_instrument_capture_table.php](database/migrations/2026_01_30_160000_create_instrument_capture_table.php)
- [database/migrations/2026_03_23_124643_create_ls_ground_rent_staging_table.php](database/migrations/2026_03_23_124643_create_ls_ground_rent_staging_table.php)
- [database/migrations/2026_03_21_000002_create_legal_search_timeline_arrangements_table.php](database/migrations/2026_03_21_000002_create_legal_search_timeline_arrangements_table.php)
- [database/migrations/2026_04_09_100000_add_performance_indexes_oss_op_pages.php](database/migrations/2026_04_09_100000_add_performance_indexes_oss_op_pages.php)

