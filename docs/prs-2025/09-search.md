# 09 — Search (Jan–Dec 2025)

> Source images 12–13. Header: *"SEARCH"*

## Table (verbatim)

**The month column is missing from the source table** — the leftmost column is `MALE` and there
are no row labels at all. There are 12 data rows plus a total row, so the rows are Jan–Dec by
position. Assigned below on that basis; **confirm with PRS before relying on the monthly split.**

| Month *(inferred)* | Male | Female | Commercial | Residential |
|---|---:|---:|---:|---:|
| January | 9 | 0 | 0 | 9 |
| February | 2 | 0 | 0 | 2 |
| March | 9 | 0 | 2 | 6 |
| April | 8 | 0 | 3 | 4 |
| May | 2 | 0 | 1 | 0 |
| June | 4 | 0 | 3 | 4 |
| July | 25 | 1 | 10 | 27 |
| August | 5 | 0 | 0 | 5 |
| September | 10 | 0 | 1 | 9 |
| October | 10 | 0 | 3 | 6 |
| November | 6 | 1 | 4 | 13 |
| December | 33 | 0 | 45 | 24 |
| **TOTAL (as printed)** | **123** | **2** | **72** | **109** |

There is **no Total column** on this table, unlike every other table in the report.

## Arithmetic check — every column foots

| Column | Sum of the 12 months | Printed total | Verdict |
|---|---:|---:|---|
| Male | 123 | 123 | ✓ |
| Female | 2 | 2 | ✓ |
| Commercial | 72 | 72 | ✓ |
| Residential | 109 | 109 | ✓ |

**This is the cleanest table in the report** — all four columns reconcile exactly.

The two views still disagree on the count of searches, though: gender says **125** (123 + 2),
land use says **181** (72 + 109). The 56-search gap is the same gender-vs-category problem as
every other table (searches requested by organisations/companies have a land use but no gender,
which would account for it — plausible, since corporate and legal-practitioner searches are
common).

Per-month the two views also diverge sharply: December is 33 by gender but 69 by land use;
November 7 vs 17.

## Notable figures

- **~181 searches in 2025** on the land-use basis (the more complete of the two counts).
- **December is a massive outlier: 45 commercial searches**, against a monthly average of ~2.5
  for the rest of the year, and more than the other eleven months combined (27). July is the
  secondary peak (37 by land use). This end-of-year surge in commercial due-diligence is the
  headline finding and needs an explanation in the narrative — year-end transaction closing, a
  policy deadline, or a data artefact.
- Searches are the lowest-volume activity in the report (~181 vs 907 CofOs and 6,047 permits).
- Female requesters: 2 out of 125.

## Charts (images 12–13)

Two charts for one table:

- **Image 12** — stacked column, legend `SEARCH MALE / FEMALE / COMMERCIAL / RESIDENTIAL`. Almost
  entirely blank; only the total bar renders at the far right, because the axis is scaled to the
  total. Unusable as printed.
- **Image 13** — the same chart with the axis fixed, X labels showing raw row numbers `1`–`14`
  instead of month names (a direct consequence of the missing month column). The December and
  total bars are the only substantial ones.

Both need rebuilding with proper month labels and no total bar.

## KLAES data source

Legal Search is a substantial existing subsystem — `LegalSearchService`
([app/Services/LegalSearchService.php](app/Services/LegalSearchService.php)),
`FileSearchRequest` ([app/Models/FileSearchRequest.php](app/Models/FileSearchRequest.php)), and
extensive documentation in [docs/LEGAL_SEARCH_OVERVIEW.md](docs/LEGAL_SEARCH_OVERVIEW.md).

Two things to settle first:

1. **What counts as a "search"?** A paid, formally requested official search (a registry
   product) is not the same as a staff member running a lookup in the Legal Search module. The
   PRS figure of ~181/year is far too low to be system lookups, so it must mean *formal search
   applications*. Count those — `FileSearchRequest` rows with a completed/paid status, not
   `LegalSearchService` invocations.
2. **Gender/land use of what?** Presumably the applicant's gender and the land use of the
   property searched. Confirm both are captured on the request record.

This is also the table where KLAES can add the most value over the spreadsheet: turnaround time
per search, requester type (solicitor / bank / individual / government), and outcome — none of
which the manual report attempts.
