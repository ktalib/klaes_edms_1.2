# OP vs. Transfer of Title (ToT) — Mismatch Root Cause Analysis & Permanent Solutions

**Prepared:** 2026-05-13  
**System:** KLAES EDMS — Land Administration (Laravel 9 / SQL Server)  
**Scope:** OP Resettlement Module — `pra`, `instrument_capture` tables

---

## Executive Summary

Over the past sessions, multiple properties were found showing incorrect **Occupancy Permits (OPs)** linked to **Transfer of Title (ToT)** records. The mismatch caused the wrong grantor (Party 1) to be displayed, incorrect plot addresses, and in some cases the `party_1` field showing as **"Unknown"**. This report identifies all root causes found in the data and codebase, and proposes layered permanent solutions.

---

## Cases Reviewed

| Case | Prop ID | ToT File No | Wrong OP Party | Correct OP Party | Fix Applied |
|---|---|---|---|---|---|
| 1 | `80004477` | `RES-2026-2175` | Tanimu Saidu | Hamisu Abubakar | Detach + Create correct OP |
| 2 | `72841` | `COM-2026-207` | Rabiu Umar | Yusuf Muhd Lawan & Other | Detach + Create + Detach wrong ToT |
| 3 | `80003254` | `RES-2023-6111` | Haruna Mustapha | Unknown (physical file needed) | Full reset — ToT kept only |

---

## Root Cause #1 — `temp_fileno` Collision (Primary Cause)

**What happened:**  
The `temp_fileno` (e.g., `TEMP-39501`, `TEMP-41738`) was used as the shared link between an Occupancy Permit in `instrument_capture` and the corresponding PRA transaction. However, **one `temp_fileno` was assigned to two different physical properties** — either due to a data entry error or a migration defect.

**Why it causes mismatches:**  
The `findExistingOpInSource()` method in `PraRecordService.php` uses `temp_fileno` as a **fallback lookup** (Step 1 in the priority chain) when no explicit `source_op_id` is provided:

```php
// PraRecordService.php:681
if ($tempFileno !== null) {
    $ic = $conn->table('instrument_capture')
        ->where('temp_fileno', $tempFileno)
        ->where('instrument_type', 'Occupancy Permit (OP)')
        ...
        ->orderByDesc('id')
        ->first(); // ← Returns whichever row was inserted LAST
}
```

This means if two IC rows share the same `temp_fileno`, the **most recently inserted one wins** — regardless of whether it belongs to the correct property. The system silently picks the wrong OP.

**Evidence:**  
- `TEMP-39501` was linked to both `C166-HARUNA MUSTAPHA` (Plot 590A) and `ASHIRU A TIJJANI` (Plot 1412).
- `TEMP-41818` was linked to both `Tanimu Saidu` (Plot IND495B) and `Hamisu Abubakar` (Plot 4115).

---

## Root Cause #2 — Missing `source_op_id` on Legacy Records

**What happened:**  
When ToT records were initially generated (pre-Phase 3 upgrade), the `source_op_id` and `source_op_table` columns on the `pra` table were **not populated**. These columns were introduced later as the precise OP-to-ToT lineage anchor.

**Why it causes mismatches:**  
The `praTransactions()` method in `OpResettlementApplicationController` resolves which OP to show alongside a ToT using this fallback logic:

```php
// OpResettlementApplicationController.php:650
if ($propId) {
    $q->orWhere('parent_prop_id', $propId);
}
```

Without `source_op_id`, the system falls back to matching by **shared `prop_id`** or **`parent_prop_id`**. If the prop_id was incorrectly linked (from the `temp_fileno` collision above), the wrong OP is resolved and displayed.

---

## Root Cause #3 — `prop_id` Shared Between Unrelated Records

**What happened:**  
In the legacy data migration (before the `force_fresh_prop_id = true` pattern was enforced), both the OP and the ToT were written with the **same `prop_id`**. This was the old design: "share the prop_id across the chain."

**Why it causes mismatches:**  
The `praTransactions()` method now queries both by `prop_id` AND `parent_prop_id`:

