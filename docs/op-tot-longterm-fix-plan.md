# OP–TOT Mismatch: Long-Term Fix Implementation Plan

**Status:** Planning  
**Depends on:** `docs/op-tot-mismatch-rootcause.md`  
**Target:** Prevent wrong OP being permanently linked to a TOT at the data and code level

---

## Overview of Changes

| # | Fix | Type | Risk | Effort |
|---|---|---|---|---|
| F1 | Ownership guard in `saveFfrChangeOfName` | Code | Low | Small |
| F2 | Warning log when multiple OP candidates found | Code | None | Trivial |
| F3 | Populate lineage columns in `directOpCapture` | Code | Low | Small |
| F4 | Remove dangerous mlsFNo fallback in `directOpCapture` | Code | Medium | Small |
| F5 | Restrict FFR file-picker to valid source OPs | Code + UI | Low | Medium |
| F6 | Add `instrument_capture_id` column to `pra` for hard OP→TOT link | Migration + Code | Low | Medium |
| F7 | DB uniqueness constraint `(source_op_table, source_op_id)` on `pra` | Migration | Medium | Small |

**Do these in order.** F1–F4 fix the code paths. F5 prevents bad input. F6–F7 add DB-level enforcement.

---

## F1 — Ownership Guard in `saveFfrChangeOfName`

**File:** `app/Http/Controllers/LandsOneStopShop/ApplicationController.php`  
**After line 1504** (after `resolveMotherOpRecord` is called)

### What to add

```php
// After: $opRecord = $this->resolveMotherOpRecord($sourceHistory);

if ($opRecord) {
    $opOwner = strtoupper(trim((string) (
        data_get($opRecord, 'party_2')
        ?: data_get($opRecord, 'parties.party_2')
        ?: ''
    )));
    $expectedParty1 = strtoupper(trim((string) ($validated['party_1'] ?? '')));

    if ($expectedParty1 !== '' && $opOwner !== '' && $opOwner !== $expectedParty1) {
        Log::warning('FFR save blocked: source OP owner mismatch', [
            'source_file_no'  => $sourceFileNo,
            'op_owner'        => $opOwner,
            'expected_party1' => $expectedParty1,
            'pra_id'          => data_get($opRecord, 'id'),
        ]);
        return response()->json([
            'success' => false,
            'message' => "Source file owner ({$opOwner}) does not match the expected original holder ({$expectedParty1}). Please select the correct source file.",
        ], 422);
    }
}
```

### Why this works
- `party_2` of the source OP = the allottee (e.g. "B80 SRTUCTURE")
- `party_1` submitted by the user = the original holder they typed for the CoN
- If they don't match, the user picked the wrong source file → reject before any DB write

### Edge cases to handle
- `party_1` may be blank if the UI allows omitting it — guard only activates when both sides are non-empty
- Case-insensitive compare (both uppercased)
- Fuzzy match may be needed if name spellings differ slightly — start with exact, add Levenshtein later if needed

---

## F2 — Log Warning When Multiple OP Candidates Found

**File:** `app/Http/Controllers/LandsOneStopShop/ApplicationController.php`  
**Method:** `resolveMotherOpRecord` (line 2807)

### What to add

```php
// After: $candidates = ... ->all();
// Before: if (empty($candidates)) {

if (count($candidates) > 1) {
    Log::warning('resolveMotherOpRecord: ambiguous — multiple OP candidates, picking oldest', [
        'count'        => count($candidates),
        'prop_ids'     => array_column($candidates, 'prop_id'),
        'temp_filenos' => array_column($candidates, 'temp_fileno'),
        'pra_ids'      => array_column($candidates, 'id'),
    ]);
}
```

### Why this matters
Surfacing this in logs means operations staff can catch mismatches before data is corrupted. Pair with a Slack/email alert on `warning` level for this specific message if monitoring is available.

---

## F3 — Populate Lineage Columns in `directOpCapture`

**File:** `app/Http/Controllers/ApplicationController.php`  
**Method:** `directOpCapture` (around line 2613)

### Context
`saveFfrChangeOfName` already sets `source_op_table`, `source_op_id`, `parent_prop_id` on the TOT row. `directOpCapture` creates the same OP→TOT pair but never sets these columns.

### What to add

When building the TOT payload in `directOpCapture`, after the OP is created and `$opPropId` / `$totSourceId` are known:

```php
// Add to TOT payload (pra row being created for the Transfer of Title):
'source_op_table' => 'instrument_capture',   // OP lives in instrument_capture
'source_op_id'    => $totSourceId,            // instrument_capture.id of the OP
'parent_prop_id'  => $opPropId,               // prop_id of the OP
```

