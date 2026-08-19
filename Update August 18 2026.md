# Update — August 18, 2026

Client update list. Status column reflects work in this repo as of 2026-08-19.

| # | Item | Area | Status |
|---|---|---|---|
| 1 | Direction popup then FileNo popup; pre-fill the Related FileNo section; flag self-reference | File Indexing | Done (not browser-tested) |
| 2 | Auto-trigger the bill on contravention | SPAS | Not started |
| 3 | Auto-trigger the second serve once the first-serve SMS has been sent | SPAS | Not started |
| 4 | Bulk SMS naming convention | SMS | Not started |
| 5 | Missing KLAES logos on the Special Assignment Memo | Special Assignment | Not started |
| 6 | Add "Department of Special Assignment" to the memo title | Special Assignment | Not started |
| 7 | Add "Department of Special Assignment" to the Change of Purpose Sheet title | Special Assignment | Not started |
| 8 | Landuse checker + FileNo validation on the Change of Purpose issue sheet | Change of Purpose | Not started |
| 9 | RofO Duplicate / Triplicate copy outlook | RofO Print | Done (not print-tested) |
| 10 | File Back Cover workflow differs between PageTyping and Doc-WARE edit | Scanning | Not started |
| 11 | Add "Reassign Scanned File" to the PageTyping and Doc-WARE action menus | Scanning | Not started |
| 12 | Master Edit / Edit Mode button gating all Doc-WARE edit actions | Doc-WARE | Done (not browser-tested) |
| 13 | Master Folder for Duplex Parcel Update | Parcel Update | Not started |
| 14 | Link / Unlink File Indexing action menu on Scan Upload, PageTyping and Doc-WARE | Scanning | Not started |
| 15 | Move the RoT position under the File Information section | File Information | Not started |
| 16 | Show "RoT" in italics under the Instrument Type as well as in the comment section | File Information | Not started |

---

## 1. Indexing interface — enter the FileNo, pre-fill Related FileNo

Update the Indexing interface for **Re-grant, Resettlement and Closed** so that choosing a
direction is immediately followed by a second popup asking for the file number that direction
points at. That number is pre-filled into the Related FileNo section, which remains available for
further update afterwards.

**Flow:** tick the status -> direction popup (e.g. *Re-granted From* / *Re-granted To*) -> FileNo
popup -> the **global file number selector**, so the officer picks a real, existing file off the
registry tabs instead of typing a number. The picked number is written into the Related File
Number card. Closing the selector without applying leaves the number unset; it is optional.

**Validation:** if the file picked is the same as the file being indexed, the system flags it and
reopens the selector — a file cannot be re-granted, resettled or continued from itself.
The comparison ignores case and spacing and treats a trailing `(T)` temporary suffix as the same
physical file.

**Files involved:** `resources/views/fileindexing/addons/create_indexing.blade.php`,
`resources/views/fileindexing/addons/kangis_update_indexing.blade.php` — the title-status block is
duplicated byte-for-byte across the two, so both change together.
`resources/views/fileindexing/edit.blade.php` carries only the Related FileNo card and no title
status block, so it is not affected.

> An earlier inline-field version of this item (with the Withdrawals included) was built on
> 2026-08-19 and reverted in full; the popup flow above replaced it.

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

**What changed:** the Duplicate and Triplicate were forced fully monochrome by a
`grayscale(100%)` filter on `.content-wrapper`, plus overrides painting the badge and the copy
label black. That wrapper filter has been removed and the copies are now desaturated element by
element, because a CSS filter applies to the whole subtree and a descendant cannot opt out of it —
so the coat of arms and the red badge could not be given colour back any other way.

**Copy label colours (c and d):** each copy keeps its own colour as before — Original red
`#ff0000`, Duplicate blue `#0000ff`, Triplicate green `#008000`. What changed is that these were
being overridden to black on the office copies; that override is gone, so all three now actually
print in their own colour. (A first pass set both office labels to the header red `#c90202`; the
client asked for the original per-copy colours back.)

**Knock-on:** with the wrapper filter gone, the ornate border frame also prints in colour on the
office copies. The frame is a `border-image`, so it cannot be desaturated independently of its
element. Office-copy body text still reads black, and the security paper is still desaturated so
the copies print on plain paper.

**Files:** `resources/views/land_rofos/templates/rofo_print.blade.php` and
`resources/views/land_rofos/templates/batch_rofo_print.blade.php` — both carry their own copy of
these rules and must stay in step. `rofo_print_old.blade.php` (legacy) and the SLTR RofO templates
were left alone; they have no Duplicate/Triplicate monochrome handling. Confirm whether the SLTR
RofO is in scope.

## 10. File Back Cover workflow

The workflow for File Back Cover under PageTyping differs from that of the Doc-WARE edit type.

## 11. Reassign Scanned File

Add **Reassign Scanned File** to the PageTyping and Doc-WARE action menus.

## 12. Doc-WARE — Master Edit

Master Edit should control **all** the Edit buttons on Doc-WARE: the editing actions display only
once the user activates edit mode, so an **Edit Mode** button was added to turn it on.

**What changed:** the document viewer toolbar gained a `master-edit-toggle` button. The five
actions that change something — **Move to NR, Master Folder, Reassign, Edit Type, Quality
Control** — moved into a `master-edit-actions` group that is hidden until edit mode is on. Read-only
controls (page navigation, zoom, rotate-view) are always available.

- Off by default, and reset to off in `clearDocumentViewerData()` — every file opened starts locked.
- The button shows a padlock and reads "Edit Mode" when off; unlocked and red, reading "Editing On",
  when on.
- Switching it back off closes whatever editor was open — QC edit mode and the Edit Page
  Classification dialog — rather than leaving it stranded. `closeModal` in the classification module
  is exposed as `window.closeEditFileTypeDialog` for this.

**File:** `resources/views/filearchive/partials/document_viewer_modal.blade.php`.

**Note:** the "Edit Typing" button in `file_details_modal.blade.php` is already commented out, so the
viewer toolbar holds every live edit action on Doc-WARE. Master Edit is a UI gate, not a permission —
there is still no role check on the Doc-WARE views. Confirm whether it should also be role-gated, and
whether PageTyping should share the same switch (see item 10).

## 13. Duplex Parcel Update — Master Folder

The Master Folder for Duplex Parcel Update.

The Duplex feature itself (Update August 17 2026, item 5) is planned in
[docs/plans/DUPLEX_PARCEL_UPDATE.md](docs/plans/DUPLEX_PARCEL_UPDATE.md).

## 14. Link / Unlink File Indexing

Add an action menu for **Unlinking and Linking File Indexing** under Scan Upload, PageTyping and
Doc-WARE.

## 15. RoT position

Change the position of the RoT under the File Information section.

## 16. RoT under Instrument Type

Also add **"RoT"** in italics under the Instrument Type, in addition to the comment section.