```php
$q->orWhere('prop_id', $propId);
$q->orWhere('parent_prop_id', $propId);
```

If an unrelated OP from a data entry error also carries that `prop_id`, it is pulled into the timeline — resulting in an extra, incorrect OP being displayed.

---

## Root Cause #4 — No Location/Layout Validation at OP-to-ToT Linking

**What happened:**  
When the ToT generation process links a source OP, it does **not validate** that the OP and ToT share the same:
- Plot Number
- District / Layout
- Land Use

**Why it causes mismatches:**  
Plot numbers are recycled across different layouts. Plot `1412` exists in **Western Bypass, Kumbotso** AND **Kudiddifawa Extension, Ungogo**. Without a spatial/layout check, the `temp_fileno` or `op_serial_number` fallback may resolve to the OP in the wrong layout.

---

## Root Cause #5 — `party_1 = 'Unknown'` on ToT Records

**What happened:**  
When a ToT is created via `OSSOPCHANGEOFNAME` and the source OP cannot be resolved (no `source_op_id`, no matching `temp_fileno`), the system defaults the grantor (`party_1` / `Grantor`) to `'Unknown'`.

**Code path:**
```php
// OpResettlementApplicationController.php:2426
$currentHolder = $opRow->Grantee ?? $opRow->party_2 ?? '';

// OpResettlementApplicationController.php:2499
'Grantor' => $allottee, // ← Becomes empty/Unknown if OP resolution fails
```

---

## Permanent Solution Plan

### Tier 1 — Data Constraints (Immediate)

> [!CAUTION]
> Apply these SQL changes to the production `sqlsrv` database after reviewing with the DBA.

#### 1a. Add a Unique Index on `instrument_capture.temp_fileno`

```sql
-- Prevent future temp_fileno collisions at the database level.
-- Run this after deduplicating existing rows first.
CREATE UNIQUE INDEX UQ_instrument_capture_temp_fileno
ON instrument_capture (temp_fileno)
WHERE temp_fileno IS NOT NULL AND is_deleted = 0;
```

#### 1b. Add a `CHECK` Constraint to Block `'Unknown'` Party Names

```sql
ALTER TABLE pra
ADD CONSTRAINT CHK_pra_party1_not_unknown
CHECK (party_1 NOT IN ('Unknown', 'UNKNOWN', 'unknown') OR transaction_type NOT LIKE '%Transfer of Title%');
```

This forces operators to supply a valid grantor before the record can be saved.

---

### Tier 2 — Code Hardening (Short-Term)

#### 2a. Enforce Location Validation in `findExistingOpInSource()`

Before returning a match on `temp_fileno` or `op_serial_number`, add a **sanity cross-check** against the ToT's known plot details:

```php
// In PraRecordService::findExistingOpInSource()
// After finding a candidate $ic row by temp_fileno:

$totPlotNo   = $identifiers['plot_no'] ?? null;
$totDistrict = $identifiers['districtName'] ?? null;

if ($totPlotNo && $ic->plot_number && $ic->plot_number !== $totPlotNo) {
    Log::warning('OP-ToT mismatch: plot_number conflict', [
        'temp_fileno'  => $tempFileno,
        'ic_plot'      => $ic->plot_number,
        'tot_plot'     => $totPlotNo,
    ]);
    // Do NOT return this candidate — fall through to next resolution step.
} else {
    $row = (array) $ic;
    $row['_source_table'] = 'instrument_capture';
    return $row;
}
```

#### 2b. Make `source_op_id` Mandatory for All New ToT Records

In `OpResettlementApplicationController::generateTot()`, add a hard guard to prevent ToT creation without the OP lineage being explicitly resolved:

```php
// Before Step 3: Create Transfer of Title row
if (empty($praPayload['source_op_id'])) {
    return response()->json([
        'success' => false,
        'message' => 'Cannot generate Transfer of Title: source OP (source_op_id) was not resolved. Please select a specific OP manually.',
    ], 422);
}
```

#### 2c. Backfill `source_op_id` on Existing Orphaned ToT Records

