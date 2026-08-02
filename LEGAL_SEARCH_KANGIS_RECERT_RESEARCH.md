# Legal Search (LS) — Architecture Research & KANGIS Recertification Linkage

**Date:** 2026-07-30
**Scope:** `app/Services/LegalSearchService.php` (8,155 lines), `app/Http/Controllers/LegalSearchController.php`, `resources/views/legal_search/js.blade.php` (7,408 lines), `related_file_number` register, and the KANGIS recertification linkage chain.
**Method:** code walk + live queries against the `sqlsrv` production connection.

---

## 1. The three parallel LS pipelines

This is the single most important structural fact about Legal Search: **the same timeline is built three separate times, by three separate implementations.**

| # | Pipeline | Entry point | Grouping / ordering done by |
|---|----------|-------------|------------------------------|
| 1 | **On-screen timeline** | `LegalSearchService::search()` (line 26) | Returns a *flat* row list + `lifecycle_meta` + `lifecycle_order`. Grouping, synthetic commissioning rows and weighting are re-done **in JavaScript**. |
| 2 | **Print / report slip** | `LegalSearchService::buildPrintReport()` (line 4527) | Server-side: `groupTimelineByLifecycle()` → `ensureLifecycleSyntheticRows()` → `dedupeLifecycleRows()` → `arrangeLifecycleFileRows()`. |
| 3 | **Browser mirror of #2** | `resources/views/legal_search/js.blade.php` ~3450–4450 | Hand-ported copies of `placeKangisRecertBeforeCofo`, `classifyLifecycleEventType`, `dedupeLifecycleRows`, `isRecertLandFile`, Rule 4 hoisting. |

Only the *weights* are shared, via `App\Support\LegalSearchTimelineWeights` (PHP const `MAP`, injected into JS with `@json(...)`). Everything else is duplicated logic that must be kept in sync by hand.

> **Consequence:** whenever the on-screen timeline and the printed report disagree, the cause is almost always that a fix landed in one of these three and not the other two. Section 4 documents a live instance of exactly this.

### Weight table (`LegalSearchTimelineWeights::MAP`)

```
OCCUPANCY_PERMIT         14      PARCEL_UPDATE          null (floats)
TRANSFER_OF_TITLE_OP     13      TITLE_STATUS_UPDATE    null (floats)
FILE_COMMISSIONING       12      FILE_DECOMMISSIONING   null (floats)
TEMP_FILE_COMMISSIONING  12      DCIV_COMMISSIONING     null (floats)
RIGHT_OF_OCCUPANCY        9
KANGIS_RECERTIFICATION    8      <-- keeps a recert above the C of O it produced
CERTIFICATE_OF_OCCUPANCY  1
OTHER_INSTRUMENTS         1
```

Note the drift from the spec in `resources/views/legal_search/KLAES Legal Search Timeline.md`: the spec says OP=11, ToT=10, CofO=0, OTHER=0. The code has since promoted OP/ToT above File Commissioning (14/13) and lifted CofO/OTHER off zero (1). The spec document is **stale** and should not be used as the reference.

---

## 2. Data flow of `search()`

