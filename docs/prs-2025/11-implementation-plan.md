# 11 — Implementation Plan: PRS Annual Report Module

Goal: generate the [2025 PRS progress reports](README.md) from live KLAES data, for any year,
replacing the hand-keyed Excel workbooks.

## Scope

**Three departmental reports, fourteen sections.** Each section = one aggregate query + one
table + one chart.

**Survey** (1 section)
1. Layouts implemented **(needs a new data source; not derivable today)**

**Deeds** (8 sections)
2. Deed of Assignment 3. Deed of Mortgage 4. Bank facility ranking
5. Certificate of Occupancy 6. Occupancy permit / resettlement allocation
7. Deed of Release 8. Deed Devolution 9. Search

**Land** (5 sections)
10. Applications for conversion 11. Applications for direct allocation
12. Direct allocation by gender 13. OSS applications by size/purpose
14. OSS applications by gender

Plus: cover page, PRS narrative/observation blocks, PDF + Excel export.

Out of scope: the toner requisition appended to the Land report ([17](17-admin-annex.md)).

### Departments are report *scopes*, not separate modules

Build one module with a department dimension, not three. `Department` already exists as a model,
and the file-tracker work established a canonical 15-department vocabulary
(`DepartmentNormalizer`) — reuse it so "Survey", "Deeds" and "Lands" resolve to the same names
the rest of KLAES uses.

## Architecture

Follows the existing dashboard pattern in the codebase (controller → service → blade + JS chart),
not a new framework.

```
app/Http/Controllers/Prs/PrsAnnualReportController.php   index, section(), export()
app/Services/Prs/
    PrsReportAggregator.php      orchestrates; one method per section, returns a common DTO
    DeedRegistrationStats.php    assignment / mortgage / release / devolution (shared union query)
    CofOStats.php
    OccupancyPermitStats.php
    SearchStats.php
    LandApplicationStats.php     conversion / direct allocation / OSS (shared oss_applications query)
    SurveyLayoutStats.php        reads the new survey_layouts table
    BankNameNormalizer.php       free-text mortgagee → canonical bank
resources/views/prs/annual_report/
    index.blade.php
    partials/section-table.blade.php    one partial, driven by the DTO
    partials/section-chart.blade.php
public/js/prs/annual-report.js
```

### One shape for every section

Every table in both reports is the same shape: 12 month rows + total, a gender split, a
category split, and a total. Build **one** DTO and **one** table partial rather than fourteen
bespoke views:

```php
PrsSection {
    string  $key;            // 'deed_assignment'
    string  $department;     // 'Deeds' | 'Survey' | 'Lands'
    string  $title;
    string  $measure;        // 'applications_received' | 'transactions_registered' | 'allocations_made'
    bool    $monthly;        // false for the Land conversion/allocation annual-row sections
    array   $months;         // [ ['month'=>1,'label'=>'January','gender'=>[...],'landuse'=>[...],'total'=>108], ... ]
    array   $genderKeys;     // ['male','female','joint','organisation','government','not_recorded']
    array   $landUseKeys;    // ['residential','commercial','industrial','agricultural','institutional']
    array   $densityKeys;    // ['high','low','small_scale']  — OSS sections only, separate dimension
    array   $totals;
    array   $notes;          // caveats surfaced in the rendered report
}
```

