# Legal Search — KANGIS & Land/Physical Planning Recertification Update

**Date:** 2026-07-06 (updated; originally 2026-07-05)
**Area:** Legal Search (LS) — Official (for filing purpose)
**Status:** Complete & verified (read-only DB probes on **live** `10.50.1.1` + `php -l` clean)

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
| 3 | **KANGIS recert showed the MLS number, not the KANGIS number** | For a reciprocal KANGIS Recertification pair where *both* endpoints are family numbers, the counterpart flip never fired and the reciprocal-collapse pass then preferred the *not-searched* endpoint — surfacing the land/MLS number (`CON-AG-2014-35`) instead of the KANGIS number (`MLKN 2455`) that the recert actually belongs to. |
| 4 | **Recertification one hop too far never appeared** | The match only looked at the searched file's *own* numbers. When a recert belonged to an *ancestor* (e.g. searching a Change-of-Purpose grandchild, whose mother holds the KANGIS recert), the ancestor was surfaced but its recert was not — the recert's endpoints were never in the candidate set. |

All four are now fixed. The orange "Related Fileno" row style, badge, and contamination-guard
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

### Which endpoint a KANGIS recert row displays (Defect 3 fix)

A KANGIS Recertification records the recertification of a **KANGIS-legacy file** (e.g. `MLKN 2455`)
that also carries an MLS number (e.g. `CON-AG-2014-35`). The event belongs to the KANGIS file, so
the row must **always display the KANGIS-format endpoint** (`KNML` / `MLKN` / `KNGP` / `KN <n>`) —
never the MLS counterpart — regardless of which side was searched.

This overrides the generic counterpart flip. It is needed because the link is usually stored as a
**reciprocal pair** (`A → B` *and* `B → A`); when both endpoints are family numbers the generic
flip fires for neither row, and the reciprocal-collapse pass would otherwise keep the *not-searched*
side (the MLS number). Pinning to the KANGIS-format side makes both stored directions resolve to
the same number, which then collapses to a single correct row.

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
you searched (except KANGIS recerts, which always show the KANGIS-format side — see §2).

### Second hop: an ancestor's recertification (Defect 4 fix)

The candidate match above is single-level: it only pulls links whose endpoint is one of the
searched file's **own** numbers. A recertification often sits one link further out. After the first
query, LS therefore collects every endpoint it just discovered that is **not** already a searched
candidate (the ancestors, e.g. a mother file reached through a Subdivision link) and runs **one
extra query** for those endpoints — restricted to `transaction_type LIKE '%Recertification%'`.

- The extra hop is **exactly one level** (no recursion) — a recert two ancestors up will not chain.
- It is restricted to recertifications, so it **cannot** drag in the ancestor's unrelated
  subdivisions or mergers.
- Results are merged and **de-duped by `rfn.id`** against the first query.

---

## 4b. Worked lineage example — the `CON-AG-2014-35` family

This family (verified live on `10.50.1.1`) exercises every rule above. Root parcel `prop_id = 7530`.

```
                         MLKN 2455  ── KANGIS Recertification ──►  CON-AG-2014-35
                                                                    (mother, prop 7530)
                                                                          │
                                            ┌─────────────── Subdivision ─┼───────────────┐
                                            ▼                             ▼               ▼
                                     CON-AG-2026-108            CON-AG-2026-109    CON-AG-2026-110
                                       (prop 130472)              (prop 130473)      (prop 130474)
                                            │                             │
                                   Change of Purpose             Change of Purpose
                                            ▼                             ▼
                                     CON-COM-2026-430           CON-COM-2026-431
                                       (prop 130472)              (prop 130473)
```

Underlying `related_file_number` links (note the reciprocal KANGIS pair and the zero-padded
duplicate `MLKN 02455`, all collapsed to one row on screen):

