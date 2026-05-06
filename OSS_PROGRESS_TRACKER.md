# OSS OP — Implementation Progress Tracker
**Last updated:** 18 March 2026

---

## ✅ COMPLETED

| # | Item | Notes |
|---|------|-------|
| 2 | Change "Direct Recommendation" label to "Direct" | User handled this themselves |
| 6 | Rename "Fetch File Record" → "Fetch Existing File Record" | Done — `applications.blade.php` button label |
| 7 | Exclude CON-prefix files from FEFR file number selector | Done — `global-fileno-modal.js` `excludePrefixes` support; `ffrPickSourceFile()` passes `['CON']` |
| 8 | Adhere to OP Recommendation template changes | Already done in a previous session |
| 9 | Default OSS table rows to 25 | Done — controller default changed 50→25; dropdown pre-selects 25 |
| 10 | Map TP No / Plot No / LGA for Transfer of Title rows | Done — `saveFfrChangeOfName` now extracts these from the mother OP PRA record and writes to `fileNumber` table |
| 11 | Expand Print Manager (Verification, Acknowledgement, Recommendation, Commissioning) | Done — `print-manager-modal.blade.php` partial created; included in main view; row action wired |
| 12 | Fix "Edit Record" CSS (scroll + purpose dropdown) | Done — modal flex/scroll fixed; purpose field is now a dynamic `<select>` loaded from API |
| 15 | OP Details popup — side-by-side 3-column layout for 3 transactions | Done — `showTempFileDetailsByPropId()` uses `grid-cols-3` / `grid-cols-2` / single-col depending on count; popup width scales accordingly |
| 16 | Add "Total Commissioned" + "OSS Records" summary cards | Done — 2 new cards added; controller passes counts; grid expanded to 6 columns |
| 17 | Export CSV and Print/PDF from OSS toolbar | Done — **CSV** and **Print/PDF** buttons added to toolbar; `exportOssTableToCsv()` and `printOssTable()` JS functions implemented |
| 1 | Add Reg Time & Date to OP Capture Cards | Done — Reg Date/Reg Time fields added under OP Registration Details; JS payloads now include `reg_date`/`reg_time`; service persists `reg_date` and conditionally persists `reg_time` when schema supports it |
| 5 | Map Customer Type for Transfer of Title Records | Done — `saveFfrChangeOfName` now derives `customer_type` from Party 2 and persists it to `fileNumber`, `file_indexings`, `file_indexing_links`, `customers_staging`, and `mls_file_no` (when columns exist) |

---

## ⏳ PENDING / INCOMPLETE

### Item 4 · Inherit Mother OP Reg Particulars → Transfer of Title + Backfill
**What:**
1. In `saveFfrChangeOfName` / `captureFfrExisting` — copy `reg_no`, `reg_date`, `reg_time` (and other property data already done: plot_no, tp_no, lga) from the mother OP PRA record into the new PRA row.
2. Write a backfill Artisan command to fix all existing FEFR-generated PRA records that are missing these inherited values.

**Files:** `ApplicationController.php` (`saveFfrChangeOfName`, `captureFfrExisting`), `routes/console.php`, new Artisan command class.  
**Status:** Reg No/Date/Time + property fallback inheritance implemented in `saveFfrChangeOfName` and `captureFfrExisting`; **backfill Artisan command still pending**.

---

### Item 3 · Add Recommendation to OSS Sub Module (**HOLD ON**)
**What:** Needs clarification on what "OSS Sub Module" refers to — no page by that name was found.  
**Status:** On hold — awaiting user clarification.

---

### Item 13 · Fix Temp FileNo Hyperlink in OSS Table (**HOLD ON**)
**What:** Verify and fix the purple `.js-op-temp-file-link` button so it renders correctly, fires `showTempFileDetailsByPropId()`, and has all `data-` attributes populated.  
**Files:** `applications.blade.php` (row template).  
**Status:** On hold per user instruction.

---

### Item 14 · Investigate & Fix Incomplete Transaction — CRES-2016-1490 (**HOLD ON**)
**What:** Look up file CRES-2016-1490, identify what's missing/inconsistent in `oss_applications` / `mls_file_no_pra`, and fix the data or the save logic.  
**Status:** On hold per user instruction.

---

## Summary

| Status | Count | Items |
|--------|-------|-------|
| ✅ Complete | 13 | 1, 2, 5, 6, 7, 8, 9, 10, 11, 12, 15, 16, 17 |
| ⏳ To do (active) | 1 | 4 |
| ⏸ On hold | 3 | 3, 13, 14 |
| **Total** | **17** | |
