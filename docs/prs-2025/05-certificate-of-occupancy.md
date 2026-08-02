# 05 — Certificate of Occupancy Registered (Jan–Dec 2025)

> Source images 7 (bottom) – 8. Header: *"CERTIFICATE OF OCCUPANCY REGISTRED"*

## Table (verbatim)

| Month | M | F | Commercial | Residential | Industry | Agriculture | Total |
|---|---:|---:|---:|---:|---:|---:|---:|
| January | 103 | 7 | 6 | 100 | 2 | 2 | 110 |
| February | 101 | 5 | 5 | 95 | 3 | 3 | 106 |
| March | 49 | 5 | 9 | 40 | 4 | 1 | 54 |
| April | 40 | 2 | 3 | 35 | 1 | 1 | 42 |
| May | 45 | 2 | 5 | 40 | 0 | 0 | 47 |
| June | 105 | 3 | 9 | 88 | 2 | 6 | 108 |
| July | 25 | 1 | 2 | 20 | 2 | 1 | 28 |
| August | 88 | 3 | 9 | 75 | 1 | 1 | 89 |
| September | 71 | 1 | 10 | 75 | 1 | 1 | 72 |
| October | 88 | 2 | 11 | 75 | 0 | 2 | 90 |
| November | 124 | 4 | 11 | 75 | 0 | 3 | 128 |
| December | 33 | 0 | 3 | 75 | 1 | 1 | 33 |
| **TOTAL (as printed)** | **872** | **32** | **83** | **793** | **17** | **2** | **907** |

## Arithmetic check

| Column | Sum of the 12 months | Printed total | Verdict |
|---|---:|---:|---|
| M | 872 | 872 | ✓ |
| F | 35 | 32 | ✗ off by −3 |
| Commercial | 83 | 83 | ✓ |
| Residential | 793 | 793 | ✓ |
| Industry | 17 | 17 | ✓ |
| Agriculture | **22** | **2** | ✗ **printed total is wrong — almost certainly a dropped digit (22 → 2)** |
| Total | 907 | 907 | ✓ |

Beyond the column totals:

- Gender: `872 + 32 = 904` vs. total 907 (or `872 + 35 = 907` ✓ using the *corrected* female
  sum — so **the female total is the error, and 35 is right**). This is a rare case where the
  report is self-correcting: use F = 35 and gender reconciles perfectly with the total.
- Categories: `83 + 793 + 17 + 22 = 915` vs. 907 — still 8 over, even after fixing agriculture.

### ⚠ Suspected fabricated data — Residential, August–December

Residential reads **75, 75, 75, 75, 75** for August through December. A flat repeat of the same
value across five consecutive months does not occur naturally in registration counts. Compare
January–July, which vary freely (100, 95, 40, 35, 40, 88, 20).

This same 100/95/40/35/40/88/20/75… sequence **also appears verbatim in the Deed of Release
table** ([07](07-deed-of-release.md)), where it is definitely wrong. The strong inference is
that a block of cells was copy-pasted between sheets and then partially overwritten.

**Do not treat CofO Residential Aug–Dec as real.** Flag it to PRS and re-derive from source.
September makes the contamination visible: Commercial 10 + Residential 75 + Industry 1 +
Agriculture 1 = 87, against a printed total of 72 — the residential figure is 15 too high.

## Notable figures

(Trusting only the Total column, which is internally consistent.)

- **907 certificates registered in 2025.**
- Peak: **November (128)**, then January (110) and June (108). Trough: **July (28)** and
  December (33) — a sharp year-end drop-off worth a note in the narrative.
- Male recipients outnumber female roughly **25:1** (872 vs 35). Across every table in this
  report the gender gap is extreme; it is the clearest policy finding available and deserves its
  own section rather than being buried in per-table columns.
- Agriculture (22, corrected) and Industry (17) are marginal; the registry is overwhelmingly
  residential.

## Chart (image 8)

Stacked column, months on X plus a TOTAL group. Series: Commercial, Residential, Industry,
Agriculture, Total. Same flaw as the assignment chart — the TOTAL group dwarfs the months, and
because `Total` is stacked *on top of* its own components the annual bar reads ~1,800, double
the true 907.

**This chart actively misinforms.** Rebuild as a stacked column of the four categories only.

## KLAES data source

`CofO` model exists at [app/Models/CofO.php](app/Models/CofO.php), and there is a
`CofO_staging` table (its `cofo_type` column is a user-picked issuing authority — Old CofO
Ministry / KANGIS CofO / New KANGIS CofO — **not** an instrument subtype).

Decide explicitly what "registered" means for the monthly bucket: date of grant, date of
registration, or date of capture into KLAES. The three will give materially different monthly
distributions, and back-captured historical CofOs will pile onto their capture month if the
wrong field is chosen. Land use should come from `land_use`.
