# Duplex Parcel Update — Plan

**Source:** client sheet "Update August 17 2026" item 5 (plus items 6, 7, 8) and the
follow-up client conversation. Captured 2026-08-19. Supersedes the one-line
placeholder "Master Folder for Duplex Parcel Update" (item 13 of
`Update August 18 2026.md`).

**Status:** built 2026-08-19. Capture pipeline verified end to end; the final commit
step is code-complete but not yet run against live files (see §8).

---

## 1. The problem

Today the five parcel-update workflows (Change of Purpose, Subdivision, Merger,
Extension, Separation) are five separate applications. If one parcel needs three
things done to it, the officer runs three complete cycles — three captures, three
approvals, three memos, three commissionings, one after the other. Worse, each
cycle commissions real file numbers, so cycle 2 has to start from whatever cycle 1
produced, and the client gets three separate approvals for what is legally one
instruction.

The client wants **one instruction, one approval, one memo, one commissioning
event** — a "**Duplex**": a single record that carries several parcel updates in a
declared execution order, held on temporary numbers until the very end, then
committed in one shot.

> *"So even if there are 3 things, we do them one after the other. So now, because
> when you get approval, they get that approval altogether. And that also comes on
> the memo. So what they tick here will come on the memo in that order."*

---

## 2. Concept

- A Duplex is **its own register** — its own table and its own listing page, one
  row per duplex operation, with the component updates held as JSON on that row.
  It is not a flag scattered across the five existing tables.
  > *"I feel we should have this duplex as a table on its own… one row will
  > represent one duplex function, and then you can use your JSON to separate them."*
- Every file number produced inside a duplex before the final step is a
  **temporary holding number**. Nothing is commissioned, nothing is decommissioned,
  until the officer confirms the whole duplex in Land.
- The duplex carries a **Duplex ID** that threads every stage, every holding
  number and every resulting file number together.

---

## 3. Officer flow

### 3.1 Select and rank

A **Duplex** action opens the selector:

```
+-- Select Multiple Parcel Update ----+
|  [x] Change of Purpose         (3)  |
|  [x] Subdivision               (2)  |
|  [x] Merger                    (1)  |
|  [ ] Extension                      |
+-------------------------------------+
              [ Start Process ]
```

- Rank is **assigned automatically in the order the officer ticks**, and that rank
  is the execution order. First tick = 1, second = 2, third = 3.
- The rank badge is shown on the checkbox itself and colour-coded (1 red, 2 blue,
  3 …). Unticking re-numbers the rest.
- If the checkbox + badge proves awkward in practice, fall back to a per-row
  **dropdown** modelled on the existing HC/PS recommendation selector.
- Separation is not on the client's list. Decide whether it joins (§7, Q1).

### 3.2 Quantities

`Start Process` opens a second card asking **how many of each**: number of mergers,
number of subdivisions, number of change-of-purposes. Then `Continue Process`.

The counts are independent — the client's own example: subdivide one plot into
**four**, then change the purpose of only **two** of them. The pipeline must not
assume 1:1 between stages.

> *"…if he does subdivide into four, make two. So you subdivide into four, you have
> four originally, you have one plot. So you add into four? They change two."*

### 3.3 Run the stages in rank order

Each stage collects only what that stage needs, and each stage's output feeds the
next as **holding numbers**.

**Merger (rank 1 in the example)**
- Officer picks the source file numbers (file 1, 2, 3, 4 … from the global file
  selector).
- The system **automatically generates a temporary holding number** and pushes the
  record to Land for the next available number based on the serial of the files
  selected.
- The stage then shows the holding number plus a tracking ID.

**Subdivision (rank 2)**
- Input is the holding number produced by the merger, not a real file.
- Officer states the number of plots and the plot sizes (reuse the existing
  `num_plots` + `plot_sizes` range control).
- Holder names default to the mother's holder, but each child can be reassigned —
  in practice subdivided plots usually go to different people.
  > *"To ask for the name of the people, if they are not the default person. Most of
  > the time it's not the same person, they own-subdivide them."*
- Produces N holding numbers.

**Change of Purpose (rank 3)**
- Officer picks which of the subdivision children this applies to (2 of the 4 in
  the example). Unselected children are greyed out — nothing happens to them.
- The current land use is pre-filled from the mother; the officer picks the **new**
  land use.
- The system then fetches the available file numbers for that new land-use pool.
- At the end of this stage the **conveyance and the memo** are generated.

Stages that are not ticked never appear.

### 3.4 Hand-off to Land

