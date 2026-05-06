# OSS OP Change of Name — Todo List Analysis
**Date:** 17 March 2026  
**Source:** Handwritten notes (2 pages, DSS Update 17/3/21)  
**Scope:** OSS (One Stop Shop) OP (Occupancy Permit) Change of Name module  

---

## Legend
- ✅ **CLEAR** — I understand what needs to be done; ready to implement.  
- ⚠️ **NEEDS CLARIFICATION** — I have a reasonable guess but need your confirmation before coding.  
- ❓ **UNCLEAR** — I do not fully understand the requirement; please explain.

---

## Page 2 — Items 8 – 17

---

### Item 8 · Adhere to the changes on the OSS Recommendation Template
**Status:** ⚠️ NEEDS CLARIFICATION

**What I understand:**  
The `print_recommendation.blade.php` print template was just updated in our last session (serial number top-right in red, Commissioner/Date sig lines starting at 50%, labels un-bolded, location not uppercase). "Adhere to the changes" probably means the **recommendation modal** (`recommendation-modal.blade.php`) should visually align with or respect those same updates.

**What I need confirmed:**  
> Which specific changes from the print template should be reflected inside the recommendation modal itself? For example:
> - Should field labels inside the modal also be un-bolded?
> - Should the modal display the ROFO serial number?
> - Or is this about something different entirely — e.g., the modal's section order or field set should match the printed form?

---

### Item 9 · Make the No. of Records on the OSS Table be 25 Rows by Default
**Status:** ✅ CLEAR

**What I understand:**  
The main OSS table (`#op-resettlement-table`) loads records controlled by the `$limit` variable. The controller (`OpResettlementApplicationController`) currently defaults to **50** rows (`$request->input('limit', 50)`). The row-count selector UI (`#op-length`) has options: 25, 50, 100, 150, 200.

**What needs to be done:**  
1. Change the controller default from `50` → `25`  
2. Make `25` pre-selected in the `#op-length` dropdown  

**Files to change:**  
- `app/Http/Controllers/LandsOneStopShop/OpResettlementApplicationController.php` (line 18)  
- `resources/views/lands_one_stop_shop/applications.blade.php` (the `#op-length` `<select>`)

---

### Item 10 · Map TP No, Plot No & LGA for Transfer of Title Records in the OSS Table
**Status:** ⚠️ NEEDS CLARIFICATION

**What I understand:**  
In the OSS main table, the TP No, Plot No, and LGA columns display values from `oss_applications.tp_no`, `oss_applications.plot_no`, and `oss_applications.lga`. For records created through the **FEFR + Capture Existing** flow (Transfer of Title source), these fields may be coming in blank or unpopulated because the FEFR save logic (`saveFfrChangeOfName` / `captureFfrExisting`) might not copy those values into `oss_applications`.

**What needs to be done (my best guess):**  
Ensure that when a Transfer of Title OP record is saved via FEFR, `tp_no`, `plot_no`, and `lga` from the source PRA record are written into `oss_applications`.

**What I need confirmed:**  
> Are the TP No/Plot No/LGA blank for Transfer of Title rows right now? If yes, should we pull them from the PRA history for that file, or from the instrument_capture record, or from somewhere else?

---

### Item 11 · Update the Print Manager to Accommodate Verification, Acknowledgement & Recommendation — and Drop / Remove "Delete Record" Above It
**Status:** ✅ CLEAR

**What I understand:**  
The current "Printer Manager" row action (`openCommissioningPrinterManagerForOP`) only generates and prints a **Commissioning Sheet**. The requirement is to expand it into a proper multi-document print menu covering:
1. Verification print  
2. Acknowledgement print  
3. Recommendation print  
4. Commissioning Sheet (existing)  

The note "drop if above delete record" means the **Delete Entry** option currently sits above the Printer Manager in the dropdown — remove it or move it so it doesn't interfere.

**Files likely affected:**  
- `resources/views/lands_one_stop_shop/applications.blade.php` (the row action dropdown + `openCommissioningPrinterManagerForOP` JS function)