```
input query
  │
  ├─ resolveKangisCanonical()        KANGIS alias ("MLKN 2455") → mother MLS number,
  │                                  so the whole search runs as if the MLS no. was typed
  ├─ getSmeAllowedFileNos()          SME family whitelist (bypasses prop_id expansion)
  ├─ resolveStRelatedFileNos()       ST scheme + unit numbers (separate channel)
  │
  ├─ 4 base queries: file_history_staging, CofO_staging, pra, deed_registrations
  ├─ prop_id cross-table expansion        (file_indexings.prop_id / parent_prop_id, fileNumber, pra)
  ├─ resolveAncestorPropIds() expansion   (mother-file inherited history)
  │
  ├─ CONTAMINATION GUARD  (lines 198–372)
  │     keeps rows whose prop_id belongs to the searched file;
  │     exemptions: 'Related Fileno' synthetics, SME set, ST set,
  │                 OP/ToT shared prop_ids, PropID_Master aliases
  │
  ├─ fetchRelatedRecertificationRows()   ← KANGIS / Ministry recert linkage  (§3)
  ├─ fetchDcivCommissioningRows()        ← DCIV / LPCC master links
  ├─ fetchStCommissioningRows()          ← ST scheme + fragmentation rows
  ├─ fetchDecommissionLineageRows()      ← predecessor decommission events
  │
  ├─ subdivided-unit filter, decommissioned-file redirect
  ├─ usort by sort_date, applyArrangementOrder() (manual saved order)
  ├─ party_1 normalisation for recert / CoP / subdivision rows   ← BUG, see §4.1
  ├─ File Information resolution (title, size, LGA, term, lon/lat, DCIV flag, WRC flag)
  ├─ resolveFileNumberDisplay()          "SEARCHED (LINKED)" header
  ├─ suppressRedundantRelatedFileRows()  ← drops neutral link rows   ← BUG amplifier, §4.2
  └─ tagRowsWithLifecycleFileNo() + orderLifecycleFiles()
```

---

## 3. KANGIS recertification linkage — how it actually works

### 3.1 Storage

All linkage lives in **`related_file_number`** (7,844 rows live). Each row is one undirected edge:

| column | meaning |
|--------|---------|
| `file_number` | "parent" endpoint |
| `related_fileno` | counterpart endpoint (may be a **CSV / JSON list** — see `parseRelatedFileno()`) |
| `transaction_type` | edge type |
| `prop_id` | usually **empty** on recert rows |
| `comment`, `party_2`, `location`, `file_title`, `source_table` | display fallbacks |

**Live type distribution:**

```
KANGIS Recertification                                4,862
NULL (untyped)                                          991
Ministry of Land & Physical Planning Recertification    852
Merger                                                  796
Subdivision                                             332
Change of Purpose                                        11
```

Links are written by `MlsFileNoController` (the `-RC` recertification branch, line 2774+), `ManualFileLinkageController`, `DcivGenerationController` and the register rebuild. There is **no** single service owning writes — the classification rule (`^KN[\s-]?\d` ⇒ Ministry, else KANGIS) is duplicated at each write site.

### 3.2 Reading — `fetchRelatedRecertificationRows()` (line 1049)

Because recert rows carry no `prop_id`, they are only reachable by **string matching a file number**. The function therefore:

1. Builds `$candidates` = the searched number **plus every number column of every row already in the result set**, each expanded by `fileNumberVariants()` (base / `(T)` forms).
2. Queries `related_file_number` where either endpoint ∈ candidates, or `prop_id` ∈ result-set prop_ids.
3. **Second hop** (lines 1168–1222): collects endpoints just discovered that are *not* searched candidates (i.e. ancestors) and pulls **only their `%Recertification%` links**. This is what makes a grandmother's KANGIS recert visible from a Change-of-Purpose grandchild.
4. **Sibling guard** (line 1309): a non-recert link whose two endpoints are both *other* files is dropped. Recert links are deliberately exempt.
5. **KANGIS-side pinning** (line 1349): when `transaction_type` contains "KANGIS", the displayed endpoint is forced to the KANGIS-format side, so both stored directions of a pair resolve to the same number.
6. **Date borrowing**: a link has no transaction date of its own, only `created_at`. Preference order is
   `matching-type pra date` → `family max sort_date` → `created_at`; Manual-Linkage endpoints are forced to `'-'`.
7. **Reciprocal collapse** + zero-pad canonicalisation ("MLKN 02455" ⇄ "MLKN 2455") + visual dedupe.
8. Party fallbacks: `file_indexings` → `deprecated_records` → `manual_file_linkages.applicant_name`.

### 3.3 Labelling — `recertDisplayLabel()` (line 7080)

```
stored contains "physical planning" | "ministry" | "Land Recertification"
        → "Land Recertification (File Commissioning)"
stored contains "recertification"
        → if the linked number is NOT KANGIS-format  → "Related File"     (neutral)
          else new_kangis (KN\d+)                     → "Second KANGIS Recertification"
          else                                        → "First KANGIS Recertification"
otherwise → stored type unchanged
```

