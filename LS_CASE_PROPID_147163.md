# Legal Search case study — prop_id 147163 (ALHAJI MAGAJI ILU, Wudil Road / Nasarawa)

**Date:** 2026-07-30
**Query:** `SELECT * FROM file_indexings WHERE parent_prop_id = '147163' OR prop_id = '147163'`
**Purpose:** show what a Legal Search returns today for each file number in this family, and what it *should* return.

---

## 1. What the data actually says

### 1.1 The three rows returned by the query

| id | file_number | registry | prop_id | parent_prop_id | has_cofo | has_rofo | related_fileno (JSON) | new_kangis_file_no |
|----|-------------|----------|---------|----------------|----------|----------|------------------------|--------------------|
| 170230 | `COM-2022-519` | Lands (1) | **147163** | 147163 *(self)* | 0 | 1 | `["MLKN 3673","KN2690"]` | KN2690 |
| 170258 | `MLKN 3673` | KANGIS | **147163** *(shared)* | *(null)* | 1 | 0 | `["COM-2022-519","KNML 66","KN2690"]` | KN2690 |
| 170259 | `KN2690` | KANGIS | 147190 | **147163** | 0 | 0 | *(empty)* | *(empty)* |

`MLKN 3673` also carries `kangis_fileno_placeholder = "MLKN 03673"`, `kangis_fileno_resolved = "MLKN 3673"`.
All three share identical `latitude 11.9814041 / longitude 8.5583306`, `plot_size 0.0626`, `land_use COMMERCIAL`, `district WUDIL ROAD`, `lga Nasarawa`, title `ALHAJI MAGAJI ILU` — so they are unambiguously one parcel.

### 1.2 The family extends one generation further back

`MLKN 3673.related_fileno` names **`KNML 66`**, which is a *fourth* indexed file, on a **different prop_id**:

| id | file_number | registry | prop_id | related_fileno | file_title |
|----|-------------|----------|---------|----------------|------------|
| 170210 | `KNML 66` | KANGIS | **147103** | `["COM-2005-616"]` | ALHAJI MAGAJI ILU |

`COM-2005-616` — the 2005-generation land file — **exists nowhere in the system** except as a string inside
`KNML 66.related_fileno` and in `PropID_Master`. It has no `file_indexings` row, no `fileNumber` row, no
transactions.

### 1.3 PropID_Master

| prop_id | primary_file_number | mlsFNo | kangisFileNo | NewKANGISFileno |
|---------|---------------------|--------|--------------|------------------|
| 147103 | COM-2005-616 | COM-2005-616 | `KNML 66` ✔ | — |
| **147163** | COM-2022-519 | COM-2022-519 | **`COM-2022-519`** ✖ | **`MLKN 3673`** ✖ |
| 147190 | KN2690 | — | — | `KN2690` |

Row 147163 is **mis-slotted**: the MLS number sits in the KANGIS column, and an **old** KANGIS number
(`MLKN 3673`) sits in the **new**-KANGIS column. `KN2690`, the actual new-KANGIS number, is absent from
that row entirely. This single defect breaks alias resolution for the whole family (see §4.2).

### 1.4 Transactions on record

| source | id | file no. as stored | transaction | date | prop_id |
|--------|----|--------------------|-------------|------|---------|
| `CofO_staging` | 96241 | `KNML 66` | Certificate of Occupancy | **2005-06-20** | 147103 |
| `pra` | 187085 | `COM-2022-519` | Right of Occupancy | **2022-10-07** | 147163 |
| `CofO_staging` | 96245 | `MLKN 3673` | Certificate of Occupancy | **2022-10-19** | 147163 |

### 1.5 ⚠ `related_file_number` contains **NOTHING** for this family

```sql
SELECT * FROM related_file_number
WHERE file_number   IN ('COM-2022-519','MLKN 3673','MLKN 03673','KN2690','KN 2690','KNML 66')
   OR related_fileno IN (…same…);
-- 0 rows
```

`fetchRelatedRecertificationRows()` is the **only** producer of recertification timeline rows, and it reads
**only** `related_file_number`. It never reads `file_indexings.related_fileno`. Therefore **this family can
never show a recertification event of any kind** — not First, not Second, not Ministry.

### 1.6 A near-miss worth knowing about