---

### Item 12 · Check the "Edit Record" CSS Under the OSS Table
**Status:** ✅ CLEAR (investigate)

**What I understand:**  
The "Edit Record" option in the row actions dropdown (`openOpEditModal`) may have a CSS styling problem — wrong colour, broken layout, missing icon, or misaligned text. Need to open the page, trigger the dropdown, visually inspect the element, and fix any broken styles.

**Files likely affected:**  
- `resources/views/lands_one_stop_shop/applications.blade.php` (action dropdown HTML / CSS classes)

---

### Item 13 · Check the Temp FileNo Hyperlink on the OSS Table
**Status:** ✅ CLEAR (investigate)

**What I understand:**  
In the MLS File No column, when a record has a `temp_fileno`, a purple underlined clickable button (`.js-op-temp-file-link`) is shown. The handwriting "hygm/hule" most likely means **hyperlink**. Need to verify:
- Does the link render and display correctly?
- Does clicking it fire `showTempFileDetailsByPropId()` correctly?
- Are the required `data-` attributes (`data-prop-id`, `data-source-capture-id`, etc.) actually populated on the element?

**Files likely affected:**  
- `resources/views/lands_one_stop_shop/applications.blade.php` (row template around the temp_fileno button)

---

### Item 14 · Check the Incomplete Transaction Under the First Record (CRES-2016-1490)
**Status:** ✅ CLEAR (data/debug task)

**What I understand:**  
The record with file number **CRES-2016-1490** (likely the top row in the table) has an incomplete or broken transaction — possibly a missing PRA entry, blank TP/Plot/LGA, or a FEFR save that partially completed. Need to:
1. Look up this file number in `oss_applications` and `mls_file_no_pra`  
2. Find what is missing or inconsistent  
3. Fix the data and/or identify if the save logic has a bug  

---

### Item 15 · The OP Details Popup Should Display All 3 Transactions Under the Same Line
**Status:** ⚠️ NEEDS CLARIFICATION

**What I understand:**  
The `showTempFileDetailsByPropId()` function opens a SweetAlert2 popup showing each PRA transaction as a **separate stacked card** (one card per transaction, vertically stacked). "Under the same line" suggests the 3 transactions should appear **side-by-side** (horizontal/columnar layout) rather than stacked one below the other.

**What I need confirmed:**  
> By "same line" do you mean:
> - (a) Show all 3 transaction cards in a single horizontal row (3 columns side by side)?
> - (b) Collapse each transaction into a single compact row in a table?
> - (c) Something else?

---

### Item 16 (Side Note) · Add Total No. of Files Commissioned to the OSS Dashboard
**Status:** ✅ CLEAR

**What I understand:**  
The OSS page currently has 4 summary cards (Residential, Commercial, Industrial, Agriculture). Need to add a **5th card** showing the total number of files that have been commissioned (i.e., the total count of all `oss_applications` records, or specifically those with a file number assigned).

**Files likely affected:**  
- `resources/views/lands_one_stop_shop/applications.blade.php` (summary cards section)  
- `app/Http/Controllers/LandsOneStopShop/OpResettlementApplicationController.php` (pass new count to view)

---

### Item 17 (Side Note) · Export for OSS & Land FileCommully
**Status:** ❓ UNCLEAR

**What I understand:**  
"FileCommully" seems to be short for "File Commissioning". This appears to request an **export feature** (likely CSV or Excel) for commissioned file records from the OSS module and possibly the broader Land module.

**What I need confirmed:**  
> - What data should be exported? All columns in the OSS table? A specific subset?  
> - What file format — Excel (.xlsx), CSV, or PDF?  
> - Is this an export button on the OSS table page, or a separate export page?  
> - Does "Land & OSS" mean the same export button should also appear on the main Land applications page (separate from OSS)?

---

---

## Page 1 — Items 1 – 7 (DSS Update 17/3/21)

---

