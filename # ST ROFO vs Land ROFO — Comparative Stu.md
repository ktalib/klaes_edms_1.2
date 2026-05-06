# ST ROFO vs Land ROFO — Comparative Study

## Overview

Both are 2-page government Right of Occupancy (ROFO) documents consisting of **Terms of Offer** (Page 1) and **Acceptance Letter** (Page 2). They serve different workflows within the KLAES system:

- **ST ROFO** — Sectional Titling: batch prints all units in a mother application
- **Land ROFO** — Standalone Land applications: prints a single document per recommendation

---

## Key Files

| | ST ROFO | Land ROFO |
|---|---------|-----------|
| **Route** | `routes/apps.php` line 217 | `routes/app3.php` line 644 |
| **Route pattern** | `view_rofo/{id}/print` | `land-rofos/{id}/print` |
| **Route name** | `programmes.print_rofo` | `land-rofos.print` |
| **Controller** | `app/Http/Controllers/RofoController.php` → `printRofO()` (lines 1439–1610) | `app/Http/Controllers/LandRofoController.php` → `print()` (lines 163–185) |
| **View** | `resources/views/programmes/rofo_content.blade.php` (~1100 lines) | `resources/views/land_rofos/templates/rofo_print.blade.php` (~840 lines) |

---

## Data Architecture

| Aspect | ST ROFO | Land ROFO |
|--------|---------|-----------|
| Data source | `subapplications` + `rofo` + `landAdministration` tables (raw DB queries) | `LandRecommendation` Eloquent model |
| Batch printing | **Yes** — fetches ALL sub-apps for the mother application, builds `$batchRofos` array (each unit × each watermark) | **No** — single document |
| View receives | `$batchRofos` array, `$currentWatermark`, `$isPrintRoute`, `$autoPrint` | `$recommendation`, `$securityCode` |
| Additional queries in view | Fetches `shared_utilities` from `joint_site_inspection_reports` per unit | None |

---

## Fonts & Typography

| Aspect | ST ROFO | Land ROFO |
|--------|---------|-----------|
| Primary font | `'Times New Roman', serif` | `"Times New Roman", Times, serif` |
| Body font-size | 13px (screen), 12px (print) | 20pt (body), 14.5px (inner content) |
| Line-height | 1.35 | 3 (body), 1.35 (inner content) |
| Security microtext font | N/A | `'Arial Narrow', 'Helvetica Condensed', 'Courier New', monospace` (3px) |
| Security code font | `'Courier New', monospace` (13px) | `'Courier New', monospace` (13px) |

---

## Page 1 — Terms of Offer

| Aspect | ST ROFO | Land ROFO |
|--------|---------|-----------|
| Header banner color | **Blue** (`bg-blue-500`) | **Red** (`#b91c1c`) |
| Coat of Arms | Local asset (`assets/logo/Nigerian-Coat-of-Arms.png`) | External Wikipedia SVG |
| Ornate border | CSS `::before` pseudo-element on `.st-rofo-green-frame` | CSS `border-image` on `.ornate-border` class |
| Background image | `rofo_first_page_bg.jpg` local asset | None (uses SVG security wavy-line pattern) |
| Address section | Left: Ministry address box. Right top: ROFO NO + Land Use | Two-column ref-grid: Left bordered "To:" + address. Right bordered: R of O No, Plot/Plan No, Location, Date |
| Recipient box | Separate box: TO (name), ADDRESS | Inline in left bordered section with dotted underlines |
| Property details | Unit No/Scheme No, Section No (floor), Location | R of O No, Plot/Plan No, Location, Date of Issue |
| Conditions count | **9 conditions** (ST-specific: items 7–8) | **8 conditions** (Land-specific: items 7–8) |
| ST-only conditions | #7: Joint owner of common property of sectional title land; #8: Shared utilities (from JSIR) | N/A |
| Land-only conditions | N/A | #7: Petrol station 33.5% annual rental; #8: Offer lapses if copies not returned |
| Fee fields | Ground Rate, Development Charges, Survey/Processing fees (from `rofo` table) | Ground Rent, Development Charges, Survey/Processing fees (from `LandRecommendation`) |
| Signature section | Grid with Commissioner + Date + QR code inline (28×28px) | Security microtext line via `::after` pseudo-element |
| QR Code placement | Inline with date in signature grid (28×28px) | Footer area (40×40px) |
| Security background | None (uses background image) | SVG wavy line pattern at 0.04 opacity |
| Barcode | None | Code128 barcode (hidden/nearly invisible) |

---

## Page 2 — Acceptance Letter

