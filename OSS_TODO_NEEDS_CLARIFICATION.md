# OSS OP — Items Needing Clarification

**Date:** 17 March 2026  
**Action needed:** Please review each item below, answer the questions, and return this file so implementation can proceed.

---

## Item 2 · Change "Direct Recommendation" to "Direct" Under the Recommendation Card
(*I will do this*) 

**Problem:**  
The text **"Direct Recommendation"** does not appear anywhere in the codebase — not in any blade view, controller, or JS file. I cannot identify what label or text should be changed to "Direct". 

**Please answer:**  
Where exactly is "Direct Recommendation" shown? Options:
- (a) Is it a label currently inside the recommendation modal that I may have missed?  
- (b) Is it the row action dropdown item text (which currently just says **"Recommendation"**) — should that become **"Direct"**?  
- (c) Is it a badge or pill on the **printed** recommendation template?  
- (d) Is it text that will be added as part of another change on this list — not existing yet?

---

## Item 3 · Add Recommendation to the OSS Sub Module (**HOLDE ON**)

**Problem:**  
There is no view or page called "OSS Sub Module" in the codebase. The main OSS Applications page already has a "Recommendation" action per row. I don't know which page or section is meant by "Sub Module".

**Please answer:**  
- What is the "OSS Sub Module"? Is it one of these existing pages: **Plot Extension** (`plot_extension.blade.php`), **Loss of Document** (`loss_of_document.blade.php`)?  
- Or is it a page/section that doesn't exist yet and needs to be created?  
- Or does "Sub Module" mean something else entirely in your workflow?

---

## Item 7 · Restrict the File No Selection Dropdown to NOT Show converstin  Files
the file numbers with CON, in the global file number selectorr, but only for oss

**Problem:**  
When the FEFR modal's "Select" button is clicked, it opens the Global File No Modal to pick a source file. The requirement is to exclude "Commission Files" — but I need to know what defines a Commission File in the database.

**Please answer:**  
What makes a file a "Commission File"? Is it:
- (a) Records in `mls_file_no` where `source` or a status column is flagged as commissioned?  
- (b) File numbers that already exist in `oss_applications` (already processed through OSS)?  
- (c) Files with a specific prefix pattern (e.g., all `COM-`, `RES-`, or `ST-` prefixed files)?  
- (d) Something else? (d)
- the file numbers with CON, in the global file number selectorr, but only for oss

---

## Item 8 · Adhere to the Changes on the OSS Recommendation Template

**Problem:**  (**We already did this**)
The `print_recommendation.blade.php` print template was recently updated (serial number top-right, sig lines at 50%, labels un-bolded, location not uppercase). "Adhere to the changes" is unclear — I don't know if this means the recommendation **modal** should be updated to match, or if there's a separate visual/structural change needed on the template itself.

**Please answer:**  
- Should the **recommendation modal** (`recommendation-modal.blade.php`) visually mirror those print template changes (e.g., un-bold the labels inside the modal too)?  
- Or is this asking for something else — like an additional field, a section reorder, or a print preview feature inside the modal?  
- Or does "adhere" simply mean: make sure the modal's **Save** action correctly captures all the fields that appear on the updated print template?

---

## Item 10 · Map TP No, Plot No & LGA for Transfer of Title Records in the OSS Table

**Problem:**  
For Transfer of Title rows created via FEFR, the TP No, Plot No, and LGA columns may currently be blank. I know these values need to be populated, but I'm not sure which data source to pull them from.

**Please answer:**  
When saving a Transfer of Title record via FEFR, where should the TP No, Plot No, and LGA come from?   (a)
- (a) From the **source file's PRA history** (the mother OP record)?  yes
- (b) From the **instrument_capture** record linked to the source file?  
- (c) From what the **user types** into the FEFR form (should those fields be added to the FEFR modal)?  
- (d) From another source?

---

## Item 15 · OP Details Popup Should Display All 3 Transactions "Under the Same Line"

**Problem:**  
The `showTempFileDetailsByPropId()` popup currently shows each PRA transaction as a **separate stacked card** (one below the other). "Under the same line" could mean several different layouts.

**Please answer:**  
How should the 3 transactions be displayed?  
- (a) **Side by side horizontally** — 3 columns in a single row (like a 3-column grid)? yes  
- (b) **Compact table rows** — each transaction on one line in a table?  
- (c) **Something else** — please describe the layout you have in mind?

---

## Item 17 · Export for OSS & Land File Commissioning

**Problem:**  
The note says "Export for OSS & Land FileCommully (File Commissioning)". I understand this is an export feature but need specifics before building it.

**Please answer:**  
1. **What data** should be exported — all columns in the OSS table, or a specific subset?  **one ones in the view table**
2. **What format** — Excel (.xlsx), CSV, or PDF?  
3. **Where** does the export button go — on the OSS table page toolbar? A separate export page?  **CSV, or PDF**
4. **"Land & OSS"** — should the same export button also appear on the main Land applications page, or only on the OSS page?

---

*Please fill in your answers above and return this file. Items in [OSS_TODO_READY_TO_IMPLEMENT.md](OSS_TODO_READY_TO_IMPLEMENT.md) will proceed in parallel.*
