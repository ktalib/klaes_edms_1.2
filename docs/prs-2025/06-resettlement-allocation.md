# 06 — Occupancy Permit: Direct Allocation & Resettlement (Jan–Dec 2025)

> Source images 8 (bottom) – 9.
> Section header: *"Occupancy permit Direct allocation and Resstelement"*
> Table header: *"RESETTELEMENT ALLOCATION"*

## Table (verbatim)

| Month | Male | Female | Joint | Total |
|---|---:|---:|---:|---:|
| January | 445 | 39 | 0 | 484 |
| February | 390 | 21 | 0 | 411 |
| March | 39 | 19 | 0 | 42 |
| April | 600 | 48 | 0 | 648 |
| May | 364 | 13 | 0 | 377 |
| June | 472 | 12 | 0 | 484 |
| July | 755 | 14 | 0 | 769 |
| August | 443 | 5 | 0 | 448 |
| September | 558 | 20 | 0 | 578 |
| October | 485 | 30 | 0 | 515 |
| November | 740 | 40 | 0 | 780 |
| December | 478 | 33 | 0 | 511 |
| **TOTAL (as printed)** | **5,769** | **294** | **0** | **6,047** |

This table has no land-use breakdown — gender only.

## Arithmetic check

| Column | Sum of the 12 months | Printed total | Verdict |
|---|---:|---:|---|
| Male | 5,769 | 5,769 | ✓ |
| Female | 294 | 294 | ✓ |
| Joint | 0 | 0 | ✓ |
| Total | 6,047 | 6,047 | ✓ |

Every column foots. **But the rows do not**: `Male + Female` should equal Total, and it does for
eleven months out of twelve.

- **March: 39 + 19 = 58, but Total reads 42.** This is the only row-level break in the table.
- Consequently the grand total is internally inconsistent too: `5,769 + 294 = 6,063` vs. the
  printed 6,047 — a difference of exactly 16, which is the March discrepancy (58 − 42).

One of the three March values is wrong. Given April onwards is healthy, the likeliest reading is
that Male should be 23 (23 + 19 = 42), or that Total should be 58. **PRS must resolve this** —
it is a single-cell fix and it makes the whole table sound.

## Notable figures

- **6,047 occupancy permits allocated in 2025** — by far the highest-volume activity in the
  report, ~5× the deed and CofO volumes combined. Resettlement is the dominant workload.
- Peak: **November (780)** and July (769). Trough: **March (42)** — a 95% collapse from
  February's 411, which is almost certainly a real operational stoppage and warrants a footnote
  explaining it rather than being left as an unexplained cliff.
- **Female share is 4.9%** (294/6,047) — the widest gender gap in the report, and materially
  worse than the CofO figure. On a resettlement programme (i.e. state-directed allocation rather
  than market transactions) this is a policy-relevant number and should be surfaced
  prominently, not left as a column.
- `JOINT` is zero for all twelve months, as in every other table in the report.

## Chart (image 9)

Stacked column, months on X plus TOTAL, series Male / Female / Joint / Total, X labels rotated
45°. Same structural defect as elsewhere: the stacked TOTAL bar reads ~12,000, exactly double
the true 6,047, because the Total series is stacked on top of Male and Female.

Rebuild as a stacked column of Male / Female only (Joint is empty), months only, with the annual
figure as a KPI tile.

## KLAES data source

Occupancy Permit (OP) work is well covered in the codebase — see
[app/Http/Controllers/LandsOneStopShop/OpResettlementApplicationController.php](app/Http/Controllers/LandsOneStopShop/OpResettlementApplicationController.php)
and `LandsOneStopShopApplication` (which carries a `sex` field, giving the gender split
directly).

Two cautions carried over from prior work on this area:

1. **The section title says "Direct allocation AND Resettlement" but the table says
   "Resettlement Allocation" only.** Confirm whether 6,047 covers both streams or resettlement
   alone. If both, they should be split into two series — they are different processes.
2. **Batch-commissioned OPs distort monthly buckets.** 376 OP CoN files were batch-commissioned
   under a shared `op_batch`; if the monthly grouping keys off capture date, an entire batch
   lands in one month and produces a fake spike. Group by the allocation/permit date, not
   `created_at`. This may itself explain the March anomaly and the July/November peaks — check
   before presenting them as real operational variation.
