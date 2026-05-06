# Page Typing Feature Report

Source reviewed: `resources/views/pagetyping/index.blade.php`, `app/Http/Controllers/PageTypingController.php`, `app/Services/EdmsSchemaChecker.php`, `database/sql/06_add_booklet_fields_to_pagetypings.sql`, `app/Models/PageTyping.php`

**Summary**
The Page Typing dashboard is a single-page Blade view that delivers a full client-side workflow for file page classification. It includes status dashboards, multiple list tabs with search and registry filtering, and a rich typing UI that supports single-page processing, batch operations, page replacement/deletion, and PDF thumbnailing. It also includes a dedicated "PageType More" mode for files with newly added scans. Booklet Management is embedded inside the typing flow and adds serial numbering rules and UI controls for grouping pages into a shared serial prefix with alphabetic suffixes.

**Recent Fixes (2026-03-07)**
- Booklet serials now use a shared base-26 counter sourced from processed pages: counter is set from `{processedBookletPages}` on navigation, incremented after each save, and respected by Process All and multi-select flows (no more `02a/04a` repeats).
- Multi-select/booklet processing starts suffixes from the current counter and existing processed booklet pages, so batch processing continues at `...c/d/e` instead of resetting to `a`.
- Booklet mode auto-selects `Front Cover (FC)` by default; the Cover dropdown no longer sits on the placeholder and the Next button remains enabled with live serial + Page Code preview updates.
- Page Code preview and serial display are refreshed on Next/prev navigation (folder grid, fullscreen, and Next button) to keep previews in sync with the current serial and cover selection.
- Process All modal includes the per-page page-code preview for the current selections.

**Primary UI Areas**
1. Dashboard header and stats cards for pending, in-progress, completed, and PageType More counts.
2. Tabs for `Pending`, `In Progress`, `Completed`, `PageType More`, and `Typing` (plus a disabled "Custom Types" placeholder in the default flow).
3. Typing view that switches from a file list to a page classification workspace once a file is selected.

**Data Sources and API Endpoints**
Client-side data fetching is done via JSON endpoints and blade route helpers:
- Typing reference data: `pagetyping.api.typing-data` (cover types, page types, subtypes).
- File lists: `pagetyping.api.files` with `status=pending|in_progress|completed`.
- PageType More list: `pagetyping.api.pagetype-more-files`.
- File detail: `pagetyping.api.file-details`.
- Page replacement: `pagetyping.api.replace-page`.
- Page deletion: `pagetyping.api.delete-page`.
- Thumbnail lookup/save: `pagetyping.api.thumbnails`, `pagetyping.api.save-thumbnail`.
- Typing save: `pagetyping.save-single`.
- Stats refresh: `pagetyping.api.stats`.
- Folder ordering: `pagetyping.folder.order`.

**Page Typing Workflow**
1. File selection from `Pending` or `In Progress` starts a typing session.
2. The typing state includes current page, selection, serial number, cover/page/subtype, batch mode, and BC+FC/Booklet flags.
3. The UI provides a Quick File Browser that can switch between single selection and multi-select modes.
4. Page classification captures cover type, page type, subtype, serial number, and derived codes.
5. Existing page typings are preloaded and displayed for edit mode, including processed page indicators.
6. Pages can be replaced or deleted, with confirmation prompts and state re-indexing after deletion.
7. PDF rendering uses PDF.js for preview and supports thumbnail generation/storage.

**PageType More Mode**
PageType More is a parallel workflow for files with new scans added (`IsUpdated = 1` data path implied by UI text). It provides:
- A dedicated tab and optional "PageType More" URL mode (`?url=ptmore`).
- File list with existing pages, new scans, total pages, and last updated metadata.
- Actions to view combined file and start "More Pages" typing.

**Booklet Management**
Booklet Management is embedded inside the typing view and controls serial numbering for multi-page documents that should share a base serial number.
- Start Booklet: Requires a selected file; sets `bookletMode = true`, captures the current serial as the base, and starts the alphabetic suffix (`a`).
- End Booklet: Produces a summary of booklet pages, clears booklet state, and increments the main serial for the next non-booklet page.
- Serial input becomes read-only and displays `base + letter` (e.g., `12a`, `12b`, `12c`).
- Each processed booklet page tracks `booklet_id`, `is_booklet_page`, and `booklet_sequence` and keeps a local booklet summary for confirmation.
- UI includes an active booklet banner with next-serial preview and `Start Booklet`/`End Booklet` actions.

**BC+FC Mode (Related to Booklet Flow)**
The typing UI also includes a Back Cover + File Cover mode that behaves similarly to booklet numbering with alphabetic suffixes. It disables form inputs during the mode and uses a specialized counter (`0a`, `0b`, ...), with explicit start/end controls.

**Backend Serial Number Behavior (Server-Side)**
The API endpoint `pagetyping.api.file-details` returns `next_serial` and `next_serial_formatted`, computed by `PageTypingController::calculateNextSerial()`. The implementation:
- Queries the max `serial_number` for the current file (`file_indexing_id`) only.
- Returns `max + 1` or `1` when none exists.
- Ignores page type, cover type, booklet/BC+FC modes, and any global serial rules.

