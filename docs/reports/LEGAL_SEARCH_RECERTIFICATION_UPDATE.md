# Legal Search — KANGIS & Land/Physical Planning Recertification Update

**Date:** 2026-07-05
**Area:** Legal Search (LS) — Official (for filing purpose)
**Status:** Complete & verified (read-only DB probes + `php -l` clean)

---

## 1. Summary

KANGIS Recertification and Land & Physical Planning (MLPP) Recertification links are stored on
the `related_file_number` staging table and are *supposed* to appear on the Legal Search timeline
as orange **"Related Fileno"** rows. The plumbing already existed
([`LegalSearchService::fetchRelatedRecertificationRows()`](../../app/Services/LegalSearchService.php)),
but a live study of `search()` exposed **two defects**:

| # | Defect | Effect before fix |
|---|--------|-------------------|
| 1 | **Wrong endpoint on counterpart search** | The method always emitted `related_fileno`. When the searched file *was* the `related_fileno`, LS showed the user their own number back instead of the linked parent. |
| 2 | **Reach was exact-string-only** | ~99% of recert rows have an empty `prop_id`, so they only surfaced when the searched text exactly matched a stored endpoint. Format drift (`MLKN 237` vs `MLKN237`, base vs `(T)`) and prop_id history-expansion missed them. |

Both are now fixed. The orange "Related Fileno" row style, badge, and contamination-guard
passthrough were **kept unchanged** — only *which* number/label the row shows, and *when* it
matches, were corrected.

---

## 2. What a "Recertification" is

Each `related_file_number` row links two endpoints: a `file_number` (parent) and a
`related_fileno` (counterpart), plus a `transaction_type` label.

| Transaction type | Endpoint pattern | Example |
|------------------|------------------|---------|
| **KANGIS Recertification** | KANGIS-legacy number (`KNML` / `MLKN` / `KNGP`) ↔ MLS number | `MLKN 237 ↔ COM-1998-20` |
| **Land & Physical Planning Recertification** | `-RC-` MLS file ↔ old `KN <n>` file | `AG-RC-1982-200 ↔ KN 4431` |

### Classification rules (from [`add_transaction_type_to_rfn.sql`](../../database/migrations/manual/add_transaction_type_to_rfn.sql))

Evaluated top-down, first match wins:

1. `comment LIKE 'MINISTRY OF LAND AND PHYSICAL%'` → **Land & Physical Planning Recertification**
2. `comment LIKE 'KANGIS RECERTIFICATION%'` → **KANGIS Recertification**
3. Parent `file_number` starts with `KNML` / `MLKN` / `KNGP` → **KANGIS Recertification**
   (the parent itself is the KANGIS file being recertified)
4. …then Merger / Subdivision / Change of Purpose rules for the remaining rows.

---

## 3. Prerequisite: the link must live in `related_file_number`

`fetchRelatedRecertificationRows()` reads **exclusively** from the `related_file_number` table.
For a recertification to show on LS:

1. **A row must exist in `related_file_number`** linking the two file numbers — the hard requirement.
2. The row should be **typed** as `KANGIS Recertification` / `Land & Physical Planning Recertification`
   for the correct label (untyped rows still show, labelled generic "Recertification").
3. The search must hit one of the row's endpoints (see match parameters below).

> **`related_file_number` is a rebuilt staging table, not live.** It is dropped and rebuilt by
> [`create_related_file_number_table.sql`](../../database/migrations/manual/create_related_file_number_table.sql),
> which harvests links from `file_indexings.related_fileno`, `deprecated_records.related_fileno`,
> `pra.related_file_number`, and `pic.related_file_number`. `fileNumber` and `dciv_file_no` are
> intentionally excluded. If a link exists in a source table but not on LS, the staging table is
> **stale** and needs a rebuild.

---

## 4. Match parameters (how a row is pulled onto LS)

A `related_file_number` row is selected when **any** of these is true:

- `rfn.file_number` ∈ **candidates**, OR
- `rfn.related_fileno` ∈ **candidates**, OR
- `rfn.prop_id` ∈ the searched property's prop_ids.