### Item 1 · Add Reg Time & Date to All the OP Capture Cards Under Land & OSS
**Status:** ✅ CLEAR

**What I understand:**  
The "OP Capture Card" is the **Instrument Capture modal** (`#registration-dialog`, `register_modal.blade.php`). It currently has **no Registration Time or Registration Date fields**. These need to be added so that when an OP is captured, the date and time of registration are recorded alongside the instrument.

"Under Land & OSS" means this modal is shared — the same change will benefit both the Land module and OSS module without needing two separate modals.

**What needs to be done:**  
1. Add `reg_time` and `reg_date` input fields to `register_modal.blade.php`  
2. Ensure the backend (`InstrumentRegistrationController` or equivalent) saves those values  
3. Ensure the values are included in whatever save/submit payload the modal posts

**Files likely affected:**  
- `resources/views/instruments/partials/register_modal.blade.php`  
- The controller that handles instrument capture save  

---

### Item 2 · Change "Direct Recommendation" to "Direct" Under the Recommendation Card
**Status:** ❓ UNCLEAR

**What I understand:**  
The Recommendation modal (`recommendation-modal.blade.php`) currently has sections:
- Section 1–2: Auto-filled record details  
- Section 3: "Director of Land Recommendation"  
- Section 4: "Permanent Secretary Recommendation"  
- Section 5: "Commissioner Approval" (hidden)  

The text **"Direct Recommendation"** does **not appear anywhere in the codebase** currently. I cannot identify what specific text or label should be changed to "Direct".

**What I need confirmed:**  
> Where exactly is "Direct Recommendation" shown? Is it:
> - A label inside the recommendation modal (e.g., Section 3 or a sub-heading)?
> - The action dropdown item text in the table row (currently just says "Recommendation")?
> - A badge/pill on the printed recommendation template?
> - Something that will be added as part of another item on this list?

---

### Item 3 · Add Recommendation to the OSS Sub Module
**Status:** ❓ UNCLEAR

**What I understand:**  
The main OSS page (`applications.blade.php`) already has a "Recommendation" action per row. However, the note says **"OSS Sub Module"** — I cannot find any view or controller called "OSS Sub Module" in the codebase. There are other OSS pages (`plot_extension.blade.php`, `loss_of_document.blade.php`) but none are named "Sub Module".

**What I need confirmed:**  
> - What is the "OSS Sub Module"? Is it one of these pages: Plot Extension, Loss of Document, or a page that doesn't exist yet?  
> - Should the Recommendation action/modal be added to that specific sub-page?  
> - Or does "Sub Module" refer to a sub-section within the existing applications page (e.g., after a record is drilled into)?

---

### Item 4 · All Transactions at OP Registration Should Take Reg Particulars of the Mother OP → Transfer of Title — Backfill Already-Generated Ones
**Status:** ✅ CLEAR (complex implementation)

**What I understand:**  
When a new Change of Name instrument is registered via FEFR (`saveFfrChangeOfName`), the new PRA transaction record (Transfer of Title or OP) should **inherit the registration particulars** (Registration Number, Registration Date, Registration Time) from the **original / mother OP** record for that file.

Currently, the FEFR save creates a new PRA entry but does not copy the Mother OP's `reg_no`, `reg_date`, `reg_time` into it. The link exists (source file → PRA history → OP transaction), but the values aren't copied forward.

**Backfill** = run a one-time update across all existing FEFR-generated records that are missing these inherited particulars.

**What needs to be done:**  
1. In `saveFfrChangeOfName` / `captureFfrExisting`, after creating the new PRA record, look up the Mother OP's reg particulars and copy them into the new PRA row  
2. Write a migration/artisan command to backfill existing records in `mls_file_no_pra` (or equivalent table) that are missing these values

---

### Item 5 · Map the Customer Type for Transfer of Title Records in the OSS Table
**Status:** ✅ CLEAR

**What I understand:**  
In the OSS main table, Transfer of Title source records may show an empty or incorrect `customer_type` in the Customer Type column. When the FEFR flow saves a Transfer of Title record to `oss_applications`, the `customer_type` field is not being populated from the source OP record or from the new holder's customer profile.

