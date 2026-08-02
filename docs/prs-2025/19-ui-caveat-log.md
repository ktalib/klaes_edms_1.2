# 19 — Caveat Log (moved out of the UI)

The PRS report UI originally rendered a prototype banner, a per-section data-quality badge and a
"Notes & caveats" block under every chart. Those were developer-facing annotations and have been
removed from the screen. **This file is now the record.**

Nothing here is new — it is the same content, relocated. The underlying analysis lives in
[10-data-quality-audit.md](10-data-quality-audit.md); this file is specifically the set of notes
that used to be visible in the app, kept so nothing was lost in the removal.

> ⚠ **Consequence of the removal, stated plainly.** The screen no longer warns anyone that
> several figures are wrong. Most importantly, the Deed of Release land-use columns are
> Certificate of Occupancy data, and the UI no longer says so — it only greys them. Anyone
> reading the report on screen will take every number at face value. Restore the caveat block, or
> ship this file alongside the report, before it goes to PRS or beyond.

## Prototype banner (removed from the page header)

> **UI prototype — static sample data.** Every figure is transcribed verbatim from the 2025 PRS
> spreadsheets. Nothing is queried from KLAES yet, and the source data contains known errors —
> reproduced on purpose. See `docs/prs-2025/` for the full audit.

Still true. The page is served from `App\Services\Prs\PrsSampleData`, a static fixture. No
database access whatsoever.

## Mixed-measure warning (removed from under the headline tiles)

> These are **not** all the same kind of measure — some count applications *received*, others
> transactions *registered* or allocations *made*. They must never be totalled together.

The per-section **Measure** and **Basis** metadata is still rendered on screen, so this
distinction remains visible where it matters most.

## Per-section quality ratings and notes

Legend: **Sound** = every figure usable · **Annual total only** = the total foots, the breakdown
beneath it does not · **Unusable** = do not cite.

---

### 01 · Layouts Implemented — *Sound*
Plot totals foot exactly and equal Residential + Commercial + Industry.

- Facilities counts facility *sites* (schools, clinics, markets), not plots — deliberately excluded from the plot total.
- Bagadawa Village is 100% industrial; the other four layouts are overwhelmingly residential.
- **Open question:** no KLAES data source exists. Needs a new `survey_layouts` table with Survey Department data entry.

### 02 · Deed of Assignment Registration — *Annual total only*
The Total column foots (1,248). The Male total is mis-added and the gender and land-use views disagree with it.

- **Error:** printed Male total is 1,087 but the twelve months sum to 1,016.
- **Error:** three incompatible answers to "how many" — gender 1,220 · categories 1,256 · total 1,248.
- **Caution:** Joint is zero for all twelve months. Confirm whether joint assignments are genuinely never recorded, or simply not captured.
- December peaks at 175 (160 residential); March is the trough at 54. July holds 19 of the year's 32 organisation registrations.

### 03 · Deed of Mortgage Registration — *Annual total only*
Only the Total column and Organisation foot. Four of seven column totals are mis-added.

- **Error:** August records one female mortgagor, but the printed Female total is 0.
- **Error:** Residential (26 vs 29), Commercial (12 vs 18) and Male (43 vs 41) totals are all mis-added.
- PRS observation: *Fidelity and Jaiz issue more facilities than other commercial banks; Federal Mortgage Bank does not contribute at all.*
- **Good news:** cheapest section to wire up — the union query and instrument filter already exist in `MortgageController`.

### 04 · Bank Ranking by Facility — *Sound*
Sums to 61, exactly matching the Deed of Mortgage annual total — the only cross-table reconciliation in the Deeds report that holds.

- Jaiz alone holds 51% of the state's registered mortgage market; with Fidelity (30%) the two hold 80%.
- **Caution:** Federal Mortgage Bank is absent from the source list entirely. The UI renders it as an explicit 0 so the absence is visible rather than inferred.
- **Open question:** `Mortgagee` is free text — "JAIZ", "Jaiz Bank", "Jaiz Bank Plc" will fragment a naive GROUP BY. Needs a `BankNameNormalizer`.

### 05 · Certificates of Occupancy Registered — *Annual total only*
Residential for August–December is suspected fabricated, and the Agriculture total is wrong by a factor of ten.

- **Error:** Residential reads a flat 75 for Aug–Dec — five identical months do not occur naturally. The same sequence appears in the Deed of Release table. Suspected copy-paste; do not treat as real.
- **Error:** printed Agriculture total is 2; the months sum to 22. Almost certainly a dropped digit.
- The printed Female total (32) is also wrong — the months sum to 35, and 872 + 35 reconciles exactly to 907. Here the report self-corrects.
- **Open question:** decide what "registered" means — date of grant, of registration, or of capture. Back-captured historical CofOs will otherwise pile onto their capture month.

### 06 · Occupancy Permit — Direct Allocation & Resettlement — *Annual total only*
Every column foots, but the March row does not: 39 + 19 = 58, yet the total reads 42.

- **Error:** March. One of the three cells is wrong, and it is the sole cause of the 6,063 vs 6,047 grand-total gap.
- **Caution:** female share is 4.9% — the widest gender gap in the report, and this is state-directed allocation rather than market activity.
- **Caution:** March collapses 95% from February (411 → 42). Either a real operational stoppage worth a footnote, or a data gap.
- **Open question:** batch-commissioned OPs share one `op_batch`; grouping by capture date would drop a whole batch into one month and invent a spike. Group by allocation date.

### 07 · Deed of Release — *UNUSABLE*
The Commercial / Residential / Agriculture / Industry block is copied from the Certificate of Occupancy table. Only the Total column describes releases.

