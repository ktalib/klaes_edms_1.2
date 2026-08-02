# 16 — Land Department: Applications by Gender (Jan–Dec 2025)

> Source images 8–9. Header: *"Applications received based on Gender from January to December, 2025."*
> Caption: *"figure 5: Applications received based on Gender from January to December, 2025."*

## Table (verbatim)

| SN | Month | Male | Female | Row total *(computed)* |
|---:|---|---:|---:|---:|
| 1 | January | 102 | 8 | 110 |
| 2 | February | 202 | 4 | 206 |
| 3 | March | 204 | 7 | 211 |
| 4 | April | 180 | 1 | 181 |
| 5 | May | 149 | 5 | 154 |
| 6 | June | 225 | 3 | 228 |
| 7 | July | 168 | 6 | 174 |
| 8 | August | 238 | 9 | 247 |
| 9 | September | 203 | 7 | 210 |
| 10 | October | 180 | 7 | 187 |
| 11 | November | 201 | 7 | 208 |
| 12 | December | 194 | 5 | 199 |
| 13 | **TOTAL** | **2,246** | **69** | **2,315** |

## Arithmetic check — ✓ FULLY CLEAN, and it cross-reconciles

Both columns foot exactly (2,246 and 69), and `2,246 + 69 = 2,315`.

**More importantly, it reconciles month-by-month with the size/purpose table**
([15](15-oss-applications-size-purpose.md)):

| Month | Gender total | Size/purpose total | |
|---|---:|---:|---|
| January | 110 | 110 | ✓ |
| February | 206 | 206 | ✓ |
| March | 211 | 211 | ✓ |
| April | 181 | 181 | ✓ |
| May | 154 | 154 | ✓ |
| June | 228 | 228 | ✓ |
| July | 174 | 174 | ✓ |
| August | 247 | 247 | ✓ |
| September | 210 | 210 | ✓ |
| October | 187 | 187 | ✓ |
| November | 208 | 208 | ✓ |
| December | 199 | 199 | ✓ |
| **Total** | **2,315** | **2,315** | **✓** |

**Twelve out of twelve months agree exactly, and so does the annual total.** This is the only
perfect cross-table reconciliation anywhere in either PRS report, and it demonstrates the
standard the Deeds tables should be held to: two independent breakdowns of the same 2,315
applications, both summing to the same number in every period.

Contrast [10](10-data-quality-audit.md#1-gender-counts-and-land-use-counts-never-agree--in-any-table),
where the Deeds report's gender and land-use views disagree by up to 18×.

It also means these two tables have **no "not recorded" bucket** — every application has both a
gender and a category. Confirm that is real and not the result of forcing unknowns into Male.

## Notable figures

- **Female share is 3.0%** (69 / 2,315) — the **lowest of any table in either report**, below
  resettlement's 4.9% ([06](06-resettlement-allocation.md)), CofO's ~4% and the direct-allocation
  gender table's 7.3% ([14](14-land-gender-allocation.md)).
- Male:female ratio is roughly **33:1**.
- Peak female month is August (9); April had **1** female applicant out of 181.
- Female counts are stable at 1–9 per month with no trend — no sign of improvement across the
  year.
- There is **no Joint, Organisation or Government column** here, unlike the direct-allocation
  gender table which does carry them. So this table's gender dimension is binary while the other
  Land table's is five-valued. A single canonical party/gender dimension is needed.

Across both PRS reports, every measured female share falls between 3% and 7.3%. That consistency
across independent tables and departments makes it a robust finding rather than a data artefact,
and it deserves its own section in the report rather than being spread across a dozen columns.

## Chart (image 9)

Column chart, title still *"Diagram Title"*. Same two faults as Figure 4:

1. **X axis is `1`–`13` (the SN column), not month names.**
2. **Row 13 (TOTAL) is plotted as a category**, so a 2,246 bar sits next to twelve bars of
   ~100–240 and compresses them all into the bottom tenth of the plot area. The whole point of
   the chart — monthly variation — is invisible.

Also, at a 33:1 ratio the female series is a sliver at every month. Rebuild as months-only, and
either use a log scale, plot female share as a percentage line, or give female its own panel.

## KLAES data source

Direct: `oss_applications.sex`
([LandsOneStopShopApplication.php:69](app/Models/LandsOneStopShopApplication.php#L69)).

Because gender, land use, purpose and application type are all columns on the **same row**, this
table and [15](15-oss-applications-size-purpose.md) should be produced from **one query with two
pivots** — which is precisely why they reconcile here and why the KLAES version will keep
reconciling by construction.

Open items:
- Confirm the `sex` domain (`M`/`F`, `Male`/`Female`, mixed case, nulls) and whether nulls exist
  — if they do, the perfect reconciliation above is suspicious and unknowns are being coerced.
- Confirm which date field defines "received" (D1) — application date, not capture date.