| Aspect | ST ROFO | Land ROFO |
|--------|---------|-----------|
| Addressee | "The Permanent Secretary" | "The Honourable Commissioner" |
| Body text | Short acceptance + fee reference | Longer: acceptance + building plan submission + completion certificate |
| Fee table structure | Residential/Commercial categories with **hardcoded** amounts (₦20K, ₦50K, etc.) | **Dynamic** — 8 categories (Agriculture, Residential×4 densities, Commercial, Industrial) matched against `rofo_land_use_category` |
| Fee data source | Hardcoded fee amounts | Dynamic from `rofo_land_use_category`, `rofo_survey_fees`, `rofo_dev_charge` |
| Survey options | Visual checkboxes (always shown, not data-driven) | **Conditional** text based on `rofo_director_survey` / `rofo_licensed_surveyor` fields |
| Note text | "APPLICANT TO RETAIN ORIGINAL AND RETURN 2 COPIES AFTER SIGNING" | Same + "This R of O is subject to verification before any statutory payments to Revenue Department" |
| Note layout | Split: 70% note text / 30% signature+date | Simple bordered note box + separate signature row |

---

## Watermark & Print Management

| Aspect | ST ROFO | Land ROFO |
|--------|---------|-----------|
| Watermark logic | **Controller-driven**: builds `$watermarkSequence`, creates one batch entry per unit × watermark | **View-driven**: `$printVersions` array from query params, loops in Blade |
| Default behavior | Always prints all 3 (Original/Duplicate/Triplicate) for every unit | Single version unless `?status=Batch` |
| Watermark colors | Red (#b91c1c) / Blue (#2563eb) / Green (#16a34a) | Red (#ff0000) / Blue (#0000ff) / Green (#008000) |
| Print limit | No explicit limit (sets counter to max 3) | **Max 2** original prints (403 if exceeded) |
| Post-print logging | None | JS `afterprint` event POSTs to `land-rofos.log-print`, reloads opener window |
| CTC watermark | Entry-level watermark text in per-watermark color | Large 45° rotated semi-transparent red watermark overlay |
| Query params | `?isCTC=1`, `?watermark=`, `?status=` | `?status=Original|Duplicate|Triplicate|Batch|CTC`, `?isCTC=1` |

---

## CSS & Technical

| Aspect | ST ROFO | Land ROFO |
|--------|---------|-----------|
| CSS framework | **Tailwind CDN** + extensive inline styles | **Pure CSS** (no framework) |
| Page dimensions | A4 (210mm × 297mm) | A4 (210mm × 297mm) |
| Print media | Detailed `@media print` with `!important` overrides | Cleaner `@media print` block |
| Auto-print | `waitForAssets()` → `window.print()` after 200ms | `setTimeout(window.print, 1000)` |
| Print button | Removed (commented out) | Fixed top-right green button |
| Partials/includes | None — fully self-contained | None — fully self-contained |

---

## Security Features

| Aspect | ST ROFO | Land ROFO |
|--------|---------|-----------|
| Security code | Per-unit via `SecurityCodeService` (doc type: `"ST ROFO"`) | Per-document via `SecurityCodeService` (doc type: `"Lands ROFO"`) |
| Security code display | Top-right: alphabet over digits with vertical divider | Same format, top-right |
| Signature security | None | Microtext pseudo-element repeating "Kano State Ministry..." at 3px font |
| QR code data | File number or `tracking_id` from `file_indexings` table | `tracking_id` from recommendation |
| CTC watermark | Entry-level watermark text in color | Large 45° rotated semi-transparent red text |

---

## Notable Observations

1. **ST ROFO is batch-oriented** — one print job outputs all units in a mother application × all 3 watermark copies. Land ROFO is single-document.
2. **Land ROFO has stronger print controls** — explicit max 2 limit with 403 abort, post-print logging via JS; ST ROFO just sets counter to max without enforcement.
3. **Land ROFO fee table is dynamic** — matches `rofo_land_use_category` against categories and populates actual fees; ST ROFO fee table has hardcoded amounts.
4. **ST ROFO has 9 conditions** (2 extra for sectional title joint ownership & shared utilities); Land ROFO has 8 conditions (2 different for petrol stations and offer expiry).
5. **Both use `SecurityCodeService`** with the same display format (alphabet over digits) but different document type strings.
6. **Land ROFO has a security microtext signature line** that ST ROFO lacks.
7. **Both are fully self-contained** — no `@include` or partials, all inline HTML/CSS/JS.
8. **Both use Times New Roman** as the primary document font — standard for Nigerian government documents.