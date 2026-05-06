# Print File Label Registries: How It Works

## 1. Purpose
This document explains how registries work in the Print File Label module, including:
- Lands vs non-Lands behavior
- Where registry options come from
- How records are fetched and assigned to rack/shelf labels
- How rack type (`default`, `28`, `42`) affects rollover

## 2. Registry Source of Truth
- Active registries come from the `registries` table.
- Backend endpoint: `PrintLabelController::getPrintableRegistries()`.
- Query pattern follows project rule: use active rows, do not hardcode production lists.

Related code:
- `app/Http/Controllers/PrintLabelController.php`
- `resources/views/printlabel/assets/js.blade.php`
- `resources/views/printlabel/partials/page.blade.php`

## 3. Registry Selection in UI
- `globalRegistrySelect` controls the main mode:
  - `LANDS` (default)
  - Other active registries (e.g., DCIV, DEEDS, MORTGAGE, etc.)
- For Lands mode, `registrySelect` (Registry 1/2/3) is used for grouping filters.
- For non-Lands mode, the Lands batch controls are hidden and records are fetched from `file_indexings` using `general_registry`.

## 4. Lands vs Non-Lands Data Path
## 4.1 Lands (`LANDS`)
- Batch preview reads from `grouping`.
- Uses `registry_batch_no` (and optional year/registry filters).
- Supports excluding already assigned rows.
- Rack type selector is visible in this mode.

## 4.2 Non-Lands (DCIV/DEEDS/MORTGAGE/etc.)
- Record loading and preview use `file_indexings`.
- Filter uses normalized `general_registry` value.
- Empty batch input can still fetch unassigned candidates in supported path.
- Rack type is reset/treated as default unless explicitly needed by flow.

## 5. Rack/Shelf Label Rules (Current Implementation)
- Every shelf label always has capacity `1..100` files.
- Rack type controls only the maximum shelf number per rack letter:
  - `default`: max shelf `48` per rack letter
  - `28`: max shelf `28` per rack letter
  - `42`: max shelf `42` per rack letter
- Rollover examples:
  - `28` mode: `A28 -> B1`
  - `42` mode: `A42 -> B1`
- Capacity does not change by rack type; it stays `100`.

## 6. Batch Creation and Assignment
- Main endpoint: `PrintLabelController::createBatch()`.
- For grouping source:
  - Validates registry batch and selected rack/shelf.
  - Claims/creates rack label rows in `Rack_Shelf_Labels`.
  - Assigns records to shelf labels and persists to `print_label_batch_items`.
  - Updates `file_indexings.shelf_location` and `file_indexings.shelf_label_id` when available.
- Shelf validation now blocks invalid shelf numbers for selected rack type (for example shelf `29` in `28` mode).

## 7. Rack Label Status and Progress
- Endpoint: `getRackLabelStatus`.
- Returned status includes:
  - `counter`, `capacity`, `remaining`, `is_full`
  - assignment metadata
  - registry progress details
- Registry progress uses fixed shelf capacity `100` and exposes next range key as `1-100`.

## 8. Shelf Label Mode (Direct Label Printing)
- Shelf Label Mode prints label sequences without record fetch.
- Now respects rack type rollover when generating sequence:
  - In `28` mode it will not continue to `A29`; it moves to `B1`.
  - In `42` mode it will not continue to `A43`; it moves to `B1`.

## 9. Operational Notes
- Keep routes for print label APIs above wildcard routes.
- Use SQL Server (`sqlsrv`) models/tables for this module.
- If cache behavior appears stale during preview, use cache-bust-aware paths in current UI flow.

## 10. Quick Verification Checklist
1. Select `LANDS`, set rack type `28`, start at `A28`, create sequential labels, confirm next is `B1`.
2. Select `LANDS`, set rack type `42`, start at `A42`, confirm next is `B1`.
3. Confirm rack label counter always displays `/ 100`.
4. Try invalid shelf for mode (e.g., shelf `40` in `28` mode) and confirm validation error.
5. Switch to non-Lands registry and confirm record source/filter changes from grouping path to `file_indexings`.