Once the conveyance and memo are generated, the duplex appears **in Land as a
Duplex entry**, under commissioning, filterable/selectable as "Duplex" so duplex
rows do not sit mixed in with ordinary commissioning rows.

The Land view expands the duplex by Duplex ID and shows, per stage, the holding
numbers on the left and the file numbers they will become on the right:

```
Duplex ID: DPX-2026-0007

Merger        holding [ ...... ]  ->  new file [ ...... ]
Subdivision   holding [ ...... ]  ->  new file [ ...... ]   (1)
              holding [ ...... ]  ->  new file [ ...... ]   (2)
              holding [ ...... ]  ->  new file [ ...... ]   (3)
              holding [ ...... ]  ->  new file [ ...... ]   (4)
Change of     holding [ ...... ]  ->  new file [ ...... ]   (from subdivision 1)
Purpose       holding [ ...... ]  ->  new file [ ...... ]   (from subdivision 2)
```

### 3.5 Commit — one click

The officer confirms once and the system generates **all** the real file numbers
for the whole duplex in a single batch. Not one at a time.

> *"…click on Perfect. It will now generate — yes, this one, this one, this one,
> this one. I think this batch is also the best. Instead of one at a time, at once."*

The decommissioning prompt must state the full picture up front — the merger's
sources, the subdivision mother, and any intermediate holding files — **in
execution order**, and decommission them per the existing rules
(`docs/plans/PLOT_MANAGEMENT_WORKFLOW.md`): archive to `decommissioned_files` plus
`deprecated_records`, then remove from the active tables.

---

## 4. Data model (proposed)

| Table | Purpose |
|---|---|
| `duplex_parcel_updates` | One row per duplex. `duplex_id` (human ref), `status`, `stages` JSON (type + rank + counts), source file numbers, applicant, KNUPDA fields, approval/memo/conveyance timestamps, audit columns matching the existing parcel tables. |
| `duplex_parcel_update_stages` | One row per stage instance: `duplex_id`, `type`, `rank`, `status`, `input_holding_no`, `payload` JSON (plots, sizes, holders, new land use), `tracking_id`. |
| `duplex_parcel_update_files` | One row per file the duplex touches: `duplex_id`, `stage_id`, `role` (source / holding / result), `holding_no`, `final_file_no`, `prop_id`, `parent_prop_id`, decommission flag. |

Notes and constraints carried over from the existing module:

- Holding numbers are a **new concept** — the closest existing things are
  `plot_merger_applications.temp_file_no` and `land_temporary_file_numbers`, but
  neither is a chainable allocation. Decide whether holding numbers reuse the
  `(T)` temporary-number convention (which `FileLocationResolver` and
  `FileIndexingPropagationService` already understand) or get their own namespace.
  The `(T)` route inherits a lot of working plumbing and is the recommendation.
- prop_id / `parent_prop_id` / `related_fileno` must end up exactly as the
  single-workflow paths produce them, since Legal Search reconstructs lineage from
  them (`LegalSearchService::getSmeAllowedFileNos`). A duplex creates a **chain** of
  lineage (sources -> merged -> children -> renamed children), so `parent_prop_id`
  must point one level up at each hop, not all the way back to the original sources.
- Commissioning must go through `MlsFileNoController::generateBatch()` — do not
  write a second commissioning path. That method already handles subdivision,
  merger, extension and separation and was fixed in the 2026-07-10 audit
  (`docs/reports/kangis-alias/05-parcel-update-workflows-audit.md`); a parallel
  implementation would re-introduce every defect listed there.
- The intermediate holding files themselves get decommissioned at commit — they
  exist only to carry the chain.

---

## 5. Implementation phases

1. **Schema + models** — the three tables, models, Duplex ID allocator, and the
   holding-number allocator (decide `(T)` vs own namespace first).
2. **Selector + rank UI** — the Duplex action, ordered checkboxes with colour
   badges, quantities card, `Start Process` / `Continue Process`.
3. **Stage runner** — a driver that walks the stages in rank order, each stage
   reusing the existing capture form fields, writing holding numbers, never
   touching the registry.
4. **Approval, memo, conveyance** — one approval for the whole duplex; the memo
   lists the component updates **in the ticked order**. Conveyance uses the
   conveyance template (sheet item 7).
5. **Land duplex listing** — the expandable Duplex view under commissioning, with
   its own filter.
6. **Batch commit** — one transaction: allocate every final file number via
   `generateBatch`, wire the lineage chain, decommission sources and holding files,
   emit the PRA rows per stage type.