`pra` id 80994 is `mlsFNo = "KN 2690"` (**with a space**), prop_id 20812, grantee *MALAMI MUSA*, location
*Indabawa, Kano* — a **different property**, an old-MLS `KN` file. It did not leak into the results, but
`KN2690` vs `KN 2690` differ by one character and the codebase normalises whitespace in several places
(`resolveKangisCanonical()` strips all spaces). Any loosening of number matching will merge these two
unrelated parcels.

---

## 2. What Legal Search returns **today**

### 2.1 Search `COM-2022-519` (the land file)

> Header: `COM-2022-519 (MLKN 3673)` · lifecycle order: `COM-2022-519`

| # | File No. | Instrument / Transaction | Source | Date | Lifecycle block |
|---|----------|--------------------------|--------|------|------------------|
| 1 | COM-2022-519 | Right of Occupancy | PRA | 07-Oct-2022 | COM-2022-519 |
| 2 | MLKN 3673 | Certificate of Occupancy | CofO | 19-Oct-2022 | COM-2022-519 |

### 2.2 Search `MLKN 3673` (old KANGIS)

> Header: `MLKN 3673` — **no land-file pairing** · lifecycle order: `COM-2022-519 > MLKN 3673`

| # | File No. | Instrument / Transaction | Source | Date | Lifecycle block |
|---|----------|--------------------------|--------|------|------------------|
| 1 | COM-2022-519 | Right of Occupancy | PRA | 07-Oct-2022 | COM-2022-519 |
| 2 | MLKN 3673 | Certificate of Occupancy | CofO | 19-Oct-2022 | **MLKN 3673** |

### 2.3 Search `KN2690` (new KANGIS)

> Header: `KN2690` — **no land-file pairing** · lifecycle order: `KN2690 > COM-2022-519 > KNML 66 > MLKN 3673`

| # | File No. | Instrument / Transaction | Source | Date | Lifecycle block |
|---|----------|--------------------------|--------|------|------------------|
| 1 | KNML 66 | Certificate of Occupancy | CofO | 20-Jun-2005 | KNML 66 |
| 2 | COM-2022-519 | Right of Occupancy | PRA | 07-Oct-2022 | COM-2022-519 |
| 3 | MLKN 3673 | Certificate of Occupancy | CofO | 19-Oct-2022 | MLKN 3673 |

### 2.4 The three results disagree

| | search `COM-2022-519` | search `MLKN 3673` | search `KN2690` |
|---|---|---|---|
| Header shows the land file | ✔ | ✖ | ✖ |
| Header shows **both** aliases | ✖ (only MLKN 3673) | ✖ | ✖ |
| 2005 C of O (KNML 66) present | **✖ missing** | **✖ missing** | ✔ |
| Rows grouped under one land lifecycle | ✔ | ✖ (split in 2) | ✖ (split in 4) |
| File Commissioning row | ✖ | ✖ | ✖ |
| Any recertification row | ✖ | ✖ | ✖ |

Three entry points into the **same parcel** produce three different histories. That is the wrong you saw.

---

## 3. How it **should** display

Searching **any** of `COM-2022-519`, `MLKN 3673`, `KN2690`, `KNML 66`, or `COM-2005-616` should return the
**same** report, differing only in which number is highlighted as "searched".

### 3.1 File Information header (identical for all five entry points)

| Field | Value |
|-------|-------|
| **File No.** | `COM-2022-519 (MLKN 3673 / KN2690)` |
| Previous file no. | `COM-2005-616 (KNML 66)` |
| File Title | ALHAJI MAGAJI ILU |
| Land Use | Commercial |
| Plot / District / LGA | PIECE OF · Wudil Road · Nasarawa |
| Size | 0.0626 ha |
| Lon/Lat | 8.5583306, 11.9814041 |
| prop_id | 147163 (parent of 147190; successor to 147103) |

### 3.2 Timeline

Ordering follows `LegalSearchTimelineWeights::MAP` (weight desc, then date asc), with lifecycle blocks
ordered ancestors-first (Rule 11), and `placeKangisRecertBeforeCofo()` pinning each recert directly above
the C of O it produced.

#### Block A — `COM-2005-616` lifecycle *(prop_id 147103)*

