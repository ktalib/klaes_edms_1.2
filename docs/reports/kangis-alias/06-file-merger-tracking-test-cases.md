# File Merger / Related-Files Tracking — Test Cases

**Feature:** File Tracking Sheet / File Movement History now shows the movement history of **all related files** in a parcel-update group (Change of Purpose, Subdivision, Merger, Extension, Separation, Temporary Files), and the **File Details** form shows a small **Related Files** panel when the selected file belongs to such a group.

**Data source:** the `file_merger` registry, materialised from existing lineage
(`php artisan file-merger:rebuild`). Numbers below are **real groups** in the current database
(113 groups / 414 files as of the last rebuild).

---

## How to test each surface

For any entry file number below:

1. **Related Files panel (File Details form)** — go to *Create File Tracker*, load the entry file
   through **Quick Search → Log File**. The indigo **Related Files** panel appears under the File
   No / logout warning, listing every file in the lineage with a **Decommissioned** (rose) or
   **Current** (green) badge; the loaded file is tagged **(this file)**. Files not in any group
   show no panel.
2. **File Movement History sheet (print)** — open the movement-history print for the entry file
   (`/filearchive/movement-history/print?file_number=<FILE>`). The sheet stitches every related
   file's movement log into one timeline: each decommissioned parent ends with a **FILE
   DECOMMISSIONING** row; each surviving child opens with a **FILE COMMISSIONING** row.
3. **On-screen tracker timeline** — the tracker card history weaves the same related-file
   movements + markers in chronologically.
4. **Artisan spot-check** — `php artisan file-merger:rebuild --file=<FILE>` prints the resolved
   group and roles.

---

## 1. Change of Purpose — 2 parents → 1 child
**Group:** `MRG-42F632EBE0C6`

- **Enter this (child / current):** `COM-2026-123`
- Decommissioned parents: `RES-2021-2872`, `RES-2021-2874`
- **Expect:** panel shows **2 Decommissioned + 1 Current**; both parents listed above the current file.

## 2. Plot Merger — 30 parcels → 1 (large merger)
**Group:** `MRG-8361D722B1C8`

- **Enter this (child / current):** `CON-RES-2022-596`
- Decommissioned parents: `CON-RES-2022-604` … `CON-RES-2022-656` (30 source files)
- **Expect:** **30 Decommissioned + 1 Current**. Good stress test for the panel + print sheet
  pagination.

## 3. Temporary-File Merger (TEMP files consolidated into one unit)
**Group:** `MRG-F69727161FFD` — *Merged into IND-2026-3 (Merger TEMP-440133)*

- **Enter this (child / current):** `IND-2026-3`
- Decommissioned parents: `IND-RC-1982-24`, `IND-2021-13`
- Other files in the unit: `IND-2026-13`, `IND-2026-4`, `RES-RC-1982-611`
- **Expect:** all six related files listed regardless of which one you load.

## 4. Plot Subdivision — 1 mother → many fragments
**Group:** `MRG-99998624DACF`

- **Enter this (any fragment):** `RES-2007-826`  *(or `RES-2007-790`, `RES-1986-2132`, …)*
- Decommissioned mother: `RES-2020-1545`
- Fragments (children): `RES-2007-826`, `LPCC-2022-58`, `RES-1986-2132`, `RES-1981-1208`,
  `RES-1986-1516`, `RES-1986-1258`, `RES-RC-1987-6`, `RES-2007-790`, `RES-2007-291`
- **Expect:** loading **any** fragment shows the mother + **all** sibling fragments.

## 5. Change of Purpose — 1 → many
**Group:** `MRG-BB59A7856AAB`

- **Enter this (child / current):** `CON-COM-2019-45`
- Decommissioned parent: `CON-AG-1982-143`
- Other children: `CON-AG-1982-130`, `MLKN 2959`, `CON-COM-2019-46`, `MLKN 2960`

## 6. Negative test — file NOT in any group
- **Enter:** any ordinary active file that was never part of a parcel update (e.g. a routine
  `RES-2015-####` you know has no lineage).
- **Expect:** **no** Related Files panel; the movement sheet shows only that file's own history.

---

## Notes
- **Ordering:** the **print sheet** enforces lineage order (parents → decommission → child
  commission → child history). The **on-screen** timeline sorts by real timestamp, so where a new
  file was commissioned before the old ones were formally decommissioned, the two surfaces can
  order those two events differently.
- The **File Decommissioning** rows intentionally do **not** display the decommissioning reason.
- To refresh groups after new parcel updates: `php artisan file-merger:rebuild` (add `--fresh` to
  rebuild from scratch). New mergers/subdivisions also refresh automatically at decommission time.