The save endpoint `pagetyping.save-single`:
- Accepts `serial_number` as provided by the client (string or integer).
- Does not recompute or validate serial uniqueness or pattern server-side.
- Persists booklet fields: `booklet_id`, `is_booklet_page`, `booklet_sequence`.
- Derives `definition`/`display_order` from `page_number`, which can diverge from client-side `definition` in the request.

**Schema and Model Reality**
- `pagetypings.serial_number` is defined as `INT NOT NULL` in `app/Services/EdmsSchemaChecker.php`.
- The booklet SQL patch only adds booklet fields and does not change `serial_number` to a string type.
- `PageTyping` does not cast `serial_number`, but the DB type will still reject alphanumeric values (e.g., `12a`).
- This means booklet/BC+FC suffixes cannot be stored in `serial_number` unless the schema is changed or the suffix is stored in a separate column.

**Serial Number Risks and Likely Failure Points**
- Conflicting sources: a PHP block in the view computes a global next serial, but `getFileDetails()` uses per-file max + 1, and the JS runtime uses its own `calculateNextSerialNumber()`; these can disagree.
- In multiple client paths, serial numbers are overwritten with page index values (e.g., `currentIdx.toString()`), bypassing the calculated serial and booklet rules; this can silently change a computed serial.
- The serial input sanitization strips letters and enforces two digits, which can truncate valid serials and conflict with booklet/BC+FC suffixes when users edit it.
- Letter suffix selection uses `letters.length` rather than the highest existing suffix, which can repeat existing letters when suffixes are not contiguous.
- Serial allocation is fully client-side; `save-single` does not validate uniqueness or sequence, so concurrent typists can generate duplicates.
- Server uses numeric `max(serial_number)` and an INT column, which breaks alphanumeric serials like `12a` and makes booklet serials effectively lossy.

**Serial Number Improvements**
- Centralize serial allocation server-side with explicit rules and return the canonical serial from `pagetyping.api.file-details` and/or `pagetyping.save-single`.
- Enforce uniqueness and sequence at save time, and return a corrected serial if a collision is detected.
- Stop overwriting serials with page index values in client flows; use a single serial calculation path.
- Accept variable-length serials and preserve suffixes; do not hard-trim to two digits on input.
- When generating suffixes, use the max existing suffix (`a`..`z`) to avoid repeats.
- If alphanumeric serials are required, change `serial_number` to NVARCHAR (or split into `serial_number_int` + `serial_suffix`) and adjust queries accordingly.

**Other Feature Improvements**
- Booklet Management: prevent starting a booklet while BC+FC is active, and persist `currentBooklet`, `booklet_sequence`, and `is_booklet_page` reliably in the backend so summaries and audits are consistent.
- PageType More: align search behavior so the client does not re-filter the server-filtered list; ensure the API returns counts for `existing_pages`, `new_scans`, and `total_pages` consistently.

**Key Files**
- `resources/views/pagetyping/index.blade.php`
- `app/Http/Controllers/PageTypingController.php`
- `app/Services/EdmsSchemaChecker.php`
- `database/sql/06_add_booklet_fields_to_pagetypings.sql`
- `app/Models/PageTyping.php`

**Potential Next Checks (If You Want Deeper Coverage)**
1. Confirm how `serial_number` is typed in the live `pagetypings` table and whether alphanumeric serials are permitted end-to-end.
2. Validate the expected serial rules with business users (global vs per-file sequences; booklet/BC+FC handling) and codify those rules in the backend.
3. Add audit logging for serial adjustments if the backend reassigns serials on collision.


<!--  Booklet Management, Examlple

Assume:

CoverType code = FC
PageType code = COFO
PageSubType code = ORIG
Then for 5 booklet pages:

Page 1: Serial 19a, Page Code FC-COFO-ORIG-19a
Page 2: Serial 19b, Page Code FC-COFO-ORIG-19b
Page 3: Serial 19c, Page Code FC-COFO-ORIG-19c
Page 4: Serial 19d, Page Code FC-COFO-ORIG-19d
Page 5: Serial 19e, Page Code FC-COFO-ORIG-19e
After you end Booklet Management, the next serial becomes 20 (and will display as 20 or 20 padded to 20/20 based on the UI formatting rules).

If you want, tell me the exact cover type, page type, and subtype you use, and I’ll generate the exact page codes for your case. -->

**Issue: Previous pages re-labeled as booklet when Booklet is toggled mid-run**
- Observed: Pages typed as normal (e.g., serial 0, 01, 02) were re-rendered in the Process All summary as booklet serials (e.g., 3a, 3b, 3c) after enabling Booklet, even though they were not part of the booklet.
- Cause: Process All normalization applied the active booklet suffix to every pending page whenever Booklet mode was on, regardless of the page’s own booklet flag.
- Fix: Normalize serials per page using per-page flags. Only pages marked is_booklet_page/ooklet_id receive booklet suffixes; others keep their stored serials. Save payload now also uses per-page booklet/BC+FC flags instead of the global mode state.
- Status: Implemented in esources/views/pagetyping/index.blade.php (Process All summary + save payload now respect per-page booklet/BC+FC flags).

