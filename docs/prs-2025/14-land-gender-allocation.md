# 14 — Land Department: Direct Government Allocation Based on Gender (2025)

> Source image 5. Table header: *"DIRECT GOVERNMENT ALLOCATION BASE ON GENDER"*
> Chart caption: *"Figure 3: Bar chart Present application recerved for Direct allcation base on status withhin Year 2025"*

## Table (verbatim)

| Base | Male | Female | Joint | Private Orga | Governmental | Row total *(computed)* |
|---|---:|---:|---:|---:|---:|---:|
| DIRECT ALLOCATION | 5,570 | 448 | 8 | 0 | 0 | 6,026 |
| DIRECT CONVERSION | 701 | 55 | 3 | 0 | 0 | 759 |
| DIRECT INDUSTRAIL | 58 | 0 | 0 | 0 | 0 | 58 |
| DIRECT AGRICULTURE | 15 | 1 | 2 | 0 | 0 | 18 |
| **Column total** *(computed)* | **6,344** | **504** | **13** | **0** | **0** | **6,861** |

`INDUSTRAIL` is as-printed. No totals row is given in the source.

## ⚠ The row labels are misaligned with the direct-allocation table

Compare the row totals above against the category totals in
[13-land-direct-allocation.md](13-land-direct-allocation.md):

| Direct allocation table | | Gender table | | Match? |
|---|---:|---|---:|---|
| Residential | 6,021 | DIRECT ALLOCATION | 6,026 | close — off by 5 |
| Commercial | 701 | DIRECT CONVERSION | 759 | **male alone = 701, exactly** |
| Agriculture | 58 | DIRECT **INDUSTRAIL** | 58 | exact — **label swapped** |
| Industrial | 18 | DIRECT **AGRICULTURE** | 18 | exact — **label swapped** |

Two of the four rows match a land-use total *exactly* but carry the **wrong label**: the figure
sitting under "Direct Industrial" (58) is the Agriculture total, and the figure under "Direct
Agriculture" (18) is the Industrial total. The Agriculture and Industrial rows have been
transposed.

The Commercial ↔ Direct Conversion row is worse: the *male* cell (701) equals the Commercial
category total exactly, which means a land-use total has been pasted into a gender cell and the
female (55) and joint (3) values built around it.

**Conclusion: this table is not an independent gender breakdown.** It is the direct-allocation
land-use row re-cut, with two labels transposed and at least one cell mis-keyed. It cannot be
used as printed.

## Other structural problems

1. **The "Base" column mixes two different dimensions.** "Direct Allocation" and "Direct
   Conversion" are *application streams*; "Direct Industrial" and "Direct Agriculture" are *land
   uses*. These are not four members of one category, so the table has no consistent grain and
   the rows cannot legitimately be summed.
2. **Title vs. caption mismatch.** The table says "base on **gender**"; the chart caption and
   chart title say "base on **status**". One of the two is wrong.
3. **`PRIVATE ORGA` and `GOVERMENTAL` are zero in all four rows.** In a report on *government*
   allocation, a Governmental column that is entirely zero is implausible — more likely never
   captured. Compare the Deeds report, where `JOINT` is zero throughout for the same reason.

## Notable figures (treat as indicative only)

- Overall female share **7.3%** (504 / 6,861) — better than the Deeds report's resettlement
  figure of 4.9% ([06](06-resettlement-allocation.md)) and CofO's ~4%, but still low.
- Joint applications: 13 in the year. Unlike the Deeds report, `JOINT` here is **not** uniformly
  zero — which suggests joint capture does work in the Land Department's system and its absence
  in the Deeds tables is a genuine capture gap, not a policy reality. Useful evidence for D2.

## Chart (image 5)

Title *"DIRECT GOVERMENT ALLOCATION BASE ON STATUS"*. A 3-D column chart with the Y axis
formatted as **percent 0–100%** while the plotted values are raw counts, so every non-zero bar
saturates at 100% and the chart conveys nothing.

The **X axis labels are the values themselves** — `5570`, `701`, `58` — because the category
range was set to the male column instead of the Base column. The fourth row (15) and the two
zero columns render as empty lozenges.

This chart is unsalvageable. Replace with a horizontal stacked bar per stream, Male / Female /
Joint / Organisation, and a percentage axis only if the values are actually converted to shares.

## KLAES data source

`oss_applications` carries **`sex`**
([LandsOneStopShopApplication.php:69](app/Models/LandsOneStopShopApplication.php#L69)) alongside
`application_type` and `land_use` — so gender, stream and land use are all on the same row. This
table can therefore be generated correctly as a **single cross-tab** (stream × gender, and
separately stream × land use), which structurally prevents the label transposition and
dimension-mixing seen above.

That is the fix: don't build a gender table and a land-use table separately from the same data
and hope they agree. Emit one query with both dimensions and pivot it two ways.

**Also resolves D2 for this department:** unlike the Deeds tables — where gender lives on
`file_indexings` / `mls_file_no` and needs a join — Land Department gender is native to
`oss_applications`. Expect the Land sections to be materially easier than the Deeds sections.