| # | File No. | Instrument / Transaction Type | Party 1 (Grantor) | Party 2 (Grantee) | Transaction Date | Reg. No. | Weight | Comment |
|---|----------|-------------------------------|--------------------|--------------------|------------------|----------|--------|---------|
| 1 | COM-2005-616 | File Commissioning | Kano State Ministry of Land and Physical Planning | ALHAJI MAGAJI ILU | 2005 *(year from file no.)* | 0/0/0 | 12 | ⚠ only if `COM-2005-616` is indexed — today it is not (see §4.5) |
| 2 | KNML 66 | **First KANGIS Recertification** | Kano Geographic Information Service | ALHAJI MAGAJI ILU | 2005 | 0/0/0 | 8 | KNML 66 |
| 3 | KNML 66 | Certificate of Occupancy | KANO STATE GOVERNMENT | ALHAJI MAGAJI ILU | **20-Jun-2005** | *(as registered)* | 1 | — |
| 4 | COM-2005-616 | File Decommissioning | — | ALHAJI MAGAJI ILU | – *(blank — back-linkage, Rule 2)* | 0/0/0 | float | Superseded by COM-2022-519 |

#### Block B — `COM-2022-519` lifecycle *(prop_id 147163, current)*

| # | File No. | Instrument / Transaction Type | Party 1 (Grantor) | Party 2 (Grantee) | Transaction Date | Reg. No. | Weight | Comment |
|---|----------|-------------------------------|--------------------|--------------------|------------------|----------|--------|---------|
| 5 | COM-2022-519 | File Commissioning | Kano State Ministry of Land and Physical Planning | ALHAJI MAGAJI ILU | 2022 *(year from file no.)* | 0/0/0 | 12 | — |
| 6 | COM-2022-519 | Right of Occupancy | KANO STATE GOVERNMENT | ALHAJI MAGAJI ILU | **07-Oct-2022** | *(as registered)* | 9 | — |
| 7 | KN2690 | **Second KANGIS Recertification** | Kano Geographic Information Service | ALHAJI MAGAJI ILU | 2026 | 0/0/0 | 8 | KN2690 |
| 8 | MLKN 3673 | **First KANGIS Recertification** | Kano Geographic Information Service | ALHAJI MAGAJI ILU | 2022 | 0/0/0 | 8 | MLKN 3673 |
| 9 | MLKN 3673 | Certificate of Occupancy | KANO STATE GOVERNMENT | ALHAJI MAGAJI ILU | **19-Oct-2022** | *(as registered)* | 1 | — |

**Note on rows 7–8.** Both recertifications carry weight 8, so within the band they sort by date — 2022
before 2026. But `placeKangisRecertBeforeCofo()` then pulls the MLKN 3673 recert *down* so it sits directly
above its own C of O (row 9), which leaves the **Second** recertification printing above the **First**.
That is what the current rules produce. If the registry wants First-then-Second always, the adjacency rule
needs a tie-break on recertification generation — **please confirm the intended order before this is coded.**

**Note on the 2005 → 2022 transition.** There is no `decommissioned_files` row for `COM-2005-616`, so row 4
cannot be generated today. Either the decommissioning is backfilled, or Block A must be presented purely as
inherited history with no decommissioning line.

---

## 4. Why it doesn't display that way — root causes

