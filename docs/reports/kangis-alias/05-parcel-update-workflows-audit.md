# Parcel Update Workflows — Audit (CoP, Subdivision, Merger, Extension, Separation)

**Date:** 2026-07-10
**Scope:** Verify that the five parcel-update workflows save `parent_prop_id` and lineage
correctly through the commissioning flow (`MlsFileNoController::generateMlsFileNumber` /
`generateBatch`), and that the Legal Search timeline can reconstruct each chain.

---

## 1. Verified working (code + live data)

### Subdivision (batch commissioning — the standard path)
Checked live batches RES-2026-2803/2804 (mother RES-RC-1986-57), CON-AG-2026-102/103
(CON-AG-2025-83), RES-2026-2230/2231 (RES-RC-1982-448):

- `file_indexings.parent_prop_id` = mother's prop_id ✅ (e.g. 67447)
- `file_indexings.related_fileno` = `["<mother>"]` ✅
- `fileNumber.parent_prop_id` / `related_fileno` mirrored ✅
- PRA `Subdivision` row per child with own `prop_id` and mother named in comments ✅
- Mother decommissioned → `decommissioned_files` (successor pointer where the column exists) ✅
- Two-way `file_indexing_links` ✅ · `mls_file_no.source = 'Subdivision'`, `commissioning_date` set ✅

### Merger (single-path commissioning)
Code path complete: `parent_prop_id` = CSV of all source prop_ids, all sources
decommissioned with successor pointer, history reassigned via `updateHistoricalPropId`.
**No merger has been commissioned yet** (2 approved, pending) — verify the first live one.

### Change of Purpose (rename path, `application_type = 'change_of_purpose'`)
Old file archived with successor pointer, `fileNumber`/`mls_file_no`/`file_indexings`/staging
renamed in place, prop_id inherited (same parcel), CoP PRA row with `old -> new` comments
(this is what anchors the timeline lineage chain).

### Extension
`parent_prop_id` + `related_fileno` + decommission + `Plot Extension` PRA row all correct
(code-verified; the `<file> AND EXTENSION` variant).

---

## 2. Defects found and fixed (2026-07-10)

| # | Defect | Impact | Fix |
|---|--------|--------|-----|
| 1 | `generateBatch` used `$parcelNotifier` without defining it (only `generateMlsFileNumber` defined it) | **Every Plot Separation commissioning crashed** before commit → full rollback, 500 error. (Separation always batches ≥2 plots, and zero separations exist in live data — the crash was never hit in production yet.) | Defined `$parcelNotifier = app(ParcelUpdateNotificationService::class)` in `generateBatch` |
| 2 | `resolveSourceValue()` had no `separation` branch → source fell through to `'Direct Allocation'` | `mls_file_no.source` wrong; the PRA branch never matched → **no "Plot Separation" transaction row** → no parcel-update row on the timeline, so the lineage chain (mother commissioning → Separation → child commissioning) could not anchor | Added `separation` branches (application_type + file_option); single-path `in_array` now includes `'Separation'`; instrument type standardised to `Plot Separation` |
| 3 | `generateBatch` PRA block reset `$parentPropId = null` and re-resolved with a bare `->value('prop_id')` (no allocator fallback, no separation branch) | `pra.parent_prop_id` **empty on every batch-commissioned child** (confirmed live on all subdivision children) — Legal Search treats `pra.parent_prop_id` as the authoritative family link when finding a decommissioned mother's children | Removed the reset; the batch now uses the fully-resolved value from the lineage block |
| 4 | Batch PRA passed `related_fileno`, a key `PraRecordService` silently drops | Related file numbers never persisted on batch PRA rows | Mapped to `related_file_number` (the persisted column) in both paths |
| 5 | CoP decommission row stamped `commissioning_date = now()` | Archive claimed a years-old file was commissioned on the CoP day (violates the "no fake legacy dates" rule, §1.1 of doc 04) | Preserves the old file's real `mls_file_no.commissioning_date` (or `fileNumber`'s), else NULL |
| 6 | CoP rename kept the OLD commissioning_date on the renamed `mls_file_no` row | The successor's "File Commissioning" timeline entry predated the Change of Purpose itself (violates §1.3 of doc 04: new file starts its own lifecycle) | Renamed row now gets `commissioning_date = now()` (the CoP date) |

---

## 3. Legacy data gaps (pre-fix rows — candidates for the one-off data-repair script)

- `pra.parent_prop_id` empty on existing batch-commissioned subdivision children
  (RES-2026-2803/2804, CON-AG-2026-102/103, RES-2026-2230/2231). Values recoverable from
  `file_indexings.parent_prop_id`.
- **RES-2026-2270** (subdivision child of MLKN 3020, commissioned 2026-05-25 under older code):
  `source = 'Direct Allocation'`, **no Subdivision PRA row**, empty `parent_prop_id` —
  lineage survives only via `related_fileno` + `decommissioned_files`.
- CoP legacy rows: CON-COM-2026-302 PRA row has `prop_id 10198` vs `file_indexings` 52666
  (mismatch), empty instrument_type; CON-COM-2026-227 has bare-string `related_fileno`
  (not JSON) and no prop_id.
- `decommissioned_files.commissioning_date` empty for all archived legacy mothers (expected —
  their real commissioning dates are unknown; blank is correct per client rule §1.1).

These belong with action item #5 in
[04-client-meeting-timeline-lineage-notes.md](04-client-meeting-timeline-lineage-notes.md)
(reviewed one-off script against production).
