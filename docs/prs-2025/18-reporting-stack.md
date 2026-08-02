# 18 — Reporting Stack for the PRS Annual Report Module

**Headline: nothing new needs installing.** Every capability the module requires — charts,
PDF, Excel, Word, server-side tables, scheduling — is already a dependency of this project and
already in use somewhere in the codebase. The job is to pick the established option in each
category and use it consistently, not to add tooling.

## Decisions

| Need | Use | Status in project |
|---|---|---|
| Charts | **Chart.js 4.4.0** | Already dominant — 13 files vs 6 for ApexCharts |
| PDF export | **barryvdh/laravel-dompdf ^2.0** | Installed, used in 5+ controllers |
| Excel export | **maatwebsite/excel ^3.1** | Installed; one working example to copy |
| Word export | **phpoffice/phpword ^1.4** | Installed, used in `MemoController` |
| Aggregate tables | **Plain Blade** | DataTables is overkill for 13-row tables |
| Drill-down lists | **yajra/laravel-datatables ^10.11** | Established pattern across the app |
| Frontend | **Blade + Alpine 3 + Tailwind 3**, built by laravel-mix | Project standard |
| Scheduling | **Laravel scheduler** | Pattern exists in `ReportService::scheduleReport()` |

## Charts — Chart.js, and pin the version

The codebase currently runs **two** charting libraries:

- **Chart.js** — 13 files, including [public/js/file-tracker-dashboard.js](public/js/file-tracker-dashboard.js),
  [public/js/commissioner-dashboard.js](public/js/commissioner-dashboard.js),
  [resources/views/ops_dashboard/index.blade.php](resources/views/ops_dashboard/index.blade.php),
  [resources/views/legal_search/js.blade.php](resources/views/legal_search/js.blade.php)
- **ApexCharts** — 6 files, including [resources/views/dashboard/super_admin.blade.php](resources/views/dashboard/super_admin.blade.php)
  and the `programmes` report partials

**Use Chart.js.** It's the majority, and the dashboards nearest to this work (file tracker, ops,
legal search) all use it.

### Two problems to fix while we're here

**1. Version fragmentation.** Four different specs are loaded across the app:

```
https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js   ×5
https://cdn.jsdelivr.net/npm/chart.js                           ×5   ← unpinned, floats to latest
https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js ×2
https://cdn.jsdelivr.net/npm/chart.js@4                          ×1
```

The unpinned ones are a latent breakage: Chart.js 3 → 4 changed the options API, so a page
written against 3.9.1 that resolves to 4.x renders wrong or not at all.