First-vs-Second is decided by the **linked KANGIS file's own number format**, not by the searched file. Old `KNML/MLKN/KNGP` = the 2014–2024 exercise; new `KN…` = 2025–present.

### 3.4 Format detection — `identifyFileNumberType()` (line 4442) — **the weak link**

```php
'st'         ^ST-(RES|COM|IND|AG)-\d{4}-\d+-\d+$
'parent'     ^ST-(RES|COM|IND|AG)-\d{4}-\d+$
'mls'        ^(COM|RES|IND|AG|CON-…)-\d{4}-\d+$  or  -\d+$
'kangis'     ^[A-Z]{4}\s?\d{3,6}$
'new_kangis' ^KN\d{2,6}$
'unknown'    everything else
```

`isKangisFormat()` = `kangis || new_kangis`, and it gates **almost every KANGIS behaviour**: the recert label, `extractKangisEndpoint()`, `extractKangisLifecycleKey()`, `aliasHintsFromDisplay()`, `tagRowsWithLifecycleFileNo()` alias folding, and the "a KANGIS file never gets a File Commissioning row" suppression at line 5468.

### 3.5 Lifecycle grouping

`tagRowsWithLifecycleFileNo()` (line 6868) builds a `KANGIS alias → owning land file` map, seeded by:
- `aliasHintsFromDisplay()` — parses the `"MAIN (KANGIS)"` header string; these keys are **locked**;
- `resolveAliasHintOwners()` — repoints a hint to the true owner when the alias actually pairs with an ancestor rather than the searched file;
- any row carrying both a KANGIS and a land number (recert rows win over weaker pairings);
- `cofoLifecycleByKangis` — anchors a KANGIS key to the lifecycle of its C of O row.

Then `placeKangisRecertBeforeCofo()` forces each recert immediately above its matching KANGIS C of O within the same lifecycle band, and `arrangeLifecycleFileRows()` hoists `Land Recertification (File Commissioning)` rows directly under File Commissioning (Rule 4).

---

## 4. Defects found (all verified against live data)

### 4.1 🔴 Ministry recertifications are attributed to KANGIS on screen

**Where:** `LegalSearchService.php:486–495` vs `LegalSearchService.php:7086`

`fetchRelatedRecertificationRows()` runs first (line 376) and rewrites `transaction_type` to
`"Land Recertification (File Commissioning)"` for Ministry recerts. The party-normalisation loop at
line 486 then tests the **rewritten** label:

```php
if (str_contains($_type, 'recertification')) {
    $_row['party_1'] = (str_contains($_type, 'ministry') || str_contains($_type, 'physical planning'))
        ? 'Kano State Ministry of Land and Physical Planning'
        : 'Kano Geographic Information Service';
}
```

`"land recertification (file commissioning)"` contains neither `ministry` nor `physical planning`, so
**every one of the 852 Ministry recertification rows is stamped "Kano Geographic Information Service."**

**Live proof — `COM-RC-1982-19`:**

```
KN 3232 | Land Recertification (File Commissioning) | Related Fileno | p1=Kano Geographic Information Service
```

The print report does **not** have this bug — `makePrintMinistryRecertRow()` (line 7224) hardcodes
`'grantor' => 'Kano State Ministry of Land and Physical Planning'`. So the slip and the screen
contradict each other on the same row.

**Fix:** test the *stored* type before relabelling, or add `'land recertification'` to the ministry
condition at line 489.

---

### 4.2 🔴 KANGIS recertifications silently vanish when the KANGIS number has a unit suffix or <3 digits

**Where:** `identifyFileNumberType()` regex `^[A-Z]{4}\s?\d{3,6}$`

Two real, common KANGIS number shapes fail this regex:

| shape | example | count in recert links |
|-------|---------|-----------------------|
| unit suffix `-N` / `_N` | `MLKN 2280-1`, `KNML 3855_3`, `MLKN 219_3` | **130** |
| fewer than 3 digits | `MLKN 42`, `MLKN 43`, `KNML 1` | **79** |
| | **total** | **209** |

