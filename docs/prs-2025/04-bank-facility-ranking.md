# 04 — Bank Ranking Based on Facility Ranking (2025)

> Source image 7 (top). Headers: *"BANK RANKING BASE"*, *"BANK PARTITION / FACILITY RANKING"*

## Table (verbatim)

| Bank | Facilities |
|---|---:|
| JAIZ | 31 |
| FIDELITY | 18 |
| UNION | 3 |
| FCMB | 3 |
| ACCESS BANK | 2 |
| UNITY BANK | 2 |
| UBA | 1 |
| STANBIC IBTC | 1 |
| **TOTAL** *(not printed — computed)* | **61** |

*(Source order is Access, Unity, UBA, Union, Fidelity, FCMB, Stanbic IBTC, Jaiz — i.e.
unsorted. Re-sorted descending above; a table titled "ranking" should be sorted by rank.)*

## Arithmetic check — CLEAN

**61 facilities, which exactly matches the Deed of Mortgage annual total of 61.** This is the
only cross-table reconciliation in the entire report that holds, and it confirms the intended
grain: *one row per registered mortgage, attributed to the mortgagee bank.*

## Notable figures

- **Jaiz Bank alone accounts for 51%** of all mortgage facilities (31/61); Fidelity 30% (18/61).
  Together the two hold **80% of the state's registered mortgage market**.
- The remaining six banks share 12 facilities between them.
- Jaiz is a non-interest (Islamic) bank — its dominance in Kano State is a genuine finding and
  the most quotable number in the report.
- **Federal Mortgage Bank of Nigeria does not appear at all.** The PRS observation calls this
  out explicitly. Note the difference between "zero facilities" and "not in the list" — the
  KLAES version should render known-but-zero banks as explicit zero rows so the absence is
  visible rather than inferred.

## Chart (image 7)

Vertical column chart, dark background, banks on X, count on Y, data labels on. Axis titles are
the placeholders "X AXIS" and "Y AXIS". Unsorted, so the shape of the distribution is not
apparent until you read the numbers.

For the KLAES version: **sort descending and use a horizontal bar chart** — bank names are long
and rotate badly on a vertical axis, and the ranking reads top-to-bottom naturally.

## KLAES data source

Derived from the same mortgage query as [03](03-deed-mortgage.md); the bank is the mortgagee:

- `pra` / `file_history_staging` → `Mortgagee` column
- `instrument_capture` → `party_2_name`

(Both are already aliased to `party_2` in
[MortgageController.php:88](app/Http/Controllers/MortgageController.php#L88).)

### The normalisation problem

`Mortgagee` is free text. "JAIZ", "Jaiz Bank", "Jaiz Bank Plc" and "JAIZ BANK PLC" will all
occur, and a naive `GROUP BY` produces a fragmented, wrong ranking. A lookup/normaliser is
required — the same shape as the existing `DepartmentNormalizer` used for file-tracker
departments.

There is already a `VfcBank` model in [app/Models/VfcBank.php](app/Models/VfcBank.php) — check
whether it holds a usable bank list before creating a new `banks` table.

Proposed: a `banks` reference table (name, short_name, category: commercial / non-interest /
federal / microfinance) plus an alias table, and a `BankNameNormalizer` service. Categorising
banks also lets the report answer the PRS observation directly ("non-interest banks hold X% of
the market") instead of leaving the reader to know that Jaiz is non-interest.