| id | file_number | related_fileno | transaction_type |
|----|-------------|----------------|------------------|
| 7808 | CON-AG-2026-108 | CON-AG-2014-35 | Subdivision |
| 7809 | CON-AG-2026-109 | CON-AG-2014-35 | Subdivision |
| 1836 | CON-AG-2026-110 | CON-AG-2014-35 | Subdivision |
| 1837 | CON-COM-2026-430 | CON-AG-2026-108 | *(untyped → "Related File")* |
| 1838 | CON-COM-2026-431 | CON-AG-2026-109 | *(untyped → "Related File")* |
| 7807 | CON-AG-2014-35 | MLKN 2455 | KANGIS Recertification |
| 7810 | MLKN 2455 | CON-AG-2014-35 | KANGIS Recertification |
| 8328 | CON-AG-2014-35 | MLKN 02455 | KANGIS Recertification |

**Searching `CON-COM-2026-430`** (a Change-of-Purpose grandchild) resolves in two hops:

1. First query matches on the grandchild's own numbers (`CON-COM-2026-430`, `CON-AG-2026-108`) →
   surfaces the direct parent `CON-AG-2026-108` and, via id 7808, the **mother `CON-AG-2014-35`**.
2. `CON-AG-2014-35` is a *newly-discovered ancestor* → the second hop queries its recert links
   (7807 / 7810 / 8328) → surfaces the **KANGIS Recertification**, pinned to `MLKN 2455` and
   collapsed to a single row.

Resulting timeline:

```
  CON-AG-2026-108  | Subdivision              (direct subdivision parent)
  CON-COM-2026-430 | Change of Purpose        (the searched file)
  CON-AG-2014-35   | Subdivision              (mother  — Defect 4 chain, hop 1)
  MLKN 2455        | KANGIS Recertification   (recert  — Defect 4 chain, hop 2; Defect 3 pin)
```

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

**Edit 4 — pin the displayed endpoint for KANGIS recerts (Gap/Defect 3)**
- After the generic flip, if `transaction_type` contains `KANGIS`, force the displayed side to the
  endpoint matching the KANGIS-format pattern `^[A-Z]{2,4}\s?\d{2,6}$` (covers `MLKN`/`KNML`/`KNGP`/
  `KN <n>`). Set `parent_file_number` to the other (MLS) side. Both stored directions of a
  reciprocal pair now resolve to the same KANGIS number and collapse to one row.

**Edit 5 — second-hop recertification query (Gap/Defect 4)**
- After the primary `->get()`, collect endpoints just discovered that are not in `$searchedSet`
  (the ancestors), expand via `fileNumberVariants()`, and run one extra `related_file_number` query
  filtered to `transaction_type LIKE '%Recertification%'`. Merge the results into `$rows`, de-duped
  by `rfn.id`. One level only — no recursion.

### Front-end: [`resources/views/legal_search/js.blade.php`](../../resources/views/legal_search/js.blade.php)

No recertification-specific change in this update — the orange "Related Fileno" rendering already
handled these rows. *(This file carries earlier, separate commissioning-date routing changes and is
part of the same prod upload batch.)*

---

## 6. Verification

Read-only probes via `php artisan tinker --execute`, running the real `search()`:

**Defects 1 & 2 (original study):**

| Search | Result after fix |
|--------|------------------|
| `MLKN 237` | → `COM-1998-20` (unchanged, still correct) |
| `COM-1998-20` | → **`MLKN 237` and `MLKN 1734`** (was: `COM-1998-20` ×2) |
| `AG-RC-1982-200` | → `KN 4431` |
| `KN 4431` | → **`AG-RC-1982-200` and `RES-RC-1996-2`** (was: `KN 4431` ×2) |

**Defects 3 & 4 (`CON-AG-2014-35` family, verified live on `10.50.1.1`):**