Failure cascade:

```
identifyFileNumberType('MLKN 42') = 'unknown'
  → isKangisFormat()             = false
  → recertDisplayLabel()          returns 'Related File'   (neutral)
  → suppressRedundantRelatedFileRows()  DROPS the row entirely
     (because MLKN 42 already has its own transactions in the timeline)
  → the recertification event never appears
```

**Live proof — `CON-COM-2012-162`** (linked to both `MLKN 901` and `MLKN 42`):

```
MLKN 42          | Right of Occupancy            | PRA            | Jun 4, 2012
MLKN 901         | First KANGIS Recertification  | Related Fileno | Jan 28, 2015
```

`MLKN 901` gets its recert row; `MLKN 42` — an equally valid KANGIS recertification link — has **no
recert row at all**. Its transactions appear, but the event that ties it to this land file is gone.

The same regex also breaks:
- `extractKangisLifecycleKey()` → suffixed KANGIS rows cannot be grouped or deduped by KANGIS key;
- the line 5468 guard → a suffixed KANGIS number can wrongly receive a synthetic "File Commissioning" row;
- `resolveKangisCanonical()` uses a **different**, more permissive regex (`^(MLKN|KNML|KNGP)\s?\d{1,6}$`), so searching `KNML 1` canonicalises fine but then fails every downstream `isKangisFormat()` check.

**Fix:** one shared, tolerant KANGIS regex — roughly
`^(MLKN|KNML|KNGP)\s?\d{1,6}([-_]\d{1,3})?$` for legacy and `^KN[\s-]?\d{2,6}$` for new — used by
`identifyFileNumberType()`, `resolveKangisCanonical()` and `isOldMlsKnFileNo()` alike. Note that
`identifyFileNumberType()`'s `new_kangis` branch (`^KN\d{2,6}$`) rejects any space, while
`isOldMlsKnFileNo()` (`^KN[- ]\d+`) *requires* a separator — the two are mutually exclusive by
accident, not design.

---

### 4.3 🟠 The "Ministry of Land & Physical Planning" register filter matches nothing

**Where:** `RelatedFileNumberController.php:96`

```php
case 'mlpp':
    $q->where('transaction_type', 'Land & Physical Planning Recertification');
```

The stored string is `"Ministry of Land & Physical Planning Recertification"`. Verified:

```
exact filter match count : 0
actual stored string count: 852
```

The Related File Numbers register's MLPP filter returns an empty list. `MlsFileNoController:2784`
also *writes* the short form `'Land & Physical Planning Recertification'`, so the register now has two
spellings for one concept. **Fix:** switch the filter to `LIKE '%Physical Planning Recertification'`
and normalise the write sites.

---

### 4.4 🟠 214 spurious "KANGIS Recertification" links between two land files

Neither endpoint is KANGIS-format (e.g. `RES-1994-1888 ⇄ RES-RC-1982-1489`, `COM-2013-95 ⇄ COM-RC-1982-19`).
`recertDisplayLabel()` correctly refuses to relabel these ("must NOT masquerade as a KANGIS recert"),
so they render as bare rows with a blank type:

```
COM-2013-95 | - | Related Fileno | p1=PARTERSON ZOCHONIS NIGERIA LTD | cmt=KANGIS RECERTIFICATION COM-RC-1982-19
```

This is a **data-quality** problem, not a code bug — but the current handling is confusing: an
untyped row surfaces with a comment that literally reads "KANGIS RECERTIFICATION". These 214 rows
should be re-typed (probably to `Subdivision` or `NULL`) or the neutral row hidden.

---

### 4.5 🟡 Rows render with a blank file number

Visible on `COM-RC-1982-19`: four rows show `fileno = '-'`. `search()` returns raw staging rows
whose `fileno`/`mlsFNo` are empty; only the print path has `extractOwnFileNo()` fallback logic.
The on-screen timeline has no equivalent, so the file number column is blank.

