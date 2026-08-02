# 08 — Deed Devolution (Jan–Dec 2025)

> Source image 11. Header: *"DEED DEVOLUTTION"*

## Table (verbatim)

Header as printed: `MALE` / `FEMALR` (typo for FEMALE) / `COMMERCIAL` / `RESIDENTIAL` /
`INDUSTRY` / `AGRICULTURE` / `TOTAL`.

| Month | Male | Female | Commercial | Residential | Industry | Agriculture | Total |
|---|---:|---:|---:|---:|---:|---:|---:|
| January | 5 | 0 | 5 | 0 | 0 | 10 | 20 |
| February | 1 | 0 | 1 | 0 | 0 | 2 | 4 |
| March | 0 | 0 | 0 | 0 | 4 | 20 | 24 |
| April | 0 | 0 | 0 | 4 | 1 | 10 | 15 |
| May | 0 | 0 | 1 | 1 | 0 | 2 | 4 |
| June | 1 | 1 | 0 | 0 | 0 | 7 | 9 |
| July | 0 | 0 | 0 | 0 | 0 | 37 | 37 |
| August | 0 | 0 | 0 | 0 | 0 | 5 | 5 |
| September | 0 | 0 | 0 | 0 | 0 | 10 | 10 |
| October | 0 | 0 | 0 | 1 | 2 | 10 | 13 |
| November | 1 | 0 | 0 | 0 | 0 | 17 | 18 |
| December | 0 | 1 | 0 | 2 | 1 | 33 | 37 |
| **TOTAL (as printed)** | **7** | **3** | **7** | **8** | **8** | **163** | **196** |

## Arithmetic check

| Column | Sum of the 12 months | Printed total | Verdict |
|---|---:|---:|---|
| Male | 8 | 7 | ✗ off by −1 |
| Female | 2 | 3 | ✗ off by +1 |
| Commercial | 7 | 7 | ✓ |
| Residential | 8 | 8 | ✓ |
| Industry | 8 | 8 | ✓ |
| Agriculture | 163 | 163 | ✓ |
| Total | 196 | 196 | ✓ |

The two gender errors are equal and opposite (−1 male, +1 female), so `M + F = 10` either way —
consistent with a single row being mis-keyed into the wrong gender column in the totals.

Remaining breaks:

- Categories: `7 + 8 + 8 + 163 = 186` vs. total **196** — 10 devolutions uncategorised.
- Gender: `M + F = 10` vs. total **196**. Only 5% of devolutions have a gender recorded. This is
  expected for devolution — it is transmission on death, and the recipients are frequently
  estates, multiple heirs, or families rather than a single gendered individual. **The gender
  columns are not meaningful for this instrument and should be dropped from the KLAES version**
  (or replaced with a "beneficiary type" split: individual / estate / family / joint heirs).

## Notable figures

- **196 devolutions in 2025.**
- **Agriculture is 83% of all devolutions (163/196)** — the exact inverse of every other table in
  the report, which are dominated by residential. This is a real and interesting finding:
  agricultural land in Kano State moves overwhelmingly by inheritance rather than by sale.
  Compare with assignments, where agriculture does not even appear as a category.
- Peaks: **July (37) and December (37)**, both almost entirely agricultural (37 and 33). March
  (24) and January (20) follow. The July spike is 100% agriculture.
- The seasonality is worth investigating — devolution filings clustering in July and December may
  track agricultural cycles, or may simply reflect batch processing at the registry.

## Chart (image 11)

Clustered column, months plus TOTAL, seven series, on a heavy gridline background. Data labels
overlap so densely at the low end that the monthly values are unreadable (e.g. "1010024",
"00110 24" are actually adjacent labels colliding). The TOTAL group again dominates.

Rebuild as a stacked column of the four land-use categories, months only. Given agriculture is
83%, consider a small-multiples or log presentation so the other three categories stay visible.

## KLAES data source

Same three-table union as the other deeds. The canonical instrument type in
[InstrumentRegistrationController.php:1043](app/Http/Controllers/InstrumentRegistrationController.php#L1043)
is **`Devolution Order`** — not "Deed of Devolution". Filter on that, and check whether
`Deed of Devolution` / `Letters of Administration` / `Vesting Assent` variants also exist and
should be folded in.

Land use from `land_use`. Skip the gender columns per the note above.
