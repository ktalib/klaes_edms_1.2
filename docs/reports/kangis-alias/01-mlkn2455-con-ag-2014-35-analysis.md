# 01 — MLKN 2455 ↔ CON-AG-2014-35: Relationship & Search Analysis

> **Scope:** Read-only analysis. No production data was modified. Findings are
> derived from the application code and the query results already confirmed
> against production (the live DB was offline during this pass, so the SQL in
> this document is provided for you to re-run to confirm the row-level facts).

---

## 1. The relationship

`CON-AG-2014-35` is the **mother / original MLS file**. `MLKN 2455` is the
**KANGIS file number** later assigned to the *same physical file* during the
**KANGIS Recertification** exercise. They are two identifiers for one land file.

### ⚠️ CONFIRMED DATA REALITY (2026-07-07) — the alias is NOT co-located

The tables *have* columns that could hold both numbers on one row
(`mlsFNo`/`mls_file_no`, `kangisFileNo`/`kangis_file_no`,
`NewKANGISFileno`/`new_kangis_file_no`). **But for this pair they were never
populated that way.** The production query returned:

| src | mlsFNo | kangisFileNo | NewKANGISFileno | prop_id |
|---|---|---|---|---|
| `pra` | `CON-AG-2014-35` | NULL | NULL | **7530** |
| `CofO_staging` | **`MLKN 2455`** | NULL | NULL | **25896** |

**Root cause — a mapping error at data entry:** `MLKN 2455` (a KANGIS legacy
number) was **saved into the `mlsFNo` column** of `CofO_staging` as though it
were an MLS file number, and given its **own separate `prop_id` (25896)**. The
mother `CON-AG-2014-35` lives under `prop_id 7530`. `kangisFileNo` /
`NewKANGISFileno` are **NULL** on both rows.

Consequences:

- The two numbers are **not co-located on any record**, so there is **no
  `mlsFNo + kangisFileNo` fingerprint** to key an alias off (my initial
  assumption — corrected here).
- `MLKN 2455` behaves like an **independent MLS file** with its own prop_id,
  which is exactly why it renders as a *separate* "Kangis Recertification"
  timeline row instead of resolving into the mother.
- The **only bridge** between them is the `related_file_number` recert link
  (see §1a).

### 1a. Where the link actually lives

Since the co-located columns are empty, the KANGIS↔MLS association is carried by
`related_file_number` (built from `related_fileno` / `NewKANGISFileno` values on
the source tables). Confirm the actual bridge and where the `MLKN 2455` string
appears (read-only):

```sql
-- (i) The recert link row(s) between the two numbers
SELECT 'related_file_number' AS src, id, file_number, related_fileno, prop_id,
       source_table, source_id, transaction_type, comment
FROM   dbo.related_file_number WITH (NOLOCK)
WHERE  UPPER(REPLACE(LTRIM(RTRIM(file_number)),' ','')) IN ('MLKN2455','CON-AG-2014-35')
   OR  UPPER(REPLACE(LTRIM(RTRIM(related_fileno)),' ','')) IN ('MLKN2455','CON-AG-2014-35');

-- (ii) Everywhere 'MLKN 2455' is stored AS AN MLS number (the mis-mapping),
--      and everywhere it appears as a related/KANGIS pointer (the correct linkage)
SELECT 'pra.mlsFNo'  AS where_stored, prop_id, related_file_number, NewKANGISFileno
FROM   dbo.pra WITH (NOLOCK) WHERE UPPER(REPLACE(LTRIM(RTRIM(mlsFNo)),' ',''))='MLKN2455'
UNION ALL
SELECT 'CofO.mlsFNo', prop_id, related_file_number, NewKANGISFileno
FROM   dbo.CofO_staging WITH (NOLOCK) WHERE UPPER(REPLACE(LTRIM(RTRIM(mlsFNo)),' ',''))='MLKN2455'
UNION ALL
SELECT 'FH.mlsFNo', prop_id, related_file_number, NewKANGISFileno
FROM   dbo.file_history_staging WITH (NOLOCK) WHERE UPPER(REPLACE(LTRIM(RTRIM(mlsFNo)),' ',''))='MLKN2455';
```

### ✅ CONFIRMED RESULTS (2026-07-07)

Query (i) — the recert link **exists and is usable**:

| id | file_number | related_fileno | prop_id | source_table | transaction_type |
|---|---|---|---|---|---|
| 7807 | `CON-AG-2014-35` | `MLKN 2455` | NULL | deprecated_records | KANGIS Recertification |
| 7810 | `MLKN 2455` | `CON-AG-2014-35` | NULL | deprecated_records | KANGIS Recertification |
| **8328** | `CON-AG-2014-35` | **`MLKN 02455`** | **7530** | pra | KANGIS Recertification |
| 7808 | `CON-AG-2026-108` | `CON-AG-2014-35` | NULL | deprecated_records | Subdivision |
| 7809 | `CON-AG-2026-109` | `CON-AG-2014-35` | NULL | deprecated_records | Subdivision |
| 1836 | `CON-AG-2026-110` | `CON-AG-2014-35` | NULL | file_indexings | Subdivision |

Query (ii) — the mis-mapping island: `MLKN 2455` sits in **`pra.mlsFNo`** *and*
**`CofO_staging.mlsFNo`**, both under **`prop_id 25896`**.

**Three decisive takeaways:**

1. **The KANGIS↔MLS alias is materialised in `related_file_number`** as
   `KANGIS Recertification` rows (ids 7807 / 7810 / 8328). A query-time resolver
   can therefore map `MLKN 2455 → CON-AG-2014-35` **today, with no data change.**
2. **Zero-padding is real:** the `pra`-sourced link stores **`MLKN 02455`** while
   the `deprecated_records` links store **`MLKN 2455`**. The resolver's
   normalization *must* strip leading zeros per segment (the codebase already
   notes this at [LegalSearchService.php:990,1049](../../app/Services/LegalSearchService.php)).
3. **Row 8328 carries the mother's `prop_id = 7530` directly** — so from a
   KANGIS search the resolver can recover **both** the mother MLS number **and**
   its prop_id from `related_file_number` alone. That prop_id (7530) is the key
   to pulling in the mother's full `pra`/`FH`/`CofO` history, and to bridging past
   the mis-mapped island (25896).

### 1b. Two possible remediation directions

1. **Data correction (root fix):** move `MLKN 2455` out of the `mlsFNo` column
   into the mother's `kangisFileNo`, and re-point its prop_id (25896) to the
   mother's (7530) — turning it into a genuine co-located alias. This is the
   *correct* long-term fix but is a **write** operation (out of scope now) and
   must be done carefully (there may be many such mis-mapped KANGIS numbers).
2. **Resolver over the existing link (query-time, no writes):** treat
   `related_file_number` (recert links) as the alias source and rewrite the
   searched KANGIS number to the mother MLS at query time. Safe now; see §3 and
   `02-automation-plan.md`.

### Also present: the KANGIS-only orphan

Separately, there is a standalone `fileNumber` row for `MLKN 2455`
(`id 107959`, `mlsfNo = NULL`, `kangisFileNo = 'MLKN 2455'`), left by a
decommission that deleted only by `mlsfNo`. It is yet another prop_id and must
never be chosen as canonical. (The `PlotWorkflowService` KANGIS-match fix already
prevents new such orphans.)

---

## 2. How search works today

Entry point: `LegalSearchService::search()`
([app/Services/LegalSearchService.php:25](../../app/Services/LegalSearchService.php)).

1. The searched value (`query`) becomes `$fileNo`.
2. Four source tables are searched:
   `searchFileHistoryStaging`, `searchCofoStaging`, `searchPra`,
   `searchDeedRegistrations`.
3. Each applies `applyFilters(...)`
   ([LegalSearchService.php:1857](../../app/Services/LegalSearchService.php)), which
   matches the file number **EXACTLY** (upper/trim equality) against these
   columns:

   ```php
   ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']
   ```

   The match is exact against these four columns. **Given the confirmed data
   (§1), `MLKN 2455` is stored in `CofO_staging.mlsFNo`** — so the exact match
   fires on the **`mlsFNo`** column, returning that single mis-mapped
   `CofO_staging` row (its own **`prop_id 25896`**). `kangisFileNo` is NULL, so
   nothing matches the mother via the KANGIS column.
4. A prop_id "contamination guard"
   ([LegalSearchService.php:131-253](../../app/Services/LegalSearchService.php))
   keeps rows sharing the searched file's prop_id. Here that prop_id is **25896**
   (the mis-mapped record), **not** the mother's **7530** — so the mother's own
   `pra` history is filtered *out*, not in.

### So what actually happens when searching `MLKN 2455`

Because of the mis-mapping, `MLKN 2455` resolves to an **isolated record**
(prop_id 25896), **not** to the mother `CON-AG-2014-35` (prop_id 7530):

- Only the mis-mapped `CofO_staging` row's own data is returned; the mother's
  full `pra`/`FH` history is **excluded** (different prop_id).
- The mother is only reachable indirectly, as a synthetic **"Kangis
  Recertification"** related row via `related_file_number` — i.e. shown as a
  *separate linked file*, not as the same file.