---

### 4.6 🟡 `tagRowsWithLifecycleFileNo()` is called without `$primaryFileNo` on the screen path

`search()` line 671 calls it with two arguments; the signature's third parameter `$primaryFileNo`
defaults to `''`. The consequence is that the **system-temp rollup** at line 6951 —

```php
if ($lifecycle !== '' && $this->isSystemTempFileNo($lifecycle) && $normPrimary !== '') {
    $lifecycle = $normPrimary;   // TEMP-91950 rows fold into the searched file
}
```

— **never fires on screen**, only in the print path (`groupTimelineByLifecycle` line 8112 passes it).
So a `TEMP-xxxxx` row can appear as its own lifecycle group on screen and not in the slip.

---

### 4.7 🟡 `groupTimelineByLifecycle()` can drop rows

`$lifecycleFiles` is computed **before** `ensureLifecycleSyntheticRows()` and the second
`tagRowsWithLifecycleFileNo()` call (lines 8121–8141). The final loop only emits rows whose
`lifecycle_file_no` is in `$orderedFiles`:

```php
foreach ($orderedFiles as $fno) {
    $fileRows = array_filter($rows, fn ($r) => ($r['lifecycle_file_no'] ?? '') === $fno);
```

If the re-tag assigns a row a lifecycle owner that was not in the pre-computed set (possible, since
the newly-added synthetic rows can change the `kangisToMain` map), that row is **silently discarded
from the printed report**. Low frequency, but it is an unbounded silent-loss path — a defensive
"append unmatched rows" tail would close it.

---

## 5. Stale documentation

- `resources/views/legal_search/KLAES Legal Search Timeline.md` — weights (OP 11 / ToT 10 / CofO 0)
  no longer match `LegalSearchTimelineWeights::MAP` (14 / 13 / 1). Rule B in that document describes
  a `date - 1 second` hack that was never implemented; `placeKangisRecertBeforeCofo()` reorders rows
  instead.
- `recertDisplayLabel()`'s own docblock says the Ministry branch returns
  `"Ministry of Land and Physical Planning Recertification"`; the code returns
  `"Land Recertification (File Commissioning)"`. That divergence is the direct cause of §4.1.

---

## 6. Recommended fix order

| # | Fix | Risk | Effect |
|---|-----|------|--------|
| 1 | §4.1 party_1 for Ministry recerts | trivial, 1 line | 852 rows corrected; screen matches slip |
| 2 | §4.3 MLPP register filter + write normalisation | trivial | register filter works again |
| 3 | §4.2 unified KANGIS number regex | **medium — touches every KANGIS code path; needs regression on a sample of MLKN/KNML/KNGP/KN files, both suffixed and short** | ~209 hidden recertifications restored |
| 4 | §4.6 pass `$primaryFileNo` on the screen path | low | TEMP-xxx rows stop forming phantom groups |
| 5 | §4.7 append-unmatched tail in `groupTimelineByLifecycle` | low | closes a silent row-loss path |
| 6 | §4.4 data clean-up of 214 mis-typed links | data-only | removes confusing blank-type rows |
| 7 | Refresh the two stale docs in §5 | trivial | |

**Structural recommendation:** the three-way duplication in §1 is the root cause of the screen-vs-slip
class of bug (§4.1, §4.6). The cheapest durable step is to make `search()` return
`groupTimelineByLifecycle()`-processed rows and have the JS *render* rather than *re-derive* — but
that is a substantial refactor and should be scoped separately.

---

## 7. Open question

The KANGIS unit-suffix numbers (`MLKN 2280-1`, `KNML 3855_3`) use **two different separators** for
what appears to be the same concept. Before fixing §4.2, confirm with the registry team:

- Are `-1` / `_1` the same thing (a fragment/unit of the parent KANGIS file)?
- Should `MLKN 2280-1` group into `MLKN 2280`'s lifecycle, or stand as its own KANGIS file?

The answer changes whether `extractKangisLifecycleKey()` should strip the suffix or preserve it.
