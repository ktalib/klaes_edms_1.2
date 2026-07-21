# Legal Search — Timeline Lifecycle Grouping: Implementation & Hardening Report

**Date:** 2026-07-20
**Area:** Legal Search (LS) — on-screen timeline **and** printable report
**Status:** ~98% stable (verified against live data for the worked cases below)
**Primary code:**
[`app/Services/LegalSearchService.php`](../../app/Services/LegalSearchService.php) (backend, authoritative),
[`resources/views/legal_search/js.blade.php`](../../resources/views/legal_search/js.blade.php) (frontend mirror),
[`resources/views/legal_search/style.blade.php`](../../resources/views/legal_search/style.blade.php) (group divider)

> **Read this alongside** [`../LEGAL_SEARCH_OVERVIEW.md`](../LEGAL_SEARCH_OVERVIEW.md) (end-to-end architecture),
> [`../guides/KLAES Legal Search Timeline Developer ImplementationFlow.md`](../guides/KLAES%20Legal%20Search%20Timeline%20Developer%20ImplementationFlow.md)
> (the client's weighting/sorting spec), and [`kangis-alias/`](kangis-alias/) (the MLKN 2455 / CON-AG-2014-35 case files).
> This report documents the layer those docs do **not**: how sorted rows are then **grouped into per-file
> lifecycle blocks**, and the five invariants that keep that grouping honest. For safe day-to-day maintenance,
> use the companion **[Agent Guide](../guides/LEGAL_SEARCH_LIFECYCLE_GROUPING_AGENT_GUIDE.md)**.

---

## 1. What "lifecycle grouping" is

Earlier work (the *Developer Implementation Flow* spec) defined how timeline rows are **weighted and sorted**
into one chronological stream. Lifecycle grouping is the layer on top: it takes that stream and **breaks it
into per-file blocks**, each rendered as

```
  ── File Commissioning ────────────────────  (green divider marks the block top)
     … the file's associated transactions …
     File Decommissioning        (only if the file was actually decommissioned)
```

Blocks are ordered **searched/primary file first, then related files** (children, successors, parents). A thin
green rule (`.ls-group-divider`, a 2px top border) separates each block.

The purpose: a legal searcher reads one land file's whole life as a contiguous story, then the next file's,
rather than a flat date-sorted list in which a child file's commissioning is interleaved among the mother's
dealings.

### The single most important architectural fact

**The backend is authoritative for grouping — for BOTH deliverables.** `search()` stamps every row with a
`lifecycle_file_no` via [`tagRowsWithLifecycleFileNo()`](../../app/Services/LegalSearchService.php); the
on-screen timeline consumes those tagged rows (`getRelatedTransactions()` → `searchResults` →
`data.transactions`), and the printable report calls
[`groupTimelineByLifecycle()`](../../app/Services/LegalSearchService.php). The frontend functions
(`groupAndSortTimeline`, `arrangeLifecycleFileRows`, `dedupeLifecycleRows`) **re-derive** the same grouping and
must **agree** with the backend, not fight it. **Fix grouping bugs in the PHP first; then mirror in the JS.**

---

## 2. The five invariants (the rules that were hardened)

Each rule below is a client requirement surfaced during review. Each has a backend home and a frontend mirror.

### R1 — KANGIS numbers are aliases, and the searched file's alias is LOCKED

A KANGIS number (`KNML`/`MLKN`/`KNGP` + 3–6 digits, or `KN <n>` = new_kangis) is an **alias** of a permanent
land file, not a lifecycle of its own. Every KANGIS-keyed row (KANGIS Recertification, KANGIS C of O,
KANGIS-keyed deed history) must **fold into its owning land file's block**.

**The gotcha that caused the original bug:** the *same* KANGIS number can be recertified against **multiple**
files. Real data: two "KANGIS Recertification" rows for **KNML 6992**, parented to *both* CON-COM-2023-197 and
CON-RES-2019-809. A stray recert row was repointing the alias, so KANGIS rows formed a phantom bottom group and
duplicate recerts survived.

**Fix:** the authoritative signal is the **searched file's `file_number_display`** (e.g.
`"CON-COM-2023-197 (KNML 6992)"`). [`aliasHintsFromDisplay()`](../../app/Services/LegalSearchService.php) parses
it into `{normalizedKangis => normalizedMain}`, and those hint keys are **LOCKED** in
`tagRowsWithLifecycleFileNo()` — never overwritten by row-derived pairings. Frontend mirror:
`buildKangisAliasMap()` seeds from `_file_number_display`, `selectedFile`,
`window.__lsLastSearchedFileNumber`, and `userSelectedFileNumber`.

### R2 — Within a block: Commissioning first, Decommissioning last; Recert directly above its C of O