- **File Information is blank** anyway: every `file_indexings` lookup keys on
  `file_number` / `mls_file_no` (never `kangis_file_no`), and there is no
  indexing row under `MLKN 2455`:
  - active prop_id — [LegalSearchService.php:61-79](../../app/Services/LegalSearchService.php)
  - `search()` File Info — [LegalSearchService.php:340-364](../../app/Services/LegalSearchService.php)
  - `buildPrintReport()` File Info — [LegalSearchService.php:3537-3548](../../app/Services/LegalSearchService.php)
- **The displayed primary number stays `MLKN 2455`** — `$fileNumber = $searchedFileNo`
  ([LegalSearchService.php:3524](../../app/Services/LegalSearchService.php)); the
  `$isKangis` display-append ([:3575-3589](../../app/Services/LegalSearchService.php))
  can't recover the MLS because it relies on `file_indexings.related_fileno`,
  which isn't found.

**Net effect:** searching `MLKN 2455` today behaves like a *different, mostly
empty file* — not like `CON-AG-2014-35`. The two are joined only by the recert
link, precisely because the KANGIS number was mis-stored as an MLS number under a
separate prop_id.

---

## 3. Quickest & safest approach

The clean design is still **canonicalize the searched value once at the search
boundary** — rewrite a KANGIS number to its mother MLS and feed that into the
existing pipeline (which keys on `mlsFNo` / `file_number`). What changes, given
§1, is **where the mapping comes from**: because the numbers are *not*
co-located, the resolver must read the **`related_file_number` recert links**,
not `mlsFNo + kangisFileNo`.

### Resolver contract (corrected)

`resolveCanonicalFileNo(string $searched): array{ canonical: string, alias: ?string }`

Resolution order:
1. **Co-located columns** (works for correctly-mapped files): if `$searched`
   matches a `kangisFileNo`/`kangis_file_no`/`NewKANGISFileno` on a record that
   also has a non-empty MLS number → return that MLS as `canonical`.
2. **`related_file_number` recert link** (needed for `MLKN 2455` and every
   mis-mapped file): if `$searched` is one endpoint of a
   `transaction_type = 'KANGIS Recertification'` link, return the **MLS-formatted
   endpoint** as `canonical` and the KANGIS endpoint as `alias`.
3. Otherwise return `{ canonical: <searched>, alias: null }` — unchanged
   behaviour for ordinary searches.

Guards:
- **Never** resolve to the KANGIS-only orphan (`fileNumber.mlsfNo = NULL`).
- Prefer the endpoint that **looks like an MLS number** (`$isKangis` decides) —
  don't return a KANGIS string as the canonical.

### Where to apply it

At the top of `search()` and `buildPrintReport()`: set `$fileNo = canonical`
for all record/prop_id/file_indexings lookups; keep `alias` for the
`CON-AG-2014-35 (MLKN 2455)` display. Every downstream lookup already keys on the
MLS number, so this one change resolves File Info, Lon/Lat, Location, title,
timeline, and the report to the mother.

> **Caveat introduced by the mis-mapping:** step 2 makes the searched number
> resolve to the mother, but the **mis-mapped `CofO_staging` row still carries
> `mlsFNo = MLKN 2455` under prop_id 25896**. That row won't automatically fold
> into the mother's prop_id (7530). To pull it in, the resolver should also add
> prop_id 25896 to the searched prop-id set (as an allowed alias prop_id), or the
> data must be corrected (§1b option 1). This is the residue of storing a KANGIS
> number as an MLS number.

### Why this is the safest option

- **One centralized change**, not scattered edits to every lookup.
- **Read-only / no data migration** — query-time resolution.
- **No hard-coding** — derives the mapping from `related_file_number` (and
  co-located columns where present), so it covers every recertified file.
- **Fail-open** — unknown numbers pass through unchanged; ordinary MLS/KANGIS-only
  searches don't regress.

### The proper long-term fix (separate, write operation)

The query-time resolver is a **workaround for a data-quality defect**. The root
cause is that `MLKN 2455` was stored as an `mlsFNo` with its own prop_id instead
of as the mother's `kangisFileNo`. Correcting the data (move the KANGIS number to
`kangisFileNo`, re-point prop_id 25896 → 7530) removes the need for the recert-link
detour entirely — but it is out of scope for this read-only pass and likely
affects **many** recertified files, so it needs its own audited migration (see
`02-automation-plan.md` §5–§6).

See `02-automation-plan.md` for the reusable design and
`03-existing-fix-review.md` for the concrete minimal implementation plan.