- **Error:** contaminated. Residential reads 100, 95, 40, 35, 40, 88, 20, 75 for Jan–Aug — identical to the CofO table. Those columns are CofO data and must be discarded.
- **Error:** three incompatible totals — gender says 49, the Total column says 97, the categories say 906. An 18× spread.
- Every column foots perfectly, which is exactly what makes this table dangerous: it looks clean.
- **Open question:** canonical instrument type is `Deed of Surrender and Release`, not "Deed of Release" — check both spellings or the count comes out short.

**UI behaviour retained:** the contaminated columns are still greyed in the table, and the chart still plots only the Total column. The red explainer that said *why* has been removed.

### 08 · Deed of Devolution — *Annual total only*
Land-use columns foot; the two gender totals are swapped by one. Gender is meaningless for this instrument.

- Agriculture is 83% of all devolutions (163/196) — the exact inverse of every other table. Agricultural land in Kano moves by inheritance, not by sale.
- **Error:** Male total printed 7 (months sum to 8); Female printed 3 (months sum to 2). Equal and opposite — one row mis-keyed into the wrong gender column.
- **Caution:** only 10 of 196 devolutions carry a gender at all. Devolution transmits to estates and heirs — drop the gender columns or replace with beneficiary type.
- July and December both peak at 37, almost entirely agricultural. Possible agricultural-cycle seasonality, or registry batch processing.

### 09 · Official Searches — *Annual total only*
All four columns foot exactly, but the source table has no month column — months are inferred from row order.

- **Caution:** the source table has no month column at all; the leftmost column is Male. Months are inferred from row position and must be confirmed with PRS.
- December is a massive outlier: 45 commercial searches, more than the other eleven months combined (27). Needs an explanation in the narrative.
- Gender counts 125, land use counts 181. The 56-search gap is plausibly corporate and legal-practitioner searches, which have a land use but no gender.
- **Open question:** settle what a "search" is. ~181/year is far too low for system lookups, so this must mean formal, paid search applications.

### 12 · Applications for Conversion — *Annual row only*
A single row with no monthly or gender detail — nothing to cross-check, but nothing obviously wrong either.

- **Caution:** the Land Department document is headed "January to December **2026**" while every table inside says 2025. The content is 2025; the title is a typo.
- Agriculture (311) exceeds Commercial (234) — the only table outside Devolution where that happens.
- **Open question:** does a conversion live in `change_of_purpose_applications`, in `oss_applications` with `application_type='Conversion'`, or both? And does PRS count the previous or the new purpose?

### 13 · Applications for Direct Allocation — *Annual row only*
Sound as far as it goes, but these four totals are reused — mislabelled — in the gender matrix.

- Commercial share is 10.3% here against 3.5% for conversion — a real contrast between the two streams that the source narrative never draws out.
- **Error:** these four totals reappear as the *row* totals of the gender matrix, with Agriculture and Industrial transposed. Both tables cannot be right.
- **Caution:** `SIT` is undefined — Site and Service? Sectional Titling? It is 0 all year and appears nowhere else.
- **Open question:** is this 6,798 the same population as the Deeds report's 6,047? One counts applications received, the other allocations made — but neither report says so.

### 14 · Direct Government Allocation by Gender — *UNUSABLE*
Not an independent gender breakdown — it is the direct-allocation land-use row re-cut, with two labels swapped and at least one cell mis-keyed.

- **Error:** Agriculture and Industrial rows are transposed. The 58 under "Direct Industrial" is the Agriculture total; the 18 under "Direct Agriculture" is the Industrial total.
- **Error:** the Direct Conversion *male* cell (701) equals the Commercial *land-use* total exactly — a land-use figure pasted into a gender cell.
- **Error:** the Base column mixes application streams (Allocation, Conversion) with land uses (Industrial, Agriculture). The rows have no consistent grain and cannot legitimately be summed.
- **Caution:** Private Organisation and Governmental are zero in all four rows. In a report on *government* allocation that is implausible — almost certainly never captured.
- **Good news:** `oss_applications` carries `sex`, `application_type` and `land_use` on one row, so the correct version is a single cross-tab — which structurally prevents this error.

### 15 · OSS Applications by Size and Purpose — *Sound*
Every column total foots, every one of the twelve month rows sums to its own total, and it reconciles to the gender table in all twelve months.

- **Good news:** the best table in either report. Whatever process produced it should be the model for every other section.
- High density is 96.2% of all applications. Low density totals 21 for the entire year — roughly one every three weeks.
- **Caution:** categories mix plot *density* (High, Low, Small Scale) with *land use* (Commercial, Industrial). These are not mutually exclusive and must become two separate dimensions.
- **Open question:** confirm which column holds density. It may be derived from plot dimensions, in which case the banding rule must be captured explicitly.

### 16 · OSS Applications by Gender — *Sound*
Both columns foot, and the monthly totals match the size/purpose table exactly in all twelve months.

- **Good news:** the only perfect cross-table reconciliation in either report — twelve out of twelve months agree with the size/purpose table, and so does the annual total.
- **Caution:** female share is 3.0% (69 of 2,315) — the lowest of any table in either report. April recorded 1 female applicant out of 181.
- Across both reports every measured female share falls between 3% and 7.3%. That consistency across independent sources makes it a robust finding, not an artefact.
- **Open question:** confirm whether `oss_applications.sex` has nulls. If it does, the perfect reconciliation is suspicious and unknowns are being coerced into Male.

## Restoring any of this to the UI

The removal was presentational only. `resources/views/prs/annual_report/partials/section.blade.php`
renders from the section DTO; re-adding a `notes` key to `PrsSampleData` and a block in that
partial brings it back. The two partials that rendered it (`quality-badge.blade.php`,
`notes.blade.php`) were deleted — recover them from git history rather than rewriting.
