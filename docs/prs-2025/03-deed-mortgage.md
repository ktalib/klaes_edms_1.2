# 03 — Deed of Mortgage Registration (Jan–Dec 2025)

> Source images 5–6. Header: *"DEED OF MORTGAGE REGISTRATION FROM JANUARY TO DECEMBER 2025"*

## Table (verbatim)

| Month | M | F | Residential | Commercial | Organ | Joint | Total |
|---|---:|---:|---:|---:|---:|---:|---:|
| January | 1 | 0 | 2 | 0 | 1 | 0 | 3 |
| February | 3 | 0 | 2 | 0 | 3 | 0 | 5 |
| March | 7 | 0 | 4 | 4 | 2 | 0 | 10 |
| April | 3 | 0 | 1 | 2 | 0 | 0 | 3 |
| May | 4 | 0 | 2 | 2 | 1 | 0 | 5 |
| June | 5 | 0 | 3 | 0 | 2 | 0 | 5 |
| July | 2 | 0 | 1 | 1 | 0 | 0 | 2 |
| August | 1 | 1 | 2 | 1 | 0 | 0 | 3 |
| September | 1 | 0 | 2 | 1 | 1 | 0 | 4 |
| October | 8 | 0 | 5 | 4 | 0 | 0 | 9 |
| November | 1 | 0 | 3 | 0 | 2 | 0 | 5 |
| December | 5 | 0 | 2 | 3 | 2 | 0 | 7 |
| **TOTAL (as printed)** | **43** | **0** | **26** | **12** | **14** | **0** | **61** |

Note the column order differs from every other deed table: Residential comes **before**
Commercial here. `Organ` = Organisation.

## Arithmetic check

| Column | Sum of the 12 months | Printed total | Verdict |
|---|---:|---:|---|
| M | 41 | 43 | ✗ off by +2 |
| F | 1 | 0 | ✗ **August's single female mortgagor is dropped from the total** |
| Residential | 29 | 26 | ✗ off by −3 |
| Commercial | 18 | 12 | ✗ off by −6 |
| Organ | 14 | 14 | ✓ |
| Joint | 0 | 0 | ✓ |
| Total | 61 | 61 | ✓ |

Only the Total column and Organ survive. `26 + 12 + 14 = 52` against a total of 61.

## Notable figures

- **61 mortgages for the whole year** against 1,248 assignments — mortgage activity is ~5% of
  transfer activity. This is the headline the PRS observation (below) is reacting to.
- October (9) and March (10) are the peaks; July (2) the trough. Volumes are too small for the
  monthly series to be meaningful — annual and quarterly views are more honest.
- Female mortgagors: **1 in the entire year** (August). Whether that reflects reality or a
  capture gap is worth stating explicitly in the report.

## Chart (image 6)

3-D exploded pie with 13 slices — the twelve months **plus the TOTAL**. The TOTAL slice (61) is
therefore half the pie and every month is a sliver. The chart is still titled "Diagram Title".

This chart should not be reproduced. A pie of a time series is the wrong form, a 3-D pie
distorts the areas, and including the total as a slice makes it meaningless. Replace with a
simple monthly column chart, or fold this into the bank-ranking chart which is the actual story.

## PRS observation (verbatim from image 6)

> "The fidelity and jaiz bank give out more facility then order commercail bank across state and
> also federal Mortgage bank don't contribute in mortgage completely which there is need for
> awerness and also proper find out way facilities are not given out across the state."

Cleaned up: *Fidelity Bank and Jaiz Bank issue substantially more mortgage facilities than other
commercial banks in the state. The Federal Mortgage Bank has made no contribution at all. There
is a need for public awareness, and for an investigation into why facilities are not being
issued more widely across the state.*

Supporting numbers are in [04-bank-facility-ranking.md](04-bank-facility-ranking.md).

## KLAES data source

Same three-table union as assignments, filtered to
`instrument_type IN ('Deed of Mortgage', 'Tripartite Mortgage')` — this exact filter is already
implemented in [MortgageController.php:28-30](app/Http/Controllers/MortgageController.php#L28-L30)
and the controller already exposes per-source counts and a combined DataTable. The monthly
aggregation is a small addition on top of existing, working code — **this is the cheapest table
in the report to implement.**

Mortgagee bank comes from the `Mortgagee` column (`pra`, `file_history_staging`) or
`party_2_name` (`instrument_capture`); see [04](04-bank-facility-ranking.md) for the
normalisation problem.
