# Legal Search — Client Requirements (Kunle Chat Summary)

**Source:** `chat.litcoffee` — voice transcript + WhatsApp messages, March 20 2026  
**Client:** Kunle  
**Developer:** Speaker 2 (iorkuakator)

---

## 1. Search Results — Cleanup Mode (Match / Drop / Remove)

When a file number search returns results across the 4 tabs (PRA, File History, Deed Registrations, CofO), the user needs a way to **clean up** the data before generating the final report.

### Flow

1. Results appear in the 4 tabs as normal (read-only by default).  
2. A toggle/switch enables **"Cleanup Mode"**.  
3. In Cleanup Mode:
   - **Checkboxes** appear on each row (left side) across all 4 tabs.
   - Three action buttons become enabled (they are **greyed out** until Cleanup Mode is active):

| Button | Action |
|--------|--------|
| **Match** | Assign an orphan record (no `prop_id`) to an existing group by setting its `prop_id` to the target value. |
| **Drop** | Remove selected transactions from a `prop_id` group **without deleting** — unlinks from the nest (sets `prop_id = NULL`) but the record stays active. |
| **Remove** | Soft-delete the record by setting `is_deleted = 1`. |

4. After Match/Drop/Remove, the results **refresh** to reflect the changes.

### Cross-table concern

> *"The issue is implementing across 4 tables without seeing all 4 at the same time."*

Kunle suggested a **"dumping ground"** — a unified staging area where cross-table operations can be done. The per-tab checkboxes would be for **fine-tuning within a single table**.

### Conflict popup

If 2+ transactions with **different `prop_id`s** are selected for Match/Drop/Remove at the same time, show a **confirmation popup** warning about the mismatch before proceeding.

---

## 2. Edit Button — Incomplete Transaction Details

An **Edit** button on each transaction row to let users fill in missing data.

> *"Some of those instruments no get clear details or dem no get at all."*

Likely an inline edit or modal to update fields like party names, dates, registration particulars, etc.

---

## 3. Arrange Mode — Reorder Transactions on Final View

Before generating the PDF/print report, the user needs to **manually reorder** transactions.

### Why

- Some transactions don't have registration dates/times.
- The auto-chronological sort may put things in the wrong order (e.g., an OP captured from lands would appear last when it should be first).

### Flow

1. On the **final preview** (the combined timeline before print), an **"Arrange"** button activates Arrange Mode.
2. In Arrange Mode, checkboxes appear.
3. User clicks rows in the desired order: first click = position 1, second click = position 2, etc. (**serialization from 1 to n**).
4. Alternatively, drag-and-drop could be used (Kunle was open to this; suggested reusing the page-stepping pattern from the existing codebase).
5. When satisfied, user clicks **Save/Confirm**, then generates the final PDF/print.

---

## 4. Layout Change — Three-Section Screen

Currently the file details view has **2 sections**:
- Left: File Information
- Right: Transaction History (4 tabs)

Kunle wants **3 sections**:
1. **File Information** (top-left or left column)
2. **4-tab Transaction History** (individual source tables — PRA, FH, Deed, CofO — with Cleanup Mode buttons)
3. **Timeline View** (combined chronological view of all transactions across all 4 tables — this is where the Arrange mode lives before generating the final print view)

The final print view should just be the clean output — no editing/arranging controls.

---

## Open Questions — RESOLVED

1. **Match — UI for selecting target `prop_id`:** Checkbox-based. User selects the orphan record(s) and then selects the target record (which has a `prop_id`). The checkbox approach was confirmed as preferred.
2. **Drop vs Remove — they ARE different:**
   - **Drop** = Unlink transaction(s) from a `prop_id` group/nest without deleting. Sets `prop_id = NULL` so the record becomes an orphan, but remains active and visible in future searches.
   - **Remove** = Soft-delete by setting `is_deleted = 1`. The record is excluded from results.
3. **"Dumping ground" = the combined results view itself.** NOT a separate table. Kunle clarified: *"The corrected records will automatically be edited (in the frontend) & corrected in their respective tables."* The "dumping ground" is simply the area where all records matching the search parameter are displayed together — i.e., the search results section that spans all 4 tabs. No separate staging table needed.
4. **Edit button — all fields are editable.** Full inline/modal edit of any field on a transaction row.
5. **Arrange mode — session-only drag-and-drop.** When the chronological sort by `transaction_date` doesn't produce the correct order, the user can drag-and-drop rows within the table to manually reorder. This is for the current print session — not persisted to DB.
6. **Timeline view — yes, same as Property Records timeline.** See [PROPERTY_TIMELINE_PLAN.md](PROPERTY_TIMELINE_PLAN.md). The timeline table should be placed **below** the Transaction History tabs and File Information card, as a third section on the file details page.
7. **4 buttons greyed out until Cleanup Mode is triggered:** Match, Drop, Remove, and Edit are all disabled by default. They only become active when the user toggles Cleanup Mode on.
