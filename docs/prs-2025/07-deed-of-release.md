# 07 — Deed of Release (Jan–Dec 2025)

> Source image 10. Header: *"DEED OF RELEASE"*

## ⚠ This is the most corrupted table in the report. Read the audit before using any figure.

## Table (verbatim)

Column headers as printed are broken: the gender block reads `GENDER M` and then a second
column headed **`MALE`** — which from context must be **FEMALE** (it is 0 for every month except
December, matching the female-column pattern of the other tables).

| Month | M | F *(printed "MALE")* | Commercial | Residential | Industry | Agriculture | Total |
|---|---:|---:|---:|---:|---:|---:|---:|
| January | 4 | 0 | 6 | 100 | 2 | 2 | 6 |
| February | 5 | 0 | 5 | 95 | 3 | 3 | 11 |
| March | 6 | 0 | 9 | 40 | 4 | 1 | 11 |
| April | 3 | 0 | 3 | 35 | 1 | 1 | 18 |
| May | 1 | 0 | 5 | 40 | 0 | 0 | 0 |
| June | 1 | 0 | 9 | 88 | 2 | 6 | 2 |
| July | 7 | 0 | 2 | 20 | 2 | 1 | 10 |
| August | 4 | 0 | 9 | 75 | 1 | 1 | 11 |
| September | 8 | 0 | 10 | 60 | 1 | 1 | 9 |
| October | 2 | 0 | 11 | 74 | 0 | 2 | 5 |
| November | 4 | 0 | 11 | 110 | 0 | 3 | 8 |
| December | 3 | 1 | 3 | 28 | 1 | 1 | 6 |
| **TOTAL (as printed)** | **48** | **1** | **83** | **765** | **34** | **24** | **97** |

## Arithmetic check

| Column | Sum of the 12 months | Printed total | Verdict |
|---|---:|---:|---|
| M | 48 | 48 | ✓ |
| F | 1 | 1 | ✓ |
| Commercial | 83 | 83 | ✓ |
| Residential | 765 | 765 | ✓ |
| Industry | 34 | 34 | ✓ |
| Agriculture | 24 | 24 | ✓ |
| Total | 97 | 97 | ✓ |

Every column foots perfectly — which is exactly what makes this table dangerous. It looks clean.
It is not:

- **Gender says 49 releases (48 M + 1 F). The Total column says 97. The categories say 906**
  (83 + 765 + 34 + 24). Three mutually incompatible answers to "how many deeds of release were
  registered in 2025", differing by up to **18×**.
- Per-month the Total column bears no relation to any other column: May has Total 0 but 45
  categorised records; January has Total 6 but 110 categorised; April has Total 18 against
  M=3 and 40 categorised.

### The contamination

The Commercial / Residential / Industry / Agriculture block is **copied from the Certificate of
Occupancy table** ([05](05-certificate-of-occupancy.md)). Compare Residential:

| | Jan | Feb | Mar | Apr | May | Jun | Jul | Aug |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| CofO | 100 | 95 | 40 | 35 | 40 | 88 | 20 | 75 |
| Release | 100 | 95 | 40 | 35 | 40 | 88 | 20 | 75 |

Identical for the first eight months, and Commercial/Industry/Agriculture track the same way
(Jan 6/2/2, Feb 5/3/3, Mar 9/4/1, Apr 3/1/1 …). From September the two tables diverge slightly
(Release shows 60, 74, 110, 28 where CofO shows the flat 75s), consistent with someone pasting
the CofO block and then editing part of it.

**Conclusion: the entire land-use breakdown for Deed of Release is CofO data and must be
discarded.** The only column that plausibly describes releases is the Total (97), and possibly
the gender pair (48/1) — which are themselves mutually inconsistent.

## Notable figures

State only what survives: **~97 deeds of release registered in 2025**, low single-digit to
low-double-digit monthly volumes, September the busiest (9–11). Do not publish a land-use split
for this instrument until it is re-derived.

Sanity check against mortgages: 61 mortgages registered vs. 97 releases. A release discharges a
mortgage, so releases exceeding new mortgages is plausible (older mortgages being discharged),
but the ratio is worth confirming.

## Chart (image 10)

Stacked column, months plus TOTAL, with a `DEED OF RELEASE` series plus unlabelled series (the
legend shows bare colour swatches with no names). Because it plots the contaminated CofO
columns, the chart is dominated by the residential band and shows CofO's shape, not releases'.
Discard entirely.

## KLAES data source

Same three-table union as [02](02-deed-assignment.md) / [03](03-deed-mortgage.md), filtered to
the release instrument type. Note the canonical name in
[InstrumentTypeController.php:110](app/Http/Controllers/InstrumentTypeController.php#L110) is
**`Deed of Surrender and Release`** (a protected type), not "Deed of Release" — check whether
both spellings exist in the data before filtering, or the count will come out short.

Because a release is tied to a prior mortgage, the KLAES version can do better than the
spreadsheet: link each release to the mortgage it discharges via `prop_id`/file number, and
report discharge rate and average time-to-discharge alongside the raw count.
