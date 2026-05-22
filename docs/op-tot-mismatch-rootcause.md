# OP–TOT Mismatch: Root Cause Analysis & Fix Proposal

**Date:** 2026-05-21  
**Affected records (confirmed):**
| prop_id | Wrong OP (instrument_capture) | TOT (pra) | Wrong party |
|---|---|---|---|
| 69579486 | id=15614, TEMP-50177, B80 Srtucture | id=149417, RES-2026-2220 | Aisha Abubakar Salisu |
| 69574082 | id=5162, TEMP-50114, Gali Rabiu | id=149403, RES-2026-2219 | Aisha Abubakar Salisu |

---

## 1. Data Model (Quick Reference)

| Table | Role | Key columns |
|---|---|---|
| `instrument_capture` | Stores OP (Occupancy Permit) records | `prop_id`, `temp_fileno`, `mlsFNo`, `op_type`, `party_1_name`, `party_2_name` |
| `pra` | Stores TOT (Transfer of Title) records | `prop_id`, `temp_fileno`, `mlsFNo`, `instrument_type`, `party_1`, `party_2` |
| `PropID_Master` | Maps file numbers → prop_ids | `prop_id`, `primary_file_number`, `temp_fileno` |

Records sharing the same `prop_id` are displayed together in the OP Details modal as "1 property with N Transactions".

**Correct OP structure:**
- `party_1_name` = Kano State Government
- `party_2_name` = Original allottee (e.g. B80 Srtucture)

**Correct TOT structure:**
- `party_1` = Original allottee (the OP holder, now transferring)
- `party_2` = New holder (the applicant)

---

## 2. How The Mismatch Happens — Step-by-Step

### Normal FFR Change-of-Name flow (`saveFfrChangeOfName`)

```
LandsOneStopShop/ApplicationController.php : 1465
```

1. User selects a **source file** (the existing OP to be transferred).
2. Line 1491 — `findAllByFileNumber($sourceFileNo)` searches `pra` using **OR across 6 columns** (`mlsFNo`, `kangisFileNo`, `NewKANGISFileno`, `fileno`, `np_fileno`, `temp_fileno`).
3. Line 1504 — `resolveMotherOpRecord($sourceHistory)` picks the best OP from those results.
4. Line 1537-1539 — `prop_id` is taken from that OP record.
5. A new TOT row is inserted into `pra` with that `prop_id` and the same `temp_fileno` inherited from the source OP.

**The result:** OP and TOT share the same `prop_id` → they appear as "1 property" in the modal.

---

## 3. Root Causes

### Root Cause A — No cross-table validation of source file ownership (PRIMARY)

**File:** `app/Http/Controllers/LandsOneStopShop/ApplicationController.php:1465–1539`

`saveFfrChangeOfName` accepts any `source_file_no` and uses whatever OP it finds.  
There is **no check that the source OP's owner (party_2) matches the applicant (party_1) of the change-of-name being processed**.

Consequence: if a user selects the wrong source file — accidentally or because the file-picker does not filter by applicant — the new TOT gets linked to the wrong OP's `prop_id`.

For the two confirmed cases, the source files TEMP-50177 and TEMP-50114 belong to B80 Srtucture and Gali Rabiu respectively, but were used as sources for Aisha Abubakar Salisu's CoN applications.

---

### Root Cause B — `resolveMotherOpRecord` picks by timestamp only, not by applicant

**File:** `app/Http/Controllers/LandsOneStopShop/ApplicationController.php:2807–2863`

```php
usort($candidates, function (array $left, array $right): int {
    return $this->rowTimestampForMotherSelection($left)
        <=> $this->rowTimestampForMotherSelection($right);
});
return $candidates[0] ?? null;   // OLDEST match wins
```

When `findAllByFileNumber` returns multiple OPs (possible when temp_fileno is shared across records, or PropID_Master maps multiple file numbers), the method silently picks the **oldest** one with no verification of applicant name or ownership. There is no warning logged when more than one candidate is found.

---

### Root Cause C — `findAllByFileNumber` searches 6 columns with broad OR logic

**File:** `app/Services/Pra/Repositories/PraRecordRepository.php:72–128`

```php
// fileNumberColumns() returns: mlsFNo, kangisFileNo, NewKANGISFileno, fileno, np_fileno, temp_fileno
foreach ($fileColumns as $column) {
    $builder->orWhereRaw('UPPER(' . $grammar->wrap($column) . ') = ?', [$normalized]);
}
```

A single search for `TEMP-50177` returns **every pra row where any of those 6 columns equals TEMP-50177**. Because both OP and TOT rows inherit the same `temp_fileno`, a lookup for one file number can surface records belonging to completely different properties if they share any file-number token.

