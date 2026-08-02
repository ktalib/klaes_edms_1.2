# 01 — Survey Department: Layouts Implemented (2025)

> Source images 1–2.
> Header text: *"LAYOUT IMPLEMENTED : The Departement had facilated the implementation FIVE(5) layout in 2025"*

## Table (verbatim)

| Name of layout | Number of plot | Residential | Commercial | Industry | Facilities |
|---|---:|---:|---:|---:|---:|
| BAGADAWA VILLEGE | 1,142 | 0 | 0 | 1,142 | 6 |
| BUK RIMI ZAKARA | 4,425 | 4,230 | 195 | 0 | 3 |
| DAWANAN | 741 | 718 | 23 | 0 | 0 |
| DAWANAN EXTENTION | 3,543 | 3,543 | 0 | 0 | 5 |
| RUNKUSAWA | 3,082 | 3,082 | 0 | 0 | 4 |
| **TOTAL** *(not in source — computed)* | **12,933** | **11,573** | **218** | **1,142** | **18** |

Spellings above are as-printed (`VILLEGE`, `EXTENTION`). Canonical names should be
`Bagadawa Village`, `Buk Rimi Zakara`, `Dawanan`, `Dawanan Extension`, `Runkusawa`.

## Arithmetic check — CLEAN

- Plot column sums to 12,933 and equals Residential + Commercial + Industry (11,573 + 218 + 1,142).
- **Facilities is not part of the plot count.** It is a count of facility *sites* (schools,
  clinics, markets, open space) within the layout, not plots. Do not add it into the total —
  the source chart does not.
- Bagadawa Village is 100% industrial (1,142 plots); the other four are overwhelmingly
  residential. Only Buk Rimi Zakara and Dawanan carry commercial plots.

## Chart (image 2)

Clustered vertical bar, one group per layout, five series:
`Number of plot`, `Residential`, `Commercial`, `Industry`, `Facilities`. Data labels shown,
rotated. Legend on top. No axis title. Zero values are printed as `0` on the axis.

Weakness: `Number of plot` is a *total* plotted alongside its own components, so the first bar
double-counts the rest. In the KLAES version, either drop the total series or use a stacked bar
of the three land-use categories (which sums to the total by definition), with Facilities on a
separate secondary display.

## KLAES data source

No existing table holds layout implementation. Candidates:

- `districts` / `street_names` hold locality names but not layout plot inventories.
- Plot-level data lives per-file (`file_indexings`, `mls_file_no`), which is a *file* count, not
  a *layout* count — they will not agree.

**Conclusion: this table needs a new source.** Proposed minimal schema:

```
survey_layouts
  id, layout_name, lga_id (nullable), year_implemented,
  plots_total, plots_residential, plots_commercial, plots_industry,
  facility_sites, remarks, created_by, timestamps
```

Data entered by the Survey Department, not derived. A validation rule should enforce
`plots_residential + plots_commercial + plots_industry = plots_total`, since the 2025 data
already satisfies it and the constraint is what makes the table trustworthy.