Phase order inside a block is **Commissioning → Transactions → Decommissioning**, and phases are classified by
[`classifyLifecycleEventType()`](../../app/Services/LegalSearchService.php) reading **`transaction_type`** (not
`source_table`/`instrument_type`) — because a related file's commissioning/decommissioning label lives in
`transaction_type`. Within the transaction phase, each **KANGIS Recertification sits immediately above its
matching KANGIS C of O**, and duplicate recerts for the same KANGIS key are suppressed
([`placeKangisRecertBeforeCofo()`](../../app/Services/LegalSearchService.php), mirrored in JS). `dedupeLifecycleRows()`
emits each event type's winner at its **first-occurrence** position so the recert stays above the C of O.

### R3 — Commissioning date authority = `mls_file_no` presence, else year only

A file's **File Commissioning** row shows a genuine date **only if the file was KLAES-commissioned**, and
**presence in the `mls_file_no` table is the sole authoritative signal**. A file absent from `mls_file_no` was
not KLAES-commissioned → its Commissioning row shows **only the bare year embedded in the file number**
(e.g. `CON-AG-2014-35` → `2014`) — never a legacy `fileNumber` date, never `file_indexings.created_at`, never
the earliest transaction's date.

Enforced in [`resolveCommissioningInfo()`](../../app/Services/LegalSearchService.php): if no `mls_file_no` row
matches the file-number variants it returns year-only **before** consulting any legacy path. Frontend mirror:
`buildLifecycleCommissioningRow()` uses `meta.commissioning_date` then year — the old "earliest-transaction
date" fallback was **removed** because it leaked a full date for non-KLAES files. See
[`../commissioning-date`… memory note] and [`../LEGAL_SEARCH_OVERVIEW.md`](../LEGAL_SEARCH_OVERVIEW.md) §8.

### R4 — Children are usually commissioned only, not decommissioned

Most child files (subdivision/merger/change-of-purpose successors) are **commissioned but never
decommissioned**. A File Decommissioning row must appear for a block **only if that file was actually
decommissioned**, per-file, not because the *searched* file was superseded.

**The bug:** the searched file's global flag `window._lsLineage?.is_superseded` was being applied to **every**
block, so active children wrongly showed a Decommissioning row. **Fix** in
`ensureLifecycleSyntheticRows()`:

```js
const isSearchedFileGroup = (fno === searchedNo) || (fno === primaryMainNo);
const isDecommissioned = metaDecommissioned || (isSearchedFileGroup && !!window._lsLineage?.is_superseded);
```

The global flag is now scoped to the searched file's own block; every other block uses its **per-file**
`meta.is_decommissioned`. Verified: for CON-COM-2023-197's family only the main file + units 108/109 were
decommissioned; 110, 430, 431 stayed active.

### R5 — "Last Transaction" = last real dealing on the MAIN file, in timeline display order

The **Last Transaction** status field (`#last-transaction-value` in `renderFileHistory`) must reflect the most
recent genuine **dealing on the searched/main file only**, matching what the timeline table shows. Rules:

1. **Use the deduped/preferred set** (`dedupeTransactionsForTimelineAndReport(rows).preferred`), not raw
   `getRelatedTransactions`. A shared instrument (e.g. a Deed of Assignment appearing on several files) is
   attributed by dedup to one file; the raw set wrongly counted it for the main file.
2. **Restrict to the main file's block** (`lifecycle_file_no` === searched key; a searched KANGIS alias is
   mapped through `buildKangisAliasMap`).
3. **Exclude non-dealings:** parcel updates (Subdivision, Merger, Consolidation, Change of Purpose, Extension,
   Reconstitution, Resettlement, Regrant), certificate/admin events (**Certificate of Occupancy**, **KANGIS
   Recertification**), and synthetic lifecycle rows (Commissioning/Decommissioning/Temporary File).
4. **Pick the LAST eligible row in the timeline's display order — not a date-max.** Run the eligible rows through
   `sortTimelineChronologically()` and take the last element.

> **Why rule 4 changed (2026-07-20):** a date-max silently drops a dealing that has **no transaction date**.
> Real case: **CON-AG-2014-35** has a **Deed of Mortgage** recorded only by registration particulars
> (reg `56/21/21`, no `transaction_date`). `sortTimelineChronologically()` parks undated rows at the very end
> as "floaters" — so they are the most recent — but a date-max skipped it and returned the earlier
> Right of Occupancy (Sep 22 2014). Sorting the same way the timeline does keeps the field in agreement with
> what the user sees.

---

## 3. Bugs found and fixed this cycle

