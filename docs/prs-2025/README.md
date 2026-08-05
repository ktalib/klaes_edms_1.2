# PRS Annual Progress Report 2025 — Source Data & Implementation Notes

Transcription of the **2025 progress reports** prepared by the *Department of Planning, Research
and Statistics* (PRS), covering three departments:

- **Survey Department** — layouts implemented
- **Deed Department** — assignment, mortgage, CofO, resettlement, release, devolution, search
- **Land Department** — conversion, direct allocation, OSS applications

Source: 23 screenshots of the original spreadsheet/Word reports supplied by the client (13 for
Survey/Deeds, 10 for Land). Everything here is transcribed **verbatim from those images**,
including the errors. Nothing was silently corrected — every arithmetic inconsistency is recorded
in [10-data-quality-audit.md](10-data-quality-audit.md) and flagged inline in each dataset file.

> **Note on the Land Department document header:** it reads *"FROM JANUARY TO DECEMBER 2026"*
> while every table, caption and chart inside says 2025. The content is 2025; the title is a
> typo. See [12](12-land-conversion-applications.md).

## Goal

Reproduce these reports inside KLAES as a generated **PRS Annual Report** module, so that PRS no
longer hand-keys them in Excel. Each dataset file below documents: the exact table as it appears
today, the chart that accompanies it, and the KLAES data source that should feed it.

## Files

### Survey & Deed Departments

| File | Contents |
|---|---|
| [01-survey-layouts.md](01-survey-layouts.md) | Survey Dept — layouts implemented in 2025 (5 layouts, plot categories) |
| [02-deed-assignment.md](02-deed-assignment.md) | Deed of Assignment registrations by month |
| [03-deed-mortgage.md](03-deed-mortgage.md) | Deed of Mortgage registrations by month |
| [04-bank-facility-ranking.md](04-bank-facility-ranking.md) | Mortgagee bank ranking + PRS written observation |
| [05-certificate-of-occupancy.md](05-certificate-of-occupancy.md) | Certificates of Occupancy registered by month |
| [06-resettlement-allocation.md](06-resettlement-allocation.md) | Occupancy permit — direct allocation & resettlement |
| [07-deed-of-release.md](07-deed-of-release.md) | Deed of Release registrations by month |
| [08-deed-devolution.md](08-deed-devolution.md) | Deed of Devolution registrations by month |
| [09-search.md](09-search.md) | Legal/official searches conducted by month |

### Land Department

| File | Contents |
|---|---|
| [12-land-conversion-applications.md](12-land-conversion-applications.md) | Applications for conversion — 6,595 |
| [13-land-direct-allocation.md](13-land-direct-allocation.md) | Applications for direct allocation — 6,798 |
| [14-land-gender-allocation.md](14-land-gender-allocation.md) | Direct government allocation by gender — **row labels transposed** |
| [15-oss-applications-size-purpose.md](15-oss-applications-size-purpose.md) | OSS applications by plot size/purpose — 2,315 — **fully clean** |
| [16-oss-applications-gender.md](16-oss-applications-gender.md) | OSS applications by gender — 2,315 — **fully clean, cross-reconciles** |
| [17-admin-annex.md](17-admin-annex.md) | Toner requisition appended to the report — out of scope |

### Cross-cutting

| File | Contents |
|---|---|
| [10-data-quality-audit.md](10-data-quality-audit.md) | **Read before implementing** — every broken total, copy-paste artefact and structural fault, across all three departments |
| [11-implementation-plan.md](11-implementation-plan.md) | Proposed KLAES module: routes, services, queries, charts, phasing |
| [18-reporting-stack.md](18-reporting-stack.md) | Tooling: Chart.js / dompdf / maatwebsite / PhpWord — all already installed, no new packages |
| [19-ui-caveat-log.md](19-ui-caveat-log.md) | **The caveats the UI no longer shows.** Per-section quality ratings and warnings, moved out of the screen — read before quoting any figure off the report page |
| [20-live-data-implementation.md](20-live-data-implementation.md) | **Gap audit against the live database.** What KLAES can actually answer today, measured — the gender gap, the missing reporting year, and three corrections to [11](11-implementation-plan.md) |

## Report structure (as delivered)

