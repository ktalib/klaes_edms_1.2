# 02 — Deed of Assignment Registration (Jan–Dec 2025)

> Source images 3–4. Header: *"DEED OF ASSIGNMENT REGISTRATION FROM JANUARY TO DECEMBER 2025"*

## Table (verbatim)

| Month | M | F | Commercial | Residential | Organisation | Joint | Total |
|---|---:|---:|---:|---:|---:|---:|---:|
| January | 100 | 7 | 8 | 100 | 1 | 0 | 108 |
| February | 100 | 13 | 4 | 109 | 0 | 0 | 113 |
| March | 54 | 0 | 0 | 54 | 0 | 0 | 54 |
| April | 70 | 2 | 16 | 60 | 4 | 0 | 76 |
| May | 110 | 10 | 30 | 74 | 0 | 0 | 124 |
| June | 51 | 0 | 11 | 40 | 0 | 0 | 51 |
| July | 70 | 18 | 25 | 82 | 19 | 0 | 107 |
| August | 70 | 16 | 30 | 56 | 0 | 0 | 86 |
| September | 102 | 27 | 29 | 100 | 0 | 0 | 129 |
| October | 88 | 20 | 16 | 93 | 0 | 0 | 109 |
| November | 100 | 10 | 6 | 98 | 6 | 0 | 116 |
| December | 101 | 10 | 23 | 160 | 2 | 0 | 175 |
| **TOTAL (as printed)** | **1,087** | **133** | **198** | **1,026** | **32** | **0** | **1,248** |

## Arithmetic check

| Column | Sum of the 12 months | Printed total | Verdict |
|---|---:|---:|---|
| M | 1,016 | 1,087 | ✗ **off by +71** |
| F | 133 | 133 | ✓ |
| Commercial | 198 | 198 | ✓ |
| Residential | 1,026 | 1,026 | ✓ |
| Organisation | 32 | 32 | ✓ |
| Joint | 0 | 0 | ✓ |
| Total | 1,248 | 1,248 | ✓ |

Two further inconsistencies:

1. **Gender ≠ Total.** `M + F = 1,087 + 133 = 1,220`, but the report's total is **1,248** — 28
   registrations have no gender. Even using the corrected male sum (1,016) the gap widens to 232.
2. **Categories ≠ Total.** `198 + 1,026 + 32 + 0 = 1,256`, which *overshoots* the total of 1,248
   by 8. Per-month the two also disagree, e.g. January `8 + 100 + 1 = 109` vs. total `108`.

So the Total column is the only internally consistent series; treat the printed M total as a
typo and the category/gender breakdowns as approximate.

## Notable figures

- 1,248 assignments registered in 2025 — the single largest deed category in the report.
- **December is the peak month (175)**, driven by residential (160). March is the trough (54).
- Residential dominates at 82% of categorised registrations; commercial 16%; organisation 3%.
- `JOINT` is zero for all twelve months — either joint ownership is genuinely never recorded on
  assignments, or the column is not being captured. Worth confirming with the Deeds Registry
  before building it into the KLAES version.
- July is an outlier for organisations (19 of the year's 32).

## Chart (image 4)

Clustered column, dark background, months on X plus a `TOTAL` group on the right. Series:
Commercial, Residential, Organisation, Joint, Total.

Weakness: including the TOTAL group in the same axis as the months squashes the monthly bars to
near-illegibility — the annual bar is ~10× taller than any month. In the KLAES chart, drop the
TOTAL group and show the annual figures as KPI tiles above the chart.

## KLAES data source

Deed registrations live in a union of three tables on the `sqlsrv` connection (this pattern is
already established in [MortgageController.php:48-124](app/Http/Controllers/MortgageController.php#L48-L124)):

- `instrument_capture`
- `pra`
- `file_history_staging`

with `deed_registrations` supplying the authoritative `deeds_date`.

Filter: `instrument_type = 'Deed of Assignment'` (see the normalisation map in
[InstrumentRegistrationController.php:1036-1043](app/Http/Controllers/InstrumentRegistrationController.php#L1036-L1043)
— `Deed of Assignment` is already a canonical target).

Group by month of `deeds_date` (fall back to `reg_date`, then `created_at`).

**Open questions to resolve before coding** (see [11-implementation-plan.md](11-implementation-plan.md)):
- Land use: `land_use` exists on the property-record tables — confirm it is populated on
  assignment rows and what its distinct values are.
- Gender: `gender` exists on `file_indexings` and `mls_file_no`, not on the deed tables. The
  grantee's gender likely has to be joined through the file. "Organisation" is presumably a
  *party type* (corporate grantee), not a land use — which explains why the category column
  mixes land use and party type and why the totals don't reconcile.
