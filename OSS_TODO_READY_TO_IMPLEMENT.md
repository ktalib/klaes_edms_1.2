# OSS OP — Items Ready to Implement
**Date:** 17 March 2026  
**Status:** All items below are fully understood. Implementation can begin immediately.

---

## Item 1 · Add Reg Time & Date to All the OP Capture Cards (Land & OSS)

**What needs to be done:**  
The Instrument Capture modal (`#registration-dialog`, `register_modal.blade.php`) currently has **no Registration Time or Registration Date fields below the registration details**. Add both fields so that when an OP is captured, the date and time of registration are recorded.

This modal is shared between Land and OSS — one change covers both.

**Files to change:**  
- `resources/views/instruments/partials/register_modal.blade.php` — add `reg_time` + `reg_date` inputs  
- The controller that handles the instrument capture save — persist those two values

---

## Item 4 · Transactions at OP Registration Should Inherit Mother OP Reg Particulars → Transfer of Title + Backfill

**What needs to be done:**  
When a new Change of Name is saved via FEFR (`saveFfrChangeOfName` / `captureFfrExisting`), the new PRA transaction row must **inherit the Registration Number, Registration Date, and Registration Time and othe property data , eg, plot number, tp number etc** from the **original / mother OP** record for that file. 

 

---

## Item 5 · Map Customer Type for Transfer of Title Records in OSS Table

**What needs to be done:**  
When a Transfer of Title entry is created via FEFR and saved to `oss_applications`, the `customer_type` field is not being populated. Ensure it is correctly derived from the new party / applicant data (submitted in the FEFR form) and persisted.

**Files to change:**  
- `app/Http/Controllers/LandsOneStopShop/ApplicationController.php` (`saveFfrChangeOfName`)

---

## Item 6 · Rename "Fetch File Record" Button to "Fetch Existing File Record" (FEFR)

**What needs to be done:**  
The orange `#btn-ffr` button label currently reads **"Fetch File Record"**. Change it to **"Fetch Existing File Record"**.

**Files to change:**  
- `resources/views/lands_one_stop_shop/applications.blade.php` (button around line 194–197)

---

## Item 9 · Default OSS Table Rows to 25

**What needs to be done:**  
1. Change the controller default from `50` → `25`  
2. Make `25` the pre-selected option in the `#op-length` dropdown

**Files to change:**  
- `app/Http/Controllers/LandsOneStopShop/OpResettlementApplicationController.php` (line 18: `'limit', 50` → `'limit', 25`)  
- `resources/views/lands_one_stop_shop/applications.blade.php` (`#op-length` `<select>` — change `@selected($limit == $option)` logic so 25 is default)

---

## Item 11 · Expand Print Manager + Remove "Delete Entry" Above It
dont not remove the "Delete Entry , (the Print Manager should be above"Delete Entry" )
**What needs to be done:**  
The current "Printer Manager" row action only opens a Commissioning Sheet. Expand it into a multi-document print menu:
1. Verification print  
2. Acknowledgement print  
3. Recommendation print  
4. Commissioning Sheet (keep existing)   create partial file for this cus the print manager is global, so let ujuse include cus aother module will it too in the future 

Also: **remove the "Delete Entry" action** from the row actions dropdown (the note says to drop it if it sits above the Printer Manager).

**Files to change:**  
- `resources/views/lands_one_stop_shop/applications.blade.php` (row action dropdown HTML + `openCommissioningPrinterManagerForOP` JS function)

---

## Item 12 · Fix "Edit Record" CSS in OSS Table

**What needs to be done:**  
 the edit card , you wont be able to scroll down, and the 
 purpose drop is not wrking 

**Files to change:**  
- `resources/views/lands_one_stop_shop/applications.blade.php` (action dropdown HTML / CSS classes)

---

## Item 13 · Fix Temp FileNo Hyperlink in OSS Table (HOLD ON)

**What needs to be done:**  
Verify and fix that the purple `.js-op-temp-file-link` button in the MLS File No column:
- Renders correctly  
- Fires `showTempFileDetailsByPropId()` on click  
- Has all required `data-` attributes populated (`data-prop-id`, `data-source-capture-id`, `data-source-pra-id`, `data-temp-fileno`, `data-mls-fileno`, `data-new-party-name`)

**Files to change:**  
- `resources/views/lands_one_stop_shop/applications.blade.php` (row template temp_fileno button)

---

## Item 14 · Investigate & Fix Incomplete Transaction for CRES-2016-1490 (HOLD ON)

**What needs to be done:**  
1. Look up file number **RES-2016-1470** in `oss_applications` and `mls_file_no_pra`  
2. Identify what is missing or inconsistent (missing PRA entry, blank fields, partial FEFR save)  
3. Fix the data directly and/or patch the save logic if a systematic bug is found

---

## Item 16 · Add "Total Files Commissioned from file number generator and the totol number of oss records" Card to OSS Dashboard

**What needs to be done:**  
Add a **5th summary card** to the OSS page showing the total count of commissioned files (i.e., `oss_applications` records with a file number assigned, or total count of all records).

**Files to change:**  
- `resources/views/lands_one_stop_shop/applications.blade.php` (summary cards section — add 5th card)  
- `app/Http/Controllers/LandsOneStopShop/OpResettlementApplicationController.php` (pass the new count to the view)

---

## Implementation Order (suggested)

| Priority | Item | Complexity |
|----------|------|------------|
| 1 | Item 6 — Rename FEFR button | Trivial (1 line) |
| 2 | Item 9 — Default rows to 25 | Trivial (2 lines) |
| 3 | Item 12 — Fix Edit Record CSS | Low |
| 4 | Item 13 — Fix Temp FileNo hyperlink | Low |
| 5 | Item 16 — Add Commissioned card | Low |
| 6 | Item 11 — Expand Print Manager | Medium |
| 7 | Item 5 — Map Customer Type (Transfer of Title) | Medium |
| 8 | Item 1 — Add Reg Time/Date to OP Capture modal | Medium |
| 9 | Item 14 — Investigate CRES-2016-1490 | Medium (data task) |
| 10 | Item 4 — Inherit Mother OP reg particulars + backfill | High |