```
PROGRESS REPORT FOR SURVEY DEPARTMENT — JAN TO DECEMBER 2025
  └─ Layout implemented (table + clustered bar chart)

PROGRESS REPORT FOR DEED DEPARTMENT — JAN TO DECEMBER 2025
  ├─ Deed of Assignment registration      (table + clustered bar chart)
  ├─ Deed of Mortgage registration        (table + 3-D pie chart) + OBSERVATION
  ├─ Bank ranking based on facility       (table + column chart)
  ├─ Certificate of Occupancy registered  (table + stacked column chart)
  ├─ Occupancy permit direct allocation & resettlement (table + stacked column chart)
  ├─ Deed of Release                      (table + stacked column chart)
  ├─ Deed Devolution                      (table + clustered column chart)
  └─ Search                               (table + stacked column chart)

PROGRESS REPORT FOR LAND DEPARTMENT — JAN TO DECEMBER 2025 (headed "2026" in error)
  ├─ Application for conversion           (annual row + 3-D bar chart, printed twice)
  ├─ Application for direct allocation    (annual row + 3-D bar chart)
  ├─ Direct govt allocation by gender     (4×5 matrix + broken 3-D chart)
  ├─ Applications by size and purpose     (Fig. 4 — monthly table + stacked column)
  ├─ Applications by gender               (Fig. 5 — monthly table + column chart)
  └─ [annex] toner cartridge requisition
```

## Annual figures at a glance

| Measure | 2025 | Source |
|---|---:|---|
| Survey plots laid out | 12,933 | [01](01-survey-layouts.md) |
| Occupancy permits allocated | 6,047 | [06](06-resettlement-allocation.md) |
| Direct allocation applications | 6,798 | [13](13-land-direct-allocation.md) |
| Conversion applications | 6,595 | [12](12-land-conversion-applications.md) |
| OSS applications received | 2,315 | [15](15-oss-applications-size-purpose.md) |
| Deeds of assignment | 1,248 | [02](02-deed-assignment.md) |
| Certificates of Occupancy | 907 | [05](05-certificate-of-occupancy.md) |
| Devolutions | 196 | [08](08-deed-devolution.md) |
| Searches | ~181 | [09](09-search.md) |
| Deeds of release | 97 | [07](07-deed-of-release.md) |
| Mortgages | 61 | [03](03-deed-mortgage.md) |

⚠ These are **not** all the same kind of measure — some count applications *received*, others
transactions *completed*. They must never be totalled or charted together without labelling
which is which. See [13](13-land-direct-allocation.md) on the 6,798 vs 6,047 ambiguity.

## Cross-cutting conventions in the source

- **Period**: calendar months January–December 2025, plus a TOTAL row.
- **Gender**: every deed table splits the applicant/party into `M` / `F` columns. A `JOINT`
  column exists on some tables and is `0` throughout the entire report.
- **Land use categories**: not consistent across tables. **Six different vocabularies** are in
  use across the three departments — Assignment uses Commercial/Residential/Organisation/Joint;
  CofO, Release and Devolution use Commercial/Residential/Industry/Agriculture; Mortgage uses
  Residential/Commercial/Organ/Joint; Survey uses Residential/Commercial/Industry/Facilities;
  Land conversion/allocation uses Residential/Commercial/Agriculture/Industrial(/SIT); OSS uses
  High density/Low density/Commercial/Industrial/Small scale. A single canonical land-use
  vocabulary is required — see [11-implementation-plan.md](11-implementation-plan.md).
- **Gender totals never reconcile with category totals** on any *Deed* table. They are two
  independent counts of the same registrations, and in the source they disagree. This is the
  single biggest thing to fix in the KLAES version.
- **The Land Department's OSS tables are the exception and the model to follow.**
  [15](15-oss-applications-size-purpose.md) and [16](16-oss-applications-gender.md) reconcile
  perfectly — same 2,315 total, agreeing in all twelve months — because gender, land use and
  purpose all live on the same `oss_applications` row. Every other section should be built the
  same way: one query, two pivots.
- **Female share sits between 3% and 7.3% in every table that measures it**, across all three
  departments. Consistent enough across independent sources to be a real finding, and it
  deserves its own section rather than a column in each table.
