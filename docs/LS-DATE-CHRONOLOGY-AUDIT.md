# Legal Search — Reg Date / Transaction Date chronology audit

**Date:** 2026-08-07
**Scope:** ordering of *real instrument* rows (the `OTHER_INSTRUMENTS` weight band — assignments,
mortgages, surrenders, leases, powers of attorney) in the LS timeline and print slip.
**Status:** findings only. Nothing here has been fixed.

Related: [LS-WEIGHTING METHOD.md](LS-WEIGHTING%20METHOD.md), [LEGAL_SEARCH_OVERVIEW.md](LEGAL_SEARCH_OVERVIEW.md)

---

## Method

Ran `LegalSearchService::buildPrintReport()` over 15 files and recomputed, per row, the
classification (`LegalSearchTimelineWeights::classify`), the weight, and the sort key that
`getTransactionTimestamp()` would produce — then checked monotonicity *within* the
`OTHER_INSTRUMENTS` band.

Files sampled: `RES-2015-26`, `LAND-1992-1`, `IND-RC-1982-39`, `IND-1982-70`, `IND-RC-1981-7`,
`IND-1981-3`, `COM-RC-1981-30`, `IND-1983-11`, `RES-RC-1982-81`, `RES-2021-462`, `COM-1991-274`,
`RES-1983-481`, `RES-1985-636`, `RES-1986-417`, `RES-1983-995`.

---

## Verdict — the sort is correct

Real instruments are strictly ascending by **registration date** in every file sampled. Zero
ordering breaks. Example (`IND-1982-70`):

```
 2  Deed Of Mortgage               1   Nov 8, 1983    -> 1983-11-08 (reg)
 3  Deed Of Assignment             1   Oct 26, 1984   -> 1984-10-26 (reg)
 4  Deed Of Surrender And Release  1   Feb 14, 1985   -> 1985-02-14 (reg)
 ...
13  Deed Of Assignment             1   Mar 15, 2023   -> 2023-03-15 (reg)
16  Certificate Of Occupancy       0   Dec 1, 1982    -> placed by weight, not date
```

The current-year exception (weight `-1`, added 2026-08-07) also verifies on live data:
`RES-RC-1982-81` row 9 (Deed Of Mortgage, Jul 28 2026) and `RES-2021-462` row 4
(Deed Of Assignment, Jan 6 2026) both resolve to `-1` and land last.

---

## Finding 1 — Transaction Date column is not chronological (by design)

The sort key is `reg_date -> deeds_date -> transaction_date`, so only the **Reg Date** column
reads top-to-bottom. Transaction Date legitimately zig-zags — a deed executed in 1983 and
registered in 1992 is normal.

| File | Row | Reg Date | Txn Date |
|---|---|---|---|
| IND-RC-1982-39 | 10 | Sep 17, 1992 | Jun 13, 1983 |
| COM-1991-274 | 2 | Mar 4, 1997 | Dec 17, 1995 |
| IND-RC-1981-7 | 5 | Jun 25, 1986 | Feb 22, 1982 |

**Open question for the client:** only one of the two columns can descend the page. Confirm Reg
Date is the intended one before changing anything.

**Severity:** none (behaviour is correct) — but it looks like a bug to a reader, so worth
documenting in the report legend.

---

## Finding 2 — zero dates (`0001-01-01`) rank as year 1  **[REAL DEFECT]**

`0001-01-01` parses as a *valid* date, so it does two bad things:

1. prints literally as **"Jan 1, 0001"** in the date columns;
2. anchors the row to year 1, claiming the **earliest** slot in its band instead of being
   treated as undated (undated rows correctly sink to the tail of the band).

Confirmed in `RES-1983-995` — the zero-dated deed outranks the real 2025 one:

```
 3  Deed Of Assignment   1   Jan 1, 0001    Jan 1, 0001   -> 0001-01-01 (reg)   <-- ranked first
 4  Deed Of Assignment   1   Dec 12, 2025   Jan 1, 0001   -> 2025-12-12 (reg)
```

Also hit: `RES-1986-417` row 3 ("Other"); cosmetically visible in `IND-RC-1981-7` row 15 and
`COM-1991-274` rows 4-5.

### Blast radius — 1,074 rows

| Table | Column | Rows |
|---|---|---|
| `file_history_staging` | `transaction_date` | 659 |
| `file_history_staging` | `reg_date` | 238 |
| `pra` | `transaction_date` | 87 |
| `CofO_staging` | `transaction_date` | 64 |
| `pra` | `deeds_date` | 26 |
| `deed_registrations` | — | 0 |

Rows where the zero date lands in `reg_date` / `deeds_date` (264) can be **mis-ranked**; rows
where it only lands in `transaction_date` (810) are cosmetic — the reg date still wins the sort.

### Proposed fix

Guard both date parsers so a year below ~1900 returns `null`, routing the row to the undated
tail with the other blanks. Two call sites, both already the single date entry point:

- PHP `$parseTimelineDateValue` — [app/Services/LegalSearchService.php:5337](../app/Services/LegalSearchService.php#L5337)
- JS `parseTimelineDateValue` — [resources/views/legal_search/js.blade.php:3364](../resources/views/legal_search/js.blade.php#L3364)

A display-side guard is also needed so "Jan 1, 0001" renders as `-`.

---

## Finding 3 — screen and print disagree on same-day rows  **[DIVERGENCE]**

PHP's `parseTimelineDateValue` takes a **time** argument and applies `reg_time` to the timestamp
([LegalSearchService.php:5391-5393](../app/Services/LegalSearchService.php#L5391-L5393)). The JS
twin takes only a value and ignores time entirely
([js.blade.php:3364](../resources/views/legal_search/js.blade.php#L3364)), so on screen same-day
rows fall back to id order.

`IND-RC-1981-7` has three rows on 1990-03-22 — Deed of Lease `11:45`, Deed of Assignment
`11:55 AM`, plus a mortgage. The slip orders them by time; the screen may not.

**Fix:** give the JS parser the same optional time parameter and pass `item.reg_time` /
`item.deeds_time` from `getTransactionTimestamp()`, mirroring the PHP candidate pairs.

**Secondary, minor:** JS parses a bare year via `new Date("2015")` (UTC midnight) but a
`dd/mm/yyyy` string via `new Date(y, m-1, d)` (local midnight). Mixing UTC- and local-anchored
timestamps is a sub-day skew — harmless for day-level ordering, but it is an inconsistency.

---

## Finding 4 — data anomalies, not code

- `COM-1991-274`: `deeds_date = 2026-12-16` (future) against `transaction_date = Dec 16 2025` —
  a year typo. Now sorts last under the current-year rule, which makes the bad row *more*
  visible rather than less.
- `RES-RC-1982-81`: both the RofO and the C of O carry `reg_date = Jul 28, 2026` against 1996 /
  1981 transaction dates — looks like a bulk-capture timestamp written into `deeds_date`.
- `RES-2015-26`: 17 of 22 deeds have no date at all; they stack undated at the foot of the band.
  Correct handling, but the file reads as unordered.

Worth a wider sweep for `reg_date`/`deeds_date` values later than "today" — those are almost
certainly capture timestamps, and they now sink to the bottom of the timeline.

---

## Suggested order of work

1. **Finding 2** — real mis-ranking, one-line guard in two parsers plus a display guard.
2. **Finding 3** — screen/print divergence, contained to the JS parser signature.
3. **Finding 4** — data cleanup, needs a decision on how to correct captured dates.
4. **Finding 1** — client confirmation only.
