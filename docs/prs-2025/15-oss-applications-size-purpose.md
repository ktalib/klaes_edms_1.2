# 15 — Land Department: Applications by Size and Purpose of Plot (Jan–Dec 2025)

> Source images 6–7. Header: *"Applications received based on the size and purpose plots from January to December, 2025."*
> Caption: *"Figure 4: bar chat present applications received based on the size and purpose plot from January to December, 2025."*

## Table (verbatim)

| SN | Month | High density | Low density | Commercial | Industrial | Small scale | Total app received |
|---:|---|---:|---:|---:|---:|---:|---:|
| 1 | JAN | 97 | 5 | 4 | 1 | 3 | 110 |
| 2 | FEB | 200 | 2 | 3 | 0 | 1 | 206 |
| 3 | MARCH | 206 | 0 | 3 | 0 | 2 | 211 |
| 4 | APRIL | 175 | 2 | 4 | 0 | 0 | 181 |
| 5 | MAY | 145 | 1 | 3 | 4 | 1 | 154 |
| 6 | JUNE | 228 | 0 | 0 | 0 | 0 | 228 |
| 7 | JULY | 167 | 2 | 2 | 2 | 1 | 174 |
| 8 | AUGUST | 239 | 2 | 4 | 0 | 2 | 247 |
| 9 | SEPT | 207 | 0 | 2 | 1 | 0 | 210 |
| 10 | OCT | 170 | 5 | 5 | 3 | 4 | 187 |
| 11 | NOV | 206 | 0 | 2 | 0 | 0 | 208 |
| 12 | DEC | 188 | 2 | 4 | 2 | 3 | 199 |
| 13 | **TOTAL** | **2,228** | **21** | **36** | **13** | **17** | **2,315** |

## Arithmetic check — ✓ FULLY CLEAN

**This is the best table in either PRS report.** It passes every check:

- All six column totals foot exactly (2,228 / 21 / 36 / 13 / 17 / 2,315).
- **Every one of the twelve month rows sums to its own printed total** — Jan 97+5+4+1+3 = 110 ✓,
  through Dec 188+2+4+2+3 = 199 ✓.
- The category totals sum to the grand total: 2,228 + 21 + 36 + 13 + 17 = **2,315** ✓.
- And it cross-reconciles perfectly with the gender table — see
  [16-oss-applications-gender.md](16-oss-applications-gender.md).

Whatever process produced this table should be the model for the rest of the report.

## Notable figures

- **2,315 applications received in 2025.**
- **High density is 96.2%** (2,228) of all applications. Low density is 21 for the entire year —
  roughly one every three weeks. The Kano land market, as measured here, is almost entirely
  high-density residential.
- Peak: **August (247)**, then June (228), March (211), September (210). Trough: **January
  (110)** — exactly half the monthly average, consistent with a slow start to the year rather
  than an anomaly.
- Monthly volume is otherwise remarkably stable (154–247 from Feb onward), with none of the
  wild swings seen in the Deeds tables. Another sign this data is sound.
- June is the only month with **zero** commercial, industrial and small-scale applications —
  228 high density and nothing else. Worth a one-line check that nothing was lost that month.

## ⚠ Note the dimension change

This table's categories are **plot size/density** (High density, Low density, Small scale) mixed
with **land use** (Commercial, Industrial) — a fifth distinct vocabulary across the two reports.
"High density" and "Commercial" are not mutually exclusive properties of a plot, yet they are
being counted as if they were, and the row sums work only because each application is assigned
to exactly one bucket.

For the KLAES version, **size/density and land use must be separate dimensions**, cross-tabbed
rather than flattened. See [10](10-data-quality-audit.md#4-inconsistent-dimensions-across-tables).

## ⚠ Denominator mismatch with the other Land tables

| Table | Annual figure |
|---|---:|
| Conversion applications ([12](12-land-conversion-applications.md)) | 6,595 |
| Direct allocation applications ([13](13-land-direct-allocation.md)) | 6,798 |
| **Applications by size/purpose (this table)** | **2,315** |

All three claim to count *applications received in 2025*, and the third is a third of the size
of either of the other two. They must be different populations, but the report never says so.
The KLAES version has to state the scope of each figure explicitly on the face of the table.

## Chart (image 7)

Stacked column, title still *"Diagram Title"*. Two faults:

1. **X axis shows `1`–`13`, the SN column, not month names.** The month column was in the sheet
   but not in the chart range.
2. **Row 13 (TOTAL) is plotted as a thirteenth category, and the `TOTAL APP RECEIVED` series is
   stacked on top of its own components** — so the total bar reads ~4,600 for a true 2,315,
   exactly double. Same defect as five charts in the Deeds report.

Rebuild: months on X, stacked columns of the five categories only, no total series, no total
category. Given high density is 96%, the other four will be invisible in a stack — consider a
separate small-multiple for the four minor categories at their own scale.

## KLAES data source

`oss_applications` ([app/Models/LandsOneStopShopApplication.php](app/Models/LandsOneStopShopApplication.php)),
which carries `application_type`, `land_use`, `purpose` and `sex` on one row.

Density/size is the open question: confirm which column holds High density / Low density / Small
scale. It may be derived from plot dimensions rather than stored directly, in which case the
banding rule needs to be captured explicitly.

**Performance warning:** the OSS applications page is known to crash Chrome (1,818 districts ×
~23 duplicated selects ≈ 42k option nodes / 5.8 MB). That is a page-rendering problem, not a
query problem — but do not build the PRS report *on top of* that page. Aggregate server-side and
render a small result set.