| # | Symptom (client report) | Root cause | Fix | Rule |
|---|-------------------------|-----------|-----|------|
| 1 | KANGIS rows (KNML 6992) formed a phantom bottom group instead of joining the main file | Second recert row (parent CON-RES-2019-809) overwrote the alias map | Lock hint keys from the searched file's display in `tagRowsWithLifecycleFileNo()`; add `aliasHintsFromDisplay()` | R1 |
| 2 | The same KNML 6992 Recertification displayed twice | Per-group dedupe can't see cross-group duplicates once the alias split them | Alias lock reunites them; `dedupeLifecycleRows()` first-occurrence winner | R1/R2 |
| 3 | CON-RES-2019-809 rendered Commissioning & Decommissioning at the block bottom | Phase classification read the wrong field | `classifyLifecycleEventType()` reads `transaction_type` | R2 |
| 4 | Active child files showed a File Decommissioning row | Searched file's `is_superseded` global applied to every block | Scope the global flag to the searched block; use per-file `meta.is_decommissioned` | R4 |
| 5 | Non-KLAES files showed a full commissioning date | Legacy `fileNumber` / `file_indexings` / earliest-txn fallbacks leaked a date | `resolveCommissioningInfo()` returns year-only unless in `mls_file_no` | R3 |
| 6 | Last Transaction showed a parcel-update / CofO / a related file's deed | Field used the raw set and didn't exclude admin/parcel types | Deduped set + main-block restriction + exclusions | R5 |
| 7 | Last Transaction showed Right of Occupancy where a later **undated** Deed of Mortgage exists | Date-max can't rank a dateless dealing | Order eligible rows via `sortTimelineChronologically()`, take the last | R5 |

---

## 4. Verified cases

| File | Expected | Result |
|------|----------|--------|
| **CON-COM-2023-197** | KNML 6992 rows fold into main block; one recert; Last Transaction = **Right of Occupancy** (later Deed of Assignment deduped to CON-RES-2005-148; C of O/Recert excluded) | ✅ |
| **CON-COM-2023-197 family** | Only main + units 108/109 decommissioned; 110/430/431 active | ✅ |
| **CON-AG-2014-35** | Commissioning date shows year **2014** (not in `mls_file_no`); Last Transaction = **Deed of Mortgage** (undated main-file dealing, later than RoO) | ✅ |

All verification was done via read-only `php artisan tinker`/`php <script>` probes against the configured
`sqlsrv` connection; see the Agent Guide for the exact recipes.

---

## 5. How this extends the existing documentation

| Existing doc | Covers | This report adds |
|--------------|--------|------------------|
| [`LEGAL_SEARCH_OVERVIEW.md`](../LEGAL_SEARCH_OVERVIEW.md) | prop_id spine, 4 source tables, search pipeline, contamination/decommission guards, synthetic rows, output shape | The **grouping layer** applied after those rows are assembled |
| [Developer ImplementationFlow](../guides/KLAES%20Legal%20Search%20Timeline%20Developer%20ImplementationFlow.md) | Timeline **weights** + composite sort + positional rules (Temp nesting, Recert<CofO, decommission pairs, caveats) | How sorted rows are **partitioned into per-file blocks** and the 5 grouping invariants |
| [`LEGAL_SEARCH_RECERTIFICATION_UPDATE.md`](LEGAL_SEARCH_RECERTIFICATION_UPDATE.md) | How recert links become "Related Fileno" rows | Where those recert rows **land** in the grouped view (R1/R2) |
| [`kangis-alias/`](kangis-alias/) | MLKN 2455 / CON-AG-2014-35 case analysis | The general alias-locking rule (R1) that generalises those cases |

---

## 6. Known residual risk (the ~2%)

- **Alias signal depends on `file_number_display`.** If a search path reaches the timeline without a populated
  `"MAIN (ALIAS)"` display string, R1's lock has nothing to seed from and KANGIS rows can drift. Always ensure
  the display string is set for entry points that render the timeline.
- **`related_file_number` staleness** (inherited from the overview): missing recert links are usually a stale
  staging table, not a grouping bug — rebuild before debugging grouping.
- **Dedup attribution of shared instruments** decides which block a shared deed/mortgage lands in, which in turn
  drives R5. If Last Transaction looks wrong, first confirm which file `dedupeTransactionsForTimelineAndReport`
  attributed the shared instrument to.

---

## 7. Related documents

- **Agent guide (start here to make changes):**
  [`../guides/LEGAL_SEARCH_LIFECYCLE_GROUPING_AGENT_GUIDE.md`](../guides/LEGAL_SEARCH_LIFECYCLE_GROUPING_AGENT_GUIDE.md)
- [`../LEGAL_SEARCH_OVERVIEW.md`](../LEGAL_SEARCH_OVERVIEW.md)
- [`../guides/KLAES Legal Search Timeline Developer ImplementationFlow.md`](../guides/KLAES%20Legal%20Search%20Timeline%20Developer%20ImplementationFlow.md)
- [`LEGAL_SEARCH_RECERTIFICATION_UPDATE.md`](LEGAL_SEARCH_RECERTIFICATION_UPDATE.md)
- [`LEGAL_SEARCH_AND_WEIGHTING_AND_TIMELINE_STUDY.md`](LEGAL_SEARCH_AND_WEIGHTING_AND_TIMELINE_STUDY.md)
- [`kangis-alias/`](kangis-alias/) — MLKN 2455 / CON-AG-2014-35 analysis & automation plan