Where **candidates** = the searched file number **plus every file number already resolved in the
LS result set** (columns `fileno` / `file_number` / `mlsFNo` / `kangisFileNo` / `NewKANGISFileno`),
each expanded through `fileNumberVariants()` to add base and `(T)` forms.

Because these rows carry no `prop_id`, candidates are derived only from the searched property's own
records — there is **no cross-property leak risk**.

LS then displays the **counterpart endpoint** — i.e. the side of the link that is *not* the file
you searched.

---

## 5. Code changes

### File: [`app/Services/LegalSearchService.php`](../../app/Services/LegalSearchService.php)

Method `fetchRelatedRecertificationRows($conn, string $fileNo, array $existingRows)`.

**Edit 1 — candidate-set builder + widened WHERE + normalization set (Gap 2)**
- Collect a de-duplicated candidate set from `$fileNo` plus every file-number column across
  `$existingRows`, expanded via `fileNumberVariants()`.
- Replace the old `WHERE rfn.file_number = ? OR rfn.related_fileno = ?` with a
  `whereIn(file_number, $candidates) OR whereIn(related_fileno, $candidates) OR whereIn(prop_id, $propIds)`.
- Add a `$norm` closure (uppercase, whitespace-collapse, per-segment leading-zero strip) and build
  a `$searchedSet` of normalized candidate identifiers.

**Edit 2 — counterpart-selection logic (Gap 1)**
- For each row, normalize both endpoints. If `related_fileno` is in the searched set but
  `file_number` is not, flip to display `file_number` (the parent); otherwise keep `related_fileno`.
- Pick the display title from whichever side is being shown.

**Edit 3 — emitted-row field updates**
- `party_1` → the resolved counterpart title.
- `parent_file_number` → the *other* side, so reciprocal-collapse and the front-end "parent"
  tooltip stay correct.

### Front-end: [`resources/views/legal_search/js.blade.php`](../../resources/views/legal_search/js.blade.php)

No recertification-specific change in this update — the orange "Related Fileno" rendering already
handled these rows. *(This file carries earlier, separate commissioning-date routing changes and is
part of the same prod upload batch.)*

---

## 6. Verification

Read-only probes via `php artisan tinker --execute`, running the real `search()`:

| Search | Result after fix |
|--------|------------------|
| `MLKN 237` | → `COM-1998-20` (unchanged, still correct) |
| `COM-1998-20` | → **`MLKN 237` and `MLKN 1734`** (was: `COM-1998-20` ×2) |
| `AG-RC-1982-200` | → `KN 4431` |
| `KN 4431` | → **`AG-RC-1982-200` and `RES-RC-1996-2`** (was: `KN 4431` ×2) |

- No self-referential rows (emitted `fileno` never equals the searched file).
- `php -l app/Services/LegalSearchService.php` → clean.
- Merger / Subdivision links also improved as a side effect (searching a merger source now shows
  the merged-into target).

---

## 7. Production upload manifest

| Item | Detail |
|------|--------|
| **Code files** | `app/Services/LegalSearchService.php`, `resources/views/legal_search/js.blade.php` |
| **DB writes** | **None.** Only read-only `SELECT`s were run during the study. |
| **DB dependency** | The `related_file_number` table must exist on prod, populated by the manual SQL scripts. If the recert links are not showing, rebuild it via `create_related_file_number_table.sql` then re-run `add_transaction_type_to_rfn.sql`. |

---

## 8. Related files

- [`app/Services/LegalSearchService.php`](../../app/Services/LegalSearchService.php) — LS query/service layer (modified)
- [`app/Http/Controllers/RelatedFileNumberController.php`](../../app/Http/Controllers/RelatedFileNumberController.php) — `/related-file-number` QA browser
- [`resources/views/related_file_number/index.blade.php`](../../resources/views/related_file_number/index.blade.php) — QA browser UI
- [`database/migrations/manual/create_related_file_number_table.sql`](../../database/migrations/manual/create_related_file_number_table.sql) — staging-table rebuild
- [`database/migrations/manual/add_transaction_type_to_rfn.sql`](../../database/migrations/manual/add_transaction_type_to_rfn.sql) — transaction_type classification
