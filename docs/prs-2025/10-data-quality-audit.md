# 10 — Data Quality Audit of the 2025 PRS Reports

**Read this before implementing anything.** Every table in all three source reports was re-added
column by column and row by row. **Five of the fourteen tables are arithmetically sound** — and
all but one of those five come from Survey or Land, not Deeds.

The purpose of this audit is not to criticise PRS — it is to establish that **the KLAES version
cannot be validated against most of these numbers.** If we generate the reports from live data,
the output will not match the 2025 spreadsheets, and that is expected and correct. The audit
below is the evidence for saying so.

## Summary

| # | Table | Dept | Column totals foot? | Rows consistent? | Verdict |
|---|---|---|---|---|---|
| 01 | Survey layouts | Survey | ✓ | ✓ | **Sound** |
| 02 | Deed of Assignment | Deeds | 6 of 7 | ✗ | Male total wrong (+71); gender ≠ categories ≠ total |
| 03 | Deed of Mortgage | Deeds | 3 of 7 | ✗ | Four columns mis-totalled; a female record dropped |
| 04 | Bank ranking | Deeds | ✓ | ✓ | **Sound** — and reconciles to the mortgage total (61) |
| 05 | Certificate of Occupancy | Deeds | 5 of 7 | ✗ | Agriculture total 2 vs 22; Residential Aug–Dec fabricated |
| 06 | Resettlement allocation | Deeds | ✓ | ✗ (1 row) | Single broken row: March |
| 07 | Deed of Release | Deeds | ✓ | ✗✗✗ | **Land-use block is copied from CofO. Unusable.** |
| 08 | Deed Devolution | Deeds | 5 of 7 | ✗ | ±1 gender swap; gender meaningless for this instrument |
| 09 | Search | Deeds | ✓ | ✗ | Clean columns, but no month labels and no total column |
| 12 | Conversion applications | Land | n/a (1 row) | n/a | Sound as far as it goes; no monthly or gender detail |
| 13 | Direct allocation | Land | n/a (1 row) | n/a | Sound as far as it goes; totals reused wrongly in #14 |
| 14 | Direct allocation by gender | Land | — | ✗✗ | **Row labels transposed; a land-use total pasted into a gender cell** |
| 15 | OSS by size/purpose | Land | ✓ | ✓ | **Sound — the best table in either report** |
| 16 | OSS by gender | Land | ✓ | ✓ | **Sound — and reconciles to #15 in all 12 months** |

### The pattern

