# 03 — Existing Implementation Review & Minimal Fix Plan

> What already exists for the KANGIS↔MLS relationship, what is reusable, what is
> still missing, and the smallest change to close the gap. Read-only review; no
> data was modified.

---

## 1. Existing code that already handles the relationship

| Capability | Location | Notes |
|---|---|---|
| Staging search matches KANGIS columns **exactly** | `applyFilters()` file-number columns `['mlsFNo','fileno','kangisFileNo','NewKANGISFileno']` — [LegalSearchService.php:1857](../../app/Services/LegalSearchService.php) (used at :1642, :1703, :1766) | Searching `MLKN 2455` **already returns the mother's `pra`/`CofO`/`FH` rows**. This is the biggest existing asset. |
| Synthetic "KANGIS Recertification" link rows | `fetchRelatedRecertificationRows()` — [LegalSearchService.php:592](../../app/Services/LegalSearchService.php) | Surfaces the KANGIS↔MLS relationship on the timeline; includes reciprocal-pair collapse and the KANGIS-endpoint display pinning ([:862-876](../../app/Services/LegalSearchService.php)). |
| Prop-id contamination guard | [LegalSearchService.php:131-253](../../app/Services/LegalSearchService.php) | Keeps only rows sharing the searched file's prop_id; already treats `kangisFileNo`/`NewKANGISFileno` as identity columns ([:133,:215,:320](../../app/Services/LegalSearchService.php)). |
| KANGIS detection + display append | `$isKangis` and `$fileNumberDisplay` logic — [LegalSearchService.php:3491,:3575-3589](../../app/Services/LegalSearchService.php) | Renders `... (relatedMls)` when searching a KANGIS number — but depends on `file_indexings.related_fileno`, which the KANGIS-keyed lookup misses. |
| Sibling guard (subdivision) | `fetchRelatedRecertificationRows` sibling filter + `isSubdividedUnit()` — [LegalSearchService.php:820-842,:2904](../../app/Services/LegalSearchService.php) | Ensures resolving to the mother does not drag in sibling children. |
| Zero-padding awareness | [LegalSearchService.php:990,:1049](../../app/Services/LegalSearchService.php) | `MLKN 2455` vs `MLKN 02455` already acknowledged. |
| Decommission KANGIS-key fix | `PlotWorkflowService::decommissionFiles()` — now matches `kangisFileNo`/`kangis_file_no` | Prevents future KANGIS-only orphans (already applied). |

---

## 2. Existing database structures that support it

- **Co-located columns** on `pra`, `CofO_staging`, `file_history_staging`,
  `file_indexings`, `fileNumber` — the MLS and KANGIS numbers live on the same
  row (`mlsFNo`+`kangisFileNo`, `mls_file_no`+`kangis_file_no`, `mlsfNo`+
  `kangisFileNo`). **This is the alias mapping, already present.**
- **`related_file_number`** — materialised KANGIS↔MLS recert links
  (`transaction_type = 'KANGIS Recertification'`), built by
  [create_related_file_number_table.sql](../../database/migrations/manual/create_related_file_number_table.sql).
- **`NewKANGISFileno` / `new_kangis_file_no`** — the recert successor pointer.

No new table is strictly required to build the alias map (see
`02-automation-plan.md` §2).

---

## 3. What is reusable

- The **exact-match on `kangisFileNo`** in `applyFilters` — already does the
  heavy lifting for the timeline.
- The **`related_file_number` table** — a ready secondary source of KANGIS↔MLS
  pairs.
- The service's **`norm()`** helper and file-number-column lists — reuse for
  consistent normalization in the resolver.
- The **`$isKangis()`** predicate and the **display-append** logic — reuse to
  render `CON-AG-2014-35 (MLKN 2455)`.

---

## 4. Gaps still to address

1. **`file_indexings` lookups ignore KANGIS columns** — the File Information
   card (title, plot, TP, Lon/Lat, location) is blank on a KANGIS search. Three
   sites:
   - active prop_id — [LegalSearchService.php:61-79](../../app/Services/LegalSearchService.php)
   - `search()` File Info — [LegalSearchService.php:340-364](../../app/Services/LegalSearchService.php)
   - `buildPrintReport()` File Info — [LegalSearchService.php:3537-3548](../../app/Services/LegalSearchService.php)
2. **No canonicalization of the searched number** — `$fileNumber = $searchedFileNo`
   ([:3524](../../app/Services/LegalSearchService.php)) keeps `MLKN 2455` as the
   primary key/label instead of resolving to `CON-AG-2014-35`.
3. **No single reusable resolver** — KANGIS handling is spread across ad-hoc
   spots; other consumers (global modal, APIs, mobile) don't share it.
4. **Data-hygiene orphan** — the KANGIS-only `fileNumber` row (`id 107959`)
   still exists; harmless to search but should be cleaned (see the earlier
   `MLKN 2455` cleanup script). The `PlotWorkflowService` fix stops new ones.

---

## 5. Recommended minimal-change implementation plan

**Step 1 — Add one resolver (new, ~40 lines).**
`app/Services/FileNumberAliasResolver.php`:
`resolveCanonical($searched): { canonical, alias, propId }`. **Primary source:
`related_file_number` recert links** (`transaction_type = 'KANGIS Recertification'`)
— proven to hold `CON-AG-2014-35 ↔ MLKN 2455` and to carry the mother
`prop_id 7530` (row 8328); **fallback:** co-located columns for cleanly-mapped
files. Exclude KANGIS-only orphans; normalize with the shared rules **including
leading-zero stripping** (`MLKN 2455 == MLKN 02455`, confirmed in prod). Cache
per request.

**Step 1b — Bridge the mis-mapped island.** When the resolver returns a
`propId` (e.g. 7530), also fold in the mis-mapped record's prop_id (25896) as an
allowed alias prop_id, so the isolated `pra`/`CofO` rows stored under
`mlsFNo = MLKN 2455` join the mother's timeline. (This is the query-time
workaround; the durable fix is the data correction in `01-...` §1b/§3.)

**Step 2 — Call it once at the two entry points.**
- `search()` — after computing `$fileNo`, do
  `[$fileNo, $aliasKangis] = resolve($fileNo)`; use `$fileNo` (now the MLS) for
  every existing lookup — they already key on `mlsFNo`/`file_number`, so File
  Info, prop_id, timeline, and report all resolve to the mother automatically.
- `buildPrintReport()` — same, and pass `$aliasKangis` into the existing
  `$fileNumberDisplay` block so the header reads `CON-AG-2014-35 (MLKN 2455)`.

**Step 3 — (Optional, even smaller) Belt-and-braces on File Info.** Add
`kangis_file_no` / `new_kangis_file_no` to the three `file_indexings` `where`
clauses so File Info resolves even if Step 1 is bypassed. If Step 1 ships, this
is redundant but cheap insurance.

**Step 4 — Reuse.** Point the global file-number modal and the file-number APIs
at the same resolver so KANGIS aliasing is consistent app-wide.

### Why this is minimal & safe
- **~1 new class + 2 call sites**; no rewrite of the many downstream queries.
- **Fail-open:** unknown/normal numbers pass through unchanged — zero regression
  risk for ordinary MLS or KANGIS-only searches.
- **No production data change** required to make search behave correctly (the
  orphan cleanup is separate and optional).
- **Generic:** works for every recertified file, satisfying the "no hard-coding /
  scales to all files" requirement.

### Acceptance test
`search('MLKN 2455')` ≡ `search('CON-AG-2014-35')` across primary number, title,
Lon/Lat, location, plot/TP, timeline, and printed report — plus the regression
suite in `02-automation-plan.md` §6.