`$measure` is not decoration. Three sections count applications *received*, others count
transactions *registered* or allocations *made*, and the source reports never distinguish them —
which is why 6,798 and 6,047 look like a contradiction
([13](13-land-direct-allocation.md#klaes-data-source)). Render it on the face of every table.

`$monthly = false` handles the two Land sections that exist only as an annual row today. The
underlying `oss_applications` data supports a monthly cut, so the module can **improve on the
source** here — but confirm PRS want that before changing the shape of their report.

### The deed union

All four deed sections share one query. The three-table union already exists and works in
[MortgageController.php:48-124](app/Http/Controllers/MortgageController.php#L48-L124) —
`instrument_capture` ∪ `pra` ∪ `file_history_staging`, `is_deleted` guarded, with
`deed_registrations.deeds_date` as the authoritative transaction date. **Extract that union into
`DeedRegistrationStats` and parameterise the instrument type** rather than writing it a fifth
time.

Canonical instrument types (from
[InstrumentRegistrationController.php:1036-1043](app/Http/Controllers/InstrumentRegistrationController.php#L1036-L1043)
and [InstrumentTypeController.php:110](app/Http/Controllers/InstrumentTypeController.php#L110)):

| Section | Filter |
|---|---|
| Assignment | `instrument_type = 'Deed of Assignment'` |
| Mortgage | `instrument_type IN ('Deed of Mortgage','Tripartite Mortgage')` |
| Release | `'Deed of Surrender and Release'` — **verify variants**, PRS calls it "Deed of Release" |
| Devolution | `'Devolution Order'` — **verify variants** (Letters of Administration? Vesting Assent?) |

### The Land applications query

All five Land sections come from **one table**: `oss_applications`
([app/Models/LandsOneStopShopApplication.php](app/Models/LandsOneStopShopApplication.php)), which
carries `application_type`, `land_use`, `purpose`, `prev_land_purpose` and `sex` on a single row.

| Section | Filter / pivot |
|---|---|
| Conversion | `application_type = 'Conversion'`, pivot by land use |
| Direct allocation | `application_type = 'Direct Allocation'`, pivot by land use |
| Allocation by gender | same rows, pivot by `sex` × `application_type` |
| OSS by size/purpose | all rows, pivot by density/purpose |
| OSS by gender | all rows, pivot by `sex` |

`application_type` is already validated against the enum `Direct Allocation, Conversion` at
[CommissionNewSTController.php:335](app/Http/Controllers/CommissionNewSTController.php#L335).

**This is the key structural point of the whole plan:** sections 12–16 must be produced by **one
query pivoted five ways**, never five independent counts. That single decision is what makes the
gender and land-use views reconcile — the source proves it both ways round, since the two OSS
tables cut from one dataset agree in all twelve months
([16](16-oss-applications-gender.md)) while the hand-built gender matrix from the same department
has transposed labels and a land-use total pasted into a gender cell
([14](14-land-gender-allocation.md)).

Do not build the Land report on top of the OSS applications *page* — that page is known to crash
Chrome (1,818 districts × ~23 duplicated selects ≈ 42k option nodes). Aggregate server-side.

## Decisions required before coding

These change the output materially. Do not guess.

| # | Question | Why it matters |
|---|---|---|
| D1 | **Which date buckets a record into a month** — transaction/deeds date, registration date, or capture date? | Back-captured historical records will pile onto their capture month and invent spikes. Use `deeds_date`, fall back to `reg_date`, then `created_at`, and record which was used per row. |
| D2 | **What is the gender of a registration?** Grantee? Applicant? First party? | `gender` lives on `file_indexings` / `mls_file_no`, **not** on the deed tables — it needs a join through the file, and the join key must be agreed. |
| D3 | **Canonical land-use vocabulary** | Source uses four different vocabularies (see [10](10-data-quality-audit.md#4-inconsistent-dimensions-across-tables)). Need one enum + a mapping from whatever `land_use` actually contains. |
| D4 | **Does "Search" mean formal search applications or system lookups?** | ~181/year is far too low for lookups; almost certainly `FileSearchRequest` records with a paid/completed status. |
| D5 | **Does the resettlement figure cover direct allocation too, or resettlement only?** | Section title and table title disagree. If both, split into two series. |
| D6 | **Where does layout data come from?** | Nothing in KLAES holds layout plot inventories. Needs a new `survey_layouts` table with Survey Dept data entry. |
| D7 | **Do conversions live in `change_of_purpose_applications`, `oss_applications` with `application_type='Conversion'`, or both?** | If both, which is authoritative — and do they agree? Getting 6,595 out of either is the first validation test. Also: does PRS count the *previous* or the *new* purpose? `prev_land_purpose` / `new_purpose` both exist, so KLAES can report the full from→to matrix. |
| D8 | **Is Land's 6,798 "direct allocation" the same population as Deeds' 6,047 "resettlement allocation"?** | Same year, same authoring department, both called allocation, never cross-referenced. `mls_file_no.source` already distinguishes `Direct Allocation` / `OP Direct Allocation` / `OP Resettlement` ([MlsFileNoController.php:1484-1485](app/Http/Controllers/MlsFileNoController.php#L1484-L1485)) — the report must make the same distinction. |
| D9 | **Which column holds plot density (High / Low / Small scale)?** | May be stored, or derived from plot dimensions — if derived, the banding rule must be captured explicitly. Blocks section 13. |
| D10 | **What is `SIT`?** | A column on the direct allocation table, 0 all year, appears nowhere else. Site and Service? Sectional Titling? Confirm or drop. |
| D11 | **Does `oss_applications.sex` have nulls?** | The source's perfect gender reconciliation ([16](16-oss-applications-gender.md)) implies every application has a gender. If nulls exist in the data, unknowns are being coerced into Male and the 3% female share is overstated. |

I'd resolve D1, D2 and D4 with the Deeds Registry and PRS in one sitting — they gate everything.
D7 and D8 are the Land equivalents and can go in the same meeting. D9–D11 are quick schema
checks I can do against the database directly, no meeting needed.

## Fixes to carry over from the audit

Non-negotiable improvements over the spreadsheet, all traced in
[10-data-quality-audit.md](10-data-quality-audit.md):

1. **One row set, many breakdowns.** Gender and land-use splits must both sum to the section
   total. Add explicit `Organisation` / `Estate` / `Not recorded` buckets to gender so it always
   reconciles; add `Uncategorised` to land use.
2. **Party type ≠ land use ≠ plot density ≠ application stream.** Four separate dimensions,
   cross-tabbed, never flattened into one row. `Organisation` moves out of the Deeds land-use
   row; `High/Low density` and `Small scale` move out of the OSS land-use row; the Land gender
   matrix stops mixing streams (Direct Allocation, Direct Conversion) with land uses (Direct
   Industrial, Direct Agriculture) in one column.
3. **Totals are never chart series, and never categories.** Months on the axis, annual figures
   as KPI tiles. This also fixes the Land Figures 4 and 5, which plot SN row 13 as a
   thirteenth month.
4. **Charts take their category range from the label column.** Three source charts label the X
   axis `1`–`13` or with raw values because the range was set to the wrong column.
5. **No 3-D.** Both Land bar charts use it; it misreads heights by ~200 units and flattens every
   small category into an unreadable lozenge.
6. **Drop gender from Devolution** (5% coverage; the instrument transmits to estates and heirs)
   or replace it with beneficiary type.
7. **Zero rows are rendered explicitly** — a bank with no facilities shows `0`, so absences like
   Federal Mortgage Bank are visible rather than inferred. Same for `Private Organisation` /
   `Governmental` in the Land gender matrix, which are all-zero and probably just uncaptured.
8. **Every table states its measure** (`applications received` vs `registered` vs `allocated`)
   and its date basis on its face.
9. Every generated table carries its own footnote block (`$notes`) stating the date basis, the
   join used for gender, and any coverage caveat.

## Charts

**Tooling is settled in [18-reporting-stack.md](18-reporting-stack.md): Chart.js 4.4.0, pinned
and bundled via npm rather than CDN.** Note the dompdf trap documented there — dompdf does not
execute JS or rasterise `<canvas>`, so PDF export must round-trip
`chart.toBase64Image()` output as inline PNGs or every chart exports blank.

Load the `dataviz` skill before writing chart code. Corrections required per section:

| Section | Source chart | Replacement |
|---|---|---|
| Survey layouts | Clustered bar incl. plot total | Stacked bar of the 3 categories (sums to total by definition); facilities shown separately |
| Assignment | Clustered column + TOTAL group | Stacked column, months only |
| Mortgage | 3-D pie, 13 slices incl. total | Column chart; volumes are small — consider quarterly |
| Bank ranking | Unsorted vertical column | **Horizontal bar, sorted descending** (long names, natural ranking) |
| CofO | Stacked column with total stacked on components | Stacked column of 4 categories, months only |
| Resettlement | Stacked column with total | Stacked column, Male/Female only (Joint is empty) |
| Release | Contaminated | Rebuild from real data once re-derived |
| Devolution | Clustered column, labels colliding | Stacked column; agriculture is 83%, so keep the other 3 legible |
| Search | Two broken charts, numeric X labels | One column chart with month names |
| Land conversion | 3-D bar, printed twice, no labels | One 2-D horizontal bar with data labels; 91/4/3/1 split needs an inset for the minor categories |
| Land direct allocation | 3-D bar, `S/NO` plotted, `Series 1/2` legend | 2-D horizontal bar, range excluding `S/NO` |
| Land gender matrix | 0–100% axis on raw counts, values as X labels | Horizontal stacked bar per stream; percentage axis only if values are actually converted to shares |
| OSS size/purpose | Stacked column, X = `1`–`13`, total stacked on components | Months on X, 5 categories only; high density is 96% so give the other four their own small-multiple |
| OSS gender | Column, X = `1`–`13`, total as 13th category | Months only; at 33:1 plot female share as a % line or its own panel |

## Phasing

**Phase 1 — Land OSS sections (13 + 14) and Mortgage/bank ranking.**

Start with the Land OSS pair, not Deeds. They are the two cleanest tables in the source, they
come from a single table with gender and category on the same row, and they already reconcile —
so they validate the "one query, many pivots" architecture end to end with data we can actually
check against. Ship the section shell (DTO, table partial, chart partial) with them.

Mortgage and bank ranking come alongside: the union query, instrument filter and DataTable
already exist in `MortgageController`, and the bank ranking is the only *Deeds* reconciliation
in the source that holds (61 ↔ 61).

**Phase 2 — Land conversion, direct allocation, gender matrix (sections 12, 13, 14).** Same
`oss_applications` query, different pivots. Blocked on D7, D9, D10.

**Phase 3 — Assignment, CofO, Devolution, Release.** Same deed union, different filters; blocked
on D1–D3.

**Phase 4 — Resettlement and Search.** OP data has the `op_batch` distortion to handle (see
[06](06-resettlement-allocation.md)) and the D8 overlap with Land's direct allocation to settle;
Search is blocked on D4.

**Phase 5 — Survey layouts.** New table, new data-entry screen, Survey Dept backfill for 2025.

**Phase 6 — Narrative, exports, year selector, department scoping.** The PRS observation blocks
are authored text, so the module needs an editable narrative field per section, not just charts.
Export must produce all three departmental reports separately as well as combined.

Export priority is **Word first, then Excel, then PDF** — see
[18-reporting-stack.md](18-reporting-stack.md#word--the-format-prs-actually-use). PRS author
these in Word today and need to add commentary; a PDF-only module means they keep maintaining a
hand-keyed Word copy alongside it, which reintroduces exactly the transcription errors this
module exists to eliminate.

## Validation

The generated report **will not match most of the 2025 spreadsheet**, and should not be expected
to — [10](10-data-quality-audit.md) documents why. Validate instead against:

**Tier 1 — must match exactly.** These source tables are fully sound, including monthly detail,
so a mismatch means our query is wrong, not the spreadsheet:

- **OSS applications: all twelve months of both tables**, totalling 2,315 — by size/purpose
  ([15](15-oss-applications-size-purpose.md)) and by gender ([16](16-oss-applications-gender.md)).
  This is the strongest test available in either report.
- Survey: 12,933 plots across 5 layouts.
- Mortgage 61 ↔ bank ranking 61.

**Tier 2 — annual totals only** (the Total columns foot; the breakdowns beneath them don't):
Assignment 1,248, CofO 907, Resettlement 6,047, Release 97, Devolution 196, conversion 6,595,
direct allocation 6,798.

**Tier 3 — structural invariants the module must satisfy everywhere**, regardless of what the
source did:

- For every section and every month: gender total = category total = section total.
- Every land-use figure resolves to the canonical enum; no section invents a vocabulary.
- No chart contains a total as a series or as a category.

The spreadsheet fails all three invariants across most of its tables; the module must pass them
across all of them.

Where a generated figure differs materially from the 2025 report, that difference is a finding
about the old process — capture it rather than tuning the query to reproduce the old number. The
Deed of Release land-use split and the Land gender matrix are *expected* to differ wildly,
because the source figures there are known to be wrong.
