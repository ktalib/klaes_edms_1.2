# 20 — Live Data Implementation: Gap Audit & Plan

The PRS Annual Report screen *was* served entirely from `App\Services\Prs\PrsSampleData`, a static
fixture ([19](19-ui-caveat-log.md)). This document is the audit that had to happen before that
fixture could be replaced — what the live KLAES database can actually answer, what it cannot, and
what had to be built or backfilled first — followed by the record of what shipped ([§11](#11-what-shipped)).

[11-implementation-plan.md](11-implementation-plan.md) was written against the *source spreadsheets*
and the *schema as documented*. This one is written against the database. **It contradicts the
earlier plan in three places**, and those contradictions are the point — see
[§4](#4-correction-oss_applications-is-not-the-land-source) and [§5](#5-the-reporting-year-does-not-exist-in-klaes).

## Method

Every figure below was measured directly against the `sqlsrv` connection (database `klas`) on
**2026-08-02**. Counts are live and will drift. Nothing here is inferred from the schema alone;
where something is an inference rather than a measurement it says so.

---

> **Status: the report is live.** Since 2026-08-03 the page is served from
> `PrsReportAggregator`, which queries the database. `PrsSampleData` is retained as the 2025
> reference transcription but is no longer wired to the page. What shipped, and what it cost, is in
> [§11](#11-what-shipped). The gap analysis below is what made it possible and still explains every
> number the page produces.

## 1. Headline verdict

| Question | Answer |
|---|---|
| Can the 14 sections be generated from live data today? | **13 of 14 are live.** Only Survey layouts is absent — no table exists. |
| Is gender the blocking gap? | **Yes, for 11 of 14 sections.** Coverage is 0.004% on `file_indexings` and 0.03% on `mls_file_no`. |
| Is gender the *only* blocking gap? | No. The reporting year, the Land source table, and the date columns are each independently blocking. |
| Can the module ship for **2025**? | **No.** KLAES holds almost no 2025 activity — see [§5](#5-the-reporting-year-does-not-exist-in-klaes). |
| What is the realistic first reportable year? | **2026**, generated in early 2027, provided gender capture is fixed now. |

---

## 2. Gap 1 — Gender (the primary gap)

Gender was added to `file_indexings` and `mls_file_no` on **2026-07-28**
([`2026_07_28_000001`](../../database/migrations/2026_07_28_000001_add_gender_to_file_indexings_table.php),
[`2026_07_28_000002`](../../database/migrations/2026_07_28_000002_add_gender_to_mls_file_no_table.php)),
both `nvarchar(10) NULL`. Nullable was the right call — 133k existing rows and every corporate file
would otherwise be invalid — but it means the column is **prospective only**.

### 2.1 Measured coverage

| Table | Rows | Gender populated | Coverage | Values present |
|---|---:|---:|---:|---|
| `file_indexings` | 133,779 | **6** | **0.004%** | Male 4 · Corporate 1 · Female 1 |
| `mls_file_no` | 6,069 | **2** | **0.03%** | Male 1 · Joint 1 |
| `oss_applications` (`sex`) | 350 | **334** | **95.4%** | Male 304 · Female 30 · null 16 |
| `recertification_applications` | 1 | 1 | — | table is effectively empty |
| `recertification_owners` | 0 | 0 | — | table is empty |
| `mother_applications` (`applicant_title`) | 14 | 6 | — | Alhaji 5 · Hajia 1 |
| `subapplications` (`applicant_title`) | 75 | 49 | — | Alhaji 49 (all male-coded) |

`oss_applications.sex` is **the only gender population in KLAES with usable coverage**, and it is
350 rows.

### 2.2 There is no gender column on any deed table

This is the structural problem, and it is worse than [D2](11-implementation-plan.md#decisions-required-before-coding)
assumed. Confirmed by scanning `INFORMATION_SCHEMA.COLUMNS` for every column named
`gender`/`sex`/`applicant_title`/`salutation` across the whole database:

- `pra` — **no gender column** (133,939 rows)
- `file_history_staging` — **no gender column** (19,737 rows)
- `deed_registrations` — **no gender column** (9,502 rows)
- `CofO_staging` — **no gender column** (32,101 rows)
- `file_search_requests` — **no gender column** (890 rows)

So gender for every Deeds section must be reached by **join through the file number** to
`file_indexings.gender`. That join was measured:

```
pra rows whose mlsFNo matches a file_indexings.file_number   62,737  (46.8% of pra)
  ...of which the indexing row carries a gender                   1
```

**One row.** Wiring the join correctly today produces a gender breakdown of 1 registration out of
133,939. Every Deeds gender chart would render empty.

### 2.3 Capture is live but leaking

Validation was added at four write paths — `required_unless:source,scanning_upload`, values
`Male|Female|Corporate|Joint`:

- [FileIndexController.php:173](../../app/Http/Controllers/FileIndexController.php#L173) and [:1047](../../app/Http/Controllers/FileIndexController.php#L1047)
- [FileIndexingController.php:1091](../../app/Http/Controllers/FileIndexingController.php#L1091) and [:3338](../../app/Http/Controllers/FileIndexingController.php#L3338)
- [MlsFileNoController.php:1630](../../app/Http/Controllers/MlsFileNoController.php#L1630) — `required`, and additionally allows `Government`

Since go-live (rows created on or after 2026-07-28):

| Table | New rows | With gender |
|---|---:|---:|
| `file_indexings` | 10 | **5** |
| `mls_file_no` | 4 | **2** |

**Half the post-go-live rows still land null.** The validated controllers are not the only writers.
[`FileIndexingService::createFromMlsFileNumber()`](../../app/Services/FileIndexingService.php#L23)
copies `$mlsFile->gender`, which is itself null for 6,067 of 6,069 rows, so auto-indexing propagates
the hole rather than filling it. `createFromFileNumberData()` ([:68](../../app/Services/FileIndexingService.php#L68))
takes `$data['gender'] ?? null` with no enforcement at all.

Two further inconsistencies to settle before any aggregation is written:

1. **The two enums disagree.** Indexing allows `Male|Female|Corporate|Joint`; commissioning allows
   those plus `Government`. `RecertificationController` writes lowercase `male|female`
   ([:555](../../app/Http/Controllers/RecertificationController.php#L555)). Three vocabularies for
   one dimension.
2. **`Corporate` is a party *type*, not a gender.** It is being stored in the gender column, which
   is exactly the "party type ≠ gender" flattening that
   [fix #2 of the audit](11-implementation-plan.md#fixes-to-carry-over-from-the-audit) exists to
   prevent. `mls_file_no.customer_type` already holds this properly and is well populated:
   Individual 5,395 · Corporate 664 · Government 9 · Multiple 1.

### 2.4 Remediation — do these in order

**G1. Fix the leak first.** Nothing else matters if new rows keep landing null. Move the gender
requirement out of the four controllers and into a single choke point — a model observer on
`FileIndexing` / `MlsFileNo`, or `FileIndexingService` itself — so auto-index, batch and service
paths are covered, not just the two request-validated forms. Target: 100% of rows created after
the fix carry a non-null gender or an explicit `Not Applicable`.

**G2. ~~Split the dimension~~ — REJECTED by the client, 2026-08-03.** The proposal was to hold
`Male | Female` in `gender` and move party type to `customer_type`. **The client specified the four
values — Male, Female, Corporate, Joint — and they stand.** `Government` is not among them and has
been folded into `Corporate` (11 rows on `mls_file_no`), and the commissioning validation rule no
longer accepts it, so all screens now offer the same four.

**The aggregator reports all four as categories** — Male, Female, Corporate and Joint each get their
own series and their own column. Suppressing Corporate would hide 14,608 files rather than describe
them, and the four-way split is a truer picture of who holds land in Kano than a two-way one.

The one thing this obliges: **a percentage must name its denominator.** Two different, both-correct
figures come out of the same column, and they are not interchangeable:

| Figure | Denominator | Value today |
|---|---|---:|
| Female share **of individuals** | `Male + Female` = 38,097 | **6.58%** |
| Female share **of all files** | all four = 52,881 | **4.74%** |

The 2025 source tables measure the first — their gender columns only ever counted people — so that
is the one to compare against the 3–7.3% band. The second is the right figure for "what proportion
of the register is held by women". Label whichever is shown; never print a bare "female share".

The three competing enums are reconciled through `GenderNormalizer::normalize()`, mirroring the
existing `DepartmentNormalizer` pattern.

**G3. Propagate on commission.** `createFromMlsFileNumber()` should be the guaranteed path for
`mls_file_no.gender → file_indexings.gender`, and commissioning should refuse to complete without
gender for `customer_type = Individual`. Both halves of the pair are then populated by
construction.

### G1 and G3 — done, 2026-08-03

Enforcement moved off the four controllers and onto the models, matching the
`DepartmentNormalizer` + mutator pattern already used for file-tracker departments. Every write to
`file_indexings` and `mls_file_no` goes through Eloquent (`::create()` / `->save()`), so a mutator
plus a `creating` hook covers all of them — form, service, batch and auto-index alike.

| Change | File |
|---|---|
| `setGenderAttribute()` folds every write onto the four values | [FileIndexing.php](../../app/Models/FileIndexing.php), [MlsFileNo.php](../../app/Models/MlsFileNo.php) |
| `creating` hook infers from the holder name when gender is absent, and stamps `gender_source` | same |
| Commissioning gender carried across with its provenance | [FileIndexingService.php](../../app/Services/FileIndexingService.php) |
| `Government` removed from the commissioning rule | [MlsFileNoController.php](../../app/Http/Controllers/MlsFileNoController.php) |

Inference runs on **creation only** — an update never rewrites an existing row, so the 80,898
unresolved rows stay honestly null until someone captures them.

Verified against the live database inside a rolled-back transaction:

| Input | Stored gender | `gender_source` |
|---|---|---|
| `ALHAJI TEST SUBJECT` | Male | `honorific` |
| `TEST HOLDINGS LIMITED` | Corporate | `organisation` |
| `MINISTRY OF WORKS` | Corporate | `organisation` |
| `SANI DAHIRU YAKASAI` | **NULL** | null |
| any name + `female` | Female | `captured` |
| any name + `Government` | Corporate | `captured` |

The bare given name staying NULL is the point: a new row is only filled when there is evidence,
never by guessing, and a captured value is never overwritten by an inferred one.

**G4. Backfill — and be honest about the ceiling.** There is no internal source to backfill 133,773
rows from. The options, ranked:

| Option | Reach | Assessment |
|---|---|---|
| Copy from `mls_file_no` | 2 rows | Useless — the source is empty too |
| Copy from `oss_applications.sex` via file number | ≤334 rows | Worth doing; the only real seed |
| Derive `customer_type` from `file_title` (LTD/PLC/LIMITED/NIG/ENTERPRISE) | ~11,312 rows match | **Do this.** Does not give gender, but correctly marks ~8.5% of files as non-individual, removing them from the gender denominator |
| Infer from honorific in `file_title` (Alhaji/Hajia/Mr/Mrs/Malam/Hajiya) | unmeasured | Viable for a *first pass only*, written to a separate `gender_source` column so inferred values are never mistaken for captured ones |
| Registry data-entry backfill | 133k rows | The only complete answer. Not a software task — scope it with PRS and the Registry as a resourced exercise |

Record provenance either way: add `gender_source` (`captured | derived_title | inferred_honorific |
backfilled`). Any PRS chart must then be able to state what share of its denominator was inferred,
or the 3–7% female share finding ([16](16-oss-applications-gender.md)) becomes uncheckable.

**G5. Until coverage is real, render gender honestly.** Every gender chart shows an explicit
`Not Recorded` bucket sized to the true gap. A chart reading "Male 4, Female 1" with 133,773 rows
silently dropped is worse than no chart. Cite the OSS gender section as the model — it is the one
place the split is real.

### 2.5 Backfill executed — 2026-08-02

`php artisan gender:backfill` ([BackfillGender.php](../../app/Console/Commands/BackfillGender.php),
classifier in [GenderNormalizer.php](../../app/Services/GenderNormalizer.php)) has been written and
run. Provenance is recorded in a new `gender_source` column on `file_indexings`, `mls_file_no` and
`indexing_duplicates`.

| Table | Before | After |
|---|---:|---:|
| `file_indexings` | 6 (0.004%) | **52,881 (39.53%)** |
| `mls_file_no` | 2 (0.03%) | **1,613 (26.58%)** |

Result by provenance:

| Source | `file_indexings` | `mls_file_no` | Reliability |
|---|---:|---:|---|
| `captured` | 6 | 2 | Entered on a form |
| `oss_sex` | 334 | 30 | Captured on the OSS form |
| `pair` | 100 | 1 | Copied across the commissioning/indexing pair |
| `organisation` | 14,507 | 1,239 | Name is a company or public body — gender does not apply |
| `honorific` | 37,758 | 341 | **Inferred** from Alhaji / Hajiya / Mallam / Mrs … |
| `joint` | 176 | 0 | Two parties of differing gender, connector present |

**Effect on the report:** `pra` rows that can resolve a gender through the file-number join went
from **1 to 27,638**. The Deeds gender split is now renderable.

**Two things to hold on to:**

1. **71% of the filled values are inferred, not captured.** Any chart built on this must read
   `gender_source`, and any figure quoted to PRS must state the inferred share. This is precisely
   what [G4](#24-remediation--do-these-in-order) required and what `gender_source` exists for. The
   whole backfill reverses with `php artisan gender:backfill --reset`, which leaves `captured`
   values untouched.
2. **The inferred female share is 6.58%** (2,507 of 38,097 individuals). Every table in the 2025
   source reports a female share between 3% and 7.3% ([README](README.md)). An inference method
   with no knowledge of those reports landing inside their band is real corroboration of the
   finding — but it is not proof, since honorific-bearing names may not be representative of the
   80,898 rows still unresolved.

**Deliberately not inferred:** bare given names (no dictionary — it would lift coverage well past
39% at an unmeasurable error rate), status titles Dr/Engr/Prof/Barr/Hon/Chief (1,347 rows lead with
Dr alone), and mid-name honorifics such as "Hafsatu Alhaji Musa", where the title is the father's.
That last rule costs ~3.7% of honorific matches and removes the only error class that
systematically misreads women as men.

---

## 3. Gap 2 — Section-by-section source matrix

Status legend: **Ready** = queryable today · **Partial** = source exists, blocked on normalisation
or gender · **Blocked** = no source.

| # | Section | Source table(s) | Rows available | Gender? | Status |
|---:|---|---|---:|---|---|
| 01 | Survey layouts | — | 0 | n/a | **Blocked** — `survey_layouts` does not exist |
| 02 | Deed of Assignment | `pra` ∪ `file_history_staging` ∪ `deed_registrations` | 8,636 + 12,166 + 280 | join → 1 row | **Partial** |
| 03 | Deed of Mortgage | same union, `Deed of Mortgage` + `Tripartite Mortgage` | 5,817 + 1,833 | join → ~0 | **Partial** |
| 04 | Bank facility ranking | `pra.Mortgagee` (free text) | 7,650 mortgage rows | not needed | **Partial** — needs `BankNameNormalizer` |
| 05 | Certificate of Occupancy | `CofO_staging` | 32,101 | join → ~0 | **Partial** |
| 06 | Occupancy permit / resettlement | `mls_file_no.source IN (OP Resettlement, OP Direct Allocation)` | 982 | 2 rows | **Partial** |
| 07 | Deed of Release | union, 3 instrument spellings | 3,944 | join → ~0 | **Partial** |
| 08 | Deed of Devolution | union, `Deed of Devolution` / `Devolution Order` | 606 | drop per audit fix #6 | **Partial** |
| 09 | Search | `file_search_requests` | 890 | **no column** | **Blocked** for gender; total is queryable |
| 12 | Applications for conversion | `mls_file_no.source = 'Conversion'` | **3,127** | 2 rows | **Partial** |
| 13 | Direct allocation | `mls_file_no.source = 'Direct Allocation'` | **1,882** | 2 rows | **Partial** |
| 14 | Allocation by gender | as above | 5,009 | **2 rows** | **Blocked** |
| 15 | OSS by size/purpose | `oss_applications` | **350** | n/a | **Blocked** — `land_use` is 100% null |
| 16 | OSS by gender | `oss_applications.sex` | **350** | **95.4%** | **Ready** (small n) |

Only section 16 is genuinely ready. Section 04 becomes ready as soon as the bank normaliser exists,
because it needs no gender at all.

---

## 4. Correction — `oss_applications` is *not* the Land source

[11-implementation-plan.md](11-implementation-plan.md) makes this its "key structural point":
sections 12–16 come from one `oss_applications` query pivoted five ways. **Measured against the
data, that plan does not work.**

| Plan assumed | Actually measured |
|---|---|
| `oss_applications` carries `application_type`, `land_use`, `purpose`, `sex` on one row | `land_use` is **null in all 350 rows**; `purpose` is null in 334 of 350, and the 16 non-null values are strings like `TRANSFER OF TITLE (OP) \| PLAN NO: TP/K/2777` |
| `application_type` is enum `Direct Allocation \| Conversion` | Holds **`residential` (334) and `commercial` (16)** — i.e. a land use, not a stream |
| Volume comparable to 2,315 | **350 rows total** |

The real source for conversion and direct allocation is **`mls_file_no`**, which carries the stream
in `source` and the land use in `land_use`:

| `mls_file_no.source` | Rows | PRS section |
|---|---:|---|
| Conversion | 3,127 | 12 |
| Direct Allocation | 1,882 | 13 |
| OP Resettlement | 895 | 06 |
| OP Direct Allocation | 87 | 06 |
| MLS_Commissioned | 54 | — |
| Subdivision / Change of Purpose / Temporary / Extension | 24 | — |

This also **answers D8** ([11](11-implementation-plan.md#decisions-required-before-coding)): the
Land report's "direct allocation" and the Deeds report's "resettlement allocation" are different
`source` values on the same table and must never be conflated. `MlsFileNoController` already makes
the distinction.

And it **answers D10**: `SIT` is not a phantom. It is a real `land_use` code on `mls_file_no`
(9 rows), alongside `CON-RES` 2,540 · `RES` 2,459 · `CON-COM` 435 · `IND` 243 · `COM` 211 ·
`CON-AG` 102 · `CON-IND` 56 · `RES-RC` 6 · `IND-RC` 3 · `AG` 3 · `CON-RES-RC` 2. The `CON-` prefix
encodes conversion, duplicating `source` — decide which is authoritative. `-RC` is undocumented;
confirm it with the Lands Department.

**Consequence for the plan's phasing.** Phase 1 was "start with the Land OSS pair because they are
the cleanest". They are the cleanest *in the spreadsheet*; in the database they are the thinnest.
Revised phasing is in [§8](#8-revised-phasing).

---

## 5. The reporting year does not exist in KLAES

Measured year distributions:

| Source | Year distribution |
|---|---|
| `mls_file_no.commissioning_date` | **2026 = 5,953. 2025 = 0.** All of it. |
| `oss_applications.created_at` | **2026 = 350.** All of it. |
| `file_search_requests.created_at` | **2026 = 890.** All of it. |
| `deed_registrations.deeds_date` | **2026 = 9,502.** All of it. |
| `pra.created_at` | 2026 = 88,000 · 2025 = 45,937 |
| `pra.transaction_date` | 2025 = 4,132 · 2022 = 3,334 · 1991 = 2,482 · spread across four decades |
| `CofO_staging.cofo_date` | 1991 = 994 · 2018 = 962 · 2000 = 845 · **2025 = 20** |

The pattern is unambiguous: **capture happened in 2026; the transactions are historical
back-capture.** The file commissioning, OSS and search modules only went live in 2026, so they hold
no 2025 activity at all.

What a live 2025 report would actually print, against what PRS published:

| Section | PRS 2025 report | Live KLAES for 2025 |
|---|---:|---:|
| Deed of Assignment | 1,248 | **323** (`pra` 56 + `file_history_staging` 267) |
| Deed of Mortgage | 61 | **16** |
| Deed of Release | 97 | **23** |
| Deed of Devolution | 196 | **0** |
| Certificates of Occupancy | 907 | **20** |
| Occupancy permits | 6,047 | **0** |
| Conversion applications | 6,595 | **0** |
| Direct allocation applications | 6,798 | **0** |
| OSS applications | 2,315 | **0** |
| Survey plots | 12,933 | **0** |

**Do not ship a 2025 live report.** It would show a 74–100% shortfall against a document PRS
already published, and the shortfall is an artefact of when KLAES went live, not a finding about
the Registry. The year selector must be data-driven — offer only years with material coverage — and
the first honest target is **2026**.

This supersedes the framing in [11 §Validation](11-implementation-plan.md#validation), which
expected mismatches to be *findings about the old process*. Most of the 2025 gap is simply absence.

---

## 6. Gap 3 — Dates are strings, and a fifth of them do not parse

| Column | Type | Non-null | Parses as date | Fails |
|---|---|---:|---:|---:|
| `pra.deeds_date` | **nvarchar** | 32,844 / 133,939 | 31,801 | 1,043 |
| `pra.transaction_date` | **nvarchar** | 60,409 / 133,939 | 60,335 | 74 |
| `pra.reg_date` | **nvarchar** | 204 | 197 | 7 |
| `CofO_staging.cofo_date` | **nvarchar** | 24,448 / 32,101 | 19,129 | **5,319** |
| `CofO_staging.deeds_date` | **nvarchar** | 195 | 195 | 0 |
| `mls_file_no.commissioning_date` | `date` | 5,953 / 6,069 | 5,953 | 0 |

Consequences for **D1**:

- Every date predicate must be `TRY_CONVERT(date, col)`. A bare `YEAR(deeds_date)` throws
  `SQLSTATE[22007]` on the live data — verified, not hypothetical.
- `pra.deeds_date` — the column [11](11-implementation-plan.md#the-deed-union) nominates as
  authoritative — is **null for 75.5% of `pra`**. `transaction_date` has nearly twice the coverage.
  Recommended fallback chain: `deeds_date → transaction_date → reg_date → created_at`, with the
  column used recorded per row and surfaced in the section footnote.
- ~~**Never bucket by `created_at`.**~~ **OVERRIDDEN BY THE CLIENT, 2026-08-03 — and they were
  right.** The original objection was that `created_at` puts 88,000 back-captured historical
  instruments into 2026. That is true, and it is the point: the PRS document is a **progress
  report of registry output**, so it should measure what the department processed in the year, not
  when a 1991 deed was originally executed.

  The transaction-date basis also made the department's largest activity invisible. 37,730
  Occupancy Permits were captured in 2026 and carry no usable transaction date at all, so they
  appeared in no section whatsoever. Coverage settles it:

  | Column | Populated | Share |
  |---|---:|---:|
  | `created_at` | 133,938 of 133,939 | **99.999%** |
  | `transaction_date` | 60,409 | 45.1% |
  | `deeds_date` | 32,844 (1,043 unparseable) | 24.5% |

  Every section now reads **Basis: Date captured**, so the meaning is on the face of the report.
  The historical dates are untouched on the row — a transaction-date view remains possible for
  anyone who wants to ask a different question.
- 5,319 unparseable `cofo_date` values need a cleanup pass, not a silent `WHERE ... IS NOT NULL` —
  otherwise 22% of CofO history disappears without trace.

---

## 7. Gap 4 — Vocabularies need normalisers before any GROUP BY

Three free-text dimensions will fragment a naive aggregate.

**Instrument type** — `pra` holds `Deed of Surrender and Release` (3,300), `Surrender and Release`
(333) **and** `Deed of Release` (311) as three separate values for one PRS section.
`file_history_staging` adds case variants: `ASSIGNMENT` (2,658) beside `Deed of Assignment` (9,508),
`MORTGAGE` (1,407) beside `Deed of Mortgage` (2,426), `DEED OF SURRENDER AND RELEASE` (1,072).
`deed_registrations` uses `Devolution Order` where `pra` uses `Deed of Devolution`. This confirms
the "**verify variants**" warnings in [11](11-implementation-plan.md#the-deed-union) — and the
answer is that every variant is real and in use.

**Land use** — `pra.land_use`: `RESIDENTIAL` 105,508 · `COMMERCIAL` 14,067 · null 8,886 ·
`Industrial` 2,571 · `AGRICULTURAL` 1,409 · `RES` 404 · `COMMERCIAL AND RESIDENTIAL` 290 ·
`RESIDENTIAL/COMMERCIAL` 243 · `Agriculture` 172 · `RESIDENTIAL AND COMMERCIAL` 135 · `IND` 134 ·
`COM` 23. Three problems at once: case variance, abbreviation variance, and **combined values that
belong to two categories**. `mls_file_no` uses a different scheme again (`CON-RES`, `RES-RC`). This
is **D3**, and it needs a `LandUseNormalizer` with an explicit `Mixed` bucket — silently assigning
`RESIDENTIAL/COMMERCIAL` to one side would misstate both.

**Bank names** — `pra.Mortgagee` is free text, as [11](11-implementation-plan.md) anticipated.
`BankNameNormalizer` is still required.

---

## 8. Revised phasing

Ordered by what the data can actually support, not by what the spreadsheet looked cleanest.

**Phase 0 — Stop the bleeding (do now, blocks everything).**
G1–G3 from [§2.4](#24-remediation--do-these-in-order): single choke point for gender, split gender
from party type, propagate on commission. This is a small change today and a migration with a data
rewrite in six months. **This is the whole reason this document exists.**

**Phase 1 — The sections that need no gender.**
Bank facility ranking (04) and the *totals* of Assignment/Mortgage/Release/Devolution (02, 03, 07,
08). Build `DeedRegistrationStats` with the three-table union, the instrument-type normaliser and
the `TRY_CONVERT` date chain. Ship gender columns as a single `Not Recorded` bucket. This delivers
real charts from real data immediately and proves the union.

**Phase 2 — Land streams from the right table.**
Conversion (12) and direct allocation (13) from `mls_file_no.source`, with `LandUseNormalizer` over
the `CON-*` codes. Resolve `CON-` prefix vs `source` authority and the `-RC` suffix first. Section
06 comes along for free — same table, different `source` values.

**Phase 3 — CofO (05) and Search (09).**
CofO needs the 5,319 unparseable `cofo_date` rows cleaned first. Search is queryable for totals
today (890 rows) but has no land-use or gender column, so it renders as a single series — which is
honest, and settles **D4**: at 890 rows in a year these are formal search requests, not lookups.

**Phase 4 — Gender views.**
Only once Phase 0 has produced real coverage. Until then sections 14 and the gender split of every
Deeds table stay behind a feature flag rather than rendering 1-row charts.

**Phase 5 — OSS (15, 16) and Survey (01).**
OSS is blocked on `land_use` being populated at all — a data-entry problem, not a query problem.
Survey needs the `survey_layouts` table and a Survey Department data-entry screen (**D6**,
unchanged).

**Phase 6 — Narrative, exports, year selector.** As per [11](11-implementation-plan.md), with the
year selector driven by measured coverage rather than a hardcoded list.

---

## 9. Status of the original decision questions

| # | Question | Status after this audit |
|---|---|---|
| D1 | Which date buckets a record | **Answered, with a caveat.** `deeds_date → transaction_date → reg_date`, never `created_at`. All columns are nvarchar; `TRY_CONVERT` mandatory. See [§6](#6-gap-3--dates-are-strings-and-a-fifth-of-them-do-not-parse) |
| D2 | Gender of a registration | **Still open, and now urgent.** No gender column on any deed table; join to `file_indexings` yields 1 row. See [§2](#2-gap-1--gender-the-primary-gap) |
| D3 | Canonical land use | **Still open.** Measured vocabularies in [§7](#7-gap-4--vocabularies-need-normalisers-before-any-group-by); needs a `Mixed` bucket |
| D4 | What "search" means | **Answered.** 890 `file_search_requests` in 2026 — formal requests. No gender or land use available |
| D5 | Resettlement vs direct allocation | **Answered.** Distinct `mls_file_no.source` values: OP Resettlement 895, OP Direct Allocation 87 |
| D6 | Layout data source | **Confirmed absent.** `survey_layouts` does not exist |
| D7 | Where conversions live | **Answered — neither of the expected options.** `mls_file_no.source = 'Conversion'` (3,127). `change_of_purpose_applications` holds 86 rows; `oss_applications` holds none |
| D8 | Is 6,798 the same as 6,047 | **Answered: no.** Different `source` values on one table |
| D9 | Plot density column | **Still open**, and now moot for Phase 1 — `oss_applications.land_use` is entirely null |
| D10 | What is `SIT` | **Partially answered.** A real `land_use` code on `mls_file_no`, 9 rows. Meaning still unconfirmed |
| D11 | Nulls in `oss_applications.sex` | **Answered: yes.** 16 of 350 (4.6%). The source spreadsheet's perfect reconciliation was therefore not achievable from this data — unknowns were being absorbed somewhere |

D2 and D3 are the only true blockers left, and D2 is a data-capture programme rather than a query.

---

## 10. Validation targets for the live module

The Tier 1/2/3 targets in [11](11-implementation-plan.md#validation) were set against the 2025
spreadsheet and are **not achievable** — see [§5](#5-the-reporting-year-does-not-exist-in-klaes).
Replace them with:

**Tier 1 — internal consistency (must hold in every section, every month).**
- gender total = land-use total = section total, with `Not Recorded` and `Mixed` buckets carrying
  the difference explicitly.
- No chart contains a total as a series or a category.
- Every land-use value resolves through `LandUseNormalizer`; zero pass-through values.

**Tier 2 — cross-source reconciliation (measurable today).**
- Mortgage count (`Deed of Mortgage` + `Tripartite Mortgage`) = sum of the bank ranking. This is
  the one reconciliation that held in the source, and it needs no gender.
- `mls_file_no` OP rows ↔ `deed_registrations` `Occupancy Permit (OP)` rows (8,939). A large gap
  here would surface the `op_batch` distortion already recorded in
  [op-batch-remediation](06-resettlement-allocation.md).
- `pra` ∪ `file_history_staging` ∪ `deed_registrations` deduplicated by `prop_id` + instrument +
  date — the union must not double-count rows migrated between staging tables.

**Tier 3 — coverage reporting, rendered on the page.**
Every section states the share of its rows with a usable date, a resolved land use, and a captured
gender. A section at 0.004% gender coverage must say so on its face. That is the lesson of
[19](19-ui-caveat-log.md): the caveats were removed from the screen once already, and the figures
they warned about are still being read at face value.

---

## 11. What shipped

Live since **2026-08-03**. The controller now injects `PrsReportAggregator`; the blades were not
changed, because the aggregator emits the same section shape the fixture did.

### Files

| File | Role |
|---|---|
| [PrsReportAggregator.php](../../app/Services/Prs/PrsReportAggregator.php) | Assembles 13 sections + headline tiles; enforces the chart rules |
| [DeedRegistrationStats.php](../../app/Services/Prs/DeedRegistrationStats.php) | The three-table deed union — 5 sections from one query |
| [CofOStats.php](../../app/Services/Prs/CofOStats.php) | Section 05 |
| [LandFileStats.php](../../app/Services/Prs/LandFileStats.php) | Sections 06, 12, 13, 14 from `mls_file_no` |
| [SearchStats.php](../../app/Services/Prs/SearchStats.php) | Section 09 |
| [OssStats.php](../../app/Services/Prs/OssStats.php) | Sections 15, 16 |
| [Support/SectionShape.php](../../app/Services/Prs/Support/SectionShape.php) | Section DTO, palette, insight derivation |
| [Support/LandUseNormalizer.php](../../app/Services/Prs/Support/LandUseNormalizer.php) | D3 — one land-use vocabulary, with a `Mixed` bucket |
| [Support/InstrumentTypeNormalizer.php](../../app/Services/Prs/Support/InstrumentTypeNormalizer.php) | Folds the instrument spellings onto 5 groups |
| [Support/BankNameNormalizer.php](../../app/Services/Prs/Support/BankNameNormalizer.php) | Section 04 — and separates private mortgagees from banks |

### What the page prints now

2026, the default year (chosen because it carries the most activity, not because it is hardcoded):

| # | Section | 2026 |
|---:|---|---:|
| 02 | Deed of Assignment | 634 |
| 03 | Deed of Mortgage | 41 |
| 04 | Bank ranking | 11 facilities across 6 institutions |
| 05 | Certificates of Occupancy | 276 |
| 06 | Occupancy permit — allocation & resettlement | 982 |
| 07 | Deed of Release | 77 |
| 08 | Deed of Devolution | 9 |
| 09 | Official searches | 890 |
| 12 | Applications for conversion | 3,075 |
| 13 | Applications for direct allocation | 1,818 |
| 14 | Allocation stream by gender | 5,952 |
| 15 | OSS applications by purpose | 350 |
| 16 | OSS applications by gender | 350 |

Sections with no rows for a year are omitted rather than rendered as zero, so 2025 shows 6 sections
and 2026 shows 13. The year selector is built from measured coverage.

### Invariants verified against live data

The Tier 1 checks from [§10](#10-validation-targets-for-the-live-module), run across all 13 sections:

- **Gender total = land-use total = section total, in every section and every month.** Confirmed for
  all 12 sections that carry both cuts. Section 09 has neither column and reports a single dimension.
- **No chart contains a total as a series or a category.**
- **Every land-use value resolves through `LandUseNormalizer`** — no pass-through values.
- All 13 embedded chart payloads parse as valid JSON.

The source spreadsheet fails the first invariant on most of its tables. The module passes it on all
of them, because gender and land use are two cuts of one row set rather than two independent counts.

### Two findings the live data produced

1. **The bank ranking corroborates the PRS narrative.** Jaiz and Fidelity lead in 2022, 2025 and
   2026 — independently reproducing the written observation in
   [04-bank-facility-ranking.md](04-bank-facility-ranking.md) from data PRS never touched.
2. **`Mortgagee` is not always a bank.** The first ranking returned private individuals alongside
   Jaiz and Fidelity — 32 facilities across 25 private mortgagees in 2026 alone. Section 04 ranks
   institutions and reports private mortgagees as a separate figure, rather than ranking a person
   as a lender or silently dropping the rows.

### Performance

First render took **97 seconds** — unusable. Two causes, both fixed:

| Cause | Fix |
|---|---|
| `LEFT JOIN file_indexings ON LTRIM(RTRIM(fi.file_number)) = ...` — a function on the indexed column makes the predicate non-sargable and forces a full scan of 133,779 rows | Join the bare column; the other side is already trimmed in the CTE |
| `sections()` and `highlights()` independently re-ran the same expensive union | Per-request memo on every stats service |

**97s → 3.5s** for the full 13-section page. This is the same non-sargable-predicate mistake that
previously timed out the file log table; it is worth checking for first whenever a KLAES page is slow.

### Still outstanding

- **Section 01 (Survey layouts)** — needs a `survey_layouts` table and Survey Department data entry (D6).
- **Exports** — Word, Excel and PDF buttons remain disabled. Word first, per
  [18-reporting-stack.md](18-reporting-stack.md).
- **Narrative blocks** — PRS author commentary per section; needs an editable field, not a chart.
- **D2 and D3 remain open questions of meaning**, not of plumbing. The module now produces a gender
  and a land-use cut for every section; whether a deed's gender should be the grantee, the assignee
  or the file holder is still a decision for PRS and the Deeds Registry.

---

## 12. Revisions after first review (2026-08-03)

Three changes requested once the live page was on screen.

### 12.1 Date basis is now the capture date

Client decision, and it reshaped the report. Full reasoning in [§6](#6-gap-3--dates-are-strings-and-a-fifth-of-them-do-not-parse).
Effect on 2026:

| Section | Transaction-date basis | Capture-date basis |
|---|---:|---:|
| Deed of Assignment | 634 | **10,536** |
| Deed of Mortgage | 41 | **7,384** |
| Certificates of Occupancy | 276 | **19,279** |
| Deed of Release | 77 | **4,553** |
| Deed of Devolution | 9 | **750** |
| Occupancy Permits | *no section existed* | **46,422** |
| Rights of Occupancy | *no section existed* | **30,015** |

The report now describes a working registry rather than a near-empty one, because it is measuring
the work rather than the age of the documents.

### 12.2 Two sections added

`InstrumentTypeNormalizer` gained two groups, both of which had no home before:

- **10 — Occupancy Permits Captured (46,422)**. The largest single activity in the register.
- **11 — Rights of Occupancy Captured (30,015)**. Second largest.

Neither exists in the 2025 PRS document. They are added because they are the bulk of what the
Deeds registry actually does, and a progress report that omits 76,000 captured instruments is not
describing the department's year.

Filter care was needed: "Right of Occupancy", "Certificate of Occupancy" and "Occupancy Permit" all
contain the word *Occupancy*, and `ST Assignment (Transfer of Title)` is an OP transfer rather than
an assignment. The predicates exclude each other explicitly.

### 12.3 Gender now has its own chart on every section

Previously gender was only table columns, and only sections 14 and 16 charted it — so the dimension
the entire backfill existed to produce was nearly invisible. Every monthly section now renders a
**Gender breakdown** panel beside the land-use chart, cut from the same rows, with `Not Recorded`
shown explicitly rather than dropped.

Verified: **the gender chart and the land-use chart sum to the same total in every section.**

Three sections have no gender panel, correctly — bank ranking (ranks lenders, not people), searches
(`file_search_requests` has no gender column), and section 14, which *is* the gender chart.

### 12.4 On "Agriculture is missing"

It was not missing; it was invisible. On the transaction-date basis whole sections held only a few
hundred rows, so Agriculture sat at 11 and disappeared inside a stacked column. On the capture-date
basis it is present in every section that carries a land use:

| Section | Agriculture, 2026 |
|---|---:|
| Rights of Occupancy | 838 |
| Certificates of Occupancy | 466 |
| Deed of Assignment | 204 |
| Deed of Mortgage | 103 |
| Applications for Conversion | 97 |
| Deed of Release | 62 |
| Occupancy Permits | 37 |
| Deed of Devolution | 36 |

One genuine exception: **section 15 (OSS) shows only Residential and Commercial**, because
`oss_applications.application_type` contains only those two values across all 350 rows and
`land_use` is entirely null. That is a data-capture gap, not a normaliser gap.

`LandUseNormalizer` folds `AGRICULTURAL` (1,409 rows), `Agriculture` (172), `AG`, `AGRIC` and
`CON-AG` onto one category, so the case and abbreviation variants no longer fragment it.

### 12.5 Page cost

15 sections and 27 charts render in **6.3 seconds**, against 3.5s for the previous 13 sections and
13 charts. The growth is the two new sections and the 12 gender panels, not a regression — the
non-sargable join and the duplicate-query fixes from [§11](#11-what-shipped) still hold.

---

## 13. Second review round (2026-08-03)

### 13.1 Gender has its own table

Gender and land use previously shared one table of up to eleven columns. They are now two tables
per section, each footing to the same Total.

Beyond width, the reason is conceptual: one flat table implies one set of columns describing one
thing, when in fact these are **two independent cuts of the same rows**. Conflating them is exactly
what produced the source report's irreconcilable totals — Deed of Assignment printed gender 1,220,
categories 1,256 and total 1,248 ([10-data-quality-audit.md](10-data-quality-audit.md)). Two
tables that visibly agree make the relationship the point rather than an accident.

`Not Recorded` is always a column, never a silent omission. With 71% of the gender dimension
inferred and the remainder genuinely unknown, a table that dropped it would overstate every share
on the row.

Verified: **gender table total = land-use table total, in all 12 monthly sections.**

### 13.2 Land use recovered from the file-number prefix

Every KLAES-generated file number encodes the land use — `RES-2026-1862`, `CON-RES-2026-1308`,
`AG-RC-2026-12` — per [.agent/skills/klaes/SKILL.md §5](../../.agent/skills/klaes/SKILL.md). The
mapping now lives in `LandUseNormalizer` in two forms: `deriveFromFileNumber()` for PHP, and
`sqlEffectiveLandUse()` which emits the same mapping as a T-SQL `CASE` so the fallback applies
inside the aggregation query without exploding the `GROUP BY` cardinality.

Applied in two places:

1. **Live, in every query.** `COALESCE(land_use, <prefix>)` — so new rows are covered without
   anyone re-running a backfill. This also gives `deed_registrations` a land use for the first
   time: that table has no `land_use` column at all, so the file number is its only signal.
2. **Persisted**, via `php artisan landuse:backfill`
   ([BackfillLandUse.php](../../app/Console/Commands/BackfillLandUse.php)).

Backfill result:

| Table | Empty | Recovered | Rate |
|---|---:|---:|---:|
| `file_history_staging` | 4,897 | **4,887** | 99.8% |
| `CofO_staging` | 229 | **189** | 82.5% |
| `pra` | 8,885 | **231** | 2.6% |
| `file_indexings` | 264 | **68** | 25.8% |

`pra` is low because its empty rows are overwhelmingly legacy or temporary numbers — `TEMP-10823`,
`KN 7593` — which carry no land use to recover. **Those are left null rather than guessed** and
surface as `Uncategorised`, which is the honest answer.

Effect on the 2026 report:

| Section | Uncategorised before | After |
|---|---:|---:|
| Deed of Assignment | 493 | **74** (0.7%) |
| Rights of Occupancy | 954 | **953** (3.2%) |
| Deed of Release | 62 | **24** (0.5%) |
| Certificates of Occupancy | 30 | **20** (0.1%) |
| Deed of Devolution | 4 | **1** (0.1%) |

Section 10 (Occupancy Permits) keeps 11,696 uncategorised — 25.2%. Those are the `TEMP-` numbers,
and the figure is left visible on purpose: it is a true statement about OP capture, not a defect in
the report.

Provenance is recorded in a new `land_use_source` column on all four tables, so a derived value is
never mistaken for a captured one and the whole thing reverses with
`php artisan landuse:backfill --reset`.

### 13.3 Page cost

27 charts and 27 tables across 15 sections render in **6.1 seconds** — unchanged by the extra
tables, since they are shaped from data already in memory rather than re-queried.

---

## 14. Third review round (2026-08-05) — the deed source changes

Client instruction: *"deeds records should come from deeds registration, and the year should be
transaction date not created at again."* Both halves reverse decisions taken on 2026-08-03 in
[§12.1](#121-date-basis-is-now-the-capture-date).

### 14.1 One source: `deed_registrations`

The deed union is gone. `pra` (109k rows) and `file_history_staging` (24k) are **back-capture**
tables — historical instruments keyed in from paper over the years. Counting them made the progress
report a picture of the data-entry backlog rather than of departmental output.

`deed_registrations` is what the Deeds Registration module
([`InstrumentRegistrationController`](../../app/Http/Controllers/InstrumentRegistrationController.php))
writes: 9,502 live rows, every one a registration that passed through the registry.

`CofO_staging` went the same way, on the same instruction — section 05 is now an ordinary deed
section reading the `COFO` group, and `CofOStats` is deleted rather than left as a second path to
the same number.

### 14.2 Date basis: `deeds_date`

The date the deed entered the register. Unlike the staging tables — where every date column is
`nvarchar` and a bare `YEAR()` throws SQLSTATE[22007] — `deed_registrations.deeds_date` is a real
`date`, so the whole `TRY_CONVERT` fallback chain is gone with the union.

### 14.3 Effect

| # | Section | Capture basis (§12.1) | Deeds-date basis |
|---:|---|---:|---:|
| 02 | Deed of Assignment | 10,536 | **280** |
| 03 | Deed of Mortgage | 7,384 | **8** |
| 04 | Bank ranking | 6 institutions | **2 institutions, 5 facilities** |
| 05 | Certificates of Occupancy | 19,279 | **194** |
| 07 | Deed of Release | 4,553 | **14** |
| 08 | Deed of Devolution | 750 | **2** |
| 10 | Occupancy Permits | 46,422 | **8,947** |
| 11 | Rights of Occupancy | 30,015 | *section gone* |

Section 11 disappears because `deed_registrations` holds no `Right of Occupancy` rows at all — RoO
registration exists only in the back-capture tables. The section is omitted rather than rendered as
zero, which is the standing rule.

Only 2026 is offered in the year selector for deeds: every row in `deed_registrations` carries a
2026 `deeds_date`. Lands, OSS and search still contribute their own years.

### 14.4 Deduplication removed, deliberately

The old union deduplicated on (file number, date) because one registration could exist in two
staging tables. With a single source that key destroys real data: `registration_number` is distinct
on all 9,502 rows, while `(fileno, deeds_date, instrument_type)` collapses to 9,437. The 65 rows it
would discard are separate register entries — mostly ST Fragmentation units registered against one
parent file on one day.

### 14.5 Two costs this basis carries

Both are visible on the page rather than papered over.

**Gender coverage collapses on the OP section.** `deed_registrations` has no gender column, so
gender is still reached by joining the file number to `file_indexings.gender`. 8,939 of the 9,502
rows are Occupancy Permits numbered `TEMP-#####`, which do not exist in `file_indexings`. Section 10
therefore resolves gender for 0.0% of rows and the gender panel is a single Not Recorded band.
Coverage elsewhere is workable: mortgage 75%, release 64%, assignment 30%, CofO 11%.

**Land use likewise.** The table has no `land_use` column, so it is recovered from the file-number
prefix. `RES-2026-640` and `CON-COM-2006-74` resolve; `TEMP-#####` cannot, so section 10 is 8,946 of
8,947 Uncategorised. Sections 02 and 05 resolve normally (assignment: 175 Residential, 53 Commercial,
5 Agriculture, 5 Industrial, 42 Uncategorised).

Both would be fixed by resolving `deed_registrations.prop_id` through `PropID_Master` to the parcel's
land file, rather than matching on the temporary file number. Not done here — it is a join with its
own correctness questions, not a one-line change.

### 14.6 Mortgagee, without a Mortgagee column

Section 04 ranked `pra.Mortgagee`. `deed_registrations` has only `grantor` and `grantee`. In a
mortgage the mortgagor grants to the mortgagee, so grantee is the lender — but the register holds
rows entered the other way round (`JAIZ BANK PLC -> JAMAL BALA`). Whichever side normalises to an
institution is taken as the mortgagee, falling back to grantee when neither does.

The ranking reconciles to the mortgage section: 5 institutional facilities (Jaiz 4, UBA 1) plus 3
held by 2 private mortgagees = 8, section 03's total.

### 14.7 Unchanged

Lands, OSS and search still date on `created_at`, and correctly: a commissioned file, an OSS
application and a search request are all *born* in KLAES, so the row's creation is the event. Only
the deed tables had a capture date distinct from the transaction.

### 14.8 Invariants and page cost

Re-verified across all 14 sections: **gender total = land-use total = section total, in every
section and every month**. 14 sections, 25 chart payloads, **1.2 seconds** — down from 6.1s, since
the page no longer scans 133,939 staging rows.