7. **Legal Search verification** — search each resulting file and confirm the full
   chain resolves and sibling units stay excluded.

---

## 6. Related items from the same client sheet

Not part of the duplex itself, but the same sheet, same module:

- **6.** Change the Application and Memo under Parcel Update (Subdivision etc).
- **7.** Add **Conveyance** to the action menu and use the Conveyance template.
- **8.** File Commissioning table edit should allow attaching Related FileNo /
  Old FileNo.
- **4.** Customer Type defaults to "Individual" in the File Commissioning interface.
- **11–13.** Special Assignment: Change of Purpose sheet card, what the
  Commissioner Memo is, and where the memo is generated.

---

## 7. Open questions for the client

1. Does **Separation** join the duplex selector? It is absent from the sketch but
   is a live workflow (and no separation has ever been commissioned in production).
2. Can the same update type appear **twice** in one duplex (e.g. two subdivisions
   at different ranks), or is each type at most once?
3. If a stage is rejected at approval, does the whole duplex fail, or can the
   officer re-run that one stage while the rest hold?
4. Who approves a duplex — the same authority as the heaviest component, or a
   fixed authority regardless of composition?
5. Should the Land officer be able to **edit** stage details at the commissioning
   screen, or only confirm/reject the whole thing?
6. Holding numbers: reuse the `(T)` convention, or a distinct duplex namespace?


---

## 8. As built (2026-08-19)

Client answers that shaped the build:

| Q | Answer |
|---|---|
| Separation in the selector | Yes — all five types |
| Same type twice | Optional; ranks are 1..N and a type may repeat |
| Rejected stage | That stage alone reopens; the others hold their payloads and holding numbers |
| Approver | Same authority the single workflows already use |
| Land editing at commissioning | No — confirm or reject the whole duplex |
| Holding numbers | Distinct namespace: `DPX-2026-0007-H03` |

Also: the existing UI is untouched. The Duplex is **a page of its own**, and the Land
confirm/reject step lives on that page rather than as a tab on the commissioning screen —
partly because a duplex is often just one update, and the officer should still work here.

### Files

| Layer | Path |
|---|---|
| Schema | `database/migrations/2026_08_19_000000_create_duplex_parcel_update_tables.php` |
| Production SQL | `database/sql/2026_08_19_create_duplex_parcel_update_tables.sql` + `..._ledger.mysql.sql` + `verify_duplex_parcel_update_schema.sql` |
| Models | `app/Models/DuplexParcelUpdate{,Stage,File}.php` |
| Holding numbers | `app/Services/DuplexHoldingNumberService.php` |
| Commit | `app/Services/DuplexCommitService.php` |
| Controller | `app/Http/Controllers/Deeds/ParcelUpdate/DuplexParcelUpdateController.php` |
| Views | `resources/views/deeds/parcel_update/duplex/` (index, wizard, commission, js, 3 print templates) |
| Routes | `routes/app3.php` — `duplex-parcel-update.*`, 18 routes |
| Sidebar | one entry below the Parcel Update items in `lands.blade.php` |

### How the chain actually resolves at commit

Holding numbers are a **planning device**. At commit, stages run in rank order and each one
consumes the *real* file numbers the previous stage just produced, so by the time a stage runs
its input is always a real registry file — which is exactly what the commissioning engine
expects. Each stage materialises a row in the matching existing application table
(`plot_subdivision_applications`, `plot_merger_applications`, …), tagged
`[Duplex DPX-… · stage N]`, because that is where `generateBatch` reads its lineage from.

Decommissioning then falls out for free: each stage retires its own input, so the original
sources go with stage 1 and every intermediate file is retired by the stage that consumes it.

### Verified

- Migration applied; all three tables confirmed on **sqlsrv** via the catalog, not the ledger.
- 3-stage duplex (Merger → Subdivision ×4 → CoP on 2 of 4) captured end to end;
  holding chain came out `H01` → `H02..H05` → `H06..H09`.
- **Registry untouched by capture** — `fileNumber`, `file_indexings`, `mls_file_no`, `pra`,
  `decommissioned_files` row counts unchanged.
- Holding namespace clean — no `DPX-…-Hnn` value present in any registry table.
- Gates hold: memo blocked without KNUPDA, send-to-land blocked without the conveyance.
- Test rows deleted by id afterwards.

### Not yet verified

The **commit** has not been run. Doing so would mint live file numbers and decommission real
files in the shared dev database, so it needs a supervised run against files chosen for the
purpose. Note also that no merger or separation has ever been commissioned in production, so a
duplex containing either exercises that path for the first time.
