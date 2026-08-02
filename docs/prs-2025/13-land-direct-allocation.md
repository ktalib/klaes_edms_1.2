# 13 — Land Department: Applications for Direct Allocation (2025)

> Source image 4. Caption: *"Figure 2: Bar chart Prersent application received for Direct allcation 2025"*

## Table (verbatim)

| S/NO | Residential | Commercial | Agriculture | Industrial | SIT |
|---|---:|---:|---:|---:|---:|
| **TOTAL** | **6,021** | **701** | **58** | **18** | **0** |

Annual total (not printed): **6,798**.

`SIT` is presumably *Site and Service* (or Sectional Titling). It is **0** for the year, and
appears in no other table in either report — confirm what it means before carrying it forward.

Like conversion, this is a single annual row: no monthly and no gender breakdown.

## Notable figures

- **6,798 direct allocation applications in 2025** — slightly more than conversion (6,595). The
  two streams together are ~13,400 applications.
- **Residential 88.6%** (6,021), Commercial 10.3% (701), Agriculture 0.9% (58),
  Industrial 0.3% (18).
- The category mix differs sharply from conversion: direct allocation has **3× the commercial
  share** (10.3% vs 3.5%) and one fifth the agriculture share (0.9% vs 4.7%). That is a real and
  reportable contrast between the two streams — the narrative doesn't currently draw it out.

## ⚠ These totals reappear, mislabelled, in the gender table

The four category totals here (6,021 / 701 / 58 / 18) turn up as the four **row** totals of the
"Direct Government Allocation Base on Gender" table — with the Agriculture and Industrial labels
swapped. See [14-land-gender-allocation.md](14-land-gender-allocation.md) for the full
side-by-side. Whichever table is wrong, they cannot both be right.

## Chart (image 4)

Title *"APPLICATION FOR DIRECT ALLOCATION"*, 3-D bar. Faults:

1. **`S/NO` is plotted as a category.** The empty spreadsheet column was included in the chart
   range, so the X axis reads `S/NO, RESIDENTIAL, COMMERCIAL, AGRICULTURE, INDUSTRIAL, SIT` with
   a phantom bar at the left.
2. **Legend reads `Series 1` / `Series 2`** — the header row was not picked up, and a second
   empty series is being plotted.
3. Same 3-D distortion as the conversion chart: the residential bar reads ~5,800 against a true
   6,021, and Agriculture (58) / Industrial (18) / SIT (0) are all flat lozenges,
   indistinguishable from one another.
4. No data labels.

Rebuild as a 2-D horizontal bar, data range excluding `S/NO`, with labels.

## KLAES data source

`Direct Allocation` is already a first-class value in the codebase:

- [CommissionNewSTController.php:335](app/Http/Controllers/CommissionNewSTController.php#L335) —
  `application_type` validated `in:Direct Allocation,Conversion`.
- `mls_file_no.source` distinguishes **`Direct Allocation`** from **`OP Direct Allocation`** and
  `OP Resettlement`; the disambiguation rules are documented at
  [MlsFileNoController.php:1484-1485](app/Http/Controllers/MlsFileNoController.php#L1484-L1485)
  (*"Direct Allocation + Allocation List → Direct Allocation"*, *"Direct Allocation + Default +
  Direct Allocation → OP Direct Allocation"*).

**Question to settle (D8): is this 6,798 the same population as the Deeds report's 6,047
resettlement/occupancy-permit allocations?** ([06](06-resettlement-allocation.md)) The two
reports come from the same PRS department, cover the same year, and both describe "allocation",
but the numbers differ and neither references the other. The codebase distinguishes
`Direct Allocation` / `OP Direct Allocation` / `OP Resettlement` precisely because these are
different things — the reports need to make the same distinction explicit, or a reader will
assume 6,798 and 6,047 are two attempts at one number.

Note also that this table counts **applications received**, whereas the Deeds report's 6,047
counts **allocations made**. Applications ≠ approvals, and the module should never let those two
measures share a chart without labelling which is which.