| # | Symptom | Root cause | Location |
|---|---------|-----------|----------|
| 4.1 | **No recertification row anywhere** | `fetchRelatedRecertificationRows()` reads only `related_file_number`, which has **0 rows** for this family. The linkage exists only in `file_indexings.related_fileno`, which is never consulted as a recert source. | `LegalSearchService.php:1049` |
| 4.2 | **Searching `MLKN 3673` does not resolve to `COM-2022-519`** | `resolveKangisCanonical()` looks up `PropID_Master.kangisFileNo`. For prop_id 147163 that column holds `"COM-2022-519"`, not `"MLKN 3673"` — the alias is mis-slotted into `NewKANGISFileno`, which the lookup never reads. Its fallback (a `%Recertification%` link) also fails, because §4.1. | `LegalSearchService.php:6258-6286` + `PropID_Master` id 150233 |
| 4.3 | **Searching `KN2690` can never resolve to its land file** | `resolveKangisCanonical()`'s guard regex is `^(MLKN\|KNML\|KNGP)\s?\d{1,6}$`. **New-KANGIS `KN…` numbers are not accepted at all**, so the function returns `null` immediately for every Second-Recertification file in the system. | `LegalSearchService.php:6240` |
| 4.4 | **`KNML 66` can never be labelled a KANGIS recert** | `identifyFileNumberType()` uses `^[A-Z]{4}\s?\d{3,6}$` — `KNML 66` has only **2** digits, so it returns `'unknown'` and `isKangisFormat()` is false. This is the defect quantified in the main report (79 short-number links + 130 unit-suffixed links = 209 affected). | `LegalSearchService.php:4461` |
| 4.5 | **No File Commissioning row** | `fileNumber.SOURCE` is `'indexing'`, not `'MLS_Commissioned…'`, so `resolveCommissioningInfo()` returns `'-'`. The fallback rule (show the year embedded in the file number) is implemented only in `buildPrintReport()`/JS, not in `search()`'s payload. Separately, `COM-2005-616` has no `file_indexings` row at all, so the `is_indexed` gate suppresses its commissioning row. | `LegalSearchService.php:2411`, `:5413` |
| 4.6 | **`KNML 66` appears only on the `KN2690` search** | It is reached through prop_id/SME expansion off `KN2690`'s `parent_prop_id` chain. The `COM-2022-519` and `MLKN 3673` searches never walk to prop_id 147103 because nothing links 147163 → 147103 except a JSON string inside `MLKN 3673.related_fileno`. | `resolveAncestorPropIds()` `:6441` |
| 4.7 | **Header shows at most one alias** | `resolveFileNumberDisplay()` emits a single `MAIN (ALIAS)` pair. A file with both an old and a new KANGIS number has no representation. | `LegalSearchService.php:6099` |
| 4.8 | **Lifecycle splits into 2–4 blocks** | `tagRowsWithLifecycleFileNo()` folds a KANGIS row into its owner only when the `kangis → land` map is populated — and that map is seeded from the header hint (broken, §4.2/4.7) and from recert rows (absent, §4.1). With neither, every KANGIS number becomes its own lifecycle block. | `LegalSearchService.php:6868` |

---

## 5. What to fix, in order

| # | Action | Type | Effect on this case |
|---|--------|------|---------------------|
| 1 | Correct `PropID_Master` id 150233: `kangisFileNo = 'MLKN 3673'`, `NewKANGISFileno = 'KN2690'` | **data** | `MLKN 3673` search resolves to the land file; header pairing works |
| 2 | Create the missing `related_file_number` links: `COM-2022-519 ⇄ MLKN 3673` (KANGIS Recertification), `COM-2022-519 ⇄ KN2690` (KANGIS Recertification), `COM-2005-616 ⇄ KNML 66` (KANGIS Recertification), `COM-2005-616 ⇄ COM-2022-519` (Recertification / successor) | **data** | rows 2, 7, 8 appear; lifecycle blocks merge |
| 3 | Widen `identifyFileNumberType()` KANGIS regexes: legacy `^(MLKN\|KNML\|KNGP)\s?\d{1,6}([-_]\d{1,3})?$`, new `^KN[\s-]?\d{2,6}$` | code | `KNML 66` becomes a recognised KANGIS file |
| 4 | Let `resolveKangisCanonical()` accept `KN…` numbers, and search `NewKANGISFileno` as well as `kangisFileNo` | code | `KN2690` search resolves to the land file |
| 5 | Make `fetchRelatedRecertificationRows()` fall back to `file_indexings.related_fileno` when `related_file_number` has no link | code | families indexed but never link-backfilled stop showing empty histories |
| 6 | Extend `resolveFileNumberDisplay()` to render `MAIN (OLD / NEW)` when both aliases exist | code | header becomes `COM-2022-519 (MLKN 3673 / KN2690)` |
| 7 | Backfill the `COM-2005-616 → COM-2022-519` decommissioning | **data** | row 4 appears; generation boundary becomes explicit |

Items 1, 2 and 7 are **data repairs and will not be fixed by code changes**. Given that 4,862 KANGIS
recertification links exist system-wide while this family has none, it is worth running a reconciliation
between `file_indexings.related_fileno` and `related_file_number` to find out how many other families are
in the same state.

---

## 6. Questions for the registry before coding

1. **Is `KNML 66` / `COM-2005-616` genuinely the same parcel** as `MLKN 3673` / `COM-2022-519`? The only
   evidence is a JSON back-link plus matching owner name and coordinates; the prop_ids differ (147103 vs
   147163). If yes, the two prop_ids need an explicit lineage link.
2. **First-before-Second recertification ordering** — see the note under §3.2.
3. **`MLKN 3673` shares prop_id 147163 with the land file**, while `KN2690` got its own 147190 with
   `parent_prop_id`. Which is the intended pattern? The documented decision (Option A) says each of
   Land / Old KANGIS / New KANGIS gets its own prop_id and points up via `parent_prop_id` — `MLKN 3673`
   does not follow that.