Additionally the repository searches `PropID_Master` (lines 101–124) and pulls in ALL pra rows for every matching `prop_id`. If PropID_Master maps a temp_fileno to a prop_id from a different property, cross-contamination occurs.

---

### Root Cause D — OP and TOT share `temp_fileno`; PropID lookup reuses the OP's prop_id

**File:** `app/Http/Controllers/ApplicationController.php` (directOpCapture, lines ~2613–2631)  
**File:** `app/Services/PropertyIdAllocationService.php:52–99` (allocateOrRetrievePropId)

In `directOpCapture`, both the OP row (in `pra`) and the TOT row share the same `temp_fileno`. When a subsequent lookup calls `allocateOrRetrievePropId` with that `temp_fileno`, `PropID_Master` returns the existing `prop_id` — intentionally reusing it. This is correct for the SAME property.

It becomes a bug when the `temp_fileno` used to create the TOT belongs to a **different** property's OP. The allocator dutifully returns the existing prop_id (the wrong one) with no error.

---

### Root Cause E — No uniqueness constraint on prop_id in pra

The `pra` table has no `UNIQUE` constraint on `prop_id`. Multiple distinct properties can end up sharing the same `prop_id` with no DB-level rejection. The only enforcement is application-level, which the above bugs bypass.

---

## 4. Sequence of Events (Reconstructed for Confirmed Cases)

```
[Step 1] OP Resettlement created for B80 Srtucture
         instrument_capture: id=15614, temp_fileno=TEMP-50177, prop_id=69579486
         PropID_Master: TEMP-50177 → 69579486

[Step 2] CoN application for Aisha Abubakar Salisu / RES-2026-2220 initiated

[Step 3] saveFfrChangeOfName called with source_file_no = TEMP-50177
         (user selected B80's file as the source, incorrectly)

[Step 4] findAllByFileNumber('TEMP-50177') hits pra
         → resolves to prop_id = 69579486 via PropID_Master

[Step 5] No ownership check: system accepts prop_id = 69579486 without verifying
         that B80 Srtucture is the expected party_1 for Aisha's CoN

[Step 6] New TOT inserted:
         pra: id=149417, mlsFNo=RES-2026-2220, temp_fileno=TEMP-50177,
              prop_id=69579486, party_2=AISHA ABUBAKAR SALISU

[Result] prop_id=69579486 now ties B80's OP to Aisha's TOT → wrong grouping
```

The identical sequence applies to TEMP-50114 / RES-2026-2219 / Gali Rabiu / prop_id=69579486.

---

## 5. Contributing Factor — UI File Picker

The file-picker that lets users choose the "source file" for an FFR Change of Name likely shows **all files** without filtering by applicant name or status. This makes it easy to accidentally (or unknowingly) select the wrong OP as the source. The backend performs no sanity-check after the selection.

---

## 6. Why It Only Affects Some Files

Not every CoN application triggers this. The mismatch only occurs when:
1. The user selects a source file that belongs to a **different** applicant, AND
2. No backend ownership check rejects it.

Files where the correct source OP is selected are processed correctly. This explains why the bug appears sporadically rather than universally.

---

## 7. Recommendations

### Immediate (data fixes)
- [x] Detach wrong OPs from affected prop_ids (`_fix_op_detach.php` — done 2026-05-21)
- [x] Create correct OP records under the right prop_ids (`_fix_op_party1.php` — done 2026-05-21)
- [ ] Audit `pra` for other TOT rows where `prop_id` matches an `instrument_capture` row for a **different** applicant name. SQL below.

```sql
-- Find other potential mismatches
SELECT p.id as pra_id, p.mlsFNo, p.prop_id,
       p.party_1 as tot_party1, p.party_2 as tot_party2,
       ic.id as ic_id, ic.temp_fileno,
       ic.party_1_name as op_party1, ic.party_2_name as op_party2
FROM pra p
JOIN instrument_capture ic
    ON TRY_CAST(ic.prop_id AS bigint) = TRY_CAST(p.prop_id AS bigint)
WHERE p.instrument_type LIKE '%Transfer%'
  AND UPPER(LTRIM(RTRIM(ic.party_2_name))) <> UPPER(LTRIM(RTRIM(p.party_1)))
  AND ic.instrument_type LIKE '%Occupancy Permit%'
ORDER BY p.id DESC;
```

---

### Short-Term (code fixes)

#### Fix 1 — Add ownership validation in `saveFfrChangeOfName`

