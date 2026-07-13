# Land Registry Audit & Correction — Summary

**Date:** 2026-07-13
**Scope:** `file_indexings.registry`, `.general_registry`, `.physical_registry` for the Lands + SIT registry family (Registry 1 / 2 / 3).

## Problem

`file_indexings.registry` was manually entered / inherited from unrelated sources, and had drifted far from the actual Registry 1/2/3 structure. Root causes found and fixed at the source (not just patched after the fact):

- The File Indexing create dialog auto-filled the `Registry` field from a **land-use → registry guess** (`INDUSTRIAL → "Registry 1 - Deeds"`, etc.) — unrelated to the file number's real prefix/year.
- `general_registry` had accumulated spelling drift ("Lands Registry" vs "Land Registry" vs "Land  Registry" with a double space) and outright wrong categories on Lands-family files (e.g. "Cadastral Registry", "KANGIS Registry", "Secret Registry" on RES/COM/CON- files).
- ~30% of rows had a blank `general_registry` / `physical_registry`.

## Source of truth

[config/file_ranges.php](../../config/file_ranges.php) — prefix + year → Registry 1/2/3, per KLAES Admin's registry chart. Updated this session:
- `RES-RC`: was capped at 1991, corrected to 1981–2026 (matches Registry 1).
- Added `RES` 1992–2026 → Registry 2 (Pool Office) — previously only documented in a comment, never a real range.
- Added `SIT` 1981–2026 → Registry 2.

## What was built

| File | Purpose |
|---|---|
| [app/Services/RegistryDetector.php](../../app/Services/RegistryDetector.php) *(new)* | Derives Registry 1/2/3 from a file number, reusing `FileLocationResolver::parse()`/`matchRange()` so `config/file_ranges.php` stays the single source of truth. Returns a structured reason (`matched`, `unparseable_no_year`, `prefix_not_in_registry_config`, etc.) for out-of-scope / bad file numbers. |
| [app/Console/Commands/FixFileIndexingRegistry.php](../../app/Console/Commands/FixFileIndexingRegistry.php) *(new)* | `php artisan registry:fix-file-indexings` — audits and corrects `registry`, `general_registry`, `physical_registry`. Dry-run by default; `--apply` writes. Same command for local and production. |
| [app/Http/Controllers/Api/RegistryDetectionApiController.php](../../app/Http/Controllers/Api/RegistryDetectionApiController.php) *(new)* | `GET /api/registry/detect?file_number=...` — backs the create-form live auto-detect. |
| [routes/api.php](../../routes/api.php) | Registers the route above. |
| [resources/views/fileindexing/addons/partials/sections/file_archive_details.blade.php](../../resources/views/fileindexing/addons/partials/sections/file_archive_details.blade.php) | `Registry` field stays read-only/auto-filled; users with `assign_role` in `super admin/administrator/admin/editor` get a lock-icon toggle to override it manually. |
| [public/js/fileindexing/create-indexing-dialog.js](../../public/js/fileindexing/create-indexing-dialog.js) | Registry field now calls `/api/registry/detect` on every file-number change (both the happy path and the "no grouping match" path) instead of the old land-use guess. Admin override resets on every dialog reset. |
| [tests/Unit/Services/RegistryDetectorTest.php](../../tests/Unit/Services/RegistryDetectorTest.php) *(new)* | 33 cases: every registry rule + year boundary from the spec, plus edge cases (empty, unparseable, other registry families, longest-prefix-match). All passing. |

## Correction logic

For every row, resolve a candidate file number (`file_number` → `temp_file_no` → `mls_file_no`, first non-empty), run it through `RegistryDetector`:

- **No match** (unparseable, empty, or a different registry family — ST/SLTR/DCIV/KANGIS/Survey) → **skipped**, not touched. This tool only knows the Lands/SIT rules.
- **Match found** →
  - `registry`: always overwritten to the detected number (1/2/3), whatever it currently holds.
  - `general_registry`: always overwritten to the canonical label (`Lands Registry` / `SIT Registry`) via the existing `FileIndexing::detectRegistryFromFileNumber()` — this field is just as deterministic from the file number as `registry` is, so spelling drift and wrong categories get normalized too.
  - `physical_registry`: only filled in when **currently empty** (`"Registry {N} - Land"` or `"SIT Registry"`, matching the `physical_registries` catalog exactly). Existing values are never overwritten — it can legitimately carry sub-classification (`"Registry 1 - Deed"` vs `"Registry 1 - Land"`) that the file number alone can't disambiguate.

Every run writes a CSV to `storage/app/registry-audit/{dry-run|applied}-{timestamp}.csv` (id, file_number, field, old_value, new_value) — the audit trail / rollback reference.

## Local results (three passes, all applied)

| Pass | registry corrected | general_registry corrected | physical_registry filled |
|---|---|---|---|
| 1 — initial `registry` fix | 26,308 | – | – |
| 2 — backfill blank `general_registry`/`physical_registry` | 0 | 51,123 | 37,206 |
| 3 — normalize non-blank `general_registry` drift | 0 | 2,834 | 0 |

**Total rows scanned:** 125,467. **Left untouched (correctly, out of this tool's scope):** 8,712 unparseable (no 4-digit year), 2,479 belong to other registry families.

Final dry-run after all three passes: **0 remaining mismatches** (idempotent). Final `general_registry` distribution for the Lands/SIT family: `Lands Registry` (113,620), `SIT Registry` (658), 2 rows with no file number to classify from.

## Production readiness

- [x] Same command works on prod — no environment-specific code.
- [x] Defaults to dry-run; requires explicit `--apply` to write.
- [x] Idempotent — safe to re-run; a second run reports 0 corrections.
- [x] Every write is logged to CSV (old value + new value) before/as it happens, for audit and manual rollback if needed.
- [x] Strictly scoped — never touches rows outside the Lands/SIT family or rows it can't confidently classify.
- [x] Unit tests cover the detection rules (33 passing).
- [ ] **Not yet run against production** — run when ready:
  ```
  php artisan registry:fix-file-indexings              # dry-run first, review the report + CSV
  php artisan registry:fix-file-indexings --apply       # then write
  ```
  Recommend taking a DB snapshot/backup immediately before the `--apply` run on production, given the row counts involved (tens of thousands of updates).

## Not done

- `physical_registry` sub-classification (Deed vs Land vs Cadastral) for genuinely ambiguous existing values was left alone by design — no reliable signal exists in the file number to resolve it automatically.
- The two rows with blank `general_registry` and no file number on any of the three lookup columns cannot be auto-classified; flagged for manual review if needed.
