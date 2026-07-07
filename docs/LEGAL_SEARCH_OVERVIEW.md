# Legal Search — General Knowledge & Architecture

**Area:** Legal Search (LS)
**Primary code:** [`app/Services/LegalSearchService.php`](../app/Services/LegalSearchService.php)
**DB connection:** `sqlsrv` (SQL Server, database `klas`) — production lives at `10.50.1.1`.

> This is a general reference for how Legal Search works end-to-end. For the specific
> recertification-display fixes, see
> [`reports/LEGAL_SEARCH_RECERTIFICATION_UPDATE.md`](reports/LEGAL_SEARCH_RECERTIFICATION_UPDATE.md).

---

## 1. What Legal Search is

Legal Search answers the question *"what is the complete transaction history of this piece of
land?"* Given a file number (or a set of property attributes), it assembles a single **chronological
timeline** of every registered transaction on that property — regardless of which file number the
property was known by at the time, or which underlying table the transaction was recorded in.

It powers two deliverables:

| Entry point | Method | Produces |
|-------------|--------|----------|
| On-screen search / timeline | `search(array $params)` | The `transactions` array + file-information header used by the LS UI. |
| Official / Online / Pay-per-search printout | `buildPrintReport(array $q)` | A print-ready payload (`rows`, parties, dates, notices) for the three HTML templates. |

Both read the same sources and share the same lineage logic; `buildPrintReport()` additionally
collapses and formats rows for the printed report.

---

## 2. The property model — `prop_id` is the spine

The single most important concept in LS: **a property is identified by its `prop_id` (the physical
parcel), not by its file number.** File numbers change over a parcel's life; the `prop_id` is what
ties the history together.

- **`prop_id`** = one physical parcel. A file's transactions all carry the parcel's `prop_id`.
- **`parent_prop_id`** = the parcel(s) a file descends from. May be a **CSV** (e.g. a merger whose
  sources were several parcels). Used to walk lineage upward.
- **Change of Purpose / Change of Name** = a *rename* → the successor keeps the **same** `prop_id`.
- **Subdivision / Separation** = a *split* → each child gets a **new** `prop_id`,
  with `parent_prop_id` pointing back at the mother.
- **Merger** = *many → one* → the survivor records the sources in `parent_prop_id`.

> See the memory note *"prop_id is per-parcel, not per-file"* — `PropID_Master` has fewer rows than
> file numbers precisely because a rename reuses the parcel's `prop_id`.

This model drives the two behaviors that make LS more than a `WHERE file_number = ?` query:
**prop_id cross-table expansion** (§4) and the **contamination guard** (§5).

---

## 3. The four primary source tables

`search()` queries four transaction tables in parallel, each through its own method, then normalizes
every row to a common shape via `normalizeRow()`:

| Table | Method | What it holds |
|-------|--------|---------------|
| `file_history_staging` | `searchFileHistoryStaging()` | Digitised historical file-history entries. |
| `CofO_staging` | `searchCofoStaging()` | Certificate of Occupancy registrations. |
| `pra` | `searchPra()` | Property Registration / transaction records (the richest source — mergers, subdivisions, change-of-purpose, deeds, etc.). Carries `prop_id` **and** `parent_prop_id`. |
| `deed_registrations` | `searchDeedRegistrations()` | Deed registrations (CofO rows are excluded here to avoid double-counting — `excludeCofoFromDeedRegistrations()`). |

All four support the same attribute filters (`applyFilters()`): grantor/grantee name, LGA, district,
location, plot number, plan number, size, caveat.

Two further sources feed **synthetic** rows (§6): `related_file_number` (recertification / lineage
links) and `decommissioned_files` (successor/predecessor markers).

---

## 4. The search pipeline (`search()`)

