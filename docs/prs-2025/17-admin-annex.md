# 17 — Administrative Annex: One Stop Shop Consumables Request

> Source image 10, appended to the end of the Land Department report.

## Content (verbatim)

> "One stop shop is in need of replacement of four (4) colour HP toner Cartridges, enable us
> continue with our routine official work"

| SN | Items |
|---:|---|
| 1 | HP toner cartridge 216A (black) |
| 2 | HP toner cartridge 216A (magenta) |
| 3 | HP toner cartridge 216A (yellow) |
| 4 | HP toner cartridge 216A (cyan) |

## Status: out of scope for the report module

This is a **procurement/requisition request**, not statistical data. It has no year, no counts to
aggregate and no data source in KLAES. It has been physically appended to the annual progress
report, presumably because the report was the document going up the chain that month.

## Handling

Do **not** model this in the PRS Annual Report module. Two options:

1. **Preferred** — exclude it. Requisitions belong in a separate memo, not in a statistical
   report. Flag to PRS that mixing them weakens both documents.
2. If PRS insist the report must carry departmental requests, the module's per-section
   **narrative field** (Phase 5 of [11-implementation-plan.md](11-implementation-plan.md)) can
   hold free text and a simple item list as a closing annex. No schema, no aggregation, no chart.

Recorded here only so that nobody later finds this page in the source images and assumes a
dataset was missed.