Run this SQL to retroactively populate the lineage column for ToT records that share the same `prop_id` as exactly one OP in `pra`:

```sql
UPDATE tot
SET tot.source_op_id  = op.id,
    tot.source_op_table = 'pra'
FROM pra tot
INNER JOIN pra op
    ON op.prop_id = tot.parent_prop_id
   AND op.instrument_type LIKE '%Occupancy Permit%'
   AND (op.is_deleted IS NULL OR op.is_deleted = 0)
WHERE (tot.instrument_type LIKE '%Transfer of Title%'
   OR  tot.transaction_type LIKE '%Transfer of Title%')
  AND tot.source_op_id IS NULL
  AND tot.system_source = 'OSSOPCHANGEOFNAME';
```

---

### Tier 3 — UI / Workflow Improvement (Medium-Term)

#### 3a. Require Manual OP Selection for Ambiguous Cases

When the system detects **multiple candidate OPs** for the same `temp_fileno` or `op_serial_number`, the ToT generation modal should:
1. **Pause** and present a picker showing all candidate OPs with their plot, location, and party details.
2. **Require** the user to explicitly select the correct one.
3. **Store** the user's selection as `source_op_id` before proceeding.

This eliminates the silent `orderByDesc('id')->first()` ambiguity at source.

#### 3b. OP Details Modal — Add Layout Mismatch Warning

In the OP Details modal (currently in use on the ToT Staging Dashboard), add a visual badge when:
- The OP's `districtName` or `location` does not match the ToT's `location`.
- The OP's `plot_number` does not match the ToT's `plot_no`.

```
⚠️ Layout Mismatch: OP is in "Western Bypass, Kumbotso" but ToT is in "Kudiddifawa Extension, Ungogo"
```

#### 3c. Add Staging Table Audit Columns

Add the following columns to the `pra_tot_staging` table to track resolution history:

```sql
ALTER TABLE pra_tot_staging ADD
    resolution_type   NVARCHAR(30)  NULL,   -- 'auto', 'manual', 'reset'
    resolved_note     NVARCHAR(500) NULL,   -- free text note from operator
    physical_file_ref NVARCHAR(100) NULL;   -- file cabinet reference
```

---

### Tier 4 — Monitoring (Long-Term)

#### 4a. Schedule Weekly Mismatch Scan

Add an Artisan command (or SQL Agent Job) that runs the ToT staging detection query weekly and emails a summary:

```php
// app/Console/Commands/DetectOpTotMismatches.php
Artisan::call('op-tot:detect-mismatches --auto-stage');
```

#### 4b. Add a Health Check Endpoint

Expose a read-only health check route for administrators:

```
GET /maintenance/tot/health
```

Returns:
- Total unresolved mismatches
- Count with `party_1 = 'Unknown'`
- Count with layout conflicts
- Count with missing `source_op_id`

---

## Summary of Changes Required

| Priority | Location | Action |
|---|---|---|
| 🔴 Immediate | SQL Server DB | Unique index on `instrument_capture.temp_fileno` |
| 🔴 Immediate | SQL Server DB | Backfill `source_op_id` on orphaned ToT rows |
| 🟠 Short-Term | `PraRecordService` | Add plot/layout cross-check in `findExistingOpInSource()` |
| 🟠 Short-Term | `OpResettlementApplicationController` | Block ToT creation without resolved `source_op_id` |
| 🟡 Medium-Term | ToT Generation Modal (JS/Blade) | Add ambiguous OP picker UI |
| 🟡 Medium-Term | OP Details Modal (JS/Blade) | Add layout mismatch warning badge |
| 🟢 Long-Term | Artisan Console / Scheduler | Weekly mismatch detection scan |
| 🟢 Long-Term | Maintenance routes | `/maintenance/tot/health` endpoint |

---

> [!NOTE]
> All cases resolved manually in this session used the **detach + new prop_id** pattern.
> The correct approach for future data fixes is to always use `pra_tot_staging` → manual review → approve/generate, rather than direct SQL updates.

---

*Document generated from live session review of cases on Prop IDs: 80004477, 72841, 80003254.*