`$totSourceId` is already computed a few lines earlier (it's the `instrument_capture.id` of the newly created OP). This just needs to be passed into the TOT payload.

### Impact
- Makes every TOT row traceable back to its source OP row regardless of which code path created it
- Enables reliable audit queries without relying on prop_id coincidence

---

## F4 — Remove Dangerous mlsFNo Fallback in `directOpCapture`

**File:** `app/Http/Controllers/ApplicationController.php`  
**Lines:** ~1851–1858 (Fallback 2)

### Current dangerous code

```php
// Fallback 2: query latest by file number (temp or source)
if (empty($opPropId)) {
    $opPropId = DB::connection('sqlsrv')
        ->table('pra')
        ->where('mlsFNo', $opFileNo)   // ← no uniqueness, returns arbitrary row
        ->orderByDesc('id')
        ->value('prop_id');
}
```

### Problem
`orderByDesc('id')` returns the most-recently-inserted pra row matching `mlsFNo`. In a concurrent environment (multiple users processing different files), this can return the wrong prop_id.

### Replacement

```php
// Fallback 2: fail loudly rather than silently use a wrong prop_id
if (empty($opPropId)) {
    Log::error('directOpCapture: could not resolve OP prop_id — aborting TOT creation', [
        'op_file_no' => $opFileNo,
        'op_row_id'  => $opRowId ?? null,
        'user_id'    => Auth::id(),
    ]);
    DB::connection('sqlsrv')->rollBack();
    return response()->json([
        'success' => false,
        'message' => 'Could not link OP record. Please try again or contact support.',
    ], 500);
}
```

### Why
A failed transaction is recoverable. A silently wrong prop_id creates the exact data corruption this plan is fixing.

---

## F5 — Restrict FFR File-Picker to Valid Source OPs

### Context
There is currently no server-side endpoint that filters which OPs can be selected as a source for an FFR Change of Name. The user can type or pick any file number.

### New endpoint

**File:** `app/Http/Controllers/LandsOneStopShop/ApplicationController.php`  
**Route:** `GET /lands-one-stop-shop/applications/ffr-source-ops`

```php
public function ffrSourceOps(Request $request): JsonResponse
{
    $search = trim((string) $request->input('q', ''));

    $query = DB::connection('sqlsrv')
        ->table('instrument_capture as ic')
        ->select([
            'ic.id',
            'ic.temp_fileno',
            'ic.mlsFNo',
            'ic.op_type',
            'ic.party_2_name as allottee',
            'ic.land_use',
            'ic.plot_number',
        ])
        ->whereRaw("ic.instrument_type LIKE '%Occupancy Permit%'")
        ->where(function ($q) use ($search) {
            if ($search !== '') {
                $q->where('ic.temp_fileno', 'LIKE', "%{$search}%")
                  ->orWhere('ic.mlsFNo',    'LIKE', "%{$search}%")
                  ->orWhere('ic.party_2_name', 'LIKE', "%{$search}%");
            }
        })
        ->orderByDesc('ic.id')
        ->limit(50)
        ->get();

    return response()->json(['success' => true, 'data' => $query]);
}
```

**Route registration** (`routes/app3.php` inside the lands-one-stop-shop group):
```php
Route::get('/applications/ffr-source-ops', [ApplicationController::class, 'ffrSourceOps']);
```

### UI change
Replace the free-text source file input in the FFR CoN form with a **searchable dropdown** that calls this endpoint. Each option shows: `TEMP-XXXXX — [Allottee Name] — [Plot/Land Use]`.

This way the user sees exactly who the OP belongs to before selecting it, making accidental selection of the wrong file obvious.

---

## F6 — Add `instrument_capture_id` to `pra` for Hard OP→TOT Link

### Migration

**File:** `database/migrations/YYYY_MM_DD_add_instrument_capture_id_to_pra.php`

```php
public function up(): void
{
    // SQL Server uses sp_executesql for conditional column add
    DB::connection('sqlsrv')->statement("
        IF NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'pra' AND COLUMN_NAME = 'instrument_capture_id'
        )
        BEGIN
            ALTER TABLE pra ADD instrument_capture_id BIGINT NULL;
            CREATE INDEX IX_pra_instrument_capture_id ON pra (instrument_capture_id)
                WHERE instrument_capture_id IS NOT NULL;
        END
    ");
}

public function down(): void
{
    DB::connection('sqlsrv')->statement("
        IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_pra_instrument_capture_id')
            DROP INDEX IX_pra_instrument_capture_id ON pra;
        IF EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'pra' AND COLUMN_NAME = 'instrument_capture_id'
        )
            ALTER TABLE pra DROP COLUMN instrument_capture_id;
    ");
}
```

### Code changes

**In `directOpCapture`** — after the OP is inserted into `instrument_capture` and `$totSourceId` is known:
```php
// Add to TOT pra payload:
'instrument_capture_id' => $totSourceId,
```

**In `saveFfrChangeOfName`** — if the resolved OP comes from `instrument_capture`:
```php
// After resolveMotherOpRecord, if source_op_table = instrument_capture:
if (data_get($opRecord, 'source') === 'instrument_capture' || /* from IC */) {
    $praPayload['instrument_capture_id'] = $opRecordId;
}
```

**In `PraRecordService::createRecord`** — pass through `instrument_capture_id` if present:
```php
// In prepareRecordPayload or gatherIdentifierSet:
if (array_key_exists('instrument_capture_id', $input)) {
    $payload['instrument_capture_id'] = $input['instrument_capture_id'] ?? null;
}
```

### Backfill existing data

After the migration runs, backfill `instrument_capture_id` for existing rows where `source_op_table = 'instrument_capture'` and `source_op_id` is set:

```sql
UPDATE pra
SET instrument_capture_id = source_op_id
WHERE source_op_table = 'instrument_capture'
  AND source_op_id IS NOT NULL
  AND instrument_capture_id IS NULL;
```

Also backfill for the two manually fixed records:
```sql
UPDATE pra SET instrument_capture_id = 17667 WHERE id = 149417;  -- RES-2026-2220
UPDATE pra SET instrument_capture_id = 17668 WHERE id = 149403;  -- RES-2026-2219
```

---

## F7 — Uniqueness Constraint on `(source_op_table, source_op_id)` in `pra`

### Intent
Prevent two different TOT rows from claiming the same OP as their source. One OP → at most one TOT.

### Migration

**File:** `database/migrations/YYYY_MM_DD_add_unique_source_op_to_pra.php`

```php
public function up(): void
{
    // First, check for and clean duplicates
    // (only rows where source_op_table + source_op_id are both non-NULL)
    DB::connection('sqlsrv')->statement("
        IF NOT EXISTS (
            SELECT 1 FROM sys.indexes
            WHERE name = 'uq_pra_source_op'
        )
        BEGIN
            CREATE UNIQUE INDEX uq_pra_source_op ON pra (source_op_table, source_op_id)
            WHERE source_op_table IS NOT NULL AND source_op_id IS NOT NULL;
        END
    ");
}

public function down(): void
{
    DB::connection('sqlsrv')->statement("
        IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'uq_pra_source_op')
            DROP INDEX uq_pra_source_op ON pra;
    ");
}
```

> **Note:** This is a filtered unique index (SQL Server supports `WHERE` clause on unique indexes). It only enforces uniqueness on non-NULL pairs, so rows with no lineage data are unaffected.

### Before running
Verify no existing duplicates:
```sql
SELECT source_op_table, source_op_id, COUNT(*) as cnt
FROM pra
WHERE source_op_table IS NOT NULL AND source_op_id IS NOT NULL
GROUP BY source_op_table, source_op_id
HAVING COUNT(*) > 1;
```
If any rows are returned, resolve them before adding the constraint.

---

## Implementation Order & Checklist

```
Phase 1 — Code fixes (no migration needed, deploy immediately)
  [ ] F1 — Ownership guard in saveFfrChangeOfName
  [ ] F2 — Warning log in resolveMotherOpRecord
  [ ] F3 — Lineage columns in directOpCapture TOT payload
  [ ] F4 — Remove dangerous mlsFNo fallback

Phase 2 — UI + endpoint (requires front-end change)
  [ ] F5 — ffrSourceOps endpoint
  [ ] F5 — Replace source file free-text input with filtered dropdown

Phase 3 — DB migrations (run on production during low-traffic window)
  [ ] F6 — Add instrument_capture_id column to pra
  [ ] F6 — Backfill instrument_capture_id from source_op_id where applicable
  [ ] F6 — Backfill two manually fixed records (ids 149417, 149403)
  [ ] F7 — Verify no duplicate (source_op_table, source_op_id) pairs
  [ ] F7 — Add filtered unique index uq_pra_source_op
```

---

## Audit Query (Run Before Phase 3)

Find any other existing mismatches in production before adding the constraint:

```sql
SELECT
    p.id         AS pra_id,
    p.mlsFNo,
    p.prop_id,
    p.party_1    AS tot_party1,
    p.party_2    AS tot_party2,
    ic.id        AS ic_id,
    ic.temp_fileno,
    ic.party_1_name AS op_party1,
    ic.party_2_name AS op_party2
FROM pra p
JOIN instrument_capture ic
    ON TRY_CAST(ic.prop_id AS bigint) = TRY_CAST(p.prop_id AS bigint)
WHERE p.instrument_type LIKE '%Transfer%'
  AND ic.instrument_type  LIKE '%Occupancy Permit%'
  AND UPPER(LTRIM(RTRIM(ic.party_2_name))) <> UPPER(LTRIM(RTRIM(p.party_1)))
ORDER BY p.id DESC;
```

Any rows returned need to be fixed using the `_fix_op_detach.php` pattern before Phase 3 migrations run.