⚠ **Correction after building the UI:** [layouts/app.blade.php:44](resources/views/layouts/app.blade.php#L44)
loads **Chart.js 3.9.1 globally**, for every page in the app. Moving to 4.4.0 is therefore a
*coordinated upgrade*, not a drop-in — a page cannot load its own 4.x alongside it without
clashing on the global `Chart` symbol.

The PRS module is consequently written against **3.9.1**, the version actually present. The
v3/v4 difference that bites here is scale borders: v3 uses `grid.drawBorder` / `grid.borderColor`,
while the `scale.border` object is v4-only and is silently ignored on v3 — so v4-style config
fails quietly rather than loudly. Consolidating the app on one pinned version remains the right
call, but it is its own task with its own regression surface across the 13 existing chart pages.

**2. CDN dependency.** Every chart in the app loads from `cdn.jsdelivr.net`. For a ministry with
intermittent connectivity, that means **the annual report renders chartless whenever the link is
down**. `chart.js` is not in [package.json](package.json) — add it as an npm dependency and
bundle it through laravel-mix, which the project already uses.

```bash
npm install chart.js@4.4.0
```

Do not design the charts from this file — **load the `dataviz` skill before writing chart
code**, and follow the chart-form corrections already specified per section in
[11-implementation-plan.md](11-implementation-plan.md#charts).

## PDF — dompdf, with one significant gotcha

[barryvdh/laravel-dompdf](composer.json) `^2.0` is installed and used via
`\Barryvdh\DomPDF\Facade\Pdf::loadHTML()` in
[app/Services/ReportService.php:181](app/Services/ReportService.php#L181), plus `loadView` in
`FileIndexingController`, `ProgrammesController`, `PhsAdminController` and others.

### ⚠ dompdf cannot render Chart.js charts

dompdf renders HTML and CSS. It does **not** execute JavaScript, and it does not rasterise
`<canvas>`. A Blade view that draws charts client-side will export a PDF with **blank spaces
where every chart should be**. This is the single biggest technical trap in this module — a
report whose entire point is nine charts.

**Recommended fix — client-side rasterisation.** Chart.js exposes
`chart.toBase64Image('image/png', 1)`. On export, serialise every chart to a data URI, POST them
with the export request, and have the Blade PDF template emit `<img src="{{ $chartPng }}">`.
dompdf handles inline base64 PNGs fine.

This keeps **one** chart definition feeding both the screen and the PDF, which matters — the
source reports' charts diverged from their tables precisely because they were maintained
separately.

Alternatives, for the record:
- *Server-side SVG generation* — a second chart implementation to keep in sync. Rejected.
- *Headless Chrome (Browsershot)* — best output, but adds a Node/Chromium dependency to a
  WAMP-hosted production box. Rejected as disproportionate.

Note there is **no `config/dompdf.php`** — the module needs A4 landscape for the wide monthly
tables (13 columns on the Deeds tables), so publish the config or pass paper options per call:

```php
Pdf::loadView('prs.annual_report.pdf', $data)->setPaper('a4', 'landscape');
```

## Excel — maatwebsite/excel, and do not copy `ReportService`

`maatwebsite/excel ^3.1` and `phpoffice/phpspreadsheet 1.29` are both installed.

**Follow [app/Exports/ActivityMonitoringExport.php](app/Exports/ActivityMonitoringExport.php)** —
the one working example, implementing `FromArray`, `WithHeadings`, `WithStyles`.

**Do not follow `ReportService::generateExcel()`.** Read
[app/Services/ReportService.php:287-297](app/Services/ReportService.php#L287-L297): it checks for
the Excel class, and then — despite the package being installed — writes a **CSV renamed to
`.xlsx`**. Users get a file Excel warns about on open. That method is a stub; the comment
*"Full Excel support would require the library to be installed"* is stale, because the library
*is* installed.

For this module, one `PrsAnnualReportExport` implementing `WithMultipleSheets` — one sheet per
section — matches how PRS actually work in Excel today.

## Word — the format PRS actually use

`phpoffice/phpword ^1.4` is installed and used in
[app/Http/Controllers/MemoController.php](app/Http/Controllers/MemoController.php) and
[PrimaryActionsController.php](app/Http/Controllers/PrimaryActionsController.php).

**Worth prioritising over PDF.** The source documents are Word files with editable narrative
blocks ("OBSERVATION", the PRS commentary in
[03-deed-mortgage.md](03-deed-mortgage.md#prs-observation-verbatim-from-image-6)). If the module
only emits PDF, PRS will keep maintaining a parallel Word document by hand to add commentary —
and we're back to hand-keyed figures. A `.docx` export with generated tables and charts plus
editable narrative sections lets them keep their workflow and lose the transcription errors.

I'd ship Word **and** Excel in Phase 6, and treat PDF as the third priority.

## Tables — plain Blade, not DataTables

`yajra/laravel-datatables-oracle ^10.11` plus `datatables.net 2.3.4` (npm) are the project
standard for list views, and the module's drill-downs ("show me the 61 mortgages behind this
number") should use them.

But the **report tables themselves are 13 rows** with a fixed 12-month shape. DataTables adds
search, paging and sort that are all meaningless here, plus a client-side dependency on a table
that must print. Render them as plain Blade `<table>` from the `PrsSection` DTO
([11-implementation-plan.md](11-implementation-plan.md#one-shape-for-every-section)) — one
partial, fourteen sections, and it prints and exports without special-casing.

## Frontend — Blade + Alpine, not Livewire

[package.json](package.json): Alpine 3, Tailwind 3, axios, laravel-mix. That's the stack.

**Do not use Livewire here.** `livewire/livewire ^2.12` is in composer, but it appears in exactly
**one** Blade view in the entire application — it is effectively unused, and a reporting module
would be a poor place to introduce a second interactivity paradigm. Year selector and department
filter are a form submit or a small Alpine component.

## Query layer — nothing extra needed

Aggregation is plain Laravel query builder against the `sqlsrv` connection, exactly as
[MortgageController.php:48-124](app/Http/Controllers/MortgageController.php#L48-L124) already
does for the three-table deed union. `doctrine/dbal ^4.2` is present for schema operations (the
new `survey_layouts` migration).

One performance note: these are twelve-month aggregates over `instrument_capture` ∪ `pra` ∪
`file_history_staging`. Group in SQL, not in PHP collections — and be aware of the sargability
trap already documented for this codebase (`UPPER(file_number)` defeated an index seek in the
file-log table; SQL Server's CI collation makes the wrapper unnecessary).

Cache each generated section — a closed year never changes, so
`Cache::remember("prs:{$year}:{$section}", ...)` with invalidation on the underlying capture
tables is sufficient. No reporting/OLAP layer is warranted at this data volume.

## Scheduling

[ReportService.php:334-420](app/Services/ReportService.php#L334-L420) already implements
`scheduleReport()`, `calculateNextRun()`, `sendScheduledReports()`, `getReportHistory()` and
`cleanupOldReports()`.

**Reuse the shape, not the code** — `ReportService` is hardcoded to `UserActivityLog`
([line 80](app/Services/ReportService.php#L80)) and is not a generic reporting framework despite
the name. Its format-switch / schedule / history / cleanup structure is a sound template for
`PrsReportAggregator`.

## What we are explicitly not adding

| Rejected | Why |
|---|---|
| Power BI / Metabase / Superset | External BI tool for 14 fixed tables PRS already know the shape of; adds hosting, licensing and a second security boundary |
| Laravel Nova / Filament | Whole admin framework for one report module |
| ApexCharts (as the choice) | Real and works, but the minority library here; standardising on Chart.js reduces what future maintainers must know |
| Browsershot / headless Chrome | Chromium on a WAMP production box to solve a problem `toBase64Image()` already solves |
| A data warehouse / OLAP layer | Thousands of rows per year, not millions |
| Livewire | Effectively unused in this codebase (1 view) |

## Summary for the plan

Phase 1 needs: `npm install chart.js@4.4.0`, a `PrsSection` Blade partial pair (table + canvas),
and a controller returning the DTO as JSON. Exports (Phase 6) need one `PrsAnnualReportExport`
(maatwebsite), one PhpWord template, and the base64 chart round-trip for dompdf.

No new composer packages.