Quality tracks the **source system**, not the department. The two OSS tables (#15, #16) are
perfect because gender, land use and purpose live on one `oss_applications` row and were cut
from one dataset. The Deeds tables fail because each breakdown was counted separately by hand.
The Land Department's own hand-built matrix (#14) fails in exactly the same way as the Deeds
tables — so this is a *method* problem, not a departmental one, and generating from the database
fixes it everywhere.

## The four structural faults

### 1. Gender counts and land-use counts never agree — in any table

| Table | By gender | By land use | By Total column |
|---|---:|---:|---:|
| Assignment | 1,220 | 1,256 | 1,248 |
| Mortgage | 43 | 52 | 61 |
| CofO | 907 | 915 | 907 |
| Release | 49 | 906 | 97 |
| Devolution | 10 | 186 | 196 |
| Search | 125 | 181 | — |
| **OSS applications (Land)** | **2,315** | **2,315** | **2,315** ✓ |

These are three independent hand-counts of the same registrations. The root cause is almost
certainly that gender is only recorded for individual persons, so corporate, joint and estate
parties fall out of the gender count but stay in the land-use count. That is a legitimate
modelling fact — but it must be made **explicit**, not left as an unexplained discrepancy.

**The last row proves it is fixable.** The Land Department's OSS tables agree exactly — in the
annual total *and* in all twelve individual months — because both views were cut from one
dataset ([16](16-oss-applications-gender.md#arithmetic-check--fully-clean-and-it-cross-reconciles)).

**Fix:** in KLAES, derive every breakdown from one row set. Add an explicit
`Organisation / Estate / Not recorded` bucket to the gender dimension so the gender split always
sums to the total. Never let two breakdowns of the same measure disagree.

### 2. Copy-paste contamination between tables

**Two separate instances, one in each report.**

**(a) Deeds — Release ← CofO.** The Deed of Release land-use block *is* the CofO land-use block
(see [07](07-deed-of-release.md) for the side-by-side). CofO's own Residential column is flat at
75 for the last five months of the year, the tell that the block was pasted and then only partly
overwritten.

**(b) Land — gender matrix ← direct allocation columns.** The four row totals of the "Direct
Government Allocation Base on Gender" table are the four land-use column totals of the direct
allocation table, with **Agriculture and Industrial transposed** ([14](14-land-gender-allocation.md)):

| Direct allocation table | | Gender table row | | |
|---|---:|---|---:|---|
| Residential | 6,021 | DIRECT ALLOCATION | 6,026 | off by 5 |
| Commercial | 701 | DIRECT CONVERSION | 759 | male cell = 701 exactly |
| Agriculture | 58 | DIRECT **INDUSTRAIL** | 58 | **label swapped** |
| Industrial | 18 | DIRECT **AGRICULTURE** | 18 | **label swapped** |

**Fix:** generation from the database removes this class of error entirely. It is the single
strongest argument for the module.

### 3. Charts that plot totals alongside their own components

**Seven charts across both reports.** Assignment, CofO, Resettlement, Release and Devolution all
include a `TOTAL` group on the month axis, and several **stack** the Total series on top of the
components — so the annual bar reads double the true value (CofO's shows ~1,800 for a true 907;
Resettlement's ~12,000 for a true 6,047). The Land Department's Figures 4 and 5 repeat the fault
exactly: both plot SN row 13 (the TOTAL row) as a thirteenth category, and Figure 4 stacks
`TOTAL APP RECEIVED` on its own components for a bar reading ~4,600 against a true 2,315.

Additional chart faults, Land Department only:

- **Figures 4 and 5 label the X axis `1`–`13`** — the SN column — instead of month names, even
  though the month column is right there in the sheet.
- **Figure 3 sets a 0–100% axis on raw counts**, so every non-zero bar saturates at 100%, and
  takes its X labels from the male column (`5570`, `701`, `58`) instead of the Base column.
- **The conversion chart is printed twice**, and both copies sit under a heading announcing
  direct allocation.
- **The direct allocation chart plots the empty `S/NO` column as a category** and legends its
  series as `Series 1` / `Series 2`.
- **Both Land bar charts use 3-D perspective**, which distorts heights (residential reads ~5,800
  against a true 6,021) and flattens every small category into an unreadable lozenge.

**Fix:** months only on the category axis; annual figures as KPI tiles above the chart. Never
include a total as a series in a stacked chart. No 3-D. Set the category range to the label
column. Also: the mortgage 3-D pie (13 slices, one of which is the total) should be replaced with
a column chart, and **four** charts still carry the placeholder title "Diagram Title", plus axis
titles "X AXIS" / "Y AXIS".

### 4. Inconsistent dimensions across tables

**Six** different category vocabularies are used:

| Table | Categories |
|---|---|
| Survey layouts | Residential, Commercial, Industry, Facilities |
| Assignment | Commercial, Residential, **Organisation**, Joint |
| Mortgage | **Residential, Commercial** (order reversed), Organ, Joint |
| CofO / Release / Devolution | Commercial, Residential, Industry, Agriculture |
| Search | Commercial, Residential |
| Land conversion / direct allocation | Residential, Commercial, Agriculture, Industrial (+ **SIT**) |
| OSS applications | **High density, Low density**, Commercial, Industrial, **Small scale** |

Three distinct problems here:

- `Organisation` (Deeds) is a **party type**, not a land use. Mixing it into the land-use row is
  part of why the assignment and mortgage categories overshoot their totals.
- `High density` / `Low density` / `Small scale` (OSS) are **plot size/density**, not land use.
  Flattening them into the same row as Commercial and Industrial forces each application into
  exactly one bucket and makes it impossible to ask "how many high-density *commercial* plots?"
- `SIT` (direct allocation) is undefined — Site and Service? Sectional Titling? It is 0 for the
  year and appears nowhere else.
- The Land gender matrix ([14](14-land-gender-allocation.md)) mixes **application streams**
  (Direct Allocation, Direct Conversion) with **land uses** (Direct Industrial, Direct
  Agriculture) in one column, so its rows have no consistent grain and cannot legitimately be
  summed.

**Fix:** one canonical land-use enum (Residential, Commercial, Industrial, Agricultural,
Institutional/Facilities) used by every table, with **party type**, **plot density/size** and
**application stream** as three further separate dimensions — cross-tabbed, never flattened.

## Individual cells to query with PRS

| Table | Cell | Issue | Suggested resolution |
|---|---|---|---|
| Assignment | Male total | 1,087 printed, months sum to 1,016 | Confirm which is right |
| Mortgage | Female total | 0 printed, but August has 1 | Should be 1 |
| Mortgage | Residential / Commercial totals | 26 / 12 printed; months sum to 29 / 18 | Re-add |
| CofO | Agriculture total | 2 printed, months sum to 22 | Almost certainly a dropped digit → 22 |
| CofO | Female total | 32 printed, months sum to 35 | **35 is right** — it makes gender reconcile to 907 |
| CofO | Residential, Aug–Dec | Flat 75 five months running | Re-derive; suspected fabrication |
| Resettlement | March row | 39 + 19 = 58, total says 42 | One of the three cells is wrong |
| Release | All land-use columns | Copied from CofO | Discard and re-derive |
| Devolution | Male / Female totals | 7/3 printed, months sum to 8/2 | Single row mis-keyed |
| Search | Month column | Absent entirely | Confirm rows are Jan–Dec in order |
| Land report | Document title | Says "January to December **2026**" | Content is 2025 — correct the title |
| Land gender | Industrial / Agriculture rows | Labels transposed vs. the allocation table | Swap them back |
| Land gender | Direct Conversion, male = 701 | Equals the Commercial land-use total exactly | Re-derive the whole row |
| Land gender | Direct Allocation row total 6,026 | Allocation table says Residential 6,021 | Reconcile the 5-record gap |
| Land gender | Private Orga / Governmental | All-zero in a *government* allocation report | Almost certainly not captured |
| Direct allocation | `SIT` column | Undefined, 0 all year | Confirm meaning or drop |

## What is safe to quote from the 2025 reports

Only these figures survived checking and can be cited as-is:

**Fully sound — every figure, including monthly detail:**

- Survey: 5 layouts, **12,933 plots** (11,573 residential / 218 commercial / 1,142 industrial),
  18 facility sites.
- Mortgage: **61 facilities**, and the full bank ranking (Jaiz 31, Fidelity 18, Union 3, FCMB 3,
  Access 2, Unity 2, UBA 1, Stanbic IBTC 1) — these cross-reconcile.
- **OSS applications: the complete monthly tables** — 2,315 total, by size/purpose
  ([15](15-oss-applications-size-purpose.md)) and by gender
  ([16](16-oss-applications-gender.md)). Both are internally clean and reconcile to each other
  in all twelve months.

**Annual totals only** (the Total columns foot; the breakdowns beneath them do not):

- Assignment **1,248**, CofO **907**, Resettlement **6,047**, Release **97**, Devolution **196**.
- Search column totals: 123 M / 2 F / 72 commercial / 109 residential.
- Land: conversion **6,595** (5,982 R / 234 C / 311 Ag / 68 Ind), direct allocation **6,798**
  (6,021 R / 701 C / 58 Ag / 18 Ind) — single-row tables with no detail to be wrong.

**Do not quote at all:** the entire Deed of Release land-use breakdown, CofO Residential for
August–December, and the whole Land gender matrix ([14](14-land-gender-allocation.md)).

Everything else — monthly gender splits and monthly land-use splits in the Deeds report — should
be regenerated rather than reused.

## Cross-report ambiguities to resolve with PRS

These are not arithmetic errors; they are unstated relationships between figures that a reader
will inevitably try to connect.

1. **6,798 direct allocation applications (Land) vs. 6,047 allocations (Deeds).** Same year, same
   department authoring both, both called "allocation", never cross-referenced. One counts
   applications *received*, the other allocations *made* — but the reports don't say so, and the
   codebase distinguishes `Direct Allocation` / `OP Direct Allocation` / `OP Resettlement`
   precisely because they differ. See [13](13-land-direct-allocation.md).
2. **Three different "applications received" denominators in one Land report**: 6,595
   (conversion), 6,798 (direct allocation), 2,315 (OSS by size/purpose). The third is a third the
   size of the others with no explanation of scope.
3. **Applications vs. approvals is never distinguished anywhere.** No table states whether it
   counts submissions, approvals or completions. This must be explicit on the face of every
   generated table.