1. **Validate input.** No criteria → `emptyResult()`.
2. **SME allow-list.** `getSmeAllowedFileNos()` resolves legitimately-linked sibling files (e.g. a
   merger's source files) so they survive later filtering. When an SME search is in play, the
   prop_id expansion is **bypassed** (the allow-list defines the scope instead).
3. **Query the four primary tables** with the attribute filters.
4. **Collect active prop_ids** for the searched file from `file_indexings` (`prop_id` +
   `parent_prop_id`) and `fileNumber` (`parent_prop_id`), plus `parent_prop_id` off any `pra`
   merger rows.
5. **prop_id cross-table expansion.** `collectPropIds()` gathers every prop_id seen so far, then
   `searchByPropIds()` re-queries all four tables for *other* rows sharing those prop_ids (excluding
   already-fetched ids via `buildExistingIdMap()`). This is how a search on one file number surfaces
   transactions filed under the property's *other* numbers.
6. **Merge** the four result sets into `$all`.
7. **Contamination guard** (§5).
8. **Decommission redirect** (§5).
9. **Append synthetic rows** — recertification/lineage (`fetchRelatedRecertificationRows()`) and
   decommission lineage (`fetchDecommissionLineageRows()`).
10. **Sort chronologically** by `sort_date` (undated rows sort last), then apply any saved manual
    arrangement (`applyArrangementOrder()`).
11. **Resolve the file-information header** — title, location, district, LGA, land use, plot/TP no,
    aggregate size (§7), temporary `(T)` number, commissioning date/number, DCIV investigation flag,
    and the previous/current/successor `lineage`.
12. **Return** the structured result (§8).

---

## 5. Two guards that keep the timeline honest

### Contamination guard (cross-property leak protection)

prop_id expansion and recert/SME links can drag in rows belonging to a **different** parcel (e.g. a
KANGIS recertification tying file A on parcel X to file B on parcel Y). After merging, LS:

1. Determines the prop_id(s) that genuinely belong to the searched file (from rows whose file-number
   columns actually match the search, plus their `parent_prop_id`).
2. Drops any transaction row whose `prop_id` differs — **except**:
   - synthetic `'Related Fileno'` rows (lineage markers, always preserved),
   - SME allow-listed siblings,
   - merger parents (`parent_prop_id`).
3. Only filters when the searched file resolves to a definite prop_id; otherwise leaves rows intact.

For subdivided units it additionally keeps only the searched unit + its mother, excluding *other*
sibling units (`isSubdividedUnit()`).

### Decommission redirect

If the directly-searched file was itself decommissioned/superseded (`getDecommissionedFileNumbers()`):

- **1:1 rename** (Change of Purpose/Name, recert, amendment) → the successor inherits the **same**
  prop_id and already carries the full history, so LS **drops the searched (old) number's own rows**
  and effectively redirects to the successor's records.
- **Split/Merge** (Subdivision / Merger / Separation) → successors have **new** prop_ids, so the old
  file's history isn't represented elsewhere — LS **keeps** it.
- `false_decommissioning = 1` rows are Title-Status flags, not real decommissions, and are excluded.
- Synthetic `'Related Fileno'` rows always survive.

---

## 6. Synthetic rows

Beyond real transaction rows, LS appends two kinds of derived rows, both tagged
`source_table = 'Related Fileno'` (recerts/lineage) or the decommission lineage marker:

### `fetchRelatedRecertificationRows()` — the `related_file_number` staging table

Emits the orange **"Related Fileno"** timeline rows for recertifications, subdivisions, mergers,
change-of-purpose and generic links harvested into `related_file_number`. Key behaviors:

- **Candidate match** — a link is pulled in when either endpoint (`file_number` / `related_fileno`)
  matches the searched file *or any file number already resolved into the result set* (expanded via
  `fileNumberVariants()`), or when `rfn.prop_id` matches. Because recert rows usually have an empty
  `prop_id`, candidates are derived from the property's own numbers → no cross-property leak.
- **Counterpart display** — normally shows the side of the link that is *not* the searched file.
- **KANGIS pin** — a `KANGIS Recertification` always displays the KANGIS-format endpoint
  (`KNML`/`MLKN`/`KNGP`/`KN <n>`), never the MLS counterpart.
- **Second hop** — after the first match, the recert links of any newly-discovered *ancestor* are
  pulled in (one level, recert-types only), so a recert belonging to a mother surfaces when you
  search a grandchild.
- **Date borrowing** — these links have no date of their own (only a link-creation timestamp), so
  the date is borrowed from the linked file's `pra` history (type-matched), else the family's most
  recent real transaction date, else the link-creation timestamp.
- **De-dup / collapse** — reciprocal pairs (`A→B` and `B→A`) and zero-padding variants
  (`MLKN 2455` vs `MLKN 02455`) collapse to a single displayed row.

> `related_file_number` is a **rebuilt staging table**, not live. It is dropped and repopulated by
> [`database/migrations/manual/create_related_file_number_table.sql`](../database/migrations/manual/create_related_file_number_table.sql)
> from `file_indexings`, `deprecated_records`, `pra`, and `pic`. If a link exists in a source but
> not on LS, the staging table is **stale** and needs a rebuild.

### `fetchDecommissionLineageRows()` — the `decommissioned_files` table

Adds lineage markers for files that were decommissioned/superseded, so the chain (predecessor →
current → successor) is visible on the timeline.

---

## 7. File numbers, variants & size weighting

- **`identifyFileNumberType()`** classifies a string (MLS `CON-COM-YYYY-N`, KANGIS `MLKN`/`KNML`/
  `KNGP`, old MLS `KN <n>`, temporary `(T)`, DCIV/LPCC, etc.).
- **`fileNumberVariants()`** expands a number into its base and `(T)` temporary forms so matching
  survives format drift.
- **`isSubdividedUnit()`** detects a subdivided-unit number and can resolve its mother.
- **Aggregate `file_size`** is chosen by source weighting: `CofO_staging` (4) > `file_history_staging`
  (3) > `pra` (2) > `deed_registrations` (1); falls back to a direct `pra.plot_size` lookup by prop_id.

---

## 8. Output shape (`search()` return)

```
transactions              // the chronological timeline (array of normalized rows)
under_investigation       // DCIV flag (bool) + investigation_note / _reason / _dciv_file_number
file_title                // resolved from file_indexings, else fileNumber.FileName
file_location / _district / _lga / _land_use / _plot_number / _tp_no / _size
file_related_fileno       // raw related_fileno string on the indexing record
file_index_number         // the file_indexings.file_number that matched
file_temp_number          // the "(T)" number tied to this file, if any
file_history_count / cofo_count / pra_count / deed_count / total_count
file_commissioning_date   // + file_commissioned_number (which number was commissioned)
lineage                   // previous / current / successor chain (resolveFileLineage())
```

Each row in `transactions` is normalized to a common set of columns (`fileno`, `mlsFNo`,
`kangisFileNo`, `transaction_type`, `transaction_date`, `sort_date`, `party_1..4`, `location`,
`prop_id`, `parent_prop_id`, `source_table`, caveat fields, etc.).

---

## 9. Timeline management (Cleanup mode)

The LS UI exposes editing actions that map to service methods (all table-name-validated via
`validateTable()`):

| Action | Method | Effect |
|--------|--------|--------|
| Match | `matchRecords()` | Assign a shared `prop_id` to selected rows (links them to a property). |
| Drop / Remove | `dropRecords()` / `removeRecords()` | Hide/soft-remove rows from the timeline. |
| Edit | `updateRecord()` | Edit a single record's fields. |
| Caveat transfer | `transferCaveat()` | Move a caveat between records/tables. |
| Arrange | `saveTimelineArrangement()` / `getTimelineArrangement()` | Persist a manual row order per prop_id. |

`detectPropIdConflicts()` warns when a Match would collide with existing prop_id assignments.

---

## 10. Report notices (`buildPrintReport()`)

- **Caveat** — surfaced from the caveat fields on matching rows.
- **DCIV / Under Investigation** — `resolveDcivInvestigation()` flags files tied to a DCIV via
  `master_dciv_links`.
- **W/R/C & CofO status** — from `duplicate_fileno` comment tags (`[WRC]`, `[COFO_READY]`,
  `[COFO_COLLECTED]`); category is read from the comment prefix, matched by file variants or prop_id.
  `[DUPLICATE]` / `[TEMPORARY]` are intentionally not surfaced.

---

## 11. Operational notes

- **All investigative DB probes against production (`10.50.1.1`) must be READ-ONLY.** It is the live
  registry. Use `php artisan tinker --execute` for `SELECT`-only checks; `php -l` for lint.
- **Staging dependency.** If recert/lineage links don't appear, the culprit is usually a stale
  `related_file_number` — rebuild it (`create_related_file_number_table.sql`) then re-run the
  transaction-type classification.
- **Local vs live.** The `sqlsrv` connection is switched via `.env`. Data present on live may be
  absent locally (e.g. newly-created Change-of-Purpose records) — confirm the connection target
  (`config('database.connections.sqlsrv.host')`) before concluding a file "doesn't exist."

---

## 12. Table glossary

| Table | Role |
|-------|------|
| `file_indexings` | Active file index — `prop_id`, `parent_prop_id`, title, location, `related_fileno`, `(T)` linkage. |
| `fileNumber` | File-number registry — `mlsfNo`, `kangisFileNo`, `NewKANGISFileno`, `FileName`, `parent_prop_id`. |
| `pra` | Property Registration / transactions (primary source; has `parent_prop_id`). |
| `pic` | Companion of `pra`; also a `related_file_number` source. |
| `CofO_staging` / `file_history_staging` / `deed_registrations` | Other primary transaction sources. |
| `related_file_number` | Rebuilt staging table of inter-file links (recert / subdivision / merger / change-of-purpose). |
| `decommissioned_files` | Superseded files + successor pointers (lineage + redirect). |
| `deprecated_records` | Archived file records — title/holder fallback + a `related_file_number` source. |
| `manual_file_linkages` | Manual backfill of merger/subdivision sources + applicant name (last-resort party fallback). |
| `duplicate_fileno` | W/R/C and CofO status tags for report notices. |
| `master_dciv_links` | DCIV ↔ file links for the Under-Investigation flag. |
| `PropID_Master` | Canonical parcel registry (one row per parcel). |

---

## 13. Related documents

- [`reports/LEGAL_SEARCH_RECERTIFICATION_UPDATE.md`](reports/LEGAL_SEARCH_RECERTIFICATION_UPDATE.md) — recertification display fixes + worked lineage example.
- [`LS-WEIGHTING METHOD.md`](LS-WEIGHTING%20METHOD.md) — the source-weighting method for aggregate values.
- [`database/migrations/manual/create_related_file_number_table.sql`](../database/migrations/manual/create_related_file_number_table.sql) — staging-table rebuild + classification.
