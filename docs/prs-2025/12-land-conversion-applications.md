# 12 — Land Department: Applications for Conversion (2025)

> Source images 1–3 of the Land Department report.
> Document header: *"PROGRESS REPORT FOR LAND DEPARTMENT FROM JANUARY TO DECEMBER **2026**"*
> Table header: *"APPLICATION FOR CONVERSION FOR **2025**"*

## ⚠ Year mismatch in the document header

The report title says **2026** but every table, figure caption and chart inside it says **2025**.
Since 2026 is not yet complete, the content is 2025 and **the title is a typo**. Worth correcting
before this circulates — a document headed "January to December 2026" dated mid-2026 will be read
as a full-year 2026 report.

## Table (verbatim)

| S/NO | Residential | Commercial | Agriculture | Industrial | |
|---|---:|---:|---:|---:|---|
| **TOTAL** | **5,982** | **234** | **311** | **68** | |

Annual total (not printed): **6,595**.

There is no monthly breakdown and no gender breakdown — this is a single annual row. The table
has an empty `S/NO` column and two trailing blank rows, both spreadsheet artefacts.

## Arithmetic check

Nothing to reconcile — a single row with no printed total. Note the column order here
(Residential, Commercial, **Agriculture, Industrial**) is the reverse of the direct-allocation
table's (…**Agriculture, Industrial**) — actually the same, but *both* differ from the
Deeds report's ordering. See [10](10-data-quality-audit.md#4-inconsistent-dimensions-across-tables).

## Notable figures

- **6,595 conversion applications in 2025.**
- **Residential is 90.7%** (5,982). Commercial 3.5%, Agriculture 4.7%, Industrial 1.0%.
- Agriculture (311) exceeds Commercial (234) here — the only table in either report where that
  happens outside Devolution.

## Charts (images 2–3)

Title: *"APPLICATION FORCONVERSION"* (missing space). 3-D bar chart, four categories, Y axis to
6,000.

Three problems:

1. **The chart is duplicated** — images 2 and 3 are the same chart appearing twice in the
   document, both captioned *"Figure 1"* above and *"APPLICATION FOR DIRECT ALLOCATION 2025"*
   below. The second occurrence should be deleted; as printed, the conversion chart sits directly
   under a heading announcing direct allocation, which mislabels it.
2. **3-D perspective distorts the bars.** The residential bar reads ~5,900 against a true 5,982,
   and the three small bars are rendered as flat lozenges on the floor of the chart with no
   readable height. Commercial (234), Agriculture (311) and Industrial (68) are visually
   indistinguishable from each other and from zero.
3. **No data labels**, so the three small categories are unreadable at any size.

With a 90/4/3/1 distribution, a plain 2-D horizontal bar with data labels is the only form that
works. Consider also showing the three minor categories in an inset at their own scale.

## KLAES data source

Conversion = **change of purpose**. The model exists:
[app/Models/ChangeOfPurposeApplication.php](app/Models/ChangeOfPurposeApplication.php),
table `change_of_purpose_applications`, with `land_use`, `purpose` and `new_purpose` fillable.

Also relevant: `oss_applications`
([app/Models/LandsOneStopShopApplication.php](app/Models/LandsOneStopShopApplication.php)) carries
`application_type`, `land_use`, `purpose`, `prev_land_purpose` and `sex`, and
[CommissionNewSTController.php:335](app/Http/Controllers/CommissionNewSTController.php#L335)
validates `application_type` against the enum **`Direct Allocation, Conversion`** — so the two
Land Department streams are already a first-class distinction in the codebase.

**Question to settle (D7):** does a conversion application live in
`change_of_purpose_applications`, in `oss_applications` with `application_type = 'Conversion'`,
or both? If both, which is authoritative for counting — and do they agree? Getting 6,595 out of
either one is the first validation test.

**Note on the land-use dimension:** for a conversion, the meaningful figure is arguably the
*new* purpose, not the current one. `prev_land_purpose` / `new_purpose` exist, so the KLAES
version can report the conversion matrix (from → to), which is strictly more useful than the
single column the spreadsheet manages. Confirm with PRS which direction their 5,982 counts.