**What needs to be done:**  
Ensure `customer_type` is correctly derived (from the new party / applicant data submitted in the FEFR form) and saved to `oss_applications.customer_type` when a Transfer of Title entry is created.

**Files likely affected:**  
- `app/Http/Controllers/LandsOneStopShop/ApplicationController.php` (`saveFfrChangeOfName`)

---

### Item 6 · Change "Fetch File Record" to "Fetch Existing File Record" (FEFR)
**Status:** ✅ CLEAR

**What I understand:**  
The orange button `#btn-ffr` currently has the label **"Fetch File Record"**. The label must be changed to **"Fetch Existing File Record"** to match the FEFR acronym.

**Files to change:**  
- `resources/views/lands_one_stop_shop/applications.blade.php` (the `#btn-ffr` button, around line 194–197)

---

### Item 7 · Restrict the File No Selection Dropdown to NOT Show Commission Files
**Status:** ⚠️ NEEDS CLARIFICATION

**What I understand:**  
When the user clicks "Select" inside the FEFR modal, it opens the **Global File No Modal** (`#globalFileNoModal`) — a searchable dropdown/list to pick a source file number. The requirement is to filter out **Commission Files** from this list so the user can only select non-commission file numbers.

**What I need confirmed:**  
> What defines a "Commission File" in the database? Is it:
> - (a) Files in `mls_file_no` where `source` = something specific (e.g., `'Commission'`, `'Commissioned'`)?
> - (b) Files that exist in `oss_applications` (already commissioned through OSS)?
> - (c) Files with a specific prefix pattern (e.g., `COM-`, `ST-`)?
> - (d) Files flagged with a status column?

---

## Summary Table

| # | Item | Status | Action Needed |
|---|------|--------|--------------|
| 1 | Add Reg Time & Date to OP Capture Cards | ✅ CLEAR | Implement |
| 2 | Change "Direct Recommendation" to "Direct" | ❓ UNCLEAR | **Clarify where this text appears** |
| 3 | Add Recommendation to OSS Sub Module | ❓ UNCLEAR | **Clarify what "OSS Sub Module" refers to** |
| 4 | Transactions inherit Mother OP reg particulars + backfill | ✅ CLEAR | Implement |
| 5 | Map Customer Type for Transfer of Title rows | ✅ CLEAR | Implement |
| 6 | Rename button to "Fetch Existing File Record" | ✅ CLEAR | Implement (1-line change) |
| 7 | Restrict FEFR file selector — no Commission Files | ⚠️ CLARIFY | **Clarify what makes a file a "Commission File"** |
| 8 | Adhere to OSS Recommendation Template changes | ⚠️ CLARIFY | **Clarify which specific changes to mirror** |
| 9 | Default OSS table rows to 25 | ✅ CLEAR | Implement |
| 10 | Map TP No/Plot No/LGA for Transfer of Title rows | ⚠️ CLARIFY | **Confirm where those values come from** |
| 11 | Expand Print Manager (Verification/Ack/Rec + remove Delete above) | ✅ CLEAR | Implement |
| 12 | Fix "Edit Record" CSS in OSS table | ✅ CLEAR | Investigate & fix |
| 13 | Fix Temp FileNo hyperlink in OSS table | ✅ CLEAR | Investigate & fix |
| 14 | Investigate incomplete transaction CRES-2016-1490 | ✅ CLEAR | Investigate & fix data |
| 15 | OP details popup — all 3 transactions on same line | ⚠️ CLARIFY | **Confirm layout: horizontal row vs table vs other** |
| 16 | Add Total Files Commissioned card to OSS dashboard | ✅ CLEAR | Implement |
| 17 | Export for OSS & Land File Commissioning | ❓ UNCLEAR | **Clarify format, fields, and placement** |

---

*This file was auto-generated for review. Once you confirm the clarification items, implementation will proceed.*