**File:** `app/Http/Controllers/LandsOneStopShop/ApplicationController.php` around line 1504

After `resolveMotherOpRecord()` resolves the source OP, compare its owner against the expected `party_1` (original holder) supplied by the user. Reject the request if they do not match.

```php
// After line 1504
$opRecord = $this->resolveMotherOpRecord($sourceHistory);

// === NEW: ownership guard ===
if ($opRecord) {
    $opOwner = strtoupper(trim((string)(
        data_get($opRecord, 'party_2') ?: data_get($opRecord, 'parties.party_2') ?: ''
    )));
    $expectedParty1 = strtoupper(trim((string)($validated['party_1'] ?? '')));

    if ($expectedParty1 !== '' && $opOwner !== '' && $opOwner !== $expectedParty1) {
        Log::warning('FFR save blocked: source OP owner mismatch', [
            'source_file_no'  => $sourceFileNo,
            'op_owner'        => $opOwner,
            'expected_party1' => $expectedParty1,
        ]);
        return response()->json([
            'success' => false,
            'message' => "Source file owner ({$opOwner}) does not match the expected party 1 ({$expectedParty1}). Please select the correct source file.",
        ], 422);
    }
}
// === END NEW ===
```

---

#### Fix 2 — Log a warning in `resolveMotherOpRecord` when multiple candidates are found

**File:** `app/Http/Controllers/LandsOneStopShop/ApplicationController.php:2807`

```php
private function resolveMotherOpRecord(array $history): ?array
{
    // ... existing filter logic ...

    if (count($candidates) > 1) {
        Log::warning('resolveMotherOpRecord: multiple OP candidates found — using oldest', [
            'count'      => count($candidates),
            'prop_ids'   => array_column($candidates, 'prop_id'),
            'temp_filenos' => array_column($candidates, 'temp_fileno'),
        ]);
    }

    usort($candidates, ...);
    return $candidates[0] ?? null;
}
```

This does not fix the root cause but surfaces the ambiguity in logs so it can be caught before causing data corruption.

---

#### Fix 3 — File picker should filter by applicant / system_source

The FFR CoN file-picker UI should only show OP files where:
- `system_source = 'OSSOPCHANGEOFNAME'` OR
- The OP's `party_2` matches the name entered for the original holder

This prevents users from accidentally selecting an unrelated OP.

---

### Long-Term (structural fixes)

#### Fix 4 — Add a `UNIQUE` constraint on `(prop_id, instrument_type)` in `pra`

Prevents two different Transfer of Title rows from sharing the same `prop_id`, making the grouping enforced at the DB level.

```sql
ALTER TABLE pra
ADD CONSTRAINT uq_pra_prop_instrument
UNIQUE (prop_id, instrument_type);
-- Note: test carefully — may need to handle NULL prop_id rows first
```

#### Fix 5 — Store `instrument_capture_id` on the TOT row in `pra`

When a TOT is created via directOpCapture or saveFfrChangeOfName, record the explicit `instrument_capture.id` of the source OP on the pra row. This creates a hard foreign-key link and removes reliance on `prop_id` coincidence for grouping.

#### Fix 6 — Separate `temp_fileno` for OP vs TOT

The OP holds the `temp_fileno` as its own identity. The TOT should carry the **official MLS file number** (`mlsFNo`) as its primary identifier, not inherit the OP's `temp_fileno`. This eliminates the ambiguity in `findAllByFileNumber` where a `temp_fileno` search returns both the OP and the TOT.

---

## 8. Files Modified (2026-05-21 data fix)

| File | Purpose |
|---|---|
| `_fix_op_detach.php` | Detaches wrong OPs, allocates new prop_ids, creates correct OPs |
| `_fix_op_party1.php` | Corrects party_1_name → Kano State Government on new IC rows |

These scripts are safe to delete after the audit in §7 is complete.

---

## 9. Summary

| # | Factor | Severity |
|---|---|---|
| A | No ownership check in `saveFfrChangeOfName` — any source file is accepted | **Critical** |
| B | `resolveMotherOpRecord` picks oldest OP silently when multiple found | High |
| C | `findAllByFileNumber` broad OR search returns unrelated records | Medium |
| D | PropID reuse via shared `temp_fileno` ties the wrong OP to the new TOT | High |
| E | No DB-level uniqueness on `prop_id` in `pra` | Medium |
| F | File picker shows all files, no applicant filter | Medium |

**Single-sentence root cause:**  
A user selected the wrong source file (an OP belonging to a different applicant) during a Change of Name FFR, and the backend blindly accepted it because there is no validation that the source OP's owner matches the expected original holder.