| Search | KANGIS recert row shown | Mother shown | Notes |
|--------|-------------------------|--------------|-------|
| `MLKN 2455` | `MLKN 2455` ✓ (was `CON-AG-2014-35`) | — | Defect 3: KANGIS number now pinned |
| `CON-AG-2014-35` | `MLKN 2455` ✓ (single row) | self | reciprocal pair + `MLKN 02455` collapse to one |
| `CON-COM-2026-430` | **`MLKN 2455` ✓** | **`CON-AG-2014-35` ✓** | Defect 4: recert reached via 2-hop from COP grandchild |
| `CON-COM-2026-431` | **`MLKN 2455` ✓** | **`CON-AG-2014-35` ✓** | same |

- No self-referential rows (emitted `fileno` never equals the searched file).
- KANGIS recert row appears **exactly once** per search — no duplicates from the reciprocal pair
  or the zero-padded `MLKN 02455` variant.
- The second hop is bounded to `%Recertification%` types — searching the COP children does **not**
  pull in the mother's other subdivision siblings as noise.
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

## 8. Follow-up: W/R/C & CoFO status notices (duplicate_fileno)

**Added 2026-07-05.** When a searched file exists in the `duplicate_fileno` registry under a
`[WRC]` or `[COFO_*]` comment tag, the report now shows a general status notice — modelled on the
existing **Caveat** note.

| Tag in `duplicate_fileno.comment` | Notice on report | Colour |
|-----------------------------------|------------------|--------|
| `[WRC]` (and all WRC sub-statuses: CANCELLED, WITHDRAWN, REVOKED, CLOSED, SURRENDERED, EMPTY FILE, ISSUE FILE) | *N.B. This Application has been Cancelled !!!* (editable per-file) | red `#dc2626` |
| `[COFO_READY]` | *The Certificate of Occupancy for this property is ready for collection.* | green `#166534` |
| `[COFO_COLLECTED]` | *The Certificate of Occupancy for this property has been collected.* | green `#166534` |

- **Category is read from the `comment` tag prefix**, not the (unreliable) `registry` column.
- **Matching:** the searched file's number variants (`fileNumberVariants()`) OR its `prop_id`.
- `[COFO_COLLECTED]` overrides `[COFO_READY]` when both are present.
- `[DUPLICATE]` and `[TEMPORARY]` tags are intentionally **not** surfaced.

**Backend:** `LegalSearchService::buildPrintReport()` — new `duplicate_fileno` lookup; payload gains
`wrc_comment` and `cofo_comment`.
**Front-end:** the three print templates (`OFFICIAL SEARCH REPORT.html`, `ONLINE.html`,
`PAY-PER-SEARCH.html`) gain `#report-wrc-comment` / `#report-cofo-comment` spans + render logic,
mirroring the other dynamic comment fields.

### Bug fix bundled in the same commit

`buildPrintReport()` called a **non-existent** `resolveCommissioningDate()` (an incomplete rename —
the method is now `resolveCommissioningInfo()` returning `['date' => …, 'number' => …]`). This threw
a fatal error on **every** print-report generation. Fixed to
`resolveCommissioningInfo($fileNumber, $fileNo)['date']`. **This bug is present at current `HEAD`,
so prod is affected until this file is uploaded.**

**DB dependency:** the `duplicate_fileno` table (already on prod). **No DB writes** — read-only.

---

## 9. Related files

- [`app/Services/LegalSearchService.php`](../../app/Services/LegalSearchService.php) — LS query/service layer (modified)
- [`app/Http/Controllers/RelatedFileNumberController.php`](../../app/Http/Controllers/RelatedFileNumberController.php) — `/related-file-number` QA browser
- [`resources/views/related_file_number/index.blade.php`](../../resources/views/related_file_number/index.blade.php) — QA browser UI
- [`database/migrations/manual/create_related_file_number_table.sql`](../../database/migrations/manual/create_related_file_number_table.sql) — staging-table rebuild
- [`database/migrations/manual/add_transaction_type_to_rfn.sql`](../../database/migrations/manual/add_transaction_type_to_rfn.sql) — transaction_type classification
