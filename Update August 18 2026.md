# Update — August 18, 2026

Client update list. Status column reflects work in this repo as of 2026-08-19.

| # | Item | Area | Status |
|---|---|---|---|
| 1 | Enter the FileNo at the title status and auto-backfill the Related FileNo section | File Indexing | Reverted (built, then undone on request) |
| 2 | Auto-trigger the bill on contravention | SPAS | Not started |
| 3 | Auto-trigger the second serve once the first-serve SMS has been sent | SPAS | Not started |
| 4 | Bulk SMS naming convention | SMS | Not started |
| 5 | Missing KLAES logos on the Special Assignment Memo | Special Assignment | Not started |
| 6 | Add "Department of Special Assignment" to the memo title | Special Assignment | Not started |
| 7 | Add "Department of Special Assignment" to the Change of Purpose Sheet title | Special Assignment | Not started |
| 8 | Landuse checker + FileNo validation on the Change of Purpose issue sheet | Change of Purpose | Not started |
| 9 | RofO Duplicate / Triplicate copy outlook | RofO Print | Not started |
| 10 | File Back Cover workflow differs between PageTyping and Doc-WARE edit | Scanning | Not started |
| 11 | Add "Reassign Scanned File" to the PageTyping and Doc-WARE action menus | Scanning | Not started |
| 12 | Master Edit should control all Edit buttons on Doc-WARE | Doc-WARE | Not started |
| 13 | Master Folder for Duplex Parcel Update | Parcel Update | Not started |
| 14 | Link / Unlink File Indexing action menu on Scan Upload, PageTyping and Doc-WARE | Scanning | Not started |
| 15 | Move the RoT position under the File Information section | File Information | Not started |
| 16 | Show "RoT" in italics under the Instrument Type as well as in the comment section | File Information | Not started |

---

## 1. Indexing interface — enter the FileNo, auto-backfill Related FileNo

Update the Indexing interface for **Re-grant, Resettlement, Withdrawal etc.** so the officer can
enter the FileNo of the file in question at the title status itself. That number auto-backfills
the Related FileNo section, which remains available for further update afterwards.

**Scope agreed:** Re-grant, Resettlement, Closed (already had a counterpart field) plus
Withdrawal (Application) and Withdrawal (Allocation). No Merger / Subdivision / DCIV.

**Behaviour agreed:** when a related FileNo is later edited or removed in the Related FileNo
section, the title-status counterpart *follows* the edit rather than clearing and forcing an
explicit re-choice.

**Files involved:** `resources/views/fileindexing/addons/create_indexing.blade.php`,
`resources/views/fileindexing/addons/kangis_update_indexing.blade.php` — the title-status block
is duplicated byte-for-byte across the two, so both must change together.
`resources/views/fileindexing/edit.blade.php` carries only the Related FileNo card and no title
status block, so it is not affected.

> Implemented on 2026-08-19 and then reverted in full at the client's request. Nothing from this
> item currently remains in the working tree.

## 2. SPAS — auto-trigger billing on contravention

Let all SPAS entries automatically trigger the bill once there is a contravention.

## 3. SPAS — auto-trigger the second serve

The second serve should fire automatically once the first-serve SMS has been sent.

## 4. Bulk SMS naming convention

- Ministry → **Kano MLPP**
- KANGIS → **KANGIS**

## 5. Special Assignment Memo — KLAES logos

The KLAES logos are missing from the Special Assignment Memo.

## 6. Special Assignment Memo — title

Add **"Department of Special Assignment"** to the title of the memo.

## 7. Change of Purpose Sheet — title

Add **"Department of Special Assignment"** to the title of the Change of Purpose Sheet.

## 8. Change of Purpose Sheet — landuse checker and FileNo validation

Add a landuse checker to the Change of Purpose Sheet Registry issue-sheet interface, and add
validation to the FileNos.

## 9. RofO Duplicate and Triplicate copies — outlook

- a. Coat of Arms in colour
- b. Red badge maintained on all copies
- c. "Duplicate" write-up in the same colour as the header
- d. "Triplicate" write-up in the same colour as the header

## 10. File Back Cover workflow

The workflow for File Back Cover under PageTyping differs from that of the Doc-WARE edit type.

## 11. Reassign Scanned File

Add **Reassign Scanned File** to the PageTyping and Doc-WARE action menus.

## 12. Doc-WARE — Master Edit

Master Edit should control **all** the Edit buttons on Doc-WARE.

## 13. Duplex Parcel Update — Master Folder

The Master Folder for Duplex Parcel Update.

## 14. Link / Unlink File Indexing

Add an action menu for **Unlinking and Linking File Indexing** under Scan Upload, PageTyping and
Doc-WARE.

## 15. RoT position

Change the position of the RoT under the File Information section.

## 16. RoT under Instrument Type

Also add **"RoT"** in italics under the Instrument Type, in addition to the comment section.
